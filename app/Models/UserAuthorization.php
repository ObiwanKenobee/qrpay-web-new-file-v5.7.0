<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAuthorization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id'    => 'integer',
        'email'      => 'string',
        'phone'      => 'string',
        'code'       => 'integer',
        'token'      => 'string',
        'temp_data'  => 'object',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
}
