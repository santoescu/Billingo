<?php

namespace App\Services\Dian;

use App\Models\Company;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class UblDocumentSigner
{
    private const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';
    private const XADES_NS = 'http://uri.etsi.org/01903/v1.3.2#';
    private const EXT_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const C14N_ALGORITHM = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const ENVELOPED_SIGNATURE_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';
    private const SIGNATURE_METHOD = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const DIGEST_METHOD = 'http://www.w3.org/2001/04/xmlenc#sha256';

    private const POLICY_URL = 'https://facturaelectronica.dian.gov.co/anexotecnico/modelovalidacionprevia/politicadefirma.pdf';
    private const POLICY_DESCRIPTION = 'Política de firma para facturas electrónicas de la República de Colombia';
    private const POLICY_HASH = 'dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y=';

    /**
     * Firma un XML UBL 2.1 sin firmar con el certificado de la empresa.
     *
     * @param  Company  $company  Empresa cuyo certificado DIAN se usa para firmar.
     * @param  string  $unsignedXml  XML UBL 2.1 ya armado por UblDocumentBuilder, sin firmar.
     * @return string XML firmado (XAdES-EPES), listo para comprimir y enviar a la DIAN.
     */
    public function sign(Company $company, string $unsignedXml): string
    {
        [$certPem, $keyPem, $extraCertsPem] = $this->loadCertificateChain($company);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($unsignedXml);

        $extensionsNode = $doc->getElementsByTagNameNS(self::EXT_NS, 'UBLExtensions')->item(0);
        if (! $extensionsNode) {
            throw new RuntimeException('El XML no tiene ext:UBLExtensions; no se puede firmar.');
        }

        $signatureId = 'xmldsig-' . $this->generateUuid();
        $signedPropsId = $signatureId . '-signedprops';

        $signatureNode = $doc->createElementNS(self::DS_NS, 'ds:Signature');
        $signatureNode->setAttribute('Id', $signatureId);
        $signatureNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', self::XADES_NS);

        $signedInfoNode = $doc->createElementNS(self::DS_NS, 'ds:SignedInfo');
        $canonicalizationMethod = $doc->createElementNS(self::DS_NS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::C14N_ALGORITHM);
        $signedInfoNode->appendChild($canonicalizationMethod);
        $signatureMethod = $doc->createElementNS(self::DS_NS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::SIGNATURE_METHOD);
        $signedInfoNode->appendChild($signatureMethod);

        $referenceDoc = $this->buildReference($doc, $signatureId . '-ref0', '', self::ENVELOPED_SIGNATURE_ALGORITHM);
        $signedInfoNode->appendChild($referenceDoc);

        $keyInfoNode = $this->buildKeyInfo($doc, $certPem, $extraCertsPem);

        $qualifyingProperties = $this->buildQualifyingProperties($doc, $signatureId, $signedPropsId, $certPem, $extraCertsPem);
        $referenceSignedProps = $this->buildReference($doc, null, '#' . $signedPropsId, self::C14N_ALGORITHM, null, 'http://uri.etsi.org/01903#SignedProperties');
        $signedInfoNode->appendChild($referenceSignedProps);

        $signatureNode->appendChild($signedInfoNode);

        $signatureValueNode = $doc->createElementNS(self::DS_NS, 'ds:SignatureValue');
        $signatureValueNode->setAttribute('Id', $signatureId . '-sigvalue');
        $signatureNode->appendChild($signatureValueNode);

        $signatureNode->appendChild($keyInfoNode);

        $objectNode = $doc->createElementNS(self::DS_NS, 'ds:Object');
        $objectNode->appendChild($qualifyingProperties);
        $signatureNode->appendChild($objectNode);

        $extensionNode = $doc->createElementNS(self::EXT_NS, 'ext:UBLExtension');
        $extensionContentNode = $doc->createElementNS(self::EXT_NS, 'ext:ExtensionContent');
        $extensionContentNode->appendChild($signatureNode);
        $extensionNode->appendChild($extensionContentNode);
        $extensionsNode->appendChild($extensionNode);

        $canonical = $doc->C14N(false, false);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($canonical);
        $doc->encoding = 'UTF-8';

        $doc->formatOutput = true;
        $formattedXml = $doc->saveXML();
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($formattedXml);
        $signatureNodeForStrip = $doc->getElementsByTagNameNS(self::DS_NS, 'Signature')->item(0);
        $this->stripWhitespaceRecursively($signatureNodeForStrip);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::DS_NS);
        $xpath->registerNamespace('xades', self::XADES_NS);
        $signedInfoNode = $xpath->query('//ds:SignedInfo')->item(0);
        $referenceDocDigestValue = $xpath->query(".//ds:Reference[@Id='{$signatureId}-ref0']/ds:DigestValue")->item(0);
        $referenceSignedPropsDigestValue = $xpath->query(".//ds:Reference[@URI='#{$signedPropsId}']/ds:DigestValue")->item(0);
        $signedPropsNode = $xpath->query("//xades:SignedProperties[@Id='{$signedPropsId}']")->item(0);
        $signatureValueNode = $xpath->query("//ds:SignatureValue[@Id='{$signatureId}-sigvalue']")->item(0);

        $signedPropsDigest = base64_encode(hash('sha256', $signedPropsNode->C14N(false, false), true));
        $referenceSignedPropsDigestValue->nodeValue = $signedPropsDigest;

        $cloneDoc = $doc->cloneNode(true);
        $clonedSignature = $cloneDoc->getElementsByTagNameNS(self::DS_NS, 'Signature')->item(0);
        $clonedSignature->parentNode->removeChild($clonedSignature);
        $documentDigest = base64_encode(hash('sha256', $cloneDoc->documentElement->C14N(false, false), true));
        $referenceDocDigestValue->nodeValue = $documentDigest;

        $canonicalSignedInfo = $signedInfoNode->C14N(false, false);
        $privateKey = openssl_pkey_get_private($keyPem);
        if (! $privateKey || ! openssl_sign($canonicalSignedInfo, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el documento con la llave de la empresa.');
        }
        $signatureValueNode->nodeValue = base64_encode($signature);

        return $doc->saveXML();
    }

    /**
     * Construye un elemento ds:Reference dentro de ds:SignedInfo.
     *
     * @param  DOMDocument  $doc  Documento sobre el que se crean los nodos.
     * @param  string|null  $id  Atributo Id de la referencia, o null para omitirlo.
     * @param  string  $uri  Atributo URI de la referencia (p. ej. "" o "#id").
     * @param  string|null  $transformAlgorithm  Algoritmo del ds:Transform a declarar, o null para omitir ds:Transforms.
     * @param  string|null  $digestValue  Valor inicial de ds:DigestValue (vacío si se calculará después).
     * @param  string|null  $type  Atributo Type de la referencia, o null para omitirlo.
     * @return DOMElement Nodo ds:Reference construido (aún no adjunto al árbol).
     */
    private function buildReference(DOMDocument $doc, ?string $id, string $uri, ?string $transformAlgorithm, ?string $digestValue = null, ?string $type = null): DOMElement
    {
        $reference = $doc->createElementNS(self::DS_NS, 'ds:Reference');
        if ($id !== null) {
            $reference->setAttribute('Id', $id);
        }
        if ($type !== null) {
            $reference->setAttribute('Type', $type);
        }
        $reference->setAttribute('URI', $uri);

        if ($transformAlgorithm !== null) {
            $transforms = $doc->createElementNS(self::DS_NS, 'ds:Transforms');
            $transform = $doc->createElementNS(self::DS_NS, 'ds:Transform');
            $transform->setAttribute('Algorithm', $transformAlgorithm);
            $transforms->appendChild($transform);
            $reference->appendChild($transforms);
        }

        $digestMethod = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $reference->appendChild($digestMethod);

        $reference->appendChild($doc->createElementNS(self::DS_NS, 'ds:DigestValue', $digestValue ?? ''));

        return $reference;
    }

    /**
     * Construye el elemento ds:KeyInfo con el certificado de firma.
     *
     * @param  DOMDocument  $doc  Documento sobre el que se crean los nodos.
     * @param  string  $certPem  Certificado de firma en formato PEM.
     * @param  array  $extraCertsPem  Certificados intermedios/raíz adicionales de la cadena, en PEM.
     * @return DOMElement Nodo ds:KeyInfo construido (aún no adjunto al árbol).
     */
    private function buildKeyInfo(DOMDocument $doc, string $certPem, array $extraCertsPem): DOMElement
    {
        $keyInfo = $doc->createElementNS(self::DS_NS, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS(self::DS_NS, 'ds:X509Data');

        foreach (array_merge([$certPem], $extraCertsPem) as $cert) {
            $x509Data->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509Certificate', $this->certToBase64Der($cert)));
        }

        $parsed = openssl_x509_parse($certPem);
        $issuerSerial = $doc->createElementNS(self::DS_NS, 'ds:X509IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509IssuerName', $this->formatDistinguishedName($parsed['issuer'] ?? [])));
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509SerialNumber', $this->serialNumberToDecimal($parsed)));
        $x509Data->appendChild($issuerSerial);
        $x509Data->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509SubjectName', $this->formatFullSubjectDistinguishedName($parsed['subject'] ?? [])));

        $keyInfo->appendChild($x509Data);

        return $keyInfo;
    }

    /**
     * Construye xades:QualifyingProperties (SignedProperties de XAdES-EPES).
     *
     * @param  DOMDocument  $doc  Documento sobre el que se crean los nodos.
     * @param  string  $signatureId  Id del ds:Signature al que apunta el atributo Target.
     * @param  string  $signedPropsId  Id que se asigna a xades:SignedProperties.
     * @param  string  $certPem  Certificado de firma en formato PEM.
     * @param  array  $extraCertsPem  Certificados intermedios/raíz adicionales de la cadena, en PEM.
     * @return DOMElement Nodo xades:QualifyingProperties construido (aún no adjunto al árbol).
     */
    private function buildQualifyingProperties(DOMDocument $doc, string $signatureId, string $signedPropsId, string $certPem, array $extraCertsPem): DOMElement
    {
        $qualifyingProperties = $doc->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);

        $signedProperties = $doc->createElementNS(self::XADES_NS, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', $signedPropsId);

        $signedSignatureProperties = $doc->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');

        $horaFirmaColombia = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $signedSignatureProperties->appendChild($doc->createElementNS(self::XADES_NS, 'xades:SigningTime', $horaFirmaColombia->format('Y-m-d\TH:i:s.vP')));

        $signingCertificate = $doc->createElementNS(self::XADES_NS, 'xades:SigningCertificate');
        foreach (array_merge([$certPem], $extraCertsPem) as $cert) {
            $signingCertificate->appendChild($this->buildXadesCert($doc, $cert));
        }
        $signedSignatureProperties->appendChild($signingCertificate);

        $signaturePolicyIdentifier = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyIdentifier');
        $signaturePolicyId = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyId');
        $sigPolicyId = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyId');
        $sigPolicyId->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Identifier', self::POLICY_URL));
        $sigPolicyId->appendChild($doc->createElementNS(self::XADES_NS, 'xades:Description', self::POLICY_DESCRIPTION));
        $signaturePolicyId->appendChild($sigPolicyId);
        $sigPolicyHash = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyHash');
        $policyDigestMethod = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $policyDigestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $sigPolicyHash->appendChild($policyDigestMethod);
        $sigPolicyHash->appendChild($doc->createElementNS(self::DS_NS, 'ds:DigestValue', self::POLICY_HASH));
        $signaturePolicyId->appendChild($sigPolicyHash);
        $signaturePolicyIdentifier->appendChild($signaturePolicyId);
        $signedSignatureProperties->appendChild($signaturePolicyIdentifier);

        $signerRole = $doc->createElementNS(self::XADES_NS, 'xades:SignerRole');
        $claimedRoles = $doc->createElementNS(self::XADES_NS, 'xades:ClaimedRoles');
        $claimedRoles->appendChild($doc->createElementNS(self::XADES_NS, 'xades:ClaimedRole', 'supplier'));
        $signerRole->appendChild($claimedRoles);
        $signedSignatureProperties->appendChild($signerRole);

        $signedProperties->appendChild($signedSignatureProperties);
        $qualifyingProperties->appendChild($signedProperties);

        return $qualifyingProperties;
    }

    /**
     * Construye xades:Cert (bloque de identificación del certificado de firma).
     *
     * @param  DOMDocument  $doc  Documento sobre el que se crean los nodos.
     * @param  string  $certPem  Certificado en formato PEM.
     * @return DOMElement Nodo xades:Cert construido (aún no adjunto al árbol).
     */
    private function buildXadesCert(DOMDocument $doc, string $certPem): DOMElement
    {
        $cert = $doc->createElementNS(self::XADES_NS, 'xades:Cert');

        $certDigest = $doc->createElementNS(self::XADES_NS, 'xades:CertDigest');
        $digestMethod = $doc->createElementNS(self::DS_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $certDigest->appendChild($digestMethod);
        $der = base64_decode($this->certToBase64Der($certPem));
        $certDigest->appendChild($doc->createElementNS(self::DS_NS, 'ds:DigestValue', base64_encode(hash('sha256', $der, true))));
        $cert->appendChild($certDigest);

        $parsed = openssl_x509_parse($certPem);
        $issuerSerial = $doc->createElementNS(self::XADES_NS, 'xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509IssuerName', $this->formatDistinguishedName($parsed['issuer'] ?? [])));
        $issuerSerial->appendChild($doc->createElementNS(self::DS_NS, 'ds:X509SerialNumber', $this->serialNumberToDecimal($parsed)));
        $cert->appendChild($issuerSerial);

        return $cert;
    }

    /**
     * Convierte el número de serie de un certificado (openssl_x509_parse()) a decimal.
     *
     * @param  array  $parsed  Resultado de openssl_x509_parse().
     * @return string Número de serie en base 10.
     */
    private function serialNumberToDecimal(array $parsed): string
    {
        $hex = $parsed['serialNumberHex'] ?? '0';

        $decimal = '0';
        foreach (str_split(strtoupper($hex)) as $char) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($char));
        }

        return $decimal;
    }

    /**
     * Formatea el emisor/subject de un certificado (campos estándar) como cadena "cn=...,o=...".
     *
     * @param  array  $components  Bloque 'issuer' o 'subject' de openssl_x509_parse().
     * @return string Distinguished Name formateado.
     */
    private function formatDistinguishedName(array $components): string
    {
        $map = [
            'CN' => 'cn', 'O' => 'o', 'OU' => 'ou', 'C' => 'c',
            'ST' => 'st', 'L' => 'l', 'street' => 'street',
        ];
        $order = ['CN', 'O', 'OU', 'C', 'ST', 'L', 'street'];

        $parts = [];
        foreach ($order as $key) {
            if (isset($components[$key]) && $components[$key] !== '') {
                $parts[] = $map[$key] . '=' . $components[$key];
            }
        }

        return implode(',', $parts);
    }

    /**
     * Formatea el subject completo de un certificado de persona natural de Certicámara
     * (incluye givenName, sn, serialNumber y los OIDs propios de Certicámara) como
     * cadena "cn=...,o=...", en el orden que espera la DIAN.
     *
     * @param  array  $components  Bloque 'subject' de openssl_x509_parse().
     * @return string Distinguished Name formateado.
     */
    private function formatFullSubjectDistinguishedName(array $components): string
    {
        $map = [
            'CN' => 'cn', 'O' => 'o', 'OU' => 'ou', 'C' => 'c',
            'ST' => 'st', 'L' => 'l', 'street' => 'street',
            'GN' => 'givenName', 'SN' => 'sn', 'serialNumber' => 'serialNumber',
        ];

        $certicamaraOids = ['1.3.6.1.4.1.23267.2.3', '1.3.6.1.4.1.23267.2.2'];

        $parts = [];
        foreach ($components as $key => $value) {
            if ($key === 'UNDEF') {
                foreach ((array) $value as $i => $undefValue) {
                    $parts[] = ($certicamaraOids[$i] ?? 'UNDEF') . '=' . $undefValue;
                }

                continue;
            }

            if ($value === '' || is_array($value)) {
                continue;
            }

            $parts[] = ($map[$key] ?? $key) . '=' . $value;
        }

        return implode(',', array_reverse($parts));
    }

    /**
     * Extrae el certificado PEM como base64 del DER, sin cabeceras ni saltos de línea.
     *
     * @param  string  $certPem  Certificado en formato PEM.
     * @return string Certificado en base64 (DER), en una sola línea.
     */
    private function certToBase64Der(string $certPem): string
    {
        return trim(preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $certPem));
    }

    /**
     * Carga el certificado y la llave privada del .p12/.pfx vigente de la
     * empresa (el más reciente entre los que no han vencido -- ver
     * Company::activeDianCertificate()).
     *
     * @param  Company  $company  Empresa cuyo certificado se va a cargar.
     * @return array{0: string, 1: string, 2: array} Certificado PEM, llave privada PEM, y certificados adicionales de la cadena.
     */
    private function loadCertificateChain(Company $company): array
    {
        $certificate = $company->activeDianCertificate();

        if (! $certificate) {
            throw new RuntimeException(__('You no longer have any valid certificates. Please add one before signing.'));
        }

        $certs = [];

        if (! openssl_pkcs12_read($certificate->content, $certs, $certificate->password)) {
            throw new RuntimeException(__('The password does not match this certificate.'));
        }

        return [$certs['cert'], $certs['pkey'], $certs['extracerts'] ?? []];
    }

    /**
     * Genera un UUID v4 aleatorio.
     *
     * @return string UUID v4 en formato estándar con guiones.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Elimina recursivamente los nodos de texto que son solo espacio en blanco
     * dentro de un subárbol, dejando el resto del documento formateado intacto.
     *
     * @param  DOMElement  $element  Elemento raíz del subárbol a limpiar.
     */
    private function stripWhitespaceRecursively(DOMElement $element): void
    {
        $child = $element->firstChild;
        while ($child !== null) {
            $next = $child->nextSibling;

            if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue) === '') {
                $element->removeChild($child);
            } elseif ($child instanceof DOMElement) {
                $this->stripWhitespaceRecursively($child);
            }

            $child = $next;
        }
    }
}
