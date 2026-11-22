# Backend Veille

API Symfony 7.4 du périmètre V1. Les prestataires de paiement sont simulés. Les jeux de données sont synthétiques.

## Prérequis

- PHP 8.3+ avec `pdo_pgsql`
- PostgreSQL 16 (Compose : port hôte `5434`)
- Dépendances déjà installées (`composer install` dans `backend/` si `vendor/` manque)

Le schéma de départ est `docs/l00/schema/tranche-1.sql`. La migration `Version20261122190000` l’applique s’il est absent, puis ajoute les tables V1 (`migrations/sql/v1-extend.sql`). Elle ne réécrit pas les tables de la tranche 1.

## Lancer

```bash
cd backend
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed:demo
php -S 127.0.0.1:8000 -t public
```

`APP_ENV=dev` est la valeur de `.env`. Le secret de signature des jetons est `APP_SECRET` (fichier `.env.dev` en développement).

CORS autorise `http://localhost:3000` (et `127.0.0.1`) via `CORS_ALLOW_ORIGIN`, ainsi que les en-têtes `Authorization`, `X-Organization-Id`, `Idempotency-Key`, `X-Adapter-Confirmation` et `X-Adapter-Token`.

Santé : `GET /api/health`.

Les commandes métier sont sous `/api/v1`. Sauf `/auth/*`, `GET` et `POST /agent/sync/actions`, un `POST` exige `Idempotency-Key` (UUID). Le même couple clé + corps renvoie la même réponse. Un autre corps avec la même clé renvoie `409 IDEMPOTENCY_KEY_REUSED`. Les actions agent utilisent `action_id` de la même façon.

En-têtes privés :

```http
Authorization: Bearer <access_token>
X-Organization-Id: 11111111-1111-4111-8111-111111111111
```

## Comptes de démonstration

Mot de passe des comptes e-mail : `veille-dev`.

Code OTP `000000` accepté **uniquement si `APP_ENV=dev`**, après une demande de challenge. En dehors de `dev`, ce code n’est pas un passe-droit.

| Compte | Identifiant | Rôle |
| --- | --- | --- |
| G1 préparateur | `g1@org-a.test` | prépare un lot, ne peut pas l’approuver |
| G2 approbateur | `g2@org-a.test` | approuve un lot préparé par quelqu’un d’autre |
| G3 opérations | `g3@org-a.test` | gestionnaire sans droit de lot |
| A1 | téléphone `+261320000001` | agent, pas de mot de passe |
| A2 | `+261320000002` | agent |
| P1 | `p1@org-a.test` | propriétaire, membre de ORG-A et ORG-B |
| P2 | `p2@org-a.test` | propriétaire |
| R1 / R2 | `r1@org-a.test`, `r2@org-a.test` | locataires |
| F1 / F2 | `+261330000001`, `+261340000001` | apporteurs |
| ORG-B | `g1@org-b.test` | gestionnaire de l’autre organisation |
| Plateforme | `admin@haythi.test` | aucun rattachement métier |

Identifiants stables :

- ORG-A `11111111-1111-4111-8111-111111111111`
- ORG-B `22222222-2222-4222-8222-222222222222`
- H1 (visite 20 EUR = `2000` centimes) `11111111-1111-4111-8111-cccccccccc01`
- H2 (visite 50 000 MGA) `11111111-1111-4111-8111-cccccccccc02`
- H3 `11111111-1111-4111-8111-cccccccccc03`
- Logement ORG-B `22222222-2222-4222-8222-cccccccccc01`
- Mission du jour, A1 sur H2 `11111111-1111-4111-8111-ffffffff0001`
- Appareil A1 `11111111-1111-4111-8111-ffffffff0002`
- Bail B1 (loyer `1000000`, charges `100000`, dépôt `500000`, MGA, honoraires 10 %) `11111111-1111-4111-8111-121212121201`
- Bail B2 prorata (premier loyer `160000` MGA) `11111111-1111-4111-8111-121212121202`
- Bénéficiaire P1 approuvé `11111111-1111-4111-8111-343434343401`
- Commission F1 `60000` MGA `11111111-1111-4111-8111-343434343402`
- Propriétaire P1 dans ORG-A `11111111-1111-4111-8111-bbbbbbbbbb01`

Une référence d’ORG-A lue avec `X-Organization-Id` d’ORG-B répond `404`, sans indiquer qu’elle existe ailleurs.

## Ce qui est simulé

- Carte EUR, MVola, Orange Money, Airtel Money et manuel : l’initiation laisse la tentative `pending`.
- `POST /api/v1/payment-attempts/{id}/browser-return` ne crée ni paiement ni écriture, quel que soit le corps.
- Seul `POST /api/v1/payment-attempts/{id}/provider-confirm`, avec les en-têtes d’adaptateur, écrit le paiement et une écriture équilibrée (débit trésorerie, crédit revenu de prestation, même monnaie).
- Les fichiers médias ne sont pas envoyés à MinIO : l’empreinte et la taille suffisent à les marquer vérifiés.
- L’export de rapport répond `202` sans produire de PDF.
- Pas d’envoi SMS, e-mail ou mobile money réel. En `dev`, le jeton de lien magique est renvoyé dans `dev_token`.

Jeton d’adaptateur, dérivé du secret, jamais écrit en dur :

