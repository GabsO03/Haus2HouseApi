<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::create([
            'id' => (string) Str::uuid(),
            'nombre' => 'Juan Pérez',
            'email' => 'juan.perez@example.com',
            'password' => Hash::make('Usu1234-'),
            'rol' => 'worker',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user2 = User::create([
            'id' => (string) Str::uuid(),
            'nombre' => 'María Gómez',
            'email' => 'maria.gomez@example.com',
            'password' => Hash::make('Usu1234-'),
            'rol' => 'worker',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $horarioSemanal = [
            ['day' => 0, 'horas' => ['09:00-12:00', '13:00-18:00']],
            ['day' => 1, 'horas' => ['09:00-12:00', '13:00-18:00']],
            ['day' => 2, 'horas' => [null, '13:00-18:00']],
            ['day' => 3, 'horas' => ['09:00-12:00', '13:00-18:00']],
            ['day' => 4, 'horas' => ['09:00-12:00', '13:00-18:00']],
            ['day' => 5, 'horas' => [null, null]],
            ['day' => 6, 'horas' => [null, null]],
        ];

        $disponibilidad = [];
        for ($i = 0; $i < 30; $i++) {
            $date = now()->addDays($i)->toDateString();
            $dayOfWeek = now()->addDays($i)->dayOfWeek;
            $disponibilidad[] = [
                'date' => $date,
                'horas' => $horarioSemanal[$dayOfWeek]['horas'],
            ];
        }

        Worker::create([
            'user_id' => $user1->id,
            'dni' => '12345678A',
            'services_id' => json_encode([1, 2]),
            'horario_semanal' => json_encode($horarioSemanal),
            'disponibilidad' => json_encode($disponibilidad),
            'bio' => 'Trabajador experimentado en limpieza y mantenimiento.',
            'active' => true,
            'rating' => 0.00,
            'cantidad_ratings' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Worker::create([
            'user_id' => $user2->id,
            'dni' => '87654321B',
            'services_id' => json_encode([3, 4]),
            'horario_semanal' => json_encode($horarioSemanal),
            'disponibilidad' => json_encode($disponibilidad),
            'bio' => 'Especialista en jardinería y reparaciones.',
            'active' => true,
            'rating' => 0.00,
            'cantidad_ratings' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}