<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctumScanExchangePaymentEvidence extends Model
{
    protected $guarded = ['id'];

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
