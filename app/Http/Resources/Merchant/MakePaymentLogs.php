<?php

namespace App\Http\Resources\Merchant;

use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Resources\Json\JsonResource;

class MakePaymentLogs extends JsonResource
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
            "success"       => 1,
            "pending"       => 2,
            "hold"          => 3,
            "rejected"      => 4,
            "waiting"       => 5,
            "failed"        => 6,
            "processing"    => 7,
            "refund"        => 8,
        ];
        if($this->attribute == payment_gateway_const()::RECEIVED){
            return[
                'id'                    => @$this->id,
                'type'                  => $this->attribute,
                'trx'                   => @$this->trx_id,
                'transaction_type'      => $this->type,
                'transaction_heading'   => "Received Money from @" .@$this->details->sender->fullname." (".@$this->details->sender->full_mobile.")",
                'recipient_received'    => getAmount(@$this->request_amount,get_wallet_precision()).' '.get_default_currency_code(),
                'current_balance'       => getAmount(@$this->available_balance,get_wallet_precision()).' '.get_default_currency_code(),
                'status'                => @$this->stringStatus->value,
                'status_value'          => @$this->status,
                'refund_action_status'  => $this->status == PaymentGatewayConst::STATUSSUCCESS ? true : false,
                'refund_action_url'     => setRoute('api.merchant.refund.balance.make.payment'),
                'refund_action_type'    => "POST",
                'date_time'             => @$this->created_at,
                'status_info'           => (object)@$statusInfo,
            ];

        }
    }
}
