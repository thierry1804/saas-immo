# Veille — Spécification fonctionnelle et processus métier

Version 2.1 — 22 novembre 2026 — Référence de développement proposée.

Cette version remplace le contenu normatif de « Spec produit app conciergerie (Veille).md » du 22 novembre 2026. Elle couvre **une livraison unique de tout le périmètre V1**, conformément à la demande : visites et rapports, facturation et encaissements, gestion locative, annonces, visites de prospects, commissions et paiements sortants par lots. Les lots de réalisation sont des dépendances techniques, pas des livraisons commerciales séparées.

Les choix introduits ici sont des décisions de conception proposées, applicables pour développer et tester. Ils ne valent ni validation commerciale ni validation juridique. Les décisions externes encore nécessaires ont un responsable, une échéance et un effet précis sur la mise en production en section 18. On peut développer avec des prestataires simulés ; on ne peut pas déclarer les intégrations réelles terminées sur cette seule base.

## 1 Documents de référence et ordre de lecture

| Document | Contenu | Lecteurs |
| --- | --- | --- |
| Cette spécification | Périmètre, responsabilités, parcours, règles métier, interfaces, décisions | Produit, métier, design, développement, recette |
| [Contrats techniques](Veille-contrats-techniques.md) | Modèle de données, API, synchronisation, scellés, argent, tâches et sécurité | Développement, exploitation, sécurité |
| [Plan de développement et recette](Veille-plan-developpement-recette.md) | Lots, dépendances, cas de test, critères de sortie et traçabilité | Équipe de réalisation et responsable métier |

En cas de conflit, le comportement métier décrit ici prévaut sur un exemple technique. Une contradiction découverte doit être résolue dans les trois documents avant développement de la fonction concernée. Le prototype historique n'est pas une source normative : ses écrans sont des inspirations de présentation.

Les formulations « doit », « ne peut pas », « est interdit » et « jamais » expriment une exigence. Une « valeur initiale » est un choix produit modifiable seulement par un paramètre explicitement prévu. Les délais sont calendaires sauf mention contraire. Les montants d'exemple ne sont pas des tarifs décidés.

## 2 Objectif et périmètre de la livraison

### 2.1 Proposition de valeur

Veille permet à une conciergerie de coordonner ses opérations immobilières, documenter les visites, informer les parties concernées et rapprocher les sommes dues, encaissées et reversées. Le propriétaire voit les opérations attendues, les visites réalisées, les anomalies, les documents publiés et les flux qui le concernent.

La preuve de visite est un **dossier de constat dont l'intégrité après réception peut être vérifiée**. Elle ne garantit ni la véracité de chaque observation, ni l'absence de fraude sur le téléphone, ni l'acceptation du dossier par un assureur ou une juridiction.

### 2.2 Fonctions incluses

| Domaine | Inclus dans la livraison unique |
| --- | --- |
| Organisation | Plusieurs conciergeries isolées, comptes et appartenances, rôles et droits, journal d'audit |
| Patrimoine | Propriétaires, logements, mandats documentaires, contacts, accès, tarifs, checklists versionnées et photos de référence |
| Terrain | Missions ponctuelles et récurrentes, affectation, PWA Android hors ligne, photos caméra, localisation, visite impossible, incidents et urgences |
| Constat | Scellé serveur, amendements, contrôle gestionnaire, rapports web/PDF, vérification publique minimale, dossier de réclamation exportable |
| Interventions | Signalement, devis versionnés, acceptation, ordre de travail, constat de fin, facture fournisseur, paiement par lot |
| Facturation | Visites et travaux, facture par monnaie, avoirs, suivi des échéances, enregistrement manuel et paiements en ligne |
| Paiements entrants | Carte EUR, mobile money MGA pour MVola, Orange Money et Airtel Money, virement/espèces/dépôt enregistrés manuellement, rapprochement |
| Location | Candidatures, locataires, colocataires, baux et avenants, préavis, états des lieux contradictoires, loyers, charges, cautions, quittances et impayés |
| Disponibilité | Baux, réservations temporaires, séjours courts saisis manuellement, travaux et blocages propriétaire |
| Commercialisation | Annonce par logement, lien/QR, pipeline prospect, créneaux, droit de visite, attribution apporteur |
| Commissions | Barèmes versionnés, faits générateurs distincts, acquisition, garantie, contestation, lots et reçus |
| Argent de tiers | Journal équilibré, sous-comptes, comptes rendus propriétaire, reversements, conversions externes documentées |
| Portails | Gestionnaire, agent, propriétaire, locataire, apporteur ; liens à périmètre restreint pour prospects |
| Pilotage | Indicateurs métier, exports autorisés, suivi des notifications, files d'exception, procédures d'exploitation |

Toutes ces fonctions doivent passer la recette avant la livraison. Les commutateurs de fonctionnalités servent aux essais et à la configuration de chaque conciergerie, pas à masquer une fonction obligatoire non réalisée.

### 2.3 Hors livraison

Applications natives, signature électronique de bail, horodatage tiers certifié, analyse de photos par IA, WhatsApp automatisé, messagerie interne, moteur public de recherche d'annonces, synchronisation Airbnb/Booking, import iCal, API de décaissement, comptabilité fiscale et déclarations, indexation officielle automatique et régularisation annuelle des charges. Interface française ; architecture de traduction prévue. Le mode séjours courts ne constitue pas un PMS : aucune messagerie voyageurs ni coordination de ménage liée aux canaux.

Le paiement de l'abonnement SaaS Veille reste administré hors de cette application. Les factures décrites ci-dessous sont celles des conciergeries à leurs clients, jamais celles de HayThi pour le logiciel.

### 2.4 Modèle d'exploitation retenu pour la conception

HayThi fournit le logiciel ; aucun flux client n'est versé sur un compte marchand HayThi dans cette version. Chaque conciergerie possède ses comptes marchands et bancaires, identifiés par prestataire et monnaie. Pour les loyers, cautions et reversements, le scénario de référence est une conciergerie mandatée pour encaisser pour le propriétaire. Ce scénario est bloqué en production tant que les mandats, contrats et conditions applicables ne sont pas validés (DEC-02).

Un loyer reçu directement par un propriétaire peut être enregistré avec justificatif dans un compte externe dédié ; il ne crée ni trésorerie disponible pour la conciergerie, ni somme à reverser. Le système distingue donc systématiquement **qui doit, qui reçoit et pour le compte de qui**.

## 3 Vocabulaire et règles transversales

| Terme | Définition |
| --- | --- |
| Organisation | Conciergerie cliente ; identifiant organization_id utilisé pour l'isolation |
| Compte | Identité de connexion globale ; ne contient pas un unique rôle métier |
| Appartenance | Lien entre un compte et une organisation, avec plusieurs rôles et un état |
| Locataire | Occupant lié à un bail ; entité renter, distincte de l'organisation |
| Mission | Travail planifié sur un logement avec une affectation et des consignes figées |
| Visite | Une tentative terrain, pouvant être complète ou impossible ; plusieurs tentatives conservées, une seule retenue pour achever une mission |
| Dossier de constat | Faits soumis par un agent et fichiers reçus ; scellés sans réécriture métier |
| Rapport | Présentation versionnée d'un ou plusieurs constats, observations et décisions |
| Échéance | Somme exigible à une date ; ce n'est ni une facture ni un encaissement |
| Paiement | Mouvement réel confirmé ; une tentative de paiement peut échouer sans mouvement réel |
| Affectation de paiement | Montant d'un paiement appliqué à une facture ou une échéance |
| Disponibilité | Résultat des occupations et blocages temporels, jamais une simple case libre/occupé |

**INV-01 — Isolation.** Toute donnée métier, tâche asynchrone, pièce jointe et recherche est bornée par organization_id. Une référence vers un objet d'une autre organisation est refusée.

**INV-02 — Historique.** Les documents publiés, constats scellés et écritures financières validées sont immuables dans les usages ordinaires. Une correction ajoute une nouvelle version ou une contre-écriture ; une demande de suppression suit la procédure contrôlée de conservation, sans promettre une immuabilité éternelle.

**INV-03 — Argent.** Un montant porte toujours sa monnaie et son unité. On ne somme jamais EUR et MGA. Aucun solde affiché n'est modifiable manuellement.

**INV-04 — Concurrence.** Une action répétée ne crée pas un second effet. Une modification concurrente n'écrase pas silencieusement une décision plus récente.

**INV-05 — Décisions.** Acceptation, refus, annulation, dérogation, acquisition de commission et validation financière enregistrent auteur, date serveur, objet/version, motif lorsque requis et événement métier.

**INV-06 — Visibilité.** Le destinataire voit uniquement les données utiles à son rôle et aux objets auxquels il est lié. Une visite contenant une anomalie locataire ne donne pas accès au dossier complet du locataire.

**INV-07 — Délais.** Les instants sont enregistrés en UTC. L'organisation choisit son fuseau IANA, initialement Indian/Antananarivo pour une conciergerie malgache. Les échéances de loyer sont des dates civiles dans ce fuseau. Le fuseau de l'ordinateur du gestionnaire ne change pas les règles métier.

## 4 Acteurs et responsabilités

### 4.1 Matrice des droits

