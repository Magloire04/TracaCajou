<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_agent_peut_se_connecter_avec_les_bons_identifiants(): void
    {
        $coop  = Cooperative::factory()->create(['code' => 'AGPK']);
        $agent = Agent::factory()->create([
            'cooperative_id' => $coop->id,
            'email'          => 'agent@test.bj',
            'password_hash'  => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'agent@test.bj',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $agent->id)
            ->assertJsonPath('data.cooperative_code', 'AGPK')
            ->assertJsonMissingPath('data.password_hash');
    }

    public function test_retourne_401_avec_des_identifiants_invalides(): void
    {
        Agent::factory()->create(['email' => 'agent@test.bj', 'password_hash' => Hash::make('correct')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'agent@test.bj', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_retourne_422_si_email_absent(): void
    {
        $this->postJson('/api/v1/auth/login', ['password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_un_agent_authentifie_peut_se_deconnecter(): void
    {
        $agent = Agent::factory()->create();

        $this->actingAs($agent, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(204);
    }
}
