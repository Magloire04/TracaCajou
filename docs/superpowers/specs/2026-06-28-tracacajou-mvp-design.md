# SPEC — TraçaCajou MVP (design)

*Date : 2026-06-28 · Statut : **VALIDÉE** — décisions tranchées via brainstorming Superpowers le 2026-06-28.*

> Cette spec dit **QUOI** construire (comportement, règles, cas limites, hors périmètre). Le **COMMENT** détaillé (tâches, fichiers, code) viendra du PLAN.

## 1. Problème & objectif

La commercialisation du cajou est opaque (pesée/prix contestés) et une partie de la récolte fuit en contrebande depuis l'interdiction d'export de la noix brute. **TraçaCajou** trace chaque lot du producteur au transformateur et émet un **certificat d'origine vérifiable** (signé, QR), pour rendre la filière transparente et conforme.

## 2. Acteurs

- **Agent de coopérative** — enrôle les producteurs, enregistre les lots, délivre les certificats.
- **Producteur** — bénéficiaire ; donne son consentement ; reçoit un reçu/QR.
- **Vérificateur public** (transformateur, acheteur, contrôleur) — scanne un QR pour vérifier un lot, **sans compte**.

## 3. Parcours principal (happy path)

1. L'agent se connecte (cookie `httpOnly`/`secure`, session 8h).
2. L'agent enrôle un producteur (s'il n'existe pas) : prénom, nom, sexe, localité, **consentement** coché manuellement (case non pré-cochée).
3. L'agent enregistre un lot : producteur, poids (kg), humidité (%), prix homologué (FCFA/kg), date de pesée.
4. Le serveur calcule `montant_fcfa = poids_kg × prix_kg_fcfa`, crée le lot, puis **génère le certificat** : payload canonique → signature ECDSA P-384 → ULID public → rendu PDF + QR.
5. Le QR encode l'URL publique `…/certificats/{public_uuid}/verify`.
6. Un vérificateur scanne : la page publique affiche **authentique / non authentique** + données minimisées (coopérative, commune, poids, humidité, date, statut).

## 4. Règles métier

- `montant_fcfa` est **toujours** calculé serveur (jamais reçu du client).
- `humidite_pct` est **obligatoire** à la saisie (plage valide : 0–100).
- Un certificat est **immuable**. Correction = nouveau certificat ; l'ancien passe `revoque` (jamais supprimé).
- Le `public_uuid` d'un certificat est un ULID aléatoire, non devinable, et **ne contient aucune donnée personnelle**.
- Un lot a exactement un producteur et une coopérative ; un agent n'agit que sur **sa** coopérative (contrôle d'accès serveur, anti-IDOR).
- Statuts du lot : `enregistre` → `certifie` → (`revoque`).

## 5. Modèle de données (minimisation APDP)

Tous les identifiants internes sont des **ULID** (triables chronologiquement, offline-safe).

```text
cooperatives : id (ULID) · nom · code (3–5 car., ex. "AGPK") · commune
agents       : id (ULID) · prenom · nom · role · cooperative_id · password_hash (Argon2id)
producteurs  : id (ULID) · code · prenom · nom · sexe · localite
               cooperative_id · consentement_le (timestamp)
lots         : id (ULID) · code · producteur_id · cooperative_id
               poids_kg · humidite_pct · prix_kg_fcfa · montant_fcfa
               date_pesee · statut
certificats  : id (ULID) · lot_id · public_uuid (ULID) · payload_hash
               signature · emis_le · statut
```

**Codes lisibles auto-générés côté client (offline-safe) :**

| Entité | Format | Exemple |
| --- | --- | --- |
| Producteur | `{COOP_CODE}P{YYYYMMDDHHMMSS}` | `AGPKP20260628143022` |
| Lot | `{COOP_CODE}L{YYYYMMDDHHMMSS}` | `AGPKL20260628143022` |

`COOP_CODE` est fourni à l'agent lors de la connexion. En cas de collision (même seconde), le serveur renvoie `409` et le client incrémente d'une seconde.

