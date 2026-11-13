<?php

namespace App\Constants;

class EmailTemplateConst {
    // Admin notifications (sent to admins by permission)
    const ADD_MONEY             = "add-money";
    const WITHDRAW_MONEY        = "withdraw-money";
    const SEND_MONEY            = "send-money";
    const REQUEST_MONEY         = "request-money";
    const MONEY_OUT             = "money-out";
    const MONEY_IN              = "money-in";
    const MAKE_PAYMENT          = "make-payment";
    const REMITTANCE            = "remittance";
    const BILL_PAY              = "bill-pay";
    const MOBILE_TOPUP          = "mobile-topup";
    const GIFT_CARD             = "gift-card";
    const CARD_BUY              = "virtual-card-buy";
    const CARD_FUND             = "virtual-card-fund";
    const CARD_WITHDRAW         = "virtual-card-withdraw";
    const REFUND_BALANCE        = "refund-balance";
    const PAYMENT_LINK          = "payment-link";
    const NEW_USER_REGISTER     = "new-user-register";
    const USER_PASSWORD_RESET   = "user-password-reset";
    const USER_ACCOUNT_DELETE   = "user-account-delete";

    // User notifications (sent to the user/agent/merchant about their own transaction)
    const USER_ADD_MONEY_SUCCESS        = "user-add-money-success";
    const USER_ADD_MONEY_PENDING        = "user-add-money-pending";
    const USER_ADD_MONEY_APPROVED       = "user-add-money-approved";
    const USER_ADD_MONEY_REJECTED       = "user-add-money-rejected";
    const USER_WITHDRAW_REQUEST         = "user-withdraw-request";
    const USER_WITHDRAW_APPROVED        = "user-withdraw-approved";
    const USER_WITHDRAW_REJECTED        = "user-withdraw-rejected";
    const AGENT_WITHDRAW_REQUEST        = "agent-withdraw-request";
    const USER_SEND_MONEY_SENT          = "user-send-money-sent";
    const USER_SEND_MONEY_RECEIVED      = "user-send-money-received";
    const AGENT_SEND_MONEY_SENT         = "agent-send-money-sent";
    const AGENT_SEND_MONEY_RECEIVED     = "agent-send-money-received";
    const USER_REQUEST_MONEY_SENT       = "user-request-money-sent";
    const USER_REQUEST_MONEY_RECEIVED   = "user-request-money-received";
    const USER_MONEY_OUT_SENT           = "user-money-out-sent";
    const USER_MONEY_OUT_RECEIVED       = "user-money-out-received";
    const AGENT_MONEY_IN_SENT           = "agent-money-in-sent";
    const AGENT_MONEY_IN_RECEIVED       = "agent-money-in-received";
    const USER_MAKE_PAYMENT_SENT        = "user-make-payment-sent";
    const USER_MAKE_PAYMENT_RECEIVED    = "user-make-payment-received";
    const USER_REMITTANCE_REQUEST       = "user-remittance-request";
    const USER_REMITTANCE_APPROVED      = "user-remittance-approved";
    const USER_REMITTANCE_REJECTED      = "user-remittance-rejected";
    const AGENT_REMITTANCE_REQUEST      = "agent-remittance-request";
    const USER_BILL_PAY_REQUEST         = "user-bill-pay-request";
    const USER_BILL_PAY_SUCCESS         = "user-bill-pay-success";
    const USER_BILL_PAY_APPROVED        = "user-bill-pay-approved";
    const USER_BILL_PAY_REJECTED        = "user-bill-pay-rejected";
    const USER_BILL_PAY_COMPLETED       = "user-bill-pay-completed";
    const USER_BILL_PAY_FAILED          = "user-bill-pay-failed";
    const USER_MOBILE_TOPUP_REQUEST     = "user-mobile-topup-request";
    const USER_MOBILE_TOPUP_SUCCESS     = "user-mobile-topup-success";
    const USER_MOBILE_TOPUP_APPROVED    = "user-mobile-topup-approved";
    const USER_MOBILE_TOPUP_REJECTED    = "user-mobile-topup-rejected";
    const USER_GIFT_CARD_ORDER          = "user-gift-card-order";
    const USER_CARD_CREATE              = "user-virtual-card-create";
    const USER_CARD_FUND                = "user-virtual-card-fund";
    const USER_CARD_WITHDRAW            = "user-virtual-card-withdraw";
    const USER_REFUND_BALANCE           = "user-refund-balance";
    const MERCHANT_REFUND_BALANCE       = "merchant-refund-balance";
    const USER_PAYMENT_LINK_BUYER       = "user-payment-link-buyer";
    const USER_PAYMENT_LINK_RECEIVED    = "user-payment-link-received";
    const USER_KYC_APPROVED             = "user-kyc-approved";
    const USER_KYC_REJECTED             = "user-kyc-rejected";
    const USER_REGISTRATION_BONUS       = "user-registration-bonus";

