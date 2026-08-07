<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\Department;
use App\Models\ThirdParty;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Traduce el JSON de un "documento emitido" (factura, nota crédito, nota
 * débito) al XML UBL 2.1 que espera la DIAN.
 *
 * El JSON de entrada usa los mismos nombres de los elementos UBL (en
 * snake_case). El emisor siempre sale de la Company autenticada, nunca del
 * JSON.
 */
class UblDocumentBuilder
{
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const STS_NS = 'dian:gov:co:facturaelectronica:Structures-2-1';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';
    private const XADES141_NS = 'http://uri.etsi.org/01903/v1.4.1#';

    private const ROOT_NS = [
        'factura' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'nota_credito' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'nota_debito' => 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2',
    ];

    private const ROOT_ELEMENT = [
        'factura' => 'Invoice',
        'nota_credito' => 'CreditNote',
        'nota_debito' => 'DebitNote',
    ];

    private const LINE_ELEMENT = [
        'factura' => 'InvoiceLine',
        'nota_credito' => 'CreditNoteLine',
        'nota_debito' => 'DebitNoteLine',
    ];

    private const QUANTITY_ELEMENT = [
        'factura' => 'InvoicedQuantity',
        'nota_credito' => 'CreditedQuantity',
        'nota_debito' => 'DebitedQuantity',
    ];

    private const MONETARY_TOTAL_ELEMENT = [
        'factura' => 'LegalMonetaryTotal',
        'nota_credito' => 'LegalMonetaryTotal',
        'nota_debito' => 'RequestedMonetaryTotal',
    ];

    private const CUFE_TAX_ORDER = ['01', '04', '03'];

    private const DOCUMENT_CODE_TO_FAMILY = [
        '01' => 'factura',
        '02' => 'factura',
        '03' => 'factura',
        '04' => 'factura',
        '91' => 'nota_credito',
        '92' => 'nota_debito',
    ];

    private const DEFAULT_CUSTOMIZATION_ID = [
        'factura' => '10',
        'nota_credito' => ['con_referencia' => '20', 'sin_referencia' => '22'],
        'nota_debito' => ['con_referencia' => '30', 'sin_referencia' => '32'],
    ];

    private DOMDocument $doc;

    private string $tipo;

    private string $tipoDocumentoCode;

    /** @var array<string, Department|null> */
    private ?array $departmentsCache = null;

    private string $ambienteCode;

    private DocumentTotalsCalculator $totals;

    public function __construct(?DocumentTotalsCalculator $totals = null)
    {
        $this->totals = $totals ?? new DocumentTotalsCalculator();
    }

    /**
     * Construye el XML UBL 2.1 (sin firmar) de un documento a partir del JSON de entrada.
     *
     * @param  Company  $company  Empresa emisora (siempre se toma de aquí, nunca del payload).
     * @param  array  $payload  Datos del documento (tipo_documento, number, lineas, etc.).
     * @return string XML UBL 2.1 sin firmar.
     */
    public function build(Company $company, array $payload): string
    {
        $this->ambienteCode = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;
        $this->tipoDocumentoCode = $payload['tipo_documento'] ?? throw new InvalidArgumentException('El campo "tipo_documento" (código DIAN: 01, 02, 03, 04, 91, 92) es obligatorio.');

        if (! isset(self::DOCUMENT_CODE_TO_FAMILY[$this->tipoDocumentoCode])) {
            throw new InvalidArgumentException("Código de tipo de documento DIAN no soportado: {$this->tipoDocumentoCode}");
        }

        $this->tipo = self::DOCUMENT_CODE_TO_FAMILY[$this->tipoDocumentoCode];

        $this->doc = new DOMDocument('1.0', 'UTF-8');

        // Cálculo de líneas/impuestos/totales: vive en DocumentTotalsCalculator
        // (ver app/Services/Dian/DocumentTotalsCalculator.php) para que las
        // remisiones del POS puedan calcular subtotal/tax_total/total sin
        // pasar por todo este builder (no llegan a construir XML).
        $calculo = $this->totals->calcularTotalesDocumento($payload['lineas'] ?? [], $payload['cargos_descuentos'] ?? []);
        $lineas = $calculo['lineas'];
        $impuestos = $calculo['impuestos'];
        $lineExtensionAmount = $calculo['line_extension_amount'];
        $cargos = $calculo['cargos'];
        $totales = $calculo['totales'];

        $numero = $payload['number'] ?? throw new InvalidArgumentException('El campo "number" es obligatorio.');
        $moneda = $payload['moneda'] ?? 'COP';
        $ahoraColombia = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $fecha = $payload['issue_date'] ?? $ahoraColombia->format('Y-m-d');
        $hora = $payload['issue_time'] ?? $ahoraColombia->format('H:i:sP');

        $customerParty = $this->resolveCustomerParty($company, $payload);

        $softwareSecurityCode = $this->calculateSoftwareSecurityCode($company, $numero);
        $cufe = $this->calculateCufe($company, $payload, $numero, $fecha, $hora, $totales, $impuestos, $customerParty);

        $root = $this->doc->createElementNS(self::ROOT_NS[$this->tipo], self::ROOT_ELEMENT[$this->tipo]);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::DS_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::EXT_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sts', self::STS_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', self::XADES_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades141', self::XADES141_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI_NS);
        $root->setAttributeNS(self::XSI_NS, 'xsi:schemaLocation', self::ROOT_NS[$this->tipo] . ' http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd');
        $this->doc->appendChild($root);

        $root->appendChild($this->buildExtensions($company, $numero, $softwareSecurityCode, $cufe, $moneda, $totales, $customerParty, $payload));

        $this->appendCbc($root, 'UBLVersionID', 'UBL 2.1');
        $this->appendCbc($root, 'CustomizationID', $payload['customization_id'] ?? $this->defaultCustomizationId($payload));
        $profileIdSuffix = match ($this->tipo) {
            'nota_credito' => 'Nota Crédito de Factura Electrónica de Venta',
            'nota_debito' => 'Nota Débito de Factura Electrónica de Venta',
            default => 'Factura Electrónica de Venta',
        };
        $this->appendCbc($root, 'ProfileID', "DIAN 2.1: {$profileIdSuffix}");
        $this->appendCbc($root, 'ProfileExecutionID', $this->ambienteCode);
        $this->appendCbc($root, 'ID', $numero);

        $uuid = $this->appendCbc($root, 'UUID', $cufe);
        $uuid->setAttribute('schemeID', $this->ambienteCode);
        $uuid->setAttribute('schemeName', $this->tipo === 'factura' ? 'CUFE-SHA384' : 'CUDE-SHA384');

        $this->appendCbc($root, 'IssueDate', $fecha);
        $this->appendCbc($root, 'IssueTime', $hora);

        if ($this->tipo === 'factura') {
            $this->appendCbc($root, 'InvoiceTypeCode', $this->tipoDocumentoCode);
        } elseif ($this->tipo === 'nota_credito') {
            $this->appendCbc($root, 'CreditNoteTypeCode', $this->tipoDocumentoCode);
        }

        foreach ((array) ($payload['notas'] ?? []) as $nota) {
            if ($nota !== '') {
                $this->appendCbc($root, 'Note', $nota);
            }
        }

        $currencyEl = $this->appendCbc($root, 'DocumentCurrencyCode', $moneda);
        $currencyEl->setAttribute('listAgencyID', '6');
        $currencyEl->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $currencyEl->setAttribute('listID', 'ISO 4217 Alpha');

        $this->appendCbc($root, 'LineCountNumeric', (string) count($lineas));

        if ($this->tipo !== 'factura' && ! empty($payload['referencias'])) {
            $referencias = $payload['referencias'];
            $root->appendChild($this->buildDiscrepancyResponse($referencias));

            if (! empty($referencias['factura_id'])) {
                $root->appendChild($this->buildBillingReference($referencias));
            } elseif (! empty($referencias['periodo_desde']) && ! empty($referencias['periodo_hasta'])) {
                $root->appendChild($this->buildInvoicePeriod($referencias));
            }
        }

        $prefijoPuntoFacturacion = $payload['resolucion']['prefijo'] ?? $payload['prefijo'] ?? null;

        $root->appendChild($this->buildSupplierParty($company, $prefijoPuntoFacturacion, $payload['supplier_overrides'] ?? []));
        $root->appendChild($this->buildCustomerParty($company, $customerParty));

        $paymentMeansList = $payload['payment_means_list'] ?? array_filter([$payload['payment_means'] ?? null]);
        foreach ($paymentMeansList as $paymentMeans) {
            $root->appendChild($this->buildPaymentMeans($paymentMeans));
        }

        foreach ($cargos as $id => $cargo) {
            $root->appendChild($this->buildDocumentAllowanceCharge($id + 1, $cargo, $moneda));
        }

        foreach ($impuestos as $impuesto) {
            $root->appendChild($this->buildTaxTotal($moneda, $impuesto['taxable_amount'], $impuesto['tax_amount'], $impuesto['codigo'], $impuesto['nombre'], $impuesto['porcentaje']));
        }

        $root->appendChild($this->buildMonetaryTotal($moneda, $totales));

        foreach ($lineas as $index => $linea) {
            $root->appendChild($this->buildLine($moneda, $index + 1, $linea));
        }

        $this->doc->formatOutput = false;
        $canonical = $this->doc->C14N(false, false);
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->loadXML($canonical);
        $this->doc->encoding = 'UTF-8';

        return $this->doc->saveXML();
    }

