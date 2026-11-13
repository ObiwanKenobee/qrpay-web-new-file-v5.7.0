<?php

namespace App\Notifications\User\Auth;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendVerifyCode extends Notification
{
    use Queueable;

    public $email;
    public $code;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($email,$code)
    {
        $this->email = $email;
        $this->code = $code;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $code = $this->code;
        $username  = explode('@',$this->email);
        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::REGISTER_VERIFICATION_CODE, [
            'user_name'     => e(@$username[0]),
            'code'          => e($code),
        ], $notifiable);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->greeting(__("Hello")." ".@$username[0]." !")
                    ->subject(__("Verification Code ( Register )"))
                    ->line(__('You are trying to verify code for register.'))
                    ->line(__("Here is your OTP").": " . $code)
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
