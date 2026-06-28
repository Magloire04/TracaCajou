<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cooperative extends Model
{
    use HasUlids;

    protected $fillable = ['nom', 'code', 'commune'];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function producteurs(): HasMany
    {
        return $this->hasMany(Producteur::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
