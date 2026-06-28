<?php

namespace App\Http\Controllers;

use App\Models\Certificat;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CertificatController extends Controller
{
    public function __construct(private readonly SignatureService $signatureService) {}

    public function verify(string $uuid): JsonResponse
    {
        $certificat = Certificat::where('public_uuid', $uuid)->with('lot.cooperative')->first();

        if (!$certificat) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Certificat introuvable.', 'status' => 404]], 404);
        }

        $lot        = $certificat->lot;
        $payload    = $this->signatureService->buildPayload($lot, $certificat->emis_le->toIso8601String());
        $authentique = $this->signatureService->verify($payload, $certificat->signature);

        return response()->json([
            'data' => [
                'authentique'  => $authentique,
                'cooperative'  => $lot->cooperative->nom,
                'commune'      => $lot->cooperative->commune,
                'poids_kg'     => (float) $lot->poids_kg,
                'humidite_pct' => (float) $lot->humidite_pct,
                'date_pesee'   => $lot->date_pesee->format('Y-m-d'),
                'statut'       => $certificat->statut->value,
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function publicKey(): JsonResponse
    {
        $pem = file_get_contents(config('certificat.public_key_path'));

        return response()->json([
            'data' => [
                'algorithm'  => 'ECDSA P-384',
                'format'     => 'PEM',
                'public_key' => $pem,
            ],
        ]);
    }

    public function download(Request $request, string $uuid): Response
    {
        $certificat = Certificat::where('public_uuid', $uuid)->with('lot')->firstOrFail();

        // Anti-IDOR: only agents from the same cooperative can download
        if ($certificat->lot->cooperative_id !== $request->user()->cooperative_id) {
            abort(403, 'Accès refusé.');
        }

        $path = "certificats/{$certificat->public_uuid}.pdf";
        abort_unless(Storage::exists($path), 404);

        return response(Storage::get($path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"certificat_{$certificat->public_uuid}.pdf\"",
        ]);
    }
}
