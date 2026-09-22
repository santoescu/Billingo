<?php

namespace App\Services\Dian;

use App\Models\Company;

/**
 * Trae y normaliza los eventos RADIAN (acuse de recibo, recibo del bien/
 * servicio, aceptación expresa/tácita, inscripción como título valor, etc.
 * -- ver Resolución DIAN 000015 de 2021) de un documento ya procesado,
 * consultando GetDocumentInfo por su UUID/CUFE. A diferencia de GetStatus
 * (que necesita el trackId de un envío NUESTRO), GetDocumentInfo se consulta
 * por la identidad del documento y trae los eventos que CUALQUIERA de las
 * dos partes haya hecho en RADIAN, sea o no la empresa que está consultando.
 *
 * DianSoapClient::xmlToArray() colapsa un nodo XML repetido a un solo
 * objeto cuando solo aparece una vez (en vez de un arreglo de un elemento),
 * y a "" cuando el nodo viene vacío -- normalizeList() estandariza esas tres
 * formas a un arreglo simple siempre, para no repetir esa lógica en cada
 * llamador.
 */
class RadianEventsSyncService
{
    public function __construct(private DianSoapClient $client)
    {
    }

    /**
     * @return array{status: array<int, string>, events: array<int, array{codigo: ?string, descripcion: ?string, emisor: ?string, fecha: ?string, uuid: ?string}>, info: array{documents: array<int, array<string, mixed>>}}
     */
    public function fetch(Company $company, string $uuid): array
    {
        $info = $this->client->getDocumentInfo($company, $uuid);
        $document = $info['documents'][0] ?? [];

        $statusEntries = $this->normalizeList($document['Estado'] ?? null, 'KeyValueOfintstring');
        $status = array_values(array_filter(array_map(fn (array $entry) => $entry['Value'] ?? null, $statusEntries)));

        $eventEntries = $this->normalizeList($document['Eventos'] ?? null, 'Evento');
        $events = array_map(fn (array $event) => [
            'codigo' => $event['Codigo'] ?? null,
            'descripcion' => $event['Descripcion'] ?? null,
            'emisor' => $event['Emisor']['Nombre'] ?? null,
            'fecha' => $event['NumeroDocumento']['FechaFirma'] ?? $event['NumeroDocumento']['FechaEmision'] ?? null,
            'uuid' => $event['UUID'] ?? null,
        ], $eventEntries);

        // "info" viaja tal cual vino de la DIAN (sin normalizar) -- lo consume
        // window.renderDianInfo() en partials/dian-document-info.blade.php, el mismo render de
        // tarjetas que ya usaba documents/create.blade.php al validar un UUID de referencia.
        return ['status' => $status, 'events' => $events, 'info' => $info];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(mixed $node, string $childKey): array
    {
        if (! is_array($node) || ! isset($node[$childKey])) {
            return [];
        }

        $child = $node[$childKey];

        return array_is_list($child) ? $child : [$child];
    }
}