    // Auth emails (sent to the user/agent/merchant/admin themselves, always sent)
    const REGISTER_VERIFICATION_CODE    = "user-register-verification-code";
    const ACCOUNT_AUTHORIZATION_CODE    = "user-account-authorization-code";
    const PASSWORD_RESET_CODE           = "user-password-reset-code";
    const AGENT_PASSWORD_RESET_CODE     = "agent-password-reset-code";
    const PAYMENT_VERIFICATION_CODE     = "user-payment-verification-code";
    const ADMIN_PASSWORD_RESET          = "admin-password-reset";

    // Template owners: admin page + one page per account type
    const GROUP_ADMIN       = "admin";
    const GROUP_USER        = "user";
    const GROUP_AGENT       = "agent";
    const GROUP_MERCHANT    = "merchant";

    const ACCOUNT_GROUPS = [
        self::GROUP_USER        => "User Email Templates",
        self::GROUP_AGENT       => "Agent Email Templates",
        self::GROUP_MERCHANT    => "Merchant Email Templates",
    ];

    /**
     * Placeholders available in every template
     */
    const COMMON_VARIABLES = [
        'logo'          => "Site Logo",
        'site_name'     => "Site Name",
        'site_url'      => "Site URL",
        'current_year'  => "Current Year",
    ];

    /**
     * Admin notification placeholders.
     * {{details}} holds the fixed fields, admin can't change those fields.
     */
    const ADMIN_NOTIFICATION_VARIABLES = [
        'admin_name'    => "Receiver Admin Name",
        'subject'       => "Default Subject",
        'title'         => "Transaction Title",
        'details'       => "Transaction Details (Fixed Fields)",
        'date_time'     => "Date And Time",
    ];

    /**
     * User notification placeholders
     */
    const USER_NOTIFICATION_VARIABLES = [
        'user_name'     => "Full Name",
        'subject'       => "Default Subject",
        'title'         => "Transaction Title",
        'details'       => "Transaction Details (Fixed Fields)",
        'date_time'     => "Date And Time",
    ];

    const CODE_VARIABLES = [
        'user_name'     => "Full Name",
        'code'          => "Verification Code",
    ];

    /**
     * Admin templates: slug => type (+ variables/required/auth for auth emails)
     */
    const ADMIN_TEMPLATES = [
        self::ADD_MONEY             => ['type' => "Add Money"],
        self::WITHDRAW_MONEY        => ['type' => "Withdraw Money"],
        self::SEND_MONEY            => ['type' => "Send Money"],
        self::REQUEST_MONEY         => ['type' => "request Money"],
        self::MONEY_OUT             => ['type' => "Money Out"],
        self::MONEY_IN              => ['type' => "Money In"],
        self::MAKE_PAYMENT          => ['type' => "Make Payment"],
        self::REMITTANCE            => ['type' => "Remittance"],
        self::BILL_PAY              => ['type' => "Bill Pay"],
        self::MOBILE_TOPUP          => ['type' => "Mobile Topup"],
        self::GIFT_CARD             => ['type' => "Gift Card"],
        self::CARD_BUY              => ['type' => "Virtual Card Buy"],
        self::CARD_FUND             => ['type' => "Virtual Card Fund"],
        self::CARD_WITHDRAW         => ['type' => "Virtual Card Withdraw"],
        self::REFUND_BALANCE        => ['type' => "Refund Balance"],
        self::PAYMENT_LINK          => ['type' => "Payment Link"],
        self::NEW_USER_REGISTER     => ['type' => "New User Register"],
        self::USER_PASSWORD_RESET   => ['type' => "User Password Reset"],
        self::USER_ACCOUNT_DELETE   => ['type' => "User Account Delete"],
        self::ADMIN_PASSWORD_RESET  => [
            'type'      => "Admin Password Reset",
            'auth'      => true,
            'variables' => ['admin_name' => "Full Name", 'reset_url' => "Password Reset Link"],
            'required'  => 'reset_url',
        ],
    ];

