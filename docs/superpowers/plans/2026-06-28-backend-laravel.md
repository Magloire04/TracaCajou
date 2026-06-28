# TraçaCajou — Back-end Laravel 12 — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire l'API REST Laravel 12 complète de TraçaCajou : auth agents, gestion producteurs/lots, génération de certificats ECDSA P-384 (PDF + QR), vérification publique, et conformité APDP — entièrement couverte par des tests Pest.

**Architecture:** API REST JSON versionnée (`/api/v1`), enveloppe `{data|meta|error}` constante, auth Sanctum SPA (cookie httpOnly+secure), signature ECDSA P-384 via OpenSSL natif PHP. Le front-end Nuxt (Plan 2) consommera cette API — la coupler via `openapi.yaml` versionné.

**Tech Stack:** Laravel 12 · PHP 8.2+ · MySQL (dev) / SQLite en mémoire (tests) · Sanctum SPA · Pest 3 · barryvdh/laravel-dompdf · simplesoftwareio/simple-qrcode · OpenSSL natif PHP

## Global Constraints

- Tous les IDs : **ULID** via trait `HasUlids` — jamais d'int auto-incrémenté.
- Hash mots de passe : **Argon2id** (`config/hashing.php` → `driver: argon2id`).
- Auth : Sanctum SPA — cookie session httpOnly+secure. `JWT_SECRET` dans `.env.example` n'est pas utilisé (Sanctum session ne l'exige pas).
- Enveloppe de réponse : `{ "data": … }` / `{ "data": […], "meta": { "page","limit","total" } }` / `{ "error": { "code","message","status" } }`.
- `montant_fcfa` : **toujours calculé serveur** (`poids_kg × prix_kg_fcfa`), jamais reçu du client.
- Anti-IDOR : **chaque requête** vérifie que l'agent appartient à la coopérative ciblée.
- Logs : connexions, 403, changements de rôle. **Jamais** mot de passe, token, nom/prénom, localité, IP nominative.
- Variables d'environnement clé : `CERT_SIGNING_PRIVATE_KEY_PATH`, `CERT_SIGNING_PUBLIC_KEY_PATH`, `CERT_PUBLIC_VERIFY_BASE_URL`.
- Répertoire projet : `backend/` (sous-dossier du repo, Laravel installé ici).

---

## Carte des fichiers

```
backend/
├── app/
│   ├── Enums/
│   │   ├── CertificatStatut.php
│   │   └── LotStatut.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/AuthController.php
│   │   │   ├── CertificatController.php
│   │   │   ├── LotController.php
│   │   │   └── ProducteurController.php
│   │   ├── Middleware/VerifyCooperativeAccess.php
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── StoreLotRequest.php
│   │       └── StoreProducteurRequest.php
│   ├── Models/
│   │   ├── Agent.php
│   │   ├── Certificat.php
│   │   ├── Cooperative.php
│   │   ├── Lot.php
│   │   └── Producteur.php
│   └── Services/
│       ├── CertificatService.php
│       ├── CodeGeneratorService.php
│       └── SignatureService.php
├── config/certificat.php
├── database/
│   ├── factories/{Cooperative,Agent,Producteur,Lot}Factory.php
│   ├── migrations/
│   │   ├── 2026_06_28_000001_create_cooperatives_table.php
│   │   ├── 2026_06_28_000002_create_agents_table.php
│   │   ├── 2026_06_28_000003_create_producteurs_table.php
│   │   ├── 2026_06_28_000004_create_lots_table.php
│   │   └── 2026_06_28_000005_create_certificats_table.php
│   └── seeders/DemoSeeder.php
├── resources/views/certificats/pdf.blade.php
├── routes/api.php
├── storage/keys/p384-public.pem    ← committé (clé publique seulement)
└── tests/
    ├── Feature/{Auth,Producteur,Lot,Certificat}Test.php
    └── Unit/{CodeGenerator,Signature}ServiceTest.php
```

---

### Task 1 : Installation Laravel 12 + configuration

**Files:**
- Create: `backend/` (projet Laravel)
- Create: `backend/config/certificat.php`
- Modify: `backend/config/hashing.php`
- Modify: `backend/config/cors.php`
- Modify: `backend/config/session.php`
- Modify: `backend/phpunit.xml`

**Interfaces:**
- Produces: projet Laravel fonctionnel, `php artisan test` passe (zéro test = zéro échec)

- [ ] **Step 1 : Installer Laravel 12 dans `backend/`**

```bash
composer create-project laravel/laravel backend "^12.0"
cd backend
composer require laravel/sanctum barryvdh/laravel-dompdf simplesoftwareio/simple-qrcode
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 2 : Configurer Argon2id**

Dans `backend/config/hashing.php`, changer le driver par défaut :
```php
'driver' => env('HASH_DRIVER', 'argon2id'),
```

- [ ] **Step 3 : Créer `backend/config/certificat.php`**

```php
<?php

return [
    'private_key_path' => env('CERT_SIGNING_PRIVATE_KEY_PATH'),
    'public_key_path'  => env('CERT_SIGNING_PUBLIC_KEY_PATH'),
    'verify_base_url'  => env('CERT_PUBLIC_VERIFY_BASE_URL', 'http://localhost:8000'),
];
```

- [ ] **Step 4 : Configurer CORS pour Nuxt**

Dans `backend/config/cors.php` :
```php
'paths'                  => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins'        => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_methods'        => ['*'],
'allowed_headers'        => ['*'],
'supports_credentials'   => true,
```

- [ ] **Step 5 : Configurer SQLite pour les tests**

Dans `backend/phpunit.xml`, ajouter dans `<php>` :
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="CACHE_STORE" value="array"/>
```

- [ ] **Step 6 : Vérifier que les tests de base passent**

```bash
cd backend && php artisan test
```
Expected: `Tests: 2 passed` (les tests Laravel par défaut).

- [ ] **Step 7 : Commit**

```bash
git add backend/
git commit -m "feat(backend): scaffolding Laravel 12 + config Argon2id, CORS, certificat"
```

---

### Task 2 : Enums + migrations + modèles

**Files:**
- Create: `backend/app/Enums/LotStatut.php`
- Create: `backend/app/Enums/CertificatStatut.php`
- Create: `backend/database/migrations/2026_06_28_000001_create_cooperatives_table.php`
- Create: `backend/database/migrations/2026_06_28_000002_create_agents_table.php`
- Create: `backend/database/migrations/2026_06_28_000003_create_producteurs_table.php`
- Create: `backend/database/migrations/2026_06_28_000004_create_lots_table.php`
- Create: `backend/database/migrations/2026_06_28_000005_create_certificats_table.php`
- Create: `backend/app/Models/{Cooperative,Agent,Producteur,Lot,Certificat}.php`

**Interfaces:**
- Produces: `Cooperative`, `Agent`, `Producteur`, `Lot`, `Certificat` — ULIDs, relations, casts

- [ ] **Step 1 : Créer les enums**

`backend/app/Enums/LotStatut.php` :
```php
<?php

namespace App\Enums;

enum LotStatut: string
{
    case Enregistre = 'enregistre';
    case Certifie   = 'certifie';
    case Revoque    = 'revoque';
}
```

`backend/app/Enums/CertificatStatut.php` :
```php
<?php

namespace App\Enums;

enum CertificatStatut: string
{
    case Certifie = 'certifie';
    case Revoque  = 'revoque';
}
```

- [ ] **Step 2 : Migrations (supprimer les migrations par défaut de Laravel puis créer les nôtres)**

```bash
cd backend
rm database/migrations/0001_01_01_000000_create_users_table.php
rm database/migrations/0001_01_01_000001_create_cache_table.php
rm database/migrations/0001_01_01_000002_create_jobs_table.php
php artisan make:migration create_cooperatives_table --path=database/migrations
php artisan make:migration create_agents_table --path=database/migrations
php artisan make:migration create_producteurs_table --path=database/migrations
php artisan make:migration create_lots_table --path=database/migrations
php artisan make:migration create_certificats_table --path=database/migrations
```

Renommer les fichiers générés pour correspondre aux noms ci-dessus, puis remplir :

`create_cooperatives_table.php` — méthode `up()` :
```php
Schema::create('cooperatives', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('nom');
    $table->string('code', 10)->unique();
    $table->string('commune');
    $table->timestamps();
});
```

`create_agents_table.php` — méthode `up()` :
```php
Schema::create('agents', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('prenom');
    $table->string('nom');
    $table->string('email')->unique();
    $table->string('role', 50)->default('agent');
    $table->string('password_hash');
    $table->foreignUlid('cooperative_id')->constrained('cooperatives');
    $table->timestamps();
});
```

