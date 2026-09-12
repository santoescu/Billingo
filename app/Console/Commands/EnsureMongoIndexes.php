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
            // Listado de links de una empresa (ver QuotationController::create()).
            ['key' => ['company_id' => 1, 'created_at' => 1]],
        ]);

        $this->ensureIndexes('documentos_emitidos', [
            ['key' => ['company_id' => 1, 'ambiente' => 1, 'created_at' => 1]],
            ['key' => ['company_id' => 1, 'numeral' => 1]],
        ]);

        $this->ensureIndexes('documentos_pos', [
            ['key' => ['company_id' => 1, 'created_at' => 1]],
            ['key' => ['company_id' => 1, 'numeral' => 1]],
        ]);

        $this->ensureIndexes('documentos_recibidos', [
            ['key' => ['company_id' => 1, 'created_at' => 1]],
            // Chequeo de duplicados (ver ReceivedDocumentIngestionService::ingest()) -- corre en
            // cada documento que se sube a mano o llega por el correo de recepción.
            ['key' => ['company_id' => 1, 'uuid' => 1]],
            // Buscador de proveedor de la bandeja (ver DocumentoRecibidoController::data()).
            ['key' => ['company_id' => 1, 'proveedor_id' => 1]],
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

        $this->ensureIndexes('companies', [
            // Se busca por estos dos en CADA request: el primero en toda llamada a la API (ver
            // AuthenticateCompanyApiToken), el segundo en cada correo que llega al webhook de
            // SES (ver SesInboundWebhookController::resolveCompanyFromRecipient()).
            ['key' => ['api_token' => 1]],
            ['key' => ['reception_email_token' => 1]],
        ]);

        $this->ensureIndexes('company_members', [
            // La consulta más caliente de toda la app: corre en CADA request que pasa por
            // EnsureCompanyRole/EnsureCompanyRoleAny (casi cualquier página autenticada), pero
            // esta colección nunca había tenido ni un solo índice.
            ['key' => ['company_id' => 1, 'user_id' => 1]],
            // "¿A qué empresas pertenece este usuario?" (ver User::memberships(), usado al hacer
            // login y en el selector de empresa) -- filtra solo por user_id, un índice compuesto
            // con company_id primero no sirve para esa consulta.
            ['key' => ['user_id' => 1]],
        ]);

        $this->ensureIndexes('email_logs', [
            // Cada evento que manda SES (entregado/abierto/rebotado/spam) busca por esto (ver
            // SesEventWebhookController::handle()).
            ['key' => ['ses_message_id' => 1]],
            // Historial de correos de un documento (ver DocumentoEmitidoController::show()/emailLogs()).
            ['key' => ['documento_id' => 1]],
        ]);

        $this->ensureIndexes('activity_logs', [
            ['key' => ['company_id' => 1, 'created_at' => 1]],
        ]);

        $this->ensureIndexes('support_tickets', [
            ['key' => ['company_id' => 1, 'updated_at' => 1]],
        ]);

        $this->ensureIndexes('payment_methods', [
            ['key' => ['company_id' => 1]],
        ]);

        $this->ensureIndexes('sellers', [
            ['key' => ['company_id' => 1]],
        ]);

        $this->ensureIndexes('warehouses', [
            ['key' => ['company_id' => 1]],
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
