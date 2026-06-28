<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducteurTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_agent_peut_enroler_un_producteur_dans_sa_cooperative(): void
    {
        $agent = Agent::factory()->create();

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", [
                'prenom'       => 'Kofi',
                'nom'          => 'Adjovi',
                'sexe'         => 'M',
                'localite'     => 'Kétou-Centre',
                'consentement' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.prenom', 'Kofi')
            ->assertJsonMissingPath('data.localite'); // APDP : pas exposé dans la réponse

        $this->assertSame(1, Producteur::count());
        $this->assertNotNull(Producteur::first()->consentement_le);
    }

    public function test_retourne_422_si_prenom_manquant(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", [
                'nom'          => 'Adjovi',
                'consentement' => true,
            ])
            ->assertStatus(422);
    }

    public function test_retourne_422_si_consentement_absent(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", [
                'prenom' => 'Kofi', 'nom' => 'Adjovi', 'consentement' => false,
            ])
            ->assertStatus(422);
    }

    public function test_liste_les_producteurs_pagines_de_la_cooperative(): void
    {
        $agent = Agent::factory()->create();
        Producteur::factory()->count(5)->create(['cooperative_id' => $agent->cooperative_id]);

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs?limit=3");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.limit', 3);
    }

    public function test_ne_retourne_pas_les_donnees_personnelles_completes_dans_la_liste(): void
    {
        $agent = Agent::factory()->create();
        Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

        $response = $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs");

        $response->assertJsonMissingPath('data.0.localite')
            ->assertJsonMissingPath('data.0.consentement_le');
    }

    public function test_anonymise_un_producteur_a_la_suppression_apdp(): void
    {
        $agent      = Agent::factory()->create();
        $producteur = Producteur::factory()->create([
            'cooperative_id' => $agent->cooperative_id,
            'consentement_le' => now(),
        ]);

        $this->actingAs($agent, 'sanctum')
            ->deleteJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs/{$producteur->id}")
            ->assertStatus(204);

        $producteur->refresh();
        $this->assertSame('[supprimé]', $producteur->prenom);
        $this->assertSame('[supprimé]', $producteur->nom);
        $this->assertNull($producteur->sexe);
        $this->assertNull($producteur->localite);
        $this->assertNotNull($producteur->consentement_le); // conservé pour traçabilité légale
    }

    public function test_refuse_anonymisation_producteur_autre_cooperative(): void
    {
        $agent           = Agent::factory()->create();
        $autreProducteur = Producteur::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->deleteJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs/{$autreProducteur->id}")
            ->assertStatus(404); // anti-IDOR : n'existe pas dans cette coopérative
    }
}
