<?php

namespace App\Services;

use App\Models\Lot;
use RuntimeException;

/**
 * Service de signature ECDSA P-384 des certificats d'origine.
 *
 * - buildPayload() : construit le tableau canonique ordonné à partir d'un Lot.
 * - sign()         : signe le payload JSON canonique avec la clé privée P-384.
 * - verify()       : vérifie une signature avec la clé publique P-384.
 * - hashPayload()  : retourne le hash SHA-384 (hex 96 chars) du payload canonique.
 *
 * Les chemins des clés sont injectés dans le constructeur afin de pouvoir
 * utiliser des clés temporaires en test sans toucher la configuration globale.
 */
class SignatureService
{
    private string $privateKeyPath;
    private string $publicKeyPath;

    public function __construct(
        ?string $privateKeyPath = null,
        ?string $publicKeyPath  = null,
    ) {
        $this->privateKeyPath = $privateKeyPath ?? (string) config('certificat.private_key_path');
        $this->publicKeyPath  = $publicKeyPath  ?? (string) config('certificat.public_key_path');
    }

    /**
     * Construit le tableau canonique (ordre de clés fixé par la spec) à partir d'un Lot.
     *
     * Le Lot doit avoir la relation `cooperative` chargée.
     *
     * @param  Lot    $lot     Lot avec `cooperative` chargée (setRelation en test).
     * @param  string $emisLe  Horodatage ISO-8601 d'émission du certificat.
     * @return array<string, mixed>
     */
    public function buildPayload(Lot $lot, string $emisLe): array
    {
        return [
            'lot_code'     => $lot->code,
            'cooperative'  => $lot->cooperative->nom,
            'commune'      => $lot->cooperative->commune,
            'poids_kg'     => (float) $lot->poids_kg,
            'humidite_pct' => (float) $lot->humidite_pct,
            'date_pesee'   => $lot->date_pesee->format('Y-m-d'),
            'emis_le'      => $emisLe,
        ];
    }

    /**
     * Signe le payload avec la clé privée ECDSA P-384.
     *
     * @param  array<string, mixed> $payload
     * @return string  Signature encodée en base64.
     * @throws RuntimeException Si la clé privée est invalide.
     */
    public function sign(array $payload): string
    {
        $json       = $this->encodeCanonical($payload);
        $privateKey = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));

        if ($privateKey === false) {
            throw new RuntimeException('Impossible de charger la clé privée ECDSA P-384 : ' . $this->privateKeyPath);
        }

        openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA384);

        return base64_encode($signature);
    }

    /**
     * Vérifie la signature d'un payload avec la clé publique ECDSA P-384.
     *
     * @param  array<string, mixed> $payload
     * @param  string               $signature  Signature encodée en base64.
     */
    public function verify(array $payload, string $signature): bool
    {
        $json      = $this->encodeCanonical($payload);
        $publicKey = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));

        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($json, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA384) === 1;
    }

    /**
     * Calcule le hash SHA-384 (hex) du payload canonique.
     *
     * @param  array<string, mixed> $payload
     * @return string  Chaîne hexadécimale de 96 caractères.
     */
    public function hashPayload(array $payload): string
    {
        return hash('sha384', $this->encodeCanonical($payload));
    }

    /**
     * Encode le payload en JSON canonique (ordre des clés préservé, Unicode non échappé).
     *
     * @param  array<string, mixed> $payload
     */
    private function encodeCanonical(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
