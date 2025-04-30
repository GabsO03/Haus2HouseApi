<?php

namespace App\Notifications;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use App\Mail\ServicioAsignado;
use Illuminate\Notifications\Notification;

class ServiceAssignedNotification extends Notification
{
    use Queueable;

    protected $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $role = $notifiable->rol === 'client' ? 'Cliente' : 'Trabajador';
        $message = $notifiable->rol === 'client'
            ? '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido asignado a nuestro trabajador: <strong>' . $this->service->worker->user->nombre . '</strong>.</p>'
            : '<p>Te han asignado un nuevo servicio de <strong>' . $this->service->serviceType->name . '</strong> para el cliente: <strong>' . $this->service->client->user->nombre . '</strong>.</p>';


        return (new ServicioAsignado($notifiable, $this->service, $role, $message));
    }

    public function toArray($notifiable)
    {
        return [
            'service_id' => $this->service->id,
            'message' => $this->service->serviceType->name . ' asignado.',
            'role' => $notifiable->rol
        ];
    }
}
