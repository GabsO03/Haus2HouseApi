<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $notifiable;
    public $code;

    public function __construct($notifiable, $code)
    {
        $this->notifiable = $notifiable;
        $this->code = $code;
    }

    public function build()
    {
        return $this->view('emails.password_reset_code')
                    ->subject('Código de Verificación para Cambio de Contraseña')
                    ->with([
                        'notifiable' => $this->notifiable,
                        'code' => $this->code,
                    ]);
    }
}