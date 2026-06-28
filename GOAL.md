# GOAL.md — TraçaCajou (MVP)

> Fichier de but pour la commande `/goal` de Claude Code. `/goal` doit faire tourner l'implémentation **jusqu'à ce que TOUS les critères ci-dessous soient satisfaits et vérifiables**. Pas avant.

## But (north star)

Livrer un MVP démontrable où un agent de coopérative peut **enregistrer un lot de cajou et obtenir instantanément un certificat d'origine signé, vérifiable publiquement par QR code** — le tout fonctionnant en zone à faible connectivité.

## Critères de fin — Definition of Done (vérifiables)

### Fonctionnel
- [ ] Un agent peut s'authentifier (cookie `httpOnly`/`secure`).
- [ ] Un agent peut enregistrer un producteur (champs minimisés + consentement).
- [ ] Un agent peut enregistrer un lot (poids, humidité, prix/kg) ; `montant_fcfa` est **calculé côté serveur**.
- [ ] La création d'un lot génère un **certificat signé (ECDSA P-384)** : PDF + QR + UUID public.
- [ ] L'endpoint **public** `GET /api/v1/certificats/:uuid/verify` valide la signature et renvoie des données **minimisées** (origine, poids, date, statut, coopérative) — sans donnée personnelle.
- [ ] Un agent voit l'historique des lots de sa coopérative (liste paginée).
- [ ] La PWA capture un lot **hors-ligne** et le synchronise au retour du réseau.

### Qualité & tests
- [ ] Tests **unitaires** verts pour : calcul du montant, génération + vérification de signature, validations.
- [ ] Tests **d'intégration** verts pour chaque endpoint API.
- [ ] 1 test **E2E** : login → enregistrement lot → certificat → vérification QR.
- [ ] Linter (règles sécurité en mode erreur) et formatter passent ; config commitée.

### Sécurité (les 20 points doivent passer)
- [ ] Requêtes paramétrées partout ; sorties échappées ; contrôle d'accès serveur par ressource (anti-IDOR).
- [ ] Mots de passe `bcrypt`/Argon2 ; JWT en cookie `httpOnly`/`secure` ; secrets en env, aucun secret committé.
- [ ] Logs de sécurité **sans** données personnelles ; erreurs prod génériques.

### APDP (loi 2017-20)
- [ ] Aucune API ne retourne l'entité entière (champs filtrés explicitement).
- [ ] Consentement producteur capté (case non pré-cochée).
- [ ] Endpoint/fonction d'effacement (suppression ou anonymisation) disponible.
- [ ] Aucune donnée personnelle dans les logs ni dans l'URL publique de vérification.

### Contrat & démo
- [ ] `openapi.yaml` versionné et à jour, doc générée depuis le contrat.
- [ ] `README.md` : installation + lancement back/front + commande de seed.
- [ ] **Jeu de données seed** : 1 coopérative, ~10 producteurs, ≥ 1 lot certifié → scénario de **démo de pitch** exécutable de bout en bout en une commande.

## Phase Verify (obligatoire avant de déclarer le but atteint)

Lancer une revue de cohérence complète **spec ↔ tests ↔ code ↔ sécurité ↔ APDP**. Les écarts sont **listés explicitement** (TODO datés), **jamais corrigés en silence**. Le but n'est atteint que lorsque la liste d'écarts critiques est vide.

## Hors périmètre (ne déclenche PAS d'implémentation)

Mobile money, USSD/SMS, dashboard analytique multi-coop, balance connectée, module export. Toute envie d'ajouter l'un de ces éléments = stop, on reste sur le périmètre MVP.
