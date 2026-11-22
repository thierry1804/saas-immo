# ADR-004 — RLS PostgreSQL dès L01

## Statut

Accepté — 22 novembre 2026

## Contexte

INV-01 exige l’isolation par `organization_id`. Les contrats demandent une seconde barrière au-delà de l’applicatif.

## Décision

- Filtres Doctrine / voters Symfony **et** Row Level Security PostgreSQL dès L01
- Contexte transactionnel : `SET LOCAL app.organization_id = '<uuid>'` (ou équivalent)
- Rôle applicatif non propriétaire des tables ; migrations avec rôle distinct
- Politiques documentées dans `schema/rls-tranche-1.sql`

## Conséquences

- Tests de réutilisation de connexion obligatoires
- Workers héritent explicitement de l’organisation de l’événement
