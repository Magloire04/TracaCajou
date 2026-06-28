<?php

namespace App\Models;

use App\Enums\CertificatStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificat extends Model
{
    use HasUlids;

    protected $fillable = ['lot_id', 'public_uuid', 'payload_hash', 'signature', 'statut', 'emis_le'];

    protected $casts = [
        'statut'  => CertificatStatut::class,
        'emis_le' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
