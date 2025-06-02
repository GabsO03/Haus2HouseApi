<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Enums\Estados;
use App\Models\Worker;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class WorkerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $workers = Worker::with('user')->get();
    
            return response()->json([
                'data' => $workers,
                'message' => 'Trabajadores obtenidos correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener trabajadores',
                'status' => 500
            ], 500);
        }
    }

    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
                'dni' => 'required|string|unique:workers,dni',
                'services_id' => 'required|array',
                'bio' => 'nullable|string',
                'active' => 'nullable|boolean',
            ]);
            
            $user = User::create([
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'rol' => 'worker',
            ]);

            $worker = Worker::create([
                'user_id' => $user->id,
                'dni' => $validated['dni'],
                'services_id' => '{' . implode(',', $validated['services_id']) . '}',
                'bio' => $validated['bio'],
                'active' => $validated['active'] ?? false,
                'rating' => 0.00,
                'cantidad_ratings' => 0,
            ]);
    
            $worker->load('user');
    
            return response()->json([
                'data' => $worker, 'message' => 'Trabajador creado', 'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'data' => [], 'message' => 'Error al crear trabajador: ' . $e->getMessage(), 'status' => 500
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $worker = Worker::with('user')->where('user_id', $id)->firstOrFail();
    
            $worker_comments = Worker::with([
                'user',
                'services' => function ($query) {
                    $query->whereNotNull('client_comments')
                          ->whereNotNull('client_rating')
                          ->with(['client.user' => function ($query) {
                            $query->select('id', 'nombre', 'profile_photo');
                          }]);
                }
            ])->where('user_id', $id)->firstOrFail();

            $comments = $worker_comments->services->map(function ($service) {
                return [
                    'client_id' => $service->client->user_id,
                    'client_pfp' => $service->client->user->profile_photo,
                    'client_name' => $service->client->user->nombre,
                    'client_rating' => $service->client_rating,
                    'client_comments' => $service->client_comments,
                    'service_id' => $service->id,
                    'created_at' => $service->created_at->toDateTimeString(),
                ];
            });

            return response()->json([
                'data' => [
                    'worker' => $worker,
                    'comments' => $comments
                ],
                'message' => 'Trabajador encontrado junto a lo comentarios de los clientes',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener trabajador',
                'status' => 404
            ], 404);
        }
    }

    /**
     * Filter the list of resource
     */
    public function filter(Request $request)
    {
        try {
            $query = $request->input('q');
            $filters = $query ? explode(',', $query) : []; // Separo los filtros por coma
    
            $workersQuery = Worker::with('user');
    
            $workersQuery->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filter = trim($filter); // Le quito los espacios
                    
                    $query->orWhere(function ($subQuery) use ($filter) {
                        $subQuery->whereExists(function ($subSubQuery) use ($filter) {
                            $subSubQuery->selectRaw('1')
                                ->fromRaw('workers as w')
                                ->whereColumn('w.id', 'workers.id')
                                ->whereRaw('? = ANY(w.services_id)', [$filter]);
                        })
                        ->orWhereHas('user', function ($subSubQuery) use ($filter) {
                            $subSubQuery->where('nombre', 'ILIKE', "%{$filter}%")
                                        ->orWhere('email', 'ILIKE', "%{$filter}%");
                        });
                    });
                }
            });
    
            $workers = $workersQuery->get();
    
            return response()->json([
                'data' => $workers,
                'message' => 'Trabajadores filtrados',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al filtrar trabajadores',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Busca un trabajador disponible según habilidades y disponibilidad
     */
    public static function encontrarWorker(array $validated, string $previousWorkerId = null)
    {
        try {
            Log::info('Desde encontrarWorker: Starting worker search', [
                'validated' => $validated,
                'previousWorkerId' => $previousWorkerId
            ]);

            // 1: Parsear la hora de inicio y obtener el día y la hora
            Log::info('Desde encontrarWorker: Parsing start time and extracting day and hour', [
                'start_time' => $validated['start_time']
            ]);

            $serviceTypeId = $validated['service_type_id'];
            $startTime = Carbon::parse($validated['start_time'])->setTimezone('UTC');
            $endTime = Carbon::parse($validated['end_time'])->setTimezone('UTC');
            $diaBuscado = $startTime->day; // Ejemplo: 19
            $startHour = $startTime->format('H:i'); // Ejemplo: 08:00
            $endHour = $endTime->format('H:i'); // Ejemplo: 12:00

            $query = Worker::where('active', true)
                ->whereRaw('? = ANY(services_id)', [$serviceTypeId])
                ->whereRaw(
                "EXISTS (
                    SELECT 1
                    FROM json_array_elements(disponibilidad) AS elem
                    WHERE elem->>'dia' = ?
                    AND elem->>'horas' IS NOT NULL
                    AND json_typeof(elem->'horas') = 'array'
                    AND EXISTS (
                        SELECT 1
                        FROM json_array_elements(elem->'horas') AS hora
                        WHERE hora IS NOT NULL
                    )
                )",
                [$diaBuscado]
            );

            if ($previousWorkerId != null) {
                Log::info('Desde encontrarWorker: Excluding previous worker', ['previousWorkerId' => $previousWorkerId]);
                $query->where('id', '!=', $previousWorkerId);
            }

            $workers = $query->get();

            $trabajadorEncontrado = $workers->first(function ($worker) use ($diaBuscado, $startHour, $endHour) {
                return self::coincideDisponibilidad($worker, $diaBuscado, $startHour, $endHour);
            });

            Log::info('Desde encontrarWorker: Recogiendo datos', [
                'worker' => $trabajadorEncontrado
            ]);

            return $trabajadorEncontrado;
        } catch (Exception $e) {
            Log::error('Desde encontrarWorker: Error in worker search', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'validated' => $validated,
                'previousWorkerId' => $previousWorkerId
            ]);
            return null;
        }
    }

    public static function coincideDisponibilidad($worker, $diaBuscado, $startHour, $endHour)
    {
        $dia = collect($worker->disponibilidad)->firstWhere('dia', (string)$diaBuscado);
        Log::info('Disponibilidad del worker:', ['disponibilidad' => $worker->disponibilidad]);

        Log::info('Desde contains: Día actual, no entra', ['día' => $dia ?? 'dia no definido']);
        if (!$dia || !isset($dia['horas']) || empty($dia['horas'])) {
            return false;
        }

        return collect($dia['horas'])->contains(function ($rango) use ($startHour, $endHour) {
            if ($rango === null) {
                return false;
            }

            Log::info('Desde contains: Rango actual', ['rango' => $rango]);
            Log::info('Desde contains: Hora inicio y fin del servicio', [
                'startHour' => $startHour,
                'endHour' => $endHour
            ]);

            [$rangoInicio, $rangoFin] = explode('-', $rango);
            
            // Comparar directamente las horas como strings
            return $startHour >= $rangoInicio && $endHour <= $rangoFin;
        });
    }

    /**
    * Actualiza la disponibilidad del trabajador según el horario de un servicio aceptado
    */
    public static function updateDisponibilidadPorServicio(Service $service, $disponibilidad = [])
    {
        try {

            if (empty($disponibilidad)) {
                $worker = Worker::findOrFail($service->worker_id);
                $disponibilidad = is_string($worker->disponibilidad)
                ? json_decode($worker->disponibilidad, true)
                : $worker->disponibilidad;
                Log::info('Tipo de campo disponibilidad', [
                    'disponibilidad_type' => gettype($disponibilidad),
                    'disponibilidad_value' => $disponibilidad,
                ]);
            }

            // Obtengo el día del mes y los horarios del servicio
            $startTime = Carbon::parse($service->start_time);
            $endTime = Carbon::parse($service->end_time);
            $dayOfMonth = $startTime->day;
            $serviceStart = $startTime->format('H:i');
            $serviceEnd = $endTime->format('H:i');

            foreach ($disponibilidad as &$dia) {

                // Busco el día correspondiente en disponibilidad
                if ($dia['dia'] == $dayOfMonth) {
                    $newHoras = [];

                    // Cogemos las horas que hay en ese día
                    foreach ($dia['horas'] as $hora) {

                        // Si no hay nada, simplemente continua
                        if ($hora === null) {
                            $newHoras[] = $hora;
                            continue;
                        }

                        // Si hay, parseamos el rango de disponibilidad
                        [$horaStart, $horaEnd] = explode('-', $hora);
                        $horaStartTime = Carbon::createFromFormat('H:i', $horaStart);
                        $horaEndTime = Carbon::createFromFormat('H:i', $horaEnd);

                        // Si el servicio está completamente dentro del rango
                        if ($serviceStart >= $horaStart && $serviceEnd <= $horaEnd) {
                            // Dividir el rango si es necesario
                            if ($serviceStart > $horaStart) {
                                $diff = Carbon::createFromFormat('H:i', $serviceStart)->diffInMinutes($horaStartTime);
                                if ($diff > 60) { // Más de 1 hora
                                    $newHoras[] = "{$horaStart}-{$serviceStart}";
                                }
                            }
                            if ($serviceEnd < $horaEnd) {
                                $diff = $horaEndTime->diffInMinutes(Carbon::createFromFormat('H:i', $serviceEnd));
                                if ($diff > 60) { // Más de 1 hora
                                    $newHoras[] = "{$serviceEnd}-{$horaEnd}";
                                }
                            }
                        } elseif ($serviceStart < $horaStart && $serviceEnd > $horaStart && $serviceEnd <= $horaEnd) {
                            // Caso 1: Servicio empieza antes pero termina dentro
                            if ($serviceEnd < $horaEnd) {
                                $diff = $horaEndTime->diffInMinutes(Carbon::createFromFormat('H:i', $serviceEnd));
                                if ($diff > 60) { // Más de 1 hora
                                    $newHoras[] = "{$serviceEnd}-{$horaEnd}";
                                }
                            }
                        } elseif ($serviceStart >= $horaStart && $serviceStart < $horaEnd && $serviceEnd > $horaEnd) {
                            // Caso 2: Servicio empieza dentro pero termina después
                            if ($serviceStart > $horaStart) {
                                $diff = Carbon::createFromFormat('H:i', $serviceStart)->diffInMinutes($horaStartTime);
                                if ($diff > 60) { // Más de 1 hora
                                    $newHoras[] = "{$horaStart}-{$serviceStart}";
                                }
                            }
                        } elseif ($serviceStart <= $horaStart && $serviceEnd >= $horaEnd) {
                            // Caso 3: Servicio cubre todo el rango y más
                            continue;
                        } else {
                            // Mantener el rango si no hay solapamiento
                            $newHoras[] = $hora;
                        }
                    }
                    $dia['horas'] = $newHoras;
                    break;
                }
            }

            return $disponibilidad;
        } catch (Exception $e) {
            Log::error('Error al actualizar disponibilidad:', ['error' => $e->getMessage(), 'worker_id' => $service->worker_id, 'service_id' => $service->id]);
            return false;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updatePerfil(Request $request, $worker)
    {
        try {
            // Buscar el trabajador
            $worker = Worker::where('user_id', $worker)->firstOrFail();
            $user = $worker->user;

            // Validar los datos del JSON
            $validated = $request->validate([
                'nombre' => 'required|string|min:3',
                'email' => 'required|email',
                'telefono' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:255',
                'dni' => 'required|string|regex:/^\d{8}[A-Z]$/',
                'services_id' => 'required|array',
                'bio' => 'nullable|string',
                'active' => 'required|in:0,1',
                'profile_photo' => 'nullable|string',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
            ], [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico debe ser una dirección válida.',
                'telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
                'direccion.max' => 'La dirección no puede tener más de 255 caracteres.',
                'dni.required' => 'El DNI es obligatorio.',
                'dni.regex' => 'El DNI debe tener 8 dígitos seguidos de una letra mayúscula.',
                'services_id.required' => 'Debes seleccionar al menos un servicio.',
                'bio.string' => 'La biografía debe ser una cadena de texto.',
                'active.required' => 'El estado (activo) es obligatorio.',
                'active.in' => 'El estado debe ser 0 (inactivo) o 1 (activo).',
                'lat.required' => 'La latitud es obligatoria.',
                'lng.required' => 'La longitud es obligatoria.',
            ]);

            if (
                $request->profile_photo && $user->profile_photo 
            ) {
                UserController::deleteProfilePhoto($user->profile_photo);
            }

            $user->update([
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'telefono' => $validated['telefono'] ?? '',
                'direccion' => $validated['direccion'] ?? '',
                'profile_photo' => $validated['profile_photo'] ?? null,
                'latitude' => $request->lat,
                'longitude' => $request->lng,
            ]);

            // Actualizar el trabajador
            $worker->update([
                'dni' => $validated['dni'],
                'services_id' => '{' . implode(',', $validated['services_id']) . '}', // Guardar como array
                'bio' => $validated['bio'] ?? '',
                'active' => $validated['active'],
            ]);

            $worker = Worker::where('user_id', $worker->user_id)->with('user')->first();
            $worker->load('user');

            return response()->json([
                'data' => $worker,
                'message' => 'Perfil actualizado',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al actualizar perfil: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Actualiza el horario del trabajador
     */
    public function updateHorario(Request $request, $worker)
    {
        try {
            $worker = Worker::where('user_id', $worker)->firstOrFail();

            $validated = $request->validate([
                'horario_semanal' => 'required|array|size:7',
                'horario_semanal.*.dia' => 'required|integer|min:0|max:6',
                'horario_semanal.*.horas' => 'required|array|size:2',
                'horario_semanal.*.horas.*' => 'nullable|string'
            ]);

            $services = Service::where('worker_id', $worker->id)
            ->where('status', '!=', Estados::CANCELLED->value)
            ->where('status', '!=', Estados::REJECTED->value)
            ->where('status', '!=', Estados::COMPLETED->value)
            ->where('start_time', '>=', now()->startOfMonth())
            ->where('start_time', '<=', now()->endOfMonth())
            ->orderBy('start_time')
            ->get();

            // Generar disponibilidad base desde horario_semanal
            $today = now();
            $disponibilidad = $this->generateMonthlyAvailability($validated['horario_semanal'], $services, $today);

            // Ajustar disponibilidad con servicios existentes
            foreach ($services as $service) {
                $result = static::updateDisponibilidadPorServicio($service, $disponibilidad);
                if ($result === false) {
                    throw new Exception('Error al procesar servicio: ' . $service->id);
                }
                $disponibilidad = $result;
            }

            $worker->update([
                'horario_semanal' => json_encode($validated['horario_semanal']),
                'disponibilidad' => $disponibilidad
            ]);

            $worker->load('user');

            return response()->json([
                'data' => $worker,
                'message' => 'Horario actualizado',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::info('Error al actualizar el horario' . $e->getMessage());
            return response()->json([
                'data' => [],
                'message' => 'Error al actualizar horario',
                'status' => 500
            ], 500);
        }
    }

    
    public function toggleWorkerActivo($worker)
    {
        try {
            // Buscar el trabajador
            $worker = Worker::where('user_id', $worker)->firstOrFail();

            // Cambiar el estado activo (toggle)
            $newStatus = !$worker->active;

            // Actualizar el trabajador
            $worker->update([
                'active' => $newStatus
            ]);

            // Recargar el trabajador con la relación user
            $worker = Worker::where('user_id', $worker->user_id)->with('user')->first();

            return response()->json([
                'data' => $worker,
                'message' => $newStatus ? 'Trabajador activado correctamente' : 'Trabajador desactivado correctamente',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al actualizar perfil: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    private function generateMonthlyAvailability(array $horarioSemanal, Collection $services, Carbon $today): array
    {
        $disponibilidad = [];
        $daysInMonth = $today->daysInMonth;
        $firstDayOfMonth = $today->copy()->startOfMonth()->dayOfWeekIso - 1; // 0 (Lunes) a 6 (Domingo)

        for ($dia = 1; $dia <= $daysInMonth; $dia++) {
            $dayOfWeekIndex = ($firstDayOfMonth + ($dia - 1)) % 7;
            $weeklyDay = collect($horarioSemanal)->firstWhere('dia', $dayOfWeekIndex);
            $horas = $weeklyDay['horas'] ?? [null, null];

            // Añadir servicios fuera del horario como excepciones
            $dayDate = $today->copy()->startOfMonth()->addDays($dia - 1);
            $dayServices = $services->filter(function ($service) use ($dayDate) {
                return Carbon::parse($service->start_time)->isSameDay($dayDate);
            });

            $exceptionHours = [];
            foreach ($dayServices as $service) {
                $serviceStart = Carbon::parse($service->start_time)->format('H:i');
                $serviceEnd = Carbon::parse($service->end_time)->format('H:i');
                $isCovered = false;

                foreach ($horas as $hour) {
                    if ($hour === null) continue;
                    [$hourStart, $hourEnd] = explode('-', $hour);
                    if ($serviceStart >= $hourStart && $serviceEnd <= $hourEnd) {
                        $isCovered = true;
                        break;
                    }
                }

                if (!$isCovered) {
                    $exceptionHours[] = "{$serviceStart}-{$serviceEnd}";
                }
            }

            $disponibilidad[] = [
                'dia' => $dia,
                'horas' => array_merge(array_slice($horas, 0, 2 - count($exceptionHours)), $exceptionHours)
            ];
        }

        return $disponibilidad;
    }

    /**
     * Obtiene la disponibilidad y los servicios del trabajador para un mes específico
     */
    public function getHorario(string $worker)
    {
        try {
            $worker = Worker::where('user_id', $worker)->firstOrFail();

            return response()->json([
                'data' => $worker->horario_semanal,
                'message' => 'Horario del trabajador obtenido correctamente',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el horario',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $worker = Worker::findOrFail($id);
            $user = $worker->user; // Esto para eliinar tambien el user
    
            $worker->delete();
            $user->delete();
    
            return response()->json([
                'data' => [], 'message' => 'Trabajador eliminado', 'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al eliminar trabajador',
                'status' => 500
            ], 500);
        }
    }
}