| Action | Agent | Gestionnaire | Propriétaire | Locataire | Apporteur | Administrateur plateforme |
| --- | --- | --- | --- | --- | --- | --- |
| Configurer patrimoine et missions | Lecture de ses missions | Oui | Lecture de ses logements | Non | Non | Non par défaut |
| Consulter les instructions d'accès | Missions affectées, fenêtre autorisée | Oui | Ses logements | Non | Non | Non par défaut |
| Produire un constat | Missions affectées | Seulement avec rôle agent explicite | Non | Non | Non | Non |
| Relire et publier | Non | Oui | Non | Non | Non | Non |
| Consulter rapports et photos | Ses constats | Organisation | Ses logements, versions publiées | États des lieux et incidents qui le concernent | Non | Non par défaut |
| Accepter un devis | Non | Avec mandat et permission explicite | Son logement | Non | Non | Non |
| Consulter pièces de candidature | Non | Permission dossier_locatif | Sa décision et synthèse autorisée, pas les pièces par défaut | Ses pièces | Non | Non |
| Enregistrer encaissement ou préparer lot | Non | Permission finance_prepare | Non | Non | Non | Non |
| Valider décaissement ou coordonnées bénéficiaire | Non | Permission finance_approve | Non | Non | Non | Non |
| Lire grand livre et rapports financiers | Non | Permission finance_read | Ses relevés | Son compte locatif | Ses commissions | Non par défaut |
| Arbitrer attribution | Non | Permission commercial_arbitrate | Non | Non | Contester uniquement | Non |
| Gérer organisations et accès techniques | Non | Comptes de son organisation | Non | Non | Non | Oui |

Le prospect n'a pas de compte : un jeton par demande autorise seulement consulter, reporter ou annuler cette demande et déposer sa candidature. Aucun jeton de prospect ne donne accès au pipeline complet.

Les permissions financières sont séparées du rôle gestionnaire. Un lot de paiement et un changement de coordonnées bénéficiaire nécessitent deux comptes distincts : préparateur et approbateur. Les comptes techniques et administrateurs plateforme ne peuvent pas les approuver. Deux gestionnaires peuvent porter ces capacités ; aucun rôle supplémentaire visible n'est nécessaire.

Un propriétaire peut avoir des logements dans plusieurs organisations ; il choisit explicitement le contexte. Le changement d'organisation ne fusionne pas leurs rapports ni leurs soldes. Un logement a un propriétaire bénéficiaire unique dans cette livraison ; la copropriété avec répartition de revenus est hors périmètre et doit être signalée à la saisie.

### 4.2 Authentification et récupération

- Gestionnaire et administrateur : e-mail, mot de passe et second facteur obligatoire. Renforcement récent de l'authentification pour approuver un paiement ou modifier un bénéficiaire.
- Agent et apporteur : téléphone vérifié par OTP, code PIN à six chiffres et appareil enregistré. Un nouvel appareil impose un nouvel OTP. Limitation des essais et récupération assistée tracée.
- Propriétaire et locataire : lien à usage unique valable 15 minutes pour ouvrir une session ; aucune pièce privée accessible par un lien d'authentification de sept jours. Session renouvelable avec réauthentification selon le contrat technique.
- Prospect : lien d'action opaque, limité à sa demande, expiration à 30 jours après le créneau ou la clôture de la demande. Dépôt de pièces et confirmation de présence exigent une vérification téléphonique complémentaire.
- Une suspension retire les accès serveur immédiatement. Un appareil hors ligne peut encore contenir des données téléchargées ; l'expiration locale et la révocation au prochain contact limitent ce risque sans prétendre l'annuler.

### 4.3 Responsables opérationnels

Chaque organisation désigne un responsable des opérations, un contact d'urgence et deux responsables financiers pour préparation/approbation. Le responsable des opérations traite quotidiennement les missions sans retour, dossiers bloqués et rapports en attente. Le responsable financier traite quotidiennement les encaissements inconnus et rapproche les sorties. L'administrateur plateforme traite les incidents techniques, sans arbitrer les litiges commerciaux.

## 5 Vue globale des processus

~~~mermaid
flowchart TD
    A[Organisation et accès configurés] --> B[Logements et consignes validés]
    B --> C[Mission planifiée et affectée]
    C --> D[Visite sur téléphone avec ou sans réseau]
    D --> E[Dossier reçu complet et scellé]
    E --> F[Relecture et rapport publié]
    F --> G[Facture ou suivi contractuel]
    G --> H[Encaissement confirmé et rapproché]
    E --> I[Anomalie]
    I --> J[Devis accepté puis travaux]
    J --> K[Contrôle de fin et paiement prestataire]
    B --> L[Annonce et demande de visite]
    L --> M[Visite prospect et candidature]
    M --> N[Bail signé et état des lieux]
    N --> O[Loyers et dépôt de garantie]
    O --> H
    H --> P[Compte rendu et reversement]
    M --> Q[Commission selon barème]
    Q --> R[Garantie et lot approuvé]
    R --> S[Paiement sortant rapproché]
~~~

Les processus WF-01 à WF-16 ci-dessous définissent acteurs, conditions d'entrée, déroulement et issues. Une erreur de notification ne revient jamais sur une décision métier déjà enregistrée.

## 6 Organisation du patrimoine et des missions

### WF-01 Mise en service d’une conciergerie

**Déclencheur :** création de l'organisation par l'administrateur. **Responsable :** gestionnaire principal. **Entrée :** identité de l'organisation et contact principal vérifié.

1. L'administrateur crée l'organisation en état setup et invite le gestionnaire ; les secrets marchands ne sont jamais envoyés par e-mail.
2. Le gestionnaire renseigne identité de facturation, fuseau, contacts d'urgence, coordonnées, règles de relance et utilisateurs.
3. Il attribue les permissions et enregistre les comptes d'encaissement/décaissement par monnaie. Un second gestionnaire approuve les bénéficiaires sensibles.
4. Il renseigne les politiques de conservation validées et les modèles contractuels autorisés. Les paramètres non validés sont identifiés dans la page de préparation au lancement.
5. Il crée au moins un logement, un propriétaire, une checklist et un agent ; une visite d'essai est réalisée sur l'appareil cible avec des données de test.
6. La mise en production de l'organisation exige les portes de sortie de la section 18 et du plan de recette. Aucun module de V1 n'est déclaré livré parce qu'il est simplement désactivé.

**Sortie :** organisation active, comptes vérifiés et checklist de lancement horodatée. Une organisation suspendue reste consultable par ses gestionnaires autorisés mais bloque nouvelles missions, encaissements initiés et décaissements ; les confirmations de paiements déjà initiés continuent d'être traitées.

**Reprise d'une activité existante :** avant activation, le gestionnaire fixe une date de bascule. Il saisit ou importe propriétaires, logements, baux actifs, dépôts détenus, échéances ouvertes, dettes fournisseurs/commissions et soldes des comptes à cette date. Un import passe par une prévisualisation avec erreurs par ligne, puis une validation explicite ; le même lot d'import ne peut pas être appliqué deux fois. Les pièces historiques sont étiquetées « document repris », sans scellé de capture terrain rétroactif. Le responsable financier approuve des écritures d'ouverture équilibrées et rapprochées des pièces/relevés. Les anciennes échéances déjà soldées ne sont pas réémises ; les échéances futures commencent après le point de reprise. Une reprise ne déclenche ni relance, ni notification client, ni commission avant validation de la bascule. Les incertitudes restent dans une file d'écarts, pas dans des soldes inventés.

### WF-02 Créer un logement et ses consignes

**Acteur :** gestionnaire. Un logement exige nom, adresse privée, coordonnées ou mention « coordonnées à confirmer », propriétaire bénéficiaire, fuseau hérité, devise de prestation, tarif/version, quartier et consignes d'accès.

1. Le gestionnaire lie un propriétaire existant ou crée sa fiche ; le rapprochement se fait après confirmation, jamais uniquement sur un nom identique.
2. Il précise contact d'accès, détenteur des clés, mode d'accès et restrictions. Les codes d'accès sont séparés du descriptif public et exclus des PDF de rapport et journaux.
3. Il choisit une checklist publiée. Chaque point contient un identifiant stable, libellé, consigne de 140 caractères maximum, photo de référence facultative, réponse obligatoire, photo obligatoire et admissibilité de « non applicable ».
4. Il définit un minimum total de photos, initialement six pour la visite standard. « Conforme » est une réponse valide à un point obligatoire ; une anomalie exige une photo et une note. « Non applicable » exige un motif et n'est permis que si le modèle l'autorise.
5. La publication d'une nouvelle version de checklist conserve la précédente. Les missions déjà créées ne changent pas automatiquement de consignes.

**Sortie :** logement actif, checklist publiée. Un logement archivé reste lisible pour l'historique ; l'archivage est refusé tant qu'il comporte un bail actif, une mission ouverte ou un solde non traité. Une modification du propriétaire est prospective : les constats, factures et baux conservent leurs parties historiques et droits d'accès.

### WF-03 Planifier et affecter des missions

**Entrée :** logement actif, agent actif, checklist publiée. **Acteur :** gestionnaire.

1. Le gestionnaire choisit type, créneau de début/fin, agent et checklist. Un créneau de durée nulle est refusé.
2. À la création, le système fige version de checklist, paramètres de photo/GPS et tarif applicable. Les informations d'accès sont référencées séparément pour permettre leur actualisation et révocation.
3. Une récurrence accepte chaque semaine, chaque mois ou tous les N jours. Elle génère les 30 prochains jours, quotidiennement. Une occurrence est unique par récurrence et instant prévu. Pour un jour 29/30/31 absent du mois, le dernier jour du mois est utilisé.
4. Le système refuse un conflit ferme d'agent et indique les indisponibilités du logement. Les changements de créneau sont des révisions journalisées, jamais une suppression de mission.
5. La notification d'affectation est mise en file après validation de la transaction. Le tableau de bord indique séparément « affectée » et « appareil synchronisé ».
6. L'agent télécharge les missions des 48 prochaines heures et les missions ouvertes non achevées. Le gestionnaire voit la dernière synchronisation de l'appareil.

