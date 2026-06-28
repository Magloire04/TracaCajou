<?php

namespace Database\Factories;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;

class CooperativeFactory extends Factory
{
    protected $model = Cooperative::class;

    public function definition(): array
    {
        return [
            'nom'     => 'Coopérative ' . strtoupper($this->faker->lexify('????')),
            'code'    => strtoupper($this->faker->unique()->lexify('???')),
            'commune' => $this->faker->randomElement(['Kétou','Savè','Tchaourou','Parakou','Bohicon']),
        ];
    }
}
