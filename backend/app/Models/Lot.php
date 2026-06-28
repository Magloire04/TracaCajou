<?php

namespace App\Models;

use App\Enums\LotStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lot extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'code', 'producteur_id', 'cooperative_id', 'poids_kg', 'humidite_pct',
        'prix_kg_fcfa', 'montant_fcfa', 'date_pesee', 'statut',
    ];

    protected $casts = [
        'statut'       => LotStatut::class,
        'date_pesee'   => 'date',
        'poids_kg'     => 'float',
        'humidite_pct' => 'float',
        'prix_kg_fcfa' => 'float',
        'montant_fcfa' => 'float',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function producteur(): BelongsTo
    {
        return $this->belongsTo(Producteur::class);
    }

    public function certificat(): HasOne
    {
        return $this->hasOne(Certificat::class);
    }
}
