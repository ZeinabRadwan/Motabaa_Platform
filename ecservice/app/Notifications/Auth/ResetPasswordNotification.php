<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends ResetPassword
{

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(Lang::get('passwords.passwords.reset.subject'))
            ->greeting(Lang::get('passwords.passwords.reset.line1'))
            ->line(Lang::get('passwords.passwords.reset.line2'))
            ->action(Lang::get('passwords.passwords.reset.action'), url(config('app.front_url')."/reset-password"."?token=". $this->token. "&email=".$notifiable->email))
            ->line(Lang::get('passwords.passwords.reset.line3'));
    }

}
