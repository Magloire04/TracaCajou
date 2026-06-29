# TraçaCajou

Système de certification d'origine numérique pour la filière anacarde au Bénin.

Chaque lot de noix de cajou reçoit un **certificat signé (ECDSA P-384)**, vérifiable publiquement par QR code — pour rendre la commercialisation transparente, lutter contre la contrebande et garantir un juste prix au producteur.

---

## Fonctionnalités MVP

| Fonctionnalité | Description |
| --- | --- |
| Authentification agent | Cookie `httpOnly`/`secure`, session 8h (Argon2id) |
| Enrôlement producteur | Champs minimisés + consentement APDP obligatoire |
| Enregistrement lot | Poids, humidité, prix/kg — montant calculé côté serveur |
| Certificat signé | ECDSA P-384, PDF + QR code imprimable |
| Vérification publique | `GET /api/v1/certificats/:uuid/verify` — sans compte, sans donnée nominative |
| Historique coopérative | Liste paginée des lots |
| PWA offline-first | Saisie terrain sans réseau, synchronisation différée *(Phase 2)* |

---

## Stack technique

| Couche | Technologie |
| --- | --- |
| Back-end | Laravel 12 · PHP 8.2+ |
| Base de données | MySQL (dev) · SQLite en mémoire (tests) |
| Authentification | Laravel Sanctum SPA |
| Signature certificats | OpenSSL ECDSA P-384 (natif PHP) |
| Génération PDF | barryvdh/laravel-dompdf |
| QR code | GD natif PHP |
| Front-end | Nuxt 3 / Vue 3 PWA *(Phase 2)* |

---

## Installation

### Prérequis

- PHP 8.2+
- Composer
- MySQL 8+ (ou MariaDB 10.6+)
- OpenSSL (pour la génération de la paire de clés)

### 1. Cloner le dépôt

```bash
git clone https://github.com/Magloire04/TracaCajou.git
cd TracaCajou/backend
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :

```env
DB_CONNECTION=mysql
DB_DATABASE=tracacajou
DB_USERNAME=<utilisateur>
DB_PASSWORD=<mot_de_passe>

CERT_SIGNING_PRIVATE_KEY_PATH=/chemin/vers/p384-private.pem
CERT_SIGNING_PUBLIC_KEY_PATH=/chemin/vers/backend/storage/keys/p384-public.pem
CERT_PUBLIC_VERIFY_BASE_URL=https://votre-domaine.bj
```

### 4. Générer la paire de clés ECDSA P-384

```bash
# Clé privée (NE PAS committer — hors dépôt)
openssl ecparam -name secp384r1 -genkey -noout -out p384-private.pem

# Clé publique (déjà committée pour la vérification indépendante)
openssl ec -in p384-private.pem -pubout -out backend/storage/keys/p384-public.pem
```

### 5. Migrer et seeder

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

### 6. Lancer le serveur

```bash
php artisan serve
```

---

## Scénario de démo (pitch)

```bash
php artisan migrate:fresh
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Le seeder crée automatiquement :
- **1 coopérative** : AGPK (Kétou)
- **1 agent** : `agent@agpk.bj` / `Demo@2026!`
- **10 producteurs** béninois avec consentement APDP
- **1 lot certifié** (425,5 kg · 7,2 % humidité · 270 FCFA/kg)

L'UUID du certificat et l'URL de vérification sont affichés en sortie.

---

## Tests

```bash
cd backend
php artisan test
```

**41 tests** · 108 assertions · couverture unitaire + intégration + E2E

| Suite | Couverture |
| --- | --- |
| Unitaires | CodeGeneratorService, SignatureService (ECDSA, hash, payload) |
| Intégration | Tous les endpoints API (auth, producteurs, lots, certificats) |
| E2E | Login → lot → certificat → vérification publique |

---

## API (extrait)

| Méthode | Route | Auth | Description |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | — | Connexion agent |
| `POST` | `/api/v1/cooperatives/:id/producteurs` | ✓ | Enrôler un producteur |
| `POST` | `/api/v1/cooperatives/:id/lots` | ✓ | Créer un lot + générer le certificat |
| `GET` | `/api/v1/certificats/:uuid/verify` | — | Vérification publique (données minimisées) |
| `GET` | `/api/v1/certificats/public-key` | — | Clé publique ECDSA pour vérification indépendante |
| `DELETE` | `/api/v1/cooperatives/:id/producteurs/:id` | ✓ | Anonymisation APDP |

Contrat complet : [`backend/openapi.yaml`](backend/openapi.yaml)

---

## Sécurité & conformité APDP

- **Anti-IDOR** : chaque requête vérifie l'appartenance de l'agent à la coopérative ciblée
- **APDP (loi n° 2017-20)** : consentement explicite à l'enrôlement, anonymisation sur demande, endpoint public sans données nominatives
- **Certificat immuable** : toute correction = nouveau certificat ; l'ancien est marqué `revoqué`
- **Logs** : connexions et accès refusés uniquement — aucune donnée personnelle

---

## Structure du dépôt

```
TracaCajou/
├── backend/              # API Laravel 12
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   └── Services/     # SignatureService, CertificatService, ...
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/      # DemoSeeder
│   ├── storage/keys/     # Clé publique P-384 (committée)
│   ├── tests/
│   └── openapi.yaml      # Contrat API OpenAPI 3.1
├── docs/
│   └── superpowers/
│       ├── specs/        # Spécification MVP validée
│       └── plans/        # Plan d'implémentation back-end
├── CLAUDE.md             # Conventions de développement
└── GOAL.md               # Critères de fin (Definition of Done)
```

---

## Feuille de route

| Phase | Contenu | Statut |
| --- | --- | --- |
| **Phase 1 — Back-end** | API REST · certificats ECDSA · APDP | ✅ Livré |
| **Phase 2 — Front-end** | PWA Nuxt 3 · offline-first · sync queue | 🔜 À venir |

---

*Projet développé dans le cadre de la filière anacarde au Bénin — transparence, traçabilité, juste prix.*
