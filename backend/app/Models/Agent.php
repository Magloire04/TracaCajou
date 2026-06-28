<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Agent extends Authenticatable
{
    use HasApiTokens, HasUlids;

    protected $fillable = ['prenom', 'nom', 'email', 'role', 'password_hash', 'cooperative_id'];
    protected $hidden   = ['password_hash'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }
}
