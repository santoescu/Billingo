<?php

namespace App\Http\Controllers;

use App\Models\CatalogLink;
use App\Models\Company;
use App\Models\ThirdParty;
use App\Models\Warehouse;
use App\Services\Dian\IssueDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Catálogo público (sin login) para que un cliente final arme su propia
 * cotización: cada acción resuelve la empresa a partir del CatalogLink del
 * token de la URL, no de la empresa "activa" en sesión (no hay usuario
 * autenticado acá). Un link puede quedar atado a una bodega puntual (el
 * catálogo solo muestra/vende de ahí) o a ninguna (todas las bodegas
 * juntas) -- ver CatalogLinkController para cómo se crean.
 * Reusa el mismo pipeline de Cotizaciones ya construido
 * (DocumentoEmitidoController::issueQuotation(), que ya recibe la Company
 * como parámetro explícito y no depende de sesión).
 */
class PublicCatalogController extends Controller
{
    /**
     * Resuelve el link (y la empresa dueña, vía la relación). Un token que
     * no existe es un 404 (link mal copiado/inventado); un token válido de
     * una empresa que ya no tiene el módulo 'cotizaciones' no es 404 -- se
     * deja resolver para que las acciones respondan "servicio no
     * disponible" en vez de un error genérico (el link pudo haber estado
     * funcionando antes).
     */
    private function resolveLink(string $token): CatalogLink
    {
        $link = CatalogLink::findByToken($token);

        abort_unless($link, 404);

        return $link;
    }

    /**
     * Productos vendibles por este link: si está atado a una bodega
     * puntual, solo los que tengan stock ahí (y el "stock" que se le
     * muestra al cliente es el de esa bodega, no el total de la empresa);
     * si es general, el mismo filtro de siempre (stock > 1 en total).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    private function scopedProductsQuery(CatalogLink $link, string $query = '')
    {
        $company = $link->company;

        return $company->products()->active()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($builder) use ($query) {
                    $builder->where('code', 'like', '%' . $query . '%')
                        ->orWhere('barcode', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%');
                });
            })
            ->when($link->warehouse_id, function ($builder) use ($link) {
                $builder->where('warehouse_stocks', 'elemMatch', [
                    'warehouse_id' => $link->warehouse_id,
                    'stock' => ['$gt' => 1],
                ]);
            }, function ($builder) {
                $builder->where('stock', '>', 1);
            });
    }

    /**
     * Traduce productos al shape del JS, ajustando "stock" al de la bodega
     * del link cuando aplica -- el cliente de un link por bodega no debe ver
     * el total de la empresa, solo lo que hay donde le van a despachar.
     * También ajusta "prices" según lo elegido al crear el link: si no se
     * eligió ningún tipo de precio visible, se deja tal cual (todos, mismo
     * comportamiento de antes de esta feature); si se eligieron algunos, la
     * tarjeta y el tooltip del catálogo público solo muestran esos, con el
     * "principal" primero (esa es la que se pinta grande en la tarjeta, ver
     * displayPrice = product.prices[0] en public/catalog.blade.php).
     */
    private function mapProductsForLink(CatalogLink $link, $products, DocumentoEmitidoController $documentController): array
    {
        $mapped = $documentController->mapProductsForJs($products);

        return $mapped->map(function (array $product) use ($link) {
            if ($link->warehouse_id) {
                $warehouseEntry = collect($product['warehouses'])->firstWhere('warehouse_id', $link->warehouse_id);
                $product['stock'] = $warehouseEntry['stock'] ?? 0;
            }

            // No se manda el desglose por bodega en la respuesta pública:
            // un cliente final no debe poder ver, ni siquiera abriendo las
            // herramientas de desarrollador del navegador, cuántas bodegas
            // tiene la empresa ni sus nombres -- solo el total ya resuelto
            // arriba (product.stock).
            unset($product['warehouses']);

            $visibleIds = $link->visible_price_type_ids ?? [];
            if (! empty($visibleIds)) {
                $prices = collect($product['prices'])->filter(fn ($price) => in_array($price['price_type_id'], $visibleIds, true));

                if ($link->primary_price_type_id) {
                    $prices = $prices->sortByDesc(fn ($price) => $price['price_type_id'] === $link->primary_price_type_id);
                }

                $product['prices'] = $prices->values()->all();
            }

            return $product;
        })->all();
    }

    public function show(Request $request, string $token, DocumentoEmitidoController $documentController)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        if (! $company->hasModule('cotizaciones')) {
            return view('public.unavailable');
        }

        $products = $this->scopedProductsQuery($link)->orderBy('description')->limit(60)->get();

