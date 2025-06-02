<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        $horariosSemanales = [
            // Trabajador 1: Disponible casi todo el día, lunes a viernes
            [
                ['dia' => 0, 'horas' => ['07:00-20:00']], // Lunes
                ['dia' => 1, 'horas' => ['07:00-20:00']], // Martes
                ['dia' => 2, 'horas' => ['07:00-20:00']], // Miércoles
                ['dia' => 3, 'horas' => ['07:00-20:00']], // Jueves
                ['dia' => 4, 'horas' => ['07:00-20:00']], // Viernes
                ['dia' => 5, 'horas' => ['09:00-14:00']], // Sábado
                ['dia' => 6, 'horas' => [null]],          // Domingo
            ],
            // Trabajador 2: Mañanas y noches, toda la semana
            [
                ['dia' => 0, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 1, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 2, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 3, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 4, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 5, 'horas' => ['06:00-12:00', '18:00-23:00']],
                ['dia' => 6, 'horas' => ['06:00-12:00', '18:00-23:00']],
            ],
            // Trabajador 3: Turnos variados, con descansos intermedios
            [
                ['dia' => 0, 'horas' => ['08:00-14:00', '16:00-22:00']],
                ['dia' => 1, 'horas' => ['08:00-14:00', '16:00-22:00']],
                ['dia' => 2, 'horas' => ['08:00-14:00', '16:00-22:00']],
                ['dia' => 3, 'horas' => ['10:00-16:00']],
                ['dia' => 4, 'horas' => ['10:00-16:00']],
                ['dia' => 5, 'horas' => ['09:00-17:00']],
                ['dia' => 6, 'horas' => ['09:00-17:00']],
            ],
            // Trabajador 4: Solo mañanas, lunes a domingo
            [
                ['dia' => 0, 'horas' => ['07:00-13:00']],
                ['dia' => 1, 'horas' => ['07:00-13:00']],
                ['dia' => 2, 'horas' => ['07:00-13:00']],
                ['dia' => 3, 'horas' => ['07:00-13:00']],
                ['dia' => 4, 'horas' => ['07:00-13:00']],
                ['dia' => 5, 'horas' => ['07:00-13:00']],
                ['dia' => 6, 'horas' => ['07:00-13:00']],
            ],
            // Trabajador 5: Disponibilidad total, lunes a viernes
            [
                ['dia' => 0, 'horas' => ['00:00-23:59']],
                ['dia' => 1, 'horas' => ['00:00-23:59']],
                ['dia' => 2, 'horas' => ['00:00-23:59']],
                ['dia' => 3, 'horas' => ['00:00-23:59']],
                ['dia' => 4, 'horas' => ['00:00-23:59']],
                ['dia' => 5, 'horas' => [null]],
                ['dia' => 6, 'horas' => [null]],
            ],
        ];
        $horarioSemanal = $this->faker->randomElement($horariosSemanales);
        $disponibilidad = [];
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now()->addDays($i);
            $dayOfMonth = $date->day;
            $dayOfWeek = $date->dayOfWeek;
            $disponibilidad[] = [
                'dia' => $dayOfMonth,
                'horas' => $horarioSemanal[$dayOfWeek]['horas'],
            ];
        }

        $services = range(1, 7);

        return [
            'user_id' => null, // Se asignará en el seeder
            'dni' => $this->faker->unique()->regexify('[0-9]{8}[A-Z]'), // DNI más robusto
            'services_id' => '{' . implode(',', $services) . '}',
            'horario_semanal' => json_encode($horarioSemanal),
            'disponibilidad' => $disponibilidad,
            'bio' => $this->faker->sentence(10),
            'active' => $this->faker->boolean(90),
            'rating' => 0.00,
            'cantidad_ratings' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}