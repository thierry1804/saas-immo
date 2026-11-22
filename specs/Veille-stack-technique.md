# Veille — Stack technique retenue

Version 1.0 — 22 novembre 2026

## 1. Décision d’architecture

La plateforme Veille est développée comme un **monolithe modulaire Laravel** avec un frontend web/PWA. Cette décision privilégie la cohérence transactionnelle des workflows immobiliers et financiers tout en conservant une séparation claire des domaines métier.

Les seuls compromis autorisés sont des adaptateurs externes interchangeables. Aucun prestataire de paiement ne doit devenir une dépendance directe du cœur métier.

## 2. Stack principale

| Domaine | Technologie proposée | Justification |
|---|---|---|
| Backend | Laravel maintenu sur une version LTS | Validation, autorisations, files, transactions, API et tests structurés |
| Langage | PHP avec typage strict | Écosystème Laravel mature et compatible avec les applications transactionnelles |
| API | Laravel REST/OpenAPI | Contrats explicites, pagination, idempotence et codes d’erreur stables |
| Base principale | PostgreSQL | Transactions ACID, contraintes, clés étrangères composites et calculs financiers fiables |
| Accès données | Eloquent plus SQL ciblé | ORM pour les workflows courants ; SQL explicite pour les agrégats et contraintes complexes |
| File/cache | Redis | Outbox, notifications, tâches planifiées, cache non autoritatif |
| Worker | Laravel Horizon ou équivalent | Priorités urgences/paiements, retries, dead letters et supervision |
| Frontend PWA | Livewire ou Blade/Inertia selon les écrans | Réduction de la duplication et support cohérent des rôles web |
| Application terrain | PWA installable Android | Hors ligne requis ; IndexedDB, service worker et reprise des transferts |
| Stockage objet | MinIO ou S3 compatible | Médias privés, versionnage, reprise par parties et URLs temporaires |
| Recherche | PostgreSQL au démarrage | Évite une infrastructure supplémentaire tant que les volumes restent ceux de la recette |
| Observabilité | Logs structurés + Sentry | Erreurs, latences, files, rapprochements et restaurations observables |
| Conteneurs locaux | Docker Compose | Reproductibilité du développement et des recettes |

## 3. Organisation Laravel

Le dépôt doit suivre une organisation modulaire correspondant aux frontières fonctionnelles des contrats techniques :

- `Identity` : comptes, appartenances, MFA, appareils et sessions
- `Property` : propriétaires, logements, mandats, consignes et checklists
- `Fieldwork` : missions, tentatives, synchronisation hors ligne
- `Evidence` : scellés, amendements, rapports et vérification
- `Maintenance` : anomalies, devis, ordres et contrôles
- `Lettings` : annonces, demandes, candidatures et baux
- `Billing` : factures, avoirs, échéances et reçus
- `Payments` : tentatives, paiements, prestataires et rapprochements
- `Ledger` : comptes, écritures équilibrées et soldes
- `Payouts` : bénéficiaires, lots, réservations et reversements
- `Referrals` : revendications, commissions et litiges
- `Communications` : notifications, modèles et tentatives d’envoi

Les modules communiquent par services applicatifs et événements métier. Ils ne lisent pas directement les tables internes d’un autre domaine. Les commandes financières changent l’état, écrivent l’audit et insèrent l’événement Outbox dans la même transaction PostgreSQL.

## 4. Monnaies, paiements et intégrations

### 4.1 Principe

Les montants sont enregistrés dans leur unité contractuelle :

- EUR : centimes
- MGA : ariary entiers

Les montants sont stockés en valeurs entières ou décimales sûres selon le contrat PostgreSQL choisi. Les interfaces API n’acceptent jamais une somme implicite EUR/MGA.

### 4.2 Prestataires de paiement

Stripe n’est pas retenu dans la conception initiale car son fonctionnement marchand à Madagascar ne peut pas être considéré comme établi pour ce projet.

Chaque intégration doit implémenter une interface commune :

- initier un paiement
- lire son état
- vérifier une notification
- normaliser une transaction
- importer les relevés
- déclarer ses capacités
- contrôler les limites et délais

Les moyens entrants doivent inclure :

- **Carte EUR** : prestataire vérifié pour l’entité bénéficiaire réelle
- **MVola** : directement ou via un agrégateur commercialement habilité
- **Orange Money** : directement ou via un agrégateur commercialement habilité
- **Airtel Money** : directement ou via un agrégateur commercialement habilité
- **Virement, espèces et dépôt** : saisie manuelle validée et rapprochée

Un adaptateur prestataire ne peut pas confirmer un paiement uniquement sur un retour navigateur ou un callback non vérifié. Le webhook brut, sa signature ou sa preuve, le compte marchand résolu et l’identifiant externe sont conservés avant tout traitement métier.

Avant intégration, DEC-03 doit être fermé avec les éléments suivants :

- éligibilité réelle de l’entité marchand
- frais et formats des montants
- signatures et callbacks
- délais de confirmation
- limites de polling
- formats de relevés
- formats de lots sortants
- procédure de remboursement ou de retour

### 4.3 Paiements sortants

La version 1 ne doit pas faire confiance à une API de décaissement sans validation opérationnelle. Les lots sortants sont préparés, approuvés séparément, exportés dans le format accepté du prestataire ou de la banque, puis rapprochés ligne par ligne.