## 6. API (enveloppe `{data|meta|error}`, `/api/v1`)

| Méthode | Route | Auth | Rôle |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/login` | non | Connexion agent (pose cookie httpOnly/secure) |
| POST | `/api/v1/auth/logout` | oui | Déconnexion |
| GET | `/api/v1/cooperatives/:id/producteurs` | oui | Liste paginée des producteurs |
| POST | `/api/v1/cooperatives/:id/producteurs` | oui | Enrôler un producteur |
| GET | `/api/v1/cooperatives/:id/lots` | oui | Historique des lots (paginé) |
| POST | `/api/v1/cooperatives/:id/lots` | oui | Créer un lot **+ générer le certificat** |
| GET | `/api/v1/lots/:id` | oui | Détail d'un lot (champs minimisés) |
| GET | `/api/v1/certificats/:uuid/verify` | **non** | Vérification publique (signature + données minimisées) |
| GET | `/api/v1/certificats/public-key` | **non** | Clé publique PEM pour vérification indépendante |

**Pagination :** `?page` (défaut 1), `?limit` (défaut 20, max 100), `?sort_by`, `?order`.

**Codes HTTP :** 201 (création), 200 (lecture/vérif), 204 (logout), 401/403 (auth/accès), 404, 409 (doublon code), 422 (validation métier), 500 (générique en prod).

**Réponse `verify` (APDP-conforme — aucune donnée nominative) :**
```json
{
  "data": {
    "authentique": true,
    "cooperative": "Coopérative AGPK",
    "commune": "Kétou",
    "poids_kg": 425.5,
    "humidite_pct": 7.2,
    "date_pesee": "2026-06-28",
    "statut": "certifie"
  }
}
```

## 7. Certificat & signature

**Payload canonique signé (ordre stable, champs fixes) :**
```json
{
  "lot_code": "AGPKL20260628143022",
  "cooperative": "Coopérative AGPK",
  "commune": "Kétou",
  "poids_kg": 425.5,
  "humidite_pct": 7.2,
  "date_pesee": "2026-06-28",
  "emis_le": "2026-06-28T14:30:22Z"
}
```

**Signature :** hash SHA-384 du payload JSON canonique → signature ECDSA P-384.

**Gestion de la clé privée (MVP) :**
- Clé générée ex nihilo avec `openssl ecparam -name secp384r1 -genkey -noout -out tracacajou_private.pem`.
- Stockée hors dépôt Git (`.gitignore` strict), chemin chargé via `CERT_PRIVATE_KEY_PATH` dans `.env`.
- Clé publique dérivée committée dans `storage/keys/tracacajou_public.pem` (non secrète).
- **Risque documenté :** compromission du VPS = révocation de tous les certificats émis. Migration SoftHSM2 planifiée en phase 2.

**Rendu :** PDF (récapitulatif lisible + QR imprimable) et QR seul (pour étiquette terrain).

**Vérification :** recalcul du hash + validation de la signature côté serveur → `authentique: true/false`. Si `statut = revoque`, l'indiquer explicitement.

## 8. Offline-first (PWA)

**Stockage local :** IndexedDB via **Dexie.js** (deux tables locales) :

```text
lotsEnAttente : id (ULID client) · code · producteur_id · cooperative_id
                poids_kg · humidite_pct · prix_kg_fcfa · date_pesee
                statut ("en_attente"|"en_cours"|"erreur") · tentatives · cree_le