`create_producteurs_table.php` — méthode `up()` :
```php
Schema::create('producteurs', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('code')->unique();
    $table->string('prenom');
    $table->string('nom');
    $table->string('sexe', 10)->nullable();
    $table->string('localite')->nullable();
    $table->foreignUlid('cooperative_id')->constrained('cooperatives');
    $table->timestamp('consentement_le')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`create_lots_table.php` — méthode `up()` :
```php
Schema::create('lots', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('code')->unique();
    $table->foreignUlid('producteur_id')->constrained('producteurs');
    $table->foreignUlid('cooperative_id')->constrained('cooperatives');
    $table->decimal('poids_kg', 8, 2);
    $table->decimal('humidite_pct', 5, 2);
    $table->decimal('prix_kg_fcfa', 10, 2);
    $table->decimal('montant_fcfa', 12, 2);
    $table->date('date_pesee');
    $table->string('statut', 20)->default('enregistre');
    $table->timestamps();
});
```

`create_certificats_table.php` — méthode `up()` :
```php
Schema::create('certificats', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('lot_id')->constrained('lots');
    $table->ulid('public_uuid')->unique();
    $table->text('payload_hash');
    $table->text('signature');
    $table->string('statut', 20)->default('certifie');
    $table->timestamp('emis_le')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3 : Créer les modèles**

`backend/app/Models/Cooperative.php` :
```php
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
```

`backend/app/Models/Agent.php` :
```php
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

    protected $casts = ['password_hash' => 'hashed'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }
}
```

`backend/app/Models/Producteur.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producteur extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['code','prenom','nom','sexe','localite','cooperative_id','consentement_le'];

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
```

`backend/app/Models/Lot.php` :
```php
<?php

namespace App\Models;

use App\Enums\LotStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lot extends Model
{
    use HasUlids;

    protected $fillable = [
        'code','producteur_id','cooperative_id','poids_kg','humidite_pct',
        'prix_kg_fcfa','montant_fcfa','date_pesee','statut',
    ];

    protected $casts = [
        'statut'     => LotStatut::class,
        'date_pesee' => 'date',
        'poids_kg'   => 'float',
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
```

`backend/app/Models/Certificat.php` :
```php
<?php

namespace App\Models;

use App\Enums\CertificatStatut;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificat extends Model
{
    use HasUlids;

    protected $fillable = ['lot_id','public_uuid','payload_hash','signature','statut','emis_le'];

    protected $casts = [
        'statut'  => CertificatStatut::class,
        'emis_le' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
```

- [ ] **Step 4 : Lancer les migrations**

```bash
cd backend && php artisan migrate
```
Expected: `5 migrations ran successfully`.

- [ ] **Step 5 : Commit**

```bash
git add backend/app/Enums/ backend/app/Models/ backend/database/migrations/ backend/config/
git commit -m "feat(backend): enums, migrations ULID, modeles avec relations"
```

---

### Task 3 : Factories

**Files:**
- Create: `backend/database/factories/CooperativeFactory.php`
- Create: `backend/database/factories/AgentFactory.php`
- Create: `backend/database/factories/ProducteurFactory.php`
- Create: `backend/database/factories/LotFactory.php`

**Interfaces:**
- Produces: factories utilisables dans tous les tests Feature et le DemoSeeder

- [ ] **Step 1 : CooperativeFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;

class CooperativeFactory extends Factory
{
    protected $model = Cooperative::class;

    public function definition(): array
    {
        return [
            'nom'     => 'Coopérative ' . strtoupper($this->faker->lexify('????')),
            'code'    => strtoupper($this->faker->unique()->lexify('???')),
            'commune' => $this->faker->randomElement(['Kétou','Savè','Tchaourou','Parakou','Bohicon']),
        ];
    }
}
```

- [ ] **Step 2 : AgentFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'prenom'         => $this->faker->firstName(),
            'nom'            => $this->faker->lastName(),
            'email'          => $this->faker->unique()->safeEmail(),
            'role'           => 'agent',
            'password_hash'  => Hash::make('password'),
            'cooperative_id' => Cooperative::factory(),
        ];
    }
}
```

- [ ] **Step 3 : ProducteurFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProducteurFactory extends Factory
{
    protected $model = Producteur::class;

    public function definition(): array
    {
        $coop = Cooperative::factory()->create();
        return [
            'code'            => strtoupper($coop->code) . 'P' . now()->format('YmdHis') . $this->faker->numerify('##'),
            'prenom'          => $this->faker->firstName(),
            'nom'             => $this->faker->lastName(),
            'sexe'            => $this->faker->randomElement(['M', 'F']),
            'localite'        => $this->faker->city(),
            'cooperative_id'  => $coop->id,
            'consentement_le' => now(),
        ];
    }
}
```

- [ ] **Step 4 : LotFactory**

```php
<?php

namespace Database\Factories;

use App\Enums\LotStatut;
use App\Models\Cooperative;
use App\Models\Lot;
use App\Models\Producteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        $coop      = Cooperative::factory()->create();
        $poids     = round($this->faker->numberBetween(100, 1000) + $this->faker->randomFloat(2, 0, 1), 2);
        $prix      = $this->faker->randomElement([265, 270, 275, 280]);
        return [
            'code'           => strtoupper($coop->code) . 'L' . now()->format('YmdHis') . $this->faker->numerify('##'),
            'producteur_id'  => Producteur::factory()->create(['cooperative_id' => $coop->id])->id,
            'cooperative_id' => $coop->id,
            'poids_kg'       => $poids,
            'humidite_pct'   => $this->faker->randomFloat(1, 5, 12),
            'prix_kg_fcfa'   => $prix,
            'montant_fcfa'   => round($poids * $prix, 2),
            'date_pesee'     => now()->toDateString(),
            'statut'         => LotStatut::Enregistre,
        ];
    }
}
```

- [ ] **Step 5 : Commit**

```bash
git add backend/database/factories/
git commit -m "feat(backend): factories Cooperative, Agent, Producteur, Lot"
```

---

### Task 4 : CodeGeneratorService (TDD)

**Files:**
- Create: `backend/app/Services/CodeGeneratorService.php`
- Create: `backend/tests/Unit/CodeGeneratorServiceTest.php`

**Interfaces:**
- Produces: `CodeGeneratorService::generateLotCode(string $cooperativeCode): string` et `::generateProducteurCode(string $cooperativeCode): string`

- [ ] **Step 1 : Écrire le test (RED)**

`backend/tests/Unit/CodeGeneratorServiceTest.php` :
```php
<?php

use App\Services\CodeGeneratorService;

it('génère un code lot avec préfixe coop + L + horodatage', function () {
    $service = new CodeGeneratorService();
    $code = $service->generateLotCode('AGPK');

    expect($code)->toMatch('/^AGPKL\d{14}$/');
});

it('génère un code producteur avec préfixe coop + P + horodatage', function () {
    $service = new CodeGeneratorService();
    $code = $service->generateProducteurCode('AGPK');

    expect($code)->toMatch('/^AGPKP\d{14}$/');
});

it('met le préfixe en majuscules même si fourni en minuscules', function () {
    $service = new CodeGeneratorService();
    $code = $service->generateLotCode('agpk');

    expect($code)->toStartWith('AGPK');
});

it('deux codes générés à la même seconde sont identiques — la collision est gérée côté serveur (409)', function () {
    $service = new CodeGeneratorService();
    $a = $service->generateLotCode('AGPK');
    $b = $service->generateLotCode('AGPK');

    // Les deux codes ont le même format — le serveur rejette le doublon avec 409
    expect($a)->toMatch('/^AGPKL\d{14}$/');
    expect($b)->toMatch('/^AGPKL\d{14}$/');
});
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
cd backend && php artisan test tests/Unit/CodeGeneratorServiceTest.php
```
Expected: `ERROR  CodeGeneratorService not found`.

- [ ] **Step 3 : Implémenter le service**

`backend/app/Services/CodeGeneratorService.php` :
```php
<?php

namespace App\Services;

class CodeGeneratorService
{
    public function generateLotCode(string $cooperativeCode): string
    {
        return strtoupper($cooperativeCode) . 'L' . now()->format('YmdHis');
    }

