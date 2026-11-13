<?php

namespace App\Notifications\User\MobileTopup;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class TopupMail extends Notification
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
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_MOBILE_TOPUP_REQUEST, $notifiable) ? [] : ['mail'];
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
        $trx_id = $this->data->trx_id;
        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');

        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_MOBILE_TOPUP_REQUEST, [
            'user_name'     => e($user->fullname),
            'subject'       => e(__("Mobile Top Up For")." ". $data->topup_type.' ('.$data->mobile_number.' )'),
            'title'         => e(__("Sender Mobile top Up Email Heading")." ".$data->topup_type),
            'details'       => EmailTemplate::detailsTable([
                __("web_trx_id")                 => $trx_id,
                __("request Amount")             => getAmount($data->request_amount,4).' '.get_default_currency_code(),
                __("Fees & Charges")             => getAmount($data->charges,4).' '.get_default_currency_code(),
                __("Total Payable Amount")       => get_amount($data->payable,get_default_currency_code(),'4'),
                __("Status")                     => $data->status,
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject(__("Mobile Top Up For")." ". $data->topup_type.' ('.$data->mobile_number.' )')
                    ->line(__("Sender Mobile top Up Email Heading")." ".$data->topup_type." ,".__("details of mobile top up").":")
                    ->line(__("web_trx_id").": " .$trx_id)
                    ->line(__("request Amount").": " . getAmount($data->request_amount,4).' '.get_default_currency_code())
                    ->line(__("Fees & Charges").": " . getAmount($data->charges,4).' '.get_default_currency_code())
                    ->line(__("Total Payable Amount").": " . get_amount($data->payable,get_default_currency_code(),'4'))
                   ->line(__("Status").": " .$data->status)
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
