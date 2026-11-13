<?php

namespace Database\Seeders\Admin;

use App\Constants\SmsTemplateConst;
use App\Models\Admin\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates every SMS template for each account type (user/agent/merchant) that receives it.
     * Refreshes templates no admin has edited yet (last_edit_by null), admin edited templates are never overwritten.
     *
     * @return void
     */
    public function run()
    {
        foreach(SmsTemplateConst::templates() as $config) {
            $sms_template = SmsTemplate::firstOrNew(['act' => $config['act'], 'group' => $config['group']]);
            $info = [
                'name'          => $config['type'],
                'subj'          => $config['type'],
                'shortcodes'    => SmsTemplateConst::variables($config['act']),
            ];

            if($sms_template->exists && $sms_template->last_edit_by) {
                $sms_template->update($info);
                continue;
            }

            $sms_template->fill(array_merge($info, [
                'sms_body'      => $this->defaultBody($config['act']),
                'sms_status'    => $sms_template->exists ? $sms_template->sms_status : 1,
            ]))->save();
        }
    }

    /**
     * Standard SMS text. Short (1-2 SMS parts), only placeholders the system sends for that SMS.
     */
    public function defaultBody(string $act): string
    {
        return [
            'SVER_CODE'             => "Your phone verification code is {{code}}. Do not share this code with anyone.",
            'PASS_RESET_CODE'       => "Your password reset code is {{code}}. Do not share this code with anyone. If you did not request it, please ignore this message.",
            'NEW_USER_REGISTER'     => "Welcome to {{site_name}}! Your account has been created. Login: {{email}} / {{phone}}, Password: {{password}}. Please change your password after your first login.",

            'ADD_MONEY_COMPLETE'    => "{{amount}} {{currency}} has been added to your wallet via {{gateway_name}}.",
            'ADD_MONEY_REQUEST'     => "Your add money request of {{amount}} via {{method}} has been received and is under review. Charge: {{charge}}. TRX: {{trx}}",
            'ADD_MONEY_APPROVE'     => "Your add money request of {{amount}} via {{gateway_name}} has been approved. TRX: {{trx}}",
            'ADD_MONEY_REJECT'      => "Your add money request of {{amount}} via {{gateway_name}} has been rejected. Reason: {{rejection_message}}. TRX: {{trx}}",

            'WITHDRAW_REQUEST'      => "Your withdraw request of {{amount}} via {{method_name}} has been received. You will get {{will_get}}. TRX: {{trx}}, {{time}}",
            'WITHDRAW_APPROVE'      => "Your withdraw request of {{amount}} via {{method}} has been approved. TRX: {{trx}}, {{time}}",
            'WITHDRAW_REJECT'       => "Your withdraw request of {{amount}} via {{method}} has been rejected. Reason: {{reject_reason}}. TRX: {{trx}}, {{time}}",

            'SEND_MONEY'            => "You sent {{amount}} to {{to_user}}. Charge: {{charge}}. Balance: {{balance}}. TRX: {{trx}}, {{time}}",
            'SEND_MONEY_RECEIVE'    => "You received {{amount}} from {{from_user}}. Balance: {{balance}}. TRX: {{trx}}, {{time}}",
            'REQUEST_MONEY'         => "Your money request of {{amount}} has been sent to {{receiver}}. TRX: {{trx}}, {{time}}",
            'REQUEST_MONEY_RECEIVER'=> "{{requestor}} has requested {{amount}} from you. Log in to accept or reject it. TRX: {{trx}}, {{time}}",
            'MONEY_OUT'             => "Money out of {{amount}} to {{agent}} was successful. Charge: {{charge}}. TRX: {{trx}}, {{time}}",
            'MONEY_OUT_TO_AGENT'    => "You received a money out of {{amount}} from {{user}}. TRX: {{trx}}, {{time}}",
            'MONEY_IN_AGENT'        => "Money in of {{amount}} to {{user}} was successful. Charge: {{charge}}, Payable: {{payable}}. Balance: {{balance}}. TRX: {{trx}}",
            'MONEY_IN'              => "{{amount}} has been added to your wallet by {{agent}}. Balance: {{balance}}. TRX: {{trx}}, {{time}}",
            'MAKE_PAYMENT'          => "Your payment of {{amount}} to {{to_user}} was successful. Charge: {{charge}}. TRX: {{trx}}, {{time}}",
            'MAKE_PAYMENT_MERCHANT' => "You received a payment of {{amount}} from {{from_user}}. TRX: {{trx}}, {{time}}",

            'SEND_REMITTANCE'       => "Your remittance of {{send_amount}} to {{recipient}} ({{to_country}}) has been received. Recipient gets {{recipient_amount}}. TRX: {{trx}}",
            'SEND_REMITTANCE_AGENT' => "Remittance of {{send_amount}} from {{sender_recipient}} to {{receiver_recipient}} ({{to_country}}) has been received. Recipient gets {{recipient_amount}}. TRX: {{trx}}",
            'REMITTANCE_APPROVED'   => "Your remittance of {{send_amount}} to {{recipient}} ({{to_country}}) has been approved. Recipient gets {{recipient_amount}}. TRX: {{trx}}",
            'REMITTANCE_REJECTED'   => "Your remittance of {{send_amount}} to {{recipient}} has been rejected. Reason: {{reason}}. Balance: {{balance}}. TRX: {{trx}}",

            'BILL_PAY_APPROVED'     => "Your {{bill_type}} bill payment of {{amount}} (Bill No: {{bill_number}}, {{month}}) has been approved. TRX: {{trx}}, {{time}}",
            'BILL_PAY_REJECTED'     => "Your {{bill_type}} bill payment of {{amount}} (Bill No: {{bill_number}}) has been rejected. Reason: {{reason}}. Balance: {{balance}}. TRX: {{trx}}",
            'BILL_PAY'              => "Your {{bill_type}} bill payment of {{amount}} (Bill No: {{bill_number}}, {{month}}) has been received. TRX: {{trx}}, {{time}}",
            'MOBILE_TOPUP_APPROVED' => "Your mobile top up of {{amount}} to {{mobile_number}} has been approved. TRX: {{trx}}, {{time}}",
            'MOBILE_TOPUP_REJECTED' => "Your mobile top up of {{amount}} to {{mobile_number}} has been rejected. Reason: {{reason}}. Balance: {{balance}}. TRX: {{trx}}",
            'MOBILE_TOPUP'          => "Mobile top up of {{amount}} to {{mobile_number}} ({{name}}) has been received. Payable: {{payable}}. TRX: {{trx}}, {{time}}",

            'GIFT_CARD'             => "Your order for {{qty}} x {{title}} has been placed. Total payable: {{payable}}. TRX: {{trx}}, {{time}}",
            'VIRTUAL_CARD_BUY'      => "Your virtual card {{card_pan}} has been created with {{card_amount}}. TRX: {{trx}}, {{time}}",
            'VIRTUAL_CARD_FUND'     => "{{request_amount}} has been added to your virtual card {{card_pan}}. Card balance: {{card_amount}}. TRX: {{trx}}",
            'VIRTUAL_CARD_WITHDRAW' => "{{request_amount}} has been withdrawn from your virtual card {{card_pan}}. Card balance: {{card_amount}}. TRX: {{trx}}",

            'REFUND'                => "{{title}}. Amount: {{amount}}. Balance: {{balance}}. {{time}}",
            'PAYMENT_LINK_BUYER'    => "Your payment of {{request_amount}} via payment link was successful. TRX: {{trx}}, {{time}}",
            'PAYMENT_LINK_SENDER'   => "Your payment of {{request_amount}} via payment link was successful. Charge: {{total_charge}}. TRX: {{trx}}, {{time}}",
            'PAYMENT_LINK_USER'     => "You received {{request_amount}} via your payment link. Charge: {{total_charge}}, You get: {{will_pay}}. TRX: {{trx}}",

            'KYC_APPROVED'          => "Congratulations! Your KYC verification has been approved. Your account is now fully verified.",
            'KYC_REJECTED'          => "Your KYC verification has been rejected. Reason: {{reason}}. Please submit your information again.",
            'REGISTRATION_BONUS'    => "Welcome to {{site_name}}, {{user_name}}! A registration bonus of {{amount}} has been added to your wallet.",
        ][$act];
    }
}
