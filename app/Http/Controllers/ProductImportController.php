<?php

namespace App\Http\Controllers;

use App\Models\PriceType;
use App\Models\Product;
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

        // La vista previa usa el valor formateado (tal como se ve en Excel:
        // "38.000,00", "001", etc.), que es más representativo para el usuario
        // que el número crudo.
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
     */
    public function import(Request $request)
    {
        // Con archivos grandes (miles de filas) el procesamiento puede tardar
        // más que el límite por defecto de PHP y cortarse a la mitad -- cuando
        // eso pasa, PHP devuelve una página de error HTML en vez de JSON, lo
        // que rompe el fetch() del frontend ("Unexpected token '<'").
        set_time_limit(1800);

        $company = $this->currentCompany($request);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.*.column' => ['required', 'integer', 'min:0'],
            'mapping.*.target' => ['required', 'string', 'in:code,description,barcode,unit_code,tracks_inventory,stock,warehouse_stock,price'],
            'mapping.*.price_type_name' => ['nullable', 'string', 'max:255'],
            'mapping.*.warehouse_name' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $this->resolveImportPath($data['token']);
        abort_unless($path, 404);

        // Se leen las dos versiones de cada celda: la cruda (número de PHP,
        // sin formatear) para columnas numéricas -- así no se corrompen los
        // separadores de miles/decimales -- y la formateada (tal como Excel
        // la muestra) para columnas de texto como "Código", que pueden
        // depender del formato de celda para conservar ceros a la izquierda
        // (ej. la celda vale 1 pero el formato "000" la muestra como "001").
        $rawRows = array_slice($this->readRows($path, formatted: false), 1);
        $formattedRows = array_slice($this->readRows($path, formatted: true), 1);
        $textTargets = ['code', 'description', 'barcode', 'unit_code', 'tracks_inventory'];

        // Si el mapeo trae al menos una columna de "stock en bodega", el Excel
        // es la fuente de verdad para ESAS bodegas puntuales -- las demás que
        // el producto ya tuviera (no mencionadas en el Excel) no se tocan.
        $hasWarehouseMapping = collect($data['mapping'])->contains('target', 'warehouse_stock');

        // Si el Excel no trae NINGUNA columna de inventario (ni "sin asignar",
        // ni bodega, ni "¿inventariable?"), no se toca nada de inventario al
        // actualizar un producto existente -- si no, se le pondría stock en 0
        // y se le apagaría "controla inventario" solo por actualizar el precio.
        $hasInventoryMapping = collect($data['mapping'])
            ->pluck('target')
            ->intersect(['tracks_inventory', 'stock', 'warehouse_stock'])
            ->isNotEmpty();

        // Se traen de una sola vez TODOS los productos cuyo código aparece en
        // el archivo, en vez de una consulta por fila -- con archivos de miles
        // de filas, cada ida y vuelta a Atlas suma y es lo que hacía que la
        // importación se pasara del límite de tiempo.
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
            $prices = [];
            $warehouseStocks = [];

            foreach ($data['mapping'] as $map) {
                $isTextTarget = in_array($map['target'], $textTargets, true);
                $rawValue = $rawRow[$map['column']] ?? '';
                $value = trim((string) ($isTextTarget ? ($formattedRow[$map['column']] ?? '') : $rawValue));

                if ($value === '') {
                    continue;
                }

                // Las columnas de texto (código, descripción, etc.) usan el
                // valor formateado (ver arriba), que ya conserva ceros a la
                // izquierda y evita notación científica -- las numéricas usan
                // el valor crudo para no arrastrar separadores mal leídos.
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
                    default => null,
                };
            }

            if (! $codigo) {
                $skipped++;
                $skippedReasons['no_code'] = ($skippedReasons['no_code'] ?? 0) + 1;

                continue;
            }

            $product = $existingProducts->get($codigo);

            // El precio (y la descripción) solo son obligatorios para CREAR un
            // producto nuevo -- si ya existe, este archivo puede traer solo
            // bodegas o solo precios sin que eso implique borrar lo demás.
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

            $tracksInventory = $tracksInventory || ! empty($newWarehouseEntries);

            // Solo se incluye en la actualización lo que este archivo realmente
            // trae -- así una importación que solo trae bodegas no borra la
            // descripción/precio/código de barras que el producto ya tenía,
            // y viceversa.
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

            if ($hasInventoryMapping) {
                // Se fusiona con las bodegas que el producto ya tuviera: solo
                // se sobrescriben las que vinieron en el Excel.
                $existingEntries = $product
                    ? collect($product->warehouse_stocks ?? [])->keyBy('warehouse_id')->all()
                    : [];
                $mergedEntries = $hasWarehouseMapping
                    ? array_merge($existingEntries, $newWarehouseEntries)
                    : $existingEntries;

                // El total siempre se recalcula a partir de TODAS las bodegas
                // que el producto queda teniendo (no solo las nuevas de esta
                // fila), más lo "sin asignar" -- si no, el total quedaba por
                // debajo de lo que en realidad sumaban las bodegas.
                $totalStock = $unassignedStock + array_sum(array_column($mergedEntries, 'stock'));

                $productData['warehouse_stocks'] = array_values($mergedEntries);
                $productData['tracks_inventory'] = $tracksInventory;
                $productData['stock'] = $tracksInventory ? $totalStock : 0;
            }

            if ($product) {
                $product->update($productData);
                $updated++;
            } else {
                // Para crear ya se validó arriba que hay descripción y al
                // menos un precio; lo demás usa un valor por defecto sensato.
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
