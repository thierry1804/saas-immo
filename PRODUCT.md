# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Confirmé par le dépôt et les specs (pas un choix de session) : monorepo plat, API Symfony 7.4 (API Platform + commandes), PWA Next.js 15, PostgreSQL 16, Redis, MinIO. Back-office Twig pour l’admin plateforme. Auth duale : session cookie pour le gestionnaire, JWT court + refresh opaque pour le terrain.

## Users

- Gestionnaire de conciergerie : prépare le patrimoine, affecte les missions, relit les constats, publie les rapports, prépare ou approuve l’argent selon une permission distincte. Deux comptes distincts pour préparer et approuver un décaissement.
- Agent de terrain : sur téléphone Android, souvent sans réseau, réalise la visite, les photos caméra, l’impossibilité ou l’urgence, puis synchronise.
- Propriétaire : consulte les visites publiées, les anomalies, les documents et les relevés de ses logements, éventuellement dans plusieurs organisations qu’il choisit explicitement.
- Locataire : suit son bail, ses loyers, ses quittances et signale un incident.
- Apporteur : revendique un contact, suit ses annonces et ses commissions.
- Prospect : sans compte, agit par lien opaque limité à sa demande.
- Administrateur plateforme (HayThi) : incidents techniques et organisations, sans arbitrer les litiges commerciaux ni approuver un paiement.

## Product Purpose

Veille permet à une conciergerie de coordonner ses opérations immobilières, documenter les visites, informer les parties et rapprocher les sommes dues, encaissées et reversées. Le propriétaire voit les opérations attendues, les visites réalisées, les anomalies, les documents publiés et les flux qui le concernent.

Succès : une livraison unique de tout le périmètre V1 (organisation, patrimoine, terrain, constat, interventions, facturation, paiements, location, commercialisation, commissions, argent de tiers, portails, pilotage), recette passée, prestataires de paiement simulés tant que DEC-03 n’est pas clos.

## Positioning

La preuve de visite est un dossier de constat dont l’intégrité après réception peut être vérifiée (scellé serveur, amendement, pas de réécriture). Le système distingue toujours qui doit, qui reçoit et pour le compte de qui, sans mélanger EUR et MGA ni les organisations.

## Operating Context

Conciergeries, d’abord malgaches : fuseau initial Indian/Antananarivo, monnaies EUR (centimes) et MGA (ariary entiers). L’agent travaille sur le logement, clés et accès en main, avec ou sans réseau. Le gestionnaire relit au bureau. HayThi fournit le logiciel ; les comptes marchands appartiennent à la conciergerie. L’encaissement pour compte de propriétaire reste bloqué en production tant que DEC-02 n’est pas validé. Interface française, architecture de traduction prévue.

## Capabilities and Constraints

Périmètre V1 confirmé par l’utilisateur pour cette construction : Identity, Property, Fieldwork, Evidence, Maintenance, Lettings, Billing, Payments, Ledger, Payouts, Referrals, Communications.

Invariants à préserver : isolation par organization_id (INV-01), immuabilité des documents publiés et écritures validées (INV-02), monnaie explicite (INV-03), idempotence (INV-04), décisions tracées (INV-05), visibilité par rôle (INV-06), instants UTC (INV-07).

Hors livraison : applications natives, signature électronique de bail, horodatage tiers, analyse photo par IA, WhatsApp, messagerie interne, moteur public d’annonces, sync Airbnb/Booking, API de décaissement, comptabilité fiscale. L’abonnement SaaS Veille n’est pas facturé dans l’application.

Paiements : adaptateurs simulés (carte EUR, MVola, Orange Money, Airtel Money, manuel). Un retour navigateur ne solde jamais une facture.

Hypothèse assumée : les jeux de données sont synthétiques (ORG-A / ORG-B et le jeu de la section 3 du plan de recette). Aucun secret marchand réel.

## Brand Commitments

Nom du produit : Veille. Éditeur : HayThi. Voix : français, phrases courtes, verbes d’action, pas de promesse de preuve judiciaire. Les maquettes `docs/l00/ui/` sont des parcours, pas une identité visuelle.

## Evidence on Hand

- `specs/Veille-specification-fonctionnelle.md`, `specs/Veille-contrats-techniques.md`, `specs/Veille-plan-developpement-recette.md`, `specs/Veille-stack-technique.md`
- Contrats exécutables tranche 1 : `docs/l00/openapi/`, `docs/l00/schema/`, ADR `docs/l00/adr/`
- Maquettes de parcours : `docs/l00/ui/`
- Squelette : `backend/` (domaines vides), `frontend/` (Next.js par défaut)

Absences à ne pas fabriquer : clients réels, tarifs commerciaux, avis, chiffres de performance mesurés, prestataires de paiement réellement intégrés.

## Product Principles

- Une organisation ne voit jamais les objets d’une autre.
- Une correction ajoute une version ou une contre-écriture ; elle n’efface pas le fait.
- L’agent doit pouvoir finir sa visite sans réseau, et l’écran ne dit « reçu » qu’après accusé serveur.
- L’argent affiché est un solde calculé, jamais une case éditable.
- Préparer et approuver un mouvement sensible sont deux personnes.