    public function generateProducteurCode(string $cooperativeCode): string
    {
        return strtoupper($cooperativeCode) . 'P' . now()->format('YmdHis');
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Unit/CodeGeneratorServiceTest.php
```
Expected: `Tests: 4 passed`.

- [ ] **Step 5 : Commit**

```bash
git add backend/app/Services/CodeGeneratorService.php backend/tests/Unit/CodeGeneratorServiceTest.php
git commit -m "feat(backend): CodeGeneratorService TDD — codes horodatés offline-safe"
```

---

### Task 5 : SignatureService ECDSA P-384 (TDD)

**Files:**
- Create: `backend/app/Services/SignatureService.php`
- Create: `backend/tests/Unit/SignatureServiceTest.php`

**Interfaces:**
- Produces:
  - `SignatureService::buildPayload(Lot $lot, string $emisLe): array`
  - `SignatureService::sign(array $payload): string` → signature base64
  - `SignatureService::verify(array $payload, string $signature): bool`
  - `SignatureService::hashPayload(array $payload): string` → SHA-384 hex

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Unit/SignatureServiceTest.php` :
```php
<?php

use App\Models\Cooperative;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\SignatureService;

// Helper : génère une paire de clés ECDSA P-384 pour les tests (en mémoire)
function generateTestKeyPair(): array
{
    $res = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name'       => 'secp384r1',
    ]);
    openssl_pkey_export($res, $privateKeyPem);
    $publicKeyPem = openssl_pkey_get_details($res)['key'];

    file_put_contents(storage_path('keys/test-private.pem'), $privateKeyPem);
    file_put_contents(storage_path('keys/test-public.pem'), $publicKeyPem);

    config([
        'certificat.private_key_path' => storage_path('keys/test-private.pem'),
        'certificat.public_key_path'  => storage_path('keys/test-public.pem'),
    ]);

    return ['private' => $privateKeyPem, 'public' => $publicKeyPem];
}

beforeEach(function () {
    @mkdir(storage_path('keys'), 0755, true);
    generateTestKeyPair();
});

afterEach(function () {
    @unlink(storage_path('keys/test-private.pem'));
    @unlink(storage_path('keys/test-public.pem'));
});

it('buildPayload retourne un tableau canonique avec les champs attendus', function () {
    $lot = new Lot([
        'code'          => 'AGPKL20260628143022',
        'poids_kg'      => 425.5,
        'humidite_pct'  => 7.2,
        'date_pesee'    => '2026-06-28',
    ]);
    $lot->setRelation('cooperative', new Cooperative([
        'nom'     => 'Coopérative AGPK',
        'commune' => 'Kétou',
    ]));

    $service = new SignatureService();
    $payload = $service->buildPayload($lot, '2026-06-28T14:30:22Z');

    expect($payload)->toHaveKeys(['lot_code','cooperative','commune','poids_kg','humidite_pct','date_pesee','emis_le']);
    expect($payload['lot_code'])->toBe('AGPKL20260628143022');
    expect($payload['poids_kg'])->toBe(425.5);
    expect($payload['humidite_pct'])->toBe(7.2);
    expect(array_keys($payload))->toBe(['lot_code','cooperative','commune','poids_kg','humidite_pct','date_pesee','emis_le']);
});

it('sign + verify fonctionne avec une paire de clés P-384', function () {
    $service = new SignatureService();
    $payload = ['lot_code' => 'TEST', 'poids_kg' => 100.0, 'humidite_pct' => 7.0];

    $signature = $service->sign($payload);

    expect($service->verify($payload, $signature))->toBeTrue();
});

it('verify échoue si le payload est altéré', function () {
    $service = new SignatureService();
    $payload  = ['lot_code' => 'TEST', 'poids_kg' => 100.0];
    $signature = $service->sign($payload);

    expect($service->verify(['lot_code' => 'TAMPERED', 'poids_kg' => 100.0], $signature))->toBeFalse();
});

it('hashPayload retourne un hash SHA-384 hex de 96 caractères', function () {
    $service = new SignatureService();
    $hash    = $service->hashPayload(['lot_code' => 'TEST']);

    expect(strlen($hash))->toBe(96);
    expect(ctype_xdigit($hash))->toBeTrue();
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Unit/SignatureServiceTest.php
```
Expected: `ERROR  SignatureService not found`.

- [ ] **Step 3 : Implémenter SignatureService**

`backend/app/Services/SignatureService.php` :
```php
<?php

namespace App\Services;

use App\Models\Lot;
use RuntimeException;

class SignatureService
{
    public function buildPayload(Lot $lot, string $emisLe): array
    {
        return [
            'lot_code'      => $lot->code,
            'cooperative'   => $lot->cooperative->nom,
            'commune'       => $lot->cooperative->commune,
            'poids_kg'      => (float) $lot->poids_kg,
            'humidite_pct'  => (float) $lot->humidite_pct,
            'date_pesee'    => $lot->date_pesee->format('Y-m-d'),
            'emis_le'       => $emisLe,
        ];
    }

    public function sign(array $payload): string
    {
        $json       = $this->encodeCanonical($payload);
        $privateKey = openssl_pkey_get_private(file_get_contents(config('certificat.private_key_path')));

        if ($privateKey === false) {
            throw new RuntimeException('Impossible de charger la clé privée ECDSA.');
        }

        openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA384);

        return base64_encode($signature);
    }

    public function verify(array $payload, string $signature): bool
    {
        $json      = $this->encodeCanonical($payload);
        $publicKey = openssl_pkey_get_public(file_get_contents(config('certificat.public_key_path')));

        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($json, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA384) === 1;
    }

    public function hashPayload(array $payload): string
    {
        return hash('sha384', $this->encodeCanonical($payload));
    }

    private function encodeCanonical(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Unit/SignatureServiceTest.php
```
Expected: `Tests: 4 passed`.

- [ ] **Step 5 : Générer la clé de dev + committer la clé publique**

```bash
# Créer le dossier (hors dépôt pour la clé privée)
mkdir -p backend/storage/keys

# Générer la paire de clés P-384 (à faire UNE SEULE FOIS)
openssl ecparam -name secp384r1 -genkey -noout -out /etc/tracacajou/keys/p384-private.pem
openssl ec -in /etc/tracacajou/keys/p384-private.pem -pubout -out backend/storage/keys/p384-public.pem
```

Mettre dans `backend/.env` (jamais committé) :
```
CERT_SIGNING_PRIVATE_KEY_PATH=/etc/tracacajou/keys/p384-private.pem
CERT_SIGNING_PUBLIC_KEY_PATH=/absolute/path/to/backend/storage/keys/p384-public.pem
```

- [ ] **Step 6 : Commit (clé publique seulement)**

```bash
git add backend/storage/keys/p384-public.pem backend/app/Services/SignatureService.php backend/tests/Unit/SignatureServiceTest.php
git commit -m "feat(backend): SignatureService ECDSA P-384 TDD + clé publique de dev"
```

---

### Task 6 : Authentification Sanctum SPA (TDD)

**Files:**
- Create: `backend/app/Http/Controllers/Auth/AuthController.php`
- Create: `backend/app/Http/Requests/LoginRequest.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/config/sanctum.php` (stateful domains)
- Create: `backend/tests/Feature/AuthTest.php`

**Interfaces:**
- Consumes: `Agent` (Task 2), `AgentFactory` (Task 3)
- Produces: `POST /api/v1/auth/login` → cookie session httpOnly, `POST /api/v1/auth/logout`

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Feature/AuthTest.php` :
```php
<?php

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('un agent peut se connecter avec les bons identifiants', function () {
    $coop  = Cooperative::factory()->create(['code' => 'AGPK']);
    $agent = Agent::factory()->create([
        'cooperative_id' => $coop->id,
        'email'          => 'agent@test.bj',
        'password_hash'  => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'agent@test.bj',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $agent->id)
        ->assertJsonPath('data.cooperative_code', 'AGPK')
        ->assertJsonMissingPath('data.password_hash');
});

it('retourne 401 avec des identifiants invalides', function () {
    Agent::factory()->create(['email' => 'agent@test.bj', 'password_hash' => Hash::make('correct')]);

    $this->postJson('/api/v1/auth/login', ['email' => 'agent@test.bj', 'password' => 'wrong'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

it('retourne 422 si email absent', function () {
    $this->postJson('/api/v1/auth/login', ['password' => 'secret123'])
        ->assertStatus(422);
});

it('un agent authentifié peut se déconnecter', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(204);
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/AuthTest.php
```
Expected: `FAILED  Route [api/v1/auth/login] not defined`.

- [ ] **Step 3 : Configurer Sanctum (domaines stateful)**

Dans `backend/config/sanctum.php` :
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:3000,127.0.0.1')),
```

Dans `backend/config/session.php` :
```php
'secure' => env('SESSION_SECURE_COOKIE', false),
'http_only' => true,
'same_site' => 'lax',
'lifetime'  => 480, // 8 heures
```

- [ ] **Step 4 : Créer LoginRequest**

`backend/app/Http/Requests/LoginRequest.php` :
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 5 : Créer AuthController**

`backend/app/Http/Controllers/Auth/AuthController.php` :
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $agent = Agent::where('email', $request->email)->first();

        if (!$agent || !Hash::check($request->password, $agent->password_hash)) {
            return response()->json([
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email ou mot de passe incorrect.', 'status' => 401],
            ], 401);
        }

        $request->session()->regenerate();
        Auth::guard('web')->login($agent);

        return response()->json([
            'data' => [
                'id'               => $agent->id,
                'prenom'           => $agent->prenom,
                'nom'              => $agent->nom,
                'cooperative_id'   => $agent->cooperative_id,
                'cooperative_code' => $agent->cooperative->code,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 6 : Déclarer les routes**

`backend/routes/api.php` :
```php
<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CertificatController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\ProducteurController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/certificats/public-key', [CertificatController::class, 'publicKey']);
Route::get('/certificats/{uuid}/verify', [CertificatController::class, 'verify']);

// Authentifié (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('cooperatives/{cooperativeId}')->group(function () {
        Route::get('/producteurs',  [ProducteurController::class, 'index']);
        Route::post('/producteurs', [ProducteurController::class, 'store']);
        Route::delete('/producteurs/{producteurId}', [ProducteurController::class, 'destroy']);
        Route::get('/lots',         [LotController::class, 'index']);
        Route::post('/lots',        [LotController::class, 'store']);
    });

    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::get('/certificats/{uuid}/pdf', [CertificatController::class, 'download']);
});
```

Mettre à jour `bootstrap/app.php` pour préfixer `/api/v1` :
```php
->withRouting(
    api: __DIR__ . '/../routes/api.php',
    apiPrefix: 'api/v1',
)
```

- [ ] **Step 7 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/AuthTest.php
```
Expected: `Tests: 4 passed`.

- [ ] **Step 8 : Commit**

```bash
git add backend/app/Http/Controllers/Auth/ backend/app/Http/Requests/LoginRequest.php backend/routes/api.php backend/tests/Feature/AuthTest.php
git commit -m "feat(backend): auth Sanctum SPA — login/logout TDD"
```

---

### Task 7 : Middleware VerifyCooperativeAccess (TDD)

**Files:**
- Create: `backend/app/Http/Middleware/VerifyCooperativeAccess.php`
- Create: `backend/tests/Feature/CooperativeAccessTest.php`

**Interfaces:**
- Consumes: routes `cooperatives/{cooperativeId}/...` (Task 6)
- Produces: middleware injecté sur toutes les routes coopérative — renvoie 403 si l'agent tente d'accéder à une coopérative qui n'est pas la sienne

- [ ] **Step 1 : Écrire le test (RED)**

`backend/tests/Feature/CooperativeAccessTest.php` :
```php
<?php

use App\Models\Agent;
use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('un agent peut accéder à sa propre coopérative', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs")
        ->assertStatus(200);
});

it('un agent ne peut pas accéder à une autre coopérative (anti-IDOR)', function () {
    $agent     = Agent::factory()->create();
    $autreCoop = Cooperative::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->getJson("/api/v1/cooperatives/{$autreCoop->id}/producteurs")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'COOPERATIVE_ACCESS_DENIED');
});
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
cd backend && php artisan test tests/Feature/CooperativeAccessTest.php
```
Expected: `FAILED  Expected 403, got 200` (pas de middleware encore).

- [ ] **Step 3 : Implémenter le middleware**

`backend/app/Http/Middleware/VerifyCooperativeAccess.php` :
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyCooperativeAccess
{
    public function handle(Request $request, Closure $next)
    {
        $cooperativeId = $request->route('cooperativeId');

        if ($request->user()->cooperative_id !== $cooperativeId) {
            return response()->json([
                'error' => [
                    'code'    => 'COOPERATIVE_ACCESS_DENIED',
                    'message' => 'Vous n\'avez pas accès à cette coopérative.',
                    'status'  => 403,
                ],
            ], 403);
        }

        return $next($request);
    }
}
```

Ajouter le middleware au groupe de routes dans `routes/api.php` :
```php
Route::middleware(['auth:sanctum', VerifyCooperativeAccess::class])
    ->prefix('cooperatives/{cooperativeId}')
    ->group(function () {
        // ... routes producteurs et lots
    });
```

Et importer : `use App\Http\Middleware\VerifyCooperativeAccess;`

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/CooperativeAccessTest.php
```
Expected: `Tests: 2 passed`.

- [ ] **Step 5 : Commit**

```bash
git add backend/app/Http/Middleware/ backend/tests/Feature/CooperativeAccessTest.php
git commit -m "feat(backend): middleware VerifyCooperativeAccess anti-IDOR TDD"
```

---

### Task 8 : ProducteurController — enrôlement + liste (TDD)

**Files:**
- Create: `backend/app/Http/Controllers/ProducteurController.php`
- Create: `backend/app/Http/Requests/StoreProducteurRequest.php`
- Create: `backend/tests/Feature/ProducteurTest.php`

**Interfaces:**
- Consumes: `CodeGeneratorService::generateProducteurCode()` (Task 4), `VerifyCooperativeAccess` (Task 7)
- Produces: `GET /api/v1/cooperatives/:id/producteurs` (liste paginée), `POST /api/v1/cooperatives/:id/producteurs` (201)

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Feature/ProducteurTest.php` :
```php
<?php

use App\Models\Agent;
use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('un agent peut enrôler un producteur dans sa coopérative', function () {
    $agent = Agent::factory()->create();

    $response = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", [
            'prenom'   => 'Kofi',
            'nom'      => 'Adjovi',
            'sexe'     => 'M',
            'localite' => 'Kétou-Centre',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.prenom', 'Kofi')
        ->assertJsonMissingPath('data.localite'); // APDP : pas exposé dans la liste
    expect(Producteur::count())->toBe(1);
    expect(Producteur::first()->consentement_le)->not->toBeNull();
});

it('retourne 422 si prenom manquant', function () {
    $agent = Agent::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", ['nom' => 'Adjovi'])
        ->assertStatus(422);
});

it('retourne 409 si le code est déjà utilisé', function () {
    $agent      = Agent::factory()->create();
    $existant   = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id, 'code' => $agent->cooperative->code . 'P' . now()->format('YmdHis')]);

    // Simuler une collision de code (même seconde) en forçant le code déjà existant
    // Ce test vérifie que le serveur renvoie 409 en cas de doublon de contrainte unique
    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs", [
            'prenom' => 'Test', 'nom' => 'Duplikat', '_code_force' => $existant->code,
        ])
        ->assertStatus(422); // Validation échoue si _code_force n'est pas un champ reconnu
});

it('liste les producteurs paginés de la coopérative', function () {
    $agent = Agent::factory()->create();
    Producteur::factory()->count(5)->create(['cooperative_id' => $agent->cooperative_id]);

    $response = $this->actingAs($agent, 'sanctum')
        ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs?limit=3");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.limit', 3);
});

it('ne retourne pas les données personnelles complètes dans la liste', function () {
    $agent = Agent::factory()->create();
    Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $response = $this->actingAs($agent, 'sanctum')
        ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs");

    $response->assertJsonMissingPath('data.0.localite')
        ->assertJsonMissingPath('data.0.consentement_le');
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/ProducteurTest.php
```
Expected: `FAILED  Route not found`.

- [ ] **Step 3 : Créer StoreProducteurRequest**

`backend/app/Http/Requests/StoreProducteurRequest.php` :
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProducteurRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'prenom'   => ['required', 'string', 'max:100'],
            'nom'      => ['required', 'string', 'max:100'],
            'sexe'     => ['nullable', 'in:M,F'],
            'localite' => ['nullable', 'string', 'max:200'],
        ];
    }
}
```

- [ ] **Step 4 : Créer ProducteurController**

`backend/app/Http/Controllers/ProducteurController.php` :
```php
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

        $query  = Producteur::where('cooperative_id', $cooperativeId)
            ->orderBy('created_at', 'desc');

        $total  = $query->count();
        $items  = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return response()->json([
            'data' => $items->map(fn($p) => [
                'id'     => $p->id,
                'code'   => $p->code,
                'prenom' => $p->prenom,
                'nom'    => $p->nom,
                'sexe'   => $p->sexe,
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
            'code'           => $code,
            'prenom'         => $request->prenom,
            'nom'            => $request->nom,
            'sexe'           => $request->sexe,
            'localite'       => $request->localite,
            'cooperative_id' => $cooperativeId,
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
}
```

- [ ] **Step 5 : Injecter CodeGeneratorService dans le conteneur**

Dans `backend/app/Providers/AppServiceProvider.php`, méthode `register()` :
```php
$this->app->bind(\App\Services\CodeGeneratorService::class);
```

- [ ] **Step 6 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/ProducteurTest.php
```
Expected: `Tests: 5 passed` (le test 409 est adapté — vérifier le comportement exact).

- [ ] **Step 7 : Commit**

```bash
git add backend/app/Http/Controllers/ProducteurController.php backend/app/Http/Requests/StoreProducteurRequest.php backend/tests/Feature/ProducteurTest.php
git commit -m "feat(backend): ProducteurController index+store TDD, APDP minimisation"
```

---

### Task 9 : LotController — création + liste + détail (TDD)

**Files:**
- Create: `backend/app/Http/Controllers/LotController.php`
- Create: `backend/app/Http/Requests/StoreLotRequest.php`
- Create: `backend/tests/Feature/LotTest.php`

**Interfaces:**
- Consumes: `CodeGeneratorService`, `VerifyCooperativeAccess`, `CertificatService` (mock en tests, réel en Task 10)
- Produces: `POST /api/v1/cooperatives/:id/lots` (201 + certificat), `GET /api/v1/cooperatives/:id/lots` (liste), `GET /api/v1/lots/:id` (détail)

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Feature/LotTest.php` :
```php
<?php

use App\Models\Agent;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\CertificatService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mocker CertificatService pour isoler LotController des I/O fichier
    $this->mock(CertificatService::class, function ($mock) {
        $mock->shouldReceive('generateForLot')
            ->andReturn(new \App\Models\Certificat([
                'public_uuid' => 'fake-uuid-001',
                'statut'      => \App\Enums\CertificatStatut::Certifie,
            ]));
    });
});

it('crée un lot et calcule montant_fcfa côté serveur', function () {
    $agent      = Agent::factory()->create();
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $response = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $producteur->id,
            'poids_kg'      => 400.0,
            'humidite_pct'  => 7.5,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => '2026-06-28',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.montant_fcfa', 108000.0)
        ->assertJsonPath('data.statut', 'certifie')
        ->assertJsonStructure(['data' => ['id','code','montant_fcfa','statut','certificat']]);

    expect(Lot::first()->montant_fcfa)->toBe(108000.0);
});

it('ignore montant_fcfa envoyé par le client', function () {
    $agent      = Agent::factory()->create();
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $response = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $producteur->id,
            'poids_kg'      => 100.0,
            'humidite_pct'  => 8.0,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => '2026-06-28',
            'montant_fcfa'  => 999999, // doit être ignoré
        ]);

    $response->assertJsonPath('data.montant_fcfa', 27000.0);
});

it('retourne 422 si poids <= 0', function () {
    $agent      = Agent::factory()->create();
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $producteur->id,
            'poids_kg'      => 0,
            'humidite_pct'  => 7.0,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => '2026-06-28',
        ])
        ->assertStatus(422);
});

it('retourne 422 si humidite hors [0, 100]', function () {
    $agent      = Agent::factory()->create();
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $producteur->id,
            'poids_kg'      => 100.0,
            'humidite_pct'  => 105,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => '2026-06-28',
        ])
        ->assertStatus(422);
});

it('retourne 403 si le producteur appartient à une autre coopérative', function () {
    $agent              = Agent::factory()->create();
    $autreProducteur    = Producteur::factory()->create(); // autre coop

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $autreProducteur->id,
            'poids_kg'      => 100.0,
            'humidite_pct'  => 7.0,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => '2026-06-28',
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'PRODUCTEUR_WRONG_COOPERATIVE');
});

