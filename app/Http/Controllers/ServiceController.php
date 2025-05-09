<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Enums\Estados;
use App\Models\Worker;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StripeController;
use App\Notifications\ServiceAssignedNotification;

class ServiceController extends Controller
{

    public function show(int $service)
    {
        $serviceData = Service::with('client.user', 'worker.user', 'serviceType')
            ->findOrFail($service);

        $user = Auth::user();
        
        // Verifico si el usuario es el cliente o el trabajador del servicio
        if (!$user || ($user->id !== $serviceData->client_id && $user->id !== $serviceData->worker_id)) {
            return response()->json([
                'data' => [],
                'message' => 'No tienes permiso para acceder a esta información',
                'status' => 403,
            ], 403);
        }

        try {
            return response()->json([
                'data' => $serviceData,
                'message' => 'Servicio obtenido correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el servicio: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Obtiene la disponibilidad y los servicios del trabajador para un mes específico
     */
    public function getSchedule(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'worker') {
                return response()->json([
                    'data' => [],
                    'message' => 'No tienes permiso para acceder a esta información',
                    'status' => 403
                ], 403);
            }

            $worker = Worker::where('user_id', $user->id)->firstOrFail();

            // Usar fecha actual si no se proporcionan mes y año
            $today = Carbon::today();
            $month = $request->input('month', $today->month);
            $year = $request->input('year', $today->year);

            // Validar parámetros de mes y año
            $request->merge(['month' => $month, 'year' => $year]);
            $request->validate([
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer|min:2020|max:2030',
            ]);

            // Obtener disponibilidad
            $disponibilidad = json_decode($worker->disponibilidad, true) ?? [];

            // Obtener servicios ACCEPTED o IN_PROGRESS para el mes
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            $services = Service::where('worker_id', $worker->id)
                ->whereIn('status', [Estados::ACCEPTED->value, Estados::IN_PROGRESS->value])
                ->whereBetween('start_time', [$startOfMonth, $endOfMonth])
                ->with('serviceType')
                ->get()
                ->map(function ($service) {
                    return [
                        'day' => Carbon::parse($service->start_time)->day,
                        'start_time' => Carbon::parse($service->start_time)->format('H:i'),
                        'end_time' => Carbon::parse($service->end_time)->format('H:i'),
                        'service_type' => $service->serviceType->name,
                    ];
                })->groupBy('day')->toArray();

            return response()->json([
                'data' => [
                    'disponibilidad' => $disponibilidad,
                    'services' => $services,
                ],
                'message' => 'Horario obtenido correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el horario: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function serviceTypes() {
        try {

            $servicesTypes = ServiceType::all();

            return response()->json([
                'data' => $servicesTypes,
                'message' => 'Servicio obtenido correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el servicio: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Crea un nuevo servicio
     */
    public function store(Request $request)
    {
        try {
            // Recojo los datos para asginar un trabajador de acuerdo a los requisitos
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'service_type_id' => 'required|exists:service_types,id', // O sea, si es limpieza, cocina o cuidado
                'description' => 'nullable|string|max:255', // Esto es que es lo que va a hacer
                'specifications' => 'nullable|string|max:255', // Esto las especificaciones, tipo si es un alergico
                'request_time' => 'required|date', // Hora cuando lo solicitó
                'start_time' => 'required|date|after:request_time', // Hora que puso que empieza el servicio, ej. mañana a las 12
                'end_time' => 'required|date|after:start_time', // Hora que puso que empieza el servicio, ej. mañana a las 12
                'client_location' => 'required|string|max:255', // donde vive el cliente
                'total_amount' => 'required|numeric|min:0'
            ]);

            // Lo asigno
            $worker = WorkerController::encontrarWorker($validated);

            if (!$worker) { // En caso que no haya un trabajador disponible
                return response()->json([
                    'data' => [],
                    'message' => 'No hay trabajadores disponibles',
                    'status' => 400
                ], 400);
            }

            // Finalmente creo el servicio, los últimos campos se llenarán al terminarlo
            $service = Service::create([
                'client_id' => $validated['client_id'],
                'worker_id' => $worker->id,
                'service_type_id' => $validated['service_type_id'],
                'description' => $validated['description'] ?? null,
                'specifications' => $validated['specifications'] ?? null,
                'request_time' => $validated['request_time'],
                'start_time' => $validated['start_time'],
                'duration_hours' => null,
                'end_time' => $validated['end_time'] ?? null,
                'client_location' => $validated['client_location'],
                'worker_location' => $worker->current_location ?? null,
                'status' => Estados::PENDING->value,
                'total_amount' => $validated['total_amount'],
                'payment_stripe_id' => null,
                'client_rating' => null,
                'worker_rating' => null,
                'client_comments' => null,
                'worker_comments' => null,
                'incident_report' => null,
            ]);

            // Una vez ya asignado se les manda una notificacion a ambas partes para que esten pendientes
            UserController::notifyUsers($service, $worker);


            // Cargo el objeto con los demás a traves de sus relaciones
            $service->load('client.user', 'worker.user', 'serviceType');

            return response()->json([
                'data' => $service,
                'message' => 'Servicio creado y trabajador asignado',
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al crear servicio: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Lista servicios pendientes
     */
    public function index(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:workers,id'
        ]);

        $worker = Worker::findOrFail($request->id);

        $user = Auth::user();
        if (!$user || ($user->role !== 'worker' && $user->worker->id !== $worker->id)) {
            return response()->json([
                'data' => [],
                'message' => 'No tienes permiso para acceder a esta información',
                'status' => 403,
            ], 403);
        }

        try {
            $services = Service::where('status', Estados::PENDING->value)
                ->where('worker_id', $worker->id)
                ->with('client.user', 'worker.user', 'serviceType')
                ->get();

            return response()->json([
                'data' => $services,
                'message' => 'Servicios pendientes obtenidos',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener servicios pendientes: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }



    /**
     * Actualizar el estado de un servicio
     */
    public function actualizarEstado(Request $request, $id) {
        try {
            $service = Service::findOrFail($id);

            $request->validate([
                'status' => 'required|in:' . implode(',', array_column(Estados::cases(), 'value'))
            ]);

            $nuevoEstado = Estados::from($request['status']);

            switch ($service->status->value) {
                case Estados::PENDING->value:
                    if (!in_array($nuevoEstado, [Estados::ASSIGNED])) {
                        return response()->json([
                            'data' => [],
                            'message' => 'Un servicio pendiente solo puede ser asignado',
                            'status' => 400
                        ], 400);
                    }
                break;

                case Estados::ASSIGNED->value:
                    if (!in_array($nuevoEstado, [Estados::ACCEPTED, Estados::CANCELLED])) {
                        return response()->json([
                            'data' => [],
                            'message' => 'Un servicio asignado solo puede ser aceptado o cancelado',
                            'status' => 400
                        ], 400);
                    }

                    // Sí se acepta el servicio, se realiza el pago automático
                    if ($nuevoEstado === Estados::ACCEPTED) {
                        try {
                            StripeController::procesarPago($service);

                            if (!WorkerController::updateDisponibilidad($service)) {
                                // Revertir el pago si falla la actualización
                                StripeController::reembolsar($service);
                                return response()->json([
                                    'data' => [],
                                    'message' => 'Error al actualizar la disponibilidad del trabajador',
                                    'status' => 500
                                ], 500);
                            }
                        } catch (Exception $e) {
                            return response()->json([
                                'data' => [],
                                'message' => 'No se pudo procesar el pago: ' . $e->getMessage(),
                                'status' => 400
                            ], 400);
                        }
                    }
                    elseif ($nuevoEstado === Estados::CANCELLED) {
                        $this->reasignarWorker($service);
                        
                        $service = $service->fresh()->load('client.user', 'worker.user', 'serviceType'); // Con fresh me aseguro que el objeto este actualizado

                        return response()->json([
                            'data' => $service,
                            'message' => 'Servicio cancelado y reasignado',
                            'status' => 200
                        ]);
                    }
                break;

                case Estados::ACCEPTED->value:
                    if (!in_array($nuevoEstado, [Estados::IN_PROGRESS, Estados::CANCELLED])) {
                        return response()->json([
                            'data' => [],
                            'message' => 'Un servicio aceptado solo puede pasar a en progreso o ser cancelado',
                            'status' => 400
                        ], 400);
                    }
                    if ($nuevoEstado === Estados::CANCELLED) {
                        // Validar comentarios y ratings al cancelar
                        $this->cancelarServicio($request, $service);

                        $service = $service->fresh()->load('client.user', 'worker.user', 'serviceType'); // Con fresh me aseguro que el objeto este actualizado
                
                        return response()->json([
                            'data' => $service->fresh()->load('client.user', 'worker.user', 'serviceType'),
                            'message' => 'Servicio cancelado con reembolso',
                            'status' => 200
                        ]);
                    }
                    
                break;

                case Estados::IN_PROGRESS->value:
                    if (!in_array($nuevoEstado, [Estados::COMPLETED, Estados::CANCELLED])) {
                        return response()->json([
                            'data' => [],
                            'message' => 'Un servicio en progreso solo puede ser completado o cancelado',
                            'status' => 400
                        ], 400);
                    }

                    
                    if ($nuevoEstado === Estados::COMPLETED) {
                        $service->end_time = now();
                        $startTime = new DateTime($service->start_time);
                        $endTime = new DateTime($service->end_time);
                        $interval = $startTime->diff($endTime);
                        $service->duration_hours = $interval->h + ($interval->i / 60);
                    } elseif ($nuevoEstado === Estados::CANCELLED) {
                        $this->cancelarServicio($request, $service);
                        return response()->json([
                            'data' => $service->fresh()->load('client.user', 'worker.user', 'serviceType'),
                            'message' => 'Servicio cancelado con reembolso',
                            'status' => 200
                        ]);
                    }

                break;

                default:
                    return response()->json([
                        'data' => [],
                        'message' => 'El estado del servicio no permite esta acción',
                        'status' => 400
                    ], 400);
                break;

            }

            $service->status = $nuevoEstado;
            $service->save();

            $service->load('client.user', 'worker.user', 'serviceType');

            return response()->json([
                'data' => $service,
                'message' => 'Estado del servicio actualizado',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [$nuevoEstado->value],
                'message' => 'Error al actualizar estado del servicio: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Valorar un servicio después de completado
     */
    public function valorar(Request $request, $id)
    {
        try {
            $service = Service::findOrFail($id);

            if ($service->status->value !== Estados::COMPLETED->value) {
                return response()->json([
                    'data' => [],
                    'message' => 'El servicio no está completado',
                    'status' => 400
                ], 400);
            }

            $validated = $request->validate([
                'client_rating' => 'nullable|integer|between:1,5',
                'worker_rating' => 'nullable|integer|between:1,5',
                'client_comments' => 'nullable|string',
                'worker_comments' => 'nullable|string',
            ]);

            $service->update([
                'client_rating' => $validated['client_rating'] ?? $service->client_rating,
                'worker_rating' => $validated['worker_rating'] ?? $service->worker_rating,
                'client_comments' => $validated['client_comments'] ?? $service->client_comments,
                'worker_comments' => $validated['worker_comments'] ?? $service->worker_comments,
            ]);

            if ($validated['client_rating']) {
                $worker = $service->worker;
                $worker->rating = (($worker->rating * $worker->cantidad_ratings) + $validated['client_rating']) / ($worker->cantidad_ratings + 1);
                $worker->cantidad_ratings += 1;
                $worker->save();
            }

            if ($validated['worker_rating']) {
                $client = $service->client;
                $client->rating = (($client->rating * $client->cantidad_ratings) + $validated['worker_rating']) / ($client->cantidad_ratings + 1);
                $client->cantidad_ratings += 1;
                $client->save();
            }

            $service->load('client.user', 'worker.user', 'serviceType');

            return response()->json([
                'data' => $service,
                'message' => 'Servicio calificado',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al calificar servicio: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Si el trabajador rechaza (no cancela porque solo está en pendiente)
     */
    private function reasignarWorker(Service $service)
    {
        // Busca de nuevo un trabajador que cumpla conlas condiciones
        $previousWorkerId = $service->worker_id;
        $worker = WorkerController::encontrarWorker([
            'service_type_id' => $service->service_type_id,
            'start_time' => $service->start_time,
        ], $previousWorkerId);

        if (!$worker) {
            $service->status = Estados::CANCELLED;
            $service->worker_id = null;
            $service->save();
            $service->client->user->notify(new ServiceAssignedNotification($service));
            return;
        }

        $service->worker_id = $worker->id;
        $service->status = Estados::ASSIGNED;
        $service->save();

        UserController::notifyUsers($service, $worker);
    }

    /**
     * Función por si el servicio es cancelado
     */
    public function cancelarServicio(Request $request, Service $service) : void {
            // Validar comentarios y ratings al cancelar
        $validated = $request->validate([
            'client_comments' => 'nullable|string',
            'worker_comments' => 'nullable|string',
            'client_rating' => 'nullable|integer|between:1,5',
            'worker_rating' => 'nullable|integer|between:1,5',
        ]);

        // Procesar reembolso
        if ($service->payment_stripe_id) {
            StripeController::reembolsar($service);
        }

        // Guardar comentarios y ratings
        $service->update([
            'client_comments' => $validated['client_comments'] ?? $service->client_comments,
            'worker_comments' => $validated['worker_comments'] ?? $service->worker_comments,
            'client_rating' => $validated['client_rating'] ?? $service->client_rating,
            'worker_rating' => $validated['worker_rating'] ?? $service->worker_rating,
        ]);

        // Actualizar ratings
        if ($validated['client_rating']) {
            $worker = $service->worker;
            $worker->rating = (($worker->rating * $worker->cantidad_ratings) + $validated['client_rating']) / ($worker->cantidad_ratings + 1);
            $worker->cantidad_ratings += 1;
            $worker->save();
        }
        if ($validated['worker_rating']) {
            $client = $service->client;
            $client->rating = (($client->rating * $client->cantidad_ratings) + $validated['worker_rating']) / ($client->cantidad_ratings + 1);
            $client->cantidad_ratings += 1;
            $client->save();
        }

        $service->status = Estados::CANCELLED;
        $service->save();
        UserController::notifyUsers($service, $service->worker);        
    }

}