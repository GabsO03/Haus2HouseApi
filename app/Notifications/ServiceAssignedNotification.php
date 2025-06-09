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

    private function crearMensaje($notifiable, $nuevoEstado): string
    {
            $esCliente = $notifiable->rol === 'client';

        switch ($nuevoEstado) {
            
            case 'assigned' && $this->service->previousWorkerId !== null: // Interpretado como "reasignado"
                if ($esCliente) {
                    return '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido reasignado a un nuevo trabajador: <strong>' . $this->service->worker->user->nombre . '</strong>.</p>';
                } else {
                    return '<p>Te han reasignado el servicio de <strong>' . $this->service->serviceType->name . '</strong> para el cliente: <strong>' . $this->service->client->user->nombre . '</strong>.</p>';
                }

            case 'assigned':
                if ($esCliente) {
                    return '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido asignado a nuestro trabajador: <strong>' . $this->service->worker->user->nombre . '</strong>.</p>';
                } else {
                    return '<p>Te han asignado un nuevo servicio de <strong>' . $this->service->serviceType->name . '</strong> para el cliente: <strong>' . $this->service->client->user->nombre . '</strong>.</p>';
                }

            case 'cancelled':
                if ($this->service->worker_id === null) {
                    return '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido cancelado y no se pudo reasignar a un trabajador.</p>';
                } else {
                    if ($esCliente) {
                        return '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido cancelado. Lamentamos las molestias.</p>';
                    } else {
                        return '<p>El servicio de <strong>' . $this->service->serviceType->name . '</strong> para el cliente <strong>' . $this->service->client->user->nombre . '</strong> ha sido cancelado.</p>';
                    }
                }
            
            case 'rejected': // Interpretado como "reasignado"
                if ($esCliente) {
                    return '<p>Tu servicio de <strong>' . $this->service->serviceType->name . '</strong> ha sido rechazado y no pudimos reasignar a otro trabajador. <p>';
                }

            default:
                return '<p>El estado del servicio ha cambiado a ' . $nuevoEstado . '.</p>';
        }
    }

    public function toMail($notifiable)
    {
        $role = $notifiable->rol === 'client' ? 'Cliente' : 'Trabajador';
        $nuevoEstado = $this->service->status->value;
        $message = $this->crearMensaje($notifiable, $nuevoEstado);

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
