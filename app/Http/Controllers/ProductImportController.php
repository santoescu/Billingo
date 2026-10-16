<?php

namespace App\Http\Controllers;

use App\Services\ProductImportProcessor;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportController extends Controller
{
    private const SAMPLE_ROWS = 3;

    /**
     * Lee el Excel subido y devuelve al navegador los encabezados, unas filas de muestra y TODAS
     * las filas (crudas y formateadas). No guarda nada en el servidor: el navegador conserva las
     * filas y las reenvía por bloques a chunk(), así la importación no depende de que cada
     * petición caiga en la misma instancia ni de archivos temporales en disco.
     */
    public function preview(Request $request)
    {
        $this->currentCompany($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $path = $request->file('file')->getRealPath();

        $rawRows = $this->readRows($path, formatted: false);
        $formattedRows = $this->readRows($path, formatted: true);

        if (empty($formattedRows)) {
            return response()->json(['message' => __('The file has no rows.')], 422);
        }

        return response()->json([
            'headers' => array_map(fn ($value) => trim((string) $value), $formattedRows[0]),
            'sample' => array_slice($formattedRows, 1, self::SAMPLE_ROWS),
            'row_count' => count($formattedRows) - 1,
            'raw_rows' => array_slice($rawRows, 1),
            'formatted_rows' => array_slice($formattedRows, 1),
        ]);
    }

    /**
     * Procesa un bloque de filas (ver ProductImportProcessor) y devuelve los conteos y las líneas
     * del reporte de ese bloque, que el navegador va acumulando.
     */
    public function chunk(Request $request, ProductImportProcessor $processor)
    {
        set_time_limit(300);

        $company = $this->currentCompany($request);
        $this->authorizeInventoryManagement($company);
        $this->authorizeInventoryManagement($company);

        $data = $request->validate([
            'raw_rows' => ['required', 'array', 'max:500'],
            'formatted_rows' => ['required', 'array', 'max:500'],
            'mapping' => ['required', 'array'],
            'mapping.*.column' => ['required', 'integer', 'min:0'],
            'mapping.*.target' => ['required', 'string', 'in:code,description,barcode,unit_code,tracks_inventory,stock,warehouse_stock,price,cost'],
            'mapping.*.price_type_name' => ['nullable', 'string', 'max:255'],
            'mapping.*.warehouse_name' => ['nullable', 'string', 'max:255'],
            'stock_mode' => ['nullable', 'string', 'in:overwrite,add'],
        ]);

        return response()->json($processor->process(
            $company,
            $data['raw_rows'],
            $data['formatted_rows'],
            array_values($data['mapping']),
            $data['stock_mode'] ?? 'overwrite',
            (string) $request->user()->_id,
        ));
    }

    /**
     * Arma y descarga el reporte en PDF con las líneas que el
     * navegador acumuló de todos los bloques.
     */
    public function report(Request $request, ProductImportProcessor $processor)
    {
        set_time_limit(300);

        $company = $this->currentCompany($request);
        $this->authorizeInventoryManagement($company);

        $data = $request->validate([
            'report' => ['required', 'array'],
            'report.*.code' => ['required', 'string'],
            'report.*.description' => ['nullable', 'string'],
            'report.*.action' => ['required', 'string', 'in:created,updated'],
            'report.*.stock_action' => ['required', 'string'],
            'report.*.imported_stock' => ['nullable', 'numeric'],
            'report.*.final_stock' => ['required', 'numeric'],
            'report.*.warehouses' => ['nullable', 'array'],
            'report.*.warehouses.*.name' => ['required', 'string'],
            'report.*.warehouses.*.imported' => ['required', 'numeric'],
            'report.*.warehouses.*.final' => ['required', 'numeric'],
            'created' => ['required', 'integer'],
            'updated' => ['required', 'integer'],
        ]);

        return response($processor->renderReport($company, $data['report'], $data['created'], $data['updated']), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-importacion-productos.pdf"',
        ]);
    }

    /**
     * @param  bool  $formatted  false: valor numérico crudo de PHP (para columnas
     *     numéricas, evita que el separador de miles/decimales de PhpSpreadsheet
     *     -que usa coma de miles, al revés de Colombia- dañe los precios).
     *     true: valor tal como Excel lo muestra (para columnas de texto, que
     *     pueden depender del formato de celda, ej. ceros a la izquierda).
     */
    private function readRows(string $path, bool $formatted): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();

        return $sheet->toArray(null, true, $formatted, false);
    }
}
