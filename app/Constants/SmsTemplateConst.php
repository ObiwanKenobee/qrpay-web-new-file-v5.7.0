<?php

namespace App\Constants;

class SmsTemplateConst {
    const GROUP_USER        = "user";
    const GROUP_AGENT       = "agent";
    const GROUP_MERCHANT    = "merchant";

    const ACCOUNT_GROUPS = [
        self::GROUP_USER        => "User SMS Templates",
        self::GROUP_AGENT       => "Agent SMS Templates",
        self::GROUP_MERCHANT    => "Merchant SMS Templates",
    ];

    /**
     * Placeholders available in every SMS
     */
    const COMMON_VARIABLES = [
        'site_name'     => "Site Name",
    ];

    /**
     * Placeholder descriptions (keys are what the code sends)
     */
    const VARIABLE_LABELS = [
        'amount'                => "Amount",
        'agent'                 => "Agent",
        'asking_amount'         => "Asking Amount",
        'balance'               => "Current Balance",
        'bill_number'           => "Bill Number",
        'bill_type'             => "Bill Type",
        'card_amount'           => "Card Amount",
        'card_pan'              => "Card Number",
        'card_total_price'      => "Card Total Price",
        'charge'                => "Fees & Charges",
        'code'                  => "Verification Code",
        'currency'              => "Currency",
        'email'                 => "Email",
        'form_country'          => "Sending Country",
        'from_amount'           => "Exchange From Amount",
        'from_user'             => "From User",
        'gateway_name'          => "Payment Gateway",
        'method'                => "Method",
        'method_name'           => "Method Name",
        'mobile_number'         => "Mobile Number",
        'month'                 => "Bill Month",
        'name'                  => "Operator Name",
        'password'              => "Password",
        'payable'               => "Total Payable Amount",
        'phone'                 => "Phone",
        'qty'                   => "Card Quantity",
        'reason'                => "Rejection Reason",
        'receiver'              => "Receiver",
        'receiver_recipient'    => "Receiver Recipient",
        'received_amount'       => "Received Amount",
        'recipient'             => "Recipient",
        'recipient_amount'      => "Recipient Get",
        'reject_reason'         => "Rejection Reason",
        'rejection_message'     => "Rejection Reason",
        'request_amount'        => "Request Amount",
        'requestor'             => "Requester",
        'selling_amount'        => "Selling Amount",
        'send_amount'           => "Send Amount",
        'sender_recipient'      => "Sender Recipient",
        'site_name'             => "Site Name",
        'time'                  => "Date And Time",
        'title'                 => "Title",
        'to_amount'             => "Exchange To Amount",
        'to_country'            => "Receiving Country",
        'to_user'               => "To User",
        'topup_type'            => "Topup Type",
        'total_charge'          => "Total Charge",
        'transaction_type'      => "Transaction Type",
        'trx'                   => "Transaction ID",
        'type'                  => "Pay Type",
        'unit_price'            => "Card Unit Price",
        'user'                  => "User",
        'user_name'             => "Full Name",
        'will_get'              => "Will Get",
        'will_pay'              => "Will Get",
    ];

    const ALL_ACCOUNTS = [self::GROUP_USER, self::GROUP_AGENT, self::GROUP_MERCHANT];
    const USER_AGENT = [self::GROUP_USER, self::GROUP_AGENT];
    const USER_MERCHANT = [self::GROUP_USER, self::GROUP_MERCHANT];

