<?php

namespace App\Models;

use App\Models\Merchants\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanctumScanResource extends Model
{
    protected $fillable = [
        'merchant_id',
        'resource_type',
        'display_name',
        'description',
        'category',
        'reference_code',
        'variety',
        'origin_label',
        'occurred_on',
        'quantity',
        'unit',
        'status',
        'archived_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'occurred_on' => 'date',
        'quantity' => 'decimal:4',
        'archived_at' => 'immutable_datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function provenanceEvents(): HasMany
    {
        return $this->hasMany(SanctumScanProvenanceEvent::class, 'resource_id')
            ->orderBy('created_at');
    }
}
