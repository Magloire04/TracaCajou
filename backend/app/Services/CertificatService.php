<?php

namespace App\Services;

use App\Models\Certificat;
use App\Models\Lot;

class CertificatService
{
    /**
     * Génère et persiste un certificat signé pour le lot donné.
     * Implémentation complète en Task 10 (ECDSA P-384).
     */
    public function generateForLot(Lot $lot): Certificat
    {
        throw new \RuntimeException('CertificatService::generateForLot() not implemented — use mock in tests (Task 10).');
    }
}
