<?php

namespace Aghfatehi\Zatca\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaCertificate extends Model
{
    protected $table = 'zatca_certificates';

    protected $fillable = [
        'egs_uuid',
        'serial_number',
        'certificate',
        'private_key',
        'secret',
        'environment',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
}
