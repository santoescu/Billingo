<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\ThirdParty;
use InvalidArgumentException;

class DocumentJsonMapper
{
    /**
     * Traduce el JSON recibido al payload interno de UblDocumentBuilder,
     * más "prefix" y "numero_solicitado" para que el servicio resuelva la
     * numeración.
     *
     * @param  Company  $company  Empresa emisora (dueña del token de la petición).
     * @param  array  $request  Cuerpo completo de la petición ({"document": {"DocumentType": "...", ...}}).
     * @return array Payload interno + "prefix" y "numero_solicitado".
     */
    public function map(Company $company, array $request): array
    {
        $document = $request['document'] ?? throw new InvalidArgumentException('El campo "document" es obligatorio.');
        $tipoDocumento = $request['tipo_documento'] ?? $document['DocumentType'] ?? throw new InvalidArgumentException('Debe indicar el código DIAN del tipo de documento en "document.DocumentType" (01, 02, 03, 04, 05, 91, 92, 95).');

        $this->assertSupplierMatchesCompany($company, $document['AccountingSupplierParty'] ?? []);
        $cliente = $this->resolveCustomerParty($company, $document['AccountingCustomerParty'] ?? []);
        $paymentMeansList = $this->mapPaymentMeansList($document['PaymentMeans'] ?? []);
        $paymentMeans = $paymentMeansList[0] ?? null;

        $payload = [
            'tipo_documento' => $tipoDocumento,
            'customization_id' => $document['CustomizationID'] ?? null,
            'moneda' => $document['DocumentCurrencyCode'] ?? 'COP',
            'issue_date' => $document['IssueDate'] ?? null,
            'issue_time' => $document['IssueTime'] ?? null,
            'fecha_vencimiento' => $document['DueDate'] ?? $paymentMeans['fecha_vencimiento'] ?? null,
            'notas' => $document['Note'] ?? [],
            'accounting_customer_party' => $cliente['data'],
            'cliente_id' => $cliente['id'],
            'payment_means' => $paymentMeans,
            'payment_means_list' => $paymentMeansList,
            'cargos_descuentos' => $this->mapCargosDescuentos($document['AllowanceCharge'] ?? []),
            'lineas' => $this->mapLines($document['InvoiceLine'] ?? []),
            'prefix' => $document['PREFIX'] ?? null,
            'numero_solicitado' => $document['Numeral'] ?? $this->buildNumeralFromSecuencial($document),
            'supplier_overrides' => $this->extractSupplierOverrides($document['AccountingSupplierParty'] ?? []),
        ];

        if (! empty($document['BillingReference']) || ! empty($document['DiscrepancyResponse']) || ! empty($document['InvoicePeriod'])) {
            $payload['referencias'] = [
                'factura_id' => $document['BillingReference']['InvoiceDocumentReference']['ID'] ?? null,
                'factura_cufe' => $document['BillingReference']['InvoiceDocumentReference']['UUID'] ?? null,
                'factura_fecha' => $document['BillingReference']['InvoiceDocumentReference']['IssueDate'] ?? null,
                'periodo_desde' => $document['InvoicePeriod']['StartDate'] ?? null,
                'periodo_hasta' => $document['InvoicePeriod']['EndDate'] ?? null,
                'concepto_codigo' => $document['DiscrepancyResponse']['ResponseCode'] ?? '1',
            ];
        }

        return $payload;
    }

    /**
     * Exige que "AccountingSupplierParty.CompanyID" venga en la petición y que coincida
     * con el NIT de la empresa dueña del token -- los datos del emisor SIEMPRE se toman
     * de la Company ya registrada, nunca del JSON, pero este campo es obligatorio como
     * chequeo de que la petición realmente es para esta empresa.
     *
     * @param  Company  $company  Empresa dueña del token de la petición.
     * @param  array  $accountingSupplierParty  Bloque "AccountingSupplierParty" de la petición.
     */
    private function assertSupplierMatchesCompany(Company $company, array $accountingSupplierParty): void
    {
        $companyId = $accountingSupplierParty['CompanyID'] ?? throw new InvalidArgumentException('AccountingSupplierParty.CompanyID es obligatorio (el NIT de la empresa emisora).');

        if ($companyId !== $company->identificacion) {
            throw new InvalidArgumentException('AccountingSupplierParty.CompanyID no coincide con la empresa autenticada por el token.');
        }
    }

