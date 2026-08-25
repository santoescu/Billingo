<?php

namespace App\Http\Controllers;

use App\Models\PriceType;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductExportController extends Controller
{
    /**
     * Exporta el catálogo a Excel con las mismas columnas que espera el
     * mapeo de ProductImportController::import() (una columna "Precio: X"
     * por tipo de precio, "Bodega: Y" por bodega) -- así el archivo se
     * puede editar y volver a importar directo para actualizar. Código,
     * descripción y código de barras siempre van, sin importar qué otros
     * campos se hayan seleccionado.
     */
    public function export(Request $request)
    {
        $company = $this->currentCompany($request);

        $data = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'in:unit_code,tracks_inventory,stock,cost'],
            'price_type_ids' => ['nullable', 'array'],
            'price_type_ids.*' => ['string'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['string'],
        ]);

        $fields = $data['fields'] ?? [];
        $priceTypes = filled($data['price_type_ids'] ?? null)
            ? PriceType::where('company_id', (string) $company->_id)->whereIn('_id', $data['price_type_ids'])->orderBy('name')->get()
            : collect();
        $warehouses = filled($data['warehouse_ids'] ?? null)
            ? Warehouse::where('company_id', (string) $company->_id)->whereIn('_id', $data['warehouse_ids'])->orderBy('name')->get()
            : collect();

        $headers = [__('Code'), __('Description'), __('Barcode')];
        if (in_array('unit_code', $fields, true)) {
            $headers[] = __('Unit');
        }
        if (in_array('tracks_inventory', $fields, true)) {
            $headers[] = __('Tracks inventory (SI/NO)');
        }
        if (in_array('stock', $fields, true)) {
            $headers[] = __('Unassigned stock');
        }
        if (in_array('cost', $fields, true)) {
            $headers[] = __('Cost');
        }
        foreach ($priceTypes as $priceType) {
            $headers[] = __('Price') . ': ' . $priceType->name;
        }
        foreach ($warehouses as $warehouse) {
            $headers[] = __('Warehouse') . ': ' . $warehouse->name;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;
        foreach ($company->products()->active()->orderBy('code')->get() as $product) {
            $row = [$product->code, $product->description, $product->barcode];

            if (in_array('unit_code', $fields, true)) {
                $row[] = $product->unit_code;
            }
            if (in_array('tracks_inventory', $fields, true)) {
                $row[] = $product->tracks_inventory ? 'SI' : 'NO';
            }
            if (in_array('stock', $fields, true)) {
                $row[] = $product->tracks_inventory ? $product->unassigned_stock : null;
            }
            if (in_array('cost', $fields, true)) {
                $row[] = (float) ($product->average_cost ?? 0);
            }

            $pricesByType = collect($product->extra_prices ?? [])->keyBy('price_type_id');
            foreach ($priceTypes as $priceType) {
                $row[] = isset($pricesByType[(string) $priceType->_id])
                    ? (float) $pricesByType[(string) $priceType->_id]['price']
                    : null;
            }

            $stocksByWarehouse = collect($product->warehouse_stocks ?? [])->keyBy('warehouse_id');
            foreach ($warehouses as $warehouse) {
                $row[] = $product->tracks_inventory && isset($stocksByWarehouse[(string) $warehouse->_id])
                    ? (float) $stocksByWarehouse[(string) $warehouse->_id]['stock']
                    : null;
            }

            $sheet->fromArray($row, null, 'A' . $rowIndex);
            $rowIndex++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'productos-' . now('America/Bogota')->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
