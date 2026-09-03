<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Company extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'companies';

    protected $fillable = [
        'name',
        'identification_type',
        'identificacion',
        'dv',
        'person_type',
        'fiscal_responsibilities',
        'address',
        'city_code',
        'department_code',
        'phone',
        'email',
        'status',
        'modules',
        'dian_pin',
        'dian_software_id',
        'dian_certificate_content',
        'dian_certificate_password',
        'dian_certificate_original_name',
        'dian_environment',
        'dian_test_set_id',
        'dian_test_set_zip_key',
        'dian_habilitado',
        'api_token',
        'logo_data',
        'logo_mime',
    ];

    const DIAN_AMBIENTE_PRODUCCION = '1';
    const DIAN_AMBIENTE_PRUEBAS = '2';

    protected $hidden = [
        'dian_certificate_password',
        'dian_certificate_content',
        'api_token',
        'logo_data',
    ];

    protected $appends = [
        'dian_certificate_filename',
        'logo_url',
    ];

    protected function casts(): array
    {
        return [
            'dian_certificate_password' => 'encrypted',
            'dian_certificate_content' => 'encrypted',
            'dian_habilitado' => 'boolean',
        ];
    }

    /**
     * Genera un nuevo token de API para la empresa (reemplaza el anterior, si existía)
     * y lo guarda ya hasheado. El valor en texto plano solo se puede ver esta vez.
     *
     * @return string Token en texto plano, para mostrárselo a la empresa una sola vez.
     */
    public function generateApiToken(): string
    {
        $plainTextToken = bin2hex(random_bytes(32));
        $this->update(['api_token' => hash('sha256', $plainTextToken)]);

        return $plainTextToken;
    }

    /**
     * Resuelve la empresa dueña de un token de API en texto plano.
     *
     * @param  string  $plainTextToken  Token en texto plano recibido en la petición.
     * @return self|null Empresa dueña del token, o null si no coincide con ninguna.
     */
    public static function findByApiToken(string $plainTextToken): ?self
    {
        return self::where('api_token', hash('sha256', $plainTextToken))->first();
    }

    /**
     * Links públicos de catálogo/cotizaciones (compartibles con clientes
     * finales, sin login) -- una empresa puede tener varios: uno general
     * (sin bodega) que muestra el stock de todas juntas, y opcionalmente
     * uno por bodega puntual, para mandarle a cada sucursal/cliente el link
     * que solo le muestra lo que hay ahí.
     */
    public function catalogLinks()
    {
        return $this->hasMany(CatalogLink::class);
    }

    /**
     * Calcula el dígito de verificación de un NIT usando el algoritmo módulo 11 de la DIAN.
     */
    public static function calculateVerificationDigit(string $identification): string
    {
        $digits = array_reverse(array_map('intval', str_split(preg_replace('/\D/', '', $identification))));
        $weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

        $sum = 0;
        foreach ($digits as $i => $digit) {
            $sum += $digit * ($weights[$i] ?? 0);
        }

        $remainder = $sum % 11;

        return (string) ($remainder > 1 ? 11 - $remainder : $remainder);
    }

    public function members()
    {
        return $this->hasMany(CompanyMember::class);
    }

    /**
     * IDs de usuario administradores de esta empresa: el 'owner' (acceso
     * implícito a todo, sin entrada propia en 'modules') más cualquier
     * miembro con rol 'administrador' en AL MENOS un módulo, sin importar
     * cuál -- para avisos que le interesan a "quien administra" sin atarlos
     * a un módulo puntual (alertas de stock bajo, cartera vencida, etc.).
     *
     * @return array<int, string>
     */
    public function administratorUserIds(): array
    {
        return $this->members()
            ->get()
            ->filter(function (CompanyMember $member) {
                if ($member->role === 'owner') {
                    return true;
                }

                return collect($member->modules ?? [])->contains(fn ($assignment) => ($assignment['role'] ?? null) === 'administrador');
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Igual que administratorUserIds(), pero limitado a quien administra UN
     * módulo en particular (para avisos de soporte: si un ticket es sobre
     * POS, que le llegue a quien administra POS, no a todos los admins de
     * la empresa) -- el 'owner' siempre entra, sin importar el módulo. Si
     * nadie califica (módulo sin admin asignado, o "general"/null), cae de
     * vuelta a administratorUserIds() para no dejar el aviso sin nadie a
     * quien llegarle.
     *
     * @param  string|null  $module
     * @return array<int, string>
     */
    public function administratorUserIdsForModule(?string $module): array
    {
        if (! $module || $module === 'general') {
            return $this->administratorUserIds();
        }

        $ids = $this->members()
            ->get()
            ->filter(function (CompanyMember $member) use ($module) {
                if ($member->role === 'owner') {
                    return true;
                }

                return collect($member->modules ?? [])->contains(fn ($assignment) => ($assignment['module'] ?? null) === $module && ($assignment['role'] ?? null) === 'administrador');
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        return $ids ?: $this->administratorUserIds();
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    public function availableModules(): array
    {
        return collect(config('modules'))
            ->only($this->modules ?? [])
            ->all();
    }

    public function thirdParties()
    {
        return $this->hasMany(ThirdParty::class);
    }

    public function clients()
    {
        return $this->thirdParties()->withRole('cliente');
    }

    public function providers()
    {
        return $this->thirdParties()->withRole('proveedor');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function priceTypes()
    {
        return $this->hasMany(PriceType::class);
    }

    public function resolutions()
    {
        return $this->hasMany(Resolution::class);
    }

    public function cashShifts()
    {
        return $this->hasMany(CashShift::class);
    }

    /**
     * Código de cuenta que se usa en los servicios de la DIAN: es el mismo
     * NIT/identificación de la empresa, no hace falta pedirlo aparte.
     */
    public function dianAccountCode(): ?string
    {
        return $this->identificacion;
    }

    public function dianCertificates()
    {
        return $this->hasMany(DianCertificate::class)->orderByDesc('created_at');
    }

    /**
     * El certificado que se usa para firmar en este momento: el más
     * reciente entre los que todavía no vencieron. Si ninguno está vigente
     * (o no hay ninguno cargado todavía) devuelve null -- quien firme debe
     * avisar que hace falta agregar uno nuevo, no reusar uno vencido.
     */
    public function activeDianCertificate(): ?DianCertificate
    {
        $this->migrateLegacyDianCertificate();

        return $this->dianCertificates()
            ->get()
            ->reject(fn (DianCertificate $certificate) => $certificate->is_expired)
            ->first();
    }

    /**
     * Antes de que existiera esta colección, cada empresa solo tenía un
     * certificado guardado directo en sus propios campos ("dian_certificate_*").
     * La primera vez que se consulta la lista de certificados de una empresa
     * que todavía tiene ese campo legado (y no se ha migrado todavía), se
     * copia a un registro de DianCertificate para que conviva con los nuevos
     * sin perder el que ya estaba configurado.
     */
    public function migrateLegacyDianCertificate(): void
    {
        if (! $this->dian_certificate_content || ! $this->dian_certificate_password) {
            return;
        }

        if ($this->dianCertificates()->count() > 0) {
            return;
        }

        try {
            $info = DianCertificate::parseInfo($this->dian_certificate_content, $this->dian_certificate_password);
        } catch (\RuntimeException) {
            return;
        }

        $this->dianCertificates()->create([
            'content' => $this->dian_certificate_content,
            'password' => $this->dian_certificate_password,
            'original_name' => $this->dian_certificate_original_name,
            'subject_name' => $info['subject_name'],
            'valid_from' => $info['valid_from'],
            'valid_to' => $info['valid_to'],
        ]);
    }

    public function getDianCertificateFilenameAttribute(): ?string
    {
        if (! $this->dian_certificate_content) {
            return null;
        }

        return $this->dian_certificate_original_name ?: __('Certificate');
    }

    /**
     * Igual que Product::getImageUrlAttribute() -- guardado en base64 en el
     * documento de Mongo, no en disco (ver comentario allá).
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_data || ! $this->logo_mime) {
            return null;
        }

        return 'data:' . $this->logo_mime . ';base64,' . $this->logo_data;
    }

    public function documentosEmitidos()
    {
        return $this->hasMany(DocumentoEmitido::class);
    }

    public function documentosPos()
    {
        return $this->hasMany(DocumentoPos::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function sellers()
    {
        return $this->hasMany(Seller::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Contratos que cubren esta empresa -- un mismo contrato (con sus contadores de uso) puede
     * cubrir varias empresas a la vez (ver CompanyContract::$company_ids), así que esto no es
     * un hasMany por FK simple: se busca por contención en el arreglo "company_ids".
     */
    public function contracts()
    {
        return CompanyContract::where('company_ids', (string) $this->_id);
    }

    /**
     * @param  string  $module  Uno de: invoicing, pos, cotizaciones.
     * @return CompanyContract|null El contrato vigente hoy que cubre ese módulo específico
     *                              (puede haber otro contrato vigente al mismo tiempo para un
     *                              módulo distinto -- cada uno se resuelve por separado), el
     *                              más reciente si hay varios que califican para el mismo módulo.
     */
    public function activeContractFor(string $module): ?CompanyContract
    {
        return $this->contracts()
            ->get()
            ->filter(fn (CompanyContract $contract) => $contract->isWithinDateRange() && $contract->coversModule($module))
            ->sortByDesc(fn (CompanyContract $contract) => $contract->starts_at)
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'active')
                ->orWhereNull('status')
                ->orWhere('status', '');
        });
    }

}
