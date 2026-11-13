<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class MoneyOutLogs extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $statusInfo = [
            "success" =>      1,
            "pending" =>      2,
            "rejected" =>     3,
        ];
        return[
            'id' => $this->id,
            'trx' => $this->trx_id,
            'gateway_name' => $this->currency->gateway->name,
            'gateway_currency_name' => $this->currency->name,
            'transaction_type' => $this->type,
            'request_amount' => isCrypto($this->request_amount,get_default_currency_code(),$this->currency->gateway->crypto),
            'payable' => isCrypto($this->payable,$this->creator_wallet->currency->code,$this->currency->gateway->crypto),
            'exchange_rate' => '1 ' .get_default_currency_code().' = '.isCrypto($this->currency->rate,$this->currency->currency_code,$this->currency->gateway->crypto),
            'total_charge' => isCrypto($this->charge->total_charge,$this->currency->currency_code,$this->currency->gateway->crypto),
            'current_balance' => isCrypto($this->available_balance,get_default_currency_code(),$this->currency->gateway->crypto),
            'status' => $this->stringStatus->value,
            'date_time' => $this->created_at,
            'status_info' =>(object)$statusInfo,
            'rejection_reason' =>$this->reject_reason??"",
           ];
    }
}
