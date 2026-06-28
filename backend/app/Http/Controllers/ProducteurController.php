<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProducteurRequest;
use App\Models\Producteur;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProducteurController extends Controller
{
    public function __construct(private readonly CodeGeneratorService $codeGenerator) {}

    public function index(Request $request, string $cooperativeId): JsonResponse
    {
        $limit = min((int) $request->query('limit', 20), 100);
        $page  = max((int) $request->query('page', 1), 1);

        $query = Producteur::where('cooperative_id', $cooperativeId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return response()->json([
            'data' => $items->map(fn($producteur) => [
                'id'     => $producteur->id,
                'code'   => $producteur->code,
                'prenom' => $producteur->prenom,
                'nom'    => $producteur->nom,
                'sexe'   => $producteur->sexe,
            ]),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ]);
    }

    public function store(StoreProducteurRequest $request, string $cooperativeId): JsonResponse
    {
        $code = $this->codeGenerator->generateProducteurCode(
            $request->user()->cooperative->code
        );

        $producteur = Producteur::create([
            'code'            => $code,
            'prenom'          => $request->prenom,
            'nom'             => $request->nom,
            'sexe'            => $request->sexe,
            'localite'        => $request->localite,
            'cooperative_id'  => $cooperativeId,
            'consentement_le' => now(),
        ]);

        return response()->json([
            'data' => [
                'id'     => $producteur->id,
                'code'   => $producteur->code,
                'prenom' => $producteur->prenom,
                'nom'    => $producteur->nom,
                'sexe'   => $producteur->sexe,
            ],
        ], 201);
    }

    public function destroy(string $cooperativeId, string $producteurId): JsonResponse
    {
        $producteur = Producteur::where('id', $producteurId)
            ->where('cooperative_id', $cooperativeId)
            ->firstOrFail();

        $producteur->update([
            'prenom'   => '[supprimé]',
            'nom'      => '[supprimé]',
            'sexe'     => null,
            'localite' => null,
        ]);

        return response()->json(null, 204);
    }
}
