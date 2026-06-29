<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProducteurFactory extends Factory
{
    protected $model = Producteur::class;

    public function definition(): array
    {
        $coop = Cooperative::factory()->create();
        return [
            'code'            => strtoupper($coop->code) . 'P' . now()->format('YmdHis') . $this->faker->unique()->numerify('###'),
            'prenom'          => $this->faker->firstName(),
            'nom'             => $this->faker->lastName(),
            'sexe'            => $this->faker->randomElement(['M', 'F']),
            'localite'        => $this->faker->city(),
            'cooperative_id'  => $coop->id,
            'consentement_le' => now(),
        ];
    }
}
