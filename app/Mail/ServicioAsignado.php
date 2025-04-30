<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServicioAsignado extends Mailable
{
    use Queueable, SerializesModels;

    public $notifiable;
    public $service;
    public $role;
    public $mensaje;

    public function __construct($notifiable, $service, $role, $mensaje)
    {
        $this->notifiable = $notifiable;
        $this->service = $service;
        $this->role = $role;
        $this->mensaje = $mensaje;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function build()
    {
        return $this->from('h2h.notificaciones@gmail.com', 'Haus2House.notificaciones')
            ->to($this->notifiable->email)
            ->subject('Servicio Asignado - ' . $this->role)
            ->view('email.servicio_asignado')
            ->with([
                'notifiable' => $this->notifiable,
                'service' => $this->service,
                'role' => $this->role,
                'mensaje' => $this->mensaje
            ]);
    }
}
