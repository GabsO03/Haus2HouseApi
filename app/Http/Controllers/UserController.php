<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Worker;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Notifications\ServiceAssignedNotification;
use App\Notifications\PasswordResetCodeNotification;

class UserController extends Controller
{
    
    /**
     * Notifica a cliente y trabajador que ya asginaron su servicio
     */
    public static function notifyUsers(Service $service, Worker $worker)
    {
        $service->client->user->notify(new ServiceAssignedNotification($service));
        $worker->user->notify(new ServiceAssignedNotification($service));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = User::findOrFail($id);
    
            return response()->json([
                'data' => $user, 'message' => 'Usuario encontrado', 'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Error al obtener usuario',
                'status' => 404
            ], 404);
        }
    }

    
    /**
     * Función para mandar un código al email del user en caso
     * de que la contraseña actual coincida con la que mandó
     */
    public function changePasswordAuthorization(Request $request, $user)
    {
        try {
            Log::info('Inicia el intento de cambio de contraseña');
            
            // Validate request
            $request->validate([
                'current_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
                'new_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            ]);

            Log::info('Busca al usuario');
            $user = User::findOrFail($user);

            Log::info('Verifica la contraseña');
            $coincide = Hash::check($request->current_password, $user->password);

            if ($coincide) {
                Log::info('Realiza el cambio');
                $user->password = $request->new_password;
            }

            return response()->json([
                'data' => $coincide,
                'status' => 200
            ]);

        } catch (Exception $e) {
            return response()->json([
                'data' => false,
                'mensaje' => 'Error al cambiar la contraseña' . $e->getMessage(),
                'status' => 404
            ], 404);
        }
    }

    public function uploadProfilePhoto(Request $request)
    {

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $_FILES['profile_photo']['name'];

            try {
                $file->move(public_path('user_pfp'), $fileName);
                return response()->json($fileName);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Error al guardar el archivo'], 500);
            }
        } else {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

    }

    // ESTO ES UN DESCARTE PARA CUANDO QUIERA VERIFICAR EL EMAIL
    // public function changePasswordAuthorization(Request $request, $user)
    // {
    //     try {
    //         // Validate request
    //         $request->validate([
    //             'password' => 'required|string',
    //         ]);

    //         // Find user
    //         $user = User::findOrFail($user);

    //         // Check if provided password matches
    //         if (!Hash::check($request->password, $user->password)) {
    //             return response()->json([
    //                 'message' => 'La contraseña actual es incorrecta',
    //                 'status' => 401
    //             ], 401);
    //         }

    //         // Generate random 6-digit code
    //         $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    //         // Store code in password_reset_tokens table
    //         DB::table('password_reset_tokens')->updateOrInsert(
    //             ['email' => $user->email],
    //             [
    //                 'token' => $code,
    //                 'created_at' => now()
    //             ]
    //         );

    //         // Send notification
    //         $user->notify(new PasswordResetCodeNotification($code));

    //         return response()->json([
    //             'message' => 'Código de verificación enviado al correo',
    //             'status' => 200
    //         ]);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Error al procesar la solicitud',
    //             'status' => 500
    //         ], 500);
    //     }
    // }


}
