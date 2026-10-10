<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Procesa un bloque de filas del Excel según el mapeo de columnas que el usuario definió
 * (columna del Excel -> campo del producto). No guarda estado entre bloques: el navegador manda
 * las filas por partes (ver ProductImportController::chunk()), así que cada petición es
 * independiente y puede caer en cualquier instancia del servidor.
 *
 * Sin "add", el import es una sincronización absoluta desde la planilla (igual que "stock", que
 * reemplaza el total, no lo suma) -- por eso el costo importado fija average_cost directo, como
 * "Fix cost", en vez de recalcularlo con la fórmula de promedio ponderado (que es para entradas
 * puntuales, ver ProductController::storeStockEntry()).
 */
class ProductImportProcessor
{
    private const REPORT_ROWS_PER_PDF = 300;

    /**
     * @param  array<int, array>  $rawRows  Valores crudos (numéricos sin formato) de cada fila.
     * @param  array<int, array>  $formattedRows  Valores tal como Excel los muestra, en el mismo orden.
     * @return array{created: int, updated: int, skipped: int, skipped_reasons: array<string, int>, report: array}
     */
    public function process(Company $company, array $rawRows, array $formattedRows, array $mapping, string $stockMode, string $userId): array
    {
        $textTargets = ['code', 'description', 'barcode', 'unit_code', 'tracks_inventory'];
        $hasWarehouseMapping = collect($mapping)->contains('target', 'warehouse_stock');
        $hasTracksInventoryMapping = collect($mapping)->contains('target', 'tracks_inventory');

        $hasInventoryMapping = collect($mapping)
            ->pluck('target')
            ->intersect(['tracks_inventory', 'stock', 'warehouse_stock'])
            ->isNotEmpty();

        $codeColumn = collect($mapping)->firstWhere('target', 'code')['column'] ?? null;
        $codesInFile = $codeColumn === null
            ? []
            : collect($formattedRows)
                ->map(fn ($row) => trim((string) ($row[$codeColumn] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
        $existingProducts = Product::where('company_id', (string) $company->_id)
            ->whereIn('code', $codesInFile)
            ->get()
            ->keyBy('code');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedReasons = [];
        $priceTypeCache = [];
        $warehouseCache = [];
        $report = [];

        foreach ($rawRows as $index => $rawRow) {
            $formattedRow = $formattedRows[$index] ?? [];
            $codigo = null;
            $descripcion = null;
            $barcode = null;
            $unitCode = null;
            $tracksInventory = false;
            $unassignedStock = 0.0;
            $cost = null;
            $prices = [];
            $warehouseStocks = [];

            foreach ($mapping as $map) {
                $isTextTarget = in_array($map['target'], $textTargets, true);
                $rawValue = $rawRow[$map['column']] ?? '';
                $value = trim((string) ($isTextTarget ? ($formattedRow[$map['column']] ?? '') : $rawValue));

                if ($value === '') {
                    continue;
                }

                $number = ! $isTextTarget && is_numeric($rawValue) ? (float) $rawValue : $this->parseColombianNumber($value);

                match ($map['target']) {
                    'code' => $codigo = $value,
                    'description' => $descripcion = $value,
                    'barcode' => $barcode = $value,
                    'unit_code' => $unitCode = $value,
                    'tracks_inventory' => $tracksInventory = in_array(mb_strtoupper($value), ['SI', 'SÍ', 'YES', '1', 'TRUE', 'X']),
                    'stock' => $unassignedStock = $number,
                    'warehouse_stock' => $warehouseStocks[$map['warehouse_name'] ?: __('Warehouse')] = $number,
                    'price' => $prices[$map['price_type_name'] ?: __('Price')] = $number,
                    'cost' => $cost = $number,
                    default => null,
                };
            }

            if (! $codigo) {
                $skipped++;
                $skippedReasons['no_code'] = ($skippedReasons['no_code'] ?? 0) + 1;

                continue;
            }

            $product = $existingProducts->get($codigo)
                ?? Product::where('company_id', (string) $company->_id)->where('code', $codigo)->first();

            if ($product) {
                $existingProducts->put($codigo, $product);
            }

            if (! $product && (! $descripcion || empty($prices))) {
                $skipped++;
                $skippedReasons[! $descripcion ? 'no_description' : 'no_price'] =
                    ($skippedReasons[! $descripcion ? 'no_description' : 'no_price'] ?? 0) + 1;

                continue;
            }

            $extraPrices = [];
            foreach ($prices as $typeName => $price) {
                if (! isset($priceTypeCache[$typeName])) {
                    $priceTypeCache[$typeName] = PriceType::firstOrCreate(
                        ['company_id' => (string) $company->_id, 'name' => $typeName]
                    );
                }

                $extraPrices[] = [
                    'price_type_id' => (string) $priceTypeCache[$typeName]->_id,
                    'price' => $price,
                ];
            }

            $newWarehouseEntries = [];
            foreach ($warehouseStocks as $warehouseName => $qty) {
                if (! isset($warehouseCache[$warehouseName])) {
                    $warehouseCache[$warehouseName] = Warehouse::firstOrCreate(
                        ['company_id' => (string) $company->_id, 'name' => $warehouseName]
                    );
                }

                $newWarehouseEntries[(string) $warehouseCache[$warehouseName]->_id] = [
                    'warehouse_id' => (string) $warehouseCache[$warehouseName]->_id,
                    'stock' => $qty,
                ];
            }

            /**
             * Si el archivo no trae la columna "Controla inventario",
             * mantiene lo que el producto ya tenía en vez de asumir "no" --
             * de lo contrario, cualquier import que solo toque stock/costo
             * (como el que genera el propio "Exportar a Excel" si no se
             * marcó esa columna) apagaba el control de inventario y dejaba
             * el stock en 0 sin que el archivo dijera nada al respecto.
             */
            if (! $hasTracksInventoryMapping && $product) {
                $tracksInventory = (bool) $product->tracks_inventory;
            }
            $tracksInventory = $tracksInventory || ! empty($newWarehouseEntries);

            $productData = [];
            if ($descripcion !== null) {
                $productData['description'] = $descripcion;
            }
            if ($barcode !== null) {
                $productData['barcode'] = $barcode;
            }
            if ($unitCode !== null) {
                $productData['unit_code'] = $unitCode;
            }
            if (! empty($extraPrices)) {
                $productData['unit_price'] = $extraPrices[0]['price'];
                $productData['extra_prices'] = $extraPrices;
            }
            $isAddMode = $product && $stockMode === 'add';
            if ($cost !== null && ! $isAddMode) {
                $productData['average_cost'] = $cost;
            }

            $addedUnassigned = 0.0;
            $addedByWarehouse = [];

            if ($hasInventoryMapping) {
                $existingEntries = $product
                    ? collect($product->warehouse_stocks ?? [])->keyBy('warehouse_id')->all()
                    : [];

                if ($isAddMode) {
                    /**
                     * "Sumar" en vez de "sobrescribir": lo que trae el
                     * archivo se ADICIONA al stock/bodega que ya tenía el
                     * producto, en vez de reemplazarlo -- igual que
                     * ProductController::storeStockEntry() (misma entrada
                     * de kardex, mismo recálculo de costo promedio
                     * ponderado con la cantidad realmente agregada).
                     */
                    $mergedEntries = $existingEntries;
                    foreach ($newWarehouseEntries as $warehouseId => $entry) {
                        $addedByWarehouse[$warehouseId] = (float) $entry['stock'];
                        $prevQty = (float) ($mergedEntries[$warehouseId]['stock'] ?? 0);
                        $mergedEntries[$warehouseId] = ['warehouse_id' => $warehouseId, 'stock' => $prevQty + $entry['stock']];
                    }
                    $addedUnassigned = $unassignedStock;
                    $totalStock = (float) ($product->stock ?? 0) + $addedUnassigned + array_sum($addedByWarehouse);
                } else {
                    $mergedEntries = $hasWarehouseMapping
                        ? array_merge($existingEntries, $newWarehouseEntries)
                        : $existingEntries;

                    $totalStock = $unassignedStock + array_sum(array_column($mergedEntries, 'stock'));
                }

                $productData['warehouse_stocks'] = array_values($mergedEntries);
                $productData['tracks_inventory'] = $tracksInventory;
                $productData['stock'] = $tracksInventory ? $totalStock : 0;

                if ($isAddMode && $cost !== null) {
                    $totalAdded = $addedUnassigned + array_sum($addedByWarehouse);
                    if ($totalAdded > 0) {
                        $oldQty = (float) ($product->stock ?? 0);
                        $oldAvg = (float) ($product->average_cost ?? 0);
                        $newQty = $oldQty + $totalAdded;
                        $productData['average_cost'] = $newQty > 0.00001
                            ? round((($oldQty * $oldAvg) + ($totalAdded * $cost)) / $newQty, 4)
                            : $cost;
                    }
                }
            }

            if ($product) {
                $product->update($productData);
                $updated++;
                $report[] = [
                    'code' => $codigo,
                    'description' => $product->description,
                    'action' => 'updated',
                    'stock_action' => ! $hasInventoryMapping ? 'none' : ($isAddMode ? 'added' : 'overwritten'),
                    'imported_stock' => $isAddMode ? $addedUnassigned + array_sum($addedByWarehouse) : ($hasInventoryMapping ? $unassignedStock + array_sum(array_column($newWarehouseEntries, 'stock')) : null),
                    'final_stock' => $hasInventoryMapping ? (float) $productData['stock'] : (float) ($product->stock ?? 0),
                ];

                if ($isAddMode) {
                    $unitCost = $cost ?? (float) ($product->average_cost ?? 0);
                    if ($addedUnassigned > 0) {
                        $this->logStockMovement($product, $addedUnassigned, 'import', $userId, null, $unitCost);
                    }
                    foreach ($addedByWarehouse as $warehouseId => $qty) {
                        if ($qty > 0) {
                            $this->logStockMovement($product, $qty, 'import', $userId, $warehouseId, $unitCost);
                        }
                    }
                }
            } else {
                
                $productData['code'] = $codigo;
                $productData['company_id'] = (string) $company->_id;
                $productData['barcode'] ??= null;
                $productData['unit_code'] ??= 'EA';
                $productData['warehouse_stocks'] ??= [];
                $productData['tracks_inventory'] ??= false;
                $productData['stock'] ??= 0;
                $productData['status'] = 'active';
                $existingProducts->put($codigo, Product::create($productData));
                $created++;
                $report[] = [
                    'code' => $codigo,
                    'description' => $productData['description'] ?? '',
                    'action' => 'created',
                    'stock_action' => 'new',
                    'imported_stock' => (float) $productData['stock'],
                    'final_stock' => (float) $productData['stock'],
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'skipped_reasons' => [
                'no_code' => $skippedReasons['no_code'] ?? 0,
                'no_description' => $skippedReasons['no_description'] ?? 0,
                'no_price' => $skippedReasons['no_price'] ?? 0,
            ],
            'report' => $report,
        ];
    }

    /**
     * Arma el reporte final con todas las filas acumuladas por el navegador, en un solo PDF.
     * DomPDF guarda todas las celdas de la tabla en memoria, así que con miles de filas en un solo
     * documento agota la memoria (error fatal que ni siquiera se puede capturar). Por eso se
     * renderiza en PDFs de REPORT_ROWS_PER_PDF filas y luego se unen con FPDI.
     *
     * @return string Contenido del PDF.
     */
    public function renderReport(Company $company, array $report, int $created, int $updated): string
    {
        $chunks = array_chunk($report, self::REPORT_ROWS_PER_PDF);

        $render = function (array $rows) use ($company, $created, $updated): string {
            $pdf = Pdf::loadView('products.import-report-pdf', [
                'company' => $company,
                'report' => $rows,
                'created' => $created,
                'updated' => $updated,
            ])->setPaper('letter', 'portrait');

            $output = $pdf->output();
            unset($pdf);
            gc_collect_cycles();

            return $output;
        };

        if (count($chunks) === 1) {
            return $render($chunks[0]);
        }

        $merged = new Fpdi();
        $merged->SetAutoPageBreak(false);

        foreach ($chunks as $rows) {
            $pageCount = $merged->setSourceFile(StreamReader::createByString($render($rows)));

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('S');
    }

    /**
     * Mismo registro de kardex que ProductController::storeStockEntry(),
     * para que el stock agregado desde una importación (modo "add") quede
     * trazable igual que una entrada manual.
     */
    private function logStockMovement(Product $product, float $quantity, string $reason, string $userId, ?string $warehouseId, float $unitCost): void
    {
        StockMovement::create([
            'company_id' => $product->company_id,
            'product_id' => (string) $product->_id,
            'warehouse_id' => $warehouseId,
            'type' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'balance_after' => $warehouseId ? $product->stockForWarehouse($warehouseId) : $product->unassigned_stock,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    /**
     * Convierte un número con formato colombiano (punto de miles, coma de
     * decimales, con o sin símbolo de moneda) a float.
     */
    private function parseColombianNumber(string $value): float
    {
        $clean = preg_replace('/[^\d,.\-]/', '', $value);

        return (float) str_replace(['.', ','], ['', '.'], $clean);
    }
}
