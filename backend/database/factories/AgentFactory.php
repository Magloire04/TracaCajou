<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'prenom'         => $this->faker->firstName(),
            'nom'            => $this->faker->lastName(),
            'email'          => $this->faker->unique()->safeEmail(),
            'role'           => 'agent',
            'password_hash'  => Hash::make('password'),
            'cooperative_id' => Cooperative::factory(),
        ];
    }
}
