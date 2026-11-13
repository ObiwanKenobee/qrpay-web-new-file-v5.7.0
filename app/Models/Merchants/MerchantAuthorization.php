<?php

namespace App\Models\Merchants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantAuthorization extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'merchant_id'    => 'integer',
        'email'      => 'string',
        'phone'      => 'string',
        'code'       => 'integer',
        'token'      => 'string',
        'temp_data'  => 'object',
    ];
    public function merchant() {
        return $this->belongsTo(Merchant::class);
    }
}
