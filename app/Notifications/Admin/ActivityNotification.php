<?php

namespace App\Notifications\Admin;

use App\Models\Admin\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public array $data)
    {
        //
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
        if(isset($this->data['template'])) {
            $template_mail = EmailTemplate::mailMessage($this->data['template'], [
                'admin_name'    => e($notifiable->fullname ?? ""),
                'subject'       => e($this->data['subject'] ?? ""),
                'title'         => e($this->data['greeting'] ?? ""),
                'details'       => EmailTemplate::detailsTable($this->data['content'] ?? ""),
                'date_time'     => Carbon::now()->format('Y-m-d h:i:s A'),
            ]);
            if($template_mail) return $template_mail;
        }

        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');
        return (new MailMessage)
                    ->subject($this->data['subject'])
                    ->greeting($this->data['greeting'])
                    ->line(new HtmlString($this->data['content']))
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
