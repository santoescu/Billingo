<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoEmitido;
use App\Services\Dian\IssueDocumentService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DocumentoController extends Controller
{
    /**
     * Emite un documento electrónico (factura, nota crédito o nota débito) para la
     * empresa dueña del token de API usado en la petición, y lo envía a la DIAN.
     */
    public function store(Request $request, IssueDocumentService $service)
    {
        $company = $request->attributes->get('company');

        try {
            $documento = $service->issue($company, $request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $this->sanitizeUtf8($e->getMessage())], 422);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $this->sanitizeUtf8($e->getMessage())], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not issue the document.')], 500);
        }

        return response()->json([
            'numeral' => $documento->numeral,
            'tipo_documento' => $documento->tipo_documento,
            'uuid' => $documento->uuid,
            'status' => $documento->status,
            'status_message' => $documento->status_message,
            'response' => $documento->response,
        ], $documento->status === DocumentoEmitido::STATUS_ACCEPTED ? 201 : 422);
    }

    /**
     * Limpia bytes que no son UTF-8 válido de un mensaje de excepción (p. ej. errores
     * del sistema operativo en el codepage local de Windows) para que siempre se pueda
     * serializar a JSON sin tumbar la respuesta.
     *
     * @param  string  $message  Mensaje original, posiblemente con bytes inválidos.
     * @return string Mensaje saneado, seguro para json_encode().
     */
    private function sanitizeUtf8(string $message): string
    {
        return mb_convert_encoding($message, 'UTF-8', 'UTF-8');
    }
}
