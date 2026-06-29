<?php

namespace Database\Factories;

use App\Enums\CertificatStatut;
use App\Models\Certificat;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificatFactory extends Factory
{
    protected $model = Certificat::class;

    public function definition(): array
    {
        return [
            'lot_id'       => Lot::factory(),
            'public_uuid'  => Str::ulid()->toString(),
            'payload_hash' => hash('sha384', 'test'),
            'signature'    => base64_encode('fake-signature'),
            'statut'       => CertificatStatut::Certifie,
            'emis_le'      => now()->startOfSecond(),
        ];
    }
}