**Retard :** à deux heures après le début prévu, une mission sans début reçu ni issue connue porte le drapeau « démarrage non reçu ». Ce drapeau n'affirme pas que l'agent n'a pas commencé hors ligne. Le gestionnaire contacte l'agent si nécessaire. Une annulation exige motif ; une mission terminée ne peut pas être annulée.

**Modification de récurrence :** choix obligatoire « cette occurrence » ou « occurrences futures non commencées à partir de cette date ». Aucune réécriture des missions en cours, soumises ou terminées. Les exceptions déjà individualisées sont conservées.

**Réaffectation :** permise directement tant qu'aucune tentative n'est connue ; elle incrémente la révision d'affectation. Si une visite a commencé, le gestionnaire doit interrompre explicitement la mission et créer une mission de remplacement liée. Un ancien agent qui revient hors ligne peut déposer son dossier ; celui-ci passe en conflit et n'est ni perdu ni retenu automatiquement.

| État mission | Événement autorisé | Nouvel état | Garde et effet |
| --- | --- | --- | --- |
| scheduled | Début reçu | in_progress | Agent/révision valides ; sinon dossier en conflit |
| scheduled | Annulation | cancelled | Gestionnaire, motif obligatoire |
| in_progress | Dossier complet reçu | awaiting_review | Une tentative candidate est scellée |
| scheduled ou in_progress | Impossibilité documentée | awaiting_review | Compte rendu d'impossibilité, pas de checklist fictive |
| in_progress | Interruption | interrupted | Motif, préservation des preuves, nouvelle mission si reprise |
| awaiting_review | Complément demandé | awaiting_review | Amendement ou nouvelle mission ; original inchangé |
| awaiting_review | Issue validée | completed ou unable | Rapport préparé ; une seule tentative retenue |

Les états de synchronisation, de retard et de publication ne sont pas des états de mission. Ils sont affichés séparément pour éviter de confondre travail réalisé, données reçues et rapport transmis.

## 7 Visites terrain et constats

### WF-04 Réaliser une visite avec ou sans réseau

**Entrée :** mission téléchargée et identité locale encore utilisable. **Acteur :** agent. **Responsable de résolution :** gestionnaire.

1. À l'ouverture, l'app affiche mission, fraîcheur des consignes, nombre de dossiers non synchronisés et état réseau. L'agent vérifie les clés et règles d'accès.
2. « Commencer » crée une tentative locale et enregistre heure appareil, dernière ancre de temps serveur et position/précision si disponible. Rayon initial 200 m ; précision supérieure à 100 m, refus GPS ou absence GPS créent un drapeau de contrôle. La collecte reste possible ; la validation de mission est bloquée jusqu'à une décision motivée du gestionnaire.
3. L'agent répond aux points, prend les photos et signale les anomalies. Chaque enregistrement n'est annoncé « sauvegardé » qu'après écriture locale réussie. La caméra seule est proposée ; il n'existe pas d'import galerie dans ce parcours.
4. Les photos peuvent être recadrées ou floutées avant validation locale ; le fichier retenu pour le constat est identifié comme tel. Il n'est pas qualifié de fichier brut du capteur. L'agent confirme la photo avant calcul de son empreinte définitive.
5. La clôture vérifie réponses obligatoires, photos par point, minimum total, notes d'anomalie et tentative de position de fin. Un défaut matériel empêchant ces exigences conduit à « visite impossible/incomplète », jamais à des réponses inventées.
6. L'agent confirme le récapitulatif. La tentative devient localement closed_pending_sync et les faits sont verrouillés. Une erreur découverte ensuite crée une note complémentaire, pas une réouverture silencieuse.
7. Le système synchronise les événements puis les fichiers et le manifeste. Il affiche « enregistré sur ce téléphone », puis « envoi en cours », puis « reçu complet par Veille » uniquement après accusé serveur.
8. Le gestionnaire reçoit une tâche de relecture dès le scellé complet. L'agent peut voir les demandes de complément et le résultat de validation de ses missions.

**Visite impossible :** choisir clés absentes, accès refusé, occupant absent, risque de sécurité, matériel défaillant ou autre. Note obligatoire ; photo facultative si sa prise est sûre et possible. Le gestionnaire valide l'impossibilité, décide d'une replanification et de sa facturabilité. Aucun tarif de visite complète n'est appliqué automatiquement.

**Téléphone saturé :** l'app refuse de confirmer une nouvelle photo non enregistrée, conserve ce qui existe, propose synchronisation et libération des copies déjà acquittées. Elle n'efface jamais automatiquement une donnée en attente pour faire place.

**Profils de collecte :** le minimum de six photos concerne le modèle standard de contrôle, pas tous les types de visite. Chaque mission fige un profil explicite : contrôle standard (minimum du modèle et exigences par point), visite prospect (photos facultatives, issue et présence requises), état des lieux (points pièce/équipement/compteur prévus par son modèle, confirmations des parties traitées séparément), contrôle de travaux (photos avant/après disponibles et points de contrôle définis). Une impossibilité et une alerte urgente peuvent être déposées sans photo avec motif. Le moteur valide le profil figé de la mission ; il n'applique pas globalement les règles du contrôle standard.

### WF-05 Signaler et traiter une urgence

1. L'agent choisit la gravité urgent, décrit le danger et utilise le bouton d'appel du contact d'urgence si une liaison téléphonique est possible. Il ne doit pas attendre une synchronisation pour agir selon les consignes de sécurité de la conciergerie.
2. L'app affiche « alerte non transmise » tant que le serveur ne l'a pas reçue. Une photo n'est pas obligatoire pour déclencher l'alerte de sécurité ; elle pourra compléter le constat. Dès qu'un réseau est disponible, l'alerte est transmise avant toute photo ou reprise de fichier en attente.
3. À réception, le serveur ouvre l'anomalie urgente et met SMS/e-mail en file avec un objectif inférieur à une minute. Cette cible concerne la mise en file, pas une garantie de réception opérateur.
4. Le gestionnaire accuse prise en charge. Sans accusé après dix minutes, le contact de secours est notifié une fois et la tâche reste visible en priorité. L'absence de canal fiable déclenche le traitement manuel par le responsable des opérations.
5. La décision et les actions prises sont consignées. Aucun engagement de dépense n'est déduit de la seule urgence : il faut une acceptation du propriétaire ou un mandat d'urgence plafonné, enregistré et en vigueur.

### WF-06 Sceller relire et publier

**Déclencheur :** dossier complet reçu. **Acteur :** gestionnaire pour la validation ; serveur pour le scellé.

1. Le serveur vérifie autorisations, manifeste, empreintes, complétude et doublons dans l'organisation. Un envoi partiel reste receiving et ne produit pas de rapport publiable.
2. Il crée un scellé du dossier canonique et son horodatage de réception. Le dossier conserve les heures appareil et indicateurs de qualité ; le serveur ne transforme pas une heure déclarée en heure de capture certaine.
3. Le gestionnaire examine checklist, photos, impossibilités, anomalie GPS, conflits d'affectation, heures incohérentes et photos identiques. Il accepte chaque dérogation avec motif ou demande un complément.
4. Un complément documentaire devient un amendement scellé lié au précédent. Une nouvelle capture sur place exige une nouvelle mission de contrôle liée. Le gestionnaire ne retouche pas la photo ou la réponse d'origine.
5. Il retient une tentative pour la mission et valide l'issue. Il peut rédiger une synthèse distincte des observations de l'agent. Le résumé automatique reste déterministe, fondé sur la checklist.
6. Le serveur génère une version de rapport et un PDF. Le gestionnaire publie cette version ; l'événement produit la notification et inscrit le rapport dans le portail propriétaire.
7. Après publication, une correction crée une nouvelle version explicitement signalée au destinataire. La version précédente reste accessible comme remplacée, sauf retrait contrôlé pour confidentialité.

**Publication automatique :** option désactivée initialement. Si activée, elle ne s'applique qu'aux visites complètes sans anomalie urgente, dérogation, conflit, doublon ni complément en attente. La décision automatique et la version de règle sont journalisées ; tous les autres dossiers restent à relire.

Dans ce cas, le serveur exécute les mêmes gardes de validation et de sélection de tentative que le gestionnaire, avec un acteur système identifié, avant de publier. La seule génération d'un scellé ne suffit pas à terminer la mission.

**Délai de traitement :** objectif interne 24 h après réception complète. Au-delà, alerte gestionnaire. Le propriétaire voit une mission attendue et la mention « rapport en préparation », sans voir de notes privées ; le gestionnaire ne peut pas masquer une visite en retard en s'abstenant de publier.

**Rapport groupé mensuel :** synthèse des versions publiées sur le mois local du logement, avec liens vers les rapports individuels. Il ne fusionne pas les scellés. Une correction ultérieure produit une version de synthèse actualisée, sans réécriture de l'ancienne.

### 7.1 Vérification et export

Le propriétaire peut télécharger le PDF, le manifeste et une archive autorisée des fichiers de constat. Le PDF inclut le code du rapport, sa version et l'empreinte du manifeste ; son propre hash figure dans un reçu de publication séparé afin d'éviter une empreinte autoréférente.

La page publique de vérification n'expose ni adresse, ni noms, ni position, ni photo. Elle affiche uniquement identifiant opaque, version, date de scellement, empreintes du manifeste/PDF et état de publication. Le visiteur peut fournir l'empreinte calculée localement d'un PDF pour obtenir « correspond » ou « ne correspond pas ». Une correspondance n'atteste pas que la visite est vraie. Les dossiers retirés ou arrivés au terme de conservation ne sont pas affichés comme « intacts » par défaut.

