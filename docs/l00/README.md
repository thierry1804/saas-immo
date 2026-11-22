# L00 — Livrables exécutables

Index des artefacts produits pour le lot **L00 Cadrage exécutable**. La norme métier reste dans [`specs/`](../../specs/).

## Ordre de lecture

1. [ADR](adr/) — décisions techniques figées
2. [Registre DEC](decisions/DEC-register.md) — décisions externes à nommer
3. [OpenAPI](openapi/) — contrats API tranche 1
4. [Schéma SQL + RLS](schema/) — tables Identity → Evidence
5. [Maquettes UI](ui/index.html) — parcours cliquables par rôle
6. Squelette dépôt : [`backend/`](../../backend/), [`frontend/`](../../frontend/), [`docker-compose.yml`](../../docker-compose.yml)

## Tranche 1 (cette livraison)

Modules couverts : **Identity**, **Property**, **Fieldwork**, **Evidence**.

Les modules Billing → Referrals seront détaillés dans des tranches ultérieures alignées sur les lots L05+.

## Versions verrouillées au scaffold

| Composant | Version cible |
|---|---|
| PHP | 8.3 |
| Symfony | 7 LTS |
| Next.js | 15 (App Router) |
| PostgreSQL | 16 |
| Redis | 7 |
