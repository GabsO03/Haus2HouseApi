<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Enums\Estados;
use App\Models\Client;
use App\Models\Worker;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StripeController;
use App\Notifications\ServiceAssignedNotification;

class ServiceController extends Controller
{

    public function show(string $service)
    {
        $serviceData = Service::with('client.user', 'worker.user', 'serviceType')
            ->findOrFail($service);


        try {
            return response()->json([
                'data' => $serviceData,
                'message' => 'Servicio obtenido correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el servicio',
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
                'message' => 'Error al obtener el servicio',
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
            Log::info('Starting store function for service creation', ['request' => $request->all()]);

            // 1: Validar los datos que llegan
            Log::info('Validating request data');
            $validated = $request->validate([
                'client_id' => 'required|exists:users,id', // En realidad aquí recibe el id del user
                'service_type_id' => 'required|exists:service_types,id',
                'description' => 'nullable|string|max:255',
                'specifications' => 'nullable|string|max:255',
                'request_time' => 'required|date',
                'start_time' => [
                    'required',
                    'date',
                    'after:request_time',
                    function ($attribute, $value, $fail) use ($request) {
                        $requestTime = Carbon::parse($request->request_time);
                        $startTime = Carbon::parse($value);
                        if ($startTime->gt($requestTime->addMonth())) {
                            Log::warning('Start time validation failed: Start time is more than a month after request time', [
                                'start_time' => $value,
                                'request_time' => $request->request_time
                            ]);
                            $fail('El inicio del servicio no puede ser posterior a un mes desde la solicitud.');
                        }
                    },
                ],
                'end_time' => [
                    'required',
                    'date',
                    'after:start_time',
                    function ($attribute, $value, $fail) use ($request) {
                        $startTime = Carbon::parse($request->start_time);
                        $endTime = Carbon::parse($value);
                        if (!$endTime->isSameDay($startTime)) {
                            Log::warning('End time validation failed: End time is not on the same day as start time', [
                                'end_time' => $value,
                                'start_time' => $request->start_time
                            ]);
                            $fail('El fin del servicio debe ser el mismo día que el inicio.');
                        }
                    },
                ],
                'client_location' => 'required|string|max:255',
                'total_amount' => 'required|numeric|min:0',
                'payment_method' => 'required|string',
            ]);
            Log::info('Request data validated successfully', ['validated' => $validated]);

            // 2: Asignar un trabajador
            Log::info('Attempting to find a worker for the service');
            $worker = WorkerController::encontrarWorker($validated);
            if (!$worker) {
                Log::warning('No worker available for the service', ['validated' => $validated]);
                return response()->json([
                    'data' => [],
                    'message' => 'No hay trabajadores disponibles',
                    'status' => 400
                ], 400);
            }
            Log::info('Worker found', ['worker_id' => $worker->id]);

            // 3: Conseguir  el id del cliente
            Log::info('Fetching client ID for user', ['user_id' => $validated['client_id']]);
            $client = Client::where('user_id', $validated['client_id'])->first();
            if (!$client) {
                Log::error('Client not found for user_id', ['user_id' => $validated['client_id']]);
                return response()->json([
                    'data' => [],
                    'message' => 'Cliente no encontrado',
                    'status' => 404
                ], 404);
            }
            $client_id = $client->id;
            Log::info('Client ID retrieved', ['client_id' => $client_id]);

            // 4: Crear el servicio
            Log::info('Creating new service record');
            $service = Service::create([
                'client_id' => $client_id,
                'worker_id' => $worker->id,
                'service_type_id' => $validated['service_type_id'],
                'description' => $validated['description'] ?? null,
                'specifications' => $validated['specifications'] ?? 'Ninguna',
                'request_time' => $validated['request_time'],
                'start_time' => $validated['start_time'],
                'duration_hours' => null,
                'end_time' => $validated['end_time'] ?? null,
                'client_location' => $validated['client_location'],
                'worker_location' => $worker->current_location ?? null,
                'status' => Estados::ASSIGNED->value,
                'total_amount' => $validated['total_amount'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pendiente',
                'payment_stripe_id' => null,
                'client_rating' => null,
                'worker_rating' => null,
                'client_comments' => null,
                'worker_comments' => null,
                'incident_report' => null,
            ]);
            Log::info('Service created successfully', ['service_id' => $service->id]);

            // 5: Notify users
            Log::info('Notifying users about the service assignment', ['service_id' => $service->id, 'worker_id' => $worker->id]);
            UserController::notifyUsers($service, $worker);
            Log::info('Users notified successfully');

            // 6: Cargar las relaciones
            Log::info('Loading service relationships');
            $service->load('client.user', 'worker.user', 'serviceType');
            Log::info('Relationships loaded', ['service_id' => $service->id]);

            // 7: Devolver los datos
            Log::info('Returning success response');
            return response()->json([
                'data' => $service,
                'message' => 'Servicio creado y trabajador asignado',
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            Log::error('Error in store function', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'request' => $request->all()
            ]);
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
    public function index(string $user_id)
    {
        $user = User::findOrFail($user_id);
        $worker_client = null;

        if ($user->rol === 'worker') {
            $worker_client = Worker::with('user')->where('user_id', $user_id)->firstOrFail();
        } else {
            $worker_client = Client::with('user')->where('user_id', $user_id)->firstOrFail();            
        }
        
        try {
            $services = Service::where(function ($query) use ($worker_client) {
                $query->where('worker_id', $worker_client->id)
                      ->orWhere('client_id', $worker_client->id);
            })
            ->where('status', '!=', Estados::CANCELLED->value)
            ->where('status', '!=', Estados::REJECTED->value)
            ->where('status', '!=', Estados::COMPLETED->value)
            ->with('client.user', 'worker.user', 'serviceType')
            ->orderBy('start_time')
            ->get();

            return response()->json([
                'data' => $services,
                'message' => 'Servicios asignados obtenidos',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener servicios pendientes',
                'status' => 500
            ], 500);
        }
    }

    public function historial(string $user_id)
    {
        $user = User::findOrFail($user_id);
        $worker_client = null;

        if ($user->rol === 'worker') {
            $worker_client = Worker::with('user')->where('user_id', $user_id)->firstOrFail();
        } else {
            $worker_client = Client::with('user')->where('user_id', $user_id)->firstOrFail();            
        }
        
        try {
            $services = Service::where(function ($query) use ($worker_client) {
                $query->where('worker_id', $worker_client->id)
                    ->orWhere('client_id', $worker_client->id);
            })
            ->where(function ($query) {
                $query->where('status', Estados::CANCELLED->value)
                    ->orWhere('status', Estados::REJECTED->value)
                    ->orWhere('status', Estados::COMPLETED->value);
            })
            ->with('client.user', 'worker.user', 'serviceType')
            ->orderBy('start_time')
            ->get();

            return response()->json([
                'data' => $services,
                'message' => 'Historial de servicios obtenido',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener servicios pendientes',
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
                            if ($service->payment_method === 'card') {
                                StripeController::procesarPago($service);
                                $service->payment_status = 'pagado';
                            }

                            // Actualizar disponibilidad
                            $worker = Worker::findOrFail($service->worker_id);
                            $disponibilidad = WorkerController::updateDisponibilidadPorServicio($service);
                            if ($disponibilidad === false) {
                                // Revertir el pago si falla la actualización
                                if ($service->payment_method === 'card') {
                                    StripeController::reembolsar($service);
                                    $service->payment_status = 'reembolsado';
                                }
                                return response()->json([
                                    'data' => [],
                                    'message' => 'Error al actualizar la disponibilidad del trabajador',
                                    'status' => 500
                                ], 500);
                            }

                            // Guardar la disponibilidad actualizada
                            $worker->disponibilidad = json_encode($disponibilidad);
                            $worker->save();

                        } catch (Exception $e) {
                            // Revertir el pago en caso de error
                            if ($service->payment_method === 'card' && $service->payment_status === 'pagado') {
                                StripeController::reembolsar($service);
                                $service->payment_status = 'reembolsado';
                            }
                            return response()->json([
                                'data' => [],
                                'message' => 'No se pudo procesar el pago o actualizar la disponibilidad',
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
                        $this->cancelarServicio($service);

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

                    
                    if ($nuevoEstado === Estados::CANCELLED) {

                        $this->cancelarServicio($service);
                        return response()->json([
                            'data' => $service->fresh()->load('client.user', 'worker.user', 'serviceType'),
                            'message' => 'Servicio cancelado con reembolso',
                            'status' => 200
                        ]);

                    } else {
                        return response()->json([
                            'data' => [],
                            'message' => 'Debe confirmar el pago del servicio antes de completar',
                            'status' => 400
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
                'data' => [],
                'message' => 'Error al actualizar estado del servicio',
                'status' => 500
            ], 500);
        }
    }

    public function actualizarEstadoPago(Request $request, string $service) {
        try {
            $request->validate([
                'status' => 'required|in:emitido,pagado'
            ]);

            $service = Service::findOrFail($service);

            if ($service->payment_method != 'cash') {
                throw new Exception('Tipo pago equivocado', 400);
            }

            if ($service->status->value != Estados::IN_PROGRESS->value) {
                throw new Exception('Espere que el servicio empiece', 400);
            }

            if ($request->status == 'pagado' && $service->payment_status != 'emitido') {
                throw new Exception('Debe haber un pago primero', 400);
            }

            $service->payment_status = $request->status;
            $service->save();

            return response()->json([
                'data' => $service,
                'message' => 'Estado de pago actualizado',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage() ?? 'Error al confirmar el pago del servicio',
                'status' => $e->getCode() ?? 500
            ], $e->getCode() ?? 500);
        }
    }

    /**
     * Valorar un servicio después de completado
     */
    public function valorar(Request $request, $id)
    {
        try {
            $service = Service::findOrFail($id);

            if (!in_array($service->status->value, [Estados::COMPLETED->value, Estados::CANCELLED->value])) {
                return response()->json([
                    'data' => [],
                    'message' => 'El servicio debe completarse o cancelarse primero',
                    'status' => 400
                ], 400);
            }

            $validated = $request->validate([
                'user_id' => 'required|string',
                'user_rating' => 'nullable|integer|between:1,5',
                'user_comments' => 'nullable|string',
            ]);

            $userRole = User::find($validated['user_id'])->rol;
            $rating = $validated['user_rating'] ?? null;

            if ($userRole == 'client') {
                if ($rating) {
                    $worker = $service->worker;
                    
                    // Si el servicio ya tiene un worker_rating, restar el rating anterior
                    if ($service->worker_rating) {
                        // Restar el rating anterior del total acumulado y la cantidad de ratings
                        $totalRating = $worker->rating * $worker->cantidad_ratings - $service->worker_rating;
                        $worker->cantidad_ratings = max(0, $worker->cantidad_ratings - 1);
                        $worker->rating = $worker->cantidad_ratings > 0 
                            ? ($totalRating + $rating) / ($worker->cantidad_ratings + 1)
                            : $rating;
                    } else {
                        $worker->rating = $worker->cantidad_ratings > 0 
                            ? (($worker->rating * $worker->cantidad_ratings) + $rating) / ($worker->cantidad_ratings + 1)
                            : $rating;
                    }
                    
                    // Incrementar el conteo de ratings
                    $worker->cantidad_ratings += 1;
                    $worker->save();
                }
                $service->update([
                    'client_rating' => $validated['user_rating'],
                    'client_comments' => $validated['user_comments'],
                ]);
            }
            else {
                if ($rating) {
                    $client = $service->client;
                    
                    // Hacemos lo mismo que con el worker
                    if ($service->client_rating) {
                        $totalRating = $client->rating * $client->cantidad_ratings - $service->client_rating;
                        $client->cantidad_ratings = max(0, $client->cantidad_ratings - 1);
                        $client->rating = $client->cantidad_ratings > 0 
                            ? ($totalRating + $rating) / ($client->cantidad_ratings + 1)
                            : $rating;
                    } else {
                        $client->rating = $client->cantidad_ratings > 0 
                            ? (($client->rating * $client->cantidad_ratings) + $rating) / ($client->cantidad_ratings + 1)
                            : $rating;
                    }
                    
                    $client->cantidad_ratings += 1;
                    $client->save();
                }

                $service->update([
                    'worker_rating' => $validated['user_rating'],
                    'worker_comments' => $validated['user_comments'],
                ]);
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
                'message' => 'Error al calificar servicio',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Función por si el servicio es cancelado
     */
    public function cancelarServicio(Service $service) : void {

        // Procesar reembolso
        if ($service->payment_method == 'Efectivo' && $service->payment_stripe_id) {
            StripeController::reembolsar($service);
        }

        $service->status = Estados::CANCELLED;
        $service->save();
        
        UserController::notifyUsers($service, $service->worker);        
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
            $service->status = Estados::REJECTED;
            // $service->worker_id = null;
            $service->save();
            $service->client->user->notify(new ServiceAssignedNotification($service));
            return;
        }

        $service->worker_id = $worker->id;
        $service->status = Estados::ASSIGNED;
        $service->save();

        UserController::notifyUsers($service, $worker);
    }

}