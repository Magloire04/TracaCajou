<?php

namespace Database\Factories;

use App\Enums\LotStatut;
use App\Models\Cooperative;
use App\Models\Lot;
use App\Models\Producteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        $coop      = Cooperative::factory()->create();
        $poids     = round($this->faker->numberBetween(100, 1000) + $this->faker->randomFloat(2, 0, 1), 2);
        $prix      = $this->faker->randomElement([265, 270, 275, 280]);
        return [
            'code'           => strtoupper($coop->code) . 'L' . now()->format('YmdHis') . $this->faker->numerify('##'),
            'producteur_id'  => Producteur::factory()->create(['cooperative_id' => $coop->id])->id,
            'cooperative_id' => $coop->id,
            'poids_kg'       => $poids,
            'humidite_pct'   => $this->faker->randomFloat(1, 5, 12),
            'prix_kg_fcfa'   => $prix,
            'montant_fcfa'   => round($poids * $prix, 2),
            'date_pesee'     => now()->toDateString(),
            'statut'         => LotStatut::Enregistre,
        ];
    }
}