```bash
cd backend
export APP_SECRET=$(grep '^APP_SECRET=' .env.dev | cut -d= -f2-)
php -r 'echo hash("sha256", getenv("APP_SECRET").":simulated-adapter"), "\n";'
```

L’approbation d’un lot exige une authentification de moins de cinq minutes (`auth_time` du jeton) et un compte différent du préparateur (`403 SELF_APPROVAL_FORBIDDEN`).

## Exemples

Connexion gestionnaire. Le code `000000` n’est valable qu’en `dev`.

```bash
BASE=http://127.0.0.1:8000
ORG=11111111-1111-4111-8111-111111111111

MFA=$(curl -s -X POST $BASE/api/v1/auth/password \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"g1@org-a.test","password":"veille-dev"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->mfa_token;')

TOKEN=$(curl -s -X POST $BASE/api/v1/auth/mfa \
  -H 'Content-Type: application/json' \
  -d "{\"mfa_token\":\"$MFA\",\"code\":\"000000\"}" | php -r 'echo json_decode(stream_get_contents(STDIN))->data->access_token;')
```

Agent A1 :

```bash
CHALLENGE=$(curl -s -X POST $BASE/api/v1/auth/otp/request \
  -H 'Content-Type: application/json' \
  -d '{"channel":"sms","destination":"+261320000001","intent":"login"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->challenge_id;')

TOKEN=$(curl -s -X POST $BASE/api/v1/auth/otp/verify \
  -H 'Content-Type: application/json' \
  -d "{\"challenge_id\":\"$CHALLENGE\",\"code\":\"000000\"}" | php -r 'echo json_decode(stream_get_contents(STDIN))->data->access_token;')
```

Lien magique (le champ `dev_token` n’existe qu’en `dev`) :

```bash
curl -s -X POST $BASE/api/v1/auth/magic/request \
  -H 'Content-Type: application/json' \
  -d '{"destination":"g2@org-a.test"}'
curl -s -X POST $BASE/api/v1/auth/magic/exchange \
  -H 'Content-Type: application/json' \
  -d '{"token":"<dev_token>"}'
```

Facture de visite H2, tentative, retour navigateur, puis confirmation adaptateur :

```bash
IDEM=$(php -r '$b=random_bytes(16); $b[6]=chr((ord($b[6])&0x0f)|0x40); $b[8]=chr((ord($b[8])&0x3f)|0x80); echo vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($b), 4));')

curl -s -X POST $BASE/api/v1/invoices/drafts \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: $ORG" -H "Idempotency-Key: $IDEM" \
  -H 'Content-Type: application/json' \
  -d '{"property_id":"11111111-1111-4111-8111-cccccccccc02"}'
```

Après émission (`POST /api/v1/invoices/{id}/issue`) :

```bash
curl -s -X POST $BASE/api/v1/invoices/$INVOICE/payment-attempts \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: $ORG" -H "Idempotency-Key: $IDEM" \
  -H 'Content-Type: application/json' \
  -d '{"provider":"mvola","method":"mobile_money","amount_minor":"50000","currency":"MGA"}'

curl -s -X POST $BASE/api/v1/payment-attempts/$ATTEMPT/browser-return \
  -H "X-Organization-Id: $ORG" -H 'Content-Type: application/json' \
  -d '{"status":"succeeded"}'

curl -s -X POST $BASE/api/v1/payment-attempts/$ATTEMPT/provider-confirm \
  -H "X-Organization-Id: $ORG" -H "Idempotency-Key: $IDEM" \
  -H "X-Adapter-Confirmation: simulated" -H "X-Adapter-Token: $ADAPTER" \
  -H 'Content-Type: application/json' \
  -d '{"outcome":"succeeded"}'
```

Écriture refusée si les débits et les crédits diffèrent, ou si une ligne mélange EUR et MGA : `POST /api/v1/ledger/entries` (`422 UNBALANCED_ENTRY` ou `422 CURRENCY_MISMATCH`).

Lot : G1 `POST /api/v1/payout-batches` avec le bénéficiaire P1, puis le même jeton sur `POST /api/v1/payout-batches/{id}/approve` renvoie `SELF_APPROVAL_FORBIDDEN`. G2, connecté depuis moins de cinq minutes, approuve avec un motif.

Bail et commission :

```bash
curl -s $BASE/api/v1/leases/11111111-1111-4111-8111-121212121201/rent-schedule \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: $ORG"

curl -s $BASE/api/v1/commissions/11111111-1111-4111-8111-343434343402 \
  -H "Authorization: Bearer $TOKEN" -H "X-Organization-Id: $ORG"
```

Annonce : `POST /api/v1/listings`, `POST /api/v1/listings/{id}/publish` avec `{"expected_version":1,"owner_agreement":true}`, puis `POST /api/v1/public/listings/{slug}/visit-requests`. La fiche publique ne contient ni adresse exacte ni code d’accès.

Anomalie : `POST /api/v1/anomalies`.

Visite : la mission du jour n’a pas de média. `POST /api/v1/visits/{id}/finalize` répond `422 MISSING_MEDIA` tant que six médias vérifiés manquent (checklist, minimum 6). Avec les médias et un `manifest_hash` égal au SHA-256 canonique du manifeste, le serveur scelle. Un second manifeste passe par `POST /api/v1/visits/{id}/amendments` : l’ancien scellé n’est pas modifié.

Vérification publique : `GET /api/v1/verify/{opaque_code}`. La réponse ne contient ni nom ni adresse.