    /**
     * Determina el CustomizationID por defecto según el tipo de documento y si trae referencias.
     *
     * @param  array  $payload  Payload del documento.
     * @return string CustomizationID.
     */
    private function defaultCustomizationId(array $payload): string
    {
        if ($this->tipo === 'factura') {
            return self::DEFAULT_CUSTOMIZATION_ID['factura'];
        }

        $tieneReferencia = ! empty($payload['referencias']['factura_id']);

        return $tieneReferencia
            ? self::DEFAULT_CUSTOMIZATION_ID[$this->tipo]['con_referencia']
            : self::DEFAULT_CUSTOMIZATION_ID[$this->tipo]['sin_referencia'];
    }

    /**
     * Resuelve los datos del receptor: si viene "cliente_id" se buscan los datos
     * del tercero; si viene "accounting_customer_party" inline, se usa (y
     * sobreescribe lo que haya salido del tercero) tal cual.
     *
     * @param  Company  $company  Empresa emisora (dueña del tercero, si se busca por cliente_id).
     * @param  array  $payload  Payload del documento.
     * @return array Datos del receptor.
     */
    private function resolveCustomerParty(Company $company, array $payload): array
    {
        $data = [];

        if (! empty($payload['cliente_id'])) {
            $cliente = ThirdParty::where('company_id', $company->id)->find($payload['cliente_id']);
            if (! $cliente) {
                throw new InvalidArgumentException('El cliente indicado no existe.');
            }
            $data = [
                'razon_social' => $cliente->name,
                'tipo_identificacion' => $cliente->identification_type,
                'identificacion' => $cliente->identificacion,
                'dv' => $cliente->dv,
                'tipo_persona' => $cliente->person_type,
                'responsabilidades_fiscales' => $cliente->fiscal_responsibilities,
                'direccion' => $cliente->address,
                'ciudad_codigo' => $cliente->city_code,
                'departamento_codigo' => $cliente->department_code,
                'telefono' => $cliente->phone,
                'email' => $cliente->email,
            ];
        }

        return array_merge($data, $payload['accounting_customer_party'] ?? []);
    }

