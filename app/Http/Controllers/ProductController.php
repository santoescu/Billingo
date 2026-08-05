<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MeasurementUnit;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\StockMovement;
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

        foreach ($previous->keys()->merge($current->keys())->unique() as $key) {
            $delta = round(($current[$key] ?? 0) - ($previous[$key] ?? 0), 2);

            if (abs($delta) > 0.00001) {
                $this->logStockMovement($product, $delta, $reason, $request, $key === $unassignedKey ? null : $key);
            }
        }
    }

    /**
     * Registra un movimiento puntual en el kardex.
     * $quantity es la variación (positiva = entrada, negativa = salida).
     */
    private function logStockMovement(Product $product, float $quantity, string $reason, Request $request, ?string $warehouseId): void
    {
        StockMovement::create([
            'company_id' => $product->company_id,
            'product_id' => (string) $product->_id,
            'warehouse_id' => $warehouseId,
            'type' => $quantity >= 0 ? 'in' : 'out',
            'quantity' => abs($quantity),
            'balance_after' => $warehouseId ? $product->stockForWarehouse($warehouseId) : $product->unassigned_stock,
            'reason' => $reason,
            'user_id' => (string) $request->user()->_id,
        ]);
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

        // No hay un campo de "precio principal" aparte: el precio general
        // del producto (el que se usa por defecto en la tabla y al elegirlo
        // en una línea de documento) es el primero de la lista.
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
