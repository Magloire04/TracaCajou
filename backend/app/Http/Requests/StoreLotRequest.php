<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producteur_id' => ['required', 'ulid', 'exists:producteurs,id'],
            'poids_kg'      => ['required', 'numeric', 'gt:0'],
            'humidite_pct'  => ['required', 'numeric', 'min:0', 'max:100'],
            'prix_kg_fcfa'  => ['required', 'numeric', 'gt:0'],
            'date_pesee'    => ['required', 'date'],
        ];
    }
}
