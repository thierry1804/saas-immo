# ADR-001 — Monorepo plat

## Statut

Accepté — 22 novembre 2026

## Contexte

Veille nécessite un backend Symfony et une PWA Next.js. Il fallait choisir l’organisation du dépôt.

## Décision

Monorepo plat :

- `backend/` — application Symfony
- `frontend/` — application Next.js (PWA agent + surfaces web liées)
- Pas de `packages/` partagés au démarrage
- Contrats exécutables dans `docs/l00/` ; norme métier dans `specs/`

## Conséquences

- Un seul clone pour la recette locale
- OpenAPI versionnée dans `docs/l00/openapi/` (pas encore un package npm partagé)
- Twig admin vit dans `backend/` ; Next.js dans `frontend/`
