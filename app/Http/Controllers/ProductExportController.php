<?php

namespace App\Http\Controllers;

use App\Models\PriceType;
use App\Support\PdfMerger;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * campos se hayan seleccionado. Con "template" exporta solo los
     * encabezados (plantilla vacía lista para llenar e importar).
     */
    public function export(Request $request)
    {
        $company = $this->currentCompany($request);
        $this->authorizeInventoryManagement($company);

        $data = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'in:unit_code,tracks_inventory,stock,cost'],
            'price_type_ids' => ['nullable', 'array'],
            'price_type_ids.*' => ['string'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['string'],
            'template' => ['nullable', 'boolean'],
        ]);

        $isTemplate = (bool) ($data['template'] ?? false);

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
        $products = $isTemplate ? collect() : $company->products()->active()->orderBy('code')->get();
        foreach ($products as $product) {
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
        $filename = ($isTemplate ? 'plantilla-productos-' : 'productos-') . now('America/Bogota')->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * PDF del valor del inventario: un renglón por producto que controla
     * inventario y tiene stock positivo (stock x costo promedio), con los
     * totales al inicio y al final. Mismo criterio que la pestaña "Valor del
     * inventario" y la tarjeta del panel. Se renderiza por partes y se unen
     * (ver PdfMerger) para no agotar la memoria con inventarios grandes.
     */
    public function inventoryValuePdf(Request $request)
    {
        set_time_limit(300);

        $company = $this->currentCompany($request);
        $this->authorizeInventoryManagement($company);

        $items = $company->products()->active()
            ->where('tracks_inventory', true)
            ->where('stock', '>', 0)
            ->get(['code', 'description', 'stock', 'average_cost'])
            ->map(function ($product) {
                $stock = (float) $product->stock;
                $cost = (float) ($product->average_cost ?? 0);

                return [
                    'code' => (string) $product->code,
                    'description' => (string) $product->description,
                    'stock' => $stock,
                    'average_cost' => $cost,
                    'total' => round($stock * $cost, 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $totals = [
            'cost' => round((float) $items->sum('total'), 2),
            'units' => (float) $items->sum('stock'),
            'products' => $items->count(),
        ];

        $chunks = $items->chunk(300)->map(fn ($chunk) => $chunk->values()->all())->all() ?: [[]];
        $lastIndex = count($chunks) - 1;

        $parts = [];
        foreach ($chunks as $index => $rows) {
            $pdf = Pdf::loadView('products.inventory-value-pdf', [
                'company' => $company,
                'rows' => $rows,
                'totals' => $totals,
                'isFirst' => $index === 0,
                'isLast' => $index === $lastIndex,
            ])->setPaper('letter', 'portrait');

            $parts[] = $pdf->output();
            unset($pdf);
            gc_collect_cycles();
        }

        $filename = 'valor-inventario-' . now('America/Bogota')->format('Y-m-d') . '.pdf';

        return response(PdfMerger::merge($parts), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