    /**
     * Construye ext:UBLExtensions con el bloque sts:DianExtensions.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  string  $numero  Número del documento.
     * @param  string  $softwareSecurityCode  Código de seguridad del software (ver calculateSoftwareSecurityCode()).
     * @param  string  $cufe  CUFE/CUDE del documento (ver calculateCufe()).
     * @param  string  $moneda  Código de moneda.
     * @param  array  $totales  Totales del documento.
     * @param  array  $customerParty  Datos del receptor.
     * @param  array  $payload  Payload del documento.
     * @return DOMElement Nodo ext:UBLExtensions construido (aún no adjunto al árbol).
     */
    private function buildExtensions(Company $company, string $numero, string $softwareSecurityCode, string $cufe, string $moneda, array $totales, array $customerParty, array $payload): DOMElement
    {
        $extensions = $this->doc->createElementNS(self::EXT_NS, 'ext:UBLExtensions');

        $extension = $this->doc->createElementNS(self::EXT_NS, 'ext:UBLExtension');
        $content = $this->doc->createElementNS(self::EXT_NS, 'ext:ExtensionContent');
        $dianExtensions = $this->doc->createElementNS(self::STS_NS, 'sts:DianExtensions');

        if ($this->tipo === 'factura' && ! empty($payload['resolucion'])) {
            $dianExtensions->appendChild($this->buildInvoiceControl($payload['resolucion']));
        }

        $invoiceSource = $this->doc->createElementNS(self::STS_NS, 'sts:InvoiceSource');
        $countryCode = $this->appendSts($invoiceSource, 'IdentificationCode', 'CO', self::CBC_NS, 'cbc');
        $countryCode->setAttribute('listAgencyID', '6');
        $countryCode->setAttribute('listAgencyName', 'United Nations Economic Commission for Europe');
        $countryCode->setAttribute('listSchemeURI', 'urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1');
        $dianExtensions->appendChild($invoiceSource);

        $softwareProvider = $this->doc->createElementNS(self::STS_NS, 'sts:SoftwareProvider');
        $providerId = $this->appendSts($softwareProvider, 'ProviderID', $company->identificacion);
        $providerId->setAttribute('schemeAgencyID', '195');
        $providerId->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');
        if ($company->dv !== null && $company->dv !== '') {
            $providerId->setAttribute('schemeID', (string) $company->dv);
        }
        $providerId->setAttribute('schemeName', '31');
        $softwareId = $this->appendSts($softwareProvider, 'SoftwareID', $company->dian_software_id ?? '');
        $softwareId->setAttribute('schemeAgencyID', '195');
        $softwareId->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');
        $dianExtensions->appendChild($softwareProvider);

        $securityCodeEl = $this->appendSts($dianExtensions, 'SoftwareSecurityCode', $softwareSecurityCode);
        $securityCodeEl->setAttribute('schemeAgencyID', '195');
        $securityCodeEl->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');

        $authProvider = $this->doc->createElementNS(self::STS_NS, 'sts:AuthorizationProvider');
        $authProviderId = $this->appendSts($authProvider, 'AuthorizationProviderID', '800197268');
        $authProviderId->setAttribute('schemeAgencyID', '195');
        $authProviderId->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');
        $authProviderId->setAttribute('schemeID', '4');
        $authProviderId->setAttribute('schemeName', '31');
        $dianExtensions->appendChild($authProvider);

        $this->appendSts($dianExtensions, 'QRCode', $this->qrValidationUrl($cufe));

        $content->appendChild($dianExtensions);
        $extension->appendChild($content);
        $extensions->appendChild($extension);

        return $extensions;
    }

    /**
     * Construye sts:InvoiceControl (rango de numeración autorizado).
     *
     * @param  array{numero: string, prefijo: string, rango_desde: mixed, rango_hasta: mixed, valido_desde: string, valido_hasta: string}  $resolucion  Datos de la resolución DIAN.
     * @return DOMElement Nodo sts:InvoiceControl construido (aún no adjunto al árbol).
     */
    private function buildInvoiceControl(array $resolucion): DOMElement
    {
        $invoiceControl = $this->doc->createElementNS(self::STS_NS, 'sts:InvoiceControl');
        $this->appendSts($invoiceControl, 'InvoiceAuthorization', (string) $resolucion['numero']);

        $authorizationPeriod = $this->doc->createElementNS(self::STS_NS, 'sts:AuthorizationPeriod');
        $this->appendCbc($authorizationPeriod, 'StartDate', (string) $resolucion['valido_desde']);
        $this->appendCbc($authorizationPeriod, 'EndDate', (string) $resolucion['valido_hasta']);
        $invoiceControl->appendChild($authorizationPeriod);

        $authorizedInvoices = $this->doc->createElementNS(self::STS_NS, 'sts:AuthorizedInvoices');
        $this->appendSts($authorizedInvoices, 'Prefix', (string) $resolucion['prefijo']);
        $this->appendSts($authorizedInvoices, 'From', (string) $resolucion['rango_desde']);
        $this->appendSts($authorizedInvoices, 'To', (string) $resolucion['rango_hasta']);
        $invoiceControl->appendChild($authorizedInvoices);

        return $invoiceControl;
    }

    /**
     * Construye la URL de consulta del código QR del documento.
     *
     * @param  string  $cufe  CUFE/CUDE del documento.
     * @return string URL de consulta.
     */
    private function qrValidationUrl(string $cufe): string
    {
        $host = $this->ambienteCode === '1'
            ? 'https://catalogo-vpfe.dian.gov.co'
            : 'https://catalogo-vpfe-hab.dian.gov.co';

        return "{$host}/document/searchqr?documentkey={$cufe}";
    }

    /**
     * Construye cac:DiscrepancyResponse (motivo de la nota crédito/débito).
     *
     * @param  array  $referencias  Datos de referencia a la factura original.
     * @return DOMElement Nodo cac:DiscrepancyResponse construido (aún no adjunto al árbol).
     */
    private function buildDiscrepancyResponse(array $referencias): DOMElement
    {
        $codigo = (string) ($referencias['concepto_codigo'] ?? '1');

        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:DiscrepancyResponse');

        // Solo hay ReferenceID cuando la nota referencia una factura puntual
        // (operación 20/30); sin referencia (22/32) se identifica el período
        // afectado con cac:InvoicePeriod en vez de un ID de factura.
        if (! empty($referencias['factura_id'])) {
            $this->appendCbc($node, 'ReferenceID', $referencias['factura_id']);
        }

        $this->appendCbc($node, 'ResponseCode', $codigo);
        $this->appendCbc($node, 'Description', $this->conceptoCorreccionDescripcion($codigo));

        return $node;
    }

