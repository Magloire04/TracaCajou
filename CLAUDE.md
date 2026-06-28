# CLAUDE.md — TraçaCajou

> Contrat d'équipe pour Claude Code. **Lean par design** : ici on ne met que le contexte, les conventions non automatisables et les règles métier. Tout ce qui est *« vérifie / lance toujours… »* part dans un **hook** (voir plus bas), pas ici.

## Contexte projet

**TraçaCajou** délivre à chaque lot de noix de cajou un **certificat d'origine numérique vérifiable** (signé, infalsifiable, vérifiable via QR) pour les coopératives de la filière anacarde au Bénin. Objectif : transparence de la commercialisation, lutte contre la contrebande, juste prix au producteur.

- **Stack back-end :** Laravel 12 (API REST JSON).
- **Stack front-end :** Nuxt 3 / Vue 3 en **PWA installable, offline-first** (saisie terrain sans réseau, synchronisation différée).
- **Base de données :** MySQL/PostgreSQL.
- **Signature des certificats :** ECDSA **P-384** (réutilise la clé/PKI DocSentry de CYPASS). Le certificat = payload canonique + signature + UUID public de vérification.
- **Hébergement :** VPS (Nginx). Coûts d'infra volontairement faibles.
- **Contexte légal :** application soumise à la **loi béninoise n° 2017-20 (APDP)** — elle traite des données personnelles de producteurs (nom, localité). La conformité n'est pas optionnelle.

## Périmètre MVP (ne pas déborder)

Dans le périmètre : auth agent · enregistrement producteur · enregistrement lot (pesée/prix) · génération de certificat signé (PDF + QR) · **vérification publique par QR** · historique des lots d'une coopérative.

**Hors périmètre MVP** (phase 2, ne pas implémenter sans demande explicite) : paiement mobile money, canal USSD/SMS, tableau de bord analytique multi-coopératives, intégration balance connectée, module export. Règle Superpowers : *si « ce serait bien d'ajouter… », la réponse est dans la spec — pas d'improvisation.*

## Conventions de code (non automatisables — à respecter)

**Nommage (le type de fichier détermine la convention) :**
- Composants Vue → `PascalCase` (`LotForm.vue`) · hooks/utilitaires → `camelCase` (`useOfflineSync.ts`, `formatFcfa.ts`) · config → `kebab-case` · tests → `*.test.ts` / `*.spec.ts` · dossiers → `kebab-case`.
- Variables `camelCase` · constantes `CONSTANT_CASE` · booléens préfixés `is/has/can`.
- Fonctions = **verbe + nom** (`generateCertificate()`, `verifySignature()`, `getLotsByCooperative()`). Si une fonction ne se nomme pas verbe+nom précis → elle fait trop de choses.
- **Zéro abréviation** (`producteur` pas `prod`, `certificat` pas `cert` dans le code métier), **noms en intention pas en implémentation**.

**API REST (langue commune, identique partout) :**
- Verbes HTTP = actions, URLs = ressources au **pluriel**, **nesting max 2 niveaux**, filtres en **query params**.
- Versioning par URL : `/api/v1/...`. Tout breaking change → `v2` (dépréciation annoncée ≥ 3 mois).
- **Enveloppe de réponse obligatoire et constante** :
  - Liste : `{ "data": [...], "meta": { "page", "limit", "total" } }`
  - Détail : `{ "data": { ... } }`
  - Erreur : `{ "error": { "code", "message", "status" } }`
- Codes HTTP sémantiques : 200/201/204 · 400/401/403/404/409/422 · 500. Ne pas tout mettre en 200.
- **Le contrat `openapi.yaml` fait foi**, versionné dans Git. La doc est générée depuis le contrat, pas écrite à la main.

## Règles métier (vérifier dans le code)

- Un **lot** appartient à un producteur **et** à une coopérative ; `montant_fcfa = poids_kg × prix_kg_fcfa` (calculé serveur, jamais reçu du client).
- Un **certificat** est immuable une fois émis. Toute correction = nouveau certificat ; l'ancien est marqué `revoque`, jamais supprimé (traçabilité).
- L'**UUID public** d'un certificat est aléatoire et non devinable ; il ne doit divulguer aucune donnée personnelle dans l'URL.
- La **vérification publique** (`/certificats/:uuid/verify`) est **non authentifiée** mais ne renvoie que des champs **minimisés** (origine, poids, date, statut, coopérative) — **jamais** le nom complet ni la localité précise du producteur.

## Sécurité (la sécurité naît dans le code)

- **Injections (A03)** : requêtes paramétrées via l'ORM, **jamais** de concaténation de chaînes avec des entrées externes.
- **XSS (A03)** : échappement systématique côté Vue (pas de `v-html` sur du contenu non contrôlé).
- **Contrôle d'accès serveur (A01)** : à chaque requête, vérifier *qui* + *droit sur CETTE ressource précise* (anti-IDOR). Masquer un bouton côté client ≠ sécurité.
- **Secrets (A02/A05)** : `bcrypt`/Argon2 pour les mots de passe ; **JWT en cookie `httpOnly` + `secure`**, jamais en `localStorage` ; secrets en variables d'environnement, **jamais** committés. Erreurs prod génériques (pas de stack trace verbeuse).
- **Logs (A09)** : logger connexions, 403, changements de rôle. **Ne JAMAIS logger** mots de passe, JWT, ni données personnelles (nom, localité, IP nominative).

## APDP — loi n° 2017-20 (obligation, pas option)

- **Finalité** explicite et **minimisation** : ne collecter que le strict nécessaire (le producteur n'a pas besoin d'un numéro de pièce d'identité pour le MVP).
- **Consentement** du producteur capté à l'enrôlement (libre, éclairé, **case non pré-cochée**).
- **API minimisée** : ne jamais retourner l'entité entière ; filtrer explicitement les champs (cf. anti-pattern « return Producteur::find(id) » → exposerait tout).
- **Droit à l'effacement** prévu dès la conception : suppression physique ou **anonymisation** des enregistrements à conserver légalement.

## Tests (TDD strict imposé par Superpowers)

- Test **RED** avant le code (le test échoue d'abord), puis implémentation **GREEN**, puis refactor.
- Pyramide : beaucoup d'**unitaires** (logique métier : calcul montant, signature, validation), quelques **intégration** (API + DB), **E2E réservés** aux parcours critiques (login agent, enregistrement lot → certificat, vérification QR).
- 1 test = 1 comportement, noms explicites, chaque bug reproduit par un test avant correction.

## Hooks recommandés (déterministe — à mettre en place après le 1er cycle)

- `PreToolUse` — **garde sécurité** : bloque toute écriture contenant un secret en dur, un `console.log` de données sensibles, ou une route API sans middleware d'auth.
- `PostToolUse` — **tests auto** : relance la suite de tests du module modifié.
- `Stop` — **journal d'audit** : à chaque fin de session, logger fichiers modifiés + timestamp + hash git.

## Définition de « Done »

Voir `GOAL.md`. Une tâche n'est *Done* que si : tests verts, sécurité (les 20 points) respectée, APDP vérifiée, et écarts éventuels **listés explicitement** (jamais corrigés en silence).

---
*Conventions adoptées depuis mes standards de dev (formation ASIN, juin 2026), appliquées ici à mon projet personnel TraçaCajou. Tout écart = décision documentée, datée, justifiée.*
