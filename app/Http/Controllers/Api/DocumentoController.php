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
     * Importa uno o varios documentos ya autorizados por la DIAN a partir de su UUID/CUFE,
     * para la empresa dueña del token de API usado en la petición -- mismo mecanismo que
     * IssueDocumentService::importByUuid() (regla 90): consulta el XML real con el
     * certificado de la empresa y arma el registro completo. Si el UUID ya existía, lo
     * actualiza con lo que la DIAN tenga en este momento, en vez de duplicarlo.
     *
     * "uuids" está topado a 35 por petición: cada UUID hace una llamada SOAP en vivo a la
     * DIAN, una tras otra, dentro del mismo request -- un lote más grande arriesga superar
     * el max_execution_time del servidor a la mitad, sin devolver respuesta alguna (el
     * caller no se entera de qué sí se alcanzó a importar).
     */
    public function importUuid(Request $request, IssueDocumentService $service)
    {
        $data = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:35'],
            'uuids.*' => ['string'],
        ]);

        $company = $request->attributes->get('company');
        $uuids = $data['uuids'];

        $results = [];

        foreach ($uuids as $uuid) {
            try {
                $result = $service->importByUuid($company, $uuid);
            } catch (Throwable $e) {
                report($e);
                $results[] = ['uuid' => $uuid, 'status' => 'error', 'message' => __('Could not import the document.')];

                continue;
            }

            $results[] = [
                'uuid' => $uuid,
                'status' => $this->translateImportStatus($result['status']),
                'message' => $result['message'],
                'documento' => $result['documento'] ? [
                    'id' => (string) $result['documento']->_id,
                    'numeral' => $result['documento']->numeral,
                    'tipo_documento' => $result['documento']->tipo_documento,
                    'uuid' => $result['documento']->uuid,
                    'total' => $result['documento']->total,
                ] : null,
            ];
        }

        return response()->json([
            'summary' => $this->summarizeImportResults($results),
            'results' => $results,
        ]);
    }

    /**
     * Cuenta cuántos UUID del lote quedaron en cada status ya traducido, para que el
     * caller no tenga que recorrer "results" a mano solo para saber cuántos se
     * importaron/actualizaron/fallaron.
     *
     * @param  array  $results  Resultados ya armados por importUuid(), con "status" ya en español.
     * @return array{total: int, importado: int, actualizado: int, no_encontrado: int, no_pertenece: int, cupo_agotado: int, error: int}
     */
    private function summarizeImportResults(array $results): array
    {
        $counts = ['importado' => 0, 'actualizado' => 0, 'no_encontrado' => 0, 'no_pertenece' => 0, 'cupo_agotado' => 0, 'error' => 0];

        foreach ($results as $result) {
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }

        return array_merge(['total' => count($results)], $counts);
    }

    /**
     * Traduce el "status" interno de IssueDocumentService::importByUuid() (en inglés,
     * pensado para comparar en código) al valor en español que se muestra en la
     * respuesta del API -- este proyecto responde siempre en español.
     *
     * @param  string  $status  "imported", "updated", "not_found", "mismatch", "quota_exceeded" o "error".
     * @return string Valor equivalente en español.
     */
    private function translateImportStatus(string $status): string
    {
        return match ($status) {
            'imported' => 'importado',
            'updated' => 'actualizado',
            'not_found' => 'no_encontrado',
            'mismatch' => 'no_pertenece',
            'quota_exceeded' => 'cupo_agotado',
            default => $status,
        };
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
