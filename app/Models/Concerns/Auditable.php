<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Arr;

/**
 * Registra en "activity_logs" cada creación/edición/borrado de los modelos
 * que lo usan, sin tener que acordarse de llamarlo a mano en cada
 * controlador -- se engancha a los eventos normales de Eloquent
 * (created/updated/deleted), que ya disparan igual con Model::create(),
 * ->update() o ->save()/->delete() sin importar desde qué controlador se
 * llamen. Pensado para modelos de catálogo/configuración de la empresa
 * (Product, ThirdParty, Resolution, CompanyMember, etc.) -- los documentos
 * emitidos, ventas y movimientos de caja/stock ya tienen su propio rastro
 * y no lo usan, para no duplicar información y llenar el log de ruido.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->recordAudit(ActivityLog::ACTION_CREATED));
        static::updated(fn ($model) => $model->recordAudit(ActivityLog::ACTION_UPDATED));
        static::deleted(fn ($model) => $model->recordAudit(ActivityLog::ACTION_DELETED));
    }

    /**
     * Arma y guarda la entrada del log. En "updated" solo guarda los
     * campos que de verdad cambiaron (con su valor antes/después); si el
     * único cambio fue un campo ignorado (ej. "updated_at" desde un
     * touch() suelto), no crea nada -- un cambio "vacío" no le sirve a
     * nadie. Nunca guarda campos marcados como $hidden en el modelo (ej.
     * el password/content de un certificado), mismo criterio que ya se
     * usa para no exponerlos en JSON.
     */
    protected function recordAudit(string $action): void
    {
        $excluded = array_merge($this->getHidden(), ['updated_at', 'created_at', 'id', '_id']);

        if ($action === ActivityLog::ACTION_UPDATED) {
            $dirty = Arr::except($this->getChanges(), $excluded);

            if (empty($dirty)) {
                return;
            }

            $original = $this->getOriginal();
            $changes = collect($dirty)
                ->mapWithKeys(fn ($value, $key) => [$key => ['from' => $original[$key] ?? null, 'to' => $value]])
                ->all();
        } else {
            $changes = Arr::except($this->attributesToArray(), $excluded);
        }

        $user = auth()->user();

        ActivityLog::create([
            'company_id' => $this->company_id ?? null,
            'user_id' => $user ? (string) $user->_id : null,
            'action' => $action,
            'model' => class_basename($this),
            'model_id' => (string) $this->getKey(),
            'label' => $this->auditLabel(),
            'changes' => $changes,
        ]);
    }

    /**
     * Nombre humano del registro afectado, para reconocerlo en el log sin
     * tener que abrirlo (ej. "Camisa azul" en vez de un id de Mongo). Los
     * modelos pueden sobreescribirlo si ninguno de estos campos comunes
     * arma algo útil (ver CompanyMember, que no guarda el nombre del
     * usuario directo).
     */
    protected function auditLabel(): string
    {
        foreach (['name', 'description', 'subject', 'title', 'prefix', 'label', 'original_name'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return (string) $this->getKey();
    }
}
