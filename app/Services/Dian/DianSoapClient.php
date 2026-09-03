<?php

namespace App\Services\Dian;

use App\Models\Company;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class DianSoapClient
{
    private const WSA_NS = 'http://www.w3.org/2005/08/addressing';
    private const WSSE_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    private const WSU_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    private const SOAP_NS = 'http://www.w3.org/2003/05/soap-envelope';
    private const WCF_NS = 'http://wcf.dian.colombia';
    private const X509V3_VALUE_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';
    private const BASE64_ENCODING = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    private const EC_NS = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    private ?string $lastRawResponse = null;

    private const PRODUCTION_ENDPOINT = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';

    /**
     * Llama a una operación del servicio, firmando la petición con el
     * certificado de la empresa, y devuelve el cuerpo de la respuesta ya
     * parseado (sin los sobres SOAP/WS-Addressing). El endpoint se elige
     * según el ambiente configurado en la empresa (habilitación/producción),
     * salvo que se pase uno explícito (lo usa getAcquirer(), que siempre
     * fuerza producción sin importar el ambiente de la empresa).
     */
    public function call(Company $company, string $operation, string $bodyInnerXml, ?string $endpoint = null): SimpleXMLElement
    {
        [$certPem, $keyPem] = $this->loadCertificateAndKey($company);

        $endpoint ??= $this->endpointFor($company);
        $action = self::WCF_NS . '/IWcfDianCustomerServices/' . $operation;

        $envelope = $this->buildSignedEnvelope($endpoint, $action, $bodyInnerXml, $certPem, $keyPem);

        try {
            
            $response = Http::withBody(
                $envelope,
                'application/soap+xml;charset=utf-8'
            )->timeout(30)->retry(3, 1000)->post($endpoint);
        } catch (ConnectionException $e) {
            throw new RuntimeException(__('Could not connect to the DIAN service.'), 0, $e);
        }

        $this->lastRawResponse = $response->body();

        return $this->parseResponse($this->lastRawResponse, $operation);
    }

    /**
     * Consulta los rangos de numeración autorizados para la empresa
     * (servicio GetNumberingRange).
     */
    public function getNumberingRanges(Company $company): array
    {
        $body = sprintf(
            '<wcf:GetNumberingRange><wcf:accountCode>%s</wcf:accountCode><wcf:accountCodeT>%s</wcf:accountCodeT><wcf:softwareCode>%s</wcf:softwareCode></wcf:GetNumberingRange>',
            $this->x((string) $company->dianAccountCode()),
            $this->x((string) $company->dian_pin),
            $this->x((string) $company->dian_software_id),
        );

        $result = $this->call($company, 'GetNumberingRange', $body);

        $operationCode = (string) $result->OperationCode;
        $operationDescription = (string) $result->OperationDescription;

        if ($operationCode !== '100') {
            throw new RuntimeException($operationDescription ?: __('The DIAN rejected the request.'));
        }

        $ranges = [];

        foreach ($result->ResponseList->NumberRangeResponse ?? [] as $item) {
            $technicalKey = (string) $item->TechnicalKey;

            $ranges[] = [
                'resolution_number' => (string) $item->ResolutionNumber,
                'resolution_date' => (string) $item->ResolutionDate,
                'prefix' => (string) $item->Prefix,
                'range_from' => (int) $item->FromNumber,
                'range_to' => (int) $item->ToNumber,
                'valid_from' => (string) $item->ValidDateFrom,
                'valid_to' => (string) $item->ValidDateTo,
                'technical_key' => $technicalKey !== '' ? $technicalKey : null,
            ];
        }

        return $ranges;
    }

    /**
     * Consulta los datos de un adquiriente (cliente) por tipo y número de
     * identificación (servicio GetAcquirer): trae el nombre y el correo
     * reales registrados ante la DIAN. Este servicio solo existe en el
     * ambiente de producción de la DIAN (no en habilitación), así que
     * siempre se usa ese endpoint sin importar el ambiente configurado
     * para el resto de servicios.
     */
    public function getAcquirer(Company $company, string $identificationType, string $identificationNumber): array
    {
        $body = sprintf(
            '<wcf:GetAcquirer><wcf:identificationType>%s</wcf:identificationType><wcf:identificationNumber>%s</wcf:identificationNumber></wcf:GetAcquirer>',
            $this->x($identificationType),
            $this->x($identificationNumber),
        );

        $result = $this->call($company, 'GetAcquirer', $body, self::PRODUCTION_ENDPOINT);
        $acquirer = $result->GetAcquirerResult ?? $result;

        return [
            'name' => (string) ($acquirer->ReceiverName ?? ''),
            'email' => (string) ($acquirer->ReceiverEmail ?? ''),
            'status_code' => (string) ($acquirer->StatusCode ?? ''),
            'message' => (string) ($acquirer->Message ?? ''),
        ];
    }

    /**
     * Envía una factura electrónica (servicio SendBillSync) y devuelve el
     * resultado de la validación de la DIAN en la misma respuesta. $zipContent
     * son los bytes crudos (sin codificar) de un .zip que contiene el XML de
     * la factura ya firmado (firma del documento UBL, no de esta petición
     * SOAP) — esa firma y el armado del XML/zip se hacen antes de llamar a
     * este método.
     */
    public function sendBillSync(Company $company, string $fileName, string $zipContent): array
    {
        $body = sprintf(
            '<wcf:SendBillSync><wcf:fileName>%s</wcf:fileName><wcf:contentFile>%s</wcf:contentFile></wcf:SendBillSync>',
            $this->x($fileName),
            base64_encode($zipContent),
        );

        $result = $this->call($company, 'SendBillSync', $body);

        return $this->parseDianResponse($result->SendBillSyncResult ?? $result);
    }

    /**
     * Envía un evento sobre un documento ya recibido (servicio
     * SendEventUpdateStatus) — p. ej. acuse de recibo, aceptación expresa o
     * reclamo de una factura que le llegó a la empresa como comprador.
     * $zipContent son los bytes crudos (sin codificar) de un .zip que
     * contiene el XML del evento ya firmado.
     */
    public function sendEventUpdateStatus(Company $company, string $zipContent): array
    {
        $body = sprintf(
            '<wcf:SendEventUpdateStatus><wcf:contentFile>%s</wcf:contentFile></wcf:SendEventUpdateStatus>',
            base64_encode($zipContent),
        );

        $result = $this->call($company, 'SendEventUpdateStatus', $body);

        return $this->parseDianResponse($result->SendEventUpdateStatusResult ?? $result);
    }

    /**
     * Envía un documento de nómina electrónica (servicio SendNominaSync) y
     * recibe la validación de la DIAN al instante, igual que SendBillSync
     * pero para nómina. $zipContent son los bytes crudos (sin codificar) de
     * un .zip con el XML de nómina ya firmado.
     */
    public function sendNominaSync(Company $company, string $zipContent): array
    {
        $body = sprintf(
            '<wcf:SendNominaSync><wcf:contentFile>%s</wcf:contentFile></wcf:SendNominaSync>',
            base64_encode($zipContent),
        );

        $result = $this->call($company, 'SendNominaSync', $body);

        return $this->parseDianResponse($result->SendNominaSyncResult ?? $result);
    }

    /**
     * Envía un lote de documentos de prueba a la DIAN (servicio
     * SendTestSetAsync), como parte del proceso de habilitación como
     * facturador electrónico. A diferencia de SendBillSync, no valida al
     * instante: devuelve un ZipKey (trackId) para consultar el resultado
     * después con getStatusZip(). $zipContent son los bytes crudos (sin
     * codificar) de un .zip con el set de pruebas ya firmado.
     *
     * Este servicio SOLO existe en el ambiente de pruebas de la DIAN (tiene
     * sentido: es justo el paso de habilitación), así que siempre apunta ahí
     * sin importar el ambiente configurado en la empresa.
     */
    public function sendTestSetAsync(Company $company, string $fileName, string $zipContent, string $testSetId): array
    {
        $body = sprintf(
            '<wcf:SendTestSetAsync><wcf:fileName>%s</wcf:fileName><wcf:contentFile>%s</wcf:contentFile><wcf:testSetId>%s</wcf:testSetId></wcf:SendTestSetAsync>',
            $this->x($fileName),
            base64_encode($zipContent),
            $this->x($testSetId),
        );

        $result = $this->call($company, 'SendTestSetAsync', $body, config('services.dian.endpoint'));
        $response = $result->SendTestSetAsyncResult ?? $result;

        $errors = [];
        foreach ($response->ErrorMessageList->XmlParamsResponseTrackId ?? [] as $error) {
            $errors[] = [
                'document_key' => (string) ($error->DocumentKey ?? ''),
                'processed_message' => (string) ($error->ProcessedMessage ?? ''),
                'sender_code' => (string) ($error->SenderCode ?? ''),
                'success' => filter_var((string) ($error->Success ?? 'false'), FILTER_VALIDATE_BOOLEAN),
                'xml_file_name' => (string) ($error->XmlFileName ?? ''),
            ];
        }

        return [
            'zip_key' => (string) ($response->ZipKey ?? ''),
            'errors' => $errors,
        ];
    }

    /**
     * Consulta el estado de validación de un documento ya enviado (servicio
     * GetStatus), a partir del trackId/XmlDocumentKey que devolvió DIAN al
     * enviarlo.
     */
    public function getStatus(Company $company, string $trackId): array
    {
        $body = sprintf(
            '<wcf:GetStatus><wcf:trackId>%s</wcf:trackId></wcf:GetStatus>',
            $this->x($trackId),
        );

        $result = $this->call($company, 'GetStatus', $body);

        return $this->parseDianResponse($result->GetStatusResult ?? $result);
    }

    /**
     * Consulta el estado de validación de un lote (servicio GetStatusZip),
     * a partir del ZipKey/trackId que devolvió SendTestSetAsync (u otra
     * operación asíncrona). A diferencia de getStatus(), la DIAN puede
     * devolver varios resultados (uno por documento del lote). $endpoint se
     * puede forzar explícitamente (p. ej. para consultar el zipKey de un set
     * de pruebas, que siempre se envió al ambiente de pruebas sin importar
     * el ambiente configurado en la empresa).
     */
    public function getStatusZip(Company $company, string $trackId, ?string $endpoint = null): array
    {
        $body = sprintf(
            '<wcf:GetStatusZip><wcf:trackId>%s</wcf:trackId></wcf:GetStatusZip>',
            $this->x($trackId),
        );

        $result = $this->call($company, 'GetStatusZip', $body, $endpoint);
        $response = $result->GetStatusZipResult ?? $result;

        return array_map(
            fn (SimpleXMLElement $item) => $this->parseDianResponse($item),
            iterator_to_array($response->DianResponse ?? [], false)
        );
    }

    /**
     * Consulta la información completa de un documento electrónico ya
     * procesado por la DIAN (servicio GetDocumentInfo), a partir de su
     * clave técnica (uuid/CUFE). La respuesta trae datos de emisor,
     * receptor, totales, eventos, etc.: en vez de mapear a mano cada campo
     * anidado, se convierte todo a un array plano genérico.
     */
    public function getDocumentInfo(Company $company, string $uuid): array
    {
        $body = sprintf(
            '<wcf:GetDocumentInfo><wcf:uuid>%s</wcf:uuid></wcf:GetDocumentInfo>',
            $this->x($uuid),
        );

        $result = $this->call($company, 'GetDocumentInfo', $body);
        $response = $result->GetDocumentInfoResult ?? $result;

        return [
            'status_code' => (string) ($response->StatusCode ?? ''),
            'status_description' => (string) ($response->StatusDescription ?? ''),
            'documents' => array_map(
                fn (SimpleXMLElement $document) => $this->xmlToArray($document),
                iterator_to_array($response->DocumentInfo->Documento ?? [], false)
            ),
        ];
    }

    /**
     * Consulta el estado de validación de un evento ya enviado (servicio
     * GetStatusEvent) — p. ej. una nota crédito/débito o una actualización
     * de estado — a partir de su trackId.
     */
    public function getStatusEvent(Company $company, string $trackId): array
    {
        $body = sprintf(
            '<wcf:GetStatusEvent><wcf:trackId>%s</wcf:trackId></wcf:GetStatusEvent>',
            $this->x($trackId),
        );

        $result = $this->call($company, 'GetStatusEvent', $body);

        return $this->parseDianResponse($result->GetStatusEventResult ?? $result);
    }

    /**
     * Descarga el XML completo (comprimido, en base64) de un documento ya
     * procesado por la DIAN (servicio GetXmlByDocumentKey), a partir de su
     * trackId.
     */
    public function getXmlByDocumentKey(Company $company, string $trackId): array
    {
        $body = sprintf(
            '<wcf:GetXmlByDocumentKey><wcf:trackId>%s</wcf:trackId></wcf:GetXmlByDocumentKey>',
            $this->x($trackId),
        );

        $result = $this->call($company, 'GetXmlByDocumentKey', $body);
        $response = $result->GetXmlByDocumentKeyResult ?? $result;

        $xmlBytesBase64 = (string) ($response->XmlBytesBase64 ?? '');
        $zipContent = $xmlBytesBase64 !== '' ? base64_decode($xmlBytesBase64) : null;

        return [
            'code' => (string) ($response->Code ?? ''),
            'message' => (string) ($response->Message ?? ''),
            'validation_date' => (string) ($response->ValidationDate ?? ''),
            'zip_content' => $zipContent,
            'response_xml' => $this->extractXmlFromZip($zipContent),
        ];
    }

    /**
     * Consulta las notas crédito/débito referenciadas a una factura ya
     * enviada (servicio GetReferenceNotes), a partir de su trackId.
     */
    public function getReferenceNotes(Company $company, string $trackId): array
    {
        $body = sprintf(
            '<wcf:GetReferenceNotes><wcf:trackId>%s</wcf:trackId></wcf:GetReferenceNotes>',
            $this->x($trackId),
        );

        $result = $this->call($company, 'GetReferenceNotes', $body);

        return $this->parseDianResponse($result->GetReferenceNotesResult ?? $result);
    }

    /**
     * Convierte una respuesta tipo DianResponse (la usan SendBillSync,
     * GetStatus, GetStatusEvent, GetReferenceNotes, SendNominaSync, etc.)
     * en un array simple.
     */
    private function parseDianResponse(SimpleXMLElement $response): array
    {
        $errorMessages = [];
        foreach ($response->ErrorMessage->string ?? [] as $message) {
            $errorMessages[] = (string) $message;
        }

        $xmlBase64Bytes = (string) ($response->XmlBase64Bytes ?? '');
        $zipContent = $xmlBase64Bytes !== '' ? base64_decode($xmlBase64Bytes) : null;

        return [
            'is_valid' => filter_var((string) ($response->IsValid ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'status_code' => (string) ($response->StatusCode ?? ''),
            'status_description' => (string) ($response->StatusDescription ?? ''),
            'status_message' => (string) ($response->StatusMessage ?? ''),
            'error_messages' => $errorMessages,
            'xml_document_key' => (string) ($response->XmlDocumentKey ?? ''),
            'xml_file_name' => (string) ($response->XmlFileName ?? ''),
            'raw_response' => $this->lastRawResponse,
            
            'response_xml' => $this->extractXmlFromZip($zipContent),
        ];
    }

    /**
     * Desempaqueta el primer archivo dentro de un .zip en memoria (así vienen
     * la mayoría de los XML que devuelve la DIAN, en base64 dentro de
     * XmlBase64Bytes/XmlBytesBase64). Algunos servicios (confirmado en
     * GetXmlByDocumentKey) devuelven el XML plano directamente ahí, sin
     * comprimir -- en ese caso, $zipContent ya es el XML y se devuelve tal cual.
     *
     * @param  string|null  $zipContent  Bytes crudos de un .zip, o el XML plano, o null si no vino ninguno.
     * @return string|null Contenido del XML, o null si no se pudo leer.
     */
    private function extractXmlFromZip(?string $zipContent): ?string
    {
        if (! $zipContent) {
            return null;
        }

        if (! str_starts_with($zipContent, 'PK')) {
            return $zipContent;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'dian_response_');
        file_put_contents($tmpPath, $zipContent);

        $zip = new ZipArchive();

        if ($zip->open($tmpPath) !== true) {
            unlink($tmpPath);

            return $zipContent;
        }

        $xml = $zip->numFiles > 0 ? $zip->getFromIndex(0) : false;
        $zip->close();
        unlink($tmpPath);

        return $xml !== false ? $xml : $zipContent;
    }

    /**
     * Convierte un SimpleXMLElement con estructura anidada arbitraria (como
     * la respuesta de GetDocumentInfo: emisor, receptor, totales, eventos...)
     * en un array plano recursivo, sin tener que mapear cada campo a mano.
     */
    private function xmlToArray(SimpleXMLElement $node): array|string
    {
        $children = $node->children();

        if (count($children) === 0) {
            return (string) $node;
        }

        $result = [];
        foreach ($children as $name => $child) {
            $value = $this->xmlToArray($child);

            if (isset($result[$name])) {
                if (! is_array($result[$name]) || ! array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Extrae el certificado y la llave privada del .p12 vigente de la
     * empresa (el más reciente entre los que no han vencido -- ver
     * Company::activeDianCertificate()), en formato PEM directamente de
     * openssl_pkcs12_read().
     */
    private function loadCertificateAndKey(Company $company): array
    {
        $certificate = $company->activeDianCertificate();

        if (! $certificate) {
            throw new RuntimeException(__('You no longer have any valid certificates. Please add one before signing.'));
        }

        $certs = [];

        if (! openssl_pkcs12_read($certificate->content, $certs, $certificate->password)) {
            throw new RuntimeException(__('The password does not match this certificate.'));
        }

        return [$certs['cert'], $certs['pkey']];
    }

    private function buildSignedEnvelope(string $endpoint, string $action, string $bodyInnerXml, string $certPem, string $keyPem): string
    {
        $created = gmdate('Y-m-d\TH:i:s.000\Z');
        $expires = gmdate('Y-m-d\TH:i:s.000\Z', time() + 300);
        $timestampId = 'Timestamp-' . $this->generateUuid();

        $envelopeXml = <<<XML
<soap:Envelope xmlns:soap="{$this->x(self::SOAP_NS)}" xmlns:wcf="{$this->x(self::WCF_NS)}">
   <soap:Header xmlns:wsa="{$this->x(self::WSA_NS)}">
      <wsse:Security xmlns:wsse="{$this->x(self::WSSE_NS)}" xmlns:wsu="{$this->x(self::WSU_NS)}">
         <wsu:Timestamp wsu:Id="{$timestampId}">
            <wsu:Created>{$created}</wsu:Created>
            <wsu:Expires>{$expires}</wsu:Expires>
         </wsu:Timestamp>
      </wsse:Security>
      <wsa:Action>{$this->x($action)}</wsa:Action>
      <wsa:To>{$this->x($endpoint)}</wsa:To>
   </soap:Header>
   <soap:Body>
      {$bodyInnerXml}
   </soap:Body>
</soap:Envelope>
XML;

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($envelopeXml);

        $toNode = $doc->getElementsByTagNameNS(self::WSA_NS, 'To')->item(0);
        $securityNode = $doc->getElementsByTagNameNS(self::WSSE_NS, 'Security')->item(0);
        $timestampNode = $doc->getElementsByTagNameNS(self::WSU_NS, 'Timestamp')->item(0);

        $this->signToHeader($doc, $toNode, $securityNode, $timestampNode, $certPem, $keyPem);

        return $doc->saveXML();
    }

    /**
     * Firma el header wsa:To (tal como exige sp:SignedParts en la política)
     * usando RSA-SHA256 + canonicalización exclusiva. Referencia el
     * certificado incluyéndolo completo como wsse:BinarySecurityToken (Key
     * Identifier Type = "Binary Security Token", tal como lo indica la guía
     * oficial de la DIAN para consumir sus servicios). Orden final dentro de
     * wsse:Security: Timestamp, BinarySecurityToken, Signature (verificado
     * contra una petición real firmada con este mismo certificado y
     * aceptada por la DIAN).
     */
    private function signToHeader(DOMDocument $doc, DOMElement $toNode, DOMElement $securityNode, DOMElement $timestampNode, string $certPem, string $keyPem): void
    {
        
        $toId = 'To-' . $this->generateUuid();
        $toNode->setAttributeNS(self::WSU_NS, 'wsu:Id', $toId);
        $toNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', self::WSU_NS);

        $bstId = 'X509-' . $this->generateUuid();
        $bstNode = $this->buildBinarySecurityToken($doc, $certPem, $bstId);
        $securityNode->appendChild($bstNode);

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($keyPem, false, false);

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference($toNode, XMLSecurityDSig::SHA256, ['http://www.w3.org/2001/10/xml-exc-c14n#'], [
            'id_name' => 'Id',
            'prefix' => 'wsu',
            'prefix_ns' => self::WSU_NS,
            'overwrite' => false,
        ]);
        $dsig->sign($key);

        $signatureNode = $doc->importNode($dsig->sigNode, true);
        $securityNode->appendChild($signatureNode);

        $this->stripWhitespaceTextNodes($signatureNode);

        $this->addInclusiveNamespacesToTransform($signatureNode, $toNode, ['soap', 'wcf']);

        $this->recomputeSignatureValue($signatureNode, $key);

        $signatureNode->setAttribute('Id', 'SIG-' . $this->generateUuid());

        $this->replaceKeyInfoWithBinarySecurityTokenReference($signatureNode, $bstId);
    }

    /**
     * Quita recursivamente los nodos de texto que son solo espacio en
     * blanco (indentación) dentro de $node, dejando el XML compacto.
     */
    private function stripWhitespaceTextNodes(DOMElement $node): void
    {
        $toRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '') {
                $toRemove[] = $child;
            } elseif ($child instanceof DOMElement) {
                $this->stripWhitespaceTextNodes($child);
            }
        }

        foreach ($toRemove as $child) {
            $node->removeChild($child);
        }
    }

    /**
     * Agrega <ec:InclusiveNamespaces PrefixList="..."/> dentro del único
     * ds:Transform de exc-c14n que xmlseclibs generó para la referencia de
     * $node, y recalcula el ds:DigestValue a mano (addReference() ya lo
     * había calculado sin la lista, así que queda desactualizado).
     */
    private function addInclusiveNamespacesToTransform(DOMElement $signatureNode, DOMElement $node, array $prefixes): void
    {
        $doc = $signatureNode->ownerDocument;

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $transform = $xpath->query('.//ds:Reference/ds:Transforms/ds:Transform', $signatureNode)->item(0);
        $digestValueNode = $xpath->query('.//ds:Reference/ds:DigestValue', $signatureNode)->item(0);

        $inclusiveNamespaces = $doc->createElementNS(self::EC_NS, 'ec:InclusiveNamespaces');
        $inclusiveNamespaces->setAttribute('PrefixList', implode(' ', $prefixes));
        $transform->appendChild($inclusiveNamespaces);

        $canonical = $node->C14N(true, false, null, $prefixes);
        $digestValueNode->nodeValue = base64_encode(hash('sha256', $canonical, true));
    }

    /**
     * XMLSecurityDSig::sign() calcula el SignatureValue canonicalizando el
     * SignedInfo mientras el nodo todavía vive aislado en el documento
     * interno de xmlseclibs (sin los namespaces ancestro del sobre real
     * disponibles), así que ese valor queda obsoleto en cuanto se importa el
     * nodo al documento real. Se recalcula a mano, canonicalizando el
     * SignedInfo ya adjunto (sin lista de InclusiveNamespaces: ni WSS4J ni
     * Chilkat la usan a este nivel, solo en la Reference).
     */
    private function recomputeSignatureValue(DOMElement $signatureNode, XMLSecurityKey $key): void
    {
        $doc = $signatureNode->ownerDocument;

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);
        $signedInfoNode = $xpath->query('.//ds:SignedInfo', $signatureNode)->item(0);
        $signatureValueNode = $xpath->query('.//ds:SignatureValue', $signatureNode)->item(0);

        $canonicalSignedInfo = $signedInfoNode->C14N(true, false);
        $signatureValueNode->nodeValue = base64_encode($key->signData($canonicalSignedInfo));
    }

    /**
     * Arma el wsse:BinarySecurityToken con el certificado completo (DER en
     * base64), tal como lo pide el "Key Identifier Type: Binary Security
     * Token" de la configuración oficial de la DIAN.
     */
    private function buildBinarySecurityToken(DOMDocument $doc, string $certPem, string $id): DOMElement
    {
        $base64Der = trim(preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $certPem));

        $bst = $doc->createElementNS(self::WSSE_NS, 'wsse:BinarySecurityToken', $base64Der);
        $bst->setAttribute('EncodingType', self::BASE64_ENCODING);
        $bst->setAttribute('ValueType', self::X509V3_VALUE_TYPE);
        $bst->setAttributeNS(self::WSU_NS, 'wsu:Id', $id);

        return $bst;
    }

    /**
     * xmlseclibs agrega por defecto un <ds:KeyInfo> vacío. Lo reemplazamos
     * con una referencia (wsse:Reference) al wsse:BinarySecurityToken ya
     * incluido en el mensaje, en vez de embeber el certificado de nuevo.
     */
    private function replaceKeyInfoWithBinarySecurityTokenReference(DOMElement $signatureNode, string $bstId): void
    {
        $doc = $signatureNode->ownerDocument;

        $existingKeyInfo = $signatureNode->getElementsByTagName('KeyInfo')->item(0);
        if ($existingKeyInfo) {
            $signatureNode->removeChild($existingKeyInfo);
        }

        $keyInfo = $doc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'KeyInfo');
        $keyInfo->setAttribute('Id', 'KI-' . $this->generateUuid());
        $str = $doc->createElementNS(self::WSSE_NS, 'wsse:SecurityTokenReference');
        $str->setAttributeNS(self::WSU_NS, 'wsu:Id', 'STR-' . $this->generateUuid());
        $reference = $doc->createElementNS(self::WSSE_NS, 'wsse:Reference');
        $reference->setAttribute('URI', '#' . $bstId);
        $reference->setAttribute('ValueType', self::X509V3_VALUE_TYPE);

        $str->appendChild($reference);
        $keyInfo->appendChild($str);
        $signatureNode->appendChild($keyInfo);
    }

    /**
     * El XML de respuesta usa varios prefijos de namespace. Para no lidiar
     * con el registro de namespaces en SimpleXML, quitamos los prefijos de
     * las etiquetas antes de parsear.
     */
    private function parseResponse(string $xml, string $operation): SimpleXMLElement
    {
        $withoutPrefixes = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $xml);

        try {
            $doc = new SimpleXMLElement($withoutPrefixes);
        } catch (\Exception $e) {
            throw new RuntimeException(__('The DIAN service returned an unexpected response.'));
        }

        $result = $doc->Body->{$operation . 'Response'}->{$operation . 'Result'} ?? null;

        if (! $result) {
            
            return $doc->Body;
        }

        return $result;
    }

    private function endpointFor(Company $company): string
    {
        return $company->dian_environment === Company::DIAN_AMBIENTE_PRODUCCION
            ? self::PRODUCTION_ENDPOINT
            : config('services.dian.endpoint');
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES);
    }
}
