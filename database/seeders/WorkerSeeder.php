<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        // Crear 10 trabajadores con sus usuarios
        for ($i = 0; $i < 20; $i++) {
            // Crear un usuario con coordenadas en Jaén
            $user = User::factory()->create();

            // Crear un trabajador vinculado al usuario
            Worker::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}