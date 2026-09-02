<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SupportTicket extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'support_tickets';

    const STATUS_OPEN = 'abierto';
    const STATUS_ASSIGNED = 'asignado';
    const STATUS_CLOSED = 'cerrado';

    const STATUSES = [self::STATUS_OPEN, self::STATUS_ASSIGNED, self::STATUS_CLOSED];

    const PRIORITY_LOW = 'baja';
    const PRIORITY_MEDIUM = 'media';
    const PRIORITY_HIGH = 'alta';
    const PRIORITY_URGENT = 'urgente';

    const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

    protected $fillable = [
        'company_id',
        'user_id',
        'subject',
        'module',
        'status',
        'assigned_to',
        'priority',
        'staff_last_viewed_at',
        'contact_name',
        'contact_phone',
        'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'staff_last_viewed_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * true cuando el ticket viene del formulario público "Contáctanos" del
     * login (un prospecto sin cuenta todavía) -- no tiene company_id ni
     * user_id, en cambio trae los datos de contacto sueltos
     * (contact_name/contact_phone/contact_email).
     */
    public function getIsLeadAttribute(): bool
    {
        return ! $this->company_id;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Superadmin al que quedó asignado el ticket (opcional) -- desde que se
     * asigna, los avisos de mensajes nuevos de la empresa le llegan a esta
     * persona en particular, no a todo el equipo de soporte (ver
     * SupportTicketController::notifyStaff()).
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    /**
     * Historial de cambios de estado/asignación (quién hizo qué), separado
     * de los mensajes -- no es parte de la conversación con la empresa,
     * solo lo ve el staff (ver admin/tickets/show.blade.php).
     */
    public function activities()
    {
        return $this->hasMany(SupportTicketActivity::class, 'support_ticket_id')->orderByDesc('created_at');
    }

    /**
     * "module" guarda una clave de config('modules'), o null/"general" para
     * solicitudes que no son sobre un módulo puntual (dudas de cuenta,
     * facturación con Billingo, etc.).
     */
    public function getModuleLabelAttribute(): string
    {
        if (! $this->module || $this->module === 'general') {
            return __('General');
        }

        return config('modules')[$this->module]['name'] ?? $this->module;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ASSIGNED => __('Assigned'),
            self::STATUS_CLOSED => __('Closed'),
            default => __('Open'),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            self::STATUS_ASSIGNED => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::STATUS_CLOSED => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
            default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        };
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return self::statusBadgeClasses($this->status);
    }

    public static function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => __('Low'),
            self::PRIORITY_HIGH => __('High'),
            self::PRIORITY_URGENT => __('Urgent'),
            default => __('Medium'),
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::priorityLabel($this->priority);
    }

    public static function priorityBadgeClasses(?string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300',
            self::PRIORITY_HIGH => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            self::PRIORITY_URGENT => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        };
    }

    public function getPriorityBadgeClassesAttribute(): string
    {
        return self::priorityBadgeClasses($this->priority);
    }

    /**
     * "No leído" para el staff: hubo actividad de la empresa (mensaje o
     * creación) después de la última vez que algún miembro del staff hizo
     * algo con el ticket (verlo, responder, cambiar estado o asignar --
     * ver AdminSupportTicketController, que actualiza
     * "staff_last_viewed_at" en cada una de esas acciones). No es por
     * usuario staff individual, es a nivel de ticket -- si un compañero ya
     * lo miró, para el resto ya no cuenta como nuevo.
     */
    public function getIsUnreadForStaffAttribute(): bool
    {
        if (! $this->updated_at) {
            return false;
        }

        return ! $this->staff_last_viewed_at || $this->updated_at->gt($this->staff_last_viewed_at);
    }
}
