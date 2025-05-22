<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        // Centro en Jaén, Jaén (España)
        $baseLat = 37.7692;
        $baseLng = -3.7875;
        // Radio de 20 km (aprox. 0.18 grados)
        $radius = 0.18;

        // Generar coordenadas aleatorias dentro del radio
        $angle = rand(0, 360) * pi() / 180; // Ángulo aleatorio en radianes
        $distance = sqrt(rand(0, 10000) / 10000) * $radius; // Distancia aleatoria
        $lat = $baseLat + ($distance * cos($angle));
        $lng = $baseLng + ($distance * sin($angle));

        return [
            'id' => (string) Str::uuid(),
            'nombre' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Usu1234-', // Sin Hash::make, el modelo lo hashea
            'rol' => 'worker',
            'latitude' => $lat,
            'longitude' => $lng,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}