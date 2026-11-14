<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SanctumScanUserIdentityCheck extends Model
{
    protected $table = 'sanctum_scan_user_identity_checks';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id'           => 'integer',
        'reviewed_by_admin_id' => 'integer',
        'reviewed_at'       => 'immutable_datetime',
        'created_at'        => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Identity check records are immutable.');
        });

        static::deleting(function () {
            throw new LogicException('Identity check records cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
