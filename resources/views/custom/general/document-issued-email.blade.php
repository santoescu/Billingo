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
    $isNote = in_array($documento->tipo_documento, ['91', '92'], true);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

                    {{-- Encabezado: wordmark en texto (no imagen) a propósito -- muchos
                         clientes de correo bloquean imágenes por defecto, y un logo roto se ve
                         peor que un wordmark bien tipografiado con el color de marca. --}}
                    <tr>
                        <td style="background-color: #166534; padding: 28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: #ffffff;">
                                        Billingo
                                    </td>
                                    <td align="right" style="font-size: 12px; font-weight: 600; color: #bbf7d0; text-transform: uppercase; letter-spacing: 0.08em;">
                                        {{ $isNote ? __('Note') : __('Invoice') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cintillo con el emisor real (la empresa que factura, no Billingo) --}}
                    <tr>
                        <td style="padding: 20px 32px 0;">
                            <p style="margin: 0; font-size: 12px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Issued by') }}</p>
                            <p style="margin: 2px 0 0; font-size: 17px; font-weight: 700; color: #111827;">{{ $company->name }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 32px 0;">
                            <p style="margin: 0 0 4px; font-size: 15px; color: #111827;">
                                {{ __('Hi :name,', ['name' => $customer['razon_social'] ?? __('customer')]) }}
                            </p>
                            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #4b5563;">
                                {{ __('We are sending you the :type with number :numeral, issued by :company. You will find a .zip attached to this email with the PDF and the signed XML.', [
                                    'type' => $documentTypeLabels[$documento->tipo_documento] ?? __('document'),
                                    'numeral' => $documento->numeral,
                                    'company' => $company->name,
                                ]) }}
                            </p>
                        </td>
                    </tr>

                    {{-- Tarjeta resumen: numeral/fecha a la izquierda, total grande a la
                         derecha -- es el dato que la persona busca primero al abrir el correo. --}}
                    <tr>
                        <td style="padding: 24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0; font-size: 11px; font-weight: 600; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Numeral') }}</p>
                                                    <p style="margin: 2px 0 0; font-size: 16px; font-weight: 700; color: #111827;">{{ $documento->numeral }}</p>
                                                </td>
                                                <td align="right">
                                                    <p style="margin: 0; font-size: 11px; font-weight: 600; color: #16a34a; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Total') }}</p>
                                                    <p style="margin: 2px 0 0; font-size: 22px; font-weight: 800; color: #166534;">{{ $documento->total_formatted }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px;">
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; border-bottom: 1px solid #f0f0f0;">{{ __('Issue date') }}</td>
                                    <td align="right" style="padding: 8px 0; color: #111827; font-weight: 600; border-bottom: 1px solid #f0f0f0;">{{ $documento->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280;">{{ __('Document type') }}</td>
                                    <td align="right" style="padding: 8px 0; color: #111827; font-weight: 600;">{{ $documentTypeLabels[$documento->tipo_documento] ?? __('document') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Aviso de adjuntos -- que quede claro de una que el PDF/XML vienen
                         pegados al correo, no hay que ir a buscarlos a ningún lado. --}}
                    <tr>
                        <td style="padding: 20px 32px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 12px; color: #6b7280;">
                                        📎 {{ __('The PDF and the signed XML will be attached automatically in a single .zip file.') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 28px 32px 24px;">
                            <p style="margin: 0; font-size: 11px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                {{ __('This message was sent automatically, please do not reply to this email.') }}
                            </p>
                        </td>
                    </tr>

                    {{-- Pie de marca: identifica a Billingo como la plataforma que procesa el
                         envío, sin competir visualmente con el emisor real de arriba. --}}
                    <tr>
                        <td style="background-color: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                                {{ __('Sent via') }} <span style="font-weight: 700; color: #166534;">Billingo</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
