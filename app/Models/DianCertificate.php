<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use RuntimeException;

class DianCertificate extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'dian_certificates';

    protected $fillable = [
        'company_id',
        'content',
        'password',
        'original_name',
        'subject_name',
        'valid_from',
        'valid_to',
    ];

    protected $hidden = [
        'content',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'encrypted',
            'password' => 'encrypted',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        return ! $this->valid_to || $this->valid_to->isPast();
    }

    /**
     * Abre el .p12/.pfx con la clave dada y extrae la fecha de vigencia y el
     * titular del certificado de firma (cert principal, no la cadena) --
     * usado tanto al subir uno nuevo como para migrar el certificado único
     * legado de una empresa.
     *
     * @param  string  $content  Contenido binario crudo del .p12/.pfx.
     * @param  string  $password  Clave del certificado.
     * @return array{valid_from: \Illuminate\Support\Carbon, valid_to: \Illuminate\Support\Carbon, subject_name: ?string}
     *
     * @throws RuntimeException Si la clave no coincide con el certificado.
     */
    public static function parseInfo(string $content, string $password): array
    {
        $certs = [];

        if (! @openssl_pkcs12_read($content, $certs, $password)) {
            throw new RuntimeException(__('The password does not match this certificate.'));
        }

        $info = openssl_x509_parse($certs['cert']);

        $subjectName = $info['subject']['CN']
            ?? $info['subject']['O']
            ?? null;

        return [
            'valid_from' => \Illuminate\Support\Carbon::createFromTimestamp($info['validFrom_time_t']),
            'valid_to' => \Illuminate\Support\Carbon::createFromTimestamp($info['validTo_time_t']),
            'subject_name' => $subjectName,
        ];
    }
}