    /**
     * Extrae del bloque "AccountingSupplierParty" los campos que vinieron además de
     * CompanyID: se usan como reemplazo del emisor solo en el XML de este documento,
     * pero la Company en base de datos nunca se actualiza con ellos. Cualquier campo
     * que no venga en el JSON queda ausente aquí, y UblDocumentBuilder cae de vuelta a
     * lo que ya está guardado en la Company.
     *
     * @param  array  $accountingSupplierParty  Bloque "AccountingSupplierParty" de la petición.
     * @return array Campos del emisor a sobreescribir solo para este documento.
     */
    private function extractSupplierOverrides(array $accountingSupplierParty): array
    {
        return array_filter([
            'name' => $accountingSupplierParty['PartyName'] ?? null,
            'fiscal_responsibilities' => $accountingSupplierParty['TaxLevelCode'] ?? null,
            'address' => $accountingSupplierParty['direccion'] ?? null,
            'city_code' => $accountingSupplierParty['cityCode'] ?? null,
            'department_code' => $accountingSupplierParty['CountrySubentityCode'] ?? null,
            'phone' => $accountingSupplierParty['telefono'] ?? null,
            'email' => $accountingSupplierParty['email'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Combina PREFIX + secuencial en un número completo, si no vino "Numeral" directo.
     *
     * @param  array  $document  Bloque "document" de la petición.
     * @return string|null Número completo, o null si no se indicó secuencial.
     */
    private function buildNumeralFromSecuencial(array $document): ?string
    {
        if (empty($document['secuencial'])) {
            return null;
        }

        return trim(($document['PREFIX'] ?? '') . $document['secuencial'], '-');
    }

    /**
     * Resuelve los datos del receptor (AccountingCustomerParty): si solo viene el
     * "CompanyID", lo busca en third_parties; si además vienen otros datos, actualiza
     * (o crea) el tercero con lo que llegó.
     *
     * @param  Company  $company  Empresa emisora (dueña del tercero).
     * @param  array  $accountingCustomerParty  Bloque "AccountingCustomerParty" de la petición.
     * @return array{data: array, id: string} Datos del receptor en el shape interno de UblDocumentBuilder, y el id del tercero.
     */
    public function resolveCustomerParty(Company $company, array $accountingCustomerParty): array
    {
        $identificacion = $accountingCustomerParty['CompanyID'] ?? throw new InvalidArgumentException('AccountingCustomerParty.CompanyID es obligatorio.');
        $tipoIdentificacion = $accountingCustomerParty['TypeCompanyID'] ?? null;

        $otherFields = array_filter([
            'name' => $accountingCustomerParty['PartyName'] ?? null,
            'identification_type' => $tipoIdentificacion,
            'person_type' => isset($accountingCustomerParty['AdditionalAccountID']) ? ($accountingCustomerParty['AdditionalAccountID'] === '1' ? '1' : '2') : null,
            'fiscal_responsibilities' => $accountingCustomerParty['TaxLevelCode'] ?? null,
            'address' => $accountingCustomerParty['direccion'] ?? null,
            'city_code' => $accountingCustomerParty['cityCode'] ?? null,
            'department_code' => $accountingCustomerParty['CountrySubentityCode'] ?? null,
            'phone' => $accountingCustomerParty['telefono'] ?? null,
            'email' => $accountingCustomerParty['email'] ?? null,
        ], fn ($value) => $value !== null);

        $cliente = ThirdParty::where('company_id', (string) $company->_id)
            ->where('identificacion', $identificacion)
            ->first();

        if (empty($otherFields) && ! $cliente) {
            throw new InvalidArgumentException("No se encontró ningún cliente con identificación \"{$identificacion}\"; envíe los demás datos de AccountingCustomerParty para crearlo.");
        }

        if (! empty($otherFields)) {
            if (! $cliente) {
                $this->assertRequiredFieldsForNewClient($otherFields);
            }

            $tipoIdentificacionFinal = $tipoIdentificacion ?? $cliente?->identification_type ?? '31';
            $attributes = array_merge($otherFields, [
                'company_id' => (string) $company->_id,
                'identificacion' => $identificacion,
                'dv' => $tipoIdentificacionFinal === '31' ? Company::calculateVerificationDigit($identificacion) : null,
            ]);

            if ($cliente) {
                
                $attributes['roles'] = collect($cliente->roles ?? [])->push('cliente')->unique()->values()->all();
                $cliente->update($attributes);
            } else {
                
                $attributes['roles'] = ['cliente'];
                $cliente = ThirdParty::create($attributes);
            }
        }

        return [
            'data' => [
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
            ],
            'id' => (string) $cliente->_id,
        ];
    }

    /**
     * Verifica que estén todos los campos necesarios para crear un cliente nuevo
     * (cuando no existía ya en third_parties y no hay de dónde completar los que falten).
     *
     * @param  array  $fields  Campos ya traducidos a nombres internos (ver resolveCustomerParty()).
     */
    private function assertRequiredFieldsForNewClient(array $fields): void
    {
        $required = [
            'name' => 'AccountingCustomerParty.PartyName',
            'address' => 'AccountingCustomerParty.direccion',
            'city_code' => 'AccountingCustomerParty.cityCode',
            'department_code' => 'AccountingCustomerParty.CountrySubentityCode',
        ];

        $missing = [];
        foreach ($required as $field => $jsonPath) {
            if (empty($fields[$field])) {
                $missing[] = $jsonPath;
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Este cliente no existe todavía; para crearlo faltan estos campos: ' . implode(', ', $missing) . '.');
        }
    }

    /**
     * Traduce el bloque "PaymentMeans" (arreglo, puede traer varios medios de pago) al shape interno.
     *
     * @param  array  $paymentMeansList  Bloque "PaymentMeans" de la petición.
     * @return array Lista de medios de pago en el shape interno (vacía si no vino ninguno).
     */
    private function mapPaymentMeansList(array $paymentMeansList): array
    {
        return array_values(array_map(fn (array $paymentMeans) => [
            'id' => $paymentMeans['ID'] ?? '1',
            'codigo' => $paymentMeans['PaymentMeansCode'] ?? '10',
            'fecha_vencimiento' => $paymentMeans['PaymentDueDate'] ?? null,
            'payment_id' => $paymentMeans['PaymentID'] ?? null,
        ], $paymentMeansList));
    }

    /**
     * Traduce el bloque "AllowanceCharge" a nivel documento (cargos/descuentos globales,
     * opcionales para la DIAN) al shape interno.
     *
     * @param  array  $allowanceChargeList  Bloque "AllowanceCharge" de la petición.
     * @return array Lista de cargos/descuentos en el shape interno (vacía si no vino ninguno).
     */
    private function mapCargosDescuentos(array $allowanceChargeList): array
    {
        return array_values(array_map(fn (array $item) => [
            'tipo' => ($item['ChargeIndicator'] ?? false) ? 'cargo' : 'descuento',
            'motivo' => $item['AllowanceChargeReason'] ?? null,
            'valor_tipo' => isset($item['MultiplierFactorNumeric']) ? 'porcentaje' : 'fijo',
            'valor' => $item['MultiplierFactorNumeric'] ?? $item['Amount'] ?? 0,
        ], $allowanceChargeList));
    }

    /**
     * Traduce el bloque "InvoiceLine" al shape interno "lineas" que espera UblDocumentBuilder.
     * Los montos (LineExtensionAmount, TaxableAmount, TaxAmount) del JSON se ignoran a
     * propósito: siempre se recalculan desde cantidad/precio_unitario/impuestos para
     * garantizar que coincidan con lo que exige la fórmula del CUFE/CUDE.
     *
     * @param  array  $invoiceLines  Bloque "InvoiceLine" de la petición.
     * @return array Líneas en el shape interno de UblDocumentBuilder.
     */
    private function mapLines(array $invoiceLines): array
    {
        return array_map(function (array $line) {
            $item = $line['Item'] ?? [];

            return [
                'codigo' => $item['SellersItemIdentification']['ID'] ?? null,
                'codigo_barras' => $item['StandardItemIdentification']['ID'] ?? null,
                'descripcion' => $item['Description'] ?? '',
                'cantidad' => $line['InvoicedQuantity'] ?? 1,
                'unidad_medida' => $line['unitCode'] ?? 'EA',
                'precio_unitario' => $line['Price']['PriceAmount'] ?? 0,
                'bodega_id' => $line['bodega_id'] ?? null,
                'descuento' => $this->mapLineDiscount($line['AllowanceCharge'] ?? null),
                'impuestos' => $this->mapLineTaxes($line['TaxTotal'] ?? []),
            ];
        }, $invoiceLines);
    }

    /**
     * Traduce el "AllowanceCharge" de una línea (a lo sumo uno: un descuento
     * por línea) al shape interno "descuento" (valor_tipo, valor, motivo)
     * que espera UblDocumentBuilder::buildLineasCalculadas().
     *
     * @param  array|null  $allowanceCharge  Bloque "AllowanceCharge" de la línea, si vino.
     * @return array|null Descuento en el shape interno, o null si la línea no tiene.
     */
    private function mapLineDiscount(?array $allowanceCharge): ?array
    {
        if (empty($allowanceCharge)) {
            return null;
        }

        return [
            'valor_tipo' => isset($allowanceCharge['MultiplierFactorNumeric']) ? 'porcentaje' : 'fijo',
            'valor' => $allowanceCharge['MultiplierFactorNumeric'] ?? $allowanceCharge['Amount'] ?? 0,
            'motivo' => $allowanceCharge['AllowanceChargeReason'] ?? null,
        ];
    }

    /**
     * Traduce el bloque "TaxTotal.TaxSubtotal" (uno o varios) de una línea al shape
     * interno "impuestos" (tipo, porcentaje, base gravable).
     *
     * @param  array  $taxTotal  Bloque "TaxTotal" de la línea.
     * @return array Impuestos en el shape interno de UblDocumentBuilder.
     */
    private function mapLineTaxes(array $taxTotal): array
    {
        $subtotals = $taxTotal['TaxSubtotal'] ?? [];
        $isSingle = isset($subtotals['TaxCategory']);
        $subtotals = $isSingle ? [$subtotals] : $subtotals;

        return array_map(fn (array $subtotal) => [
            'tipo' => $subtotal['TaxCategory']['TaxScheme']['ID'] ?? '01',
            'nombre' => $subtotal['TaxCategory']['TaxScheme']['Name'] ?? null,
            'porcentaje' => (float) ($subtotal['TaxCategory']['Percent'] ?? 0),
            'base_gravable' => isset($subtotal['TaxableAmount']) ? (float) $subtotal['TaxableAmount'] : null,
        ], $subtotals);
    }
}
