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
        $front = rtrim((string) config('app.front_url', ''), '/');
        if ($front === '') {
            $front = rtrim((string) config('app.url', ''), '/');
        }

        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);

        $resetUrl = $front.'/reset-password?'.$query;

        return (new MailMessage)
            ->subject(Lang::get('passwords.passwords.reset.subject'))
            ->greeting(Lang::get('passwords.passwords.reset.line1'))
            ->line(Lang::get('passwords.passwords.reset.line2'))
            ->action(Lang::get('passwords.passwords.reset.action'), url($resetUrl))
            ->line(Lang::get('passwords.passwords.reset.line3'));
    }

}
