<?php

namespace App\Http\Controllers;

use App\Models\CatalogLink;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use App\Models\Notification;
use App\Models\Quotation;
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
     * Si el catálogo público no está disponible: módulo desactivado o sin un contrato vigente
     * que cubra "cotizaciones". Al cliente final nunca se le debe mostrar el motivo real (sería
     * mostrarle un problema de facturación entre nosotros y la empresa) -- siempre el mismo
     * "servicio no disponible" genérico, sin importar cuál de las dos cosas falló. Si es
     * específicamente por falta de contrato (no porque el módulo esté desactivado a propósito),
     * sí se avisa puertas adentro a los administradores, para que sepan por qué sus clientes no
     * pueden cotizar -- máximo un aviso cada 12 horas por link, para no saturarlos si varios
     * clientes intentan entrar seguido mientras el contrato sigue vencido.
     */
    private function catalogUnavailable(CatalogLink $link): bool
    {
        $company = $link->company;

        if (! $company->hasModule('cotizaciones')) {
            return true;
        }

        if ($company->activeContractFor('cotizaciones')) {
            return false;
        }

        $this->notifyMissingContract($link, $company);

        return true;
    }

    private function notifyMissingContract(CatalogLink $link, Company $company): void
    {
        if ($link->contract_warning_notified_at && $link->contract_warning_notified_at->gt(now()->subHours(12))) {
            return;
        }

        $link->update(['contract_warning_notified_at' => now()]);

        Notification::notifyUsers(
            $company->administratorUserIds(),
            __('Public catalog unavailable'),
            __('Someone tried to open the public quotation catalog (":label"), but there is no active contract covering the Quotations module. Renew it so clients can keep quoting.', ['label' => $link->label]),
        );
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

        if ($this->catalogUnavailable($link)) {
            return view('public.unavailable');
        }

        $products = $this->scopedProductsQuery($link)->orderBy('description')->get();

        return view('public.catalog', [
            'token' => $token,
            'company' => $company,
            'warehouseId' => $link->warehouse_id,
            'products' => $this->mapProductsForLink($link, $products, $documentController),
            'departments' => Department::orderBy('descripcion')->get(),
            'fiscalResponsibilities' => FiscalResponsibility::orderBy('codigo')->get(),
        ]);
    }

    public function productSearch(Request $request, string $token, DocumentoEmitidoController $documentController)
    {
        $link = $this->resolveLink($token);

        if ($this->catalogUnavailable($link)) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $query = trim((string) $request->query('q', ''));
        $products = $this->scopedProductsQuery($link, $query)->orderBy('description')->get();

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

        if ($this->catalogUnavailable($link)) {
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
     * Crea el cliente final -- mismos campos que ThirdPartyController::store() (el formulario
     * completo que usa el staff), simplificado solo en que acá no hay lookup automático contra
     * el registro de la DIAN (esa consulta requiere sesión de empresa autenticada).
     */
    public function storeClient(Request $request, string $token)
    {
        $link = $this->resolveLink($token);
        $company = $link->company;

        if ($this->catalogUnavailable($link)) {
            return response()->json(['message' => __('This service is not available right now.')], 404);
        }

        $data = $request->validate([
            'identification_type' => ['required', 'string', 'in:11,12,13,21,22,31,41,42,47,48,50,91'],
            'identificacion' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'person_type' => ['nullable', 'string', 'in:1,2'],
            'fiscal_responsibilities' => ['nullable', 'array'],
            'fiscal_responsibilities.*' => ['string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'department_code' => ['nullable', 'string', 'max:10'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
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
            'person_type' => $data['person_type'] ?? '2',
            'fiscal_responsibilities' => implode(';', $data['fiscal_responsibilities'] ?? []),
            'address' => $data['address'] ?? null,
            'department_code' => $data['department_code'] ?? null,
            'city_code' => $data['city_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
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

        if ($this->catalogUnavailable($link)) {
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

        $this->notifyQuotationRecipients($company, $quotation);

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

    /**
     * Avisa a todos los que pueden ver el módulo de cotizaciones de esta
     * empresa (cualquier rol, no solo administrador) cuando un cliente
     * arma una desde el catálogo público -- el 'owner' de la empresa
     * también entra aunque no tenga una entrada explícita en 'modules',
     * igual que el resto de la app lo trata como con acceso a todo.
     */
    private function notifyQuotationRecipients(Company $company, Quotation $quotation): void
    {
        $recipientIds = CompanyMember::where('company_id', (string) $company->_id)
            ->get()
            ->filter(function (CompanyMember $member) {
                if ($member->role === 'owner') {
                    return true;
                }

                return collect($member->modules ?? [])->contains(fn ($assignment) => ($assignment['module'] ?? null) === 'cotizaciones');
            })
            ->pluck('user_id')
            ->unique()
            ->all();

        Notification::notifyUsers(
            $recipientIds,
            __('New quotation'),
            __(':client requested quotation :numeral for :total.', [
                'client' => data_get($quotation->payload, 'accounting_customer_party.razon_social', __('Unknown')),
                'numeral' => $quotation->numeral,
                'total' => $quotation->total_formatted,
            ]),
            route('quotations.show', ['quotation' => $quotation->_id]),
        );
    }
}