producteurs   : cache lecture-seule, mis à jour à la connexion
```

**Stratégie de synchronisation (hybride) :**
- **Auto :** dès que `navigator.onLine` passe à `true`, la `SyncQueue` pousse les lots en attente (1 à la fois, séquentiel).
- **Manuel :** bouton « Synchroniser maintenant » visible dès qu'un lot est en attente (badge avec compteur).
- **Erreur réseau :** max 3 tentatives par lot, puis statut `erreur` + alerte agent.
- **409 (doublon) :** lot retiré silencieusement (déjà synchronisé, pas d'erreur).

**Service Worker (Nuxt PWA) :** cache statique pour les assets, stratégie `NetworkFirst` pour les appels API, fallback offline gracieux avec message d'état.

**Génération du certificat :** toujours côté serveur (la signature nécessite la clé privée). Un lot offline est `enregistre` jusqu'à la synchro, puis passe `certifie` avec le certificat associé.

## 9. Sécurité

**Authentification :**
- Mots de passe hachés avec **Argon2id** (Laravel Argon2 driver).
- JWT en cookie `httpOnly` + `secure` + `SameSite=Strict`. Durée : **8h**. Refresh silencieux toutes les **2h** si actif.
- À chaque requête API : vérification token valide **ET** appartenance de l'agent à la coopérative de la ressource ciblée (anti-IDOR).

**Injections & XSS :**
- Requêtes paramétrées via Eloquent ORM exclusivement — zéro concaténation d'entrées externes.
- Échappement systématique côté Vue ; `v-html` interdit sur du contenu non contrôlé.

**Secrets :**
- Clé privée ECDSA hors dépôt (`CERT_PRIVATE_KEY_PATH` en `.env`).
- Aucun secret committé ; `.env.example` avec valeurs fictives versionné.
- Erreurs prod génériques (stack trace jamais exposée).

**Logs de sécurité :**
Enregistrer : connexions, 403, changements de rôle.
Ne jamais logger : mot de passe, JWT, nom/prénom, localité, IP nominative.

## 10. APDP — loi n° 2017-20

| Exigence | Implémentation |
| --- | --- |
| Minimisation | Chaque réponse liste explicitement les champs retournés (jamais `Model::find()` brut) |
| Consentement | Case non pré-cochée à l'enrôlement ; `consentement_le` horodaté |
| Vérification publique | Commune seulement (pas localité précise), aucun nom dans `/verify` |
| Droit à l'effacement | `DELETE /api/v1/cooperatives/:id/producteurs/:id` → anonymisation : `prenom`/`nom` → `"[supprimé]"`, `localite` → `null`, `sexe` → `null` ; `consentement_le` conservé (traçabilité légale) |
| Logs | Aucune donnée personnelle dans les logs ni dans l'URL publique de vérification |

## 11. Tests (TDD strict — RED → GREEN → refactor)

| Couche | Ce qu'on teste | Outil |
| --- | --- | --- |
| Unitaires | `calculateMontant()`, `generateCertificatePayload()`, `verifySignature()`, validations (poids/humidité/prix > 0, humidité ∈ [0,100]), génération code horodatage | Pest (back) / Vitest (front) |
| Intégration | Chaque endpoint : codes HTTP, anti-IDOR, minimisation des champs, cas limites (409, 422, 403) | Pest + Laravel HTTP tests (SQLite en mémoire) |
| E2E | Login → enregistrement lot → certificat généré → QR scanné → vérification publique | Playwright |

**Seed de démo :**
```bash
php artisan db:seed --class=DemoSeeder
```
Crée : 1 coopérative `AGPK`, 1 agent, 10 producteurs avec consentement, ≥ 1 lot certifié avec QR scannable — scénario de pitch exécutable en une commande.

## 12. Cas limites & erreurs

- Poids ou prix ≤ 0, humidité hors [0–100] → 422.
- Producteur d'une autre coopérative → 403.
- UUID de vérification inconnu → 404.
- Re-soumission d'un code lot déjà existant → 409 (pas de doublon).
- Tentative de modifier un certificat émis → refus 422 ; proposer révocation + réémission.
- Reconnexion réseau instable pendant synchro → reprise au prochain événement `online`.

## 13. Hors périmètre MVP

Mobile money, USSD/SMS, dashboard analytique multi-coopératives, balance connectée, module export, multi-langue. → Phase 2.

## 14. Definition of Done

Voir `GOAL.md`. Une tâche n'est *Done* que si : tests verts, sécurité (20 points) vérifiée, APDP conforme, `openapi.yaml` à jour, et écarts éventuels **listés explicitement** (jamais corrigés en silence).