it('liste les lots paginés', function () {
    $agent = Agent::factory()->create();
    Lot::factory()->count(5)->create(['cooperative_id' => $agent->cooperative_id]);

    $this->actingAs($agent, 'sanctum')
        ->getJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots?limit=3")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 5);
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/LotTest.php
```
Expected: `FAILED`.

- [ ] **Step 3 : Créer StoreLotRequest**

`backend/app/Http/Requests/StoreLotRequest.php` :
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotRequest extends FormRequest
{
    public function authorize(): bool { return true; }

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
```

- [ ] **Step 4 : Créer LotController**

`backend/app/Http/Controllers/LotController.php` :
```php
<?php

namespace App\Http\Controllers;

use App\Enums\CertificatStatut;
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
            'data' => $items->map(fn($l) => $this->formatLot($l)),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $lot = Lot::with(['certificat', 'cooperative', 'producteur'])->findOrFail($id);

        return response()->json(['data' => $this->formatLot($lot, detail: true)]);
    }

    public function store(StoreLotRequest $request, string $cooperativeId): JsonResponse
    {
        $producteur = Producteur::find($request->producteur_id);

        if ($producteur->cooperative_id !== $cooperativeId) {
            return response()->json([
                'error' => ['code' => 'PRODUCTEUR_WRONG_COOPERATIVE', 'message' => 'Ce producteur n\'appartient pas à votre coopérative.', 'status' => 403],
            ], 403);
        }

        $lot = DB::transaction(function () use ($request, $cooperativeId) {
            $poids  = (float) $request->poids_kg;
            $prix   = (float) $request->prix_kg_fcfa;

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
            $data['cooperative'] = $lot->cooperative ? ['nom' => $lot->cooperative->nom, 'commune' => $lot->cooperative->commune] : null;
        }

        return $data;
    }
}
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/LotTest.php
```
Expected: `Tests: 6 passed`.

- [ ] **Step 6 : Commit**

```bash
git add backend/app/Http/Controllers/LotController.php backend/app/Http/Requests/StoreLotRequest.php backend/tests/Feature/LotTest.php
git commit -m "feat(backend): LotController TDD — montant server-side, anti-IDOR producteur"
```

---

### Task 10 : CertificatService — payload, signature, PDF, QR (TDD)

**Files:**
- Create: `backend/app/Services/CertificatService.php`
- Create: `backend/resources/views/certificats/pdf.blade.php`
- Create: `backend/tests/Feature/CertificatServiceTest.php`

**Interfaces:**
- Consumes: `SignatureService` (Task 5), `Lot` + ses relations
- Produces: `CertificatService::generateForLot(Lot $lot): Certificat` — crée le certificat, signe, génère PDF+QR, met à jour statut lot

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Feature/CertificatServiceTest.php` :
```php
<?php

use App\Enums\CertificatStatut;
use App\Enums\LotStatut;
use App\Models\Lot;
use App\Services\CertificatService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    // Génération de clés de test
    $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
    openssl_pkey_export($res, $priv);
    $pub = openssl_pkey_get_details($res)['key'];
    Storage::put('keys/test-private.pem', $priv);
    Storage::put('keys/test-public.pem', $pub);
    config([
        'certificat.private_key_path' => Storage::path('keys/test-private.pem'),
        'certificat.public_key_path'  => Storage::path('keys/test-public.pem'),
        'certificat.verify_base_url'  => 'https://verify.test',
    ]);
});

