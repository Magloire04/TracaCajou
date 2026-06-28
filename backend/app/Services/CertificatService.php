<?php

namespace App\Services;

use App\Enums\CertificatStatut;
use App\Enums\LotStatut;
use App\Models\Certificat;
use App\Models\Lot;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Génère un certificat d'origine numérique signé pour un lot de cajou.
 *
 * Responsabilités :
 * - Construire et signer le payload canonique (via SignatureService).
 * - Générer le QR code PNG (URL de vérification publique) via GD (sans imagick).
 * - Générer le PDF du certificat (DomPDF + vue Blade).
 * - Persister le Certificat en base et mettre à jour le statut du Lot.
 *
 * Précondition : le Lot passé doit avoir la relation `cooperative` chargée.
 */
class CertificatService
{
    public function __construct(private readonly SignatureService $signatureService) {}

    /**
     * Génère et persiste un certificat signé pour le lot donné.
     *
     * @param  Lot        $lot  Lot avec relation `cooperative` chargée.
     * @return Certificat       Certificat persisté avec signature ECDSA P-384.
     */
    public function generateForLot(Lot $lot): Certificat
    {
        $emisLe     = now();
        $publicUuid = Str::ulid()->toString();

        $payload   = $this->signatureService->buildPayload($lot, $emisLe->toIso8601String());
        $signature = $this->signatureService->sign($payload);
        $hash      = $this->signatureService->hashPayload($payload);

        $verifyUrl = config('certificat.verify_base_url') . "/certificats/{$publicUuid}/verify";
        $qrPng     = $this->generateQrPng($verifyUrl, 300);
        Storage::put("qr/{$publicUuid}.png", $qrPng);

        $qrBase64 = base64_encode($qrPng);
        $pdf      = Pdf::loadView('certificats.pdf', [
            'lot'         => $lot,
            'emis_le'     => $emisLe->format('d/m/Y H:i'),
            'public_uuid' => $publicUuid,
            'qr_base64'   => $qrBase64,
        ]);
        Storage::put("certificats/{$publicUuid}.pdf", $pdf->output());

        $certificat = Certificat::create([
            'lot_id'       => $lot->id,
            'public_uuid'  => $publicUuid,
            'payload_hash' => $hash,
            'signature'    => $signature,
            'statut'       => CertificatStatut::Certifie,
            'emis_le'      => $emisLe,
        ]);

        $lot->update(['statut' => LotStatut::Certifie]);

        return $certificat;
    }

    /**
     * Génère un QR code PNG via GD (sans imagick).
     *
     * Utilise directement le moteur BaconQrCode pour obtenir la matrice de bits,
     * puis rend le résultat en PNG via l'extension GD de PHP.
     *
     * @param  string $content   Contenu à encoder dans le QR code.
     * @param  int    $sizePixels Taille totale de l'image en pixels (carré).
     * @return string            Contenu binaire du fichier PNG.
     */
    private function generateQrPng(string $content, int $sizePixels = 300): string
    {
        $qrCode = Encoder::encode($content, ErrorCorrectionLevel::L());
        $matrix = $qrCode->getMatrix();

        $matrixWidth = $matrix->getWidth();
        $margin      = 4; // modules de marge blanche autour du QR
        $totalModules = $matrixWidth + ($margin * 2);
        $moduleSize  = (int) floor($sizePixels / $totalModules);
        $imgSize     = $totalModules * $moduleSize;

        $image = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $matrixWidth; $y++) {
            for ($x = 0; $x < $matrixWidth; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $margin) * $moduleSize;
                    $py = ($y + $margin) * $moduleSize;
                    imagefilledrectangle($image, $px, $py, $px + $moduleSize - 1, $py + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        imagedestroy($image);

        return $pngData;
    }
}
