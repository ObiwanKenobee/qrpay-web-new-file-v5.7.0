<?php

namespace App\Models;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SanctumScanExchange extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'merchant_id' => 'integer',
        'buyer_user_id' => 'integer',
        'resource_id' => 'integer',
        'quantity' => 'decimal:4',
        'amount' => 'decimal:8',
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'declined_at' => 'immutable_datetime',
        'dispatched_at' => 'immutable_datetime',
        'received_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(SanctumScanResource::class, 'resource_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SanctumScanExchangeEvent::class, 'exchange_id')
            ->orderBy('created_at');
    }

    public function paymentEvidence(): HasOne
    {
        return $this->hasOne(SanctumScanExchangePaymentEvidence::class, 'exchange_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(SanctumScanExchangeDispute::class, 'exchange_id');
    }
}
