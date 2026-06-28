<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
  h1   { text-align: center; color: #2d5a27; font-size: 18px; }
  h2   { text-align: center; color: #2d5a27; font-size: 14px; margin-top: 0; }
  .field { margin: 6px 0; }
  .label { font-weight: bold; }
  .qr    { text-align: center; margin-top: 24px; }
  .footer { margin-top: 20px; font-size: 9px; color: #888; text-align: center; }
</style>
</head>
<body>
  <h1>CERTIFICAT D'ORIGINE — CAJOU</h1>
  <h2>{{ $lot->cooperative->nom }}</h2>

  <div class="field"><span class="label">N° Lot :</span> {{ $lot->code }}</div>
  <div class="field"><span class="label">Coopérative :</span> {{ $lot->cooperative->nom }}</div>
  <div class="field"><span class="label">Commune :</span> {{ $lot->cooperative->commune }}</div>
  <div class="field"><span class="label">Poids :</span> {{ $lot->poids_kg }} kg</div>
  <div class="field"><span class="label">Humidité :</span> {{ $lot->humidite_pct }} %</div>
  <div class="field"><span class="label">Date de pesée :</span> {{ $lot->date_pesee->format('d/m/Y') }}</div>
  <div class="field"><span class="label">Émis le :</span> {{ $emis_le }}</div>
  <div class="field"><span class="label">Statut :</span> CERTIFIÉ</div>

  @if($qr_base64)
  <div class="qr">
    <img src="data:image/png;base64,{{ $qr_base64 }}" width="180">
    <p style="font-size:9px;">Scannez ce QR pour vérifier ce certificat</p>
  </div>
  @endif

  <div class="footer">UUID : {{ $public_uuid }}</div>
</body>
</html>
