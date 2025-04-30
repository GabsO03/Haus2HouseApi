<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    public static function encontrarWorker(array $validated, int $previousWorkerId = null) // Ese id por si el primero lo cancela, así no se le asigna de nuevo
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
                'password' => bcrypt($validated['password']),
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
            $worker = Worker::with('user')->findOrFail($id);
    
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
                'disponibilidad' => 'nullable|array',
                'bio' => 'nullable|string',
                'active' => 'nullable|boolean',
            ]);
            
            $user->update([
                'nombre' => $validated['nombre'],
                'email' => $validated['email'],
                'password' => isset($validated['password']) ? bcrypt($validated['password']) : $user->password,
            ]);

            $worker->update([
                'dni' => $validated['dni'],
                'services_id' => json_encode($validated['services_id']),
                'disponibilidad' => $validated['disponibilidad'] ? json_encode($validated['disponibilidad']) : null,
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
