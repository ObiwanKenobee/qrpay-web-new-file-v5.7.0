<?php

namespace App\Notifications\User\BillPay;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class BillPayMailAutomatic extends Notification
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
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_BILL_PAY_SUCCESS, $notifiable) ? [] : ['mail'];
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

        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_BILL_PAY_SUCCESS, [
            'user_name'     => e($user->fullname),
            'subject'       => e(__("Bill Pay For")." ". $data->biller_name.' ('.$data->bill_number.' )'),
            'title'         => e(__("Bill pay successful")),
            'details'       => EmailTemplate::detailsTable([
                __("web_trx_id")                 => $trx_id,
                __("Bill Month")                 => $data->bill_month,
                __("Bill Amount")                => $data->bill_amount,
                __("Exchange Rate")              => $data->exchange_rate,
                __("Fees & Charges")             => $data->charges,
                __("Total Payable Amount")       => $data->payable,
                __("Status")                     => $data->status,
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject(__("Bill Pay For")." ". $data->biller_name.' ('.$data->bill_number.' )')
                    ->line(__("Bill pay successful").",".__("details of bill pay").":")
                    ->line(__("web_trx_id").": " .$trx_id)
                    ->line(__("Bill Month").": " .$data->bill_month)
                    ->line(__("Bill Amount").": " . $data->bill_amount)
                    ->line(__("Exchange Rate").": " . $data->exchange_rate)
                    ->line(__("Fees & Charges").": " . $data->charges)
                    ->line(__("Total Payable Amount").": " .$data->payable)
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
