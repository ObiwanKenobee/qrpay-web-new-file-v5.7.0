<?php

namespace App\Notifications\PaymentLink\Wallet;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use App\Constants\PaymentGatewayConst;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserNotification extends Notification
{
    use Queueable;

    private $user;
    private $data;
    private $trx_id;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$data, $trx_id)
    {
        $this->user = $user;
        $this->data = $data;
        $this->trx_id = $trx_id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_PAYMENT_LINK_RECEIVED, $notifiable) ? [] : ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $this->user;
        $data = $this->data;
        $trx_id = $this->trx_id;

        $date = Carbon::now();
        $dateTime = dateFormat('Y-m-d h:i:s A', $date);



        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_PAYMENT_LINK_RECEIVED, [
            'user_name'     => e($user->fullname),
            'subject'       => e(__("Payment Link Transaction via")." ". $data['transaction_type']),
            'title'         => e(__("Your payment request successfully via")." ".$data['transaction_type']),
            'details'       => EmailTemplate::detailsTable([
                __("Request Amount")                  => get_amount($data['amount'],$data['charge_calculation']['receiver_currency_code']),
                __("Exchange Rate")                   => get_amount(1,$data['charge_calculation']['base_currency_code']).' = '. get_amount($data['charge_calculation']['r_exchange_rate'],$data['charge_calculation']['receiver_currency_code']),
                __("Fees & Charges")                  => get_amount($data['charge_calculation']['r_total_charge'], $data['charge_calculation']['receiver_currency_code']),
                __("Will Get")                        => get_amount($data['charge_calculation']['receiver_amount'], $data['charge_calculation']['receiver_currency_code']),
                __("web_trx_id")                      => $trx_id,
                __("Status")                          => "Success",
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
            ->greeting(__("Hello")." ".$user->fullname." !")
            ->subject(__("Payment Link Transaction via")." ".$data['transaction_type'])
            ->line(__("Your payment request successfully via")." ".$data['transaction_type']." ,".__("details of transactions").":")
            ->line(__("Request Amount").": " . get_amount($data['amount'],$data['charge_calculation']['sender_cur_code'],4))
            ->line(__("Exchange Rate").": " . get_amount(1,$data['charge_calculation']['receiver_currency_code']).' = '. get_amount($data['charge_calculation']['exchange_rate'],$data['charge_calculation']['sender_cur_code'],4))
            ->line(__("Fees & Charges").": " . get_amount($data['charge_calculation']['conversion_charge'], $data['charge_calculation']['receiver_currency_code'],4))
            ->line(__("Will Get").": " . get_amount($data['charge_calculation']['conversion_payable'], $data['charge_calculation']['receiver_currency_code'],4))
            ->line(__("web_trx_id").": " .$trx_id)
            ->line(__("Status").": Success")
            ->line(__("Date And Time").": " .$dateTime)
            ->line(__('Thank you for using our application!'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
