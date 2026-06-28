<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Cooperative;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\CertificatService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Peuple la base avec le scénario de démonstration pitch TraçaCajou.
     *
     * Produit :
     *   - 1 coopérative AGPK (Kétou)
     *   - 1 agent Kossi Hounsou
     *   - 10 producteurs béninois avec consentement
     *   - 1 lot certifié (ECDSA P-384) — prêt pour test QR
     *
     * Prérequis : CERT_SIGNING_PRIVATE_KEY_PATH défini dans .env et fichier présent.
     */
    public function run(CertificatService $certificatService): void
    {
        // ── Vérification clé de signature ────────────────────────────────────────
        $privateKeyPath = config('certificat.private_key_path');
        if (empty($privateKeyPath) || ! file_exists($privateKeyPath)) {
            $this->command->warn('CERT_SIGNING_PRIVATE_KEY_PATH non défini ou fichier absent.');
            $this->command->warn("Chemin attendu : {$privateKeyPath}");
            $this->command->error('Seed interrompu — configurez la clé ECDSA P-384 dans .env avant de relancer.');
            return;
        }

        // ── 1. Coopérative ────────────────────────────────────────────────────────
        $cooperative = Cooperative::create([
            'nom'     => 'Coopérative AGPK',
            'code'    => 'AGPK',
            'commune' => 'Kétou',
        ]);

        // ── 2. Agent ──────────────────────────────────────────────────────────────
        Agent::create([
            'prenom'         => 'Kossi',
            'nom'            => 'Hounsou',
            'email'          => 'agent@agpk.bj',
            'role'           => 'agent',
            'password_hash'  => Hash::make('Demo@2026!'),
            'cooperative_id' => $cooperative->id,
        ]);

        // ── 3. Producteurs (données béninoises réelles) ───────────────────────────
        $nomsBeninois = [
            ['prenom' => 'Adjovi',    'nom' => 'Agossou'],
            ['prenom' => 'Brice',     'nom' => 'Dossou'],
            ['prenom' => 'Clarisse',  'nom' => 'Houénou'],
            ['prenom' => 'Désiré',    'nom' => 'Kpankpan'],
            ['prenom' => 'Evelyne',   'nom' => 'Tokplo'],
            ['prenom' => 'Fiacre',    'nom' => 'Adégbindin'],
            ['prenom' => 'Gisèle',    'nom' => 'Dègbé'],
            ['prenom' => 'Hugues',    'nom' => 'Akpovo'],
            ['prenom' => 'Irène',     'nom' => 'Zannou'],
            ['prenom' => 'Joachim',   'nom' => 'Sossou'],
        ];

        $localites = ['Kétou-Centre', 'Ilara', 'Okpometa', 'Adakplamè'];
        $sexes     = ['M', 'F'];

        $timestamp = now()->format('YmdHis');
        $producteurs = collect($nomsBeninois)->map(function (array $identite, int $index) use (
            $cooperative,
            $localites,
            $sexes,
            $timestamp,
        ): Producteur {
            return Producteur::create([
                'code'            => 'AGPKP' . $timestamp . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                'prenom'          => $identite['prenom'],
                'nom'             => $identite['nom'],
                'sexe'            => $sexes[$index % 2],
                'localite'        => $localites[$index % count($localites)],
                'cooperative_id'  => $cooperative->id,
                'consentement_le' => now(),
            ]);
        });

        // ── 4. Lot ────────────────────────────────────────────────────────────────
        $poids        = 425.5;
        $prixKgFcfa   = 270;
        $montantFcfa  = round($poids * $prixKgFcfa, 2);

        $lot = Lot::create([
            'code'           => 'AGPKL' . $timestamp,
            'producteur_id'  => $producteurs->first()->id,
            'cooperative_id' => $cooperative->id,
            'poids_kg'       => $poids,
            'humidite_pct'   => 7.2,
            'prix_kg_fcfa'   => $prixKgFcfa,
            'montant_fcfa'   => $montantFcfa,
            'date_pesee'     => now()->toDateString(),
            'statut'         => 'enregistre',
        ]);

        // ── 5. Certificat ─────────────────────────────────────────────────────────
        $lot->load('cooperative');
        $certificat = $certificatService->generateForLot($lot);

        // ── 6. Récapitulatif ──────────────────────────────────────────────────────
        $verifyBaseUrl = config('certificat.verify_base_url', 'http://localhost:8000');
        $verifyUrl     = "{$verifyBaseUrl}/api/v1/certificats/{$certificat->public_uuid}/verify";

        $this->command->newLine();
        $this->command->info('✓ Seed OK');
        $this->command->info("  Agent     : agent@agpk.bj / Demo@2026!");
        $this->command->info("  Lot       : {$lot->code}");
        $this->command->info("  Certificat UUID : {$certificat->public_uuid}");
        $this->command->info("  URL vérification: {$verifyUrl}");
        $this->command->newLine();
    }
}
