<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $attributes = $request->only(['email', 'password']);

            if (!Auth::attempt($attributes)) {
                throw ValidationException::withMessages([
                    'email' => 'Esas credenciales no son correctas.',
                ]);
            }

            $user = Auth::user();
            $userData = User::find($user->id);
            $id = '';

            // Dependiendo del rol, incluir el id del client o worker
            if ($userData->rol === 'client') {
                $userData->load('client');
                $id = $userData->client->id;
            } elseif ($userData->rol === 'worker') {
                $userData->load('worker');
                $id = $userData->worker->id;
            }

            return response()->json([
                'data' => [
                    'user' => $user, 
                    'id' => $id
                ],
                'message' => 'Inicio de sesión exitoso',
                'status' => 200,
            ]);
        
        } catch (ValidationException $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error de validación: ' . $e->getMessage(),
                'status' => 422,
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al iniciar sesión',
                'status' => 401,
            ], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        try {
            Auth::logout();

            return response()->json([
                'data' => [],
                'message' => 'Sesión cerrada correctamente',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al cerrar sesión',
                'status' => 500,
            ], 500);
        }
    }
}