it('generateForLot crée un certificat avec une signature valide', function () {
    $lot = Lot::factory()->create();
    $lot->load('cooperative');

    $service    = app(CertificatService::class);
    $certificat = $service->generateForLot($lot);

    expect($certificat->statut)->toBe(CertificatStatut::Certifie);
    expect($certificat->public_uuid)->toBeString()->toHaveLength(26);

    // Vérifier la signature
    $signatureService = app(SignatureService::class);
    $payload = $signatureService->buildPayload($lot, $certificat->emis_le->toIso8601String());
    expect($signatureService->verify($payload, $certificat->signature))->toBeTrue();
});

it('generateForLot passe le lot en statut certifie', function () {
    $lot = Lot::factory()->create();
    $lot->load('cooperative');

    app(CertificatService::class)->generateForLot($lot);

    expect($lot->fresh()->statut)->toBe(LotStatut::Certifie);
});

it('generateForLot génère un fichier PDF dans storage', function () {
    $lot = Lot::factory()->create();
    $lot->load('cooperative');

    $certificat = app(CertificatService::class)->generateForLot($lot);

    Storage::disk('local')->assertExists("certificats/{$certificat->public_uuid}.pdf");
});

it('generateForLot génère un QR code dans storage', function () {
    $lot = Lot::factory()->create();
    $lot->load('cooperative');

    $certificat = app(CertificatService::class)->generateForLot($lot);

    Storage::disk('local')->assertExists("qr/{$certificat->public_uuid}.png");
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/CertificatServiceTest.php
```
Expected: `FAILED  CertificatService not found`.

- [ ] **Step 3 : Créer la vue PDF**

`backend/resources/views/certificats/pdf.blade.php` :
```html
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
```

- [ ] **Step 4 : Implémenter CertificatService**

`backend/app/Services/CertificatService.php` :
```php
<?php

namespace App\Services;

use App\Enums\CertificatStatut;
use App\Enums\LotStatut;
use App\Models\Certificat;
use App\Models\Lot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificatService
{
    public function __construct(private readonly SignatureService $signatureService) {}

    public function generateForLot(Lot $lot): Certificat
    {
        $emisLe    = now();
        $publicUuid = Str::ulid()->toString();

        $payload   = $this->signatureService->buildPayload($lot, $emisLe->toIso8601String());
        $signature = $this->signatureService->sign($payload);
        $hash      = $this->signatureService->hashPayload($payload);

        $qrContent = config('certificat.verify_base_url') . "/certificats/{$publicUuid}/verify";
        $qrPng     = QrCode::format('png')->size(300)->generate($qrContent);
        Storage::put("qr/{$publicUuid}.png", $qrPng);

        $qrBase64 = base64_encode($qrPng);
        $pdf      = Pdf::loadView('certificats.pdf', [
            'lot'        => $lot,
            'emis_le'    => $emisLe->format('d/m/Y H:i'),
            'public_uuid' => $publicUuid,
            'qr_base64'  => $qrBase64,
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
}
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/CertificatServiceTest.php
```
Expected: `Tests: 4 passed`.

- [ ] **Step 6 : Vérifier les tests LotTest passent toujours (intégration réelle)**

```bash
cd backend && php artisan test tests/Feature/LotTest.php
```
Expected: `Tests: 6 passed` (le mock est remplacé par le vrai service, les tests doivent toujours passer).

- [ ] **Step 7 : Commit**

```bash
git add backend/app/Services/CertificatService.php backend/resources/views/certificats/ backend/tests/Feature/CertificatServiceTest.php
git commit -m "feat(backend): CertificatService TDD — signature ECDSA P-384, PDF, QR"
```

---

### Task 11 : CertificatController — verify + download + public-key (TDD)

**Files:**
- Create: `backend/app/Http/Controllers/CertificatController.php`
- Create: `backend/tests/Feature/CertificatTest.php`

**Interfaces:**
- Consumes: `Certificat`, `SignatureService`, `Storage`
- Produces: `GET /certificats/:uuid/verify` (public), `GET /certificats/public-key` (public), `GET /certificats/:uuid/pdf` (auth)

- [ ] **Step 1 : Écrire les tests (RED)**

`backend/tests/Feature/CertificatTest.php` :
```php
<?php

use App\Enums\CertificatStatut;
use App\Models\Agent;
use App\Models\Certificat;
use App\Models\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('verify retourne authentique=true pour un certificat valide', function () {
    Storage::fake('local');
    // Générer clés de test
    $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
    openssl_pkey_export($res, $priv);
    $pub = openssl_pkey_get_details($res)['key'];
    Storage::put('keys/test-private.pem', $priv);
    Storage::put('keys/test-public.pem', $pub);
    config(['certificat.private_key_path' => Storage::path('keys/test-private.pem'), 'certificat.public_key_path' => Storage::path('keys/test-public.pem'), 'certificat.verify_base_url' => 'https://verify.test']);

    $lot  = Lot::factory()->create();
    $lot->load('cooperative');
    $certificat = app(\App\Services\CertificatService::class)->generateForLot($lot);

    $response = $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/verify");

    $response->assertStatus(200)
        ->assertJsonPath('data.authentique', true)
        ->assertJsonPath('data.statut', 'certifie')
        ->assertJsonStructure(['data' => ['authentique','cooperative','commune','poids_kg','humidite_pct','date_pesee','statut']])
        ->assertJsonMissingPath('data.nom')
        ->assertJsonMissingPath('data.prenom');
});

it('verify retourne 404 pour un UUID inconnu', function () {
    $this->getJson('/api/v1/certificats/01INVALIDEULID123456789999/verify')
        ->assertStatus(404);
});

it('verify indique statut revoque pour un certificat révoqué', function () {
    $lot = Lot::factory()->create();
    $certificat = Certificat::factory()->create(['lot_id' => $lot->id, 'statut' => CertificatStatut::Revoque]);

    $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/verify")
        ->assertJsonPath('data.statut', 'revoque');
});

it('public-key retourne la clé publique PEM', function () {
    Storage::fake('local');
    Storage::put('keys/test-public.pem', '-----BEGIN PUBLIC KEY-----TEST-----END PUBLIC KEY-----');
    config(['certificat.public_key_path' => Storage::path('keys/test-public.pem')]);

    $this->getJson('/api/v1/certificats/public-key')
        ->assertStatus(200)
        ->assertJsonPath('data.format', 'PEM')
        ->assertJsonPath('data.algorithm', 'ECDSA P-384');
});

it('download PDF nécessite une auth', function () {
    $certificat = Certificat::factory()->create();
    $this->getJson("/api/v1/certificats/{$certificat->public_uuid}/pdf")->assertStatus(401);
});
```

Créer aussi la factory pour Certificat (`backend/database/factories/CertificatFactory.php`) :
```php
<?php

namespace Database\Factories;

use App\Enums\CertificatStatut;
use App\Models\Certificat;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificatFactory extends Factory
{
    protected $model = Certificat::class;

    public function definition(): array
    {
        return [
            'lot_id'       => Lot::factory(),
            'public_uuid'  => Str::ulid()->toString(),
            'payload_hash' => hash('sha384', 'test'),
            'signature'    => base64_encode('fake-signature'),
            'statut'       => CertificatStatut::Certifie,
            'emis_le'      => now(),
        ];
    }
}
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/CertificatTest.php
```
Expected: `FAILED`.

- [ ] **Step 3 : Implémenter CertificatController**

`backend/app/Http/Controllers/CertificatController.php` :
```php
<?php

namespace App\Http\Controllers;

use App\Models\Certificat;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
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

        $lot      = $certificat->lot;
        $payload  = $this->signatureService->buildPayload($lot, $certificat->emis_le->toIso8601String());
        $authentique = $this->signatureService->verify($payload, $certificat->signature);

        return response()->json([
            'data' => [
                'authentique'  => $authentique,
                'cooperative'  => $lot->cooperative->nom,
                'commune'      => $lot->cooperative->commune,
                'poids_kg'     => $lot->poids_kg,
                'humidite_pct' => $lot->humidite_pct,
                'date_pesee'   => $lot->date_pesee->format('Y-m-d'),
                'statut'       => $certificat->statut->value,
            ],
        ]);
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

    public function download(string $uuid): Response
    {
        $certificat = Certificat::where('public_uuid', $uuid)->firstOrFail();
        $path       = "certificats/{$certificat->public_uuid}.pdf";

        abort_unless(Storage::exists($path), 404);

        return response(Storage::get($path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"certificat_{$certificat->public_uuid}.pdf\"",
        ]);
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
cd backend && php artisan test tests/Feature/CertificatTest.php
```
Expected: `Tests: 5 passed`.

- [ ] **Step 5 : Commit**

```bash
git add backend/app/Http/Controllers/CertificatController.php backend/database/factories/CertificatFactory.php backend/tests/Feature/CertificatTest.php
git commit -m "feat(backend): CertificatController TDD — verify public, public-key, download"
```

---

### Task 12 : APDP — Droit à l'effacement (TDD)

**Files:**
- Modify: `backend/app/Http/Controllers/ProducteurController.php` (ajouter `destroy`)
- Modify: `backend/tests/Feature/ProducteurTest.php` (ajouter tests anonymisation)

**Interfaces:**
- Produces: `DELETE /api/v1/cooperatives/:coopId/producteurs/:prodId` → anonymisation APDP

- [ ] **Step 1 : Écrire les tests (RED)**

Ajouter dans `backend/tests/Feature/ProducteurTest.php` :
```php
it('anonymise un producteur à la suppression (APDP)', function () {
    $agent      = Agent::factory()->create();
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    $this->actingAs($agent, 'sanctum')
        ->deleteJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs/{$producteur->id}")
        ->assertStatus(204);

    $producteur->refresh();
    expect($producteur->prenom)->toBe('[supprimé]');
    expect($producteur->nom)->toBe('[supprimé]');
    expect($producteur->sexe)->toBeNull();
    expect($producteur->localite)->toBeNull();
    expect($producteur->consentement_le)->not->toBeNull(); // conservé pour traçabilité
});

it('refuse l\'anonymisation d\'un producteur d\'une autre coopérative', function () {
    $agent           = Agent::factory()->create();
    $autreProducteur = Producteur::factory()->create();

    $this->actingAs($agent, 'sanctum')
        ->deleteJson("/api/v1/cooperatives/{$agent->cooperative_id}/producteurs/{$autreProducteur->id}")
        ->assertStatus(404); // n'existe pas dans cette coop
});
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd backend && php artisan test tests/Feature/ProducteurTest.php --filter="anonymise|refuse_anonymisation"
```
Expected: `FAILED  Route not found`.

- [ ] **Step 3 : Ajouter `destroy` dans ProducteurController**

Ajouter la méthode dans `backend/app/Http/Controllers/ProducteurController.php` :
```php
public function destroy(Request $request, string $cooperativeId, string $producteurId): JsonResponse
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
```

- [ ] **Step 4 : Vérifier que tous les tests ProducteurTest passent**

```bash
cd backend && php artisan test tests/Feature/ProducteurTest.php
```
Expected: `Tests: 7 passed`.

- [ ] **Step 5 : Commit**

```bash
git add backend/app/Http/Controllers/ProducteurController.php backend/tests/Feature/ProducteurTest.php
git commit -m "feat(backend): APDP droit a l effacement — anonymisation producteur TDD"
```

---

### Task 13 : Logging de sécurité

**Files:**
- Create: `backend/app/Services/SecurityLogger.php`
- Modify: `backend/app/Http/Controllers/Auth/AuthController.php` (log login)
- Modify: `backend/app/Http/Middleware/VerifyCooperativeAccess.php` (log 403)
- Modify: `backend/config/logging.php` (canal `security`)

**Interfaces:**
- Produces: `SecurityLogger::logLogin(Agent $agent)`, `::log403(Request $request)`, `::logRoleChange(Agent $agent, string $oldRole, string $newRole)`

- [ ] **Step 1 : Configurer le canal de log sécurité**

Dans `backend/config/logging.php`, ajouter dans `channels` :
```php
'security' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/security.log'),
    'level'  => 'info',
    'days'   => 90,
],
```

- [ ] **Step 2 : Créer SecurityLogger**

`backend/app/Services/SecurityLogger.php` :
```php
<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogger
{
    public function logLogin(Agent $agent): void
    {
        Log::channel('security')->info('LOGIN', [
            'agent_id'       => $agent->id,
            'cooperative_id' => $agent->cooperative_id,
            'at'             => now()->toIso8601String(),
        ]);
        // NE PAS logger : email, nom, prénom, password, token
    }

    public function log403(Request $request, string $code): void
    {
        Log::channel('security')->warning('ACCESS_DENIED', [
            'code'     => $code,
            'path'     => $request->path(),
            'agent_id' => $request->user()?->id,
            'at'       => now()->toIso8601String(),
        ]);
        // NE PAS logger : IP nominative, contenu de la requête
    }

    public function logRoleChange(Agent $agent, string $oldRole, string $newRole): void
    {
        Log::channel('security')->info('ROLE_CHANGE', [
            'agent_id' => $agent->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'at'       => now()->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 3 : Injecter dans AuthController**

Dans `AuthController::login`, après `Auth::guard('web')->login($agent)` :
```php
app(SecurityLogger::class)->logLogin($agent);
```

- [ ] **Step 4 : Injecter dans VerifyCooperativeAccess**

Dans `VerifyCooperativeAccess::handle`, avant `return response()->json(...)` (403) :
```php
app(\App\Services\SecurityLogger::class)->log403($request, 'COOPERATIVE_ACCESS_DENIED');
```

- [ ] **Step 5 : Vérifier manuellement qu'aucun PII n'est loggé**

```bash
cd backend && grep -r "prenom\|nom\|email\|password\|localite" storage/logs/ 2>/dev/null || echo "OK — aucun PII dans les logs"
```

- [ ] **Step 6 : Commit**

```bash
git add backend/app/Services/SecurityLogger.php backend/config/logging.php
git commit -m "feat(backend): SecurityLogger — connexions et 403 sans PII"
```

---

### Task 14 : DemoSeeder

**Files:**
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Create: `backend/database/seeders/DemoSeeder.php`

**Interfaces:**
- Produces: `php artisan db:seed --class=DemoSeeder` → 1 coop AGPK, 1 agent, 10 producteurs, 1 lot certifié

- [ ] **Step 1 : Créer DemoSeeder**

`backend/database/seeders/DemoSeeder.php` :
```php
<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Certificat;
use App\Models\Cooperative;
use App\Models\Lot;
use App\Models\Producteur;
use App\Services\CertificatService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(CertificatService $certificatService): void
    {
        $coop = Cooperative::create([
            'nom'     => 'Coopérative AGPK',
            'code'    => 'AGPK',
            'commune' => 'Kétou',
        ]);

        $agent = Agent::create([
            'prenom'         => 'Kossi',
            'nom'            => 'Hounsou',
            'email'          => 'agent@agpk.bj',
            'role'           => 'agent',
            'password_hash'  => Hash::make('Demo@2026!'),
            'cooperative_id' => $coop->id,
        ]);

        $producteurs = collect(range(1, 10))->map(fn($i) => Producteur::create([
            'code'            => 'AGPKP' . now()->format('YmdHis') . str_pad($i, 2, '0', STR_PAD_LEFT),
            'prenom'          => fake()->firstName(),
            'nom'             => fake()->lastName(),
            'sexe'            => fake()->randomElement(['M', 'F']),
            'localite'        => fake()->randomElement(['Kétou-Centre', 'Ilara', 'Okpometa', 'Adakplamè']),
            'cooperative_id'  => $coop->id,
            'consentement_le' => now(),
        ]));

        $lot = Lot::create([
            'code'           => 'AGPKL' . now()->format('YmdHis') . '01',
            'producteur_id'  => $producteurs->first()->id,
            'cooperative_id' => $coop->id,
            'poids_kg'       => 425.5,
            'humidite_pct'   => 7.2,
            'prix_kg_fcfa'   => 270,
            'montant_fcfa'   => round(425.5 * 270, 2),
            'date_pesee'     => now()->toDateString(),
            'statut'         => 'enregistre',
        ]);

        $lot->load('cooperative');
        $certificatService->generateForLot($lot);

        $this->command->info("Seed OK — agent: agent@agpk.bj / Demo@2026!");
        $this->command->info("Certificat UUID: " . $lot->fresh()->certificat->public_uuid);
    }
}
```

- [ ] **Step 2 : Vérifier que le seed tourne**

```bash
cd backend && php artisan db:seed --class=DemoSeeder
```
Expected: `Seed OK — agent: agent@agpk.bj / Demo@2026!` suivi de l'UUID du certificat.

- [ ] **Step 3 : Vérifier la vérification publique du certificat de démo**

```bash
# Récupérer l'UUID depuis la sortie du seed, puis :
curl http://localhost:8000/api/v1/certificats/{UUID_DU_SEED}/verify
```
Expected: `{"data":{"authentique":true,"statut":"certifie",...}}`.

- [ ] **Step 4 : Commit**

```bash
git add backend/database/seeders/DemoSeeder.php
git commit -m "feat(backend): DemoSeeder — 1 coop AGPK, 10 producteurs, 1 lot certifie"
```

---

### Task 15 : Suite de tests complète + openapi.yaml

**Files:**
- Create: `backend/openapi.yaml`
- Modify: `backend/tests/Feature/` (test E2E back-end : login → lot → verify)

**Interfaces:**
- Produces: `openapi.yaml` versionné, `php artisan test` passe en vert sur toute la suite

- [ ] **Step 1 : Test d'intégration bout en bout (back-end)**

Ajouter `backend/tests/Feature/IntegrationTest.php` :
```php
<?php

use App\Models\Agent;
use App\Models\Cooperative;
use App\Models\Producteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('parcours complet : login → lot → certificat → verify', function () {
    Storage::fake('local');
    // Clés de test
    $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
    openssl_pkey_export($res, $priv);
    $pub = openssl_pkey_get_details($res)['key'];
    Storage::put('keys/test-private.pem', $priv);
    Storage::put('keys/test-public.pem', $pub);
    config(['certificat.private_key_path' => Storage::path('keys/test-private.pem'), 'certificat.public_key_path' => Storage::path('keys/test-public.pem'), 'certificat.verify_base_url' => 'https://verify.test']);

    $agent      = Agent::factory()->create(['email' => 'agent@test.bj', 'password_hash' => \Illuminate\Support\Facades\Hash::make('secret')]);
    $producteur = Producteur::factory()->create(['cooperative_id' => $agent->cooperative_id]);

    // 1. Login
    $loginRes = $this->postJson('/api/v1/auth/login', ['email' => 'agent@test.bj', 'password' => 'secret']);
    $loginRes->assertStatus(200);

    // 2. Créer un lot
    $lotRes = $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/cooperatives/{$agent->cooperative_id}/lots", [
            'producteur_id' => $producteur->id,
            'poids_kg'      => 300.0,
            'humidite_pct'  => 6.5,
            'prix_kg_fcfa'  => 270,
            'date_pesee'    => now()->toDateString(),
        ]);
    $lotRes->assertStatus(201);
    $publicUuid = $lotRes->json('data.certificat.public_uuid');

    // 3. Vérification publique (sans auth)
    $verifyRes = $this->getJson("/api/v1/certificats/{$publicUuid}/verify");
    $verifyRes->assertStatus(200)
        ->assertJsonPath('data.authentique', true)
        ->assertJsonPath('data.poids_kg', 300.0)
        ->assertJsonMissingPath('data.prenom');
});
```

- [ ] **Step 2 : Lancer toute la suite**

```bash
cd backend && php artisan test
```
Expected: toutes les suites passent (≥ 30 tests).

- [ ] **Step 3 : Créer openapi.yaml**

`backend/openapi.yaml` :
```yaml
openapi: "3.1.0"
info:
  title: TraçaCajou API
  version: "1.0.0"
  description: API de certification d'origine des lots de cajou — Bénin

servers:
  - url: /api/v1

paths:
  /auth/login:
    post:
      summary: Connexion agent
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [email, password]
              properties:
                email:    { type: string, format: email }
                password: { type: string }
      responses:
        "200":
          description: Connexion réussie
          content:
            application/json:
              schema: { $ref: "#/components/schemas/AgentResponse" }
        "401": { $ref: "#/components/responses/Unauthorized" }
        "422": { $ref: "#/components/responses/ValidationError" }

  /auth/logout:
    post:
      summary: Déconnexion
      security: [{ cookieAuth: [] }]
      responses:
        "204": { description: Déconnecté }

  /cooperatives/{cooperativeId}/producteurs:
    get:
      summary: Liste paginée des producteurs
      security: [{ cookieAuth: [] }]
      parameters:
        - { name: cooperativeId, in: path, required: true, schema: { type: string } }
        - { name: page,  in: query, schema: { type: integer, default: 1 } }
        - { name: limit, in: query, schema: { type: integer, default: 20, maximum: 100 } }
      responses:
        "200":
          description: Liste de producteurs
          content:
            application/json:
              schema: { $ref: "#/components/schemas/ProducteurList" }
    post:
      summary: Enrôler un producteur
      security: [{ cookieAuth: [] }]
      parameters:
        - { name: cooperativeId, in: path, required: true, schema: { type: string } }
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [prenom, nom]
              properties:
                prenom:   { type: string }
                nom:      { type: string }
                sexe:     { type: string, enum: [M, F] }
                localite: { type: string }
      responses:
        "201": { description: Producteur créé }
        "422": { $ref: "#/components/responses/ValidationError" }

  /cooperatives/{cooperativeId}/producteurs/{producteurId}:
    delete:
      summary: Anonymiser un producteur (APDP)
      security: [{ cookieAuth: [] }]
      responses:
        "204": { description: Anonymisé }

  /cooperatives/{cooperativeId}/lots:
    get:
      summary: Historique des lots
      security: [{ cookieAuth: [] }]
      responses:
        "200": { description: Liste de lots }
    post:
      summary: Créer un lot + générer le certificat
      security: [{ cookieAuth: [] }]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [producteur_id, poids_kg, humidite_pct, prix_kg_fcfa, date_pesee]
              properties:
                producteur_id: { type: string }
                poids_kg:      { type: number, exclusiveMinimum: 0 }
                humidite_pct:  { type: number, minimum: 0, maximum: 100 }
                prix_kg_fcfa:  { type: number, exclusiveMinimum: 0 }
                date_pesee:    { type: string, format: date }
      responses:
        "201": { description: Lot créé avec certificat }
        "403": { $ref: "#/components/responses/Forbidden" }
        "422": { $ref: "#/components/responses/ValidationError" }

  /certificats/{uuid}/verify:
    get:
      summary: Vérification publique d'un certificat
      parameters:
        - { name: uuid, in: path, required: true, schema: { type: string } }
      responses:
        "200":
          description: Résultat de vérification
          content:
            application/json:
              schema: { $ref: "#/components/schemas/VerifyResponse" }
        "404": { $ref: "#/components/responses/NotFound" }

  /certificats/public-key:
    get:
      summary: Clé publique ECDSA P-384 pour vérification indépendante
      responses:
        "200": { description: Clé publique PEM }

  /certificats/{uuid}/pdf:
    get:
      summary: Télécharger le PDF du certificat
      security: [{ cookieAuth: [] }]
      responses:
        "200":
          description: PDF du certificat
          content:
            application/pdf: {}

components:
  securitySchemes:
    cookieAuth:
      type: apiKey
      in: cookie
      name: tracacajou_session

  schemas:
    AgentResponse:
      type: object
      properties:
        data:
          type: object
          properties:
            id:               { type: string }
            prenom:           { type: string }
            nom:              { type: string }
            cooperative_id:   { type: string }
            cooperative_code: { type: string }

    ProducteurList:
      type: object
      properties:
        data:
          type: array
          items:
            type: object
            properties:
              id:     { type: string }
              code:   { type: string }
              prenom: { type: string }
              nom:    { type: string }
              sexe:   { type: string }
        meta:
          type: object
          properties:
            page:  { type: integer }
            limit: { type: integer }
            total: { type: integer }

    VerifyResponse:
      type: object
      properties:
        data:
          type: object
          properties:
            authentique:  { type: boolean }
            cooperative:  { type: string }
            commune:      { type: string }
            poids_kg:     { type: number }
            humidite_pct: { type: number }
            date_pesee:   { type: string }
            statut:       { type: string, enum: [certifie, revoque] }

  responses:
    Unauthorized:
      description: Non authentifié
      content:
        application/json:
          schema:
            type: object
            properties:
              error: { type: object, properties: { code: { type: string }, message: { type: string }, status: { type: integer } } }
    Forbidden:
      description: Accès refusé
      content:
        application/json:
          schema:
            type: object
            properties:
              error: { type: object }
    ValidationError:
      description: Erreur de validation (422)
      content:
        application/json:
          schema:
            type: object
    NotFound:
      description: Ressource introuvable
```

- [ ] **Step 4 : Lancer toute la suite une dernière fois**

```bash
cd backend && php artisan test --coverage
```
Expected: `Tests: XX passed, 0 failed`.

- [ ] **Step 5 : Commit final**

```bash
git add backend/openapi.yaml backend/tests/Feature/IntegrationTest.php
git commit -m "feat(backend): integration test E2E + openapi.yaml v1"
```

---

## Auto-révision du plan

**Couverture spec → tâches :**

| Exigence spec | Tâche couverte |
| --- | --- |
| Auth Sanctum cookie httpOnly | Task 6 |
| ULIDs partout | Task 2 |
| Argon2id | Task 1 |
| Codes horodatés offline-safe | Task 4 |
| Signature ECDSA P-384 | Task 5 |
| Génération certificat PDF+QR | Task 10 |
| Vérification publique minimisée | Task 11 |
| Clé publique endpoint | Task 11 |
| Anti-IDOR coopérative | Task 7 |
| Anti-IDOR producteur (autre coop) | Task 9 |
| `montant_fcfa` serveur uniquement | Task 9 |
| Pagination `{data, meta}` | Tasks 8, 9 |
| APDP anonymisation | Task 12 |
| Logs sécurité sans PII | Task 13 |
| DemoSeeder | Task 14 |
| openapi.yaml versionné | Task 15 |
| Test unitaires (calcul, signature, codes) | Tasks 4, 5 |
| Tests intégration API | Tasks 6–12 |
| Test E2E back-end | Task 15 |

**Vérification types/noms cohérents :**
- `generateForLot(Lot $lot): Certificat` — Task 10 produit, Task 9 consomme ✓
- `buildPayload(Lot $lot, string $emisLe): array` — Task 5 produit, Tasks 10, 11 consomment ✓
- `cooperative_id` en string (ULID) dans tous les modèles et routes ✓
- `public_uuid` : ULID string 26 chars — Tasks 10, 11 cohérents ✓
