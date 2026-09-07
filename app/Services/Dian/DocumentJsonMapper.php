<?php

namespace App\Services\Dian;

use App\Models\Company;
use App\Models\ThirdParty;
use InvalidArgumentException;

class DocumentJsonMapper
{
    /**
     * Tributos que el anexo técnico exige informar de forma nominal (valor fijo por unidad,
     * vía "PerUnitAmount"/"BaseUnitMeasure") en vez de porcentual (vía "Percent"):
     * `21` Timbre, `22` INC Bolsas, `23` INCarbono, `24` INCombustibles (regla explícita del
     * anexo, numeral FAX09-FAX11), más `33` INPP y `34` IBUA (confirmado en la tabla de
     * tarifas 13.3.11 -- ambos se calculan con la misma fórmula PerUnitAmount x
     * BaseUnitMeasure).
     */
    private const TRIBUTOS_NOMINALES = ['21', '22', '23', '24', '33', '34'];

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
        $tipoDocumento = $document['DocumentType'] ?? throw new InvalidArgumentException('El campo "document.DocumentType" es obligatorio (01, 02, 03, 04, 91, 92).');

        $this->assertSupplierMatchesCompany($company, $document['AccountingSupplierParty'] ?? []);
        $cliente = $this->resolveCustomerParty($company, $document['AccountingCustomerParty'] ?? []);
        $paymentMeansList = $this->mapPaymentMeansList($document['PaymentMeans'] ?? []);
        $paymentMeans = $paymentMeansList[0] ?? null;
        $customizationId = $document['CustomizationID'] ?? null;
        $lineas = $this->mapLines($document['Lines'] ?? []);
        $this->assertMandanteInformadoSiEsMandato($customizationId, $lineas);

        $payload = [
            'tipo_documento' => $tipoDocumento,
            'customization_id' => $customizationId,
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
            'lineas' => $lineas,
            'prefix' => $document['PREFIX'] ?? throw new InvalidArgumentException('El campo "document.PREFIX" es obligatorio.'),
            'numero_solicitado' => $this->buildNumeral($document),
            'supplier_overrides' => $this->extractSupplierOverrides($document['AccountingSupplierParty'] ?? []),
            'legal_monetary_total_expected' => $document['LegalMonetaryTotal'] ?? throw new InvalidArgumentException('El campo "document.LegalMonetaryTotal" es obligatorio.'),
            'orden_referencia' => $this->mapOrderReference($document['OrderReference'] ?? null),
        ];

        if (! empty($document['BillingReference']) || ! empty($document['DiscrepancyResponse']) || ! empty($document['InvoicePeriod'])) {
            $billingReference = $document['BillingReference'] ?? [];

            $payload['referencias'] = [
                'factura_id' => empty($billingReference) ? null : ($billingReference['ID'] ?? throw new InvalidArgumentException('document.BillingReference.ID es obligatorio.')),
                'factura_cufe' => empty($billingReference) ? null : ($billingReference['UUID'] ?? throw new InvalidArgumentException('document.BillingReference.UUID es obligatorio.')),
                'factura_fecha' => empty($billingReference) ? null : ($billingReference['IssueDate'] ?? throw new InvalidArgumentException('document.BillingReference.IssueDate es obligatorio.')),
                'periodo_desde' => $document['InvoicePeriod']['StartDate'] ?? null,
                'periodo_hasta' => $document['InvoicePeriod']['EndDate'] ?? null,
                'concepto_codigo' => $document['DiscrepancyResponse']['ResponseCode'] ?? '1',
            ];
        }

