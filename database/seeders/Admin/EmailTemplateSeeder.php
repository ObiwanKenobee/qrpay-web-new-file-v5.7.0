<?php

namespace Database\Seeders\Admin;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Owner (admin/user/agent/merchant) of the template being built, decides the logo
     */
    public string $group = EmailTemplateConst::GROUP_USER;

    /**
     * Run the database seeds.
     * Creates missing templates and refreshes templates no admin has edited yet (last_edit_by null).
     * Admin edited templates are never overwritten.
     *
     * @return void
     */
    public function run()
    {
        $templates = EmailTemplateConst::templates();
        // templates that no longer exist in the system (renamed/split)
        EmailTemplate::whereNotIn('slug', array_keys($templates))->delete();

        foreach($templates as $slug => $config) {
            $this->group = $config['group'];
            [$subject, $body] = $this->defaultContent($slug, $config);
            $email_template = EmailTemplate::firstOrNew(['slug' => $slug]);
            if($email_template->exists && $email_template->last_edit_by) {
                // type is a system label (translation key), keep it in sync even for edited templates
                $email_template->update(['type' => $config['type']]);
                continue;
            }

            $email_template->fill([
                'type'      => $config['type'],
                'subject'   => $subject,
                'body'      => $body,
                'status'    => $email_template->exists ? $email_template->status : true,
            ])->save();
        }
    }

    // Color tones: [text, soft background]
    const TONES = [
        'success'   => ['#15803d', '#dcfce7'],
        'warning'   => ['#b45309', '#fef3c7'],
        'danger'    => ['#b91c1c', '#fee2e2'],
        'info'      => ['#1d4ed8', '#dbeafe'],
        'brand'     => ['#5a5278', '#efedf5'],
    ];


    const PENDING_STEPS = [
        "Our team will review your request shortly.",
        "You will receive another email as soon as it has been processed.",
        "You can track the status anytime from your transaction history.",
    ];

    const REJECTED_STEPS = [
        "Review the reason mentioned in the details above.",
        "Correct the information and submit a new request if needed.",
        "Contact our support team if you need any help.",
    ];

    const SECURITY_NOTE = "If you did not make this transaction, please contact our support team immediately.";
    const WALLET_NOTE   = "The amount is now available in your wallet.";

    /**
     * Standard subject and body for each template, designed for the type of email
     * @param string $slug
     * @param array $config template config (group, base)
     * @return array [subject, body]
     */
    public function defaultContent(string $slug, array $config): array
    {
        // user/agent/merchant templates share content by base slug, account specific wording is keyed by full slug
        $key = $config['base'] ?? $slug;

        if($config['auth'] ?? false) {
            return $this->authContent($slug, $key);
        }
        if($config['group'] == EmailTemplateConst::GROUP_ADMIN) {
            return $this->adminContent($slug);
        }

        $notifications = $this->accountNotifications();
        $spec = $notifications[$slug] ?? $notifications[$key];
        return [$spec['subject'], $this->notification($spec, 'user_name')];
    }

    /**
     * User/agent/merchant notifications
     * subject, heading, tone, badge, title (show {{title}}), intro, section (details heading), steps|note
     */
    public function accountNotifications(): array
    {
        $success = fn($subject, $heading, $intro, $note = self::SECURITY_NOTE, $section = "Transaction Details", $badge = "Successful") => compact('subject', 'heading', 'intro', 'note', 'section', 'badge') + ['tone' => 'success', 'title' => true];
        $pending = fn($subject, $heading, $intro, $section = "Request Details") => compact('subject', 'heading', 'intro', 'section') + ['tone' => 'warning', 'badge' => "Pending", 'title' => true, 'steps' => self::PENDING_STEPS, 'steps_title' => "What Happens Next"];
        $rejected = fn($subject, $heading, $intro, $section = "Request Details", $badge = "Rejected") => compact('subject', 'heading', 'intro', 'section', 'badge') + ['tone' => 'danger', 'title' => true, 'steps' => self::REJECTED_STEPS, 'steps_title' => "What You Can Do"];
        $received = fn($subject, $heading, $intro, $note = self::WALLET_NOTE) => compact('subject', 'heading', 'intro', 'note') + ['tone' => 'success', 'badge' => "Received", 'title' => true, 'section' => "Transaction Details"];

        return [
            'add-money-success'         => $success("Add Money Successful - {{site_name}}", "Money Added Successfully", "Your add money request has been completed successfully and the amount has been added to your wallet.", self::WALLET_NOTE),
            'add-money-pending'         => $pending("Add Money Request Received - {{site_name}}", "Add Money Request Received", "We have received your add money request. It is now under review."),
            'add-money-approved'        => $success("Add Money Approved - {{site_name}}", "Add Money Approved", "Good news! Your add money request has been approved and the amount has been added to your wallet.", self::WALLET_NOTE, "Transaction Details", "Approved"),
            'add-money-rejected'        => $rejected("Add Money Rejected - {{site_name}}", "Add Money Rejected", "We regret to inform you that your add money request has been rejected."),
            'withdraw-request'          => $pending("Withdraw Request Received - {{site_name}}", "Withdraw Request Received", "We have received your withdraw request. It is now being processed."),
            'withdraw-approved'         => $success("Withdraw Approved - {{site_name}}", "Withdraw Completed", "Your withdraw request has been approved and processed successfully.", self::SECURITY_NOTE, "Transaction Details", "Approved"),
            'withdraw-rejected'         => $rejected("Withdraw Rejected - {{site_name}}", "Withdraw Rejected", "We regret to inform you that your withdraw request has been rejected."),
            'send-money-sent'           => $success("Money Sent Successfully - {{site_name}}", "Money Sent Successfully", "You have successfully sent money. Here is a summary of your transaction."),
            'send-money-received'       => $received("You Have Received Money - {{site_name}}", "You Have Received Money", "Great news! Money has been sent to your wallet."),
            'request-money-sent'        => ['subject' => "Money Request Sent - {{site_name}}", 'heading' => "Money Request Sent", 'tone' => 'info', 'badge' => "Request Sent", 'title' => true, 'intro' => "Your money request has been sent successfully. We will notify you once the receiver responds.", 'section' => "Request Details", 'note' => "You can cancel this request from your account while it is still pending."],
            'request-money-received'    => ['subject' => "New Money Request - {{site_name}}", 'heading' => "You Have a New Money Request", 'tone' => 'warning', 'badge' => "Action Required", 'title' => true, 'intro' => "Someone has requested money from you. Please review the request below.", 'section' => "Request Details", 'steps' => ["Log in to your account and open your money requests.", "Accept the request to send the money, or reject it if you don't recognize it."], 'steps_title' => "How To Respond"],
            'money-out-sent'            => $success("Money Out Successful - {{site_name}}", "Money Out Successful", "Your money out transaction has been completed successfully. Here is a summary."),
            'money-out-received'        => $received("You Have Received Money - {{site_name}}", "Money Received", "A customer has completed a money out transaction to your account."),
            'agent-money-out-received'  => $received("You Have Received Money - {{site_name}}", "Money Received", "A customer has completed a money out transaction to your agent account.", "The amount is now available in your agent wallet."),
            'money-in-sent'             => $success("Money In Successful - {{site_name}}", "Money In Successful", "Your money in transaction has been completed successfully. Here is a summary."),
            'money-in-received'         => $received("You Have Received Money - {{site_name}}", "Money Added To Your Wallet", "An agent has added money to your wallet."),
            'make-payment-sent'         => $success("Payment Successful - {{site_name}}", "Payment Successful", "Your payment has been completed successfully. Here is a summary of your payment."),
            'make-payment-received'     => $received("You Have Received a Payment - {{site_name}}", "Payment Received", "You have received a new payment."),
            'merchant-make-payment-received' => $received("You Have Received a Payment - {{site_name}}", "Payment Received", "A customer has paid you. The payment has been added to your merchant wallet.", "The amount is now available in your merchant wallet."),
            'remittance-request'        => $pending("Remittance Request Received - {{site_name}}", "Remittance Request Received", "We have received your remittance request. It is now being processed.", "Remittance Details"),
            'remittance-approved'       => $success("Remittance Completed - {{site_name}}", "Remittance Completed", "Your remittance request has been approved and the money is on its way to the receiver.", self::SECURITY_NOTE, "Remittance Details", "Approved"),
            'remittance-rejected'       => $rejected("Remittance Rejected - {{site_name}}", "Remittance Rejected", "We regret to inform you that your remittance request has been rejected.", "Remittance Details"),
            'bill-pay-request'          => $pending("Bill Payment Request Received - {{site_name}}", "Bill Payment Request Received", "We have received your bill payment request. It is now being processed.", "Bill Details"),
            'bill-pay-success'          => $pending("Bill Payment Submitted - {{site_name}}", "Bill Payment Submitted", "Your bill payment has been submitted to the biller and is being processed.", "Bill Details"),
            'bill-pay-approved'         => $success("Bill Payment Successful - {{site_name}}", "Bill Paid Successfully", "Your bill payment has been approved and completed successfully.", self::SECURITY_NOTE, "Bill Details", "Paid"),
            'bill-pay-rejected'         => $rejected("Bill Payment Rejected - {{site_name}}", "Bill Payment Rejected", "We regret to inform you that your bill payment request has been rejected.", "Bill Details"),
            'bill-pay-completed'        => $success("Bill Payment Successful - {{site_name}}", "Bill Paid Successfully", "Your bill has been paid successfully.", self::SECURITY_NOTE, "Bill Details", "Paid"),
            'bill-pay-failed'           => $rejected("Bill Payment Failed - {{site_name}}", "Bill Payment Failed", "We regret to inform you that your bill payment could not be completed.", "Bill Details", "Failed"),
            'mobile-topup-request'      => $pending("Mobile Top Up Request Received - {{site_name}}", "Mobile Top Up Request Received", "We have received your mobile top up request. It is now being processed.", "Top Up Details"),
            'mobile-topup-success'      => $success("Mobile Top Up Successful - {{site_name}}", "Mobile Top Up Successful", "Your mobile has been topped up successfully.", self::SECURITY_NOTE, "Top Up Details"),
            'mobile-topup-approved'     => $success("Mobile Top Up Successful - {{site_name}}", "Mobile Top Up Successful", "Your mobile top up request has been approved and completed successfully.", self::SECURITY_NOTE, "Top Up Details", "Approved"),
            'mobile-topup-rejected'     => $rejected("Mobile Top Up Rejected - {{site_name}}", "Mobile Top Up Rejected", "We regret to inform you that your mobile top up request has been rejected.", "Top Up Details"),
            'gift-card-order'           => ['subject' => "Your Gift Card Order Is Confirmed - {{site_name}}", 'heading' => "Gift Card Order Confirmed", 'tone' => 'success', 'badge' => "Order Placed", 'title' => false, 'intro' => "Thank you for your order! Your gift card order has been placed successfully.", 'section' => "Order Details", 'note' => "The gift card will be delivered to the receiver email shown above."],
            'virtual-card-create'       => ['subject' => "Your Virtual Card Is Ready - {{site_name}}", 'heading' => "Your Virtual Card Is Ready", 'tone' => 'success', 'badge' => "Card Created", 'title' => false, 'intro' => "Your new virtual card has been created successfully and is ready to use for online payments.", 'section' => "Card Details", 'note' => "Never share your card details or security code with anyone."],
            'virtual-card-fund'         => $success("Virtual Card Funded - {{site_name}}", "Virtual Card Funded", "Your virtual card has been funded successfully.", "The new balance is now available on your card.", "Card Details", "Funded"),
            'virtual-card-withdraw'     => $success("Virtual Card Withdrawal Successful - {{site_name}}", "Card Withdrawal Successful", "Funds have been withdrawn from your virtual card to your wallet successfully.", self::WALLET_NOTE, "Card Details"),
            'refund-balance'            => $received("Refund Received - {{site_name}}", "Refund Received", "A refund has been issued to your wallet."),
            'merchant-refund-balance'   => $success("Refund Completed - {{site_name}}", "Refund Completed", "Your refund has been processed successfully and the amount has been returned to the customer.", "The refunded amount has been deducted from your merchant wallet.", "Refund Details", "Refunded"),
            'payment-link-buyer'        => $success("Payment Successful - {{site_name}}", "Payment Successful", "Thank you! Your payment through the payment link has been completed successfully.", "Please keep this email as the receipt of your payment.", "Payment Details", "Paid"),
            'payment-link-received'     => $received("You Have Received a Payment - {{site_name}}", "Payment Received", "You have received a new payment through your payment link."),
            'merchant-payment-link-received' => $received("You Have Received a Payment - {{site_name}}", "Payment Received", "A customer has paid you through your payment link.", "The amount is now available in your merchant wallet."),
            'kyc-approved'              => [
                'subject' => "Your Account Is Verified - {{site_name}}", 'heading' => "Your Account Is Verified", 'tone' => 'success', 'badge' => "KYC Approved", 'title' => false,
                'intro' => "Congratulations! Your identity verification (KYC) has been approved. Your account is now fully verified.",
                'section' => "Verification Status",
                'steps' => ["Access all services available on your account.", "Make transactions without verification interruptions.", "Enjoy a more secure and trusted account."], 'steps_title' => "What You Can Do Now",
            ],
            'kyc-rejected'              => [
                'subject' => "KYC Verification Unsuccessful - {{site_name}}", 'heading' => "KYC Verification Unsuccessful", 'tone' => 'danger', 'badge' => "KYC Rejected", 'title' => false,
                'intro' => "We were unable to verify your identity with the information you submitted.",
                'section' => "Verification Status",
                'steps' => ["Check the rejection reason above.", "Make sure your documents are valid, clear and fully visible.", "Make sure your details match your documents exactly.", "Submit your KYC information again from your account."], 'steps_title' => "How To Get Verified",
            ],
            'registration-bonus'        => [
                'subject' => "Welcome Bonus Added To Your Wallet - {{site_name}}", 'heading' => "Welcome To {{site_name}}!", 'tone' => 'brand', 'badge' => "Bonus Added", 'title' => false,
                'intro' => "Thank you for joining us! As a welcome gift, a registration bonus has been added to your wallet.",
                'section' => "Bonus Details",
                'note' => "Start exploring {{site_name}} and make your first transaction today.",
            ],
        ];
    }

    /**
     * Admin notifications: review requests (warning) and activity info (info)
     */
    public function adminContent(string $slug): array
    {
        $review = fn($subject, $heading, $intro, $section = "Transaction Details") => compact('subject', 'heading', 'intro', 'section') + ['tone' => 'warning', 'badge' => "Action Required", 'title' => true, 'note' => "Please log in to the admin panel to review this request."];
        $activity = fn($subject, $heading, $intro, $section = "Transaction Details") => compact('subject', 'heading', 'intro', 'section') + ['tone' => 'info', 'badge' => "New Activity", 'title' => false];

        $notifications = [
            EmailTemplateConst::ADD_MONEY             => $review("New Add Money Request - {{site_name}}", "New Add Money Request", "A new add money request has been submitted."),
            EmailTemplateConst::WITHDRAW_MONEY        => $review("New Withdraw Request - {{site_name}}", "New Withdraw Request", "A new withdraw request has been submitted and is waiting for review."),
            EmailTemplateConst::SEND_MONEY            => $activity("Send Money Completed - {{site_name}}", "Send Money Completed", "A send money transaction has been completed."),
            EmailTemplateConst::REQUEST_MONEY         => $activity("New Money Request - {{site_name}}", "New Money Request", "A new money request has been created.", "Request Details"),
            EmailTemplateConst::MONEY_OUT             => $activity("Money Out Completed - {{site_name}}", "Money Out Completed", "A user has completed a money out transaction through an agent."),
            EmailTemplateConst::MONEY_IN              => $activity("Money In Completed - {{site_name}}", "Money In Completed", "An agent has completed a money in transaction."),
            EmailTemplateConst::MAKE_PAYMENT          => $activity("Merchant Payment Completed - {{site_name}}", "Merchant Payment Completed", "A user has made a payment to a merchant."),
            EmailTemplateConst::REMITTANCE            => $review("New Remittance Request - {{site_name}}", "New Remittance Request", "A new remittance request has been submitted and is waiting for review.", "Remittance Details"),
            EmailTemplateConst::BILL_PAY              => $review("New Bill Payment Request - {{site_name}}", "New Bill Payment Request", "A new bill payment request has been submitted.", "Bill Details"),
            EmailTemplateConst::MOBILE_TOPUP          => $review("New Mobile Top Up Request - {{site_name}}", "New Mobile Top Up Request", "A new mobile top up request has been submitted.", "Top Up Details"),
            EmailTemplateConst::GIFT_CARD             => $activity("New Gift Card Order - {{site_name}}", "New Gift Card Order", "A new gift card order has been placed.", "Order Details"),
            EmailTemplateConst::CARD_BUY              => $activity("New Virtual Card Purchased - {{site_name}}", "Virtual Card Purchased", "A new virtual card has been purchased.", "Card Details"),
            EmailTemplateConst::CARD_FUND             => $activity("Virtual Card Funded - {{site_name}}", "Virtual Card Funded", "Funds have been added to a virtual card.", "Card Details"),
            EmailTemplateConst::CARD_WITHDRAW         => $activity("Virtual Card Withdrawal - {{site_name}}", "Virtual Card Withdrawal", "Funds have been withdrawn from a virtual card.", "Card Details"),
            EmailTemplateConst::REFUND_BALANCE        => $activity("Merchant Refund Completed - {{site_name}}", "Merchant Refund Completed", "A merchant has refunded a payment to a customer.", "Refund Details"),
            EmailTemplateConst::PAYMENT_LINK          => $activity("New Payment Link Transaction - {{site_name}}", "Payment Link Transaction", "A payment has been received through a payment link.", "Payment Details") + ['title' => true],
            EmailTemplateConst::NEW_USER_REGISTER     => $activity("New Account Registered - {{site_name}}", "New Account Registered", "A new account has been registered.", "Account Details"),
            EmailTemplateConst::USER_PASSWORD_RESET   => $activity("Account Password Reset - {{site_name}}", "Account Password Reset", "An account password has been reset.", "Account Details"),
            EmailTemplateConst::USER_ACCOUNT_DELETE   => ['tone' => 'danger', 'badge' => "Account Deleted"] + $activity("Account Deleted - {{site_name}}", "Account Deleted", "An account has been deleted by its owner.", "Account Details"),
        ];

        $spec = $notifications[$slug];
        return [$spec['subject'], $this->notification($spec, 'admin_name')];
    }

    /**
     * Auth emails: highlighted code / link + security tips, no details table
     */
    public function authContent(string $slug, string $key): array
    {
        $security = ["Never share this code with anyone, including our support team.", "Our team will never ask you for this code."];

        switch($slug == EmailTemplateConst::AGENT_PASSWORD_RESET_CODE || $slug == EmailTemplateConst::ADMIN_PASSWORD_RESET ? $slug : $key) {
            case 'register-verification-code':
                return ["Verify Your Email Address - {{site_name}}", $this->layout("Verify Your Email Address",
                    '<p>Hello {{user_name}},</p>'
                    .'<p>Thank you for choosing {{site_name}}! To complete your registration, please enter the verification code below.</p>'
                    .$this->code('{{code}}')
                    .$this->steps("For Your Security", array_merge($security, ["If you did not start a registration, you can safely ignore this email."]))
                )];

            case 'account-authorization-code':
                return ["Your Login Verification Code - {{site_name}}", $this->layout("Verify It's You",
                    '<p>Hello {{user_name}},</p>'
                    .'<p>Please verify your account before accessing your dashboard. Enter the verification code below to continue.</p>'
                    .$this->code('{{code}}')
                    .$this->steps("For Your Security", array_merge($security, ["If this wasn't you, please change your password immediately."]))
                )];

            case 'password-reset-code':
                return ["Reset Your Password - {{site_name}}", $this->layout("Reset Your Password",
                    '<p>Hello {{user_name}},</p>'
                    .'<p>We received a request to reset the password for your {{site_name}} account. Use the code below to continue.</p>'
                    .$this->code('{{code}}')
                    .'<p style="text-align:center;"><a href="{{verify_url}}"><strong>Verify Code &amp; Reset Password &rarr;</strong></a></p>'
                    .$this->note("If you did not request a password reset, please ignore this email. Your password will remain unchanged.")
                )];

            case EmailTemplateConst::AGENT_PASSWORD_RESET_CODE:
                return ["Reset Your Password - {{site_name}}", $this->layout("Reset Your Password",
                    '<p>Hello {{user_name}},</p>'
                    .'<p>We received a request to reset the password for your {{site_name}} agent account. Use the code below to continue.</p>'
                    .$this->code('{{code}}')
                    .$this->note("If you did not request a password reset, please ignore this email. Your password will remain unchanged.")
                )];

            case EmailTemplateConst::ADMIN_PASSWORD_RESET:
                return ["Reset Your Admin Password - {{site_name}}", $this->layout("Reset Your Admin Password",
                    '<p>Hello {{admin_name}},</p>'
                    .'<p>We received a request to reset the password for your {{site_name}} admin account. Click the button below to set a new password.</p>'
                    .'<p style="text-align:center;"><a href="{{reset_url}}"><span style="background-color:#5a5278;color:#ffffff;"><strong>Reset Password</strong></span></a></p>'
                    .$this->note("If you did not request this, you can safely ignore this email. For security, this link will expire soon.")
                )];

            case 'payment-verification-code':
                return ["Confirm Your Payment - {{site_name}}", $this->layout("Confirm Your Payment",
                    '<p>Hello {{user_name}},</p>'
                    .'<p>A payment of <strong>{{amount}}</strong> is being made from your {{site_name}} account. Enter the code below to confirm this payment.</p>'
                    .$this->code('{{code}}')
                    .$this->steps("For Your Security", ["Never share this code with anyone.", "If you did not make this payment, do not use this code and contact our support team immediately."])
                )];
        }

        throw new \Exception("Missing auth email content: ".$slug);
    }

    /**
     * Notification body: icon, heading, status badge, greeting, intro, details, date, next steps/note
     */
    public function notification(array $spec, string $name_placeholder): string
    {
        $tone = $spec['tone'];
        return $this->layout($spec['heading'],
            $this->badge($spec['badge'], $tone)
            .($spec['title'] ? '<p style="text-align:center;"><span style="color:#6b7280;">{{title}}</span></p>' : '')
            .'<p>Hello {{'.$name_placeholder.'}},</p>'
            .'<p>'.$spec['intro'].'</p>'
            .'<h4>'.$spec['section'].'</h4>'
            .'<p>{{details}}</p>'
            .'<p><span style="color:#6b7280;">Date &amp; Time: {{date_time}}</span></p>'
            .(isset($spec['steps']) ? $this->steps($spec['steps_title'], $spec['steps']) : '')
            .(isset($spec['note']) ? $this->note($spec['note']) : '')
            .($name_placeholder == 'user_name' ? '<p><span style="color:#6b7280;">Need help? Simply contact our support team, we are always happy to help.</span></p>' : ''),
            $spec['heading_tone'] ?? $tone
        );
    }

    // ---- Editor (CKEditor) safe building blocks: alignment, headings, font/background color, lists, blockquote ----


    public function badge(string $text, string $tone): string
    {
        [$color, $background] = self::TONES[$tone];
        return '<p style="text-align:center;"><span style="background-color:'.$background.';color:'.$color.';"><strong>'.strtoupper($text).'</strong></span></p>';
    }

    public function code(string $code): string
    {
        [$color, $background] = self::TONES['brand'];
        return '<p style="text-align:center;">Your verification code</p>'
            .'<h2 style="text-align:center;"><span style="background-color:'.$background.';color:'.$color.';"><strong>'.$code.'</strong></span></h2>';
    }

    public function steps(string $title, array $steps): string
    {
        return '<h4>'.$title.'</h4><ul>'.implode('', array_map(fn($step) => '<li>'.$step.'</li>', $steps)).'</ul>';
    }

    public function note(string $text): string
    {
        return '<blockquote><p>'.$text.'</p></blockquote>';
    }

    /**
     * Logo (Light Version) of the template owner so admin sees the real logo in the editor.
     * Falls back to {{logo}} placeholder when basic settings are not seeded yet.
     */
    public function logo(): string
    {
        $basic_settings = BasicSettings::first();
        if(!$basic_settings) return '{{logo}}';

        $logo = $basic_settings->{EmailTemplate::LOGO_COLUMNS[$this->group] ?? 'site_logo'} ?: $basic_settings->site_logo;
        $src = $logo ? files_asset_path('image-assets').'/'.$logo : files_asset_path('default');
        return '<img class="image_resized" style="width:150px;" src="'.e($src).'" alt="'.e($basic_settings->site_name ?? "logo").'">';
    }

    /**
     * Common layout: logo, heading, content, footer.
     * Only editor (CKEditor) supported markup, so admin can edit without losing the design.
     */
    public function layout(string $heading, string $content, string $tone = 'brand'): string
    {
        return '<p style="text-align:center;">'.$this->logo().'</p>'
            .'<h2 style="text-align:center;">'.$heading.'</h2>'
            .$content
            .'<p>Thanks,<br><strong>{{site_name}} Team</strong></p>'
            .'<hr>'
            .'<p style="text-align:center;"><span style="color:#9ca3af;">This is an automated message, please do not reply to this email.<br>© {{current_year}} <a href="{{site_url}}"><strong>{{site_name}}</strong></a>. All rights reserved.</span></p>';
    }
}
