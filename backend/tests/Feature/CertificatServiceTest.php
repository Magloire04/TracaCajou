<?php

namespace Tests\Feature;

use App\Enums\CertificatStatut;
use App\Enums\LotStatut;
use App\Models\Lot;
use App\Services\CertificatService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Génération des clés de test (même stratégie que SignatureServiceTest)
        $dir = Storage::path('keys');
        @mkdir($dir, 0755, true);
        $privPath = $dir . DIRECTORY_SEPARATOR . 'test-private.pem';
        $pubPath  = $dir . DIRECTORY_SEPARATOR . 'test-public.pem';

        $this->generateTestKeyPair($privPath, $pubPath);

        config([
            'certificat.private_key_path' => $privPath,
            'certificat.public_key_path'  => $pubPath,
            'certificat.verify_base_url'  => 'https://verify.test',
        ]);
    }

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

    public function test_generateForLot_cree_un_certificat_avec_une_signature_valide(): void
    {
        $lot = Lot::factory()->create();
        $lot->load('cooperative');

        $service    = app(CertificatService::class);
        $certificat = $service->generateForLot($lot);

        $this->assertSame(CertificatStatut::Certifie, $certificat->statut);
        $this->assertIsString($certificat->public_uuid);
        $this->assertSame(26, strlen($certificat->public_uuid));

        // Vérifier la signature ECDSA P-384
        // Force DB round-trip to catch timestamp truncation regressions
        $certificat = $certificat->fresh();
        $lot->load('cooperative');
        $signatureService = app(SignatureService::class);
        $payload          = $signatureService->buildPayload($lot, $certificat->emis_le->toIso8601String());
        $this->assertTrue($signatureService->verify($payload, $certificat->signature));
    }

    public function test_generateForLot_passe_le_lot_en_statut_certifie(): void
    {
        $lot = Lot::factory()->create();
        $lot->load('cooperative');

        app(CertificatService::class)->generateForLot($lot);

        $this->assertSame(LotStatut::Certifie, $lot->fresh()->statut);
    }

    public function test_generateForLot_genere_un_fichier_pdf_dans_storage(): void
    {
        $lot = Lot::factory()->create();
        $lot->load('cooperative');

        $certificat = app(CertificatService::class)->generateForLot($lot);

        Storage::disk('local')->assertExists("certificats/{$certificat->public_uuid}.pdf");
    }

    public function test_generateForLot_genere_un_qr_code_dans_storage(): void
    {
        $lot = Lot::factory()->create();
        $lot->load('cooperative');

        $certificat = app(CertificatService::class)->generateForLot($lot);

        Storage::disk('local')->assertExists("qr/{$certificat->public_uuid}.png");
    }
}
