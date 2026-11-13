<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanExchangePaymentEvidence extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (self $evidence) {
            throw new LogicException('Submitted payment evidence is immutable and requires a separate verification record.');
        });

        static::deleting(function (self $evidence) {
            throw new LogicException('Submitted payment evidence cannot be deleted.');
        });
    }

    protected $casts = [
        'exchange_id' => 'integer',
        'amount' => 'decimal:8',
        'submitted_by_id' => 'integer',
        'submitted_at' => 'immutable_datetime',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(SanctumScanExchange::class, 'exchange_id');
    }
}
