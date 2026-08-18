<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Resolution;
use App\Services\Dian\DianSoapClient;
use App\Services\Dian\UblDocumentBuilder;
use App\Services\Dian\UblDocumentSigner;
use Illuminate\Http\Request;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class DianController extends Controller
{
    /**
     * Las resoluciones las emite la DIAN directamente: aquí solo se
     * consultan y se guardan (una vez) los rangos ya autorizados. No se
     * pueden editar ni eliminar, para conservar la trazabilidad del dato
     * oficial. Solo se muestran las del ambiente activo de la empresa
     * (habilitación o producción): son rangos distintos, no intercambiables.
     */
    public function resolutions(Request $request)
    {
        $company = $this->currentCompany($request);
        $environment = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        if ($environment === Company::DIAN_AMBIENTE_PRUEBAS) {
            Resolution::ensureFixedTestResolution($company);
        }

        $resolutions = $company->resolutions()
            ->where('environment', $environment)
            ->orderByDesc('valid_from')
            ->get();

        return view('dian.resolutions', compact('company', 'resolutions'));
    }

    /**
     * Las notas crédito/débito no tienen un rango de numeración autorizado
     * por la DIAN (solo aplica a facturas): la propia empresa define su
     * numeración aquí, sin sincronizar nada con la DIAN. Lo mismo aplica a
     * "Factura de venta" (código interno 'FV', no es un código DIAN real):
     * el soporte de toda venta del POS (ver IssueDocumentService::issuePosSale())
     * -- misma numeración manual que las notas, sin límite de rango. Igual
     * "Cotización" (código interno 'COT', tampoco es un código DIAN real):
     * numera las cotizaciones (ver IssueDocumentService::issueQuotation()),
     * que no se envían a la DIAN ni descuentan inventario.
     */
    public function storeManualResolution(Request $request)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'document_type' => ['required', 'in:91,92,FV,COT'],
            'prefix' => ['required', 'string', 'max:10'],
            'range_from' => ['required', 'integer', 'min:1'],
        ]);

        Resolution::create([
            'company_id' => (string) $company->_id,
            'document_type' => $data['document_type'],
            'prefix' => $data['prefix'],
            
            'range_from' => (int) $data['range_from'],
            'range_to' => null,
            'current_number' => (int) $data['range_from'],
            'status' => 'active',
            'environment' => $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS,
            'is_manual' => true,
            'is_fixed_test' => false,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Numbering created.'),
        ]);

        return redirect()->route('dian.resolutions.index');
    }

    /**
     * Solo se pueden eliminar las numeraciones manuales de notas que crea la
     * propia empresa: las resoluciones que emite/sincroniza la DIAN (o la
     * fija de pruebas SETP) no se tocan, para conservar la trazabilidad del
     * dato oficial.
     */
    public function destroyManualResolution(Request $request, string $resolutionId)
    {
        $company = $this->currentCompany($request);

        $resolution = $company->resolutions()->where('_id', $resolutionId)->first();

        abort_unless($resolution, 404);

        if (! $resolution->is_manual) {
            session()->flash('toast', [
                'type' => 'error',
                'message' => __('Only manually created numbering can be removed.'),
            ]);

            return redirect()->route('dian.resolutions.index');
        }

        $resolution->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Numbering removed.'),
        ]);

        return redirect()->route('dian.resolutions.index');
    }

    /**
     * Consulta a la DIAN (servicio GetNumberingRange) los rangos de
     * numeración autorizados y crea las resoluciones que todavía no existan
     * (por número de resolución).
     */
    public function syncResolutions(Request $request, DianSoapClient $client)
    {
        $company = $this->currentCompany($request);

        if (! $company->dian_pin || ! $company->dian_software_id) {
            session()->flash('toast', [
                'type' => 'error',
                'message' => __('Configure the pin and software ID for this company first.'),
            ]);

            return redirect()->route('dian.resolutions.index');
        }

        try {
            $ranges = $client->getNumberingRanges($company);
        } catch (RuntimeException $e) {
            session()->flash('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('dian.resolutions.index');
        }

        $environment = $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS;

        $existingNumbers = $company->resolutions()
            ->where('environment', $environment)
            ->pluck('resolution_number')
            ->all();
        $created = 0;

        foreach ($ranges as $range) {
            if (in_array($range['resolution_number'], $existingNumbers, true)) {
                continue;
            }

            Resolution::create([
                'company_id' => (string) $company->_id,
                'resolution_number' => $range['resolution_number'],
                'resolution_date' => $range['resolution_date'],
                'prefix' => $range['prefix'],
                'range_from' => $range['range_from'],
                'range_to' => $range['range_to'],
                'current_number' => $range['range_from'],
                'valid_from' => $range['valid_from'],
                'valid_to' => $range['valid_to'],
                'technical_key' => $range['technical_key'],
                'status' => 'active',
                'environment' => $environment,
                'document_type' => Resolution::DOCUMENT_TYPE_FACTURA_DEFAULT,
                'is_manual' => false,
            ]);

            $created++;
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $created > 0
                ? __(':count resolution(s) synced.', ['count' => $created])
                : __('No new resolutions were found.'),
        ]);

        return redirect()->route('dian.resolutions.index');
    }

    /**
     * Consulta a la DIAN (servicio GetAcquirer) el nombre y correo reales de
     * un tercero a partir de su tipo y número de identificación, para
     * autocompletar el formulario de clientes/proveedores.
     */
    public function acquirer(Request $request, DianSoapClient $client)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'identification_type' => ['required', 'string'],
            'identification_number' => ['required', 'string'],
        ]);

        if (! $company->dian_pin || ! $company->dian_software_id) {
            return response()->json([
                'message' => __('Configure the pin and software ID for this company first.'),
            ], 422);
        }

        try {
            $acquirer = $client->getAcquirer($company, $data['identification_type'], $data['identification_number']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($acquirer);
    }

    /**
     * Guarda el testSetId que da la DIAN y arma/envía el lote mínimo para
     * habilitación (una factura, una nota crédito y una nota débito), usando
     * la resolución fija de pruebas (SETP) y la propia empresa como emisor y
     * receptor a la vez -- solo para ejercitar la tubería técnica (armar,
     * firmar, enviar), no para representar una venta real. El testSetId se
     * guarda como parte de este mismo envío: no hace falta un paso aparte
     * para guardarlo. Responde en JSON (AJAX) para que el modal de
     * habilitación se pueda quedar abierto mostrando el resultado.
     */
    public function sendTestSet(Request $request, string $id, DianSoapClient $client)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        $data = $request->validate([
            'dian_test_set_id' => ['required', 'string', 'max:100'],
        ]);

        $company->update($data);

        if (! $company->dian_pin || ! $company->dian_software_id || ! $company->dian_certificate_content) {
            return response()->json(['message' => __('Configure the pin, software ID and certificate for this company first.')], 422);
        }

        $resolution = Resolution::ensureFixedTestResolution($company);

        try {
            $documents = $this->buildMinimumTestDocuments($company, $resolution);
            $zipContent = $this->zipDocuments($documents);

            $result = $client->sendTestSetAsync(
                $company,
                'habilitacion_' . $company->identificacion . '_' . now()->format('YmdHis') . '.zip',
                $zipContent,
                $company->dian_test_set_id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $company->update(['dian_test_set_zip_key' => $result['zip_key']]);

        return response()->json([
            'zip_key' => $result['zip_key'],
            'errors' => $result['errors'],
            'message' => $result['zip_key']
                ? __('Test set sent. Check the status in a few minutes.')
                : __('The DIAN did not return a zip key; check the errors below.'),
        ], $result['zip_key'] ? 200 : 422);
    }

    /**
     * Consulta el estado de validación del último lote de pruebas enviado
     * (servicio GetStatusZip), a partir del zip_key guardado. Si los 3
     * documentos quedan válidos, la empresa se marca como habilitada
     * definitivamente (no hace falta volver a enviar el set de pruebas).
     * Responde en JSON (AJAX).
     */
    public function testSetStatus(Request $request, string $id, DianSoapClient $client)
    {
        $company = $this->authorizeCompanyAdmin($request, $id);

        if (! $company->dian_test_set_zip_key) {
            return response()->json(['message' => __('There is no test set sent yet.')], 422);
        }

        try {
            
            $results = $client->getStatusZip($company, $company->dian_test_set_zip_key, config('services.dian.endpoint'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $habilitado = ! empty($results) && collect($results)->every(fn (array $item) => $item['is_valid']);

        if ($habilitado) {
            $company->update(['dian_habilitado' => true]);
        }

        return response()->json(['results' => $results, 'dian_habilitado' => $habilitado]);
    }

    /**
     * Arma los 3 documentos mínimos (factura, nota crédito, nota débito) con
     * datos genéricos de producto y usando la propia empresa como receptor,
     * ya firmados (XAdES), listos para comprimir y enviar.
     */
    private function buildMinimumTestDocuments(Company $company, Resolution $resolution): array
    {
        $builder = new UblDocumentBuilder();
        $signer = new UblDocumentSigner();

        $receptor = [
            'razon_social' => $company->name,
            'identificacion' => $company->identificacion,
            'dv' => $company->dv,
            'tipo_persona' => $company->person_type,
            'responsabilidades_fiscales' => $company->fiscal_responsibilities,
            'direccion' => $company->address,
            'ciudad_codigo' => $company->city_code,
            'departamento_codigo' => $company->department_code,
            'telefono' => $company->phone,
            'email' => $company->email,
        ];

        $lineas = [[
            'codigo' => 'PRUEBA001',
            'descripcion' => 'Producto de prueba - habilitación',
            'cantidad' => 1,
            'unidad_medida' => 'EA',
            'precio_unitario' => 100000,
            'impuestos' => [['tipo' => '01', 'porcentaje' => 19]],
        ]];

        $facturaNumero = $resolution->nextInvoiceNumber();

        $facturaXml = $signer->sign($company, $builder->build($company, [
            'tipo_documento' => '01',
            'number' => $facturaNumero,
            'moneda' => 'COP',
            'accounting_customer_party' => $receptor,
            'payment_means' => ['codigo' => '10'],
            'notas' => 'Documento de prueba para habilitación ante la DIAN.',
            'clave_tecnica' => $resolution->technical_key,
            'resolucion' => [
                'numero' => $resolution->resolution_number,
                'prefijo' => $resolution->prefix,
                'rango_desde' => $resolution->range_from,
                'rango_hasta' => $resolution->range_to,
                'valido_desde' => $resolution->valid_from?->format('Y-m-d'),
                'valido_hasta' => $resolution->valid_to?->format('Y-m-d'),
            ],
            'lineas' => $lineas,
        ]));

        $facturaCufe = $this->extractCufe($facturaXml);
        $facturaFecha = now()->format('Y-m-d');

        $referencias = [
            'factura_id' => $facturaNumero,
            'factura_cufe' => $facturaCufe,
            'factura_fecha' => $facturaFecha,
            'concepto_codigo' => '2',
            'concepto_descripcion' => 'Anulación de factura de prueba',
        ];

        $notaCreditoResolution = $this->ensureNoteTestResolution($company, '91', 'NCPRUEBA');
        $notaCreditoNumero = $notaCreditoResolution->nextInvoiceNumber();

        $notaCreditoXml = $signer->sign($company, $builder->build($company, [
            'tipo_documento' => '91',
            'number' => $notaCreditoNumero,
            'moneda' => 'COP',
            'accounting_customer_party' => $receptor,
            'payment_means' => ['codigo' => '10'],
            'notas' => 'Nota crédito de prueba para habilitación ante la DIAN.',
            'referencias' => $referencias,
            'prefijo' => $notaCreditoResolution->prefix,
            'lineas' => $lineas,
        ]));

        $notaDebitoResolution = $this->ensureNoteTestResolution($company, '92', 'NDPRUEBA');
        $notaDebitoNumero = $notaDebitoResolution->nextInvoiceNumber();

        $notaDebitoXml = $signer->sign($company, $builder->build($company, [
            'tipo_documento' => '92',
            'number' => $notaDebitoNumero,
            'moneda' => 'COP',
            'accounting_customer_party' => $receptor,
            'payment_means' => ['codigo' => '10'],
            'notas' => 'Nota débito de prueba para habilitación ante la DIAN.',
            'referencias' => $referencias,
            'prefijo' => $notaDebitoResolution->prefix,
            'lineas' => $lineas,
        ]));

        return [
            $facturaNumero . '.xml' => $facturaXml,
            $notaCreditoNumero . '.xml' => $notaCreditoXml,
            $notaDebitoNumero . '.xml' => $notaDebitoXml,
        ];
    }

    /**
     * Las notas no tienen resolución DIAN (ver comentario en Resolution),
     * así que para el set de pruebas se auto-provisiona una numeración
     * manual propia la primera vez (sin rango límite), igual que la empresa
     * podría crear la suya desde la pantalla de resoluciones.
     */
    private function ensureNoteTestResolution(Company $company, string $documentType, string $prefix): Resolution
    {
        return Resolution::firstOrCreate(
            [
                'company_id' => (string) $company->_id,
                'document_type' => $documentType,
                'is_manual' => true,
                'environment' => $company->dian_environment ?? Company::DIAN_AMBIENTE_PRUEBAS,
            ],
            [
                'prefix' => $prefix,
                'range_from' => 1,
                'range_to' => null,
                'current_number' => 1,
                'status' => 'active',
                'is_fixed_test' => false,
            ]
        );
    }

    private function extractCufe(string $signedXml): string
    {
        $withoutPrefixes = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $signedXml);
        $xml = new SimpleXMLElement($withoutPrefixes);

        return (string) $xml->UUID;
    }

    private function zipDocuments(array $documents): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dian_test_set_');

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::OVERWRITE);
        foreach ($documents as $fileName => $content) {
            $zip->addFromString($fileName, $content);
        }
        $zip->close();

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }
}
