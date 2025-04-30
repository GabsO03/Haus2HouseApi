<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            return response()->json([
                'data' => $user,
                'message' => 'Inicio de sesión exitoso',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al iniciar sesión: ' . $e->getMessage(),
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
                'message' => 'Error al cerrar sesión: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }
}
