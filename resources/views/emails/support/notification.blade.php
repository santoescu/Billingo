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

                    {{-- Encabezado: mismo header verde con logo+wordmark que usa el correo de
                         documentos emitidos, sin nada más -- ningún emoji/ícono, que no siempre
                         se ve igual entre clientes de correo. --}}
                    <tr>
                        <td style="background-color: #166534; padding: 28px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align: middle; padding-right: 10px;">
                                        <img src="{{ asset('images/billingo-logo-email.png') }}" width="44" height="44" alt="Billingo" style="display: block; border: 0;">
                                    </td>
                                    <td style="vertical-align: middle; font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: #ffffff;">
                                        Billingo
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 32px 0;">
                            <p style="margin: 0; font-size: 20px; font-weight: 800; color: #111827; line-height: 1.3;">{{ $title }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 10px 32px 0;">
                            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #4b5563;">{{ $summary }}</p>
                        </td>
                    </tr>

                    {{-- Tarjeta del ticket con la MISMA insignia de estado que ya se ve en el
                         panel de tickets (ver SupportTicket::statusBadgeClasses()) -- ámbar para
                         abierto, azul para asignado, gris para cerrado -- no una paleta inventada
                         aparte. --}}
                    <tr>
                        <td style="padding: 20px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Ticket') }}</p>
                                                    <p style="margin: 4px 0 0; font-size: 15px; font-weight: 700; color: #111827;">{{ $subject }}</p>
                                                </td>
                                                <td align="right" style="vertical-align: top;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" style="background-color: {{ $softColor }}; border: 1px solid {{ $borderColor }}; border-radius: 999px;">
                                                        <tr>
                                                            <td style="padding: 4px 12px; font-size: 11px; font-weight: 700; color: {{ $accentColor }}; text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap;">
                                                                {{ $badgeLabel }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Botón con sombra (no plano) para que destaque como la acción principal
                         del correo -- siempre en el verde de marca, la insignia de arriba ya se
                         encarga de comunicar el estado. --}}
                    <tr>
                        <td style="padding: 24px 32px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #166534; border-radius: 8px; box-shadow: 0 4px 10px rgba(22,101,52,0.25);">
                                        <a href="{{ $viewUrl }}" style="display: inline-block; padding: 12px 24px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none;">
                                            {{ __('View ticket') }} →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 32px 24px;">
                            <p style="margin: 0; font-size: 11px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                {{ __('This message was sent automatically, please do not reply to this email.') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                                {{ __('Sent via') }} <a href="https://billingo.com.co" style="font-weight: 700; color: #166534; text-decoration: none;">Billingo</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