    /**
     * Account notification types: base slug => type.
     * Saved slug is "{user|agent|merchant}-{base}", the receiver decides which one is used.
     */
    const ACCOUNT_NOTIFICATIONS = [
        'add-money-success'         => "Add Money Success",
        'add-money-pending'         => "Add Money Pending (Manual)",
        'add-money-approved'        => "Add Money Approved By Admin",
        'add-money-rejected'        => "Add Money Rejected By Admin",
        'withdraw-request'          => "Withdraw Money Request",
        'withdraw-approved'         => "Withdraw Approved By Admin",
        'withdraw-rejected'         => "Withdraw Rejected By Admin",
        'send-money-sent'           => "Send Money (Sender)",
        'send-money-received'       => "Send Money (Receiver)",
        'request-money-sent'        => "Request Money (Requester)",
        'request-money-received'    => "Request Money (Receiver)",
        'money-out-sent'            => "Money Out (Sender)",
        'money-out-received'        => "Money Out (Receiver)",
        'money-in-sent'             => "Money In (Sender)",
        'money-in-received'         => "Money In (Receiver)",
        'make-payment-sent'         => "Make Payment (Sender)",
        'make-payment-received'     => "Make Payment (Receiver)",
        'remittance-request'        => "Remittance Request",
        'remittance-approved'       => "Remittance Approved By Admin",
        'remittance-rejected'       => "Remittance Rejected By Admin",
        'bill-pay-request'          => "Bill Pay Request (Manual)",
        'bill-pay-success'          => "Bill Pay Request (Automatic)",
        'bill-pay-approved'         => "Bill Pay Approved By Admin",
        'bill-pay-rejected'         => "Bill Pay Rejected By Admin",
        'bill-pay-completed'        => "Bill Pay Completed (Automatic)",
        'bill-pay-failed'           => "Bill Pay Failed (Automatic)",
        'mobile-topup-request'      => "Mobile Topup Request (Manual)",
        'mobile-topup-success'      => "Mobile Topup (Automatic)",
        'mobile-topup-approved'     => "Mobile Topup Approved By Admin",
        'mobile-topup-rejected'     => "Mobile Topup Rejected By Admin",
        'gift-card-order'           => "Gift Card Order",
        'virtual-card-create'       => "Virtual Card Create",
        'virtual-card-fund'         => "Virtual Card Fund",
        'virtual-card-withdraw'     => "Virtual Card Withdraw",
        'refund-balance'            => "Refund Balance",
        'payment-link-buyer'        => "Payment Link (Payer)",
        'payment-link-received'     => "Payment Link (Receiver)",
        'kyc-approved'              => "KYC Approved",
        'kyc-rejected'              => "KYC Rejected",
        'registration-bonus'        => "Registration Bonus",
    ];

    /**
     * Account auth emails: base slug => type, variables, required placeholder
     */
    const ACCOUNT_AUTH = [
        'register-verification-code'    => [
            'type'      => "Registration Verification Code",
            'variables' => self::CODE_VARIABLES,
            'required'  => 'code',
        ],
        'account-authorization-code'    => [
            'type'      => "Account Authorization",
            'variables' => self::CODE_VARIABLES,
            'required'  => 'code',
        ],
        'password-reset-code'           => [
            'type'      => "Password Reset Code",
            'variables' => ['user_name' => "Full Name", 'code' => "Verification Code", 'verify_url' => "Verify Link"],
            'required'  => 'code',
        ],
        'payment-verification-code'     => [
            'type'      => "Payment Verification Code",
            'variables' => ['user_name' => "Full Name", 'code' => "Verification Code", 'amount' => "Payment Amount"],
            'required'  => 'code',
        ],
    ];

