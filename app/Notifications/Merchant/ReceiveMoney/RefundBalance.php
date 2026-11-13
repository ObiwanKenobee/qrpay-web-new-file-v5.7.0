<?php

namespace App\Notifications\Merchant\ReceiveMoney;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class RefundBalance extends Notification
{
    use Queueable;

    public $user;
    public $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$data)
    {
        $this->user = $user;
        $this->data = $data;

    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return EmailTemplate::isDisabled(EmailTemplateConst::MERCHANT_REFUND_BALANCE, $notifiable) ? [] : ['mail'];
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
        $date = Carbon::now();
        $username = $data->details->sender->username ?? $data->details->sender_username;

        $dateTime = $date->format('Y-m-d h:i:s A');
        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::MERCHANT_REFUND_BALANCE, [
            'user_name'     => e($user->fullname),
            'subject'       => e(__("Refund Balance")),
            'title'         => e(__("The refunded amount has been successfully sent to USER ")."@".$data->details->sender_username),
            'details'       => EmailTemplate::detailsTable([
                __("web_trx_id")                 => $data->trx_id,
                __("Refund Balance")             => get_amount($data->request_amount,get_default_currency_code(),get_wallet_precision()),
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject(__("Refund Balance"))
                    ->line(__("The refunded amount has been successfully sent to USER ")."@".$username)
                    ->line(__("web_trx_id").": " . $data->trx_id)
                    ->line(__("Refund Balance").": " . get_amount($data->request_amount,get_default_currency_code(),get_wallet_precision()))
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
