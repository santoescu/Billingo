<?php

namespace App\Services\Dian;

use App\Models\CashShift;
use App\Models\Company;
use App\Models\DocumentoEmitido;
use App\Models\DocumentoPos;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Resolution;
use App\Models\StockMovement;
use App\Models\ThirdParty;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class IssueDocumentService
{
    private const FACTURA_CODES = ['01', '02', '03', '04', '05'];
    private const NOTA_CREDITO_CODE = '91';
    private const NOTA_DEBITO_CODE = '92';

    private const PENDING_STATUS_CODES = ['98', '66'];

    private const ALREADY_PROCESSED_RULE = 'Regla: 90';

    public function __construct(
        private DocumentJsonMapper $mapper,
        private UblDocumentBuilder $builder,
        private UblDocumentSigner $signer,
        private DianSoapClient $client,
        private DocumentTotalsCalculator $totals,
    ) {
    }

    /**
     * Arma, firma y envía un documento a la DIAN, y guarda el resultado. Si ya existe un
     * documento con el mismo emisor + tipo + numeral + ambiente: si quedó aceptado, no se
     * vuelve a procesar (se devuelve tal cual, ya está autorizado); si quedó rechazado (o
     * en error/pendiente), se reconstruye con los datos nuevos y se actualiza ese mismo
     * registro en vez de crear uno nuevo (puede que el cliente o los ítems hayan cambiado).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $request  Cuerpo de la petición ({"tipo_documento": "...", "document": {...}}).
     * @param  string|null  $userId  Quién emitió la factura (null si viene de la API con
     * token de empresa, sin usuario asociado) -- ver discountInventory().
     * @return DocumentoEmitido Documento guardado, con el resultado de la DIAN.
     */
    public function issue(Company $company, array $request, ?string $userId = null): DocumentoEmitido
    {
        $payload = $this->mapper->map($company, $request);
        $tipoDocumento = $payload['tipo_documento'];
        $ambiente = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;
        $resolution = $this->resolveResolution($company, $tipoDocumento, $ambiente, $payload['prefix'] ?? null);
        $numeral = $payload['numero_solicitado'] ?? throw new InvalidArgumentException('Debe indicar "document.Numeral" o "document.secuencial" (junto con "document.PREFIX") con el número del documento.');

        $existente = DocumentoEmitido::where('company_id', (string) $company->_id)
            ->where('tipo_documento', $tipoDocumento)
            ->where('numeral', $numeral)
            ->where('ambiente', $ambiente)
            ->first();

        if ($existente && $existente->status === DocumentoEmitido::STATUS_ACCEPTED) {
            return $existente;
        }

        $secuencial = $this->syncResolutionNumbering($resolution, $numeral);

        return $this->buildSignSubmitAndPersist(
            $company, $payload, $tipoDocumento, $resolution, $numeral, $secuencial, $ambiente, $existente,
            userId: $userId,
        );
    }

    /**
     * Crea una venta del POS: SIEMPRE en la colección "documentos_pos"
     * (totalmente separada de "documentos_emitidos" -- esa es solo para
     * documentos electrónicos reales), numerada con la Resolution manual
     * tipo 'FV' ("Factura de venta") que se eligió al abrir el turno de
     * caja. No se construye ni se firma ni se envía nada a la DIAN acá: solo
     * se calculan los totales (mismo cálculo que usaría la factura real, ver
     * DocumentTotalsCalculator) y se descuenta el inventario, igual que
     * cualquier venta. Si después el cajero decide emitirla como factura
     * electrónica (ver modal de resultado del POS), eso se hace aparte con
     * issuePosSaleElectronic(), una vez que esta venta ya existe.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $request  Mismo shape que espera issue() ({"document": {...}}).
     * @param  Resolution  $resolution  Resolución manual tipo 'FV' del turno.
     * @param  CashShift  $shift  Turno bajo el que se hace la venta (para poder emitirla electrónica después, ver issueElectronic()).
     * @return DocumentoPos Venta guardada.
     */
    public function issuePosSale(Company $company, array $request, Resolution $resolution, CashShift $shift): DocumentoPos
    {
        $payload = $this->mapper->map($company, $request);

        $calculo = $this->totals->calcularTotalesDocumento($payload['lineas'] ?? [], $payload['cargos_descuentos'] ?? []);

        $numero = $resolution->claimNextNumber();
        $numeral = trim($resolution->prefix . $numero, '-');

        $documento = DocumentoPos::create([
            'company_id' => (string) $company->_id,
            'shift_id' => (string) $shift->_id,
            'resolution_id' => (string) $resolution->_id,
            'prefix' => $resolution->prefix,
            'numeral' => $numeral,
            'secuencial' => (string) $numero,
            'cliente_id' => $payload['cliente_id'] ?? null,
            'payload' => $payload,
            'issue_date' => $this->resolveIssueDateTime($payload),
            'subtotal' => $calculo['totales']['tax_exclusive_amount'],
            'tax_total' => round($calculo['totales']['tax_inclusive_amount'] - $calculo['totales']['tax_exclusive_amount'], 2),
            'total' => $calculo['totales']['payable_amount'],
            'currency' => $payload['moneda'] ?? 'COP',
            'payment_means_id' => $payload['payment_means']['id'] ?? null,
            'payment_means_code' => $payload['payment_means']['codigo'] ?? null,
            'notes' => $payload['notas'] ?? null,
        ]);

        $this->syncProducts($company, $payload['lineas'] ?? []);
        $this->discountInventory($company, $payload['lineas'] ?? [], $numeral, (string) $shift->user_id);

        return $documento;
    }

    /**
     * Crea una cotización: colección "quotations", totalmente separada de
     * "documentos_pos"/"documentos_emitidos", numerada con la Resolution
     * manual tipo 'COT'. Igual que issuePosSale() calcula los totales con
     * el mismo DocumentTotalsCalculator que usaría la factura real, pero a
     * diferencia de esa NO descuenta inventario (una cotización no es una
     * venta todavía) y no depende de ningún turno de caja. Si después se
     * convierte en una venta de POS o en una factura electrónica, eso pasa
     * por las pantallas normales de esos módulos, precargadas con el
     * cliente/líneas de esta cotización (ver QuotationController::show()).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $request  Mismo shape que espera issue() ({"document": {...}}).
     * @param  Resolution  $resolution  Resolución manual tipo 'COT'.
     * @return Quotation Cotización guardada.
     */
    public function issueQuotation(Company $company, array $request, Resolution $resolution): Quotation
    {
        $payload = $this->mapper->map($company, $request);

        $calculo = $this->totals->calcularTotalesDocumento($payload['lineas'] ?? [], $payload['cargos_descuentos'] ?? []);

        $numero = $resolution->claimNextNumber();
        $numeral = trim($resolution->prefix . $numero, '-');

        $quotation = Quotation::create([
            'company_id' => (string) $company->_id,
            'resolution_id' => (string) $resolution->_id,
            'prefix' => $resolution->prefix,
            'numeral' => $numeral,
            'secuencial' => (string) $numero,
            'cliente_id' => $payload['cliente_id'] ?? null,
            'payload' => $payload,
            'issue_date' => $this->resolveIssueDateTime($payload),
            'subtotal' => $calculo['totales']['tax_exclusive_amount'],
            'tax_total' => round($calculo['totales']['tax_inclusive_amount'] - $calculo['totales']['tax_exclusive_amount'], 2),
            'total' => $calculo['totales']['payable_amount'],
            'currency' => $payload['moneda'] ?? 'COP',
            'notes' => $payload['notas'] ?? null,
        ]);

        $this->syncProducts($company, $payload['lineas'] ?? []);

        return $quotation;
    }

    /**
     * Arma, firma y envía un documento a la DIAN usando una resolución ya
     * elegida por el caller (en vez de resolverla por tipo_documento como
     * hace issue()).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $request  Mismo shape que espera issue() ({"document": {...}}).
     * @param  Resolution  $resolution  Resolución DIAN ya elegida/validada por el caller.
     * @param  bool  $skipInventoryDiscount  True si el inventario ya se descontó (venta del POS: se descontó al crear el DocumentoPos).
     * @param  string|null  $userId  Quién emitió la factura -- ver discountInventory().
     * @return DocumentoEmitido Documento electrónico emitido.
     */
    public function issueWithResolution(Company $company, array $request, Resolution $resolution, bool $skipInventoryDiscount = false, ?string $userId = null): DocumentoEmitido
    {
        $payload = $this->mapper->map($company, $request);
        $tipoDocumento = $payload['tipo_documento'];
        $ambiente = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        $numero = $resolution->claimNextNumber();
        $numeral = trim($resolution->prefix . $numero, '-');

        return $this->buildSignSubmitAndPersist(
            $company, $payload, $tipoDocumento, $resolution, $numeral, (string) $numero, $ambiente, null,
            skipInventoryDiscount: $skipInventoryDiscount,
            userId: $userId,
        );
    }

    /**
     * Emite la factura electrónica de una venta del POS ya creada: reusa
     * directamente "$documentoPos->payload" (ya mapeado cuando se creó la
     * venta, ver issuePosSale()) en vez de volver a mapear el request
     * original -- así el documento electrónico queda con exactamente los
     * mismos datos (cliente, líneas, totales) que ya se guardaron en la
     * venta, sin depender de que el caller reconstruya el request. El
     * inventario no se vuelve a descontar (ya se descontó al crear la
     * venta).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  DocumentoPos  $documentoPos  Venta ya creada (talonario).
     * @param  Resolution  $resolution  Resolución de facturación electrónica del turno.
     * @return DocumentoEmitido Documento electrónico emitido.
     */
    public function issuePosSaleElectronic(Company $company, DocumentoPos $documentoPos, Resolution $resolution): DocumentoEmitido
    {
        $payload = $documentoPos->payload;
        $tipoDocumento = $payload['tipo_documento'] ?? '01';
        $ambiente = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        $numero = $resolution->claimNextNumber();
        $numeral = trim($resolution->prefix . $numero, '-');

        return $this->buildSignSubmitAndPersist(
            $company, $payload, $tipoDocumento, $resolution, $numeral, (string) $numero, $ambiente, null,
            skipInventoryDiscount: true,
        );
    }

    /**
     * Construye, firma y envía el XML a la DIAN, y persiste el resultado --
     * extraído de issue() para que también lo use issueWithResolution()
     * (resolución ya elegida por el caller, sin resolverla por
     * tipo_documento). El descuento de inventario sigue viviendo acá (mismas
     * condiciones de siempre), pero se puede omitir explícitamente cuando ya
     * se descontó antes (ver $skipInventoryDiscount).
     */
    private function buildSignSubmitAndPersist(
        Company $company,
        array $payload,
        string $tipoDocumento,
        Resolution $resolution,
        string $numeral,
        string $secuencial,
        string $ambiente,
        ?DocumentoEmitido $existente,
        bool $skipInventoryDiscount = false,
        ?string $userId = null,
    ): DocumentoEmitido {
        $fechaEmision = $this->resolveIssueDateTime($payload);
        $fechaVencimiento = $this->resolveDueDate($payload);

        $buildPayload = $payload;
        $buildPayload['number'] = $numeral;
        $buildPayload['prefijo'] = $resolution->prefix;
        $buildPayload['issue_date'] = $fechaEmision->format('Y-m-d');
        $buildPayload['issue_time'] = $fechaEmision->format('H:i:sP');

        if (in_array($tipoDocumento, self::FACTURA_CODES, true)) {
            $buildPayload['clave_tecnica'] = $resolution->technical_key;
            $buildPayload['resolucion'] = [
                'numero' => $resolution->resolution_number,
                'prefijo' => $resolution->prefix,
                'rango_desde' => $resolution->range_from,
                'rango_hasta' => $resolution->range_to,
                'valido_desde' => $resolution->valid_from?->format('Y-m-d'),
                'valido_hasta' => $resolution->valid_to?->format('Y-m-d'),
            ];
        } else {
            $buildPayload['referencias'] = $this->resolveReferencias($company, $payload['referencias'] ?? []);
        }

        $unsignedXml = $this->builder->build($company, $buildPayload);
        $signedXml = $this->signer->sign($company, $unsignedXml);
        $parsedXml = $this->parseSignedXml($signedXml);
        $uuid = (string) $parsedXml->UUID;
        $totales = $this->extractTotals($parsedXml);

        $this->syncProducts($company, $payload['lineas'] ?? []);

        $documentoData = [
            'company_id' => (string) $company->_id,
            'tipo_documento' => $tipoDocumento,
            'resolution_id' => (string) $resolution->_id,
            'prefix' => $resolution->prefix,
            'numeral' => $numeral,
            'secuencial' => $secuencial,
            'cliente_id' => $payload['cliente_id'] ?? null,
            'payload' => $payload,
            'xml' => $signedXml,
            'uuid' => $uuid,
            'status' => DocumentoEmitido::STATUS_PENDING,
            'status_message' => null,
            'response' => null,
            'ambiente' => $ambiente,
            'issue_date' => $fechaEmision,
            'subtotal' => $totales['subtotal'],
            'tax_total' => $totales['tax_total'],
            'total' => $totales['total'],
            'currency' => $payload['moneda'] ?? 'COP',
            'payment_means_id' => $payload['payment_means']['id'] ?? null,
            'payment_means_code' => $payload['payment_means']['codigo'] ?? null,
            'referencias' => $buildPayload['referencias'] ?? null,
            'notes' => $payload['notas'] ?? null,
        ];

        if ($fechaVencimiento) {
            $documentoData['due_date'] = $fechaVencimiento;
        }

        if ($existente) {
            $existente->update($documentoData);
            $documento = $existente;
        } else {
            $documento = DocumentoEmitido::create($documentoData);
        }

        $fileName = $numeral . '.xml';
        $zipContent = $this->zipXml($fileName, $signedXml);

        try {
            $results = $this->client->sendBillSync($company, $fileName, $zipContent);
        } catch (RuntimeException $e) {
            $documento->update([
                'status' => DocumentoEmitido::STATUS_ERROR,
                'status_message' => ['resumen' => $e->getMessage(), 'reglas' => []],
            ]);

            return $documento;
        }

        if ($this->isAlreadyProcessedRule($results) && ! empty($results['xml_document_key'])) {
            if ($this->syncWithAlreadyProcessedDocument($company, $documento, $results['xml_document_key'])) {
                return $documento;
            }
        }

        if (in_array($results['status_code'] ?? null, self::PENDING_STATUS_CODES, true)) {
            try {
                $results = $this->client->getStatus($company, $uuid);
            } catch (RuntimeException $e) {
                
            }
        }

        $isPending = in_array($results['status_code'] ?? null, self::PENDING_STATUS_CODES, true);
        $isValid = $results['is_valid'] ?? false;

        $documento->update([
            'status' => match (true) {
                $isPending => DocumentoEmitido::STATUS_PENDING,
                $isValid => DocumentoEmitido::STATUS_ACCEPTED,
                default => DocumentoEmitido::STATUS_REJECTED,
            },
            'status_message' => $this->buildStatusMessage($results),
            'response' => $results['response_xml'] ?? $results['raw_response'] ?? null,
            'fecha_expedicion' => $isPending ? null : $this->resolveFechaExpedicion($results),
        ]);

        if (! $skipInventoryDiscount && ! $isPending && $isValid && in_array($tipoDocumento, self::FACTURA_CODES, true)) {
            $this->discountInventory($company, $payload['lineas'] ?? [], $numeral, $userId);
        }

        return $documento;
    }

    /**
     * Detecta si la respuesta de la DIAN trae la "Regla: 90" (documento ya
     * procesado antes) en su lista de mensajes de error.
     *
     * @param  array  $results  Resultado de DianSoapClient::sendBillSync().
     * @return bool True si la DIAN marcó la regla 90.
     */
    private function isAlreadyProcessedRule(array $results): bool
    {
        foreach ($results['error_messages'] ?? [] as $errorMessage) {
            if (str_contains($errorMessage, self::ALREADY_PROCESSED_RULE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sincroniza el documento con la versión que la DIAN ya tiene autorizada
     * (regla 90): trae el XML real con GetXmlByDocumentKey usando el
     * xml_document_key de la respuesta de SendBillSync, y reemplaza TODO el
     * registro con eso (uuid, xml, totales, payload completo -- cliente,
     * líneas, notas, medio de pago) -- sin volver a firmar ni reenviar nada.
     * Es la versión que quedó autorizada del lado de la DIAN, no
     * necesariamente la que armamos localmente con los datos de esta petición.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  DocumentoEmitido  $documento  Documento a sincronizar.
     * @param  string  $xmlDocumentKey  xml_document_key de la respuesta de SendBillSync.
     * @return bool True si se pudo traer el XML y sincronizar; false si hay que seguir el flujo normal.
     */
    private function syncWithAlreadyProcessedDocument(Company $company, DocumentoEmitido $documento, string $xmlDocumentKey): bool
    {
        try {
            $xmlInfo = $this->client->getXmlByDocumentKey($company, $xmlDocumentKey);
        } catch (RuntimeException $e) {
            Log::warning('GetXmlByDocumentKey falló al sincronizar un documento con regla 90.', [
                'company_id' => (string) $company->_id,
                'xml_document_key' => $xmlDocumentKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (empty($xmlInfo['response_xml'])) {
            Log::warning('GetXmlByDocumentKey respondió sin XML al sincronizar un documento con regla 90.', [
                'company_id' => (string) $company->_id,
                'xml_document_key' => $xmlDocumentKey,
                'xml_info' => $xmlInfo,
            ]);

            return false;
        }

        $parsedXml = $this->parseSignedXml($xmlInfo['response_xml']);
        $totales = $this->extractTotals($parsedXml);
        $dianPayload = $this->buildPayloadFromDianXml($parsedXml);
        $clienteId = $this->resolveClienteIdFromDianPayload($company, $dianPayload['accounting_customer_party']);

        $this->syncProducts($company, $dianPayload['lineas']);

        $payload = $documento->payload ?? [];
        $payload['moneda'] = $dianPayload['moneda'];
        $payload['issue_date'] = $dianPayload['issue_date'];
        $payload['issue_time'] = $dianPayload['issue_time'];
        $payload['notas'] = $dianPayload['notas'];
        $payload['accounting_customer_party'] = $dianPayload['accounting_customer_party'];
        $payload['payment_means'] = $dianPayload['payment_means'];
        $payload['lineas'] = $dianPayload['lineas'];
        $payload['cliente_id'] = $clienteId;

        try {
            $statusResults = $this->client->getStatus($company, $xmlDocumentKey);
        } catch (RuntimeException $e) {
            $statusResults = null;
        }

        $update = [
            'status' => DocumentoEmitido::STATUS_ACCEPTED,
            'status_message' => $statusResults
                ? $this->buildStatusMessage($statusResults)
                : ['resumen' => $xmlInfo['message'] ?? null, 'reglas' => []],
            'response' => $statusResults
                ? ($statusResults['response_xml'] ?? $statusResults['raw_response'] ?? null)
                : null,
            'fecha_expedicion' => $statusResults
                ? $this->resolveFechaExpedicion($statusResults)
                : new DateTimeImmutable('now', new DateTimeZone('America/Bogota')),
            'xml' => $xmlInfo['response_xml'],
            'uuid' => (string) $parsedXml->UUID,
            'subtotal' => $totales['subtotal'],
            'tax_total' => $totales['tax_total'],
            'total' => $totales['total'],
            'currency' => $dianPayload['moneda'],
            'notes' => $dianPayload['notas'],
            'payment_means_id' => $dianPayload['payment_means']['id'] ?? null,
            'payment_means_code' => $dianPayload['payment_means']['codigo'] ?? null,
            'payload' => $payload,
            'cliente_id' => $clienteId,
        ];

        if ($dianPayload['issue_date']) {
            $update['issue_date'] = new DateTimeImmutable(
                $dianPayload['issue_date'] . ' ' . ($dianPayload['issue_time'] ?? '00:00:00'),
                new DateTimeZone('America/Bogota')
            );
        }

        if (! empty($dianPayload['payment_means']['fecha_vencimiento'])) {
            $update['due_date'] = new DateTimeImmutable(
                $dianPayload['payment_means']['fecha_vencimiento'],
                new DateTimeZone('America/Bogota')
            );
        }

        $documento->update($update);

        return true;
    }

    /**
     * Reconstruye el shape interno de "payload" (moneda, fechas, cliente, líneas,
     * notas, medio de pago) a partir del XML real que la DIAN tiene autorizado,
     * para sincronizar el documento con lo que quedó vigente del lado de ellos.
     *
     * @param  SimpleXMLElement  $xml  XML de la DIAN, parseado sin prefijos (ver parseSignedXml()).
     * @return array Payload reconstruido, mismo shape que arma DocumentJsonMapper::map().
     */
    private function buildPayloadFromDianXml(SimpleXMLElement $xml): array
    {
        $customerParty = $xml->AccountingCustomerParty->Party ?? null;
        $customerTaxScheme = $customerParty->PartyTaxScheme ?? null;
        $customerAddress = $customerParty->PhysicalLocation->Address ?? null;

        $accountingCustomerParty = [
            'razon_social' => (string) ($customerParty->PartyName->Name ?? ''),
            'tipo_identificacion' => (string) ($customerTaxScheme->CompanyID['schemeName'] ?? '31'),
            'identificacion' => (string) ($customerTaxScheme->CompanyID ?? ''),
            'dv' => (string) ($customerTaxScheme->CompanyID['schemeID'] ?? '') ?: null,
            'tipo_persona' => (string) ($xml->AccountingCustomerParty->AdditionalAccountID ?? '') === '1' ? '1' : '2',
            'responsabilidades_fiscales' => (string) ($customerTaxScheme->TaxLevelCode ?? ''),
            'direccion' => (string) ($customerAddress->AddressLine->Line ?? ''),
            'ciudad_codigo' => (string) ($customerAddress->ID ?? ''),
            'departamento_codigo' => (string) ($customerAddress->CountrySubentityCode ?? ''),
            'telefono' => (string) ($customerParty->Contact->Telephone ?? '') ?: null,
            'email' => (string) ($customerParty->Contact->ElectronicMail ?? '') ?: null,
        ];

        $lineas = [];
        foreach ($xml->InvoiceLine ?? $xml->CreditNoteLine ?? $xml->DebitNoteLine ?? [] as $line) {
            $impuestos = [];
            foreach ($line->TaxTotal->TaxSubtotal ?? [] as $subtotal) {
                $impuestos[] = [
                    'tipo' => (string) ($subtotal->TaxCategory->TaxScheme->ID ?? '01'),
                    'nombre' => (string) ($subtotal->TaxCategory->TaxScheme->Name ?? '') ?: null,
                    'porcentaje' => (float) ($subtotal->TaxCategory->Percent ?? 0),
                ];
            }

            $quantityNode = $line->InvoicedQuantity ?? $line->CreditedQuantity ?? $line->DebitedQuantity ?? null;

            $lineas[] = [
                'codigo' => (string) ($line->Item->SellersItemIdentification->ID ?? '') ?: null,
                'descripcion' => (string) ($line->Item->Description ?? ''),
                'cantidad' => (float) ($quantityNode ?? 1),
                'unidad_medida' => (string) ($quantityNode['unitCode'] ?? 'EA'),
                'precio_unitario' => (float) ($line->Price->PriceAmount ?? 0),
                'impuestos' => $impuestos,
            ];
        }

        $paymentMeans = null;
        if (isset($xml->PaymentMeans)) {
            $paymentMeans = [
                'id' => (string) ($xml->PaymentMeans->ID ?? '1'),
                'codigo' => (string) ($xml->PaymentMeans->PaymentMeansCode ?? '10'),
                'fecha_vencimiento' => (string) ($xml->PaymentMeans->PaymentDueDate ?? '') ?: null,
                'payment_id' => (string) ($xml->PaymentMeans->PaymentID ?? '') ?: null,
            ];
        }

        $notas = [];
        foreach ($xml->Note ?? [] as $note) {
            $notas[] = (string) $note;
        }

        return [
            'moneda' => (string) ($xml->DocumentCurrencyCode ?? 'COP'),
            'issue_date' => (string) ($xml->IssueDate ?? '') ?: null,
            'issue_time' => (string) ($xml->IssueTime ?? '') ?: null,
            'notas' => $notas,
            'accounting_customer_party' => $accountingCustomerParty,
            'payment_means' => $paymentMeans,
            'lineas' => $lineas,
        ];
    }

    /**
     * Busca el tercero que corresponde al receptor real que quedó en el XML
     * autorizado de la DIAN (por identificación + empresa); si no existe
     * todavía, lo crea con los datos reales del XML (igual que hace
     * DocumentJsonMapper con el flujo normal), para que quede vinculado como
     * cliente aunque nunca se hubiera registrado antes.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $accountingCustomerParty  Datos del receptor reconstruidos del XML.
     * @return string|null Id del tercero encontrado o creado, o null si el XML no traía identificación.
     */
    private function resolveClienteIdFromDianPayload(Company $company, array $accountingCustomerParty): ?string
    {
        if (empty($accountingCustomerParty['identificacion'])) {
            return null;
        }

        $cliente = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identificacion', $accountingCustomerParty['identificacion'])
            ->first();

        if ($cliente) {
            $roles = collect($cliente->roles ?? [])->push('cliente')->unique()->values()->all();
            $cliente->update(['roles' => $roles]);

            return (string) $cliente->_id;
        }

        $cliente = ThirdParty::create([
            'company_id' => (string) $company->_id,
            'identificacion' => $accountingCustomerParty['identificacion'],
            'identification_type' => $accountingCustomerParty['tipo_identificacion'] ?? '31',
            'dv' => $accountingCustomerParty['dv'] ?? null,
            'person_type' => $accountingCustomerParty['tipo_persona'] ?? '2',
            'name' => $accountingCustomerParty['razon_social'] ?? '',
            'fiscal_responsibilities' => $accountingCustomerParty['responsabilidades_fiscales'] ?? null,
            'address' => $accountingCustomerParty['direccion'] ?? null,
            'city_code' => $accountingCustomerParty['ciudad_codigo'] ?? null,
            'department_code' => $accountingCustomerParty['departamento_codigo'] ?? null,
            'phone' => $accountingCustomerParty['telefono'] ?? null,
            'email' => $accountingCustomerParty['email'] ?? null,
            'roles' => ['cliente'],
        ]);

        return (string) $cliente->_id;
    }

    /**
     * Busca la resolución activa de la empresa para el tipo de documento y ambiente indicados.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  string  $tipoDocumento  Código DIAN del tipo de documento.
     * @param  string  $ambiente  Ambiente DIAN (1=producción, 2=pruebas).
     * @param  string|null  $prefix  Prefijo para desambiguar si hay varias resoluciones activas.
     * @return Resolution Resolución activa encontrada.
     */
    private function resolveResolution(Company $company, string $tipoDocumento, string $ambiente, ?string $prefix): Resolution
    {
        $documentTypes = in_array($tipoDocumento, self::FACTURA_CODES, true) ? self::FACTURA_CODES : [$tipoDocumento];

        $query = Resolution::where('company_id', (string) $company->_id)
            ->whereIn('document_type', $documentTypes)
            ->where('environment', $ambiente)
            ->active();

        if ($prefix !== null) {
            $query->where('prefix', $prefix);
        }

        $resolution = $query->get()->first(fn (Resolution $resolution) => ! $resolution->isExpired() && ! $resolution->isExhausted());

        if (! $resolution) {
            throw new InvalidArgumentException('No hay una resolución activa configurada para este tipo de documento y ambiente.');
        }

        return $resolution;
    }

    /**
     * Valida que el número pedido por el caller esté dentro del rango autorizado de la
     * resolución (si tiene rango acotado), y adelanta el contador interno de la
     * resolución para que quede en sincronía con la numeración que ya controla el caller.
     *
     * @param  Resolution  $resolution  Resolución de la empresa para este tipo de documento.
     * @param  string  $numeral  Número completo pedido por el caller (prefijo + consecutivo).
     * @return string Consecutivo (solo la parte numérica) extraído del numeral.
     */
    private function syncResolutionNumbering(Resolution $resolution, string $numeral): string
    {
        $consecutivo = (int) preg_replace('/\D/', '', $numeral);

        if ($resolution->range_to !== null && ($consecutivo < (int) $resolution->range_from || $consecutivo > (int) $resolution->range_to)) {
            throw new InvalidArgumentException("El número \"{$numeral}\" está fuera del rango autorizado de la resolución ({$resolution->range_from}-{$resolution->range_to}).");
        }

        Resolution::where('_id', $resolution->_id)
            ->where(function ($query) use ($consecutivo) {
                $query->where('current_number', '<=', $consecutivo)
                    ->orWhereNull('current_number');
            })
            ->update(['current_number' => $consecutivo + 1]);

        return (string) $consecutivo;
    }

    /**
     * Resuelve la fecha/hora de emisión: si el caller mandó "document.IssueDate"
     * (y opcionalmente "document.IssueTime"), se combinan; si no mandó nada, se
     * usa el momento en que se está procesando el documento. El mismo valor se
     * usa tanto para el XML como para el DocumentoEmitido, para que ambos
     * queden siempre en sincronía.
     *
     * @param  array  $payload  Payload ya mapeado por DocumentJsonMapper.
     * @return DateTimeImmutable Fecha/hora de emisión resuelta (zona Bogotá).
     */
    private function resolveIssueDateTime(array $payload): DateTimeImmutable
    {
        if (empty($payload['issue_date'])) {
            return new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        }

        $time = $payload['issue_time'] ?? '00:00:00';

        return new DateTimeImmutable($payload['issue_date'] . ' ' . $time, new DateTimeZone('America/Bogota'));
    }

    /**
     * Resuelve la fecha de vencimiento ("DueDate"): si el caller no la manda directo,
     * se toma de "PaymentMeans[0].PaymentDueDate" (ya mapeada por DocumentJsonMapper); si
     * no viene en ninguno de los dos, el campo simplemente no se guarda.
     *
     * @param  array  $payload  Payload ya mapeado por DocumentJsonMapper.
     * @return DateTimeImmutable|null Fecha de vencimiento resuelta (zona Bogotá), o null si no vino.
     */
    private function resolveDueDate(array $payload): ?DateTimeImmutable
    {
        if (empty($payload['fecha_vencimiento'])) {
            return null;
        }

        return new DateTimeImmutable($payload['fecha_vencimiento'], new DateTimeZone('America/Bogota'));
    }

    /**
     * Completa los datos de referencia a la factura original de una nota (UUID, fecha),
     * buscándolos en nuestros propios documentos emitidos si solo se indicó "factura_id".
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $referencias  Datos de referencia enviados en el payload.
     * @return array Datos de referencia completos.
     */
    private function resolveReferencias(Company $company, array $referencias): array
    {
        if (empty($referencias['factura_id']) || ! empty($referencias['factura_cufe'])) {
            return $referencias;
        }

        $factura = DocumentoEmitido::where('company_id', (string) $company->_id)
            ->where('numeral', $referencias['factura_id'])
            ->whereIn('tipo_documento', self::FACTURA_CODES)
            ->first();

        if (! $factura) {
            throw new InvalidArgumentException("No se encontró la factura \"{$referencias['factura_id']}\" para referenciar en la nota.");
        }

        $referencias['factura_cufe'] = $factura->uuid;
        $referencias['factura_fecha'] ??= $factura->issue_date?->setTimezone('America/Bogota')->format('Y-m-d');

        return $referencias;
    }

    /**
     * Arma el mensaje DIAN que se guarda en "status_message": el resumen
     * (StatusMessage/StatusDescription) más el detalle de reglas que la DIAN
     * haya devuelto (ErrorMessage) -- tanto si el documento fue rechazado
     * (reglas de rechazo) como si fue aceptado con observaciones (reglas de
     * notificación), para poder mostrarlo tal cual en documents.show.
     *
     * @param  array  $results  Resultado de DianSoapClient::sendBillSync().
     * @return array{resumen: string|null, reglas: array} Mensaje DIAN estructurado.
     */
    private function buildStatusMessage(array $results): array
    {
        return [
            'resumen' => $results['status_message'] ?: ($results['status_description'] ?? null),
            'reglas' => $results['error_messages'] ?? [],
        ];
    }

    /**
     * Resuelve la fecha real en que la DIAN expidió el documento: se lee del
     * IssueDate/IssueTime del ApplicationResponse que trae GetStatus (el
     * response_xml), que es la fecha oficial de expedición; si no se pudo
     * leer, se usa el momento en que se está procesando la respuesta.
     *
     * @param  array  $results  Resultado de DianSoapClient::getStatus() (o sendBillSync() si esa consulta falló).
     * @return DateTimeImmutable Fecha de expedición resuelta (zona Bogotá).
     */
    private function resolveFechaExpedicion(array $results): DateTimeImmutable
    {
        if (! empty($results['response_xml'])) {
            try {
                $applicationResponse = $this->parseSignedXml($results['response_xml']);
                $issueDate = (string) ($applicationResponse->IssueDate ?? '');
                $issueTime = (string) ($applicationResponse->IssueTime ?? '00:00:00');

                if ($issueDate !== '') {
                    return new DateTimeImmutable($issueDate . ' ' . $issueTime, new DateTimeZone('America/Bogota'));
                }
            } catch (\Exception $e) {
                
            }
        }

        return new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
    }

    /**
     * Parsea un XML UBL ya firmado sin prefijos de namespace, para poder leer
     * sus nodos (UUID, totales) por nombre simple.
     *
     * @param  string  $signedXml  XML UBL firmado.
     * @return SimpleXMLElement Documento parseado sin prefijos.
     */
    private function parseSignedXml(string $signedXml): SimpleXMLElement
    {
        $withoutPrefixes = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $signedXml);

        return new SimpleXMLElement($withoutPrefixes);
    }

    /**
     * Lee los totales del documento (LegalMonetaryTotal o RequestedMonetaryTotal,
     * más la suma de los TaxTotal a nivel de documento) para guardarlos junto
     * al DocumentoEmitido.
     *
     * @param  SimpleXMLElement  $xml  Documento UBL parseado sin prefijos (ver parseSignedXml()).
     * @return array{subtotal: float, tax_total: float, total: float} Totales del documento.
     */
    private function extractTotals(SimpleXMLElement $xml): array
    {
        $monetaryTotal = $xml->LegalMonetaryTotal ?? $xml->RequestedMonetaryTotal ?? null;

        $taxTotal = 0.0;
        foreach ($xml->TaxTotal as $documentTaxTotal) {
            $taxTotal += (float) $documentTaxTotal->TaxAmount;
        }

        return [
            'subtotal' => (float) ($monetaryTotal->LineExtensionAmount ?? 0),
            'tax_total' => $taxTotal,
            'total' => (float) ($monetaryTotal->PayableAmount ?? 0),
        ];
    }

    /**
     * Crea en el catálogo de productos de la empresa los que vengan en las
     * líneas del documento y todavía no existan (buscados por "code"), sin
     * duplicar los que ya están. No toca precio/nombre de los que ya existen:
     * el catálogo lo sigue administrando la empresa desde su propia pantalla.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $lineas  Líneas del documento (shape interno de UblDocumentBuilder).
     */
    private function syncProducts(Company $company, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $codigo = $linea['codigo'] ?? null;

            if (! $codigo) {
                continue;
            }

            Product::firstOrCreate(
                ['company_id' => (string) $company->_id, 'code' => $codigo],
                [
                    'description' => $linea['descripcion'] ?? $codigo,
                    'barcode' => $linea['codigo_barras'] ?? null,
                    'unit_price' => $linea['precio_unitario'] ?? 0,
                    'unit_code' => $linea['unidad_medida'] ?? 'EA',
                    'tracks_inventory' => false,
                    'stock' => 0,
                    'warehouse_stocks' => [],
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * Descuenta del inventario los productos de las líneas que manejen
     * inventario (tracks_inventory), y registra el movimiento en el kardex.
     * Solo se llama una vez que la DIAN aceptó el documento.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $lineas  Líneas del documento (shape interno de UblDocumentBuilder).
     * @param  string  $numeral  Número del documento, para el motivo del movimiento.
     * @param  string|null  $userId  Quién vendió: el cajero del turno (venta POS) o quien
     * emitió la factura (venta directa por el panel web). Null en documentos emitidos por
     * la API con token de empresa (no hay un usuario asociado a esa petición).
     * @return void El costo promedio (average_cost) del producto nunca se modifica aquí, solo se lee (como snapshot, antes de mutar el stock) para valorizar la salida.
     */
    private function discountInventory(Company $company, array $lineas, string $numeral, ?string $userId = null): void
    {
        foreach ($lineas as $linea) {
            $codigo = $linea['codigo'] ?? null;

            if (! $codigo) {
                continue;
            }

            $product = Product::where('company_id', (string) $company->_id)->where('code', $codigo)->first();

            if (! $product || ! $product->tracks_inventory) {
                continue;
            }

            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $stocks = $product->warehouse_stocks ?? [];
            $warehouseId = $linea['bodega_id'] ?? null;
            $balanceAfter = null;
            $unitCost = (float) ($product->average_cost ?? 0);

            if ($warehouseId) {
                
                foreach ($stocks as &$entry) {
                    if (($entry['warehouse_id'] ?? null) === $warehouseId) {
                        $entry['stock'] = (float) ($entry['stock'] ?? 0) - $cantidad;
                        $balanceAfter = $entry['stock'];

                        break;
                    }
                }
                unset($entry);
                $product->warehouse_stocks = $stocks;
            }

            $product->stock = (float) $product->stock - $cantidad;
            $product->save();

            StockMovement::create([
                'company_id' => (string) $company->_id,
                'product_id' => (string) $product->_id,
                'warehouse_id' => $warehouseId,
                'type' => 'out',
                'quantity' => $cantidad,
                'unit_cost' => $unitCost,
                'total_cost' => round($cantidad * $unitCost, 2),
                'balance_after' => $warehouseId ? $balanceAfter : $product->unassigned_stock,
                'reason' => 'document:' . $numeral,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Comprime un XML en memoria a un .zip, tal como lo espera SendBillSync.
     *
     * @param  string  $fileName  Nombre del archivo XML dentro del zip.
     * @param  string  $xmlContent  Contenido del XML.
     * @return string Contenido binario del .zip.
     */
    private function zipXml(string $fileName, string $xmlContent): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dian_doc_');

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::OVERWRITE);
        $zip->addFromString($fileName, $xmlContent);
        $zip->close();

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }
}