    /**
     * Which emails each account type receives (base slugs)
     */
    const ACCOUNT_TEMPLATES = [
        self::GROUP_USER        => [
            'add-money-success', 'add-money-pending', 'add-money-approved', 'add-money-rejected',
            'withdraw-request', 'withdraw-approved', 'withdraw-rejected',
            'send-money-sent', 'send-money-received', 'request-money-sent', 'request-money-received',
            'money-out-sent', 'money-in-received', 'make-payment-sent',
            'remittance-request', 'remittance-approved', 'remittance-rejected',
            'bill-pay-request', 'bill-pay-success', 'bill-pay-approved', 'bill-pay-rejected', 'bill-pay-completed', 'bill-pay-failed',
            'mobile-topup-request', 'mobile-topup-success', 'mobile-topup-approved', 'mobile-topup-rejected',
            'gift-card-order', 'virtual-card-create', 'virtual-card-fund', 'virtual-card-withdraw',
            'refund-balance', 'payment-link-buyer', 'payment-link-received',
            'kyc-approved', 'kyc-rejected', 'registration-bonus',
            'register-verification-code', 'account-authorization-code', 'password-reset-code', 'payment-verification-code',
        ],
        self::GROUP_AGENT       => [
            'add-money-success', 'add-money-pending', 'add-money-approved', 'add-money-rejected',
            'withdraw-request', 'withdraw-approved', 'withdraw-rejected',
            'send-money-sent', 'send-money-received', 'money-out-received', 'money-in-sent',
            'remittance-request', 'remittance-approved', 'remittance-rejected',
            'bill-pay-request', 'bill-pay-success', 'bill-pay-approved', 'bill-pay-rejected', 'bill-pay-completed', 'bill-pay-failed',
            'mobile-topup-request', 'mobile-topup-success', 'mobile-topup-approved', 'mobile-topup-rejected',
            'kyc-approved', 'kyc-rejected',
            'register-verification-code', 'account-authorization-code', 'password-reset-code',
        ],
        self::GROUP_MERCHANT    => [
            'withdraw-request', 'withdraw-approved', 'withdraw-rejected',
            'make-payment-received', 'refund-balance', 'payment-link-received',
            'kyc-approved', 'kyc-rejected',
            'register-verification-code', 'account-authorization-code', 'password-reset-code',
        ],
    ];

    /**
     * Sample transaction rows, only used for template preview
     */
    const PREVIEW_DETAILS = [
        'TRX ID'                => "SM12345678",
        'Request Amount'        => "100.00 USD",
        'Fees & Charges'        => "2.00 USD",
        'Total Payable Amount'  => "102.00 USD",
        'Status'                => "Success",
    ];

    const PREVIEW_VALUES = [
        'user_name'     => "John Doe",
        'code'          => "123456",
        'amount'        => "100.00 USD",
        'verify_url'    => "#",
        'reset_url'     => "#",
    ];

    /**
     * All templates: slug => type, group, auth, variables, required
     */
    public static function templates(): array
    {
        static $templates = null;
        if($templates !== null) return $templates;

        $templates = [];
        foreach(self::ADMIN_TEMPLATES as $slug => $config) {
            $templates[$slug] = array_merge(['group' => self::GROUP_ADMIN, 'auth' => false], $config);
        }
        foreach(self::ACCOUNT_TEMPLATES as $group => $bases) {
            foreach($bases as $base) {
                $config = isset(self::ACCOUNT_AUTH[$base])
                            ? array_merge(self::ACCOUNT_AUTH[$base], ['auth' => true])
                            : ['type' => self::ACCOUNT_NOTIFICATIONS[$base], 'auth' => false];
                $templates[$group.'-'.$base] = array_merge($config, ['group' => $group, 'base' => $base]);
            }
        }
        return $templates;
    }