Le dossier de réclamation contient chronologie, rapports sélectionnés, constats et amendements autorisés, photos de constat, devis/factures concernés et manifeste. La génération est asynchrone ; objectif 30 s pour le jeu de référence de recette. L'interface indique progression, échec ou lien disponible, jamais une garantie d'acceptation par l'assureur.

## 8 Anomalies interventions et travaux

### WF-07 Résoudre une anomalie et payer les travaux

**Entrées :** signalement agent, locataire ou gestionnaire. **Responsable :** gestionnaire. L'anomalie est attachée au logement, avec lien vers la visite ou le signalement initial ; elle peut donc vivre au-delà d'une visite.

1. Le gestionnaire qualifie catégorie, gravité, responsable et date cible. Il rattache un doublon à une anomalie existante sans supprimer les observations d'origine.
2. Il peut résoudre sans travaux avec justification et pièce de contrôle, ou préparer un devis. Un devis identifie fournisseur, description, montant, monnaie, honoraires de suivi séparés et date de validité.
3. Le propriétaire reçoit la version exacte du devis. L'acceptation ou le refus porte sur cette version, depuis une session autorisée. Le gestionnaire peut agir à sa place seulement avec un mandat explicite couvrant nature et plafond.
4. Une modification de montant ou périmètre crée une nouvelle version et annule la possibilité d'accepter l'ancienne. Aucun travail supplémentaire n'est réputé accepté par le silence.
5. Un devis accepté permet de créer un ordre de travail et de communiquer au prestataire uniquement les informations nécessaires. Le gestionnaire dépose la facture fournisseur ; le prestataire n'a pas de portail V1.
6. L'exécution est déclarée par le gestionnaire. Une mission de contrôle de fin produit un constat scellé et une comparaison avant/après lorsque les photos existent.
7. Après validation du contrôle, l'anomalie peut être résolue et la dette fournisseur devient payable. Sans contrôle, un paiement fournisseur est bloqué ; les acomptes prestataires ne sont pas supportés dans cette version.
8. La dette est payée via WF-14. Les travaux et honoraires sont portés sur le compte du propriétaire selon le mandat, sans les compter deux fois comme facture et comme déduction du compte rendu.

| État anomalie | Transitions | Garde |
| --- | --- | --- |
| open | triaged | Responsable et gravité renseignés |
| triaged | awaiting_approval, in_progress, resolved | Action directe uniquement sans dépense ou sous mandat valide |
| awaiting_approval | approved, declined | Décision sur devis en vigueur |
| approved | in_progress | Ordre de travail créé |
| declined | triaged, closed_no_action | Nouveau devis ou clôture motivée ; jamais resolved automatiquement |
| in_progress | awaiting_control | Exécution déclarée |
| awaiting_control | resolved, in_progress | Constat de contrôle accepté ou reprise |
| resolved ou closed_no_action | triaged | Réouverture motivée, historique conservé |

Le statut du devis, celui de l'anomalie et celui du paiement fournisseur sont distincts. Toute anomalie non clôturée apparaît dans les prochaines missions du logement. Une retenue de garantie fournisseur est désactivée initialement ; si autorisée contractuellement, le montant retenu devient une dette séparée avec date de libération et ne disparaît pas du solde.

## 9 Disponibilités annonces et prospects

### WF-08 Commercialiser et organiser une visite prospect

**Entrée :** logement publiable avec accord du propriétaire enregistré. **Acteurs :** gestionnaire, prospect, apporteur et agent.

1. Le gestionnaire publie une annonce à partir d'un brouillon : quartier, description, équipements, photos expressément choisies, loyer, charges, frais, caution, date disponible et conditions de visite. Aucune photo de constat n'est rendue publique automatiquement ; elle doit être expurgée des données privées et approuvée pour publication.
2. Une page publique et un QR code sont générés. L'adresse exacte et les instructions d'accès sont absentes. Un code d'apporteur peut accompagner le lien, sans révéler d'autres prospects.
3. Le prospect indique nom, téléphone, logement souhaité, budget facultatif, date d'entrée et accord pour traiter la demande. La prospection commerciale ultérieure a un choix distinct et facultatif.
4. La vérification du téléphone précède la réservation ferme. La normalisation au format international permet de rechercher un doublon **dans la même organisation**. Plusieurs demandes peuvent appartenir au même contact ; un téléphone partagé peut être dissocié par le gestionnaire avec motif.
5. Le système propose des créneaux de 30 minutes compatibles avec l'agent et le logement. Une réservation de créneau expire après dix minutes sans confirmation. Deux validations concurrentes sur le même agent/créneau ne peuvent pas réussir.
6. Si le logement est occupé, la visite reste awaiting_occupant tant que le créneau n'est pas accepté selon les modalités autorisées pour ce logement. Aucun silence n'est traité comme un accord. Le locataire peut proposer une autre heure ; le gestionnaire confirme au prospect.
7. Si un droit de visite est exigé à l'avance, le créneau reste awaiting_payment jusqu'au paiement ou à une dispense motivée. La durée maximale de maintien est 30 minutes, annoncée au prospect ; une confirmation de paiement plus tardive déclenche un traitement manuel ou remboursement et ne recrée pas un créneau déjà pris.
8. Une confirmation contient adresse de rendez-vous, agent et lien d'annulation ; jamais les codes d'accès du logement. Rappels à J-1 et H-2, uniquement s'ils restent futurs à la confirmation.
9. Sur place, l'agent réalise une tentative de visite liée au rendez-vous. Le prospect confirme sa présence par code SMS ; ce code atteste le contrôle du téléphone dans ce contexte, pas une identité juridique. En cas d'absence réseau, la collecte reste possible mais la présence est unverified ; confirmation différée ou arbitrage est requis pour déclencher une commission dépendante de la présence.
10. L'agent saisit l'issue interested, not_interested ou wants_to_apply. Les photos sont facultatives. Le constat est scellé et le rendez-vous devient attended, no_show ou unable après validation des faits. Trois absences pour un contact génèrent une alerte, sans blacklist automatique.
11. Le gestionnaire invite à candidater ou classe la demande perdue avec motif. Les reports et annulations conservent la réservation initiale et ses notifications annulées.

