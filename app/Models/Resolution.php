<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use MongoDB\Laravel\Eloquent\Model;

class Resolution extends Model
{
    use Auditable;

    protected $connection = 'mongodb';
    protected $table = 'resolutions';

    protected $fillable = [
        'company_id',
        'resolution_number',
        'resolution_date',
        'prefix',
        'range_from',
        'range_to',
        'current_number',
        'valid_from',
        'valid_to',
        'technical_key',
        'status',
        'environment',
        'is_fixed_test',
        'document_type',
        'is_manual',
    ];

    const DOCUMENT_TYPE_FACTURA_DEFAULT = '01';

    const FIXED_TEST_RESOLUTION_NUMBER = '18760000001';
    const FIXED_TEST_PREFIX = 'SETP';
    const FIXED_TEST_TECHNICAL_KEY = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';
    const FIXED_TEST_RANGE_FROM = 990000000;
    const FIXED_TEST_RANGE_TO = 995000000;
    const FIXED_TEST_VALID_FROM = '2019-01-19';
    const FIXED_TEST_VALID_TO = '2030-01-19';

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'range_from' => 'integer',
            'range_to' => 'integer',
            'current_number' => 'integer',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documentosEmitidos()
    {
        return $this->hasMany(DocumentoEmitido::class);
    }

    protected function auditLabel(): string
    {
        return trim($this->prefix . ' (' . $this->document_type . ')');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'active')
                ->orWhereNull('status')
                ->orWhere('status', '');
        });
    }

    public function isExpired(): bool
    {
        return $this->valid_to && now()->greaterThan($this->valid_to);
    }

    /**
     * Las resoluciones manuales de notas (crédito/débito) no tienen un rango
     * autorizado por la DIAN que se pueda agotar (la empresa las numera por
     * su cuenta), así que sin 'range_to' se consideran sin límite.
     */
    public function isExhausted(): bool
    {
        if ($this->range_to === null) {
            return false;
        }

        return (int) $this->current_number > (int) $this->range_to;
    }

    public function nextInvoiceNumber(): string
    {
        return trim($this->prefix . $this->claimNextNumber(), '-');
    }

    /**
     * Reclama el siguiente número disponible de forma atómica (compare-and-
     * swap: relee el valor actual y solo lo actualiza si nadie más lo movió
     * mientras tanto, reintentando si hubo carrera). El "leer y luego
     * guardar" que había antes (ver historial) dejaba una ventana en la que
     * dos peticiones concurrentes -- el caso típico de un POS con varias
     * cajas cobrando al mismo tiempo -- podían leer el mismo número y las
     * dos terminar usándolo, duplicando la numeración ante la DIAN.
     */
    public function claimNextNumber(): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->refresh();
            $current = (int) ($this->current_number ?: $this->range_from);

            if ($this->range_to !== null && $current > (int) $this->range_to) {
                throw new \RuntimeException('El rango autorizado de la resolución DIAN se agotó.');
            }

            $query = static::where('_id', $this->_id);
            if ($this->current_number === null) {
                $query->whereNull('current_number');
            } else {
                $query->where('current_number', $current);
            }

            $updated = $query->update(['current_number' => $current + 1]);

            if ($updated > 0) {
                $this->current_number = $current + 1;

                return $current;
            }
        }

        throw new \RuntimeException('No se pudo reclamar el siguiente número de la resolución (demasiada concurrencia); intenta de nuevo.');
    }

    /**
     * Garantiza que la resolución fija de pruebas de la DIAN (SETP,
     * igual para cualquier facturador en habilitación) exista para esta
     * empresa. Se identifica por 'is_fixed_test' + 'environment', así que es
     * seguro llamarla cada vez que se listan las resoluciones en
     * habilitación: no duplica si ya existe.
     */
    public static function ensureFixedTestResolution(Company $company): self
    {
        return self::firstOrCreate(
            [
                'company_id' => (string) $company->_id,
                'is_fixed_test' => true,
                'environment' => Company::DIAN_AMBIENTE_PRUEBAS,
            ],
            [
                'resolution_number' => self::FIXED_TEST_RESOLUTION_NUMBER,
                'resolution_date' => '0001-01-01',
                'prefix' => self::FIXED_TEST_PREFIX,
                'range_from' => self::FIXED_TEST_RANGE_FROM,
                'range_to' => self::FIXED_TEST_RANGE_TO,
                'current_number' => self::FIXED_TEST_RANGE_FROM,
                'valid_from' => self::FIXED_TEST_VALID_FROM,
                'valid_to' => self::FIXED_TEST_VALID_TO,
                'technical_key' => self::FIXED_TEST_TECHNICAL_KEY,
                'status' => 'active',
                'document_type' => self::DOCUMENT_TYPE_FACTURA_DEFAULT,
                'is_manual' => false,
            ]
        );
    }
}