## 5. PWA hors ligne

Le frontend terrain utilise :

- IndexedDB pour les références, actions, brouillons, blobs et file d’envoi
- un service worker migrable
- des identifiants locaux UUID stable
- des `action_id` dédupliqués côté serveur
- des uploads reprenables par parties
- des priorités P0/P1/P2
- une autorisation limitée à 48 heures
- une réauthentification après expiration sans effacement des données locales

Les alertes urgentes sont prioritaires sur les médias et le reste des actions. Les données locales sont isolées par compte et organisation. L’outil ne doit pas afficher « reçu » avant le commit serveur et la vérification des fichiers.

## 6. Base de données et intégrité

Toutes les tables métier contiennent `organization_id`. Les relations entre agrégats utilisent des clés étrangères composites afin d’empêcher les références croisées entre organisations.

Les données financières doivent respecter :

- clé d’idempotence API conservée au moins 30 jours
- unicité durable prestataire + compte marchand + transaction externe
- affectations distinctes des paiements
- journal en partie double par monnaie
- verrouillage des dettes et disponibilités
- clôture périodique avec refus des écritures rétroactives
- correction par contre-écriture liée

Les documents publiés, constats scellés, paiements confirmés et écritures validées sont immuables dans les usages ordinaires.

## 7. Sécurité

- HTTPS obligatoire
- sessions sécurisées avec HttpOnly, Secure et SameSite appropriés
- protection CSRF sur les commandes
- MFA obligatoire pour les rôles sensibles
- Argon2id pour les mots de passe et secrets suffisamment adaptables
- OTP vérifié pour les appareils agent/apporteur
- liens magiques à usage unique stockés hachés
- clés privées de scellement séparées des données applicatives
- URLs médias temporaires délivrées après contrôle métier
- audit des approbations financières et accès support
- suppression de données et purge selon politique validée

Les agents disposent d’une autorisation locale limitée. Toute opération financière sensible nécessite une vérification des droits, du contexte organisationnel, de la version et, selon le workflow, une seconde approbation ou une authentification récente.

## 8. Stockage des médias

Le stockage objet doit offrir :

- clés contenant organisation et identifiant opaque
- stockage privé par défaut
- fichiers immuables après finalisation
- versionnage
- protection contre l’écrasement
- hashes SHA-256 vérifiés côté serveur
- URLs temporaires initialement limitées à cinq minutes
- séparation entre origine brute, fichier retenu et PDF publié
- politique indépendante de purge, restauration et conservation

## 9. Déploiement

Docker Compose est recommandé pour :

- le développement
- les tests reproductibles
- les migrations répétées
- les recettes hors ligne
- les tests de synchronisation et de charge
- l’isolation des environnements

Docker n’est pas une exigence pour la production. L’hébergement peut utiliser Docker ou des composants gérés, à condition d’isoler les environnements et de fournir :

- déploiement versionné
- sauvegardes PostgreSQL complètes avec journalisation continue
- sauvegardes objet indépendantes
- restauration testée
- exécution fiable des workers
- tâches planifiées supervisées
- secrets hors du dépôt
- comptes marchands réels interdits en développement

## 10. Infrastructure minimale proposée

### Développement

- Docker Compose
- PHP, Composer et Laravel
- PostgreSQL
- Redis
- MinIO ou stockage objet compatible S3
- HTTPS local
- données et prestataires simulés

### Production initiale

- application Laravel sur une VM ou plateforme PaaS
- PostgreSQL géré avec sauvegarde continue
- Redis géré ou supervisé
- stockage objet versionné
- SMTP/SMS géré
- monitoring et Sentry
- sauvegarde et restauration indépendantes
- séparation stricte développement, recette et production

Une architecture distribuée ou microservices n’est pas justifiée au démarrage. Elle serait introduite uniquement après observation d’un besoin réel impossible à satisfaire dans le monolithe modulaire.

## 11. Livrables techniques associés

Avant implémentation, le dépôt doit produire :

1. OpenAPI complète avec schémas et erreurs
2. migrations PostgreSQL avec contraintes et unicités
3. diagrammes d’état et tests de transition
4. schéma versionné du manifeste et fixtures JCS/SHA-256
5. outbox, inbox, idempotence et politiques de rejeu
6. adaptateurs testables pour les quatre moyens intégrés
7. propriétés de journal, affectation, conversion et rapprochement
8. procédures PWA, restauration, mise à jour et support agent
9. catalogue des actions hors ligne avec schémas versionnés
10. configuration CI/CD, sécurité et sauvegarde

## 12. Critères de validation

La stack ne sera considérée comme validée que lorsque les 122 scénarios du plan de recette donnent un résultat reproductible, particulièrement :

- isolation entre organisations
- perte ou reprise hors ligne
- idempotence des paiements
- callbacks rejoués ou falsifiés
- rapprochement MGA et EUR
- journal équilibré
- double approbation financière
- restauration cohérente PostgreSQL/objet/identifiants
- PDF, manifeste et preuve vérifiables

Cette stack doit rester une décision technique révisable uniquement sur une exigence fonctionnelle, opérationnelle ou réglementaire documentée.