**Disponibilité temporelle :** les intervalles sont semi-ouverts [début, fin[. Une fin à 10 h et une entrée à 10 h ne se chevauchent pas, mais un temps de préparation paramétré doit être respecté. Aucune double occupation ferme n'est permise. Une réservation temporaire doit être libérée ou convertie avant l'activation d'un bail. Un préavis est une date prévisionnelle de libération, pas un deuxième blocage concurrent au bail.

**Séjours courts :** saisie manuelle du voyageur, dates, monnaie, montant et acompte attendu. La confirmation crée une période d'occupation et des échéances ; le solde doit être visible. Une annulation applique la politique acceptée et génère, si nécessaire, avoir et remboursement. Le gestionnaire peut enregistrer un intérêt concurrent en attente, mais pas passer outre l'interdiction de deux occupations fermes.

**Trajets :** pas de moteur cartographique de temps de trajet obligatoire. Un délai minimal entre visites de logements différents est paramétrable par organisation, initialement 30 minutes ; un délai inférieur déclenche un avertissement et exige un motif de dérogation. Le chevauchement horaire strict reste interdit.

**Droit de visite :** la politique contient montant, payeur, échéance, crédit éventuel sur frais d'agence, règles d'annulation par prospect/agent et remboursement. Aucune politique « non remboursable » n'est activée sans validation métier/juridique. Un crédit sur frais d'agence consomme un avoir identifié, jamais une réduction invisible de l'encaissement.

| Objet | États |
| --- | --- |
| Annonce | draft → published → suspended ou rented → archived ; republication possible après contrôle de disponibilité |
| Demande de visite | requested → awaiting_occupant / awaiting_payment / confirmed → attended / no_show / unable / cancelled |
| Candidature commerciale | new → contacted → visit_planned → visit_done → application_submitted → accepted → lease_signed ; lost depuis tout état non final |

Le pipeline porte sur une opportunité contact-logement, pas sur le contact global. Une personne peut être perdue sur un logement et active sur un autre.

## 10 Candidatures baux et états des lieux

### WF-09 Contractualiser une location

1. Le candidat reçoit un lien restreint, vérifie son téléphone et dépose les pièces requises par la politique approuvée. Le gestionnaire peut saisir le dossier au guichet en indiquant sa provenance. Les pièces sont contrôlées, chiffrées et accessibles aux seuls comptes habilités.
2. Le gestionnaire vérifie la complétude puis transmet une synthèse au propriétaire. Celui-ci accepte ou refuse ; une décision du gestionnaire exige le mandat correspondant. La décision ne crée pas encore un bail actif.
3. Une candidature acceptée pose une option temporaire exclusive sur le logement, initialement sept jours, avec date d'expiration visible. Les autres candidatures restent en attente ; elles ne sont pas supprimées. Prolongation motivée possible avant expiration.
4. Le bail brouillon fige logement, bailleur, occupants, solidarité éventuelle, dates, monnaie, loyer, régime de charges, échéance, caution, frais, mandat et modèle juridique/version. Les champs obligatoires dépendent de ce modèle validé.
5. Le document est généré puis signé hors application. Le scan signé est contrôlé et rattaché. La signature manuscrite sur écran de l'état des lieux ne remplace pas la signature du bail.
6. Le gestionnaire active le bail après contrôle des signatures, parties, dates, disponibilité et conditions contractuelles d'entrée. Le bail actif crée les échéances, l'occupation et la mission d'état des lieux d'entrée dans une transaction cohérente ; l'annonce passe rented.
7. L'agent réalise l'état des lieux pièce par pièce : observations, équipements, index des compteurs, nombre de clés et photos. Les identifiants de points sont conservés pour la comparaison de sortie.
8. Chaque partie confirme **la version exacte** du constat, via OTP contextualisé ou signature manuscrite enregistrée sur écran. Une confirmation n'est pas une garantie de validité juridique. En l'absence de réseau, la signature et les faits peuvent être collectés localement ; l'OTP sera différé.
9. Le dossier passe finalized_acknowledged si les parties attendues ont confirmé, ou finalized_with_reservations avec leurs réserves. En cas de refus ou absence, le gestionnaire clôture en finalized_unilateral avec motif et notification ; il ne fabrique jamais une acceptation.
10. Toute contestation ultérieure s'ajoute au dossier avec son auteur et sa date. Elle ne réécrit pas l'état des lieux et suspend uniquement les retenues de caution concernées.

**Avenant :** nouveau document et nouvelle date d'effet ; les paramètres antérieurs restent consultables. Après scan signé et validation, seules les échéances futures non émises sont recalculées automatiquement. Une échéance déjà émise est corrigée par ajustement explicite, y compris lorsque la date d'effet est rétroactive.

**Préavis :** demande reçue avec document, auteur et date. Le système propose une date calculée à partir de la règle contractuelle validée ; le gestionnaire confirme ou motive une correction. La date de libération reste prévisionnelle jusqu'à confirmation de sortie. Les règles légales ne sont pas déduites de valeurs génériques dans le code.

**Sortie :** planifier l'état des lieux, comparer les mêmes points à l'entrée, documenter les écarts, recueillir confirmations/réserves, enregistrer restitution des clés et date réelle. Un logement en travaux reste bloqué après fin de bail. Une sortie ne clôture pas automatiquement une dette locative.

| État bail | Transition | Condition |
| --- | --- | --- |
| draft | awaiting_signatures | Document complet généré |
| awaiting_signatures | active | Scan signé validé, période disponible et mandat applicable |
| draft ou awaiting_signatures | cancelled | Motif, libération de l'option et traitement des sommes reçues |
| active | notice_received | Préavis reçu et date de fin validée |
| active ou notice_received | ended | Date réelle de fin confirmée ; solde peut rester ouvert |
| active ou notice_received | terminated | Résiliation motivée avec pièce et date d'effet |

## 11 Loyers charges et cautions

### WF-10 Appeler encaisser et suivre les loyers

**Responsable :** gestionnaire financier. **Entrée :** bail actif. Les règles suivantes constituent le profil de calcul initial proposé ; le profil doit être validé pour les contrats réellement utilisés avant production.

1. À l'activation, générer les 12 prochaines périodes mensuelles, puis compléter cet horizon chaque jour. Clé unique bail + période + version d'appel. Une période terminée ou une émission déjà validée n'est jamais réécrite par le générateur.
2. Le profil initial utilise les mois civils. À l'entrée/sortie, loyer et charge forfaitaire sont proratisés au nombre réel de jours d'occupation dans le mois, intervalle [entrée, sortie[. Arrondi une fois par ligne, moitié vers le haut dans l'unité monétaire. Exemple de recette : 310 000 MGA pour octobre, entrée le 16, 16 jours sur 31 = 160 000 MGA.
3. Le jour d'échéance est contractuel ; 29/30/31 se ramène au dernier jour si absent. La première échéance ne précède pas l'entrée : maximum entre date d'entrée et jour contractuel du mois. Aucun intérêt ni pénalité n'est appliqué par défaut.
4. Les charges V1 sont des forfaits contractuels, **pas des provisions régularisables**. Un contrat nécessitant une régularisation doit être signalé comme non compatible avec ce profil plutôt que présenté comme traité.
5. L'appel est émis cinq jours avant l'échéance ; s'il est créé plus tard, il est émis immédiatement. Il distingue loyer, charges, ajustements, période, référence et compte destinataire.
6. Le locataire paie par le moyen compatible avec la monnaie ou présente un justificatif. Le traitement commun WF-12 confirme le paiement puis affecte les fonds. Priorité : référence explicite valide ; à défaut, plus ancienne échéance non soldée du même bail et de la même monnaie. Ne jamais affecter d'office un paiement d'un autre bail ni une caution à un loyer.
7. Un paiement partiel produit un reçu du montant effectivement affecté. Une échéance totalement soldée par paiements et ajustements autorisés peut produire une quittance selon le profil juridique validé. Les trop-perçus restent un crédit du payeur, disponible pour affectation future ou remboursement autorisé.
8. Une contre-écriture d'encaissement ou un remboursement qui rouvre une dette réévalue le solde. La quittance précédente reste archivée mais marquée remplacée/invalidée avec motif ; un nouveau document porte un nouveau numéro, jamais le même fichier réécrit.
9. Chaque jour, le système calcule les impayés. À J+1, rappel courtois ; J+7, seconde relance ; J+15, projet de mise en demeure soumis au gestionnaire ; J+30, dossier à présenter au propriétaire. Une dette contestée ou en cours de rapprochement peut être mise en pause avec motif et date de réexamen. Une relance ne part pas après solde intégral.

Le montant payé, la dette restante et le retard sont des propriétés calculées. Une échéance n'a pas un unique statut « payé/en retard » qui ferait perdre l'information d'un paiement partiel. L'ancienneté de dette est calculée par tranche : non échue, 1–7, 8–30, 31–60 et plus de 60 jours.

### WF-11 Gérer le dépôt de garantie

1. Le bail crée un dépôt attendu, séparé du loyer. La réception confirmée crédite un compte de dette de caution ; elle ne constitue ni revenu de la conciergerie ni montant disponible à reverser au propriétaire.
2. À la sortie, le gestionnaire prépare un décompte : dépôt reçu, retenues proposées, pièces, montants non contestés, montant à rendre et échéance de restitution issue de la politique validée.
3. Une retenue pour dégradation référence un point de sortie, la comparaison d'entrée disponible et un devis ou une facture. Une retenue pour dette locative référence les échéances concernées et n'est possible que si le profil contractuel validé l'autorise. Aucun transfert automatique de la caution vers les loyers.
4. Le locataire reçoit le décompte et peut contester un poste. La partie contestée est gelée ; la partie non contestée peut être remboursée. Le gestionnaire arbitre avec motif et pièces selon la procédure contractuelle applicable.
5. Après approbation, les retenues créent des écritures de transfert identifiées ; la restitution passe par un lot de WF-14. Un simple clic « restituée » sans paiement confirmé est interdit.
6. Le dépôt est closed lorsque reçu = retenues validées + remboursements confirmés et qu'aucune contestation n'est ouverte. Sinon les montants restent visibles, y compris après fin du bail.

Les états du dossier sont expected, held, settlement_draft, disputed, approved, refund_pending et closed. Le délai de restitution, les plafonds et les motifs de retenue sont des paramètres contractuels validés ; aucune valeur légale française ou malgache n'est présumée dans cette spécification.

## 12 Factures et encaissements

### WF-12 Facturer recevoir et rapprocher un paiement

**Acteurs :** gestionnaire financier, payeur, prestataire de paiement et serveur. **Entrées :** prestation validée, échéance locative, caution ou droit de visite. Chaque objet payable indique débiteur, bénéficiaire, compte d'encaissement, montant et monnaie.

**Facturation des prestations**

1. À la demande du gestionnaire, le système prépare les lignes non encore facturées : visites retenues et validées, interventions contractuellement dues, honoraires. Une visite impossible ne devient facturable qu'après décision motivée et choix du montant.
2. Les lignes d'un même client et d'une même monnaie peuvent être regroupées mensuellement. Le prix vient du tarif figé à la mission ou d'un ajustement explicite avant émission. Une visite n'est pas refacturée par une relance de génération.
3. Le gestionnaire vérifie coordonnées légales, mentions et fiscalité issues du profil de facturation validé. La TVA et les déclarations sont hors fonction comptable, mais cela ne dispense pas de mentions et calculs de facture corrects : une taxe requise doit être configurée et testée avant production.
4. L'émission attribue le numéro sous verrou transactionnel et fige la facture. Une panne d'envoi ne retire pas ce numéro. Une correction crée un avoir numéroté et, si nécessaire, une nouvelle facture.
5. L'envoi produit une notification avec lien authentifié. L'état de livraison de ce message est indépendant de la facture.

**Paiement en ligne**

6. Le payeur ouvre une demande de paiement sur une facture ou une échéance. Une facture EUR permet carte et instruction de virement ; une demande MGA permet les opérateurs mobiles configurés. Le système affiche le montant, la monnaie et le bénéficiaire. Aucun taux de conversion implicite n'est proposé.
7. Le serveur verrouille l'objet payable, calcule le reste dû, crée une tentative idempotente et appelle l'adaptateur marchand. Une tentative encore pending ou unknown interdit par défaut une nouvelle tentative sur le même objet ; le gestionnaire peut débloquer après vérification tracée.
8. Le retour navigateur indique seulement « traitement en cours ». Seule une confirmation vérifiée du prestataire ou un rapprochement manuel autorisé peut créer un paiement confirmé.
9. Un timeout devient unknown, jamais failed par supposition. Le serveur interroge le prestataire avec temporisation et reçoit les callbacks tardifs. Une confirmation tardive est appliquée même après fermeture de la page ou expiration locale de la tentative.
10. Dans une même transaction métier, le serveur enregistre le paiement une fois, affecte les fonds, crée les écritures équilibrées et programme le reçu. Les frais du prestataire sont séparés du montant brut payé par le client.
11. En cas de double paiement réel, les deux mouvements sont conservés ; le surplus devient crédit non affecté et tâche de remboursement. Aucun second mouvement réel n'est ignoré au motif que la facture était déjà soldée.

**Paiement manuel**

12. Le préparateur saisit date de valeur, montant, monnaie, payeur, compte réel destinataire, moyen, référence et justificatif. Il peut rapprocher une ligne de relevé importée. Une empreinte de justificatif identique déclenche un avertissement sans prouver à elle seule un doublon.
13. Un autre compte habilité valide l'encaissement manuel avant affectation définitive et émission de quittance. Un paiement directement reçu par le propriétaire est marqué external_owner et ne peut financer un lot de la conciergerie.
14. Une erreur après validation se corrige par contre-écriture liée, avec réaffectation contrôlée. Les périodes déjà clôturées ne sont pas réécrites.

**Exceptions et remboursement**

- Référence inconnue, montant incohérent ou monnaie inattendue : paiement enregistré en suspens, non affecté ; tâche au gestionnaire. Les fonds ne disparaissent pas parce que le rapprochement échoue.
- Remboursement : demande avec motif, montant disponible et bénéficiaire ; approbation séparée ; exécution manuelle ou mécanisme validé du prestataire ; confirmation et écritures liées au paiement initial. Le remboursement d'un paiement et l'annulation de la dette sont deux opérations distinctes.
- Contestation carte : événement conservé, somme concernée gelée, dossier à traiter et écritures de régularisation sur confirmation du débit réel. Une dette n'est pas recréée arbitrairement sans règle de traitement explicite.
- La réception d'un paiement chez le prestataire peut solder la facture avant son versement bancaire. La trésorerie utilisable pour les sorties n'augmente qu'après règlement et rapprochement sur le compte disponible.

| Objet | États ou dimensions |
| --- | --- |
| Facture | draft → issued ; annulation économique par avoir, pas par suppression |
| Recouvrement | unpaid, partial, settled, overpaid ; retard calculé indépendamment |
| Tentative | created → pending → succeeded / failed_confirmed / unknown / expired_local ; confirmation tardive toujours traitable |
| Paiement | confirmed ; remboursements, rejets et contre-écritures liés, sans mutation du mouvement initial |
| Rapprochement | unmatched → partial → matched ; correction avec historique |
| Notification | queued → sent → delivered si connu ; failed après tentatives, sans changement de dette |

## 13 Apporteurs et commissions

### WF-13 Attribuer acquérir et contester une commission

1. Le gestionnaire invite l'apporteur, vérifie téléphone et coordonnées de paiement, et lui fait accepter une version de charte. L'apporteur peut partager un lien et enregistrer un prospect minimal, mais ne voit pas ses pièces ni ses loyers.
2. L'enregistrement crée une revendication d'attribution sur le couple prospect-logement. La règle initiale est premier enregistrement serveur **éligible** : téléphone vérifié par le prospect et lien avec sa demande confirmé. Une saisie non vérifiée expire après sept jours et ne bloque pas les autres apporteurs pendant 90 jours.
3. Une attribution éligible protège l'apporteur 90 jours. Un passage en direct durant cette période ne change pas l'attribution. Un autre logement crée une attribution distincte. L'heure appareil n'arbitre jamais cette priorité.
4. Le gestionnaire configure un barème versionné avant le fait générateur : bénéficiaires, base, taux/montant, plafond, partage, délai de garantie et règles d'annulation. La commission conserve la version et les données ayant servi au calcul.
5. Le moteur traite le fait générateur une seule fois et vérifie les conditions adaptées ci-dessous. Les montants sont arrondis dans l'unité monétaire ; le reliquat d'un partage est attribué au bénéficiaire désigné dans le barème afin que la somme égale le total.
6. À acquisition, la commission devient une dette conditionnelle pendant la garantie. À expiration, elle devient payable si aucun litige ni événement d'annulation n'est ouvert.
7. La préparation et le paiement se font via WF-14. Un apporteur voit ses montants, motifs, délais et références, pas les autres bénéficiaires ni le montant du loyer utilisé comme base si ce détail n'est pas nécessaire à son relevé contractuel.
8. Une contestation gèle la commission non payée. Le gestionnaire non impliqué dans le conflit arbitre avec pièces et motif. Si tous les gestionnaires sont impliqués, un responsable habilité de l'organisation est désigné ; l'administrateur technique ne tranche pas le fond.

| Fait générateur | Conditions d'acquisition | Ne doit pas exiger |
| --- | --- | --- |
| Prospect qualifié | Demande vérifiée, critères de qualification du barème validés par gestionnaire | Bail signé ou visite si non prévus au barème |
| Visite faite | Rendez-vous attended, constat scellé retenu, présence confirmée ou dérogation motivée | Frais d'agence encaissés |
| Bail signé | Bail activé avec scan, attribution valide, conditions d'encaissement du barème satisfaites | Une visite si le barème autorise explicitement une location à distance |
| Logement apporté puis loué | Apport du logement reconnu, mandat actif, premier bail activé et condition d'encaissement satisfaite | Attribution à un prospect sans lien avec l'apport du logement |

Pour le barème initial « bail signé », les frais d'agence doivent être entièrement encaissés et une visite confirmée est exigée, sauf variante explicite de location à distance. Les autres barèmes n'héritent pas de ces conditions.

La priorité d'attribution utilise eligible_at, instant serveur où la revendication devient éligible après vérification, et non l'heure d'une saisie préalable non vérifiée. La protection de 90 jours commence à cet instant. Pour les commissions, la garantie initiale est de 15 jours à partir de earned_at, sauf durée explicitement figée dans le barème. Une commission de visite peut donc adopter une autre garantie sans changer les commissions déjà acquises.

États : pending → earned → payable → reserved_for_payout → paid. Avant paiement, cancelled exige un motif autorisé. contested suspend tout passage financier ; la résolution restaure l'état admissible ou annule. Après paiement, aucune remise à zéro : une récupération éventuelle crée une créance distincte, seulement si la charte validée l'autorise. Le défaut de conception est absence de récupération automatique.

## 14 Paiements sortants et comptes rendus

### WF-14 Préparer approuver et rapprocher un lot sortant

**Applicable à :** commissions, fournisseurs, restitutions de caution, remboursements et reversements propriétaire. **V1 :** export et exécution hors application ; aucune API de décaissement.

1. Le préparateur sélectionne des dettes payables d'une même organisation, monnaie et compte payeur. Le système exclut litiges, coordonnées non approuvées, pièces manquantes et dettes déjà réservées dans un autre lot actif.
2. Le calcul vérifie à la fois le solde bénéficiaire, les fonds réellement disponibles et les montants déjà réservés. Les cautions, fonds contestés, encaissements prestataire non encore réglés et fonds d'un autre propriétaire sont indisponibles pour cette dette.
3. Le lot fige bénéficiaires, références, montants, coordonnées, mode, frais estimés et version du modèle d'export. L'approbateur distinct vérifie puis approuve. Toute modification du lot annule l'approbation précédente.
4. Le serveur génère un fichier conforme au format accepté de l'opérateur ou de la banque. Un export générique de contrôle peut exister, mais ne doit jamais être présenté comme un fichier exécutable opérateur sans homologation.
5. Le gestionnaire transmet le fichier hors application. Le téléchargement n'est pas une preuve de paiement. L'importation d'un lot chez l'opérateur est confirmée manuellement avec référence de dépôt et date.
6. Chaque ligne est rapprochée avec un résultat réel : confirmed, failed ou unknown. Une référence saisie seule ne suffit pas : justificatif ou ligne de relevé cohérente, identité du validateur, montant, monnaie et compte sont requis.
7. Une ligne confirmed crée le paiement et les écritures une fois ; une ligne failed libère sa réservation. Une ligne unknown la conserve et empêche une nouvelle émission tant qu'une vérification ne permet pas de conclure.
8. Un lot partiellement payé reste partial. Les seules lignes échouées peuvent être reprises dans un nouveau lot lié. Re-télécharger un fichier ne crée pas un nouveau lot et affiche un avertissement de double exécution.

États lot : draft → awaiting_approval → approved → exported → submitted → partial / completed / failed. Un lot peut être cancelled avant submitted ; après transmission, il faut rapprocher les lignes et traiter leurs résultats, pas annuler globalement des paiements possiblement exécutés.

### WF-15 Clôturer la période et reverser au propriétaire

1. Le gestionnaire rapproche les comptes réels et le journal avant la clôture mensuelle. Une ligne de relevé inconnue est expliquée ou laissée dans un compte de suspens ; un écart matériel non résolu bloque le reversement concerné.
2. Pour chaque propriétaire et monnaie, le système produit un relevé : solde antérieur, encaissements pour son compte, remboursements, frais contractuels, travaux, commissions à sa charge, reversements déjà faits, sommes gelées et solde final.
3. Les règles de frais précisent qui paie chaque commission ou intervention. Une commission à charge de la conciergerie ne diminue pas le solde propriétaire. Les dépôts de garantie apparaissent en information séparée et ne sont jamais distribuables.
4. Le compte rendu est validé, versionné et publié au propriétaire. Le montant à reverser correspond au solde disponible, pas simplement à « loyers moins travaux » ; les fonds déjà réservés sont déduits.
5. Le reversement suit WF-14. Le compte rendu distingue prévu, réservé et payé. Une confirmation tardive modifie le suivi du paiement, pas les lignes figées du relevé.
6. Une correction sur une période clôturée est datée dans la période ouverte, référence la période d'origine et apparaît comme régularisation sur le prochain relevé. Une nouvelle version explicative du relevé peut être publiée, avec lien vers la précédente.

**Conversion :** les fonds restent dans leur monnaie d'origine jusqu'à une conversion effectivement réalisée hors application. Le gestionnaire enregistre montant débité, montant reçu, monnaies, taux constaté, frais, comptes et justificatif ; un second compte approuve. Le journal porte des écritures équilibrées dans chaque monnaie via comptes de passage. Une préférence EUR du propriétaire ne transforme jamais un encaissement MGA en disponibilité EUR. Sans conversion constatée, le relevé montre le solde MGA et « conversion en attente ».

## 15 Notifications interfaces et indicateurs

### 15.1 Notifications et tâches humaines

| Événement | Destinataire | Canal et moment | Exception |
| --- | --- | --- | --- |
| Affectation ou révision | Agent | Push si appareil inscrit ; SMS de secours si indisponibilité/échec technique détecté | Pas de garantie de lecture ; fraîcheur de synchronisation visible |
| Démarrage non reçu après 2 h | Gestionnaire | Tableau de bord et e-mail | Contacter agent ; ne pas conclure à une absence |
| Urgence reçue | Gestionnaire puis secours | SMS et e-mail ; objectif mise en file < 1 min | Escalade à 10 min sans accusé |
| Dossier complet | Gestionnaire | File de relecture | Rappel à 24 h ; pas de publication forcée |
| Rapport publié/corrigé | Propriétaire | E-mail ; SMS de lien si préférence locale | Échec traité dans la file de notifications |
| Devis soumis/décidé | Propriétaire ou gestionnaire | E-mail/SMS selon préférence | Version expirée non acceptable |
| Créneau confirmé/reporté | Prospect et agent | SMS ; J-1 et H-2 | Annuler rappels des anciens créneaux |
| Appel, reçu, quittance | Payeur concerné | E-mail et/ou SMS de lien | Jamais de document privé joint à un SMS |
| Relance loyer | Locataire | J+1/J+7 ; actions humaines J+15/J+30 | Solde et pause recontrôlés juste avant envoi |
| Commission évolue | Bénéficiaire | SMS de lien | Un message par transition pertinente |
| Lot inconnu ou écart | Responsables financiers | Tableau de bord + e-mail | Réservation conservée et action requise |

Tout message transactionnel possède une clé de déduplication événement + destinataire + canal. Pour les rappels non urgents, fenêtre d'envoi initiale 08 h–20 h dans le fuseau de l'organisation ; les urgences et OTP ne suivent pas cette restriction. Les préférences de canal ne suppriment pas les tâches obligatoires du tableau de bord.

### 15.2 Écrans requis

Chaque écran comporte chargement, vide, erreur récupérable, accès interdit, succès et état désynchronisé si pertinent. Les actions indisponibles affichent une raison métier. La cible de largeur minimale est 360 px.

| Surface | Écrans et éléments obligatoires |
| --- | --- |
| Agent | Connexion/installation et test caméra/GPS ; missions avec dernière synchro ; détail et accès ; checklist et références ; photo ; urgence ; visite impossible ; récapitulatif ; file d'envoi détaillée ; compléments ; état des lieux ; visite prospect ; historique et appareil |
| Gestionnaire opérations | Tableau de bord d'exceptions ; logements/propriétaires ; accès ; modèles/version ; calendrier/affectation ; relecture constats ; rapports et versions ; anomalies ; devis/ordres de travail ; notifications en échec |
| Gestionnaire location | Contacts/candidatures ; comparaison autorisée ; baux/avenants/préavis ; états des lieux ; échéancier ; impayés ; cautions et contestations ; disponibilité ; annonces ; opportunités/visites ; attribution/barèmes/commissions |
| Gestionnaire finance | Factures/avoirs ; encaissements et validation ; références inconnues ; comptes/bénéficiaires ; journal ; import de relevé et rapprochement ; relevés propriétaire ; lots, approbation et lignes de résultat ; remboursements/conversions |
| Propriétaire | Tableau de ses logements ; visites attendues/manquées ; rapports et anomalies ; devis ; candidatures à décider ; factures/paiement ; relevés et reversements ; documents/export |
| Locataire | Bail et documents ; solde détaillé ; paiement ; quittances/reçus ; signalement ; disponibilité pour visites ; état des lieux/réserves ; décompte de caution et contestation |
| Apporteur | Charte ; annonces partageables ; mes demandes et attributions ; commissions et relevés ; contestation |
| Prospect/public | Annonce ; demande et vérification téléphone ; choix créneau ; suivi/annulation ; paiement du droit ; candidature ; vérification publique minimale de rapport |
| Administrateur | Organisations ; invitations/suspensions ; configuration technique ; santé des intégrations ; audit des accès de support ; incidents ; suivi des demandes de conservation |

### 15.3 Indicateurs et définitions

Les indicateurs sont calculés par organisation et période locale, filtrables par logement et agent lorsque pertinent. Les montants restent séparés par monnaie.

- Visites attendues : missions dont le début prévu est dans la période, hors annulées ; les impossibles sont comptées séparément, pas retirées du dénominateur.
- Taux de réalisation : missions completed / missions attendues arrivées à échéance. Afficher aussi le nombre de missions sans retour serveur, pour rendre visible l'incertitude hors ligne.
- Délai de publication : published_at moins complete_received_at ; médiane et 95e percentile. Le temps de terrain déclaré n'entre pas dans cette mesure serveur.
- Retards de relecture : constats complets sans issue depuis plus de 24 h.
- Anomalies : ouvertes, urgentes sans accusé, durée depuis création, résolues et rouvertes.
- Recouvrement : échéances émises, montants affectés, dette échue et paiements non affectés ; un paiement ne compte pas deux fois sur plusieurs vues.
- Disponibilité : jours sans occupation ni blocage sur la période ; distinguer vacance commercialisable et indisponibilité pour travaux.
- Commercial : opportunités, visites confirmées/effectuées, candidatures, baux activés ; taux calculés sur une cohorte identifiée, pas sur des totaux de périodes incompatibles.
- Valeur du produit : temps de préparation des rapports, utilisation terrain sans aide, consultation de rapports authentifiée et incidents de support. Les pixels d'ouverture d'e-mail ne valent pas preuve de lecture.

## 16 Exploitation incidents et protection des données

### WF-16 Traiter une exception et préserver les dossiers

1. Une erreur persistante crée une tâche avec objet, organisation, gravité, responsable et prochaine action. Elle ne doit pas exister seulement dans un journal technique.
2. Le responsable analyse sans modifier les preuves ou écritures validées. Il peut relancer une tâche idempotente, demander un complément, rapprocher une pièce ou créer une correction autorisée.
3. En cas de risque de double paiement, il gèle les sorties concernées et vérifie les comptes réels avant toute relance. Une indisponibilité de fournisseur ne transforme pas ses tentatives en échecs certains.
4. En cas de téléphone perdu, il révoque appareil et sessions ; informe l'agent ; vérifie la dernière réception. Les données jamais synchronisées peuvent être irrécupérables : le système ne reconstruit pas un constat fictif.
5. En cas d'incident de confidentialité, il retire les accès concernés, préserve les éléments nécessaires à l'analyse dans un accès restreint et suit la procédure validée de notification. Aucune photo privée ne doit apparaître dans les outils de suivi d'erreurs.
6. La clôture de tâche exige cause, action, résultat et auteur. L'audit distingue l'action de support et la décision métier.

**Suppression et conservation :** preuves, pièces, positions, factures et journaux ont des politiques distinctes. Aucune durée de cinq ans ou douze mois du document initial n'est reprise comme obligation légale. DEC-04 doit fixer les durées, justifications et gels de conservation avant production. Le code supporte date d'expiration, gel motivé, approbation et purge vérifiable. Un gel suspend la suppression ciblée, pas toutes les données du client.

La purge supprime les objets privés et réduit les métadonnées selon la politique autorisée. Un manifeste contenant des données personnelles ou une empreinte réidentifiable n'est pas présumé anonyme. Les sauvegardes expirent selon une politique approuvée ; une restauration rejoue les demandes de purge avant réouverture du service.

**Instructions d'accès :** autorisation limitée aux missions affectées de la fenêtre téléchargée ; retrait du cache après fin de mission et réception complète, au plus tard à l'expiration locale. Aucune instruction d'accès dans une notification, un PDF, une URL ou un export ordinaire.

**Support :** l'administrateur n'a pas de lecture globale automatique des pièces. Un accès exceptionnel exige motif, périmètre et expiration, approbation organisation sauf procédure d'incident préautorisée, et journal consultable. Cet accès ne permet pas de modifier un constat ou d'approuver un paiement.

## 17 Exigences mesurables et limites

| Domaine | Cible proposée et protocole |
| --- | --- |
| Agent hors ligne | Missions du cache affichées en moins de 2 s au 95e percentile sur appareil de référence défini en recette |
| Saisie | Médiane inférieure à 2 min pour checklist standard de dix points, hors photos, déplacement, incident et état des lieux ; mesure sur essais réels |
| Synchronisation | Reprise après coupure sans doublon ; aucun accusé « reçu » avant commit serveur et contrôle des fichiers |
| Photos | Cible 600 Ko, plafond 2 Mo par photo de constat après traitement ; jusqu'à 60 photos par visite et 10 Mo par pièce documentaire |
| Rapport | PDF standard de 20 photos disponible en moins de 30 s au 95e percentile sur environnement de recette de référence |
| Urgences | Mise en file des notifications < 1 min après réception serveur ; disponibilité opérateur suivie séparément |
| Disponibilité | Objectif API 99,5 % mensuel ; méthode de mesure externe et maintenance identifiée |
| Restauration | Objectifs RPO ≤ 1 h pour données serveur acquittées, RTO ≤ 4 h ; sauvegardes chiffrées et exercice avant production |
| Accessibilité | Cibles tactiles ≥ 44 px, lecture à 360 px, contraste vérifié, navigation clavier web et statuts accompagnés de texte |
| Navigateurs | Chrome Android pour agent ; Chrome, Safari, Firefox pour les autres surfaces ; versions supportées figées dans la recette de livraison |

Le stockage local et la synchronisation en arrière-plan dépendent du navigateur et de l'appareil. L'app demande un stockage persistant si disponible, surveille l'espace et propose une synchronisation visible au premier plan ; elle ne promet pas de survivre à la suppression des données par l'utilisateur. [Documentation du stockage navigateur](https://developer.mozilla.org/en-US/docs/Web/API/Storage_API/Storage_quotas_and_eviction_criteria).

## 18 Décisions externes et conditions de lancement

Le développement doit suivre les choix proposés ici, en isolant les paramètres soumis à validation. Une décision ouverte n'autorise pas un développeur à inventer une règle fiscale, un contrat marchand ou une durée de conservation. Les responsables sont des fonctions à attribuer nominativement au démarrage.

| ID | Décision et preuve attendue | Responsable | Échéance | Effet en l'absence de réponse |
| --- | --- | --- | --- | --- |
| DEC-01 | Conciergerie de référence, corpus de logements/contrats anonymisés, prix SaaS et objectif d'usage | Responsable produit | Avant recette métier | Développement possible ; validation de valeur commerciale impossible |
| DEC-02 | Circuit réel des fonds, mandats, habilitations et droit d'encaissement/reversement validés pour la conciergerie | Direction + conseil compétent | Avant intégration avec comptes réels | Argent de tiers simulé uniquement ; lancement complet bloqué |
| DEC-03 | Prestataire carte EUR et accès marchands MVola/Orange/Airtel, frais, limites, callbacks, relevés et formats de lots vérifiés | Responsable paiements | Avant recette d'intégration | Adaptateurs simulés non acceptables pour la livraison ; aucun opérateur omis silencieusement |
| DEC-04 | Politiques de conservation, information des personnes, hébergement, accès support et procédure de purge | Direction + référent données | Avant données personnelles réelles | Environnement de test avec données synthétiques seulement |
| DEC-05 | Modèles bail/avenant/préavis, profil prorata, caution, quittances, règles de charges et droit de visite approuvés | Responsable location + conseil compétent | Avant recette locative finale | Fonctions développées avec profil de test ; activation réelle interdite |
| DEC-06 | Mentions et séries de factures/avoirs/quittances, taxes, plan de comptes opérationnel et clôtures validés | Responsable financier + comptable | Avant émission réelle | Documents de test marqués sans valeur commerciale |
| DEC-07 | Barèmes, frais à charge de qui, chartes, garanties, récupération après paiement et bénéficiaires autorisés | Direction conciergerie | Avant recette commissions | Barèmes de démonstration seulement |
| DEC-08 | Fournisseur SMS, délivrabilité sur les trois réseaux, budget, nom expéditeur et procédure de secours | Responsable exploitation | Avant recette appareils réels | Pas de validation finale OTP/urgences/notifications |
| DEC-09 | Compatibilité du projet avec les engagements professionnels évoqués dans la source | Porteur du projet | Avant engagement commercial | Décision externe au logiciel ; vérifier sans encoder d'hypothèse dans le produit |
| DEC-10 | Appareils, volumes, équipe, calendrier, objectifs d'exploitation et noms des responsables | Responsable livraison | Au lancement du projet | Pas d'engagement de date ou de capacité sans référence mesurable |

Orange indique un accès Web Payment réservé aux marchands enregistrés et cite Madagascar parmi les pays couverts ; cela ne prouve ni l'éligibilité de la conciergerie ni la disponibilité du décaissement par lot. Les contrats et capacités réels restent à vérifier pour chaque prestataire. [Documentation Orange](https://developer.orange.com/apis/om-webpay).

## 19 Écarts assumés avec la version initiale

- Livraison unique maintenue pour tout le périmètre V1 ; les propositions de réduction du premier audit ne deviennent pas des suppressions implicites.
- « Preuve d'une visite réelle » remplacée par une promesse vérifiable d'intégrité du dossier après réception.
- GPS hors rayon : collecte autorisée avec contrôle obligatoire, pour éviter de bloquer un agent hors ligne.
- Heure appareil, heure de réception et confiance temporelle séparées ; retard de synchronisation distinct d'une horloge erronée.
- Mission, tentative, synchronisation, contrôle et publication possèdent des états distincts ; cas impossible et conflit conservés.
- Compte global et appartenances multiples ; organisation et locataire ne s'appellent plus tous deux tenant.
- Moyens de paiement et bénéficiaires sont choisis par flux ; HayThi n'encaisse pas les fonds clients dans le scénario de référence.
- Grand livre équilibré, paiements séparés de leurs affectations, sorties à double validation et confirmations tardives traitées.
- Chaque fait générateur de commission a ses propres conditions ; un bail n'est pas exigé pour une prime de simple visite.
- Aucune dérogation à la double occupation ferme ; les options, préavis et périodes d'occupation sont des concepts distincts.
- Conservation bornée et purge contrôlée remplacent la promesse de non-suppression absolue.
- Les estimations historiques de 174 jours-personne ne constituent plus un engagement : chiffrage à refaire sur les lots et scénarios de recette complets.

## 20 Paramètres et effet des changements

Un paramètre ne devient modifiable dans l'interface que si son périmètre, sa permission et son effet temporel sont définis. Une configuration active porte une version et un historique. Les valeurs ci-dessous sont les choix initiaux de conception, non des durées légales.

| Paramètre | Valeur initiale ou saisie requise | Qui peut le changer | Effet |
| --- | --- | --- | --- |
| Fuseau organisation | Indian/Antananarivo au démarrage malgache | Gestionnaire principal | Nouvelles règles ; anciennes récurrences conservent leur fuseau jusqu'à migration explicite |
| Rayon et précision GPS | 200 m / 100 m | Gestionnaire opérations | Nouvelles missions ; snapshot des missions existantes inchangé |
| Checklist et minimum photos | Six pour le modèle standard, autres profils explicités | Gestionnaire opérations | Nouvelle version publiée ; pas de modification rétroactive |
| Horizon téléchargement agent | 48 h | Configuration technique validée | Nouveaux paquets ; ne détruit pas les dossiers en attente |
| Horizon récurrence | 30 j | Configuration organisation | Nouvelles occurrences ; pas d'effacement de celles déjà générées |
| Seuil démarrage non reçu | 2 h après début prévu | Gestionnaire opérations | Prochains passages du détecteur ; ne modifie pas les faits |
| Délai de revue | 24 h après réception complète | Gestionnaire opérations | Alertes futures ; mesure historique conservée |
| Publication automatique | Désactivée | Gestionnaire principal | Dossiers reçus après activation et respectant les gardes ; aucune publication rétroactive silencieuse |
| Créneau et marge de trajet | 30 min / 30 min | Gestionnaire opérations | Nouveaux créneaux ; rendez-vous confirmés inchangés |
| Maintien de créneau | 10 min ; 30 min si paiement requis | Configuration produit | Valeur copiée dans la demande au moment de sa réservation |
| Option candidature | Sept jours | Gestionnaire location | Valeur copiée à acceptation ; prolongation explicite d'une option existante |
| Attribution et revendication non vérifiée | 90 jours / sept jours | Gestionnaire habilité | Valeurs figées à éligibilité/création ; pas de retrait rétroactif d'un droit |
| Garantie commission | 15 jours, sauf barème spécifique | Gestionnaire habilité | Version de barème copiée à acquisition |
| Rappel loyer | J-5 puis J+1/J+7 ; actions humaines J+15/J+30 | Gestionnaire location sur profil validé | Envois futurs non partis ; recontrôle du solde et journal du changement |
| Politique de droit de visite | Saisie obligatoire avant facturation | Gestionnaire habilité, profil approuvé | Version acceptée par le prospect ; modification non rétroactive |
| Prorata, caution, préavis, taxes, conservation | Profils validés requis avant production | Rôles habilités selon DEC-04 à DEC-06 | Versions liées aux contrats/documents ; migrations explicites si nécessaire |
| Comptes de paiement et bénéficiaires | Configuration réelle validée | Préparateur + approbateur distinct | Aucun changement silencieux d'une tentative ou d'un lot déjà figé |

Une valeur vide sur un paramètre obligatoire bloque la commande concernée avec un message précis. Elle n'est pas remplacée par une valeur légale supposée, ni par une valeur technique arbitraire non affichée.

## 21 Révision 2.1 du 22 novembre 2026

Cette révision vient d'une relecture croisée des trois documents. Elle ne change aucune règle métier existante ; elle comble des manques de couverture.

- Contrats techniques : routes ajoutées (inscription d'appareil et PIN, invitation d'apporteur et charte, transitions de bail, déclaration d'exécution des travaux, approbation de remboursement, contre-passation de paiement, déblocage de tentative, pause de relance) ; exceptions à l'idempotence listées ; tables transversales complétées (inbox webhook, opérations asynchrones, jetons d'action, abonnements push, modèles de document) ; catalogue des types d'actions de synchronisation avec priorité d'envoi.
- Urgence : l'alerte hors ligne est transmise avant tout fichier en attente (WF-05, contrats section 4.3).
- Recette : scénarios R101 à R122 ajoutés (menaces du contrat technique section 7.4 et règles de la spécification sans test) ; traçabilité corrigée.
- Rédaction : la convention sur les mots DOIT et INTERDIT, qui n'était pas appliquée, est remplacée par une formulation lisible.
