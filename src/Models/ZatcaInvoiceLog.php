<?php

namespace Aghfatehi\Zatca\Models;

use Illuminate\Database\Eloquent\Model;

class ZatcaInvoiceLog extends Model
{
    protected $table = 'zatca_invoice_logs';

    protected $fillable = [
        'invoice_serial',
        'invoice_uuid',
        'invoice_hash',
        'status',
        'phase',
        'environment',
        'request_payload',
        'response_payload',
        'error_message',
        'submitted_at',
        'cleared_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'cleared_at' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->whereNull('cleared_at');
    }
}
