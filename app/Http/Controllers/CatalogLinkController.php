<?php

namespace App\Http\Controllers;

use App\Models\CatalogLink;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administración (desde la pantalla de Cotizaciones, autenticado) de los
 * links públicos del catálogo -- ver PublicCatalogController para el lado
 * público que los consume.
 */
class CatalogLinkController extends Controller
{
    /**
     * Crea un link nuevo: sin bodega (catálogo con todas juntas) o atado a
     * una bodega puntual de la empresa (el catálogo solo muestra/vende lo
     * que hay ahí). Una empresa puede tener varios a la vez, por ejemplo uno
     * general y uno por sucursal.
     */
    public function store(Request $request)
    {
        $company = $this->currentCompany($request);

        // Las opciones válidas de bodega/tipo de precio se resuelven ANTES
        // del validate() y se usan con Rule::in(), para que una bodega o
        // tipo de precio que no le pertenece a la empresa sea un error de
        // formulario normal (redirige de vuelta con mensaje) en vez de un
        // abort() crudo que revienta la página con un error sin explicar.
        // Los selects mandan "none" (no "" ni ausencia del campo) cuando
        // queda en "Todas las bodegas"/"Precio base del producto": con ""
        // como valor, Preline lo trata como el placeholder y no se manda de
        // forma confiable (mismo motivo que "all" en pos/sell.blade.php).
        //
        // OJO: ->pluck('_id') no funciona con este ODM de Mongo (devuelve
        // null para cada elemento, probablemente porque "_id" no es un
        // nombre de columna real sino la clave primaria con manejo BSON
        // especial) -- hay que traer los modelos completos y sacar "_id" de
        // cada uno, que sí resuelve bien.
        $companyWarehouseIds = $company->warehouses()->get()->map(fn ($w) => (string) $w->_id)->all();
        $companyPriceTypeIds = $company->priceTypes()->get()->map(fn ($p) => (string) $p->_id)->all();

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'string', Rule::in(array_merge(['none'], $companyWarehouseIds))],
            'primary_price_type_id' => empty($companyPriceTypeIds)
                ? ['nullable']
                : ['required', 'string', Rule::in($companyPriceTypeIds)],
            'visible_price_type_ids' => ['nullable', 'array'],
            'visible_price_type_ids.*' => ['string', Rule::in($companyPriceTypeIds)],
        ]);

        $warehouseId = ($data['warehouse_id'] ?? null) !== 'none' ? ($data['warehouse_id'] ?? null) : null;
        $primaryPriceTypeId = $data['primary_price_type_id'] ?? null;
        $visiblePriceTypeIds = $data['visible_price_type_ids'] ?? [];

        // El precio principal siempre debe poder verse también en la lista
        // del tooltip, si no, el badge grande mostraría un precio que no
        // aparece al pasar el mouse.
        if ($primaryPriceTypeId && ! in_array($primaryPriceTypeId, $visiblePriceTypeIds, true)) {
            $visiblePriceTypeIds[] = $primaryPriceTypeId;
        }

        CatalogLink::create([
            'company_id' => (string) $company->_id,
            'warehouse_id' => $warehouseId,
            'label' => $data['label'] ?? null,
            'token' => CatalogLink::generateToken(),
            'primary_price_type_id' => $primaryPriceTypeId,
            'visible_price_type_ids' => $visiblePriceTypeIds,
        ]);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Public catalog link created.'),
        ]);

        return redirect()->route('quotations.index');
    }

    public function destroy(Request $request, string $catalogLink)
    {
        $company = $this->currentCompany($request);

        $link = $company->catalogLinks()->where('_id', $catalogLink)->first();
        abort_unless($link, 404);

        $link->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Public catalog link deleted.'),
        ]);

        return redirect()->route('quotations.index');
    }
}