        return $payload;
    }

    /**
     * Traduce "document.OrderReference" -- referencia opcional a una orden de compra (no
     * tributaria, de interés mercantil), disponible para factura, nota crédito y nota débito.
     *
     * @param  array|null  $orderReference  Bloque "document.OrderReference" de la petición.
     * @return array|null Referencia en el shape interno (null si no se mandó).
     */
    private function mapOrderReference(?array $orderReference): ?array
    {
        if ($orderReference === null) {
            return null;
        }

        return [
            'id' => $orderReference['ID'] ?? throw new InvalidArgumentException('document.OrderReference.ID es obligatorio.'),
            'issue_date' => $orderReference['IssueDate'] ?? null,
        ];
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
     * "CustomizationID" 11 (Mandatos) exige informar al mandante (dueño real de la operación,
     * por cuenta de quien el emisor factura) en al menos una línea del documento -- anexo
     * técnico, regla FBA05/FBA06. Billingo no calcula ni asume ningún mandante, solo valida
     * que al menos una línea lo traiga cuando aplica.
     *
     * @param  string|null  $customizationId  "document.CustomizationID" tal como lo mandó el caller.
     * @param  array  $lineas  Líneas ya mapeadas (ver mapLines()).
     *
     * @throws InvalidArgumentException Si "CustomizationID" es "11" y ninguna línea trae "mandante".
     */
    private function assertMandanteInformadoSiEsMandato(?string $customizationId, array $lineas): void
    {
        if ($customizationId !== '11') {
            return;
        }

        $tieneMandante = ! empty(array_filter(array_column($lineas, 'mandante')));
        if (! $tieneMandante) {
            throw new InvalidArgumentException('document.CustomizationID es "11" (Mandatos): al menos una línea debe traer "Item.InformationContentProviderParty" con la identificación del mandante.');
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
        $this->assertSupplierLocationOverrideComplete($accountingSupplierParty);

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
     * La DIAN exige municipio + departamento como un par consistente (ver anexo técnico,
     * numeral de PhysicalLocation/Address). Como estos dos campos se combinan uno por uno
     * con lo que ya tiene la Company guardada (ver UblDocumentBuilder::buildSupplierParty()),
     * reemplazar solo uno de los dos dejaría el documento con un municipio nuevo pero
     * departamento viejo (o viceversa) -- una combinación inconsistente que nunca debería
     * armarse en silencio. "direccion" no entra en esta regla: es texto libre, no depende
     * de los otros dos.
     *
     * @param  array  $accountingSupplierParty  Bloque "AccountingSupplierParty" de la petición.
     */
    private function assertSupplierLocationOverrideComplete(array $accountingSupplierParty): void
    {
        $campos = ['cityCode', 'CountrySubentityCode'];
        $presentes = array_filter($campos, fn ($campo) => isset($accountingSupplierParty[$campo]));

        if (count($presentes) > 0 && count($presentes) < count($campos)) {
            throw new InvalidArgumentException('AccountingSupplierParty: "cityCode" y "CountrySubentityCode" deben venir los dos juntos, o ninguno.');
        }
    }

    /**
     * Combina "PREFIX" + "secuencial" en el número completo del documento.
     *
     * @param  array  $document  Bloque "document" de la petición.
     * @return string Número completo (prefijo + consecutivo).
     */
    private function buildNumeral(array $document): string
    {
        if (empty($document['secuencial'])) {
            throw new InvalidArgumentException('El campo "document.secuencial" es obligatorio.');
        }

        return trim($document['PREFIX'] . $document['secuencial'], '-');
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

        $this->assertCustomerLocationOverrideComplete($accountingCustomerParty);

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
     * Misma regla que assertSupplierLocationOverrideComplete(), pero para el cliente: si el
     * cliente ya existe y la petición trae solo uno de los dos, el update lo dejaría con
     * ciudad nueva y departamento viejo (o viceversa) sin que nadie se entere. Cuando el
     * cliente es nuevo esto ya queda cubierto por assertRequiredFieldsForNewClient() (exige
     * los dos, entre otros campos), pero no está de más chequearlo acá también.
     *
     * @param  array  $accountingCustomerParty  Bloque "AccountingCustomerParty" de la petición.
     */
    private function assertCustomerLocationOverrideComplete(array $accountingCustomerParty): void
    {
        $campos = ['cityCode', 'CountrySubentityCode'];
        $presentes = array_filter($campos, fn ($campo) => isset($accountingCustomerParty[$campo]));

        if (count($presentes) > 0 && count($presentes) < count($campos)) {
            throw new InvalidArgumentException('AccountingCustomerParty: "cityCode" y "CountrySubentityCode" deben venir los dos juntos, o ninguno.');
        }
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
    public function mapCargosDescuentos(array $allowanceChargeList): array
    {
        return array_values(array_map(function (array $item) {
            $esCargo = (bool) ($item['ChargeIndicator'] ?? false);

            foreach (['AllowanceChargeReasonCode', 'MultiplierFactorNumeric', 'Amount', 'BaseAmount'] as $campo) {
                if (! isset($item[$campo])) {
                    throw new InvalidArgumentException("document.AllowanceCharge: \"{$campo}\" es obligatorio.");
                }
            }

            return [
                'tipo' => $esCargo ? 'cargo' : 'descuento',
                'motivo' => $item['AllowanceChargeReason'] ?? null,
                'codigo_razon' => $item['AllowanceChargeReasonCode'],
                'porcentaje' => (float) $item['MultiplierFactorNumeric'],
                'amount' => (float) $item['Amount'],
                'base_amount' => (float) $item['BaseAmount'],
            ];
        }, $allowanceChargeList));
    }

    /**
     * Traduce el bloque "Lines" al shape interno "lineas" que espera UblDocumentBuilder. Se
     * llama "Lines"/"Quantity" en el JSON (no "InvoiceLine"/"InvoicedQuantity") porque el mismo
     * nombre aplica sin importar el tipo de documento -- Billingo lo traduce internamente al
     * elemento UBL que corresponde ("InvoiceLine"/"CreditNoteLine"/"DebitNoteLine" y
     * "InvoicedQuantity"/"CreditedQuantity"/"DebitedQuantity", ver
     * UblDocumentBuilder::LINE_ELEMENT/QUANTITY_ELEMENT).
     * "LineExtensionAmount" del JSON no se usa tal cual: Billingo siempre calcula el suyo desde
     * cantidad/precio_unitario/"AllowanceCharge" de la línea (necesario para que coincida con lo
     * que exige la fórmula del CUFE/CUDE) y lo compara contra el del caller, rechazando el
     * documento si no coincide -- ver DocumentTotalsCalculator::assertLineExtensionAmountMatches().
     *
     * @param  array  $lines  Bloque "Lines" de la petición.
     * @return array Líneas en el shape interno de UblDocumentBuilder.
     */
    public function mapLines(array $lines): array
    {
        return array_map(function (array $line) {
            $item = $line['Item'] ?? [];

            return [
                'codigo' => $item['SellersItemIdentification']['ID'] ?? null,
                'codigo_barras' => $item['StandardItemIdentification']['ID'] ?? null,
                'codigo_barras_scheme_id' => $item['StandardItemIdentification']['SchemeID'] ?? '999',
                'descripcion' => $item['Description'] ?? throw new InvalidArgumentException('Lines.Item.Description es obligatorio.'),
                'marca' => $item['BrandName'] ?? [],
                'modelo' => $item['ModelName'] ?? [],
                'mandante' => $this->mapMandante($item['InformationContentProviderParty'] ?? null),
                'cantidad' => $line['Quantity'] ?? 1,
                'unidad_medida' => $line['unitCode'] ?? 'EA',
                'precio_unitario' => $line['Price']['PriceAmount'] ?? 0,
                'precio_base_quantity' => $line['Price']['BaseQuantity'] ?? 1,
                'bodega_id' => $line['bodega_id'] ?? null,
                'cargos_descuentos' => $this->mapLineAllowanceCharges($line['AllowanceCharge'] ?? []),
                'impuestos' => $this->mapLineTaxes($line['TaxTotal'] ?? [], $line['unitCode'] ?? 'EA'),
                'line_extension_amount_expected' => $line['LineExtensionAmount'] ?? throw new InvalidArgumentException('Lines.LineExtensionAmount es obligatorio.'),
            ];
        }, $lines);
    }

    /**
     * Traduce "Item.InformationContentProviderParty" -- solo aplica a operaciones de mandatos:
     * identifica al mandante (el tercero por cuenta de quien el emisor está facturando). En el
     * JSON va plano (sin la anidación "PowerOfAttorney.AgentParty.PartyIdentification" del XML,
     * que siempre es 1..1 obligatoria y no aporta nada al caller); Billingo arma esa estructura
     * al construir el XML. El dígito de verificación (solo aplica si "SchemeName" es "31", NIT)
     * no se pide -- Billingo lo calcula igual que para el emisor/receptor, con
     * Company::calculateVerificationDigit(). "@schemeAgencyID" siempre es el literal "195" fijo
     * por el anexo, no se pide.
     *
     * @param  array|null  $informationContentProviderParty  Bloque "Item.InformationContentProviderParty" de la línea.
     * @return array|null Mandante en el shape interno (null si la línea no aplica a mandatos).
     */
    private function mapMandante(?array $informationContentProviderParty): ?array
    {
        if ($informationContentProviderParty === null) {
            return null;
        }

        $schemeName = $informationContentProviderParty['SchemeName'] ?? throw new InvalidArgumentException('Lines.Item.InformationContentProviderParty.SchemeName es obligatorio.');
        $id = $informationContentProviderParty['ID'] ?? throw new InvalidArgumentException('Lines.Item.InformationContentProviderParty.ID es obligatorio.');

        return [
            'id' => $id,
            'scheme_name' => $schemeName,
            'scheme_id' => $schemeName === '31' ? Company::calculateVerificationDigit($id) : null,
        ];
    }

    /**
     * Traduce el "AllowanceCharge" de una línea (puede traer varios cargos/descuentos, el
     * anexo técnico permite 0..N) al shape interno que espera
     * DocumentTotalsCalculator::buildLineasCalculadas(). Igual que a nivel de documento,
     * Billingo no calcula nada acá: "MultiplierFactorNumeric", "Amount" y "BaseAmount" se
     * exigen y se mandan tal cual al XML. No existe "AllowanceChargeReasonCode" a nivel de
     * línea (ese campo es solo de "document.AllowanceCharge").
     *
     * @param  array  $allowanceChargeList  Bloque "AllowanceCharge" de la línea (arreglo).
     * @return array Cargos/descuentos de la línea en el shape interno (vacío si no vino ninguno).
     */
    private function mapLineAllowanceCharges(array $allowanceChargeList): array
    {
        return array_values(array_map(function (array $item) {
            foreach (['ChargeIndicator', 'MultiplierFactorNumeric', 'Amount', 'BaseAmount'] as $campo) {
                if (! isset($item[$campo])) {
                    throw new InvalidArgumentException("Lines.AllowanceCharge: \"{$campo}\" es obligatorio.");
                }
            }

            $esCargo = (bool) $item['ChargeIndicator'];

            return [
                'tipo' => $esCargo ? 'cargo' : 'descuento',
                'motivo' => $item['AllowanceChargeReason'] ?? null,
                'porcentaje' => (float) $item['MultiplierFactorNumeric'],
                'amount' => (float) $item['Amount'],
                'base_amount' => (float) $item['BaseAmount'],
            ];
        }, $allowanceChargeList));
    }

    /**
     * Traduce "TaxTotal" de una línea -- un arreglo de bloques, uno por cada tributo distinto
     * (anexo técnico, regla FAX01/FAS01a/FAS01b: solo puede existir un TaxTotal por tributo;
     * varias tarifas del mismo tributo van como varios "TaxSubtotal" dentro del mismo bloque) --
     * al shape interno "impuestos" (tipo, porcentaje, base gravable). Cada bloque valida que su
     * "TaxAmount" coincida con la suma de los "TaxAmount" de sus propios "TaxSubtotal".
     *
     * @param  array  $taxTotalList  Bloque "TaxTotal" de la línea (arreglo de bloques por tributo).
     * @param  string  $unitCode  "unitCode" de la línea -- para tributos nominales, "BaseUnitMeasure"
     *                            usa la misma unidad de medida del ítem, no se pide aparte.
     * @return array Impuestos en el shape interno de UblDocumentBuilder.
     */
    private function mapLineTaxes(array $taxTotalList, string $unitCode): array
    {
        $impuestos = [];

        foreach ($taxTotalList as $bloque) {
            $taxAmountBloque = (float) ($bloque['TaxAmount'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxAmount es obligatorio.'));
            $subtotals = $bloque['TaxSubtotal'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxSubtotal es obligatorio.');

            $mapeados = array_map(function (array $subtotal) use ($unitCode) {
                $tipo = $subtotal['TaxCategory']['TaxScheme']['ID'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxSubtotal.TaxCategory.TaxScheme.ID es obligatorio.');
                $esNominal = in_array($tipo, self::TRIBUTOS_NOMINALES, true);

                $impuesto = [
                    'tipo' => $tipo,
                    'nombre' => $subtotal['TaxCategory']['TaxScheme']['Name'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxSubtotal.TaxCategory.TaxScheme.Name es obligatorio.'),
                    'porcentaje' => (float) ($subtotal['TaxCategory']['Percent'] ?? 0),
                    'base_gravable' => (float) ($subtotal['TaxableAmount'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxSubtotal.TaxableAmount es obligatorio.')),
                    'tax_amount_expected' => (float) ($subtotal['TaxAmount'] ?? throw new InvalidArgumentException('Lines.TaxTotal.TaxSubtotal.TaxAmount es obligatorio.')),
                    'per_unit_amount' => null,
                    'base_unit_measure' => null,
                    'base_unit_measure_unit_code' => null,
                ];

                if ($esNominal) {
                    $impuesto['per_unit_amount'] = (float) ($subtotal['PerUnitAmount'] ?? throw new InvalidArgumentException("Lines.TaxTotal.TaxSubtotal.PerUnitAmount es obligatorio para el tributo \"{$tipo}\" (tributo nominal)."));
                    $impuesto['base_unit_measure'] = (float) ($subtotal['BaseUnitMeasure'] ?? throw new InvalidArgumentException("Lines.TaxTotal.TaxSubtotal.BaseUnitMeasure es obligatorio para el tributo \"{$tipo}\" (tributo nominal)."));
                    $impuesto['base_unit_measure_unit_code'] = $unitCode;
                }

                return $impuesto;
            }, $subtotals);

            $sumaSubtotales = round(array_sum(array_column($mapeados, 'tax_amount_expected')), 2);
            if (abs($taxAmountBloque - $sumaSubtotales) > 0.01) {
                throw new InvalidArgumentException("Lines.TaxTotal.TaxAmount ({$taxAmountBloque}) no coincide con la suma de sus \"TaxSubtotal\" ({$sumaSubtotales}).");
            }

            array_push($impuestos, ...$mapeados);
        }

        return $impuestos;
    }
}
