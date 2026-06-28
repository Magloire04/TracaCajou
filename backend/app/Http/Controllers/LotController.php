<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLotRequest;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\CertificatService;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LotController extends Controller
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly CertificatService $certificatService,
    ) {}

    public function index(Request $request, string $cooperativeId): JsonResponse
    {
        $limit = min((int) $request->query('limit', 20), 100);
        $page  = max((int) $request->query('page', 1), 1);

        $query = Lot::where('cooperative_id', $cooperativeId)
            ->with('certificat')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return response()->json([
            'data' => $items->map(fn (Lot $lot) => $this->formatLot($lot)),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $lot = Lot::with(['certificat', 'cooperative', 'producteur'])
            ->where('cooperative_id', $request->user()->cooperative_id)
            ->findOrFail($id);

        return response()->json(['data' => $this->formatLot($lot, detail: true)]);
    }

    public function store(StoreLotRequest $request, string $cooperativeId): JsonResponse
    {
        $producteur = Producteur::findOrFail($request->producteur_id);

        if ($producteur->cooperative_id !== $cooperativeId) {
            return response()->json([
                'error' => [
                    'code'    => 'PRODUCTEUR_WRONG_COOPERATIVE',
                    'message' => "Ce producteur n'appartient pas à votre coopérative.",
                    'status'  => 403,
                ],
            ], 403);
        }

        $lot = DB::transaction(function () use ($request, $cooperativeId) {
            $poids = (float) $request->poids_kg;
            $prix  = (float) $request->prix_kg_fcfa;

            $lot = Lot::create([
                'code'           => $this->codeGenerator->generateLotCode($request->user()->cooperative->code),
                'producteur_id'  => $request->producteur_id,
                'cooperative_id' => $cooperativeId,
                'poids_kg'       => $poids,
                'humidite_pct'   => (float) $request->humidite_pct,
                'prix_kg_fcfa'   => $prix,
                'montant_fcfa'   => round($poids * $prix, 2),
                'date_pesee'     => $request->date_pesee,
                'statut'         => 'enregistre',
            ]);

            $lot->load('cooperative');
            $this->certificatService->generateForLot($lot);
            $lot->refresh()->load('certificat');

            return $lot;
        });

        return response()->json(['data' => $this->formatLot($lot)], 201);
    }

    private function formatLot(Lot $lot, bool $detail = false): array
    {
        $data = [
            'id'           => $lot->id,
            'code'         => $lot->code,
            'poids_kg'     => $lot->poids_kg,
            'humidite_pct' => $lot->humidite_pct,
            'prix_kg_fcfa' => $lot->prix_kg_fcfa,
            'montant_fcfa' => $lot->montant_fcfa,
            'date_pesee'   => $lot->date_pesee?->format('Y-m-d'),
            'statut'       => $lot->statut?->value,
            'certificat'   => $lot->certificat ? [
                'public_uuid' => $lot->certificat->public_uuid,
                'statut'      => $lot->certificat->statut?->value,
            ] : null,
        ];

        if ($detail) {
            $data['cooperative'] = $lot->cooperative
                ? ['nom' => $lot->cooperative->nom, 'commune' => $lot->cooperative->commune]
                : null;
        }

        return $data;
    }
}
