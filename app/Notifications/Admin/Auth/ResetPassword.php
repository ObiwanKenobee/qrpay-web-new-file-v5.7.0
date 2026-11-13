<?php

namespace App\Notifications\Admin\Auth;

use App\Constants\EmailTemplateConst;
use App\Models\Admin\EmailTemplate;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    use Queueable;


    protected $token;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
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
        $basic_settings = BasicSettingsProvider::get();
        $reset_url =  url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $template_mail = EmailTemplate::mailMessage(EmailTemplateConst::ADMIN_PASSWORD_RESET, [
            'admin_name'    => e($notifiable->fullname ?? ""),
            'reset_url'     => e($reset_url),
        ]);
        if($template_mail) return $template_mail;

        return (new MailMessage)
                    ->view('mail-templates.admin.auth.password-reset',[
                        'reset_url'         => $reset_url,
                        'site_url'          => url('/'),
                        'site_name'         => $basic_settings->site_name ?? "",
                        'logo'              => get_logo_public_path($basic_settings),
                    ]);
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
