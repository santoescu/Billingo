<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureMongoIndexes extends Command
{
    protected $signature = 'mongo:ensure-indexes';

    protected $description = 'Crea (o confirma que ya existen) los índices de Mongo para las consultas más pesadas -- createIndex es idempotente, correrlo de nuevo no hace daño';

    /**
     * Este proyecto no usa migraciones para Mongo, así que los índices viven
     * acá en vez de en una migración -- correr este comando (en despliegue,
     * o a mano cuando se agregue una consulta nueva que lo necesite) es la
     * forma de mantenerlos.
     */
    public function handle(): int
    {
        $this->ensureIndexes('products', [
            ['key' => ['company_id' => 1]],
            ['key' => ['company_id' => 1, 'stock' => 1]],
            ['key' => ['company_id' => 1, 'tracks_inventory' => 1]],
            ['key' => ['company_id' => 1, 'code' => 1]],
            ['key' => ['company_id' => 1, 'barcode' => 1]],
            ['key' => ['company_id' => 1, 'warehouse_stocks.warehouse_id' => 1]],
        ]);

        $this->ensureIndexes('third_parties', [
            ['key' => ['company_id' => 1, 'identification_type' => 1, 'identificacion' => 1]],
            ['key' => ['company_id' => 1, 'name' => 1]],
        ]);

        $this->ensureIndexes('catalog_links', [
            ['key' => ['token' => 1]],
        ]);

        $this->ensureIndexes('documentos_emitidos', [
            ['key' => ['company_id' => 1, 'ambiente' => 1, 'created_at' => 1]],
            ['key' => ['ambiente' => 1, 'tipo_documento' => 1, 'status' => 1, 'numeral' => 1]],
            ['key' => ['company_id' => 1, 'numeral' => 1]],
        ]);

        $this->ensureIndexes('documentos_pos', [
            ['key' => ['company_id' => 1, 'created_at' => 1]],
            ['key' => ['company_id' => 1, 'numeral' => 1]],
        ]);

        $this->ensureIndexes('quotations', [
            ['key' => ['company_id' => 1, 'created_at' => 1]],
        ]);

        $this->ensureIndexes('resolutions', [
            ['key' => ['company_id' => 1, 'document_type' => 1, 'environment' => 1]],
        ]);

        $this->ensureIndexes('cash_shifts', [
            ['key' => ['company_id' => 1, 'status' => 1]],
        ]);

        $this->ensureIndexes('cash_movements', [
            ['key' => ['shift_id' => 1]],
            ['key' => ['document_id' => 1, 'type' => 1]],
        ]);

        $this->ensureIndexes('stock_movements', [
            ['key' => ['product_id' => 1, 'created_at' => 1]],
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  string  $collection
     * @param  array<int, array{key: array<string, int>}>  $indexes
     */
    private function ensureIndexes(string $collection, array $indexes): void
    {
        $mongoCollection = DB::connection('mongodb')->getDatabase()->selectCollection($collection);

        foreach ($indexes as $index) {
            $name = $mongoCollection->createIndex($index['key']);
            $this->info("{$collection}: {$name}");
        }
    }
}
