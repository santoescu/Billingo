<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;

class LoyaltyTransactionController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->currentCompany($request);

        return view('loyalty.transactions.index', compact('company'));
    }

    public function data(Request $request)
    {
        $company = $this->currentCompany($request);

        $transactions = LoyaltyTransaction::where('company_id', (string) $company->_id)
            ->with('customer')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $rows = $transactions->map(fn (LoyaltyTransaction $transaction) => [
            'id' => (string) $transaction->_id,
            'created_at' => $transaction->created_at?->setTimezone('America/Bogota')->format('Y-m-d H:i'),
            'customer_name' => $transaction->customer?->name ?: $transaction->customer?->identificacion,
            'mechanic' => LoyaltyProgram::mechanicLabel($transaction->mechanic),
            'direction' => LoyaltyTransaction::directionLabel($transaction->direction),
            'amount' => (float) $transaction->amount,
            'balance_after' => (float) $transaction->balance_after,
            'source_type' => LoyaltyTransaction::sourceLabel($transaction->source_type),
            'note' => $transaction->note,
        ]);

        return response()->json(['rows' => $rows]);
    }
}
