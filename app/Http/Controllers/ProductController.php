<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\DocumentoPos;
use App\Models\MeasurementUnit;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $products = $company->products()->orderBy('description')->get();
        $measurementUnits = MeasurementUnit::orderBy('descripcion')->get();
        $warehouses = $company->warehouses()->orderBy('name')->get();
        $priceTypes = $this->priceTypesOrDefault($company);

        return view('products.index', compact('products', 'measurementUnits', 'warehouses', 'priceTypes'));
    }

    /**
     * Detalle de un producto: todo lo que tiene en este momento (precios,
     * stock por bodega, costo promedio) y su kardex completo.
     */
    public function show(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);

        $warehouses = $company->warehouses()->orderBy('name')->get();
        $priceTypes = $this->priceTypesOrDefault($company);
        $measurementUnits = MeasurementUnit::orderBy('descripcion')->get();

        return view('products.show', compact('product', 'warehouses', 'priceTypes', 'measurementUnits'));
    }

    /**
     * Los precios de un producto se eligen siempre de un tipo de precio ya
     * creado (no hay un campo de "precio principal" aparte) -- para que
     * nunca se bloquee la creación de un producto por no tener ninguno
     * todavía, se crea uno por defecto la primera vez que hace falta.
     */
    private function priceTypesOrDefault(Company $company)
    {
        $priceTypes = $company->priceTypes()->orderBy('name')->get();

        if ($priceTypes->isEmpty()) {
            $priceType = PriceType::create(['company_id' => (string) $company->_id, 'name' => __('Price')]);
            $priceTypes = collect([$priceType]);
        }

        return $priceTypes;
    }

    public function store(Request $request)
    {
        $company = $this->currentCompany($request);
        $data = $this->validatedData($request);
        $data['company_id'] = (string) $company->_id;

        $product = Product::create($data);

        if ($product->tracks_inventory) {
            $validatedCost = $request->validate([
                'initial_unit_cost' => 'nullable|numeric|min:0',
            ]);
            $initialUnitCost = (float) ($validatedCost['initial_unit_cost'] ?? 0);

            if ($initialUnitCost > 0 && (float) $product->stock > 0) {
                $this->applyWeightedAverageEntry($product, 0.0, (float) $product->stock, $initialUnitCost);
                $product->save();
            }

            $this->logStockChanges($product, [], 0.0, $request, 'initial');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Product')]),
        ]);

        return redirect()->route('products.index');
    }

    public function update(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);

        $previousWarehouseStocks = $product->warehouse_stocks ?? [];
        $previousTotal = (float) ($product->stock ?? 0);

        $data = $this->validatedData($request);
        $product->update($data);

        if ($product->tracks_inventory) {
            $this->logStockChanges($product, $previousWarehouseStocks, $previousTotal, $request, 'adjustment');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Product')]),
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Historial de movimientos (kardex) de un producto, para el panel de
     * consulta -- se sirve por AJAX en vez de venir horneado en la carga
     * inicial de /products porque, a diferencia de precios/bodegas (siempre
     * pocos por producto), el historial de movimientos crece sin límite con
     * el tiempo.
     */
    public function kardex(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);

        $movements = $product->stockMovements()->orderByDesc('created_at')->limit(200)->get();

        $warehousesById = $company->warehouses()->get()->keyBy(fn ($warehouse) => (string) $warehouse->_id);
        $userIds = $movements->pluck('user_id')->filter()->unique()->values()->all();
        $usersById = User::whereIn('_id', $userIds)->get()->keyBy(fn ($user) => (string) $user->_id);

        // Una salida por venta guarda 'document:<numeral>' en "reason" -- para
        // poder linkear al .show de esa venta hay que resolver ese numeral
        // contra documentos_pos (venta de POS) O documentos_emitidos (factura
        // electrónica directa, sin pasar por POS), sin saber de antemano cuál
        // de los dos es. Se resuelve en batch (no una consulta por movimiento).
        $saleNumerals = $movements
            ->map(fn (StockMovement $m) => str_starts_with($m->reason ?? '', 'document:') ? substr($m->reason, strlen('document:')) : null)
            ->filter()
            ->unique()
            ->values();

        $documentosPosByNumeral = DocumentoPos::where('company_id', (string) $company->_id)
            ->whereIn('numeral', $saleNumerals)
            ->get()
            ->keyBy('numeral');

        $documentosEmitidosByNumeral = DocumentoEmitido::where('company_id', (string) $company->_id)
            ->whereIn('numeral', $saleNumerals)
            ->get()
            ->keyBy('numeral');

        return response()->json([
            'movements' => $movements->map(function (StockMovement $movement) use ($warehousesById, $usersById, $documentosPosByNumeral, $documentosEmitidosByNumeral) {
                $saleUrl = null;

                if (str_starts_with($movement->reason ?? '', 'document:')) {
                    $numeral = substr($movement->reason, strlen('document:'));

                    if ($pos = $documentosPosByNumeral->get($numeral)) {
                        $saleUrl = route('pos.sales.show', ['sale' => (string) $pos->_id]);
                    } elseif ($emitido = $documentosEmitidosByNumeral->get($numeral)) {
                        $saleUrl = route('documents.show', ['documento' => (string) $emitido->_id]);
                    }
                }

                return [
                    'date_label' => $movement->created_at?->translatedFormat('j \d\e F, Y'),
                    'type' => $movement->type,
                    'quantity' => (float) $movement->quantity,
                    'unit_cost' => (float) ($movement->unit_cost ?? 0),
                    'total_cost' => (float) ($movement->total_cost ?? 0),
                    'balance_after' => (float) $movement->balance_after,
                    'warehouse_name' => $movement->warehouse_id
                        ? ($warehousesById->get($movement->warehouse_id)?->name ?? __('Unknown warehouse'))
                        : __('Unassigned'),
                    'reason_label' => $this->humanizeReason($movement->reason),
                    'user_name' => $movement->user_id ? $usersById->get($movement->user_id)?->name : null,
                    'url' => $saleUrl,
                ];
            })->values(),
        ]);
    }

    /**
     * Traduce el "reason" crudo que se guarda en cada StockMovement (ver
     * logStockMovement()/storeStockEntry()) a un texto legible para el
     * kardex. Los prefijos ('document:', 'entry:') pueden traer un sufijo
     * variable (número de factura, nota de la entrada) que se conserva tal
     * cual después del prefijo reconocido.
     */
    private function humanizeReason(?string $reason): string
    {
        $reason ??= '';

        return match (true) {
            $reason === 'initial' => __('Initial stock'),
            $reason === 'adjustment' => __('Manual adjustment'),
            $reason === 'entry' => __('Goods entry'),
            str_starts_with($reason, 'entry:') => __('Goods entry') . ' — ' . substr($reason, strlen('entry:')),
            str_starts_with($reason, 'document:') => __('Sale') . ' — ' . substr($reason, strlen('document:')),
            default => $reason,
        };
    }

    public function destroy(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);
        $product->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Product')]),
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Registra una entrada de mercancía CON costo (a diferencia del ajuste
     * libre de stock del formulario de edición, esta es la única acción que
     * captura explícitamente cuánto costó lo que entra, y por lo tanto la
     * única que recalcula average_cost con la fórmula de promedio ponderado).
     */
    public function storeStockEntry(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);

        abort_unless($product->tracks_inventory, 422, __('This product does not track inventory.'));

        $data = $request->validate([
            'warehouse_id' => 'nullable|string',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $quantity = (float) $data['quantity'];
        $unitCost = (float) $data['unit_cost'];
        // El select manda "" para "Sin asignar" (no ausencia del campo);
        // se normaliza a null para que quede igual que el resto de la app
        // (logStockChanges/discountInventory usan null para esa bodega
        // virtual, no string vacío).
        $warehouseId = $data['warehouse_id'] ?: null;

        $oldQty = (float) ($product->stock ?? 0);
        $this->applyWeightedAverageEntry($product, $oldQty, $quantity, $unitCost);
        $product->stock = $oldQty + $quantity;

        if ($warehouseId) {
            $stocks = $product->warehouse_stocks ?? [];
            $found = false;

            foreach ($stocks as &$entry) {
                if (($entry['warehouse_id'] ?? null) === $warehouseId) {
                    $entry['stock'] = (float) ($entry['stock'] ?? 0) + $quantity;
                    $found = true;

                    break;
                }
            }
            unset($entry);

            if (! $found) {
                $stocks[] = ['warehouse_id' => $warehouseId, 'stock' => $quantity];
            }

            $product->warehouse_stocks = $stocks;
        }

        $product->save();

        $reason = 'entry' . (! empty($data['note']) ? ':' . $data['note'] : '');
        $this->logStockMovement($product, $quantity, $reason, $request, $warehouseId, $unitCost);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Stock entry registered.'),
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Corrige el costo promedio de un producto DIRECTAMENTE, sin tocar
     * cantidades: pensada para productos que ya tenían stock antes de que
     * existiera el costeo por promedio ponderado (o que se cargaron sin
     * costo inicial), donde average_cost quedó en 0 y no hay ninguna
     * "entrada" real que recalcularlo. A propósito NO crea un StockMovement
     * (no hubo ningún movimiento físico de inventario) para no ensuciar el
     * kardex con una entrada/salida que nunca pasó.
     */
    public function correctAverageCost(Request $request, string $product)
    {
        $company = $this->currentCompany($request);
        $product = Product::where('company_id', (string) $company->_id)->findOrFail($product);

        abort_unless($product->tracks_inventory, 422, __('This product does not track inventory.'));

        $data = $request->validate([
            'average_cost' => 'required|numeric|min:0',
        ]);

        $product->average_cost = (float) $data['average_cost'];
        $product->save();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Average cost updated.'),
        ]);

        return redirect()->route('products.index');
    }

    /**
     * Registra en el kardex la diferencia entre el stock anterior y el actual,
     * bodega por bodega, más lo "sin asignar" (stock total - lo asignado a
     * bodegas) tratado como una bodega virtual (warehouse_id null).
     *
     * @param  array  $previousWarehouseStocks  "warehouse_stocks" del producto antes del cambio.
     * @param  float  $previousTotal  "stock" total del producto antes del cambio.
     */
    private function logStockChanges(Product $product, array $previousWarehouseStocks, float $previousTotal, Request $request, string $reason): void
    {
        $unassignedKey = '__unassigned__';

        $previous = collect($previousWarehouseStocks)->keyBy('warehouse_id')
            ->map(fn (array $entry) => (float) ($entry['stock'] ?? 0));
        $previous[$unassignedKey] = round($previousTotal - round($previous->sum(), 2), 2);

        $current = collect($product->warehouse_stocks ?? [])->keyBy('warehouse_id')
            ->map(fn (array $entry) => (float) ($entry['stock'] ?? 0));
        $current[$unassignedKey] = round((float) $product->stock - round($current->sum(), 2), 2);

        // Este camino (ajustes manuales desde el formulario general, sin un
        // costo capturado explícitamente) no mueve el promedio ponderado: un
        // aumento se trata como si entrara al costo promedio vigente (no
        // altera la fórmula) y una disminución simplemente se valoriza a ese
        // mismo promedio -- así average_cost queda estable ante correcciones.
        $unitCost = (float) ($product->average_cost ?? 0);

        foreach ($previous->keys()->merge($current->keys())->unique() as $key) {
            $delta = round(($current[$key] ?? 0) - ($previous[$key] ?? 0), 2);

            if (abs($delta) > 0.00001) {
                $this->logStockMovement($product, $delta, $reason, $request, $key === $unassignedKey ? null : $key, $unitCost);
            }
        }
    }

    /**
     * Registra un movimiento puntual en el kardex.
     * $quantity es la variación (positiva = entrada, negativa = salida).
     * $unitCost es el costo unitario de ESTE movimiento puntual: para
     * entradas es el costo real de esa compra, para salidas/ajustes es un
     * snapshot del costo promedio del producto en ese momento.
     */
    private function logStockMovement(Product $product, float $quantity, string $reason, Request $request, ?string $warehouseId, float $unitCost): void
    {
        StockMovement::create([
            'company_id' => $product->company_id,
            'product_id' => (string) $product->_id,
            'warehouse_id' => $warehouseId,
            'type' => $quantity >= 0 ? 'in' : 'out',
            'quantity' => abs($quantity),
            'unit_cost' => $unitCost,
            'total_cost' => round(abs($quantity) * $unitCost, 2),
            'balance_after' => $warehouseId ? $product->stockForWarehouse($warehouseId) : $product->unassigned_stock,
            'reason' => $reason,
            'user_id' => (string) $request->user()->_id,
        ]);
    }

    /**
     * Recalcula el costo promedio ponderado del producto por una ENTRADA de
     * $entryQty unidades a $entryUnitCost cada una. Solo se usa en entradas;
     * las salidas no modifican average_cost, solo lo leen (ver
     * IssueDocumentService::discountInventory()).
     *
     * @param  float  $oldQty  Stock del producto ANTES de esta entrada -- se
     * recibe explícito (no se lee de $product->stock) porque en algunos
     * llamadores $product->stock ya fue mutado al total nuevo antes de poder
     * recalcular el promedio.
     */
    private function applyWeightedAverageEntry(Product $product, float $oldQty, float $entryQty, float $entryUnitCost): void
    {
        $oldAvg = (float) ($product->average_cost ?? 0);
        $newQty = $oldQty + $entryQty;

        $product->average_cost = $newQty > 0.00001
            ? round((($oldQty * $oldAvg) + ($entryQty * $entryUnitCost)) / $newQty, 4)
            : $entryUnitCost;
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'code' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:50',
            'unit_code' => 'required|string|max:10',
            'extra_prices' => 'required|array|min:1',
            'extra_prices.*.price_type_id' => 'nullable|string',
            'extra_prices.*.price' => 'nullable|numeric|min:0',
            'tracks_inventory' => 'nullable|boolean',
            'stock' => 'nullable|numeric|min:0',
            'warehouse_stocks' => 'nullable|array',
            'warehouse_stocks.*.warehouse_id' => 'nullable|string',
            'warehouse_stocks.*.stock' => 'nullable|numeric|min:0',
        ]);

        $data['tracks_inventory'] = (bool) ($data['tracks_inventory'] ?? false);
        $data['stock'] = $data['tracks_inventory'] ? (float) ($data['stock'] ?? 0) : 0;

        $data['extra_prices'] = collect($data['extra_prices'])
            ->filter(fn (array $row) => ! empty($row['price_type_id']))
            ->map(fn (array $row) => [
                'price_type_id' => $row['price_type_id'],
                'price' => (float) ($row['price'] ?? 0),
            ])
            ->values()
            ->all();

        if (empty($data['extra_prices'])) {
            throw ValidationException::withMessages([
                'extra_prices' => __('You must add at least one price.'),
            ]);
        }

        $data['unit_price'] = $data['extra_prices'][0]['price'];

        $data['warehouse_stocks'] = $data['tracks_inventory']
            ? collect($data['warehouse_stocks'] ?? [])
                ->filter(fn (array $row) => ! empty($row['warehouse_id']))
                ->map(fn (array $row) => [
                    'warehouse_id' => $row['warehouse_id'],
                    'stock' => (float) ($row['stock'] ?? 0),
                ])
                ->values()
                ->all()
            : [];

        if ($data['tracks_inventory']) {
            $assigned = round(array_sum(array_column($data['warehouse_stocks'], 'stock')), 2);

            if ($assigned > $data['stock'] + 0.00001) {
                throw ValidationException::withMessages([
                    'stock' => __('The sum of warehouse quantities cannot exceed the total stock.'),
                ]);
            }
        }

        return $data;
    }
}
