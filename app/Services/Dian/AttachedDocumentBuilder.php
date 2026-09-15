<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\DocumentoEmitido;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;

/**
 * Arma el "AttachedDocument" ("Contenedor de Factura Electrónica") que exige el Anexo Técnico
 * 1.9 de la DIAN para el envío de documentos electrónicos por correo -- un sobre UBL 2.1 que
 * envuelve, como CDATA, tanto el documento real (Invoice/CreditNote/DebitNote ya firmado) como
 * la respuesta de la DIAN (ApplicationResponse) que ya tenemos guardada. Mismo formato que
 * mandan los proveedores de quienes recibimos documentos (ver ReceivedDocumentParser, que lee
 * exactamente esta estructura) -- la estructura de este builder se verificó contra un
 * AttachedDocument real recibido de un proveedor, no se adivinó del anexo técnico solo.
 *
 * Solo se llama al momento de mandar el correo (ver DocumentIssuedMail::attachments()) -- no se
 * persiste en ningún lado, se arma y se firma cada vez.
 */
class AttachedDocumentBuilder
{
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';
    private const XADES141_NS = 'http://uri.etsi.org/01903/v1.4.1#';
    private const ROOT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2';

    private const PROFILE_ID = [
        '01' => 'Factura Electrónica de Venta',
        '02' => 'Factura Electrónica de Venta',
        '03' => 'Factura Electrónica de Venta',
        '04' => 'Factura Electrónica de Venta',
        '91' => 'Nota Crédito Electrónica',
        '92' => 'Nota Débito Electrónica',
    ];

    // El validador siempre es la DIAN misma (no algo que resolvamos por documento) -- mismo
    // texto que trae el ApplicationResponse real que ya guardamos.
    private const VALIDATOR_ID = 'Unidad Especial Dirección de Impuestos y Aduanas Nacionales';

    // "02" = "documento validado por la DIAN" -- el único código posible acá, porque
    // DocumentIssuedMail::attachments() solo se llama para documentos ya en STATUS_ACCEPTED (ver
    // DocumentoEmitidoController::sendEmail()).
    private const VALIDATION_RESULT_CODE = '02';

    private DOMDocument $doc;

    /**
     * @param  Company  $company  Empresa emisora (facturador).
     * @param  DocumentoEmitido  $documento  Documento ya aceptado por la DIAN -- debe traer
     *                                       "xml" (el documento firmado) y "response" (el
     *                                       ApplicationResponse que guardó IssueDocumentService).
     * @return string XML del AttachedDocument, sin firmar.
     */
    public function build(Company $company, DocumentoEmitido $documento): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');

