<?php

namespace App\Notifications\Agent\SendMoney;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SenderMail extends Notification
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
        return EmailTemplate::isDisabled(EmailTemplateConst::AGENT_SEND_MONEY_SENT, $notifiable) ? [] : ['mail'];
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
        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::AGENT_SEND_MONEY_SENT, [
            'user_name'     => e($user->fullname),
            'subject'       => e($data->title),
            'title'         => e($data->title),
            'details'       => EmailTemplate::detailsTable([
                __("web_trx_id")                 => $trx_id,
                __("request Amount")             => $data->request_amount,
                __("Fees & Charges")             => $data->charges,
                __("Total Payable Amount")       => $data->payable,
                __("Recipient Received")         => $data->received_amount,
                __("Status")                     => $data->status,
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject($data->title)
                    ->line("Your Send Money  Request Send Successfully, Details Of Send Money:")
                    ->line($data->title)
                  ->line(__("web_trx_id").": " .$trx_id)
                    ->line(__("request Amount").": " .$data->request_amount)
                     ->line(__("Fees & Charges").": " .$data->charges)
                    ->line(__("Total Payable Amount").": " .$data->payable)
                   ->line(__("Recipient Received").": " .$data->received_amount)
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
