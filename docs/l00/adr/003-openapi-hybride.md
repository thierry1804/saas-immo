# ADR-003 — OpenAPI hybride

## Statut

Accepté — 22 novembre 2026

## Contexte

L’API combine lectures/listes (API Platform) et commandes métier (controllers + handlers).

## Décision

- **Commandes** : OpenAPI manuelle dans `docs/l00/openapi/` (source de vérité avant/pendant L01)
- **Lectures** : schémas générés / annotés via API Platform, fusionnés dans le contrat unique
- Erreurs et enveloppes communes dans `errors.yaml`
- Sync agent et catalogue d’actions dans `sync-actions.yaml`

## Conséquences

- Les développeurs n’inventent pas de routes hors OpenAPI tranche active
- La CI pourra valider la fusion OpenAPI dès que le backend exposera les lectures
