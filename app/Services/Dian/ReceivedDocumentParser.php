<?php

namespace App\Services\Dian;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class ReceivedDocumentParser
{
    /**
     * Lee un archivo subido por el usuario (XML suelto o el .zip que casi siempre manda el
     * proveedor, con el AttachedDocument y el PDF adentro) y lo traduce al mismo arreglo que
     * parse(), agregando "xml" (el XML real, ya desenvuelto si venía en un .zip) y "pdf" (bytes
     * en base64, o null si el .zip no traía uno -- o si el archivo era un XML suelto).
     *
     * @param  string  $path  Ruta del archivo subido (UploadedFile::getRealPath()).
     * @param  string  $extension  Extensión del archivo, en minúsculas ("xml" o "zip").
     * @return array Mismas claves que parse(), más "xml" y "pdf".
     *
     * @throws InvalidArgumentException Si el .zip no trae ningún XML adentro, o si el XML no se puede parsear.
     */
    public function parseUploadedFile(string $path, string $extension): array
    {
        [$xml, $pdf] = $extension === 'zip'
            ? $this->extractFromZip($path)
            : [file_get_contents($path), null];

        return array_merge($this->parse($xml), [
            'xml' => $xml,
            'pdf' => $pdf !== null ? base64_encode($pdf) : null,
        ]);
    }

    /**
     * @param  string  $path
     * @return array{0: string, 1: string|null} [contenido del XML, contenido del PDF o null].
     *
     * @throws InvalidArgumentException Si el .zip no se puede abrir o no trae ningún XML adentro.
     */
    private function extractFromZip(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('No se pudo abrir el archivo .zip.');
        }

