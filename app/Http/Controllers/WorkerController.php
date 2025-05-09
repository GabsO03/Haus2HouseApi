<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Worker;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
                'message' => 'Error al obtener trabajadores: ' . $e->getMessage(),
                'status' => 500
            ], 500);
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
                                ->whereRaw("jsonb_typeof(w.services_id) = 'array'")
                                ->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(w.services_id) as elem WHERE UPPER(elem) LIKE UPPER(?))", ["%{$filter}%"]);
                        })
                        ->orWhere(function ($subSubQuery) use ($filter) {
                            if (is_numeric($filter)) {
                                $subSubQuery->where('rating', '>=', floatval($filter));
                            }
                        })
                        ->orWhereHas('user', function ($subSubQuery) use ($filter) {
                            $subSubQuery->where('nombre', 'ILIKE', "%{$filter}%"); // ILIKE es case-insensitive en PostgreSQL
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
                'message' => 'Error al filtrar trabajadores: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Busca un trabajador disponible según habilidades y disponibilidad
     */
    public static function encontrarWorker(array $validated, string $previousWorkerId = null) // Ese id por si el primero lo cancela, así no se le asigna de nuevo
    {
        $startTime = Carbon::parse($validated['start_time'])->setTimezone('UTC');
        $dayOfMonth = $startTime->day; // Ejemplo: 19
        $startHour = $startTime->format('H:i'); // Ejemplo: 08:00

        // Busco un trabajador que se dedique a lo que pide la solicitud y que este disponible en ese momento
        $query = Worker::where('active', true)
            ->whereRaw("services_id @> ?", [json_encode([$validated['service_type_id']])])
            ->whereRaw("EXISTS (
                SELECT 1
                FROM jsonb_array_elements(disponibilidad) AS d,
                    jsonb_array_elements(d->'horas') AS h(hora)
                WHERE (d->>'dia')::int = ?
                AND ?::time BETWEEN split_part(h.hora #>> '{}', '-', 1)::time AND split_part(h.hora #>> '{}', '-', 2)::time
            )", [$dayOfMonth, $startHour]);

        if ($previousWorkerId) {
            $query->where('id', '!=', $previousWorkerId); // Para que no sea al trabajador que canceló
        }

        $trabajadorEncontrado = Worker::with('user')->find($query->first()->id);

        return $trabajadorEncontrado;
    }

    /**
    * Actualiza la disponibilidad del trabajador según el horario de un servicio aceptado
    */
    public static function updateDisponibilidad(Service $service)
    {
        try {
            $worker = Worker::findOrFail($service->worker_id);
            $disponibilidad = json_decode($worker->disponibilidad, true);

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

            // Actualizar disponibilidad
            $worker->disponibilidad = json_encode($disponibilidad);
            $worker->save();

            Log::info('Disponibilidad actualizada para trabajador:', ['worker_id' => $worker->id, 'service_id' => $service->id]);

            return true;
        } catch (Exception $e) {
            Log::error('Error al actualizar disponibilidad:', ['error' => $e->getMessage(), 'worker_id' => $service->worker_id, 'service_id' => $service->id]);
            return false;
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
                'password' => 'required|string|min:8',
                'dni' => 'required|string|unique:workers,dni',
                'services_id' => 'required|array',
                'disponibilidad' => 'required|array|min:28|max:31',
                'disponibilidad.*.dia' => 'required|integer|between:1,31',
                'disponibilidad.*.horas' => 'present|array',
                'disponibilidad.*.horas.*' => 'nullable|string',
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
                'services_id' => json_encode($validated['services_id']),
                'disponibilidad' => $validated['disponibilidad'] ? json_encode($validated['disponibilidad']) : null,
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
            $worker = Worker::with('user')->where('user_id', $id)->get();
            // $worker = Worker::with('user')->findOrFail($id);
    
            return response()->json([
                'data' => $worker, 'message' => 'Trabajador encontrado', 'status' => 200
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
     * Update the specified resource in storage.
     */
    public function updatePerfil(Request $request, $worker)
    {
        try {
            $worker = Worker::find($worker);
            $user = $worker->user;

            
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8',
                'dni' => 'required|string|unique:workers,dni,' . $worker->id,
                'services_id' => 'required|array',
                'bio' => 'nullable|string',
                'active' => 'nullable|boolean',
                'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Solo actualiza la foto de perfil si se subió una nueva
            $profilePhotoPath = $user->profile_photo;

            if ($request->hasFile('profile_photo') && $request->file('profile_photo')->isValid()) {
                if ($user->profile_photo) {
                    Storage::disk('public')->delete($user->profile_photo);
                }
                $profilePhotoPath = $request->file('profile_photo')->store('profile_photos', 'public');
            }
            
            $user->update([
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'password' => isset($validated['password']) ? bcrypt($validated['password']) : $user->password,
                'profile_photo' => $profilePhotoPath
            ]);

            $worker->update([
                'dni' => $validated['dni'],
                'services_id' => json_encode($validated['services_id']),
                'bio' => $validated['bio'],
                'active' => $validated['active'] ?? false,
            ]);

            $worker->load('user');

            return response()->json([
                'data' => $worker, 'message' => 'Perfil actualizado', 'status' => 200
            ]);
        } catch (Exception $e) {
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
            $worker = Worker::find($worker);

            $validated = $request->validate([
                'disponibilidad' => 'required|array|min:28|max:31',
                'disponibilidad.*.dia' => 'required|integer|between:1,31',
                'disponibilidad.*.horas' => 'present|array',
                'disponibilidad.*.horas.*' => 'nullable|string'
            ]);

            $worker->update([
                'disponibilidad' => json_encode($validated['disponibilidad']),
            ]);

            $worker->load('user');

            return response()->json([
                'data' => $worker, 'message' => 'Horario actualizado', 'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al actualizar horario: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function getHorario($worker) {
        try {
            $worker = Worker::findOrFail($worker);
    
            return response()->json([
                'data' => $worker->disponibilidad, 'message' => 'Horario del trabajador', 'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener el horario',
                'status' => 404
            ], 404);
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
