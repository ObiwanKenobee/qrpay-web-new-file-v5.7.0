<?php

namespace App\Notifications\Kyc;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class Approved extends Notification
{
    use Queueable;

    public $user;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;

    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_KYC_APPROVED, $notifiable) ? [] : ['mail'];
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
        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');

        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_KYC_APPROVED, [
            'user_name'     => e($user->fullname),
            'subject'       => e(__("KYC Verification")),
            'title'         => e(__("Your KYC verification request is approved by admin")),
            'details'       => EmailTemplate::detailsTable([
                __("Status")                            => __("Approved"),
            ]),
            'date_time'     => $dateTime,
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject(__("KYC Verification"))
                    ->line(__("Your KYC verification request is approved by admin"))
                    ->line(__("Approved At").": " .$dateTime)
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
