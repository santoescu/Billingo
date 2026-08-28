<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

        $sellers = $company->sellers()->orderBy('name')->get();
        $isAdmin = true;

        return view('pos.sellers', compact('company', 'sellers', 'isAdmin'));
    }

    public function store(Request $request)
    {
        $company = $this->currentCompany($request);

        abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

        $data = $this->validatedData($request);
        $data['company_id'] = (string) $company->_id;

        Seller::create($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Seller')]),
        ]);

        return redirect()->route('pos.sellers.index');
    }

    public function update(Request $request, string $seller)
    {
        $company = $this->currentCompany($request);

        abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

        $seller = Seller::where('company_id', (string) $company->_id)->findOrFail($seller);
        $seller->update($this->validatedData($request));

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Updated :name', ['name' => __('Seller')]),
        ]);

        return redirect()->route('pos.sellers.index');
    }

    public function destroy(Request $request, string $seller)
    {
        $company = $this->currentCompany($request);

        abort_unless(User::hasCompanyAdminAccess($company->membership->role, $company->membership->modules ?? []), 403);

        $seller = Seller::where('company_id', (string) $company->_id)->findOrFail($seller);
        $seller->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Seller')]),
        ]);

        return redirect()->route('pos.sellers.index');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