    /**
     * act => type, receivers (account groups), placeholders sent by the code, auth (always sent), required placeholder
     */
    const TEMPLATES = [
        // auth (always sent)
        'SVER_CODE'             => ['type' => "Phone Verification Code",     'groups' => self::ALL_ACCOUNTS, 'keys' => ['code'], 'auth' => true, 'required' => 'code'],
        'PASS_RESET_CODE'       => ['type' => "Password Reset Code",         'groups' => self::ALL_ACCOUNTS, 'keys' => ['code'], 'auth' => true, 'required' => 'code'],
        'NEW_USER_REGISTER'     => ['type' => "New Account Credentials",     'groups' => self::ALL_ACCOUNTS, 'keys' => ['email', 'phone', 'password', 'time'], 'auth' => true, 'required' => 'password'],

        // add money
        'ADD_MONEY_COMPLETE'    => ['type' => "Add Money Success",                   'groups' => self::USER_AGENT, 'keys' => ['amount', 'currency', 'gateway_name']],
        'ADD_MONEY_REQUEST'     => ['type' => "Add Money Pending (Manual)",          'groups' => self::USER_AGENT, 'keys' => ['amount', 'charge', 'method', 'trx']],
        'ADD_MONEY_APPROVE'     => ['type' => "Add Money Approved By Admin",         'groups' => self::USER_AGENT, 'keys' => ['amount', 'gateway_name', 'trx']],
        'ADD_MONEY_REJECT'      => ['type' => "Add Money Rejected By Admin",         'groups' => self::USER_AGENT, 'keys' => ['amount', 'gateway_name', 'rejection_message', 'trx']],

        // withdraw
        'WITHDRAW_REQUEST'      => ['type' => "Withdraw Money Request",              'groups' => self::ALL_ACCOUNTS, 'keys' => ['amount', 'method_name', 'currency', 'will_get', 'trx', 'time']],
        'WITHDRAW_APPROVE'      => ['type' => "Withdraw Approved By Admin",          'groups' => self::ALL_ACCOUNTS, 'keys' => ['amount', 'method', 'trx', 'time']],
        'WITHDRAW_REJECT'       => ['type' => "Withdraw Rejected By Admin",          'groups' => self::ALL_ACCOUNTS, 'keys' => ['amount', 'method', 'reject_reason', 'trx', 'time']],

        // transfers
        'SEND_MONEY'            => ['type' => "Send Money (Sender)",                 'groups' => self::USER_AGENT, 'keys' => ['amount', 'to_user', 'charge', 'balance', 'trx', 'time']],
        'SEND_MONEY_RECEIVE'    => ['type' => "Send Money (Receiver)",               'groups' => self::USER_AGENT, 'keys' => ['amount', 'from_user', 'balance', 'trx', 'time']],
        'REQUEST_MONEY'         => ['type' => "Request Money (Requester)",           'groups' => [self::GROUP_USER], 'keys' => ['amount', 'receiver', 'trx', 'time']],
        'REQUEST_MONEY_RECEIVER'=> ['type' => "Request Money (Receiver)",            'groups' => [self::GROUP_USER], 'keys' => ['amount', 'requestor', 'trx', 'time']],
        'MONEY_OUT'             => ['type' => "Money Out (Sender)",                  'groups' => [self::GROUP_USER], 'keys' => ['amount', 'agent', 'charge', 'trx', 'time']],
        'MONEY_OUT_TO_AGENT'    => ['type' => "Money Out (Receiver)",                'groups' => [self::GROUP_AGENT], 'keys' => ['amount', 'user', 'trx', 'time']],
        'MONEY_IN_AGENT'        => ['type' => "Money In (Sender)",                   'groups' => [self::GROUP_AGENT], 'keys' => ['amount', 'user', 'charge', 'payable', 'balance', 'trx', 'time']],
        'MONEY_IN'              => ['type' => "Money In (Receiver)",                 'groups' => [self::GROUP_USER], 'keys' => ['amount', 'agent', 'balance', 'trx', 'time']],
        'MAKE_PAYMENT'          => ['type' => "Make Payment (Sender)",               'groups' => [self::GROUP_USER], 'keys' => ['amount', 'to_user', 'charge', 'trx', 'time']],
        'MAKE_PAYMENT_MERCHANT' => ['type' => "Make Payment (Receiver)",             'groups' => [self::GROUP_MERCHANT], 'keys' => ['amount', 'from_user', 'trx', 'time']],

        // remittance
        'SEND_REMITTANCE'       => ['type' => "Remittance Request",                  'groups' => [self::GROUP_USER], 'keys' => ['form_country', 'to_country', 'transaction_type', 'recipient', 'send_amount', 'recipient_amount', 'balance', 'trx', 'time']],
        'SEND_REMITTANCE_AGENT' => ['type' => "Remittance Request",                  'groups' => [self::GROUP_AGENT], 'keys' => ['form_country', 'to_country', 'transaction_type', 'sender_recipient', 'receiver_recipient', 'send_amount', 'recipient_amount', 'balance', 'trx', 'time']],
        'REMITTANCE_APPROVED'   => ['type' => "Remittance Approved By Admin",        'groups' => self::USER_AGENT, 'keys' => ['form_country', 'to_country', 'transaction_type', 'recipient', 'send_amount', 'recipient_amount', 'trx', 'time']],
        'REMITTANCE_REJECTED'   => ['type' => "Remittance Rejected By Admin",        'groups' => self::USER_AGENT, 'keys' => ['form_country', 'to_country', 'transaction_type', 'recipient', 'send_amount', 'recipient_amount', 'balance', 'reason', 'trx', 'time']],

        // bill pay / top up
        'BILL_PAY'              => ['type' => "Bill Pay",                            'groups' => self::USER_AGENT, 'keys' => ['amount', 'type', 'bill_type', 'bill_number', 'month', 'trx', 'time']],
        'BILL_PAY_APPROVED'     => ['type' => "Bill Pay Approved By Admin",          'groups' => self::USER_AGENT, 'keys' => ['amount', 'type', 'bill_type', 'bill_number', 'month', 'trx', 'time']],
        'BILL_PAY_REJECTED'     => ['type' => "Bill Pay Rejected By Admin",          'groups' => self::USER_AGENT, 'keys' => ['amount', 'type', 'bill_type', 'bill_number', 'month', 'reason', 'balance', 'trx', 'time']],
        'MOBILE_TOPUP'          => ['type' => "Mobile Topup",                        'groups' => self::USER_AGENT, 'keys' => ['amount', 'name', 'mobile_number', 'charge', 'payable', 'trx', 'time']],
        'MOBILE_TOPUP_APPROVED' => ['type' => "Mobile Topup Approved By Admin",      'groups' => self::USER_AGENT, 'keys' => ['amount', 'topup_type', 'mobile_number', 'trx', 'time']],
        'MOBILE_TOPUP_REJECTED' => ['type' => "Mobile Topup Rejected By Admin",      'groups' => self::USER_AGENT, 'keys' => ['amount', 'topup_type', 'mobile_number', 'reason', 'balance', 'trx', 'time']],

        // cards
        'GIFT_CARD'             => ['type' => "Gift Card Order",                     'groups' => [self::GROUP_USER], 'keys' => ['title', 'unit_price', 'qty', 'card_total_price', 'charge', 'payable', 'trx', 'time']],
        'VIRTUAL_CARD_BUY'      => ['type' => "Virtual Card Create",                 'groups' => [self::GROUP_USER], 'keys' => ['request_amount', 'card_amount', 'card_pan', 'trx', 'time']],
        'VIRTUAL_CARD_FUND'     => ['type' => "Virtual Card Fund",                   'groups' => [self::GROUP_USER], 'keys' => ['request_amount', 'card_amount', 'card_pan', 'trx', 'time']],
        'VIRTUAL_CARD_WITHDRAW' => ['type' => "Virtual Card Withdraw",               'groups' => [self::GROUP_USER], 'keys' => ['request_amount', 'card_amount', 'card_pan', 'trx', 'time']],

        // merchant / payment link
        'REFUND'                => ['type' => "Refund Balance",                      'groups' => self::USER_MERCHANT, 'keys' => ['title', 'amount', 'balance', 'time']],
        'PAYMENT_LINK_BUYER'    => ['type' => "Payment Link (Payer)",                'groups' => [self::GROUP_USER], 'keys' => ['request_amount', 'trx', 'time']],
        'PAYMENT_LINK_SENDER'   => ['type' => "Payment Link (Wallet Payer)",         'groups' => [self::GROUP_USER], 'keys' => ['request_amount', 'total_charge', 'will_pay', 'trx', 'time']],
        'PAYMENT_LINK_USER'     => ['type' => "Payment Link (Receiver)",             'groups' => self::USER_MERCHANT, 'keys' => ['request_amount', 'total_charge', 'will_pay', 'trx', 'time']],

        // account
        'KYC_APPROVED'          => ['type' => "KYC Approved",                        'groups' => self::ALL_ACCOUNTS, 'keys' => ['time']],
        'KYC_REJECTED'          => ['type' => "KYC Rejected",                        'groups' => self::ALL_ACCOUNTS, 'keys' => ['reason', 'time']],
        'REGISTRATION_BONUS'    => ['type' => "Registration Bonus",                  'groups' => [self::GROUP_USER], 'keys' => ['user_name', 'amount', 'time']],
    ];

