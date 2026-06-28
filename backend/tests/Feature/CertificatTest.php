<?php

namespace Tests\Feature;

use App\Enums\CertificatStatut;
use App\Models\Certificat;
use App\Models\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Génère une paire de clés ECDSA P-384 pour les tests.
     * Même stratégie que CertificatServiceTest : openssl_pkey_new() si disponible,
     * sinon CLI openssl (fallback WampServer Windows).
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

        if ($code !== 0 || !file_exists($privPath)) {
            $this->fail('Échec génération clé privée via CLI : ' . implode(PHP_EOL, $output));
        }

        exec(sprintf(
            '"%s" ec -in "%s" -pubout -out "%s" 2>&1',
            $opensslBin,
            $privPath,
            $pubPath
        ), $output2, $code2);

        if ($code2 !== 0 || !file_exists($pubPath)) {
            $this->fail('Échec extraction clé publique via CLI : ' . implode(PHP_EOL, $output2));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // verify — certificat valide (signature ECDSA réelle)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_verify_retourne_authentique_true_pour_un_certificat_valide(): void
    {
        Storage::fake('local');

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

        $lot = Lot::factory()->create();
        $lot->load('cooperative');
        $certificat = app(\App\Services\CertificatService::class)->generateForLot($lot);

        $response = $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/verify");

        $response->assertStatus(200)
            ->assertJsonPath('data.authentique', true)
            ->assertJsonPath('data.statut', 'certifie')
            ->assertJsonStructure(['data' => [
                'authentique', 'cooperative', 'commune',
                'poids_kg', 'humidite_pct', 'date_pesee', 'statut',
            ]])
            ->assertJsonMissingPath('data.nom')
            ->assertJsonMissingPath('data.prenom');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // verify — UUID inconnu → 404
    // ─────────────────────────────────────────────────────────────────────────

    public function test_verify_retourne_404_pour_un_uuid_inconnu(): void
    {
        $this->getJson('/api/v1/certificats/01INVALIDEULID123456789999/verify')
            ->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // verify — certificat révoqué → statut = 'revoque'
    // ─────────────────────────────────────────────────────────────────────────

    public function test_verify_indique_statut_revoque_pour_un_certificat_revoque(): void
    {
        $lot = Lot::factory()->create();
        $certificat = Certificat::factory()->create([
            'lot_id' => $lot->id,
            'statut' => CertificatStatut::Revoque,
        ]);

        $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/verify")
            ->assertJsonPath('data.statut', 'revoque');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // public-key — retourne la clé publique PEM
    // ─────────────────────────────────────────────────────────────────────────

    public function test_public_key_retourne_la_cle_publique_pem(): void
    {
        Storage::fake('local');
        Storage::put('keys/test-public.pem', '-----BEGIN PUBLIC KEY-----TEST-----END PUBLIC KEY-----');
        config(['certificat.public_key_path' => Storage::path('keys/test-public.pem')]);

        $this->getJson('/api/v1/certificats/public-key')
            ->assertStatus(200)
            ->assertJsonPath('data.format', 'PEM')
            ->assertJsonPath('data.algorithm', 'ECDSA P-384');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // download — requiert une authentification
    // ─────────────────────────────────────────────────────────────────────────

    public function test_download_pdf_necessite_une_auth(): void
    {
        $certificat = Certificat::factory()->create();

        $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/pdf")
            ->assertStatus(401);
    }
}
