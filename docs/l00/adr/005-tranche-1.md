# ADR-005 — Tranche 1 Identity → Evidence

## Statut

Accepté — 22 novembre 2026

## Contexte

Le périmètre V1 complet est trop large pour un OpenAPI/SQL unique au premier jet L00.

## Décision

Première tranche documentaire et schéma :

1. Identity
2. Property
3. Fieldwork
4. Evidence

Tables transversales minimales incluses : audit, outbox, idempotence.

Les modules Maintenance, Lettings, Billing, Payments, Ledger, Payouts, Referrals, Communications seront détaillés dans des tranches ultérieures.

## Conséquences

- L01–L04 peuvent démarrer sans attendre la finance
- Les maquettes UI couvrent toutefois toutes les surfaces de rôles (parcours stub pour finance/location)
