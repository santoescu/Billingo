<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PaymentMeansCode;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        $paymentMethods = $company->paymentMethods()->orderBy('name')->get();
        $paymentMeansCodes = PaymentMeansCode::orderBy('medio')->get();

        return view('pos.payment-methods', compact('company', 'paymentMethods', 'paymentMeansCodes'));
    }

    public function store(Request $request)
    {
        $company = $this->currentCompany($request);
        $data = $this->validatedData($request);
        $data['company_id'] = (string) $company->_id;

        PaymentMethod::create($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Payment method')]),
        ]);

        return redirect()->route('pos.payment-methods.index');
    }

    public function update(Request $request, string $paymentMethod)
    {
        $company = $this->currentCompany($request);
        $paymentMethod = PaymentMethod::where('company_id', (string) $company->_id)->findOrFail($paymentMethod);
        $paymentMethod->update($this->validatedData($request));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Payment method')]),
        ]);

        return redirect()->route('pos.payment-methods.index');
    }

    public function destroy(Request $request, string $paymentMethod)
    {
        $company = $this->currentCompany($request);
        $paymentMethod = PaymentMethod::where('company_id', (string) $company->_id)->findOrFail($paymentMethod);
        $paymentMethod->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Payment method')]),
        ]);

        return redirect()->route('pos.payment-methods.index');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dian_payment_means_code' => ['nullable', 'string', 'max:10'],
        ]);
    }
}