        $xml = null;
        $pdf = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($xml === null && str_ends_with(strtolower($entryName), '.xml')) {
                $xml = $zip->getFromIndex($i);
            } elseif ($pdf === null && str_ends_with(strtolower($entryName), '.pdf')) {
                $pdf = $zip->getFromIndex($i);
            }
        }

        $zip->close();

        if ($xml === null) {
            throw new InvalidArgumentException('El archivo .zip no trae ningún XML adentro.');
        }

        return [$xml, $pdf ?: null];
    }

    /**
     * Traduce el XML UBL 2.1 de una factura/nota recibida (mandada por un proveedor) a un
     * arreglo plano, listo para guardar en DocumentoRecibido -- mismo criterio de "quitar
     * prefijos de namespace antes de parsear" que ya usa IssueDocumentService::parseSignedXml()
     * para no tener que registrar cada namespace a mano.
     *
     * DIAN entrega las facturas por correo envueltas en un "AttachedDocument" ("Contenedor de
     * Factura Electrónica"), que trae DOS documentos adjuntos como CDATA:
     * - cac:Attachment/cac:ExternalReference/cbc:Description: el documento real (Invoice,
     *   CreditNote o DebitNote) -- de ahí se sacan el UUID, las líneas, el emisor/receptor y los
     *   totales, igual que del lado emisión.
     * - cac:ParentDocumentLineReference/cac:DocumentReference (con DocumentType
     *   "ApplicationResponse") trae, en su propio Attachment/ExternalReference/Description, la
     *   respuesta de la DIAN a ese documento (aceptado/con observaciones) -- así que la
     *   validación viene incluida en el mismo archivo, sin tener que consultarla aparte contra
     *   la DIAN (ver extractDianValidation()).
     *
     * Si el XML que se sube NO es un AttachedDocument (el documento suelto, sin el sobre), se
     * parsea igual pero "dian_validation" queda en null -- esa validación solo viene en el sobre.
     *
     * @param  string  $xml  Contenido crudo del archivo XML subido.
     * @return array Datos planos del documento (ver claves del arreglo de retorno).
     *
     * @throws InvalidArgumentException Si el XML no se puede parsear o no trae los campos mínimos.
     */
    public function parse(string $xml): array
    {
        $root = $this->parseWithoutNamespacePrefixes($xml);
        $isAttachedDocument = $root->getName() === 'AttachedDocument';

        $document = $root;
        if ($isAttachedDocument) {
            $inner = trim((string) ($root->Attachment->ExternalReference->Description ?? ''));

            if ($inner === '') {
                throw new InvalidArgumentException('El AttachedDocument no trae el documento real en Attachment.ExternalReference.Description.');
            }

            $document = $this->parseWithoutNamespacePrefixes($inner);
        }

        $tipoEstructura = match ($document->getName()) {
            'Invoice' => 'factura',
            'CreditNote' => 'nota_credito',
            'DebitNote' => 'nota_debito',
            default => throw new InvalidArgumentException("Tipo de documento UBL no soportado: \"{$document->getName()}\"."),
        };
        $tipoDocumento = $this->extractTipoDocumentoCode($document, $tipoEstructura);

        $uuid = (string) ($document->UUID ?? '');
        $numeroCompleto = (string) ($document->ID ?? throw new InvalidArgumentException('El documento no trae "cbc:ID" (número).'));
        $prefix = $this->extractPrefix($document);
        $secuencial = ($prefix !== '' && str_starts_with($numeroCompleto, $prefix))
            ? substr($numeroCompleto, strlen($prefix))
            : $numeroCompleto;

        $supplierParty = $document->AccountingSupplierParty->Party ?? null;
        if (! $supplierParty) {
            throw new InvalidArgumentException('El documento no trae "cac:AccountingSupplierParty".');
        }

        $customerParty = $document->AccountingCustomerParty->Party ?? null;
        if (! $customerParty) {
            throw new InvalidArgumentException('El documento no trae "cac:AccountingCustomerParty".');
        }

        $taxTotal = 0.0;
        foreach ($document->TaxTotal as $bloque) {
            $taxTotal += (float) $bloque->TaxAmount;
        }

        $monetaryTotal = $document->LegalMonetaryTotal ?? $document->RequestedMonetaryTotal ?? null;
        $paymentMeansList = $this->extractPaymentMeansList($document);

        return [
            'uuid' => $uuid,
            'tipo_documento' => $tipoDocumento,
            'prefix' => $prefix,
            'numeral' => $numeroCompleto,
            'secuencial' => $secuencial,
            'issue_date' => (string) ($document->IssueDate ?? ''),
            'due_date' => (string) ($document->DueDate ?? $paymentMeansList[0]['fecha_vencimiento'] ?? ''),
            'currency' => (string) ($document->DocumentCurrencyCode ?? 'COP'),
            'subtotal' => (float) ($monetaryTotal->LineExtensionAmount ?? 0),
            'tax_total' => $taxTotal,
            'total' => (float) ($monetaryTotal->PayableAmount ?? 0),
            'lineas' => $this->extractLineas($document, $tipoEstructura),
            'dian_validation' => $isAttachedDocument ? $this->extractDianValidation($root) : null,
            'payload' => [
                // Mismo shape que "accounting_customer_party" del lado emisión (ver
                // DocumentJsonMapper::resolveCustomerParty()) para ambos -- así los dos lados del
                // negocio hablan el mismo vocabulario de campos. "accounting_customer_party" acá
                // es QUIÉN RECIBIÓ el documento según el XML (se valida en el controller que sea
                // la empresa activa antes de guardarlo -- ver DocumentoRecibidoController::store()),
                // no tiene nada que ver con los clientes de emisión.
                'accounting_supplier_party' => $this->extractParty($supplierParty),
                'accounting_customer_party' => $this->extractParty($customerParty),
                'payment_means_list' => $paymentMeansList,
                'notas' => $this->extractNotas($document),
                'cargos_descuentos' => $this->extractCargosDescuentos($document),
                'orden_referencia' => $this->extractOrderReference($document),
                'referencias' => $this->extractBillingReference($document),
            ],
        ];
    }

    /**
     * "cac:PaymentMeans" (uno o varios) -- mismo shape que usa emisión
     * (DocumentJsonMapper::mapPaymentMeansList()) para que ambos lados hablen el mismo
     * vocabulario.
     *
     * @param  SimpleXMLElement  $document
     * @return array
     */
    private function extractPaymentMeansList(SimpleXMLElement $document): array
    {
        $lista = [];
        foreach ($document->PaymentMeans ?? [] as $paymentMeans) {
            $lista[] = [
                'id' => (string) ($paymentMeans->ID ?? ''),
                'codigo' => (string) ($paymentMeans->PaymentMeansCode ?? ''),
                'fecha_vencimiento' => (string) ($paymentMeans->PaymentDueDate ?? ''),
                'payment_id' => (string) ($paymentMeans->PaymentID ?? ''),
            ];
        }

        return $lista;
    }

    /**
     * "cbc:Note" del documento (una o varias) -- observaciones libres que escribió el proveedor.
     *
     * @param  SimpleXMLElement  $document
     * @return array<int, string>
     */
    private function extractNotas(SimpleXMLElement $document): array
    {
        $notas = [];
        foreach ($document->Note ?? [] as $nota) {
            $texto = trim((string) $nota);
            if ($texto !== '') {
                $notas[] = $texto;
            }
        }

        return $notas;
    }

    /**
     * "cac:AllowanceCharge" a nivel documento (cargos/descuentos globales) -- mismo shape que usa
     * emisión (DocumentJsonMapper::mapCargosDescuentos()).
     *
     * @param  SimpleXMLElement  $document
     * @return array
     */
    private function extractCargosDescuentos(SimpleXMLElement $document): array
    {
        $cargos = [];
        foreach ($document->AllowanceCharge ?? [] as $item) {
            $esCargo = filter_var((string) ($item->ChargeIndicator ?? 'false'), FILTER_VALIDATE_BOOLEAN);

            $cargos[] = [
                'tipo' => $esCargo ? 'cargo' : 'descuento',
                'motivo' => (string) ($item->AllowanceChargeReason ?? ''),
                'codigo_razon' => (string) ($item->AllowanceChargeReasonCode ?? ''),
                'porcentaje' => (float) ($item->MultiplierFactorNumeric ?? 0),
                'amount' => (float) ($item->Amount ?? 0),
                'base_amount' => (float) ($item->BaseAmount ?? 0),
            ];
        }

        return $cargos;
    }

    /**
     * "cac:OrderReference" -- referencia opcional a una orden de compra (no tributaria).
     *
     * @param  SimpleXMLElement  $document
     * @return array|null
     */
    private function extractOrderReference(SimpleXMLElement $document): ?array
    {
        if (! isset($document->OrderReference)) {
            return null;
        }

        return [
            'id' => (string) ($document->OrderReference->ID ?? ''),
            'issue_date' => (string) ($document->OrderReference->IssueDate ?? ''),
        ];
    }

    /**
     * Referencia a la factura original, para notas crédito/débito -- "cac:BillingReference",
     * "cac:DiscrepancyResponse" e "cac:InvoicePeriod" (mismo shape que usa emisión, ver
     * UblDocumentBuilder::buildBillingReference()/buildDiscrepancyResponse()/buildInvoicePeriod()).
     *
     * @param  SimpleXMLElement  $document
     * @return array|null Null si el documento no trae ninguno de los tres bloques (ej. es una factura).
     */
    private function extractBillingReference(SimpleXMLElement $document): ?array
    {
        $billingReference = $document->BillingReference->InvoiceDocumentReference ?? null;
        $discrepancyResponse = $document->DiscrepancyResponse ?? null;
        $invoicePeriod = $document->InvoicePeriod ?? null;

        if (! $billingReference && ! $discrepancyResponse && ! $invoicePeriod) {
            return null;
        }

        return [
            'factura_id' => $billingReference ? (string) ($billingReference->ID ?? '') : null,
            'factura_cufe' => $billingReference ? (string) ($billingReference->UUID ?? '') : null,
            'factura_fecha' => $billingReference ? (string) ($billingReference->IssueDate ?? '') : null,
            'periodo_desde' => $invoicePeriod ? (string) ($invoicePeriod->StartDate ?? '') : null,
            'periodo_hasta' => $invoicePeriod ? (string) ($invoicePeriod->EndDate ?? '') : null,
            'concepto_codigo' => $discrepancyResponse ? (string) ($discrepancyResponse->ResponseCode ?? '') : null,
            'concepto_descripcion' => $discrepancyResponse ? (string) ($discrepancyResponse->Description ?? '') : null,
        ];
    }

    /**
     * @param  SimpleXMLElement  $party  Bloque "cac:Party" (de AccountingSupplierParty o AccountingCustomerParty).
     * @return array Datos de esa parte del documento, mismo shape en ambos casos.
     */
    private function extractParty(SimpleXMLElement $party): array
    {
        return [
            'razon_social' => (string) ($party->PartyLegalEntity->RegistrationName ?? $party->PartyName->Name ?? ''),
            'identificacion' => (string) ($party->PartyTaxScheme->CompanyID ?? $party->PartyIdentification->ID ?? ''),
            'dv' => (string) ($party->PartyTaxScheme->CompanyID->attributes()['schemeID'] ?? ''),
            'direccion' => (string) ($party->PhysicalLocation->Address->AddressLine->Line ?? ''),
            'ciudad' => (string) ($party->PhysicalLocation->Address->CityName ?? ''),
            'ciudad_codigo' => (string) ($party->PhysicalLocation->Address->ID ?? ''),
            'departamento' => (string) ($party->PhysicalLocation->Address->CountrySubentity ?? ''),
            'departamento_codigo' => (string) ($party->PhysicalLocation->Address->CountrySubentityCode ?? ''),
            'telefono' => (string) ($party->Contact->Telephone ?? ''),
            'email' => (string) ($party->Contact->ElectronicMail ?? ''),
        ];
    }

    /**
     * Código DIAN del documento (01, 02, 03, 04, 91, 92) -- mismo vocabulario que usa
     * DocumentoEmitido::tipo_documento (ver DocumentJsonMapper, donde "document.DocumentType" es
     * justamente uno de estos códigos, no "factura"/"nota_credito"/"nota_debito"). Se lee del
     * campo cbc:InvoiceTypeCode/CreditNoteTypeCode/DebitNoteTypeCode según el tipo de documento; si
     * el proveedor no lo trae (pasa con notas débito -- ver UblDocumentBuilder, que tampoco lo
     * manda del lado emisión), se usa el único código que existe en la práctica para ese tipo.
     *
     * @param  SimpleXMLElement  $document
     * @param  string  $tipoEstructura  "factura"|"nota_credito"|"nota_debito" (ver parse()).
     * @return string
     */
    private function extractTipoDocumentoCode(SimpleXMLElement $document, string $tipoEstructura): string
    {
        return match ($tipoEstructura) {
            'factura' => (string) ($document->InvoiceTypeCode ?? '') ?: '01',
            'nota_credito' => (string) ($document->CreditNoteTypeCode ?? '') ?: '91',
            'nota_debito' => (string) ($document->DebitNoteTypeCode ?? '') ?: '92',
        };
    }

    /**
     * Prefijo autorizado (ej. "FESS"), sacado de la extensión propia de la DIAN
     * (ext:UBLExtensions/.../DianExtensions/InvoiceControl/AuthorizedInvoices/Prefix) -- no
     * siempre es la misma UBLExtension que trae la firma, así que se busca la que sí tenga
     * "DianExtensions" en vez de asumir una posición fija.
     *
     * @param  SimpleXMLElement  $document
     * @return string Prefijo, o cadena vacía si no se encontró.
     */
    private function extractPrefix(SimpleXMLElement $document): string
    {
        foreach ($document->UBLExtensions->UBLExtension ?? [] as $extension) {
            $dianExtensions = $extension->ExtensionContent->DianExtensions ?? null;

            if ($dianExtensions) {
                return (string) ($dianExtensions->InvoiceControl->AuthorizedInvoices->Prefix ?? '');
            }
        }

        return '';
    }

    /**
     * Líneas del documento (InvoiceLine/CreditNoteLine/DebitNoteLine según el tipo) -- mismo
     * shape que usa emisión para mostrarlas (código, descripción, cantidad, precio, subtotal).
     *
     * @param  SimpleXMLElement  $document
     * @param  string  $tipoDocumento  "factura"|"nota_credito"|"nota_debito" (ver parse()).
     * @return array
     */
    private function extractLineas(SimpleXMLElement $document, string $tipoDocumento): array
    {
        [$lineElement, $quantityElement] = match ($tipoDocumento) {
            'factura' => ['InvoiceLine', 'InvoicedQuantity'],
            'nota_credito' => ['CreditNoteLine', 'CreditedQuantity'],
            'nota_debito' => ['DebitNoteLine', 'DebitedQuantity'],
        };

        $lineas = [];
        foreach ($document->{$lineElement} ?? [] as $linea) {
            $lineas[] = [
                'codigo' => (string) ($linea->Item->SellersItemIdentification->ID ?? $linea->ID ?? ''),
                'descripcion' => (string) ($linea->Item->Description ?? ''),
                'cantidad' => (float) ($linea->{$quantityElement} ?? 0),
                'precio_unitario' => (float) ($linea->Price->PriceAmount ?? 0),
                'subtotal' => (float) ($linea->LineExtensionAmount ?? 0),
            ];
        }

        return $lineas;
    }

    /**
     * Respuesta de la DIAN a este documento, ya incluida en el mismo AttachedDocument (ver
     * docblock de parse()) -- no hace falta ninguna consulta aparte contra la DIAN. Devuelve null
     * si no hay ningún ParentDocumentLineReference de tipo "ApplicationResponse" (documento viejo,
     * o subido antes de que la DIAN alcanzara a responder).
     *
     * @param  SimpleXMLElement  $attachedDocument  El AttachedDocument original (sin desenvolver).
     * @return array|null
     */
    private function extractDianValidation(SimpleXMLElement $attachedDocument): ?array
    {
        foreach ($attachedDocument->ParentDocumentLineReference ?? [] as $parentRef) {
            $docRef = $parentRef->DocumentReference ?? null;

            if (! $docRef || (string) ($docRef->DocumentType ?? '') !== 'ApplicationResponse') {
                continue;
            }

            $inner = trim((string) ($docRef->Attachment->ExternalReference->Description ?? ''));
            $applicationResponse = $inner !== '' ? $this->parseWithoutNamespacePrefixes($inner) : null;

            $reglas = [];
            foreach ($applicationResponse->DocumentResponse->LineResponse ?? [] as $lineResponse) {
                $code = (string) ($lineResponse->Response->ResponseCode ?? '');
                $description = (string) ($lineResponse->Response->Description ?? '');

                // El código "0000" es el "sin observaciones" de la propia DIAN -- no aporta nada
                // al usuario, se salta.
                if ($code !== '' && $code !== '0000') {
                    $reglas[] = trim($code . ': ' . $description);
                }
            }

            $verification = $docRef->ResultOfVerification ?? null;
            $validationResultCode = (string) ($verification->ValidationResultCode ?? '');

            return [
                'response_code' => (string) ($applicationResponse->DocumentResponse->Response->ResponseCode ?? ''),
                'response_description' => (string) ($applicationResponse->DocumentResponse->Response->Description ?? ''),
                'validator_id' => (string) ($verification->ValidatorID ?? ''),
                'validation_result_code' => $validationResultCode,
                'validation_date' => (string) ($verification->ValidationDate ?? ''),
                'validation_time' => (string) ($verification->ValidationTime ?? ''),
                'reglas' => $reglas,
                // "02" = "Documento validado por la DIAN" (confirmado contra un documento real);
                // cualquier otro código se deja pendiente para revisión manual.
                'es_valido' => $validationResultCode === '02',
            ];
        }

        return null;
    }

    /**
     * @param  string  $xml
     * @return SimpleXMLElement
     *
     * @throws InvalidArgumentException Si el XML no se puede parsear.
     */
    private function parseWithoutNamespacePrefixes(string $xml): SimpleXMLElement
    {
        $withoutPrefixes = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $xml);

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($withoutPrefixes);
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            throw new InvalidArgumentException('El archivo no es un XML válido.');
        }

        return $parsed;
    }
}
