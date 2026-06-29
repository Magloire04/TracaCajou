<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producteur extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['code', 'prenom', 'nom', 'sexe', 'localite', 'cooperative_id', 'consentement_le'];

    protected $casts = ['consentement_le' => 'datetime'];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
