<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\DocumentoRecibido;
use App\Models\ThirdParty;
use InvalidArgumentException;
use RuntimeException;

class ReceivedDocumentIngestionService
{
    public function __construct(private ReceivedDocumentParser $parser)
    {
    }

    /**
     * Procesa un documento recibido y lo guarda como DocumentoRecibido si todo sale bien --
     * llamado tanto desde la subida manual (ver DocumentoRecibidoController::store()) como desde
     * el webhook de correo entrante de SES (ver SesInboundWebhookController), para no duplicar
     * las mismas validaciones (documento para esta empresa, no repetido, cupo de contrato
     * disponible) en dos lados.
     *
     * @param  Company  $company
     * @param  string  $filePath  Ruta local del archivo, ya en disco (UploadedFile::getRealPath()
     *                            para la subida manual, o un archivo temporal armado a partir de
     *                            un adjunto de correo para el webhook de SES).
     * @param  string  $extension  "xml" o "zip".
     * @param  string  $originalFilename  Nombre original del archivo -- para el campo file_name y
     *                                    para darle contexto a los errores de parseo/cupo (que
     *                                    todavía no tienen el numeral del documento a mano).
     * @param  string|null  $uploadedByUserId  _id del usuario que lo subió a mano, o null si vino
     *                                         por un canal automático (correo).
     * @return DocumentoRecibido
     *
     * @throws InvalidArgumentException Si el archivo no se pudo parsear, o el documento no le pertenece a la empresa, o ya se había subido antes.
     * @throws RuntimeException Si no hay cupo de contrato disponible para el módulo "receiving".
     */
    public function ingest(Company $company, string $filePath, string $extension, string $originalFilename, ?string $uploadedByUserId = null): DocumentoRecibido
    {
        try {
            $parsed = $this->parser->parseUploadedFile($filePath, $extension);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($originalFilename . ': ' . $e->getMessage());
        }

        $receptorNit = trim((string) ($parsed['payload']['accounting_customer_party']['identificacion'] ?? ''));
        if ($receptorNit !== trim((string) $company->identificacion)) {
            throw new InvalidArgumentException(__(':numeral: this document does not belong to :company, it belongs to identification :nit.', [
                'numeral' => $parsed['numeral'] ?: $originalFilename,
                'company' => $company->name,
                'nit' => $receptorNit ?: '—',
            ]));
        }

        if (DocumentoRecibido::where('company_id', (string) $company->_id)->where('uuid', $parsed['uuid'])->where('uuid', '!=', '')->exists()) {
            throw new InvalidArgumentException(__(':numeral: this document was already uploaded before (same UUID).', ['numeral' => $parsed['numeral'] ?: $originalFilename]));
        }

        try {
            $this->consumeContractQuota($company, 'receiving');
        } catch (RuntimeException $e) {
            throw new RuntimeException($originalFilename . ': ' . $e->getMessage());
        }

        $emisor = $parsed['payload']['accounting_supplier_party'];
        $proveedor = $this->resolveProveedor($company, $emisor);

        [$status, $statusMessage] = $this->resolveDianValidationStatus($parsed['dian_validation']);

        $payload = $parsed['payload'];
        $payload['lineas'] = $parsed['lineas'];
        $primerPago = $payload['payment_means_list'][0] ?? null;

        return DocumentoRecibido::create([
            'company_id' => (string) $company->_id,
            'proveedor_id' => $proveedor ? (string) $proveedor->_id : null,
            'uploaded_by' => $uploadedByUserId,
            'tipo_documento' => $parsed['tipo_documento'],
            'prefix' => $parsed['prefix'],
            'numeral' => $parsed['numeral'],
            'secuencial' => $parsed['secuencial'],
            'payload' => $payload,
            'xml' => $parsed['xml'],
            'pdf' => $parsed['pdf'],
            'file_name' => $originalFilename,
            'uuid' => $parsed['uuid'],
            'status' => $status,
            'status_message' => $statusMessage,
            'issue_date' => $parsed['issue_date'] ?: null,
            'due_date' => $parsed['due_date'] ?: null,
            'subtotal' => $parsed['subtotal'],
            'tax_total' => $parsed['tax_total'],
            'total' => $parsed['total'],
            'currency' => $parsed['currency'],
            'payment_means_id' => $primerPago['id'] ?? null,
            'payment_means_code' => $primerPago['codigo'] ?? null,
        ]);
    }

