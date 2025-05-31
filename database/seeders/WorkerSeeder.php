<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        $createdCount = 0;

        for ($i = 0; $i < 100; $i++) {
            try {
                // Crear un usuario con coordenadas en Jaén
                $user = User::factory()->create();

                // Crear un trabajador vinculado al usuario
                Worker::factory()->create([
                    'user_id' => $user->id,
                ]);

                $createdCount++;
                Log::info("Creado trabajador #$createdCount con user_id: {$user->id}");
            } catch (\Exception $e) {
                Log::error("Error al crear trabajador #$i: " . $e->getMessage());
            }
        }

        Log::info("Total trabajadores creados: $createdCount");
    }
}