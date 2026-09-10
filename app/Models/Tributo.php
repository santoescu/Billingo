<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Tributo extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'tributos';

    protected $fillable = ['codigo', 'nombre', 'es_nominal', 'tarifas', 'nota'];

    public $timestamps = false;

    private static ?array $nominalCodesCache = null;

    /**
     * Códigos de tributos nominales (calculados con PerUnitAmount x BaseUnitMeasure
     * en vez de Percent x TaxableAmount) -- cacheado en memoria para no repetir la
     * consulta por cada línea del documento.
     *
     * @return array<int, string>
     */
    public static function nominalCodes(): array
    {
        return self::$nominalCodesCache ??= self::where('es_nominal', true)->pluck('codigo')->all();
    }
}
