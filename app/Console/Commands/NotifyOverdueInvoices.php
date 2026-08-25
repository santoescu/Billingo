<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\Notification;
use Illuminate\Console\Command;

class NotifyOverdueInvoices extends Command
{
    protected $signature = 'invoices:notify-overdue';

    protected $description = 'Avisa a los administradores de cada empresa cuando una factura a crédito acaba de vencerse (una sola vez por factura)';

    /**
     * Corre diario (ver routes/console.php). Solo avisa la PRIMERA vez que
     * encuentra una factura a crédito, sin pagar, ya vencida -- el campo
     * overdue_notified_at evita que se repita el aviso cada día mientras
     * siga sin pagarse.
     */
    public function handle(): int
    {
        $overdueInvoices = DocumentoEmitido::where('payment_means_id', DocumentoEmitido::PAYMENT_MEANS_CREDIT)
            ->whereNull('paid_at')
            ->whereNull('overdue_notified_at')
            ->where('due_date', '<', now())
            ->get();

        $companiesCache = [];
        $notified = 0;

        foreach ($overdueInvoices as $invoice) {
            $companyId = (string) $invoice->company_id;
            $company = $companiesCache[$companyId] ??= Company::find($companyId);

            if (! $company) {
                continue;
            }

            Notification::notifyUsers(
                $company->administratorUserIds(),
                __('Overdue invoice'),
                __('Invoice :numeral for :total is overdue.', [
                    'numeral' => $invoice->numeral,
                    'total' => $invoice->total_formatted,
                ]),
                route('documents.show', ['documento' => $invoice->_id]),
            );

            $invoice->update(['overdue_notified_at' => now()]);
            $notified++;
        }

        $this->info("Notified {$notified} overdue invoice(s).");

        return self::SUCCESS;
    }
}
