<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);
        $data = $this->validatedData($request);
        $data['company_id'] = (string) $company->_id;

        Warehouse::create($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Warehouse')]),
        ]);

        return redirect()->route('products.index');
    }

    public function update(Request $request, string $warehouse)
    {
        $company = $this->currentCompany($request);
        $warehouse = Warehouse::where('company_id', (string) $company->_id)->findOrFail($warehouse);
        $warehouse->update($this->validatedData($request));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Warehouse')]),
        ]);

        return redirect()->route('products.index');
    }

    public function destroy(Request $request, string $warehouse)
    {
        // Con muchos productos afectados, quitar la bodega de cada uno con un
        // find()+save() por producto podía superar el límite de ejecución de
        // PHP (cada ida y vuelta a Atlas suma). Se hace en una sola operación
        // masiva ($pull) contra todos los productos de una vez.
        set_time_limit(300);

        $company = $this->currentCompany($request);
        $warehouse = Warehouse::where('company_id', (string) $company->_id)->findOrFail($warehouse);
        $warehouseId = (string) $warehouse->_id;

        // El stock que los productos tenían en esta bodega se pierde junto
        // con ella (no se reasigna a otra bodega ni se bloquea el borrado).
        Product::where('company_id', (string) $company->_id)
            ->where('warehouse_stocks.warehouse_id', $warehouseId)
            ->pull('warehouse_stocks', ['warehouse_id' => $warehouseId]);

        $warehouse->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Warehouse')]),
        ]);

        return redirect()->route('products.index');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);
    }
}
