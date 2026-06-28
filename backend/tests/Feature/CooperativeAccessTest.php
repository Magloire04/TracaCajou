<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CooperativeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_agent_peut_acceder_a_sa_propre_cooperative(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs")
            ->assertStatus(200);
    }

    public function test_un_agent_ne_peut_pas_acceder_a_une_autre_cooperative_anti_idor(): void
    {
        $agent     = Agent::factory()->create();
        $autreCoop = Cooperative::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->getJson("/api/v1/cooperatives/{$autreCoop->id}/producteurs")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'COOPERATIVE_ACCESS_DENIED');
    }
}
