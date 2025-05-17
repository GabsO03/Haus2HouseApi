<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Mail\PasswordResetCodeMail;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    protected $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new PasswordResetCodeMail($notifiable, $this->code))
            ->to($notifiable->email);
    }

    public function toArray($notifiable)
    {
        return [
            'code' => $this->code,
            'message' => 'Código de verificación para cambio de contraseña enviado.',
        ];
    }
}