    /**
     * Busca un proveedor existente por identificación (mismo NIT que en emisión, sin importar
     * el rol que ya tenga) y le agrega el rol "proveedor" si no lo tenía; si no existe ninguno,
     * lo crea con los datos que vinieron en el XML. Si el documento no trae identificación (XML
     * mal formado), no crea nada y el documento queda sin proveedor enlazado.
     */
    private function resolveProveedor(Company $company, array $emisor): ?ThirdParty
    {
        $identificacion = $emisor['identificacion'] ?? '';

        if ($identificacion === '') {
            return null;
        }

        $proveedor = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identificacion', $identificacion)
            ->first();

        if ($proveedor) {
            $proveedor->update([
                'roles' => collect($proveedor->roles ?? [])->push('proveedor')->unique()->values()->all(),
            ]);

            return $proveedor;
        }

        return ThirdParty::create([
            'company_id' => (string) $company->_id,
            'identification_type' => '31',
            'identificacion' => $identificacion,
            'dv' => $emisor['dv'] ?: null,
            'person_type' => '2',
            'name' => $emisor['razon_social'] ?: $identificacion,
            'address' => $emisor['direccion'] ?: null,
            'city_code' => $emisor['ciudad_codigo'] ?: null,
            'department_code' => $emisor['departamento_codigo'] ?: null,
            'phone' => $emisor['telefono'] ?: null,
            'email' => $emisor['email'] ?: null,
            'roles' => ['proveedor'],
            'status' => 'active',
        ]);
    }

    /**
     * Reclama un documento contra el cupo del contrato de la empresa para el módulo
     * "receiving" -- mismo criterio que IssueDocumentService::consumeContractQuota() para
     * invoicing/pos/cotizaciones, uno por documento subido (no por lote).
     *
     * @throws RuntimeException Si la empresa no tiene contrato vigente para este módulo, o si ya no queda cupo.
     */
    private function consumeContractQuota(Company $company, string $module): void
    {
        $contract = $company->activeContractFor($module);

        if (! $contract) {
            throw new RuntimeException(__('This company has no active contract covering this module.'));
        }

        $contract->claimUsage($module, (string) $company->_id);
    }

    /**
     * Traduce la validación de la DIAN que ya viene en el AttachedDocument (ver
     * ReceivedDocumentParser::extractDianValidation()) al status/status_message que se guardan en
     * DocumentoRecibido -- mismo shape ("resumen"/"reglas") que usa DocumentoEmitido para el
     * mensaje de la DIAN, así ambas pantallas .show() lo pintan igual. Si el XML subido no traía
     * ninguna validación (era el documento suelto, sin el sobre AttachedDocument, o la DIAN
     * todavía no había respondido cuando se armó el correo), el documento queda pendiente sin
     * mensaje -- no es un error, solo no hay con qué confirmarlo todavía.
     *
     * @param  array|null  $dianValidation
     * @return array{0: int, 1: array|null} [DocumentoRecibido::STATUS_*, status_message].
     */
    private function resolveDianValidationStatus(?array $dianValidation): array
    {
        if (! $dianValidation) {
            return [DocumentoRecibido::STATUS_PENDING, null];
        }

        $status = $dianValidation['es_valido']
            ? DocumentoRecibido::STATUS_ACCEPTED
            : DocumentoRecibido::STATUS_PENDING;

        $statusMessage = [
            'resumen' => $dianValidation['response_description'] ?: $dianValidation['response_code'],
            'reglas' => $dianValidation['reglas'],
        ];

        return [$status, $statusMessage];
    }
}
