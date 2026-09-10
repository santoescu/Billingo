<?php

namespace App\Console\Commands;

use App\Models\Tributo;
use Illuminate\Console\Command;

class SeedTributos extends Command
{
    protected $signature = 'tributos:seed';

    protected $description = 'Carga (o actualiza) el catálogo de tributos DIAN (anexo técnico 13.2.2) -- idempotente, correrlo de nuevo solo actualiza los datos, no duplica.';

    /**
     * Este proyecto no usa migraciones para Mongo, así que los catálogos de referencia
     * viven acá en vez de en un seeder tradicional (ver EnsureMongoIndexes.php para el
     * mismo criterio con los índices).
     *
     * Tarifas: solo se cargan cuando el anexo técnico (13.3.11 Tablas de tarifas por
     * Impuesto) fija un valor concreto. Los tributos nominales que la DIAN actualiza por
     * resolución periódica aparte (Timbre, INC Bolsas, INCarbono, INCombustibles) no
     * traen tarifa acá -- el usuario la sigue escribiendo a mano en la línea, igual que
     * hoy con IVA/ICA/INC. IBUA e ICUI sí tienen tarifa fija pero varía por año -- se deja
     * la del año más reciente conocido (2025) en "tarifas", con el detalle de los otros
     * años en "nota".
     */
    public function handle(): int
    {
        $tributos = [
            ['codigo' => '01', 'nombre' => 'IVA', 'es_nominal' => false, 'tarifas' => [0, 5, 16, 19], 'nota' => null],
            ['codigo' => '02', 'nombre' => 'IC', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Impuesto al Consumo Departamental Nominal -- tarifa fijada por cada departamento, no está en el anexo técnico.'],
            ['codigo' => '03', 'nombre' => 'ICA', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Tarifa variable por municipio, no está en el anexo técnico.'],
            ['codigo' => '04', 'nombre' => 'INC', 'es_nominal' => false, 'tarifas' => [2, 4, 8, 16], 'nota' => null],
            ['codigo' => '05', 'nombre' => 'ReteIVA', 'es_nominal' => false, 'tarifas' => [15, 100], 'nota' => null],
            ['codigo' => '06', 'nombre' => 'ReteRenta', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Tarifa variable según el concepto (compras, servicios, honorarios, etc.), más de 30 valores distintos en el anexo técnico -- no se listan acá.'],
            ['codigo' => '07', 'nombre' => 'ReteICA', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Tarifa variable por municipio, no está en el anexo técnico.'],
            ['codigo' => '08', 'nombre' => 'IC Porcentual', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Impuesto al Consumo Departamental Porcentual -- tarifa fijada por cada departamento.'],
            ['codigo' => '20', 'nombre' => 'FtoHorticultura', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Cuota de Fomento Hortifrutícola -- tarifa variable.'],
            ['codigo' => '21', 'nombre' => 'Timbre', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Impuesto de Timbre -- tarifa nominal fijada por resolución DIAN periódica.'],
            ['codigo' => '22', 'nombre' => 'INC Bolsas', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Impuesto Nacional al Consumo de Bolsa Plástica -- tarifa nominal fijada por resolución DIAN periódica.'],
            ['codigo' => '23', 'nombre' => 'INCarbono', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Impuesto Nacional del Carbono -- tarifa nominal fijada por resolución DIAN periódica.'],
            ['codigo' => '24', 'nombre' => 'INCombustibles', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Impuesto Nacional a los Combustibles -- tarifa nominal fijada por resolución DIAN periódica.'],
            ['codigo' => '25', 'nombre' => 'Sobretasa Combustibles', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Tarifa variable.'],
            ['codigo' => '26', 'nombre' => 'Sordicom', 'es_nominal' => true, 'tarifas' => [], 'nota' => 'Contribución minoristas (Combustibles) -- tarifa variable.'],
            ['codigo' => '30', 'nombre' => 'IC Datos', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Impuesto al Consumo de Datos -- tarifa variable.'],
            ['codigo' => '32', 'nombre' => 'ICL', 'es_nominal' => true, 'tarifas' => [295, 200], 'nota' => 'Impuesto al Consumo de Licores -- $295 por grado de alcohol (botella 750cc de licor), $200 por grado de alcohol (botella 750cc de vinos/aperitivos vínicos). Si la botella no es de 750cc, se prorratea: (cc del producto x tarifa) / 750.'],
            ['codigo' => '33', 'nombre' => 'INPP', 'es_nominal' => true, 'tarifas' => [0.00005], 'nota' => 'Impuesto nacional a productos plásticos -- 0.00005 UVT por cada gramo del envase/embalaje/empaque.'],
            ['codigo' => '34', 'nombre' => 'IBUA', 'es_nominal' => true, 'tarifas' => [0, 38, 65], 'nota' => 'Impuesto a las bebidas ultraprocesadas azucaradas -- valores 2025 (nominal, pesos por unidad): $0 si tiene menos de 6g de azúcar por cada 100ml, $38 si tiene entre 6g y 10g, $65 si tiene más de 10g. Valores 2024: $0/$28/$55. Valores 2023: $0/$18/$35. La DIAN actualiza estos valores cada año.'],
            ['codigo' => '35', 'nombre' => 'ICUI', 'es_nominal' => false, 'tarifas' => [20], 'nota' => 'Impuesto a productos comestibles ultraprocesados industrialmente -- tarifa 2025: 20% (sobre el valor total de la línea). Tarifa 2024: 15%. Tarifa 2023: 10%.'],
            ['codigo' => '36', 'nombre' => 'ADV', 'es_nominal' => false, 'tarifas' => [20, 25], 'nota' => 'AD VALOREM sobre el precio de venta al público antes de impuestos -- 25% para licores, 20% para vinos y aperitivos vínicos.'],
            ['codigo' => 'ZZ', 'nombre' => 'Otros', 'es_nominal' => false, 'tarifas' => [], 'nota' => 'Para tributos, tasas o contribuciones no listados en el catálogo -- el nombre de la figura tributaria lo define el facturador y no es causal de rechazo.'],
        ];

        foreach ($tributos as $tributo) {
            Tributo::updateOrCreate(['codigo' => $tributo['codigo']], $tributo);
        }

        $this->info('Catálogo de tributos actualizado: ' . count($tributos) . ' registros.');

        return self::SUCCESS;
    }
}
