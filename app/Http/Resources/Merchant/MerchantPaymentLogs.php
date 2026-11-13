<?php

namespace App\Http\Resources\Merchant;

use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPaymentLogs extends JsonResource
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
        return[
            'id'                            => $this->id,
            'trx'                           => $this->trx_id,
            'transaction_type'              => $this->type,
            'transaction_heading'           => "Payment Money from @" . @$this->details->sender_username." (".@$this->details->pay_type.")",
            'request_amount'                => getAmount($this->request_amount,get_wallet_precision()).' '.get_default_currency_code() ,
            'payable'                       => getAmount($this->payable,get_wallet_precision()).' '.get_default_currency_code(),
            'env_type'                      => $this->details->env_type,
            'sender'                        => $this->details->sender_username,
            'business_name'                 => $this->details->payment_to,
            'payment_amount'                => getAmount($this->details->charges->receiver_amount,get_wallet_precision()).' '.get_default_currency_code(),
            'status'                        => $this->stringStatus->value,
            'status_value'                  => @$this->status,
            'refund_action_status'          => $this->details->env_type == PaymentGatewayConst::ENV_PRODUCTION && $this->status == PaymentGatewayConst::STATUSSUCCESS,
            'refund_action_url'             => setRoute('api.merchant.refund.balance.payment.gateway'),
            'refund_action_type'            => "POST",
            'date_time'                     => $this->created_at,
            'status_info'                   => (object)$statusInfo,
            'rejection_reason'              => $this->reject_reason??"",

        ];
    }
}
