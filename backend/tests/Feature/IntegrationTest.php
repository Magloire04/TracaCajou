<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Génère une paire de clés ECDSA P-384 pour les tests.
     * Utilise openssl_pkey_new() si disponible, sinon CLI (fallback WampServer Windows).
     */
    private function generateTestKeyPair(string $privPath, string $pubPath): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'secp384r1',
        ]);

        if ($res !== false) {
            openssl_pkey_export($res, $privateKeyPem);
            $publicKeyPem = openssl_pkey_get_details($res)['key'];
            file_put_contents($privPath, $privateKeyPem);
            file_put_contents($pubPath, $publicKeyPem);
            return;
        }

        // Fallback Windows WampServer : appel CLI openssl
        $candidates = [
            'C:\\wamp64\\bin\\apache\\apache2.4.65\\bin\\openssl.exe',
            'C:\\wamp64\\bin\\apache\\apache2.4.62\\bin\\openssl.exe',
            'openssl',
        ];

        $opensslBin = null;
        foreach ($candidates as $candidate) {
            if ($candidate === 'openssl' || file_exists($candidate)) {
                $opensslBin = $candidate;
                break;
            }
        }

        if ($opensslBin === null) {
            $this->fail('openssl_pkey_new() indisponible et aucun openssl CLI trouvé.');
        }

        exec(sprintf(
            '"%s" ecparam -name secp384r1 -genkey -noout -out "%s" 2>&1',
            $opensslBin,
            $privPath
        ), $output, $code);

        if ($code !== 0 || ! file_exists($privPath)) {
            $this->fail('Échec génération clé privée via CLI : ' . implode(PHP_EOL, $output));
        }

        exec(sprintf(
            '"%s" ec -in "%s" -pubout -out "%s" 2>&1',
            $opensslBin,
            $privPath,
            $pubPath
        ), $output2, $code2);

        if ($code2 !== 0 || ! file_exists($pubPath)) {
            $this->fail('Échec extraction clé publique via CLI : ' . implode(PHP_EOL, $output2));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parcours complet : login → lot → certificat → vérification publique
    // ─────────────────────────────────────────────────────────────────────────

    public function test_parcours_complet_login_lot_certificat_verify(): void
    {
        Storage::fake('local');

        // Génération des clés de test
        $dir      = Storage::path('keys');
        @mkdir($dir, 0755, true);
        $privPath = $dir . DIRECTORY_SEPARATOR . 'test-private.pem';
        $pubPath  = $dir . DIRECTORY_SEPARATOR . 'test-public.pem';

        $this->generateTestKeyPair($privPath, $pubPath);

        config([
            'certificat.private_key_path' => $privPath,
            'certificat.public_key_path'  => $pubPath,
            'certificat.verify_base_url'  => 'https://verify.test',
        ]);

        // Données de test
        $cooperative = Cooperative::factory()->create();
        $agent       = Agent::factory()->create([
            'cooperative_id' => $cooperative->id,
            'email'          => 'agent@test.bj',
            'password_hash'  => Hash::make('secret'),
        ]);
        $producteur = Producteur::factory()->create([
            'cooperative_id' => $agent->cooperative_id,
        ]);

        // 1. Login via POST /api/v1/auth/login
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email'    => 'agent@test.bj',
            'password' => 'secret',
        ]);
        $loginRes->assertStatus(200);

        // 2. Créer un lot via POST /api/v1/cooperatives/{coopId}/lots
        $lotRes = $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
                'producteur_id' => $producteur->id,
                'poids_kg'      => 300.0,
                'humidite_pct'  => 6.5,
                'prix_kg_fcfa'  => 270,
                'date_pesee'    => now()->toDateString(),
            ]);
        $lotRes->assertStatus(201);
        $publicUuid = $lotRes->json('data.certificat.public_uuid');
        $this->assertNotNull($publicUuid, 'Le lot créé doit contenir un public_uuid de certificat.');

        // 3. Vérification publique (sans auth) via GET /api/v1/certificats/{uuid}/verify
        $verifyRes = $this->getJson("/api/v1/certificats/{$publicUuid}/verify");
        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.authentique', true)
            ->assertJsonPath('data.poids_kg', 300.0)
            ->assertJsonMissingPath('data.prenom');
    }
}