    /**
     * Traduce el código de concepto de corrección (cac:DiscrepancyResponse/cbc:ResponseCode)
     * al texto oficial de la DIAN (tabla "Concepto de Corrección" del anexo técnico) -- la
     * Descripción debe coincidir textualmente con esta lista, no admite texto libre.
     *
     * @param  string  $codigo  Código de concepto de corrección.
     * @return string Descripción oficial del concepto.
     */
    private function conceptoCorreccionDescripcion(string $codigo): string
    {
        $notaCredito = [
            '1' => 'Devolución parcial de los bienes y/o no aceptación parcial del servicio',
            '2' => 'Anulación de factura electrónica',
            '3' => 'Rebaja o descuento parcial o total',
            '4' => 'Ajuste de precio',
            '5' => 'Descuento comercial por pronto pago',
            '6' => 'Descuento comercial por volumen de ventas',
        ];

        $notaDebito = [
            '1' => 'Intereses',
            '2' => 'Gastos por cobrar',
            '3' => 'Cambio del valor',
            '4' => 'Otros',
        ];

        $tabla = $this->tipo === 'nota_debito' ? $notaDebito : $notaCredito;

        return $tabla[$codigo] ?? $codigo;
    }

    /**
     * Construye cac:BillingReference (referencia a la factura original de una nota).
     *
     * @param  array  $referencias  Datos de referencia a la factura original.
     * @return DOMElement Nodo cac:BillingReference construido (aún no adjunto al árbol).
     */
    private function buildBillingReference(array $referencias): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:BillingReference');
        $invoiceRef = $this->doc->createElementNS(self::CAC_NS, 'cac:InvoiceDocumentReference');
        $this->appendCbc($invoiceRef, 'ID', $referencias['factura_id']);
        $uuid = $this->appendCbc($invoiceRef, 'UUID', $referencias['factura_cufe']);
        $uuid->setAttribute('schemeName', 'CUFE-SHA384');
        $this->appendCbc($invoiceRef, 'IssueDate', $referencias['factura_fecha']);
        $node->appendChild($invoiceRef);

