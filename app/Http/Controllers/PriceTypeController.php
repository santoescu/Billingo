<?php

namespace App\Http\Controllers;

use App\Models\PriceType;
use App\Models\Product;
use Illuminate\Http\Request;

class PriceTypeController extends Controller
{
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);
        $data = $this->validatedData($request);
        $data['company_id'] = (string) $company->_id;

        PriceType::create($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Price type')]),
        ]);

        return redirect()->route('products.index');
    }

    public function update(Request $request, string $priceType)
    {
        $company = $this->currentCompany($request);
        $priceType = PriceType::where('company_id', (string) $company->_id)->findOrFail($priceType);
        $priceType->update($this->validatedData($request));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Price type')]),
        ]);

        return redirect()->route('products.index');
    }

    public function destroy(Request $request, string $priceType)
    {
        
        set_time_limit(300);

        $company = $this->currentCompany($request);
        $priceType = PriceType::where('company_id', (string) $company->_id)->findOrFail($priceType);
        $priceTypeId = (string) $priceType->_id;

        Product::where('company_id', (string) $company->_id)
            ->where('extra_prices.price_type_id', $priceTypeId)
            ->pull('extra_prices', ['price_type_id' => $priceTypeId]);

        $priceType->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Price type')]),
        ]);

        return redirect()->route('products.index');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
        ]);
    }
}