    /**
     * Preview rows that look like the real email of this template (same fields it sends)
     * @param string $slug
     * @return array label => sample value, empty for auth emails
     */
    public static function previewDetails(string $slug): array
    {
        $config = self::config($slug);
        if($config['auth'] ?? false) return [];

        $trx        = ['TRX ID' => "TX12345678"];
        $user       = ['User' => "John Doe (@johndoe)", 'Email' => "john@example.com"];
        $amount     = ['Request Amount' => "100.00 USD", 'Fees & Charges' => "2.00 USD", 'Total Payable Amount' => "102.00 USD"];
        $exchange   = ['Exchange Rate' => "1 USD = 110.00 BDT", 'Will Get' => "11,000.00 BDT"];
        $transfer   = ['Sender' => "@johndoe", 'Receiver' => "@janedoe"] + $amount + ['Recipient Received' => "100.00 USD"];
        $received   = ['Received Amount' => "100.00 USD"];
        $card       = $amount + ['Card Amount' => "100.00 USD", 'Card Pan' => "4111 **** **** 1111"];
        $bill       = ['Bill Type' => "Electricity", 'Bill Number' => "BL-778899", 'Bill Month' => "October 2026"] + $amount;
        $topup      = ['Mobile Number' => "+8801700000000", 'Operator' => "Grameenphone"] + $amount;
        $remittance = ['Transaction Type' => "Bank Transfer", 'Receiver Name' => "Jane Doe", 'Receiving Country' => "Bangladesh", 'Bank Name' => "City Bank"] + $amount + ['Receiver Will Get' => "11,000.00 BDT"];
        $pending    = ['Status' => "Pending"];
        $success    = ['Status' => "Success"];
        $rejected   = ['Status' => "Rejected", 'Rejection Reason' => "Provided information does not match"];

        $samples = [
            // admin notifications
            self::ADD_MONEY             => $user + $trx + $amount + ['Exchange Rate' => "1 USD = 1.00 USD"] + $pending,
            self::WITHDRAW_MONEY        => $user + $trx + $amount + $exchange + $pending,
            self::SEND_MONEY            => $trx + $transfer + $success,
            self::REQUEST_MONEY         => $trx + ['Sender' => "@johndoe", 'Receiver' => "@janedoe"] + $amount + $pending,
            self::MONEY_OUT             => $trx + ['Sender' => "@johndoe", 'Receiver' => "@agentdoe"] + $amount + $success,
            self::MONEY_IN              => $trx + ['Sender' => "@agentdoe", 'Receiver' => "@johndoe"] + $amount + $success,
            self::MAKE_PAYMENT          => $trx + ['Sender' => "@johndoe", 'Receiver' => "@merchantdoe"] + $amount + $success,
            self::REMITTANCE            => $user + $trx + $remittance + $pending,
            self::BILL_PAY              => $user + $trx + $bill + $pending,
            self::MOBILE_TOPUP          => $user + $trx + $topup + $pending,
            self::GIFT_CARD             => $trx + ['Card Name' => "Amazon Gift Card", 'Card Quantity' => "1"] + $amount + $success,
            self::CARD_BUY              => $user + $trx + $card + $success,
            self::CARD_FUND             => $user + $trx + $card + $success,
            self::CARD_WITHDRAW         => $user + $trx + $card + $success,
            self::REFUND_BALANCE        => $trx + ['Sender' => "@merchantdoe", 'Receiver' => "@johndoe", 'Refund Balance' => "100.00 USD"],
            self::PAYMENT_LINK          => $user + $trx + $amount + ['Will Get' => "98.00 USD"] + $success,
            self::NEW_USER_REGISTER     => ['Name' => "John Doe", 'Username' => "@johndoe", 'Email' => "john@example.com"],
            self::USER_PASSWORD_RESET   => ['Name' => "John Doe", 'Username' => "@johndoe", 'Email' => "john@example.com"],
            self::USER_ACCOUNT_DELETE   => ['Name' => "John Doe", 'Username' => "@johndoe", 'Email' => "john@example.com"],

            // user/agent/merchant notifications (base slug)
            'add-money-success'         => $amount + $exchange + $trx + $success,
            'add-money-pending'         => $amount + $exchange + $trx + $pending,
            'add-money-approved'        => $amount + $exchange + $trx + $success,
            'add-money-rejected'        => $amount + $exchange + $trx + $rejected,
            'withdraw-request'          => $trx + $amount + $exchange + $pending,
            'withdraw-approved'         => $trx + $amount + $exchange + $success,
            'withdraw-rejected'         => $trx + $amount + $exchange + $rejected,
            'send-money-sent'           => $trx + $amount + ['Recipient Received' => "100.00 USD"] + $success,
            'send-money-received'       => $trx + $received + $success,
            'request-money-sent'        => $trx + $amount + ['Will Get' => "100.00 USD"] + $pending,
            'request-money-received'    => $trx + $amount + ['Receiver Will Get' => "100.00 USD"] + $pending,
            'money-out-sent'            => $trx + $amount + ['Recipient Received' => "100.00 USD"] + $success,
            'money-out-received'        => $trx + $received + $success,
            'money-in-sent'             => $trx + $amount + ['Recipient Received' => "100.00 USD"] + $success,
            'money-in-received'         => $trx + $received + $success,
            'make-payment-sent'         => $trx + $amount + ['Recipient Received' => "100.00 USD"] + $success,
            'make-payment-received'     => $trx + $received + $success,
            'remittance-request'        => $trx + $remittance + $pending,
            'remittance-approved'       => $trx + $remittance + $success,
            'remittance-rejected'       => $trx + $remittance + $rejected,
            'bill-pay-request'          => $trx + $bill + $pending,
            'bill-pay-success'          => $trx + $bill + $pending,
            'bill-pay-approved'         => $trx + $bill + $success,
            'bill-pay-rejected'         => $trx + $bill + $rejected,
            'bill-pay-completed'        => $trx + ['Bill Month' => "October 2026", 'Bill Amount' => "100.00 USD"] + $success,
            'bill-pay-failed'           => $trx + ['Bill Month' => "October 2026", 'Bill Amount' => "100.00 USD", 'Status' => "Failed"],
            'mobile-topup-request'      => $trx + $amount + $pending,
            'mobile-topup-success'      => $trx + $amount + ['Will Get' => "100.00 USD", 'Current Balance' => "500.00 USD"] + $success,
            'mobile-topup-approved'     => $trx + $amount + ['Will Get' => "100.00 USD"] + $success,
            'mobile-topup-rejected'     => $trx + $amount + ['Will Get' => "100.00 USD"] + $rejected,
            'gift-card-order'           => $trx + ['Card Name' => "Amazon Gift Card", 'Receiver Email' => "jane@example.com", 'Card Quantity' => "1", 'Card Total Price' => "100.00 USD", 'Total Charge' => "2.00 USD", 'Total Payable Amount' => "102.00 USD"],
            'virtual-card-create'       => $trx + $card + $success,
            'virtual-card-fund'         => $trx + $card + $success,
            'virtual-card-withdraw'     => $trx + ['Request Amount' => "100.00 USD", 'Fees & Charges' => "2.00 USD", "You'll Receive" => "98.00 USD", 'Card Amount' => "400.00 USD", 'Card Pan' => "4111 **** **** 1111"] + $success,
            'refund-balance'            => $trx + ['Refund Balance' => "100.00 USD"],
            'payment-link-buyer'        => ['Request Amount' => "100.00 USD"] + $trx + $success,
            'payment-link-received'     => ['Request Amount' => "100.00 USD", 'Exchange Rate' => "1 USD = 1.00 USD", 'Fees & Charges' => "2.00 USD", 'Will Get' => "98.00 USD"] + $trx + $success,
            'kyc-approved'              => ['Status' => "Approved"],
            'kyc-rejected'              => $rejected,
            'registration-bonus'        => ['Registration Bonus' => "10.00 USD"],
        ];

        return $samples[$config['base'] ?? $slug] ?? self::PREVIEW_DETAILS;
    }

