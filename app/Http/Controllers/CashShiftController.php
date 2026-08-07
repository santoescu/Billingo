<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Apertura, cierre y consulta de turnos de caja del módulo POS. El saldo
 * "esperado" al cerrar se calcula a partir de los CashMovement del turno
 * (ver CashMovement::signedAmount()), no de un contador aparte, para que
 * siempre cuadre con el detalle que se le muestra al cajero.
 */
class CashShiftController extends Controller
{
    /**
     * Abre un turno de caja pidiendo, además del saldo inicial, la
     * resolución con la que se van a numerar las ventas de este turno:
     * 'fv_resolution_id' (talonario) es siempre obligatoria -- puede haber
     * varias resoluciones 'FV' activas (p. ej. una por cajera), el cajero
     * elige la suya acá, una sola vez por turno, no en cada venta. Si la
     * empresa tiene el módulo de facturación electrónica activo y hay
     * resoluciones '01' disponibles, también se pide 'invoicing_resolution_id'
     * (con esa se emiten las ventas que el cajero marque como electrónicas
     * durante el turno).
     */
    public function store(Request $request, DocumentoEmitidoController $documentController)
    {
        $company = $this->currentCompany($request);

        $fvResolutions = $documentController->resolutionsFor($company, 'FV');
        $invoicingResolutions = $company->hasModule('invoicing')
            ? $documentController->resolutionsFor($company, '01')
            : collect();

        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'fv_resolution_id' => ['required', 'string'],
            'invoicing_resolution_id' => [$invoicingResolutions->isNotEmpty() ? 'required' : 'nullable', 'nullable', 'string'],
        ]);

        $fvResolution = $fvResolutions->first(fn (Resolution $r) => (string) $r->_id === $data['fv_resolution_id']);

        if (! $fvResolution) {
            return back()->withErrors(['message' => __('You must choose a valid sales invoice resolution.')]);
        }

        $invoicingResolution = null;
        if (! empty($data['invoicing_resolution_id'])) {
            $invoicingResolution = $invoicingResolutions->first(fn (Resolution $r) => (string) $r->_id === $data['invoicing_resolution_id']);

            if (! $invoicingResolution) {
                return back()->withErrors(['message' => __('You must choose a valid electronic invoice resolution.')]);
            }
        }

        $existing = CashShift::where('company_id', (string) $company->_id)
            ->where('user_id', (string) $request->user()->_id)
            ->open()
            ->first();

        if ($existing) {
            return back()->withErrors(['message' => __('You already have an open cash shift.')]);
        }

        $shift = CashShift::create([
            'company_id' => (string) $company->_id,
            'user_id' => (string) $request->user()->_id,
            'opening_balance' => $data['opening_balance'],
            'opened_at' => now(),
            'status' => CashShift::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
            'fv_resolution_id' => (string) $fvResolution->_id,
            'invoicing_resolution_id' => $invoicingResolution ? (string) $invoicingResolution->_id : null,
        ]);

        CashMovement::create([
            'company_id' => (string) $company->_id,
            'shift_id' => (string) $shift->_id,
            'type' => CashMovement::TYPE_APERTURA,
            'amount' => $data['opening_balance'],
            'reason' => __('Shift opening'),
            'user_id' => (string) $request->user()->_id,
        ]);

        return redirect()->route('pos.create');
    }

    public function show(Request $request, string $shift)
    {
        $company = $this->currentCompany($request);

        $cashShift = $this->findShift($company, $shift);

        $movements = CashMovement::where('shift_id', (string) $cashShift->_id)
            ->orderBy('created_at')
            ->get();

        $expectedSoFar = $cashShift->opening_balance + $movements->sum(fn (CashMovement $movement) => $movement->signedAmount());

        return response()->json([
            'shift' => $cashShift,
            'movements' => $movements,
            'expected_balance' => $expectedSoFar,
        ]);
    }

    public function close(Request $request, string $shift)
    {
        $company = $this->currentCompany($request);

        $cashShift = $this->findShift($company, $shift);

        if ($cashShift->status !== CashShift::STATUS_OPEN) {
            return back()->withErrors(['message' => __('This cash shift is already closed.')]);
        }

        $this->authorizeShiftClose($request, $company, $cashShift);

        $data = $request->validate([
            'closing_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $movements = CashMovement::where('shift_id', (string) $cashShift->_id)->get();
        $expectedBalance = $cashShift->opening_balance + $movements->sum(fn (CashMovement $movement) => $movement->signedAmount());

        $cashShift->update([
            'closing_balance' => $data['closing_balance'],
            'expected_balance' => $expectedBalance,
            'variance' => $data['closing_balance'] - $expectedBalance,
            'closed_at' => now(),
            'status' => CashShift::STATUS_CLOSED,
        ]);

        CashMovement::create([
            'company_id' => (string) $company->_id,
            'shift_id' => (string) $cashShift->_id,
            'type' => CashMovement::TYPE_CIERRE,
            'amount' => $data['closing_balance'],
            'reason' => __('Shift closing'),
            'user_id' => (string) $request->user()->_id,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Cash shift closed.'),
        ]);

        return redirect()->route('pos.create');
    }

    private function findShift($company, string $shiftId): CashShift
    {
        $shift = CashShift::where('company_id', (string) $company->_id)
            ->where('_id', $shiftId)
            ->first();

        abort_unless($shift, 404);

        return $shift;
    }

    /**
     * Solo el cajero dueño del turno o un administrador/owner del módulo
     * POS puede cerrarlo -- así un encargado puede cerrar la caja de un
     * cajero que ya se fue, pero un cajero cualquiera no puede cerrar la
     * caja de otro.
     */
    private function authorizeShiftClose(Request $request, $company, CashShift $shift): void
    {
        if ($shift->user_id === (string) $request->user()->_id) {
            return;
        }

        $membership = $company->membership;
        abort_unless(
            $membership && User::hasCompanyAdminAccess($membership->role, $membership->modules ?? []),
            403
        );
    }
}
