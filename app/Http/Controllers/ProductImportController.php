<?php

namespace App\Http\Controllers;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportController extends Controller
{
    private const SAMPLE_ROWS = 3;

    /**
     * Lee la primera fila (encabezados) y unas cuantas de muestra del Excel
     * subido, y lo deja guardado temporalmente para el paso de importación.
     */
    public function preview(Request $request)
    {
        $this->currentCompany($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $token = (string) Str::uuid();
        $path = "imports/{$token}." . $request->file('file')->getClientOriginalExtension();
        $request->file('file')->storeAs('', $path, 'local');

        $rows = $this->readRows(Storage::disk('local')->path($path), formatted: true);

        if (empty($rows)) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => __('The file has no rows.')], 422);
        }

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $sample = array_slice($rows, 1, self::SAMPLE_ROWS);

        return response()->json([
            'token' => $token,
            'headers' => $headers,
            'sample' => $sample,
            'row_count' => count($rows) - 1,
        ]);
    }

    /**
     * Procesa el archivo guardado en preview() según el mapeo de columnas
     * que el usuario definió (columna del Excel -> campo del producto).
     *
     * El import es una sincronización absoluta desde la planilla (igual que
     * "stock", que reemplaza el total, no lo suma) -- por eso el costo
     * importado fija average_cost directo, como "Fix cost", en vez de
     * recalcularlo con la fórmula de promedio ponderado (que es para
     * entradas puntuales, ver ProductController::storeStockEntry()).
     */
    public function import(Request $request)
    {
        
        set_time_limit(1800);

        $company = $this->currentCompany($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.*.column' => ['required', 'integer', 'min:0'],
            'mapping.*.target' => ['required', 'string', 'in:code,description,barcode,unit_code,tracks_inventory,stock,warehouse_stock,price,cost'],
            'mapping.*.price_type_name' => ['nullable', 'string', 'max:255'],
            'mapping.*.warehouse_name' => ['nullable', 'string', 'max:255'],
            'stock_mode' => ['nullable', 'string', 'in:overwrite,add'],
        ]);

        $stockMode = $data['stock_mode'] ?? 'overwrite';

        $path = $this->resolveImportPath($data['token']);
        abort_unless($path, 404);

        $rawRows = array_slice($this->readRows($path, formatted: false), 1);
        $formattedRows = array_slice($this->readRows($path, formatted: true), 1);
        $textTargets = ['code', 'description', 'barcode', 'unit_code', 'tracks_inventory'];

        $hasWarehouseMapping = collect($data['mapping'])->contains('target', 'warehouse_stock');
        $hasTracksInventoryMapping = collect($data['mapping'])->contains('target', 'tracks_inventory');

        $hasInventoryMapping = collect($data['mapping'])
            ->pluck('target')
            ->intersect(['tracks_inventory', 'stock', 'warehouse_stock'])
            ->isNotEmpty();

        $codeColumn = collect($data['mapping'])->firstWhere('target', 'code')['column'] ?? null;
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

            foreach ($data['mapping'] as $map) {
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

            $product = $existingProducts->get($codigo);

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

                if ($isAddMode) {
                    $unitCost = $cost ?? (float) ($product->average_cost ?? 0);
                    if ($addedUnassigned > 0) {
                        $this->logStockMovement($product, $addedUnassigned, 'import', $request, null, $unitCost);
                    }
                    foreach ($addedByWarehouse as $warehouseId => $qty) {
                        if ($qty > 0) {
                            $this->logStockMovement($product, $qty, 'import', $request, $warehouseId, $unitCost);
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
                Product::create($productData);
                $created++;
            }
        }

        Storage::disk('local')->delete("imports/{$data['token']}." . pathinfo($path, PATHINFO_EXTENSION));

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'skipped_reasons' => [
                'no_code' => $skippedReasons['no_code'] ?? 0,
                'no_description' => $skippedReasons['no_description'] ?? 0,
                'no_price' => $skippedReasons['no_price'] ?? 0,
            ],
        ]);
    }

    /**
     * Mismo registro de kardex que ProductController::storeStockEntry(),
     * para que el stock agregado desde una importación (modo "add") quede
     * trazable igual que una entrada manual.
     */
    private function logStockMovement(Product $product, float $quantity, string $reason, Request $request, ?string $warehouseId, float $unitCost): void
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
            'user_id' => (string) $request->user()->_id,
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

    private function resolveImportPath(string $token): ?string
    {
        foreach (['xlsx', 'xls', 'csv'] as $extension) {
            $relative = "imports/{$token}.{$extension}";
            if (Storage::disk('local')->exists($relative)) {
                return Storage::disk('local')->path($relative);
            }
        }

        return null;
    }

    /**
     * @param  bool  $formatted  false: valor numérico crudo de PHP (para columnas
     *     numéricas, evita que el separador de miles/decimales de PhpSpreadsheet
     *     -que usa coma de miles, al revés de Colombia- dañe los precios).
     *     true: valor tal como Excel lo muestra (para columnas de texto, que
     *     pueden depender del formato de celda, ej. ceros a la izquierda).
     */
    private function readRows(string $path, bool $formatted): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, $formatted, false);
    }
}