        $root = $this->doc->createElementNS(self::ROOT_NS, 'AttachedDocument');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::DS_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::EXT_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', self::XADES_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades141', self::XADES141_NS);
        $this->doc->appendChild($root);

        // Vacío a propósito -- UblDocumentSigner::sign() busca este nodo por namespace y le
        // agrega su propio ext:UBLExtension con la firma, igual que hace con la factura.
        $root->appendChild($this->doc->createElementNS(self::EXT_NS, 'ext:UBLExtensions'));

        $ahoraColombia = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $numeral = $documento->numeral;
        $tipoDocumento = $documento->tipo_documento;
        $customer = $documento->payload['accounting_customer_party'] ?? [];

        $this->appendCbc($root, 'UBLVersionID', 'UBL 2.1');
        $this->appendCbc($root, 'CustomizationID', 'Documentos adjuntos');
        $this->appendCbc($root, 'ProfileID', self::PROFILE_ID[$tipoDocumento] ?? 'Factura Electrónica de Venta');
        $this->appendCbc($root, 'ProfileExecutionID', '1');
        $this->appendCbc($root, 'ID', $numeral);
        $this->appendCbc($root, 'IssueDate', $ahoraColombia->format('Y-m-d'));
        $this->appendCbc($root, 'IssueTime', $ahoraColombia->format('H:i:sP'));
        $this->appendCbc($root, 'DocumentType', 'Contenedor de Factura Electrónica');
        $this->appendCbc($root, 'ParentDocumentID', $numeral);

        $root->appendChild($this->buildParty('SenderParty', $company->name, $company->identification_type ?? '31', $company->identificacion, $company->dv, $company->fiscal_responsibilities ?? [], '01', 'IVA'));
        $root->appendChild($this->buildParty('ReceiverParty', $customer['razon_social'] ?? '', $customer['tipo_identificacion'] ?? '31', $customer['identificacion'] ?? '', $customer['dv'] ?? null, $customer['responsabilidades_fiscales'] ?? 'R-99-PN', 'ZZ', 'No aplica'));

        $root->appendChild($this->buildAttachment($documento->xml ?? ''));
        $root->appendChild($this->buildParentDocumentLineReference($documento));

        return $this->doc->saveXML();
    }

    /**
     * cac:SenderParty o cac:ReceiverParty -- ambos son la misma estructura simplificada (solo
     * cac:PartyTaxScheme, no el Party completo con dirección/contacto que sí lleva
     * AccountingSupplierParty/AccountingCustomerParty en el documento real).
     */
    private function buildParty(string $elementName, string $razonSocial, string $tipoIdentificacion, string $identificacion, ?string $dv, string|array $responsabilidades, string $taxSchemeId, string $taxSchemeName): DOMElement
    {
        $party = $this->doc->createElementNS(self::CAC_NS, 'cac:' . $elementName);
        $partyTaxScheme = $this->doc->createElementNS(self::CAC_NS, 'cac:PartyTaxScheme');

        $this->appendCbc($partyTaxScheme, 'RegistrationName', $razonSocial);

        $companyId = $this->appendCbc($partyTaxScheme, 'CompanyID', $identificacion);
        $companyId->setAttribute('schemeAgencyID', '195');
        if ($tipoIdentificacion === '31' && $dv !== null && $dv !== '') {
            $companyId->setAttribute('schemeID', (string) $dv);
        }
        $companyId->setAttribute('schemeName', $tipoIdentificacion);

        $responsabilidadesCsv = is_array($responsabilidades) ? implode(';', $responsabilidades) : $responsabilidades;
        $taxLevel = $this->appendCbc($partyTaxScheme, 'TaxLevelCode', $responsabilidadesCsv ?: 'R-99-PN');
        $taxLevel->setAttribute('listName', $responsabilidadesCsv ?: 'R-99-PN');

        $taxScheme = $this->doc->createElementNS(self::CAC_NS, 'cac:TaxScheme');
        $this->appendCbc($taxScheme, 'ID', $taxSchemeId);
        $this->appendCbc($taxScheme, 'Name', $taxSchemeName);
        $partyTaxScheme->appendChild($taxScheme);

        $party->appendChild($partyTaxScheme);

        return $party;
    }

    /**
     * cac:Attachment de primer nivel -- el documento real (factura/nota) envuelto como CDATA.
     */
    private function buildAttachment(string $documentXml): DOMElement
    {
        $attachment = $this->doc->createElementNS(self::CAC_NS, 'cac:Attachment');
        $externalReference = $this->doc->createElementNS(self::CAC_NS, 'cac:ExternalReference');
        $this->appendCbc($externalReference, 'MimeCode', 'text/xml');
        $this->appendCbc($externalReference, 'EncodingCode', 'UTF-8');

        $description = $this->doc->createElementNS(self::CBC_NS, 'cbc:Description');
        $description->appendChild($this->doc->createCDATASection($documentXml));
        $externalReference->appendChild($description);

        $attachment->appendChild($externalReference);

        return $attachment;
    }

    /**
     * cac:ParentDocumentLineReference -- trae la respuesta de la DIAN (ApplicationResponse)
     * envuelta como CDATA, más el resultado de la verificación (ver extractDianValidation() del
     * lado de recepción, que lee exactamente estos mismos campos).
     */
    private function buildParentDocumentLineReference(DocumentoEmitido $documento): DOMElement
    {
        $parentDocumentLineReference = $this->doc->createElementNS(self::CAC_NS, 'cac:ParentDocumentLineReference');
        $this->appendCbc($parentDocumentLineReference, 'LineID', '1');

        $documentReference = $this->doc->createElementNS(self::CAC_NS, 'cac:DocumentReference');
        $this->appendCbc($documentReference, 'ID', $documento->numeral);

        $uuid = $this->appendCbc($documentReference, 'UUID', (string) $documento->uuid);
        $uuid->setAttribute('schemeName', 'CUFE-SHA384');

        $this->appendCbc($documentReference, 'IssueDate', $documento->issue_date?->format('Y-m-d') ?? '');
        $this->appendCbc($documentReference, 'DocumentType', 'ApplicationResponse');

        $attachment = $this->doc->createElementNS(self::CAC_NS, 'cac:Attachment');
        $externalReference = $this->doc->createElementNS(self::CAC_NS, 'cac:ExternalReference');
        $this->appendCbc($externalReference, 'MimeCode', 'text/xml');
        $this->appendCbc($externalReference, 'EncodingCode', 'UTF-8');
        $description = $this->doc->createElementNS(self::CBC_NS, 'cbc:Description');
        $description->appendChild($this->doc->createCDATASection((string) $documento->response));
        $externalReference->appendChild($description);
        $attachment->appendChild($externalReference);
        $documentReference->appendChild($attachment);

        $resultOfVerification = $this->doc->createElementNS(self::CAC_NS, 'cac:ResultOfVerification');
        $this->appendCbc($resultOfVerification, 'ValidatorID', self::VALIDATOR_ID);
        $this->appendCbc($resultOfVerification, 'ValidationResultCode', self::VALIDATION_RESULT_CODE);
        $fechaValidacion = $documento->fecha_expedicion?->setTimezone('America/Bogota');
        $this->appendCbc($resultOfVerification, 'ValidationDate', $fechaValidacion?->format('Y-m-d') ?? '');
        $this->appendCbc($resultOfVerification, 'ValidationTime', $fechaValidacion?->format('H:i:sP') ?? '');
        $documentReference->appendChild($resultOfVerification);

        $parentDocumentLineReference->appendChild($documentReference);

        return $parentDocumentLineReference;
    }

    private function appendCbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $el = $this->doc->createElementNS(self::CBC_NS, 'cbc:' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($el);

        return $el;
    }

    /**
     * UblDocumentSigner::sign() canonicaliza (C14N) el documento completo para calcular la firma
     * -- y la canonicalización XML, por estándar, siempre convierte los CDATA en texto escapado
     * (&lt;, &gt;), sin importar el firmador. Nunca se había notado porque las facturas no
     * llevan CDATA adentro; este builder sí (los dos XML embebidos). La firma sigue siendo
     * válida igual (el hash se calcula sobre el mismo contenido canónico, con CDATA o sin él --
     * un verificador que vuelva a canonicalizar este documento para revisar la firma va a llegar
     * al mismo resultado), así que después de firmar se puede envolver ese texto de vuelta en
     * CDATA sin invalidar nada, solo para que quede legible/en el formato que se espera.
     *
     * @param  string  $signedXml  XML ya firmado por UblDocumentSigner::sign().
     * @return string Mismo XML, con los dos cbc:Description que traen un documento embebido
     *                envueltos en CDATA otra vez.
     */
    public function restoreCdataSections(string $signedXml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($signedXml);

        foreach ($doc->getElementsByTagNameNS(self::CBC_NS, 'Description') as $description) {
            $text = $description->textContent;

            if (! str_starts_with(trim($text), '<?xml')) {
                continue;
            }

            while ($description->firstChild) {
                $description->removeChild($description->firstChild);
            }

            $description->appendChild($doc->createCDATASection($text));
        }

        return $doc->saveXML();
    }
}
