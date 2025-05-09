<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $clients = Client::with('user')->get();
            return response()->json([
                'data' => $clients,
                'message' => 'Clientes obtenidos correctamente',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener clientes: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $client = Client::with('user')->where('user_id', $id)->get();

            return response()->json([
                'data' => $client,
                'message' => 'Cliente encontrado',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener cliente: ' . $e->getMessage(),
                'status' => 404,
            ], 404);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $client = Client::findOrFail($id);
            $user = $client->user;

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8',
                'telefono' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:255',
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
                'telefono' => $validated['telefono'] ?? $user->telefono,
                'direccion' => $validated['direccion'] ?? $user->direccion,
                'profile_photo' => $profilePhotoPath,
            ]);

            $client->load('user');

            return response()->json([
                'data' => $client,
                'message' => 'Cliente actualizado',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al actualizar cliente: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $client = Client::with('user')->findOrFail($id);
            $user = $client->user;

            $client->delete();
            $user->delete();

            return response()->json([
                'data' => [],
                'message' => 'Cliente eliminado',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al eliminar cliente: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }
}
