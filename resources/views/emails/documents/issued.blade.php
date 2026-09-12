@php
    $documentTypeLabels = [
        '01' => __('Electronic sales invoice'),
        '02' => __('Electronic sales invoice (export)'),
        '03' => __('Electronic transmission instrument (type 03)'),
        '04' => __('Electronic sales invoice (type 04)'),
        '91' => __('Credit note'),
        '92' => __('Debit note'),
    ];
    $customer = $documento->payload['accounting_customer_party'] ?? [];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Helvetica, Arial, sans-serif; color: #1f2933; margin: 0; padding: 24px; background-color: #f9fafb;">
    <div style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 32px; border: 1px solid #e5e7eb;">
        <p style="font-size: 18px; font-weight: bold; margin: 0 0 4px;">{{ $company->name }}</p>
        <p style="font-size: 13px; color: #6b7280; margin: 0 0 24px;">{{ __('This is an automatic message with your document.') }}</p>

        <p style="font-size: 14px; margin: 0 0 8px;">
            {{ __('Hi :name,', ['name' => $customer['razon_social'] ?? __('customer')]) }}
        </p>
        <p style="font-size: 14px; line-height: 1.5; margin: 0 0 20px;">
            {{ __('We are sending you the :type with number :numeral, issued by :company. You will find the PDF and the signed XML attached to this email.', [
                'type' => $documentTypeLabels[$documento->tipo_documento] ?? __('document'),
                'numeral' => $documento->numeral,
                'company' => $company->name,
            ]) }}
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-size: 13px;">{{ __('Numeral') }}</td>
                <td style="padding: 6px 0; text-align: right; font-size: 13px; font-weight: bold;">{{ $documento->numeral }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-size: 13px;">{{ __('Issue date') }}</td>
                <td style="padding: 6px 0; text-align: right; font-size: 13px;">{{ $documento->issue_date?->format('Y-m-d') ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-size: 13px; border-top: 1px solid #e5e7eb;">{{ __('Total') }}</td>
                <td style="padding: 6px 0; text-align: right; font-size: 15px; font-weight: bold; border-top: 1px solid #e5e7eb;">{{ $documento->total_formatted }}</td>
            </tr>
        </table>

        <p style="font-size: 12px; color: #9ca3af; margin: 24px 0 0;">
            {{ __('This message was sent automatically, please do not reply to this email.') }}
        </p>
    </div>
</body>
</html>
