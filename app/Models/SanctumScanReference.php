<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SanctumScanReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_hash',
        'schema_version',
        'resource_type',
        'resource_id',
        'display_name',
        'display_description',
        'status',
        'expires_at',
        'revoked_at',
        'created_by_guard',
        'created_by_id',
        'revoked_by_guard',
        'revoked_by_id',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'created_by_id' => 'integer',
        'revoked_by_id' => 'integer',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];
}