    /**
     * Sample values, only used for template preview
     */
    const PREVIEW_VALUES = [
        'amount' => "100.00 USD", 'agent' => "@agentdoe", 'asking_amount' => "11,000.00 BDT", 'balance' => "500.00 USD",
        'bill_number' => "BL-778899", 'bill_type' => "Electricity", 'card_amount' => "400.00 USD", 'card_pan' => "4111 **** **** 1111",
        'card_total_price' => "100.00 USD", 'charge' => "2.00 USD", 'code' => "123456", 'currency' => "USD", 'email' => "john@example.com",
        'form_country' => "United States", 'from_amount' => "100.00 USD", 'from_user' => "@janedoe", 'gateway_name' => "Stripe",
        'method' => "Bank Transfer", 'method_name' => "Bank Transfer", 'mobile_number' => "+8801700000000", 'month' => "October 2026",
        'name' => "Grameenphone", 'password' => "Abc12345", 'payable' => "102.00 USD", 'phone' => "+8801700000000", 'qty' => "1",
        'reason' => "Provided information does not match", 'receiver' => "@janedoe", 'receiver_recipient' => "Jane Doe",
        'received_amount' => "11,000.00 BDT", 'recipient' => "Jane Doe", 'recipient_amount' => "11,000.00 BDT",
        'reject_reason' => "Provided information does not match", 'rejection_message' => "Provided information does not match",
        'request_amount' => "100.00 USD", 'requestor' => "@janedoe", 'selling_amount' => "100.00 USD", 'send_amount' => "100.00 USD",
        'sender_recipient' => "John Doe", 'time' => "2026-10-23 05:30:00 PM", 'title' => "Amazon Gift Card", 'to_amount' => "11,000.00 BDT",
        'to_country' => "Bangladesh", 'to_user' => "@janedoe", 'topup_type' => "Automatic", 'total_charge' => "2.00 USD", 'transaction_type' => "Bank Transfer",
        'trx' => "TX12345678", 'type' => "Automatic", 'unit_price' => "100.00 USD", 'user' => "@johndoe", 'user_name' => "John Doe",
        'will_get' => "11,000.00 BDT", 'will_pay' => "98.00 USD",
    ];

    /**
     * All templates: slug "{group}:{act}" => act, group, type, keys, auth, required
     */
    public static function templates(): array
    {
        static $templates = null;
        if($templates !== null) return $templates;

        $templates = [];
        foreach(self::TEMPLATES as $act => $config) {
            foreach($config['groups'] as $group) {
                $templates[$group.':'.$act] = array_merge(['auth' => false, 'required' => null], $config, ['act' => $act, 'group' => $group]);
            }
        }
        return $templates;
    }

    public static function config(string $act): array
    {
        return array_merge(['type' => $act, 'groups' => self::ALL_ACCOUNTS, 'keys' => [], 'auth' => false, 'required' => null], self::TEMPLATES[$act] ?? []);
    }

    /**
     * Verification codes / credentials are always sent
     */
    public static function canDisable(string $act): bool
    {
        return !self::config($act)['auth'];
    }

    /**
     * Placeholders of an SMS: common + what the code sends for this act
     */
    public static function variables(string $act): array
    {
        $variables = self::COMMON_VARIABLES;
        foreach(self::config($act)['keys'] as $key) {
            $variables[$key] = self::VARIABLE_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key));
        }
        return $variables;
    }

    public static function required(string $act): ?string
    {
        return self::config($act)['required'];
    }
}