        return view('public.catalog', [
            'token' => $token,
            'company' => $company,
            'warehouseId' => $link->warehouse_id,
            'products' => $this->mapProductsForLink($link, $products, $documentController),
        ]);
    }

    public function productSearch(Request $request, string $token, DocumentoEmitidoController $documentController)
    {
        $link = $this->resolveLink($token);

        if (! $link->company->hasModule('cotizaciones')) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $query = trim((string) $request->query('q', ''));
        $products = $this->scopedProductsQuery($link, $query)->orderBy('description')->limit(50)->get();

        return response()->json([
            'products' => $this->mapProductsForLink($link, $products, $documentController),
        ]);
    }

    /**
     * Busca un cliente por identificación exacta (no por nombre, a
     * diferencia de la búsqueda incremental que usa el staff): un cliente
     * final se identifica solo con su cédula/NIT, y debe haber a lo mucho
     * una coincidencia por empresa.
     */
    public function findClient(Request $request, string $token)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        if (! $company->hasModule('cotizaciones')) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $identificacion = trim((string) $request->query('identificacion', ''));
        if ($identificacion === '') {
            return response()->json(['client' => null]);
        }

        $client = $company->clients()->where('identificacion', $identificacion)->first();

        return response()->json(['client' => $client ? $this->mapClientForJs($client) : null]);
    }

    /**
     * Crea el cliente final con los datos mínimos (identificación + nombre,
     * el resto opcional) -- mismo patrón que ThirdPartyController::store(),
     * simplificado porque acá no hay un formulario completo de cliente.
     */
    public function storeClient(Request $request, string $token)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        if (! $company->hasModule('cotizaciones')) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $data = $request->validate([
            'identification_type' => ['required', 'string', 'in:11,12,13,21,22,31,41,42,47,48,50,91'],
            'identificacion' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = $company->clients()->where('identificacion', $data['identificacion'])->first();
        if ($existing) {
            return response()->json(['client' => $this->mapClientForJs($existing)]);
        }

        $client = ThirdParty::create([
            'company_id' => (string) $company->_id,
            'roles' => ['cliente'],
            'identification_type' => $data['identification_type'],
            'identificacion' => $data['identificacion'],
            'dv' => $data['identification_type'] === '31' ? Company::calculateVerificationDigit($data['identificacion']) : null,
            'name' => $data['name'],
            'person_type' => '2',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return response()->json(['client' => $this->mapClientForJs($client)]);
    }

    private function mapClientForJs(ThirdParty $client): array
    {
        return [
            'id' => (string) $client->_id,
            'identification_type' => $client->identification_type,
            'identificacion' => $client->identificacion,
            'name' => $client->name,
            'person_type' => $client->person_type,
            'fiscal_responsibilities' => $client->fiscal_responsibilities,
            'address' => $client->address,
            'department_code' => $client->department_code,
            'city_code' => $client->city_code,
            'phone' => $client->phone,
            'email' => $client->email,
        ];
    }

    /**
     * Arma y guarda la cotización del cliente final -- mismo validate()/
     * shape que QuotationController::store(), pero sin depender de sesión y
     * exigiendo cliente_identificacion (acá no existe el "consumidor final"
     * del POS, el cliente siempre se identifica primero). Si el link está
     * atado a una bodega, esa bodega se le pega a cada línea del lado del
     * servidor (no confía en lo que mande el navegador), para que quede
     * lista para descontar si el staff la convierte en venta.
     */
    public function store(Request $request, string $token, DocumentoEmitidoController $documentController, IssueDocumentService $service)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        if (! $company->hasModule('cotizaciones')) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $resolution = $documentController->resolutionsFor($company, 'COT')->first();
        if (! $resolution) {
            return response()->json(['message' => __('This service is not available right now.')], 422);
        }

        if ($link->warehouse_id) {
            $items = array_map(function (array $item) use ($link) {
                $item['bodega_id'] = $link->warehouse_id;

                return $item;
            }, $request->input('items', []));

            $request->merge(['items' => $items]);
        }

        try {
            $quotation = $documentController->issueQuotation($request, $company, $resolution, $service);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not issue the quotation.')], 500);
        }

        return response()->json([
            'numeral' => $quotation->numeral,
            'total_formatted' => $quotation->total_formatted,
            'pdf_url' => route('public.catalog.quotations.pdf', ['token' => $token, 'quotation' => $quotation->_id]),
        ]);
    }

    public function pdf(Request $request, string $token, string $quotation)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        abort_unless($company->hasModule('cotizaciones'), 404);

        $documento = $company->quotations()->where('_id', $quotation)->first();
        abort_unless($documento, 404);

        $warehouseIds = collect($documento->payload['lineas'] ?? [])->pluck('bodega_id')->filter()->unique()->values()->all();
        $warehousesById = Warehouse::whereIn('_id', $warehouseIds)->get()->keyBy(fn (Warehouse $w) => (string) $w->_id);

        $pdf = Pdf::loadView('quotations.pdf', compact('company', 'documento', 'warehousesById'))
            ->setPaper('letter', 'portrait');

        return $pdf->stream('cotizacion-' . $documento->numeral . '.pdf');
    }
}
