<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Prospecto para campañas de correo en frío de Billingo mismo (no de sus clientes) -- ver
 * AdminLeadController. Se importa a mano (CSV/Excel) desde una lista conseguida por fuera (ej. un
 * export de cámara de comercio); la plataforma no descubre prospectos sola.
 */
class Lead extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'leads';

    protected $fillable = [
        'nit',
        'razon_social',
        'contact_name',
        'email',
        'city',
        'neighborhood',
        'address',
        'sector',
        'size',
        'registered_at',
        'pitch_note',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
        ];
    }

    public function emailLogs()
    {
        return $this->hasMany(LeadEmailLog::class);
    }

    /**
     * Nombre para el saludo del correo -- el de contacto si se importó, si no "equipo de
     * <razón social>" (mismo criterio que ya usaban las plantillas fijas en
     * emails/leads/outreach-text.blade.php).
     */
    public function greetingName(): string
    {
        return $this->contact_name ?: 'equipo de ' . $this->razon_social;
    }

    /**
     * Variables disponibles para un mensaje personalizado (ver LeadOutreachMail) -- el nombre a
     * la izquierda es lo que se escribe entre doble llave en el asunto/cuerpo, ej. "{{ciudad}}".
     *
     * @return array<string, string>
     */
    public function mergeVariables(): array
    {
        return [
            'nombre' => $this->greetingName(),
            'razon_social' => (string) $this->razon_social,
            'nit' => (string) ($this->nit ?? ''),
            'ciudad' => (string) ($this->city ?? ''),
            'sector' => (string) ($this->sector ?? ''),
            'correo' => (string) $this->email,
        ];
    }

    /**
     * Reemplaza "{{variable}}" en $text por el dato real de este lead (ver mergeVariables()) --
     * cualquier variable que no exista en la lista simplemente no se reemplaza (queda tal cual
     * en el texto), en vez de tronar, para no romper el envío por un typo en el mensaje.
     */
    public function fillMergeVariables(string $text): string
    {
        foreach ($this->mergeVariables() as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }
}
