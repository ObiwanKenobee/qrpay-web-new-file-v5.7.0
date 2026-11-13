<?php

namespace App\Notifications\User;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationBonusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data)
    {
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
        return EmailTemplate::isDisabled(EmailTemplateConst::USER_REGISTRATION_BONUS, $notifiable) ? [] : ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $data = $this->data;
        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::USER_REGISTRATION_BONUS, [
            'user_name'     => e($data['user_name']),
            'subject'       => e($data['system_name'] . " | ".__("Registration Bonus Added Successfully!")),
            'title'         => e(__('You got the registration bonus of').' '. get_amount($data['bonus_amount'],$data['currency_code']) .'. '.__('Now you can deposit and earn more.')),
            'details'       => EmailTemplate::detailsTable([
                __("Registration Bonus")                => get_amount($data['bonus_amount'],$data['currency_code']),
            ]),
            'date_time'     => Carbon::now()->format('Y-m-d h:i:s A'),
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->subject($data['system_name'] . " | ".__("Registration Bonus Added Successfully!"))
                    ->greeting(__("congratulations")."! (". $data['user_name'] .")")
                    ->line(__('You got the registration bonus of').' '. get_amount($data['bonus_amount'],$data['currency_code']) .'. '.__('Now you can deposit and earn more.'))
                    ->action(__('Login'), route('user.login'))
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