    public static function config(string $slug): array
    {
        return self::templates()[$slug] ?? ['type' => $slug, 'group' => self::GROUP_ADMIN, 'auth' => false];
    }

    /**
     * Template slug for the receiver: user/agent/merchant version of the same email.
     * Admin templates (no account prefix) are returned as it is.
     * @param string $slug
     * @param string $group user|agent|merchant
     */
    public static function slugFor(string $slug, string $group): string
    {
        if(!preg_match('/^(user|agent|merchant)-(.+)$/', $slug, $match)) return $slug;
        $target = $group.'-'.$match[2];
        return isset(self::templates()[$target]) ? $target : $slug;
    }

    /**
     * Notification templates can be disabled, auth templates (OTP/link) are always sent
     */
    public static function canDisable(string $slug): bool
    {
        return !(self::config($slug)['auth'] ?? false);
    }

    /**
     * Placeholders for a template: common + group/template specific
     */
    public static function variables(string $slug): array
    {
        $config = self::config($slug);
        if($config['auth'] ?? false) {
            $extra = $config['variables'] ?? [];
        }else {
            $extra = $config['group'] == self::GROUP_ADMIN ? self::ADMIN_NOTIFICATION_VARIABLES : self::USER_NOTIFICATION_VARIABLES;
        }
        return array_merge(self::COMMON_VARIABLES, $extra);
    }

    /**
     * Placeholder that must stay in the body
     */
    public static function required(string $slug): string
    {
        return self::config($slug)['required'] ?? 'details';
    }
}
