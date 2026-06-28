<?php

namespace Tests\Unit;

use App\Models\Cooperative;
use App\Models\Lot;
use App\Services\SignatureService;
use Tests\TestCase;

/**
 * Tests unitaires pour SignatureService — ECDSA P-384.
 *
 * Utilise Tests\TestCase (Laravel) pour pouvoir instancier les modèles
 * Eloquent sans connexion DB réelle (les modèles sont créés en mémoire
 * avec setRelation, sans jamais persister).
 *
 * Génération des clés : tente d'abord openssl_pkey_new() (nécessite
 * OPENSSL_CONF dans l'environnement sur Windows). Si indisponible,
 * appelle openssl(1) via CLI comme fallback Windows.
 */
class SignatureServiceTest extends TestCase
{
    private string $privateKeyPath;
    private string $publicKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $keysDir = storage_path('keys');
        if (! is_dir($keysDir)) {
            mkdir($keysDir, 0755, true);
        }

        $this->privateKeyPath = $keysDir . DIRECTORY_SEPARATOR . 'test-private.pem';
        $this->publicKeyPath  = $keysDir . DIRECTORY_SEPARATOR . 'test-public.pem';

        $this->generateTestKeyPair();
    }

    protected function tearDown(): void
    {
        @unlink($this->privateKeyPath);
        @unlink($this->publicKeyPath);
        parent::tearDown();
    }

    /**
     * Génère une paire de clés ECDSA P-384 pour les tests.
     *
     * Stratégie : openssl_pkey_new() si disponible (Linux/Mac/Windows avec
     * OPENSSL_CONF), sinon appel CLI openssl(1) (fallback Windows WampServer).
     */
    private function generateTestKeyPair(): void
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'secp384r1',
        ]);

        if ($res !== false) {
            // Chemin nominal : openssl_pkey_new() fonctionne
            openssl_pkey_export($res, $privateKeyPem);
            $publicKeyPem = openssl_pkey_get_details($res)['key'];
            file_put_contents($this->privateKeyPath, $privateKeyPem);
            file_put_contents($this->publicKeyPath, $publicKeyPem);
            return;
        }

        // Fallback Windows : appel CLI openssl (WampServer)
        $this->generateKeyPairViaCli();
    }

    /**
     * Génère les clés via l'openssl.exe de WampServer.
     * Utilisé uniquement lorsque openssl_pkey_new() échoue (Windows sans OPENSSL_CONF).
     */
    private function generateKeyPairViaCli(): void
    {
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
            $this->fail('openssl_pkey_new() indisponible et aucun openssl CLI trouvé. Positionner OPENSSL_CONF avant de lancer PHP.');
        }

        // Génère la clé privée
        exec(sprintf(
            '"%s" ecparam -name secp384r1 -genkey -noout -out "%s" 2>&1',
            $opensslBin,
            $this->privateKeyPath
        ), $output, $code);

        if ($code !== 0 || ! file_exists($this->privateKeyPath)) {
            $this->fail('Echec génération clé privée via CLI: ' . implode(PHP_EOL, $output));
        }

        // Extrait la clé publique
        exec(sprintf(
            '"%s" ec -in "%s" -pubout -out "%s" 2>&1',
            $opensslBin,
            $this->privateKeyPath,
            $this->publicKeyPath
        ), $output2, $code2);

        if ($code2 !== 0 || ! file_exists($this->publicKeyPath)) {
            $this->fail('Echec extraction clé publique via CLI: ' . implode(PHP_EOL, $output2));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Test : buildPayload retourne un tableau canonique avec les champs attendus
     * et dans le bon ordre.
     */
    public function test_buildPayload_retourne_un_tableau_canonique_avec_les_champs_attendus(): void
    {
        $lot = new Lot([
            'code'         => 'AGPKL20260628143022',
            'poids_kg'     => 425.5,
            'humidite_pct' => 7.2,
            'date_pesee'   => '2026-06-28',
        ]);
        $lot->setRelation('cooperative', new Cooperative([
            'nom'     => 'Coopérative AGPK',
            'commune' => 'Kétou',
        ]));

        $service = new SignatureService($this->privateKeyPath, $this->publicKeyPath);
        $payload = $service->buildPayload($lot, '2026-06-28T14:30:22Z');

        $this->assertArrayHasKey('lot_code', $payload);
        $this->assertArrayHasKey('cooperative', $payload);
        $this->assertArrayHasKey('commune', $payload);
        $this->assertArrayHasKey('poids_kg', $payload);
        $this->assertArrayHasKey('humidite_pct', $payload);
        $this->assertArrayHasKey('date_pesee', $payload);
        $this->assertArrayHasKey('emis_le', $payload);

        $this->assertSame('AGPKL20260628143022', $payload['lot_code']);
        $this->assertSame(425.5, $payload['poids_kg']);
        $this->assertSame(7.2, $payload['humidite_pct']);

        $this->assertSame(
            ['lot_code', 'cooperative', 'commune', 'poids_kg', 'humidite_pct', 'date_pesee', 'emis_le'],
            array_keys($payload)
        );
    }

    /**
     * Test : sign + verify fonctionne avec une paire de clés P-384.
     */
    public function test_sign_et_verify_fonctionne_avec_une_paire_de_cles_P384(): void
    {
        $service = new SignatureService($this->privateKeyPath, $this->publicKeyPath);
        $payload = ['lot_code' => 'TEST', 'poids_kg' => 100.0, 'humidite_pct' => 7.0];

        $signature = $service->sign($payload);

        $this->assertTrue($service->verify($payload, $signature));
    }

    /**
     * Test : verify échoue si le payload est altéré.
     */
    public function test_verify_echoue_si_le_payload_est_altere(): void
    {
        $service   = new SignatureService($this->privateKeyPath, $this->publicKeyPath);
        $payload   = ['lot_code' => 'TEST', 'poids_kg' => 100.0];
        $signature = $service->sign($payload);

        $this->assertFalse($service->verify(['lot_code' => 'TAMPERED', 'poids_kg' => 100.0], $signature));
    }

    /**
     * Test : hashPayload retourne un hash SHA-384 hex de 96 caractères.
     */
    public function test_hashPayload_retourne_un_hash_SHA384_hex_de_96_caracteres(): void
    {
        $service = new SignatureService($this->privateKeyPath, $this->publicKeyPath);
        $hash    = $service->hashPayload(['lot_code' => 'TEST']);

        $this->assertSame(96, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));
    }
}
