<?php

namespace App\Http\Controllers;

use Exception;
use Google\Client;
use App\Models\User;
use App\Models\Worker;
use App\Models\Service;
use Google\Service\Drive;
use Illuminate\Http\Request;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;
use App\Notifications\ServiceAssignedNotification;

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
            // Validate request
            $request->validate([
                'current_password' => ['required'],
                'new_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            ]);

            $user = User::findOrFail($user);

            $coincide = Hash::check($request->current_password, $user->password);

            if ($coincide) {
                $user->update(['password' => $request->new_password]);
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

    public static function deleteProfilePhoto($fileId)
    {
        try {
            $client = new Client();
            $client->setApplicationName('Haus2HouseApi');
            $client->setScopes([Drive::DRIVE_FILE]);
            $client->setAuthConfig(storage_path('app/credentials.json'));
            $client->setAccessType('offline');

            $service = new Drive($client);

            // Borra el archivo usando el file_id
            $service->files->delete($fileId);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al eliminar foto de Google Drive: ' . $e->getMessage());
            return false;
        }
    }

    public function uploadProfilePhoto(Request $request)
    {
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $fileName = time() . '_' . $file->getClientOriginalName();

            try {
                $client = new Client();
                $client->setApplicationName('Haus2HouseApi');
                $client->setScopes([Drive::DRIVE_FILE]);
                $client->setAuthConfig(storage_path('app/credentials.json'));
                $client->setAccessType('offline');

                $service = new Drive($client);

                $driveFile = new DriveFile();
                $driveFile->setName($fileName);
                $driveFile->setParents([env('GOOGLE_DRIVE_ID')]);

                $content = file_get_contents($file->getRealPath());
                $result = $service->files->create($driveFile, [
                    'data' => $content,
                    'mimeType' => $file->getMimeType(),
                    'uploadType' => 'multipart'
                ]);

                $permission = new Permission([
                    'type' => 'anyone',
                    'role' => 'reader',
                ]);
                $service->permissions->create($result->id, $permission);

                return response()->json($result->id);
            } catch (\Exception $e) {
                Log::error('Error al subir a Google Drive: ' . $e->getMessage());
                return response()->json(['error' => 'Error al guardar el archivo: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
    }

    public function getProfilePhoto ($fileId) {
        $url = "https://drive.google.com/uc?export=view&id={$fileId}";
        $response = Http::get($url);
        return response($response->body())->header('Content-Type', 'image/jpeg'); // Ajusta el tipo según la imagen
    }

}