        return $node;
    }

    /**
     * Construye cac:InvoicePeriod (período que cubre una nota sin referencia
     * a una factura puntual -- operación 22/32 del catálogo "Tipo de Operación").
     *
     * @param  array  $referencias  Datos de referencia de la nota, con 'periodo_desde'/'periodo_hasta'.
     * @return DOMElement Nodo cac:InvoicePeriod construido (aún no adjunto al árbol).
     */
    private function buildInvoicePeriod(array $referencias): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:InvoicePeriod');
        $this->appendCbc($node, 'StartDate', $referencias['periodo_desde']);
        $this->appendCbc($node, 'EndDate', $referencias['periodo_hasta']);

        return $node;
    }

    /**
     * Construye cac:AccountingSupplierParty (emisor del documento). Los datos salen de
     * la Company autenticada; si el payload trae "supplier_overrides" (campos enviados
     * en AccountingSupplierParty además de CompanyID), esos valores reemplazan a los de
     * la Company solo para este documento, sin modificar el registro en base de datos.
     *
     * @param  Company  $company  Empresa emisora.
     * @param  string|null  $prefijoPuntoFacturacion  Prefijo de numeración (código de sucursal ante la DIAN).
     * @param  array  $overrides  Campos del emisor a reemplazar solo para este documento (name, address, city_code, department_code, phone, email, fiscal_responsibilities).
     * @return DOMElement Nodo cac:AccountingSupplierParty construido (aún no adjunto al árbol).
     */
    private function buildSupplierParty(Company $company, ?string $prefijoPuntoFacturacion = null, array $overrides = []): DOMElement
    {
        $name = $overrides['name'] ?? $company->name;
        $address = $overrides['address'] ?? $company->address;
        $cityCode = $overrides['city_code'] ?? $company->city_code;
        $departmentCode = $overrides['department_code'] ?? $company->department_code;
        $phone = $overrides['phone'] ?? $company->phone;
        $email = $overrides['email'] ?? $company->email;
        $fiscalResponsibilities = $overrides['fiscal_responsibilities'] ?? $company->fiscal_responsibilities;

        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:AccountingSupplierParty');
        $this->appendCbc($node, 'AdditionalAccountID', $company->person_type === '1' ? '1' : '2');

        $party = $this->doc->createElementNS(self::CAC_NS, 'cac:Party');
        $partyName = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyName');
        $this->appendCbc($partyName, 'Name', $name);
        $party->appendChild($partyName);

        $party->appendChild($this->buildAddress('cac:PhysicalLocation', $address, $cityCode, $departmentCode));

        $party->appendChild($this->buildPartyTaxScheme($name, $company->identificacion, $company->dv, $fiscalResponsibilities, $address, $cityCode, $departmentCode));
        $party->appendChild($this->buildPartyLegalEntity($name, $company->identificacion, $company->dv, $prefijoPuntoFacturacion));

        if ($phone || $email) {
            $party->appendChild($this->buildContact(null, $phone, $email));
        }

        $node->appendChild($party);

        return $node;
    }

    /**
     * Construye cac:AccountingCustomerParty (receptor del documento).
     *
     * @param  Company  $company  Empresa emisora (se necesita su NIT para cac:PartyIdentification cuando el receptor es persona natural).
     * @param  array  $customerParty  Datos del receptor (ver resolveCustomerParty()).
     * @return DOMElement Nodo cac:AccountingCustomerParty construido (aún no adjunto al árbol).
     */
    private function buildCustomerParty(Company $company, array $customerParty): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:AccountingCustomerParty');
        $additionalAccountId = ($customerParty['tipo_persona'] ?? null) === '1' ? '1' : '2';
        $this->appendCbc($node, 'AdditionalAccountID', $additionalAccountId);

        $party = $this->doc->createElementNS(self::CAC_NS, 'cac:Party');

        // La DIAN exige (regla FAK61) que cuando el receptor es persona
        // natural (AdditionalAccountID=2) se informe además el NIT del
        // emisor en cac:PartyIdentification -- si no, rechaza el documento
        // por "grupo no informado".
        if ($additionalAccountId === '2') {
            $partyIdentification = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyIdentification');
            $id = $this->appendCbc($partyIdentification, 'ID', $company->identificacion);
            $id->setAttribute('schemeID', '6');
            $id->setAttribute('schemeName', '31');
            $party->appendChild($partyIdentification);
        }

        $partyName = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyName');
        $this->appendCbc($partyName, 'Name', $customerParty['razon_social'] ?? '');
        $party->appendChild($partyName);

        $party->appendChild($this->buildAddress('cac:PhysicalLocation', $customerParty['direccion'] ?? '', $customerParty['ciudad_codigo'] ?? '', $customerParty['departamento_codigo'] ?? ''));

        $tipoIdentificacion = $customerParty['tipo_identificacion'] ?? '31';

        $party->appendChild($this->buildPartyTaxScheme(
            $customerParty['razon_social'] ?? '',
            $customerParty['identificacion'] ?? '',
            $customerParty['dv'] ?? null,
            $customerParty['responsabilidades_fiscales'] ?? 'R-99-PN',
            $customerParty['direccion'] ?? '',
            $customerParty['ciudad_codigo'] ?? '',
            $customerParty['departamento_codigo'] ?? '',
            $tipoIdentificacion
        ));
        $party->appendChild($this->buildPartyLegalEntity($customerParty['razon_social'] ?? '', $customerParty['identificacion'] ?? '', $customerParty['dv'] ?? null, null, $tipoIdentificacion));

        if (! empty($customerParty['telefono']) || ! empty($customerParty['email'])) {
            $party->appendChild($this->buildContact(null, $customerParty['telefono'] ?? null, $customerParty['email'] ?? null));
        }

        $node->appendChild($party);

        return $node;
    }

    /**
     * Construye un elemento contenedor de dirección (cac:PhysicalLocation, etc.).
     *
     * @param  string  $wrapperElement  Nombre del elemento contenedor (con prefijo cac:).
     * @param  string|null  $direccion  Línea de dirección.
     * @param  string|null  $ciudadCodigo  Código DIAN de la ciudad.
     * @param  string|null  $departamentoCodigo  Código DIAN del departamento.
     * @return DOMElement Nodo contenedor construido (aún no adjunto al árbol).
     */
    private function buildAddress(string $wrapperElement, ?string $direccion, ?string $ciudadCodigo, ?string $departamentoCodigo): DOMElement
    {
        $wrapper = $this->doc->createElementNS(self::CAC_NS, $wrapperElement);
        $wrapper->appendChild($this->buildAddressContent($direccion, $ciudadCodigo, $departamentoCodigo));

        return $wrapper;
    }

    /**
     * Construye cac:Address (o el elemento equivalente indicado en $elementName).
     *
     * @param  string|null  $direccion  Línea de dirección.
     * @param  string|null  $ciudadCodigo  Código DIAN de la ciudad.
     * @param  string|null  $departamentoCodigo  Código DIAN del departamento.
     * @param  string  $elementName  Nombre del elemento a construir (con prefijo cac:).
     * @return DOMElement Nodo de dirección construido (aún no adjunto al árbol).
     */
    private function buildAddressContent(?string $direccion, ?string $ciudadCodigo, ?string $departamentoCodigo, string $elementName = 'cac:Address'): DOMElement
    {
        $address = $this->doc->createElementNS(self::CAC_NS, $elementName);
        [$cityName, $departmentName] = $this->resolveLocationNames($departamentoCodigo, $ciudadCodigo);

        $this->appendCbc($address, 'ID', $ciudadCodigo ?? '');
        $this->appendCbc($address, 'CityName', $cityName);
        $this->appendCbc($address, 'PostalZone', $ciudadCodigo ?? '');
        $this->appendCbc($address, 'CountrySubentity', $departmentName);
        $this->appendCbc($address, 'CountrySubentityCode', $departamentoCodigo ?? '');

        if ($direccion) {
            $addressLine = $this->doc->createElementNS(self::CAC_NS, 'cac:AddressLine');
            $this->appendCbc($addressLine, 'Line', $direccion);
            $address->appendChild($addressLine);
        }

        $country = $this->doc->createElementNS(self::CAC_NS, 'cac:Country');
        $this->appendCbc($country, 'IdentificationCode', 'CO');
        $countryName = $this->appendCbc($country, 'Name', 'Colombia');
        $countryName->setAttribute('languageID', 'es');
        $address->appendChild($country);

        return $address;
    }

    /**
     * Resuelve el nombre de la ciudad y del departamento a partir de sus códigos DIAN.
     *
     * @param  string|null  $departamentoCodigo  Código DIAN del departamento.
     * @param  string|null  $ciudadCodigo  Código DIAN de la ciudad.
     * @return array{0: string, 1: string} [nombre de la ciudad, nombre del departamento]
     */
    private function resolveLocationNames(?string $departamentoCodigo, ?string $ciudadCodigo): array
    {
        if (! $departamentoCodigo) {
            return ['', ''];
        }

        $this->departmentsCache ??= [];
        $department = $this->departmentsCache[$departamentoCodigo] ??= Department::where('codigo', $departamentoCodigo)->first();

        if (! $department) {
            return ['', ''];
        }

        $departmentName = $department->descripcion ?? '';
        $municipio = collect($department->municipios ?? [])->firstWhere('codigo', $ciudadCodigo);

        return [$municipio['descripcion'] ?? '', $departmentName];
    }

    /**
     * Construye cac:PartyTaxScheme.
     *
     * @param  string  $razonSocial  Razón social.
     * @param  string  $identificacion  Número de identificación (NIT/cédula/etc.).
     * @param  string|null  $dv  Dígito de verificación (solo aplica si $tipoIdentificacion es NIT/31).
     * @param  string|array  $responsabilidades  Responsabilidades fiscales (string separado por ";" o array).
     * @param  string|null  $direccion  Línea de dirección.
     * @param  string|null  $ciudadCodigo  Código DIAN de la ciudad.
     * @param  string|null  $departamentoCodigo  Código DIAN del departamento.
     * @param  string  $tipoIdentificacion  Código DIAN del tipo de identificación (13, 31, etc.).
     * @return DOMElement Nodo cac:PartyTaxScheme construido (aún no adjunto al árbol).
     */
    private function buildPartyTaxScheme(string $razonSocial, string $identificacion, ?string $dv, string|array $responsabilidades, ?string $direccion, ?string $ciudadCodigo, ?string $departamentoCodigo, string $tipoIdentificacion = '31'): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyTaxScheme');
        $this->appendCbc($node, 'RegistrationName', $razonSocial);

        $companyId = $this->appendCbc($node, 'CompanyID', $identificacion);
        $companyId->setAttribute('schemeAgencyID', '195');
        $companyId->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');
        if ($tipoIdentificacion === '31' && $dv !== null && $dv !== '') {
            $companyId->setAttribute('schemeID', (string) $dv);
        }
        $companyId->setAttribute('schemeName', $tipoIdentificacion);

        $responsabilidadesCsv = is_array($responsabilidades) ? implode(';', $responsabilidades) : $responsabilidades;
        $taxLevel = $this->appendCbc($node, 'TaxLevelCode', $responsabilidadesCsv);
        $taxLevel->setAttribute('listName', $responsabilidadesCsv);

        $registrationAddress = $this->buildAddressContent($direccion, $ciudadCodigo, $departamentoCodigo, 'cac:RegistrationAddress');
        $node->appendChild($registrationAddress);

        $taxScheme = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxScheme');
        $this->appendCbc($taxScheme, 'ID', '01');
        $this->appendCbc($taxScheme, 'Name', 'IVA');
        $node->appendChild($taxScheme);

        return $node;
    }

    /**
     * Construye cac:PartyLegalEntity.
     *
     * @param  string  $razonSocial  Razón social.
     * @param  string  $identificacion  Número de identificación (NIT/cédula/etc.).
     * @param  string|null  $dv  Dígito de verificación (solo aplica si $tipoIdentificacion es NIT/31).
     * @param  string|null  $prefijoPuntoFacturacion  Prefijo de numeración del emisor (código de sucursal ante la DIAN); si se indica, agrega cac:CorporateRegistrationScheme.
     * @param  string  $tipoIdentificacion  Código DIAN del tipo de identificación (13, 31, etc.).
     * @return DOMElement Nodo cac:PartyLegalEntity construido (aún no adjunto al árbol).
     */
    private function buildPartyLegalEntity(string $razonSocial, string $identificacion, ?string $dv, ?string $prefijoPuntoFacturacion = null, string $tipoIdentificacion = '31'): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyLegalEntity');
        $this->appendCbc($node, 'RegistrationName', $razonSocial);
        $companyId = $this->appendCbc($node, 'CompanyID', $identificacion);
        $companyId->setAttribute('schemeAgencyID', '195');
        $companyId->setAttribute('schemeAgencyName', 'CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)');
        if ($tipoIdentificacion === '31' && $dv !== null && $dv !== '') {
            $companyId->setAttribute('schemeID', (string) $dv);
        }
        $companyId->setAttribute('schemeName', $tipoIdentificacion);

        if ($prefijoPuntoFacturacion !== null && $prefijoPuntoFacturacion !== '') {
            $corporateRegistrationScheme = $this->doc->createElementNS(self::CAC_NS, 'cac:CorporateRegistrationScheme');
            $this->appendCbc($corporateRegistrationScheme, 'ID', $prefijoPuntoFacturacion);
            $node->appendChild($corporateRegistrationScheme);
        }

        return $node;
    }

    /**
     * Construye cac:Contact.
     *
     * @param  string|null  $nombre  Nombre de contacto.
     * @param  string|null  $telefono  Teléfono.
     * @param  string|null  $email  Correo electrónico.
     * @return DOMElement Nodo cac:Contact construido (aún no adjunto al árbol).
     */
    private function buildContact(?string $nombre, ?string $telefono, ?string $email): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:Contact');
        if ($nombre) {
            $this->appendCbc($node, 'Name', $nombre);
        }
        if ($telefono) {
            $this->appendCbc($node, 'Telephone', $telefono);
        }
        if ($email) {
            $this->appendCbc($node, 'ElectronicMail', $email);
        }

        return $node;
    }

    /**
     * Construye cac:PaymentMeans.
     *
     * @param  array  $paymentMeans  Datos del medio de pago (id, codigo, fecha_vencimiento, payment_id).
     * @return DOMElement Nodo cac:PaymentMeans construido (aún no adjunto al árbol).
     */
    private function buildPaymentMeans(array $paymentMeans): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:PaymentMeans');
        $this->appendCbc($node, 'ID', (string) ($paymentMeans['id'] ?? '1'));
        $this->appendCbc($node, 'PaymentMeansCode', (string) ($paymentMeans['codigo'] ?? '10'));
        if (! empty($paymentMeans['fecha_vencimiento'])) {
            $this->appendCbc($node, 'PaymentDueDate', $paymentMeans['fecha_vencimiento']);
        }
        if (! empty($paymentMeans['payment_id'])) {
            $this->appendCbc($node, 'PaymentID', $paymentMeans['payment_id']);
        }

        return $node;
    }

    /**
     * Construye cac:TaxTotal a nivel de documento para un impuesto.
     *
     * @param  string  $moneda  Código de moneda.
     * @param  float  $taxableAmount  Base gravable.
     * @param  float  $taxAmount  Valor del impuesto.
     * @param  string  $codigo  Código DIAN del impuesto.
     * @param  string  $nombre  Nombre del impuesto.
     * @param  float  $porcentaje  Porcentaje del impuesto.
     * @return DOMElement Nodo cac:TaxTotal construido (aún no adjunto al árbol).
     */
    private function buildTaxTotal(string $moneda, float $taxableAmount, float $taxAmount, string $codigo, string $nombre, float $porcentaje): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxTotal');
        $this->appendMoney($node, 'TaxAmount', $taxAmount, $moneda);

        $subtotal = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxSubtotal');
        $this->appendMoney($subtotal, 'TaxableAmount', $taxableAmount, $moneda);
        $this->appendMoney($subtotal, 'TaxAmount', $taxAmount, $moneda);

        $category = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxCategory');
        $this->appendCbc($category, 'Percent', number_format($porcentaje, 2, '.', ''));
        $scheme = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxScheme');
        $this->appendCbc($scheme, 'ID', $codigo);
        $this->appendCbc($scheme, 'Name', $nombre);
        $category->appendChild($scheme);
        $subtotal->appendChild($category);
        $node->appendChild($subtotal);

        return $node;
    }

    /**
     * Construye el total monetario del documento (LegalMonetaryTotal o RequestedMonetaryTotal).
     *
     * @param  string  $moneda  Código de moneda.
     * @param  array  $totales  Totales del documento (ver calcularTotales()).
     * @return DOMElement Nodo de totales construido (aún no adjunto al árbol).
     */
    private function buildMonetaryTotal(string $moneda, array $totales): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:' . self::MONETARY_TOTAL_ELEMENT[$this->tipo]);
        $this->appendMoney($node, 'LineExtensionAmount', $totales['line_extension_amount'], $moneda);
        $this->appendMoney($node, 'TaxExclusiveAmount', $totales['tax_exclusive_amount'], $moneda);
        $this->appendMoney($node, 'TaxInclusiveAmount', $totales['tax_inclusive_amount'], $moneda);
        if (($totales['allowance_total_amount'] ?? 0) > 0) {
            $this->appendMoney($node, 'AllowanceTotalAmount', $totales['allowance_total_amount'], $moneda);
        }
        if (($totales['charge_total_amount'] ?? 0) > 0) {
            $this->appendMoney($node, 'ChargeTotalAmount', $totales['charge_total_amount'], $moneda);
        }
        $this->appendMoney($node, 'PayableAmount', $totales['payable_amount'], $moneda);

        return $node;
    }

    /**
     * Construye cac:AllowanceCharge a nivel documento (cargo/descuento global, opcional).
     *
     * @param  int  $id  Consecutivo (1-indexed) entre los cargos/descuentos del documento.
     * @param  array  $cargo  Cargo/descuento ya calculado (ver buildCargosCalculados()).
     * @param  string  $moneda  Código de moneda.
     * @return DOMElement Nodo cac:AllowanceCharge construido (aún no adjunto al árbol).
     */
    private function buildDocumentAllowanceCharge(int $id, array $cargo, string $moneda): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:AllowanceCharge');
        $this->appendCbc($node, 'ID', (string) $id);
        $this->appendCbc($node, 'ChargeIndicator', $cargo['es_descuento'] ? 'false' : 'true');
        $this->appendCbc($node, 'AllowanceChargeReason', $cargo['motivo']);
        if ($cargo['porcentaje'] !== null) {
            $this->appendCbc($node, 'MultiplierFactorNumeric', number_format($cargo['porcentaje'], 2, '.', ''));
        }
        $this->appendMoney($node, 'Amount', $cargo['amount'], $moneda);
        if ($cargo['porcentaje'] !== null) {
            $this->appendMoney($node, 'BaseAmount', $cargo['base_amount'], $moneda);
        }

        return $node;
    }

    /**
     * Construye una línea del documento (InvoiceLine, CreditNoteLine o DebitNoteLine).
     *
     * @param  string  $moneda  Código de moneda.
     * @param  int  $id  Número de línea (1-indexed).
     * @param  array  $linea  Línea ya calculada (ver buildLineasCalculadas()).
     * @return DOMElement Nodo de línea construido (aún no adjunto al árbol).
     */
    private function buildLine(string $moneda, int $id, array $linea): DOMElement
    {
        $node = $this->doc->createElementNS(self::CAC_NS, 'cac:' . self::LINE_ELEMENT[$this->tipo]);
        $this->appendCbc($node, 'ID', (string) $id);

        $quantity = $this->appendCbc($node, self::QUANTITY_ELEMENT[$this->tipo], number_format($linea['cantidad'], 6, '.', ''));
        $quantity->setAttribute('unitCode', $linea['unidad_medida']);

        $this->appendMoney($node, 'LineExtensionAmount', $linea['line_extension_amount'], $moneda);

        if ($this->tipo === 'factura') {
            $this->appendLineAllowanceCharge($node, $linea, $moneda);
            $this->appendLineTaxTotal($node, $linea, $moneda);
        } else {
            $this->appendLineTaxTotal($node, $linea, $moneda);
            $this->appendLineAllowanceCharge($node, $linea, $moneda);
        }

        $item = $this->doc->createElementNS(self::CAC_NS, 'cac:Item');
        $this->appendCbc($item, 'Description', $linea['descripcion']);
        if ($linea['codigo']) {
            $sellersId = $this->doc->createElementNS(self::CAC_NS, 'cac:SellersItemIdentification');
            $this->appendCbc($sellersId, 'ID', $linea['codigo']);
            $item->appendChild($sellersId);
        }
        if (! empty($linea['codigo_barras'])) {
            // Código de barras del producto (EAN/UPC/GTIN, etc.) -- sin
            // schemeID porque no se conoce el estándar específico que use
            // cada empresa; la DIAN acepta el elemento sin ese atributo.
            $standardId = $this->doc->createElementNS(self::CAC_NS, 'cac:StandardItemIdentification');
            $this->appendCbc($standardId, 'ID', $linea['codigo_barras']);
            $item->appendChild($standardId);
        }
        $node->appendChild($item);

        $price = $this->doc->createElementNS(self::CAC_NS, 'cac:Price');
        $this->appendMoney($price, 'PriceAmount', $linea['precio_unitario'], $moneda);
        $baseQuantity = $this->appendCbc($price, 'BaseQuantity', '1.000000');
        $baseQuantity->setAttribute('unitCode', $linea['unidad_medida']);
        $node->appendChild($price);

        return $node;
    }

    /**
     * Agrega cac:AllowanceCharge a una línea, si tiene descuento.
     *
     * @param  DOMElement  $node  Elemento de línea al que se agrega.
     * @param  array  $linea  Línea ya calculada (ver buildLineasCalculadas()).
     * @param  string  $moneda  Código de moneda.
     */
    private function appendLineAllowanceCharge(DOMElement $node, array $linea, string $moneda): void
    {
        if ($linea['descuento_amount'] <= 0) {
            return;
        }

        $allowance = $this->doc->createElementNS(self::CAC_NS, 'cac:AllowanceCharge');
        $this->appendCbc($allowance, 'ID', '1');
        $this->appendCbc($allowance, 'ChargeIndicator', 'false');
        $this->appendCbc($allowance, 'AllowanceChargeReason', $linea['descuento_motivo']);
        if ($linea['descuento_porcentaje'] !== null) {
            $this->appendCbc($allowance, 'MultiplierFactorNumeric', number_format($linea['descuento_porcentaje'], 2, '.', ''));
        }
        $this->appendMoney($allowance, 'Amount', $linea['descuento_amount'], $moneda);
        if ($linea['descuento_porcentaje'] !== null) {
            $this->appendMoney($allowance, 'BaseAmount', $linea['base_amount'], $moneda);
        }
        $node->appendChild($allowance);
    }

    /**
     * Agrega cac:TaxTotal a una línea, si tiene impuestos.
     *
     * @param  DOMElement  $node  Elemento de línea al que se agrega.
     * @param  array  $linea  Línea ya calculada (ver buildLineasCalculadas()).
     * @param  string  $moneda  Código de moneda.
     */
    private function appendLineTaxTotal(DOMElement $node, array $linea, string $moneda): void
    {
        if (empty($linea['impuestos'])) {
            return;
        }

        $taxAmount = round(array_sum(array_column($linea['impuestos'], 'tax_amount')), 2);
        $taxTotal = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxTotal');
        $this->appendMoney($taxTotal, 'TaxAmount', $taxAmount, $moneda);
        foreach ($linea['impuestos'] as $impuesto) {
            $subtotal = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxSubtotal');
            $this->appendMoney($subtotal, 'TaxableAmount', $impuesto['base_gravable'], $moneda);
            $this->appendMoney($subtotal, 'TaxAmount', $impuesto['tax_amount'], $moneda);
            $category = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxCategory');
            $this->appendCbc($category, 'Percent', number_format($impuesto['porcentaje'], 2, '.', ''));
            $scheme = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxScheme');
            $this->appendCbc($scheme, 'ID', $impuesto['codigo']);
            $this->appendCbc($scheme, 'Name', $impuesto['nombre']);
            $category->appendChild($scheme);
            $subtotal->appendChild($category);
            $taxTotal->appendChild($subtotal);
        }
        $node->appendChild($taxTotal);
    }

    /**
     * Agrega un elemento cbc: con un valor de texto.
     *
     * @param  DOMElement  $parent  Elemento padre.
     * @param  string  $name  Nombre del elemento (sin prefijo).
     * @param  string  $value  Valor de texto.
     * @return DOMElement Elemento agregado.
     */
    private function appendCbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $el = $this->doc->createElementNS(self::CBC_NS, 'cbc:' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($el);

        return $el;
    }

    /**
     * Agrega un elemento sts: (u otro namespace indicado) con un valor de texto.
     *
     * @param  DOMElement  $parent  Elemento padre.
     * @param  string  $name  Nombre del elemento (sin prefijo).
     * @param  string  $value  Valor de texto.
     * @param  string  $ns  Namespace del elemento.
     * @param  string  $prefix  Prefijo del elemento.
     * @return DOMElement Elemento agregado.
     */
    private function appendSts(DOMElement $parent, string $name, string $value, string $ns = self::STS_NS, string $prefix = 'sts'): DOMElement
    {
        $el = $this->doc->createElementNS($ns, $prefix . ':' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($el);

        return $el;
    }

    /**
     * Agrega un elemento cbc: con un valor monetario y su atributo currencyID.
     *
     * @param  DOMElement  $parent  Elemento padre.
     * @param  string  $name  Nombre del elemento (sin prefijo).
     * @param  float  $amount  Monto.
     * @param  string  $moneda  Código de moneda.
     * @return DOMElement Elemento agregado.
     */
    private function appendMoney(DOMElement $parent, string $name, float $amount, string $moneda): DOMElement
    {
        $el = $this->appendCbc($parent, $name, number_format($amount, 2, '.', ''));
        $el->setAttribute('currencyID', $moneda);

        return $el;
    }

    /**
     * Calcula el SoftwareSecurityCode = SHA384(SoftwareID + PIN + NumFac).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  string  $numero  Número del documento.
     * @return string Código de seguridad del software.
     */
    private function calculateSoftwareSecurityCode(Company $company, string $numero): string
    {
        return hash('sha384', ($company->dian_software_id ?? '') . ($company->dian_pin ?? '') . $numero);
    }

    /**
     * Calcula el CUFE/CUDE del documento según la fórmula del anexo técnico de la DIAN.
     * Para facturas el penúltimo campo es la clave técnica de la resolución; para notas
     * crédito/débito es el Software-PIN de la empresa (no llevan clave técnica propia).
     *
     * @param  Company  $company  Empresa emisora.
     * @param  array  $payload  Payload del documento.
     * @param  string  $numero  Número del documento.
     * @param  string  $fecha  Fecha de emisión.
     * @param  string  $hora  Hora de emisión.
     * @param  array  $totales  Totales del documento.
     * @param  array  $impuestos  Impuestos agrupados.
     * @param  array  $customerParty  Datos del receptor.
     * @return string CUFE/CUDE del documento.
     */
    private function calculateCufe(Company $company, array $payload, string $numero, string $fecha, string $hora, array $totales, array $impuestos, array $customerParty): string
    {
        $porCodigo = collect($impuestos)->keyBy('codigo');

        $partes = [
            $numero,
            $fecha,
            $hora,
            number_format($totales['line_extension_amount'], 2, '.', ''),
        ];

        foreach (self::CUFE_TAX_ORDER as $codigo) {
            $partes[] = $codigo;
            $partes[] = number_format($porCodigo->get($codigo)['tax_amount'] ?? 0.0, 2, '.', '');
        }

        $partes[] = number_format($totales['payable_amount'], 2, '.', '');
        $partes[] = $company->identificacion;
        $partes[] = $customerParty['identificacion'] ?? '';
        $partes[] = $this->tipo === 'factura' ? ($payload['clave_tecnica'] ?? '') : ($company->dian_pin ?? '');
        $partes[] = $this->ambienteCode;

        return hash('sha384', implode('', $partes));
    }
}
