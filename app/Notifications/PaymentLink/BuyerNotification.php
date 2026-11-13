<?php

namespace App\Notifications\PaymentLink;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Constants\PaymentGatewayConst;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class BuyerNotification extends Notification
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
    public function __construct($user, $data, $trx_id)
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
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_PAYMENT_LINK_BUYER, $notifiable) ? [] : ['mail'];
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
        $type = 'payment_link';

        $status = __("success");

        $date = Carbon::now();
        $dateTime = dateFormat('Y-m-d h:i:s A', $date);
        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_PAYMENT_LINK_BUYER, [
            'user_name'     => e($user['name'] ?? ""),
            'subject'       => e(__("Payment Link Transaction via")." ". $data['transaction_type']),
            'title'         => e(__("Your payment request successfully via")." ".$data[$type]->currency),
            'details'       => EmailTemplate::detailsTable([
                __("Request Amount")                  => getAmount($data['amount'],2).' '. $data[$type]->currency,
                __("web_trx_id")                      => $trx_id,
                __("Status")                          => $status,
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
            ->greeting("Hello ".$user['name']." !")
            ->subject(__("Payment Link Transaction via")." ". $data['transaction_type'])
            ->line(__("Your payment request successfully via")." ".$data[$type]->currency." ,".__("details of transactions").":")
            ->line(__("Request Amount").": " . getAmount($data['amount'],2).' '. $data[$type]->currency)
            ->line(__("web_trx_id").": " .$trx_id)
            ->line(__("Status").": ".$status)
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
