<?php

namespace Tests\Feature;

use App\Enums\CertificatStatut;
use App\Enums\LotStatut;
use App\Models\Agent;
use App\Models\Certificat;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\CertificatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Correction 1 : le mock doit mettre à jour la DB (statut + certificat)
        // pour que refresh()->load() retourne les bonnes données dans le contrôleur.
        $this->mock(CertificatService::class, function ($mock) {
            $mock->shouldReceive('generateForLot')
                ->andReturnUsing(function (Lot $lot) {
                    $lot->update(['statut' => LotStatut::Certifie]);

                    return Certificat::create([
                        'lot_id'       => $lot->id,
                        'public_uuid'  => Str::ulid()->toString(),
                        'payload_hash' => hash('sha384', 'test-payload'),
                        'signature'    => base64_encode('fake-signature'),
                        'statut'       => CertificatStatut::Certifie,
                        'emis_le'      => now(),
                    ]);
                });
        });
    }

    public function test_cree_un_lot_et_calcule_montant_fcfa_cote_serveur(): void
    {
        $agent      = Agent::factory()->create();
        $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $producteur->id,
                'poids_kg'      => 400.0,
                'humidite_pct'  => 7.5,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => '2026-06-28',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'certifie')
            ->assertJsonStructure(['data' => ['id', 'code', 'montant_fcfa', 'statut', 'certificat']]);

        // montant_fcfa calculé côté serveur : 400 × 270 = 108 000
        $this->assertEquals(108000.0, $response->json('data.montant_fcfa'));
        $this->assertEquals(108000.0, Lot::first()->montant_fcfa);
    }

    public function test_ignore_montant_fcfa_envoye_par_le_client(): void
    {
        $agent      = Agent::factory()->create();
        $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

        $response = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $producteur->id,
                'poids_kg'      => 100.0,
                'humidite_pct'  => 8.0,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => '2026-06-28',
                'montant_fcfa'  => 999999, // doit être ignoré
            ]);

        $response->assertStatus(201);

        // montant_fcfa ignoré du client : 100 × 270 = 27 000 (pas 999 999)
        $this->assertEquals(27000.0, $response->json('data.montant_fcfa'));
        $this->assertEquals(27000.0, Lot::first()->montant_fcfa);
    }

    public function test_retourne_422_si_poids_inferieur_ou_egal_a_zero(): void
    {
        $agent      = Agent::factory()->create();
        $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $producteur->id,
                'poids_kg'      => 0,
                'humidite_pct'  => 7.0,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => '2026-06-28',
            ])
            ->assertStatus(422);
    }

    public function test_retourne_422_si_humidite_hors_plage(): void
    {
        $agent      = Agent::factory()->create();
        $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $producteur->id,
                'poids_kg'      => 100.0,
                'humidite_pct'  => 105,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => '2026-06-28',
            ])
            ->assertStatus(422);
    }

    public function test_retourne_403_si_producteur_appartient_a_une_autre_cooperative(): void
    {
        $agent           = Agent::factory()->create();
        $autreProducteur = Producteur::factory()->create(); // crée sa propre coopérative

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $autreProducteur->id,
                'poids_kg'      => 100.0,
                'humidite_pct'  => 7.0,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => '2026-06-28',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PRODUCTEUR_WRONG_COOPERATIVE');
    }

    public function test_liste_les_lots_pagines_de_la_cooperative(): void
    {
        $agent = Agent::factory()->create();
        Lot::factory()->count(5)->create(['cooperative_id' => $agent->cooperative_id]);

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots?limit=3")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 5);
    }
}
