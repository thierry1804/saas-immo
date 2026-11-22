# Veille — Plan de développement et recette de la livraison complète

Version 2.1 — 22 novembre 2026. Références : [spécification fonctionnelle et workflows](Veille-specification-fonctionnelle.md), [contrats techniques](Veille-contrats-techniques.md).

**Objectif : une seule livraison de tout le périmètre V1.** Les lots ci-dessous organisent le travail et les démonstrations intermédiaires en recette. Ils n'autorisent pas une mise en production amputée de la gestion locative, d'un opérateur mobile ou des commissions. Les scénarios décrits sont des tests à exécuter sur le produit à construire ; ce document ne constitue pas un procès-verbal de tests déjà passés.

## 1 Organisation du développement

### 1.1 Responsabilités à nommer

| Fonction | Responsabilité |
| --- | --- |
| Responsable produit | Arbitrage métier, périmètre, paramètres proposés, priorisation des corrections |
| Référent conciergerie | Parcours réalistes, consignes terrain, règles de location/commission, recette utilisateurs |
| Responsable technique | Architecture, contrats API/données, synchronisation, sécurité et intégrations |
| Responsable financier | Plan de comptes opérationnel, justificatifs, rapprochements et exactitude des soldes |
| Responsable recette | Jeux de données, scénarios, résultats reproductibles et anomalies |
| Responsable exploitation | Notifications, sauvegardes, incidents, support et préparation du lancement |

Une même personne peut porter plusieurs fonctions projet. Cela ne supprime pas l'obligation applicative de deux comptes distincts pour préparer et approuver une opération financière sensible. Chaque décision DEC-01 à DEC-10 doit avoir un responsable nominatif et une date dans le suivi projet.

### 1.2 Définition d’une tâche prête à développer

Avant démarrage d'une tâche, le ticket contient : workflow et règle concernés ; acteur et permission ; entrées obligatoires ; état initial et transition ; validations et erreurs ; écrans et messages ; contrat API et objets ; événement/outbox ; effets financiers éventuels ; comportement hors ligne ; critères de recette référencés.

Une réponse métier manquante devient soit le choix de conception explicite de la spécification, soit une décision DEC bloquant la partie concernée. Le développeur ne complète pas silencieusement une règle de remboursement, de préavis ou de commission.

### 1.3 Définition d’une tâche terminée

Code revu, migrations et contraintes cohérentes, contrats documentés, droits testés, erreurs observables, scénarios associés passés en recette, états vides/chargement/erreur réalisés, traductions externalisées, aucune donnée personnelle dans les logs, procédure de reprise lorsque l'action peut échouer après un effet externe. Une capture d'écran heureuse ne suffit pas pour une tâche qui manipule de l'argent ou une file hors ligne.

### 1.4 Chiffrage

Le total historique de 174 jours-personne doit être réestimé. Cette version explicite du travail auparavant implicite : conflits hors ligne, journal équilibré, confirmations tardives, doubles validations, purge et reprise. Chaque lot est chiffré en conception, réalisation, tests, intégration et corrections. Les délais externes de contrats marchands et validation des modèles sont suivis séparément de la charge de développement.

Le calendrier est construit à partir de la capacité réellement disponible, des dépendances et de la date d'obtention des accès prestataires. Aucune estimation non mesurée de cette version n'est présentée comme engagement de livraison.

## 2 Lots techniques et dépendances

| Lot | Contenu et résultat attendu | Dépendances | Conditions de clôture |
| --- | --- | --- | --- |
| L00 Cadrage exécutable | Paramètres proposés revus, décisions attribuées, dictionnaire, API et schémas détaillés, maquettes des workflows complets | Aucune | Aucun parcours majeur sans écran ni cas d'échec ; décision externe identifiable |
| L01 Fondations | Identité, MFA, appartenances, organisation, permissions, audit, outbox/inbox, environnements | L00 | Isolation API/objets/jobs et récupération de compte testées ; jetons, CSRF et rôles injectés (R105, R109) |
| L02 Patrimoine et planning | Propriétaires, mandats, logements, accès, checklists, tarifs, missions, récurrences et reprise des référentiels | L01 | Snapshots stables, réaffectation, imports et génération sans doublon |
| L03 Terrain hors ligne | PWA, cache, caméra, checklist, impossibilité, urgence, uploads repris, conflits | L02 | Mode avion, crash, quota, session expirée et deux appareils réussis en recette ; alerte urgente transmise avant les médias (R120) |
| L04 Constats et rapports | Manifeste, scellés, relecture, amendements, PDF, vérification publique et exports | L03 | Dossier incomplet non publiable ; hash/signature/version vérifiables |
| L05 Argent commun | Journal, reprise des soldes, receivables, paiements/affectations, factures/avoirs, bénéficiaires, réservations, lots | L01, L02 pour sources | Équilibre, concurrence, double validation et régularisations testés |
| L06 Intégrations entrantes | Carte EUR, MVola, Orange, Airtel, callbacks, polling, relevés, frais, remboursements | L05 + DEC-03 | Tests de contrat et parcours réels contrôlés pour chaque moyen |
| L07 Anomalies et travaux | Triage, devis, décisions, ordres, contrôle de fin, dette fournisseur et suivi | L04, L05 | Devis périmé refusé ; contrôle et paiement traçables |
| L08 Commercialisation | Occupation, annonces, demandes, créneaux, visites prospects, droits de visite, attributions | L02, L04, L05, L06 pour paiement réel | Pas de double réservation ; paiement tardif/annulation traités |
| L09 Location | Candidatures, baux, avenants, préavis, états des lieux, portails, loyers et caution | L04, L05, L08 | Cycle complet entrée → loyer → sortie → caution et litige ; transitions de bail (R122) |
| L10 Commissions et reversements | Barèmes, événements, garantie, litiges, formats de lots, relevés, conversions | L05, L07, L08, L09 | Résultats par ligne, disponibilités correctes et rapprochements |
| L11 Surfaces et exploitation | Portails finalisés, notifications/SMS, exports, tableaux de bord, conservation, support, sauvegardes | L01 à L10 | Recette des rôles, volumes, restauration et procédures |
| L12 Recette globale et lancement | Campagne transversale, appareils réels, décisions externes closes, formation, déploiement | Tous les lots | Portes de sortie de la section 7 satisfaites |

Les lots ne sont pas strictement séquentiels : les contrats de L05 peuvent être préparés pendant L03, et les demandes d'accès prestataires commencent dès L00. Une démonstration intermédiaire utilise l'environnement de recette et ne change pas l'objectif de livraison unique.

## 3 Jeu de données de référence

Utiliser exclusivement des identités synthétiques et des documents de test avant validation des politiques et environnements de données réelles.

- Organisations ORG-A et ORG-B, entièrement séparées. Un compte propriétaire est membre des deux pour tester le changement de contexte ; aucun partage métier implicite.
- ORG-A : gestionnaire G1 préparateur, G2 approbateur, G3 opérations ; agents A1 et A2 ; propriétaires P1 et P2 ; locataires R1 et R2 ; apporteurs F1 et F2. Un administrateur plateforme possède seulement ses permissions techniques.
- Logements H1 et H2 de P1, H3 de P2 ; H1 tarif de visite 20 EUR, H2 50 000 MGA. Chaque tarif est un exemple de test, pas un prix commercial.
- Checklist standard dix points, six photos minimum ; une photo obligatoire sur un point, un autre autorise non applicable avec motif, un troisième l'interdit. Version 1 puis version 2 avec consigne différente.
- Bail B1 de H2, loyer 1 000 000 MGA par mois, charge forfaitaire 100 000 MGA, dépôt 500 000 MGA, honoraires gestion 10 % du loyer encaissé hors charges. Le traitement des charges est explicitement distinct dans les tests financiers.
- Un autre bail de prorata : loyer 310 000 MGA, aucune charge, entrée au 16 octobre 2026, jour d'échéance 5. Premier loyer attendu 160 000 MGA, première échéance au 16 octobre selon le profil proposé.
- Une facture de 20 EUR = 2 000 unités mineures ; un paiement de 250 000 MGA = 250 000 unités. Aucun test ne doit appliquer automatiquement deux décimales au MGA.
- Travaux de 200 000 MGA à charge de P1 ; commission de 60 000 MGA à charge de la conciergerie ; second barème de visite à 10 000 MGA sans bail requis.
- Deux comptes réels simulés MGA et un EUR ; un compte de suivi externe propriétaire ; un compte prestataire avec versement différé et frais explicites.
- Téléphones de test sur les trois réseaux pour recette SMS autorisée ; appareils Android de référence, au moins un appareil à mémoire contrainte. Les caractéristiques et versions exactes sont enregistrées dans le procès-verbal.
- Réseau contrôlé : mode avion ; 400 kbit/s montant, latence 500 ms ; coupure toutes les 20 s ; retour stable. Il s'agit de profils de test, pas de mesures du réseau malgache.

### 3.1 Volumes de référence proposés

Recette de charge : 10 organisations, 1 000 logements au total, 100 agents actifs, 200 000 médias synthétiques, 100 000 écritures, 50 utilisateurs simultanés et cinq uploads concurrents. Adapter ces hypothèses avant engagement de capacité (DEC-10). Les résultats de performance ne sont valables que pour l'infrastructure et le jeu réellement mesurés.

## 4 Scénarios de recette

Les identifiants R001 à R122 sont stables (R001 à R100 : scénarios fonctionnels de la version 2.0 ; R101 à R122 : sécurité et règles complémentaires ajoutées en 2.1). Chaque résultat doit conserver build, environnement, acteur, données utilisées, observations, preuve et anomalie éventuelle. Les tests financiers et de concurrence doivent être automatisés dès que possible ; les essais terrain, compréhension et accessibilité combinent automatisation et observation humaine.

### 4.1 Identités droits et patrimoine

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R001 | G1 demande par UUID un logement, PDF et export de ORG-B | 404 sans donnée ni indice d'existence ; aucune URL média délivrée |
| R002 | Un worker ORG-A reçoit volontairement une référence de ORG-B | Refus avant traitement ; aucune notification ni écriture croisée |
| R003 | Un propriétaire change d'organisation puis ouvre l'ancienne URL | Contrôle de contexte ; pas de mélange de rapports ou soldes |
| R004 | Un agent tente de lire tarifs, loyers, pièces de candidature et journal | Accès refusé sur API et exports ; données absentes du paquet hors ligne |
| R005 | Un administrateur plateforme tente de consulter une pièce privée sans accès support | Refus ; ouverture seulement via procédure exceptionnelle tracée |
| R006 | Lien magique consommé deux fois, expiré ou préchargé par aperçu e-mail | Échange unique sur action explicite ; expiration appliquée ; GET d'aperçu ne connecte pas |
| R007 | Six OTP erronés puis connexion sur nouvel appareil | Limitation et challenge bloqué ; nouvel appareil exige vérification distincte |
| R008 | G1 approuve son propre paiement ou sa propre modification de bénéficiaire | SELF_APPROVAL_FORBIDDEN ; G2 avec permission et MFA récent peut approuver |
| R009 | Publier checklist V2 après création de mission en V1 | Mission et réponses restent en V1 ; nouvelle mission peut utiliser V2 |
| R010 | Changer propriétaire de H1 après publication d'une facture à P1 | Historique conservé ; nouveau propriétaire n'accède pas aux factures privées de P1 |

### 4.2 Planning et terrain

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R011 | Relancer trois fois le générateur de récurrence sur les mêmes 30 jours | Une mission par occurrence ; zéro doublon |
| R012 | Récurrence mensuelle le 31 sur février puis mars | Dernier jour de février puis 31 mars ; calcul dans le fuseau configuré |
| R013 | Modifier les futures occurrences après début d'une mission et individualisation d'une autre | Mission commencée et exception inchangées ; autres futures révisées |
| R014 | À H+2, aucun début reçu mais agent hors ligne | Drapeau « démarrage non reçu », pas affirmation de visite non faite |
| R015 | Visite complète en mode avion, fermeture forcée de l'app puis réouverture | Réponses/photos persistées retrouvées ; clôture locale possible et libellé non synchronisé |
| R016 | Photos manquantes, non applicable interdit et anomalie sans note | Clôture normale refusée avec points précis ; voie d'impossibilité disponible |
| R017 | Agent hors rayon, précision mauvaise puis GPS refusé | Collecte possible avec drapeaux ; validation par gestionnaire exige motif |
| R018 | Clés absentes et aucune photo possible | Compte rendu impossible accepté avec note ; pas de réponses conformes ni facture automatique |
| R019 | Urgence créée en mode avion | « Alerte non transmise » et consigne d'appel visibles ; aucune fausse réception serveur |
| R020 | Urgence reçue, puis absence d'accusé pendant dix minutes | Notification mise en file < 1 min ; une escalade au secours ; tâche reste ouverte |
| R021 | Remplir le stockage avant une nouvelle photo | Pas de succès affiché pour photo non sauvée ; données précédentes conservées |
| R022 | Deux agents/appareils transmettent une tentative de la même mission | Deux dossiers conservés, conflit visible ; une seule tentative sélectionnable atomiquement |
| R023 | Réaffecter la mission pendant que A1 travaille hors ligne | Dépôt ancien conservé en conflit ; aucun écrasement de l'affectation à A2 |
| R024 | Révoquer appareil puis essayer de synchroniser | Upload normal interdit ; récupération seulement en quarantaine après autorisation |
| R025 | Session expire avec six photos en attente | Réauthentification demandée ; aucun effacement et reprise après connexion |
| R026 | Déployer une nouvelle PWA avec changement de schéma local, file non vide | Migration préserve données/action_id ; pas d'activation destructrice au milieu de visite |

### 4.3 Synchronisation constats et rapports

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R027 | Couper le réseau à chaque partie d'upload puis reprendre | Seules parties manquantes reprises ; hash final identique ; pas de doublon de média |
| R028 | Finaliser avec cinq médias reçus sur six | MISSING_MEDIA, état receiving ; aucun scellé complet ni rapport publiable |
| R029 | Perdre la réponse après commit de finalisation puis réessayer | Même reçu et même seal_id ; un événement métier effectif |
| R030 | Renvoyer même action_id/clé avec corps différent | 409 explicite ; aucune mutation silencieuse du fait initial |
| R031 | Visite à 09 h appareil synchronisée à 11 h, horloge cohérente | Deux heures affichées, envoi différé ; pas de clock_suspect déduit du seul retard |
| R032 | Modifier l'heure appareil pendant la visite et produire une fin avant le début | Drapeau temporel ; preuve conservée et revue obligatoire |
| R033 | Recalculer manifeste dans une implémentation indépendante avec clés JSON permutées | Même JCS/hash ; tableaux selon ordre contractuel ; signature vérifiable |
| R034 | Modifier un octet d'une photo ou du PDF exporté | Vérification échoue pour ce fichier ; aucune fausse correspondance |
| R035 | Corriger une réponse après scellement et publier une correction | Original intact ; amendement et nouvelle version liés ; notification de correction |
| R036 | Présenter une photo de même hash dans deux visites de ORG-A | Alerte revue ; aucune révélation de médias d'ORG-B |
| R037 | Publication automatique active avec anomalie urgente ou dérogation GPS | Publication automatique refusée ; tâche de revue humaine |
| R038 | Propriétaire ouvre une mission terminée sans rapport publié depuis 24 h | « Rapport en préparation » visible ; gestionnaire alerté ; notes privées non exposées |
| R039 | Ouvrir page publique de vérification puis tenter de deviner d'autres codes | Aucun nom/adresse/photo ; limitation de débit ; statut remplacé/retiré exact |
| R040 | Générer PDF et dossier de 20 photos, puis télécharger deux fois | Bytes stables par version ; lien autorisé ; cible 30 s mesurée pour la génération |

### 4.4 Anomalies travaux et commercialisation

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R041 | Deux visites et un locataire signalent la même fuite | Une anomalie suivie avec trois observations liées ; aucune preuve initiale supprimée |
| R042 | Propriétaire accepte un devis alors qu'une nouvelle version existe | QUOTE_SUPERSEDED ; version nouvelle à présenter ; aucun ordre automatique |
| R043 | Refuser le devis puis tenter de marquer l'anomalie résolue sans action | Refus distinct de résolution ; clôture sans action motivée ou nouveau devis |
| R044 | Payer des travaux sans facture ou sans constat de contrôle accepté | Blocage de dette payable/lot ; pièces manquantes identifiées |
| R045 | Réouvrir une anomalie résolue après nouvel incident | Historique conservé ; prochain passage la rappelle ; nouvelles dates visibles |
| R046 | Publier une annonce avec photo issue d'un constat non approuvée et code d'accès | Refus de publication des éléments privés ; choix explicite de photos publiques |
| R047 | Deux prospects confirment le même agent au même créneau simultanément | Une seule confirmation ; l'autre obtient SLOT_TAKEN et alternatives |
| R048 | Visite demandée dans un logement occupé sans accord de créneau | awaiting_occupant ; aucune confirmation automatique par silence |
| R049 | Payer le droit après expiration et réattribution du créneau | Paiement réel enregistré ; tâche de report/remboursement ; aucune double réservation |
| R050 | Annuler/report de rendez-vous avant rappels J-1/H-2 | Rappels anciens rendus obsolètes ; notification unique du nouveau créneau |
| R051 | Visite prospect hors ligne sans SMS de présence | Constat conservé, présence unverified ; commission dépendante bloquée jusqu'à décision |
| R052 | Enregistrer même téléphone pour deux logements puis un téléphone partagé | Un contact avec opportunités distinctes ; dissociation motivée possible ; pas de fusion inter-organisation |
| R053 | Créer séjour chevauchant bail ferme, puis séjour démarrant exactement à la fin | Chevauchement refusé ; contiguïté autorisée si délai de préparation respecté |
| R054 | Réserver logement par candidature puis activer son bail | Option convertie dans la même transaction ; aucune double occupation ni trou de réservation |

### 4.5 Location et caution

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R055 | Activer bail sans scan signé ou avec modèle non validé pour production | Refus ciblé ; aucune échéance/occupation créée partiellement |
| R056 | Activer bail complet puis rejouer la commande | Un bail actif, un jeu d'échéances, une occupation et une mission d'entrée |
| R057 | Confirmer état des lieux puis tenter d'associer l'OTP à une autre version | Refus ; confirmation liée au hash exact de version |
| R058 | Une partie refuse de signer ou est absente | Clôture unilatérale motivée possible ; jamais affichée comme accord des deux parties |
| R059 | Sortie compare modèle dont un point a été ajouté depuis l'entrée | Points stables comparés ; ajout signalé sans fausse dégradation antérieure |
| R060 | Prorata du bail de test entré le 16 octobre | Loyer 160 000 MGA ; échéance le 16 octobre ; calcul reproductible |
| R061 | Avenant rétroactif après émission et paiement partiel d'une échéance | Pas de mutation du document émis ; ajustement lié et nouveau solde calculé |
| R062 | Paiement de 400 000 MGA sur appel de 1 100 000 MGA | Reçu 400 000, dette 700 000, pas de quittance de solde |
| R063 | Paiement complémentaire de 800 000 MGA sur cette dette | Affectation 700 000 ; crédit payeur 100 000 ; quittance selon profil validé |
| R064 | Caution reçue et loyers impayés | Aucun transfert automatique ; caution non disponible pour reversement |
| R065 | Retenue dégradation sans point de sortie ou justificatif | Refus ; la dette locative suit un motif distinct autorisé |
| R066 | Locataire conteste 100 000 sur une caution de 500 000, reste à rendre 400 000 | Poste de 100 000 gelé ; remboursement de 400 000 autorisable et traçable |
| R067 | Fin de bail avec dette locative et travaux après sortie | Bail ended, compte débiteur encore ouvert, logement indisponible tant que travaux bloquants |
| R068 | Relance planifiée puis paiement intégral juste avant l'envoi | Recontrôle du solde ; relance non envoyée ; aucun doublon au rejeu |

### 4.6 Encaissements journal et factures

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R069 | Émettre deux factures simultanément puis perdre la réponse d'émission | Numéros uniques ; renvoi même facture ; numéro conservé malgré échec e-mail |
| R070 | Corriger une facture émise et tenter de refacturer sa visite | Avoir et nouvelle facture explicitement liés ; source initiale non doublée |
| R071 | Initier 20 EUR et 250 000 MGA chez les adaptateurs | 2 000 unités EUR et 250 000 unités MGA ; formats externes vérifiés séparément |
| R072 | Retour navigateur « succès » sans confirmation serveur | Facture non soldée ; tentative pending/unknown, aucune quittance |
| R073 | Callback authentique répété puis callback falsifié | Un seul paiement réel pour le doublon ; faux callback rejeté ; aucune écriture |
| R074 | Timeout dix minutes puis confirmation tardive à deux heures | unknown puis succès vérifié ; un paiement et un reçu ; pas de débit réinitié automatiquement |
| R075 | Deux paiements réels différents soldent la même facture | Deux mouvements conservés ; surplus crédité ; tâche de remboursement |
| R076 | Paiement confirmé avec monnaie ou référence inattendue | Suspens et tâche de rapprochement ; aucune affectation arbitraire |
| R077 | Prestataire verse net après frais | Client crédité du brut payé ; frais séparés ; trésorerie disponible seulement au règlement |
| R078 | Saisir puis approuver paiement reçu directement par propriétaire | Compte external_owner ; aucune disponibilité ni reversement de conciergerie créé |
| R079 | Corriger paiement manuel confirmé et rembourser une échéance déjà quittancée | Contre-écriture, dette réévaluée selon décision, ancienne quittance marquée remplacée ; pas d'effacement |
| R080 | Injecter transaction comptable déséquilibrée et addition EUR/MGA | Refus atomique ; aucun solde partiel ; vues toujours séparées par monnaie |
| R081 | Exécuter le journal d'exemple de la section 6 du contrat technique | Dette P1 nulle, caution 500 000, trésorerie 600 000, revenu 100 000 ; comptes équilibrés |
| R082 | Importer deux fois le même relevé puis affecter deux fois une ligne | Import dédupliqué ; montant de relevé non consommé deux fois |

### 4.7 Commissions lots et reversements

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R083 | F1 enregistre un contact non vérifié puis F2 apporte une demande vérifiée | Pas de monopole 90 jours pour F1 non vérifié ; règle d'éligibilité appliquée et audit |
| R084 | Acquérir commission « visite » sans bail puis commission « bail » sans frais encaissés | Première acquise si présence validée ; seconde reste pending |
| R085 | Modifier barème après acquisition puis rejouer fait générateur | Montant ancien inchangé ; aucune deuxième commission |
| R086 | Contester commission payable puis atteindre fin de garantie | Gel maintenu ; pas d'inclusion en lot avant résolution |
| R087 | Deux préparateurs réservent même dette/fonds dans deux lots concurrents | Une réservation valide ; l'autre refusée sans dépassement de disponibilité |
| R088 | Changer bénéficiaire après approbation ou approuver avec session MFA ancienne | Lot inchangé ; nouvelle version exige nouveau cycle ; authentification récente exigée |
| R089 | Exporter puis re-télécharger un lot sans exécution externe | Aucun paiement confirmé ; même lot/hash avec avertissement de double exécution |
| R090 | Lot à trois lignes : confirmée, échouée, inconnue | Une payée, une réservation libérée, une réservation conservée ; reprise seulement de l'échouée |
| R091 | Demander reversement utilisant caution ou fonds d'un autre propriétaire | INSUFFICIENT_AVAILABLE_FUNDS malgré trésorerie globale positive |
| R092 | Préférence propriétaire EUR avec solde MGA sans conversion constatée | Solde MGA affiché ; aucun fonds EUR fictif ni somme multidevise |
| R093 | Conversion externe confirmée puis clôture et correction tardive | Deux jeux équilibrés, frais distincts ; correction dans période ouverte et relevé lié |

### 4.8 Exploitation qualité et livraison

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R094 | Échec SMS/e-mail puis relance du worker et modification du créneau | Métier inchangé ; notifications dédupliquées et messages obsolètes annulés |
| R095 | Purger un dossier expiré avec un gel sur une pièce puis restaurer une sauvegarde | Gel respecté ; purge autorisée vérifiable ; demandes de purge rejouées avant réouverture |
| R096 | Restaurer base/objets/secrets et reprendre outbox/inbox | RPO/RTO mesurés, hashes cohérents, aucune nouvelle émission financière intempestive |
| R097 | Tester rôles à 360 px, clavier, contraste, erreurs et réseau faible | Tous les parcours requis utilisables ; états non dépendants de la couleur ; aucune action cachée nécessaire |
| R098 | Exécuter jeu de charge et parcours agent sur appareils documentés | Mesures conservées face aux cibles ; aucune extrapolation à une infrastructure non testée |
| R099 | Exécuter un paiement réel contrôlé puis rapprochement pour carte, MVola, Orange et Airtel | Montants, références, frais et compte final vérifiés pour chacun ; simulateur seul insuffisant |
| R100 | Reprendre une activité existante, rejouer son import puis exécuter journée multi-rôle, fin de mois et sortie de bail | Ouverture rapprochée sans doublon ni anciennes relances ; objets et soldes retraçables ; zéro tâche critique ignorée ; validation métier/finance/opérations |

### 4.9 Sécurité applicative

Ces scénarios couvrent les menaces minimales du contrat technique (section 7.4). Ils sont automatisés autant que possible et rejoués à chaque livraison.

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R101 | Réutiliser une URL média après ses cinq minutes de validité, puis demander une nouvelle URL | Première requête refusée ; nouvelle URL délivrée seulement après contrôle métier |
| R102 | Ouvrir par une ancienne URL ou un ancien lien un PDF remplacé puis un PDF retiré pour confidentialité | Statut superseded ou withdrawn exact ; aucun octet du document retiré pour un accès ordinaire |
| R103 | Saisir du HTML et du script dans notes, descriptions et libellés, puis afficher en web et générer le PDF | Texte rendu littéralement, aucun script exécuté, PDF sain |
| R104 | Déposer un exécutable ou une page HTML renommé en image, et un PDF à contenu actif | Rejet par type réel et analyse de contenu ; fichier jamais accessible ni rendu |
| R105 | Envoyer à une route d'invitation ou de mise à jour un payload avec rôle, permissions ou organization_id non autorisés | Champs refusés ou ignorés ; aucun rôle ni appartenance créés hors droits |
| R106 | Double clic puis deux requêtes simultanées de paiement sur la même créance, avec et sans Idempotency-Key identique | Une seule tentative active ; la seconde renvoie le même résultat avec la clé, PAYMENT_UNRESOLVED sans |
| R107 | Demander export de relevé, lot ou journal avec un compte sans la permission requise | 403 ; aucune opération asynchrone créée ni fichier produit |
| R108 | Initier un upload sur un objet parent d'une autre mission, d'une autre organisation ou sans droit | Refus avant tout octet stocké ; aucun upload_id délivré |
| R109 | Soumettre une commande de session depuis un autre site sans jeton CSRF ; chercher un jeton de session dans URL et journaux | Commande refusée ; aucun jeton ni corps d'authentification dans les URL ou journaux |
| R110 | Webhook d'un prestataire sans signature, sans puis avec corroboration serveur à serveur | Rien n'est confirmé sur la seule notification ; un paiement et un reçu après corroboration positive |

### 4.10 Règles complémentaires

| ID | Action et contexte | Résultat attendu |
| --- | --- | --- |
| R111 | Avec un jeton de prospect, consulter, reporter et annuler sa demande, puis tenter pipeline, autre demande et annonce privée ; réessayer 31 jours après le créneau | Seule sa demande est accessible ; le reste donne 403 ou 404 ; jeton expiré après 30 jours |
| R112 | F2 demande par UUID attributions, commissions et relevés de F1 | 404 sans indice d'existence ; F1 ne voit pas le loyer servant de base si non nécessaire à son relevé |
| R113 | Option de candidature de sept jours non convertie, puis prolongation avant et après expiration | Expiration : disponibilité recalculée et gestionnaire notifié ; prolongation motivée acceptée avant, nouvelle option nécessaire après ; autres candidatures conservées |
| R114 | Suspendre ORG-A puis créer mission, initier un encaissement, soumettre un décaissement et recevoir le callback d'un paiement déjà initié | Création et initiation refusées ; callback traité ; consultation par les gestionnaires autorisés maintenue |
| R115 | Gestionnaire accepte un devis sous mandat : dans le plafond, au-delà du plafond, mandat expiré | Seule la première acceptation réussit ; mandat et décision journalisés ; refus motivés pour les autres |
| R116 | Locataire R1 après fin de bail, avec un nouvel incident sur le logement | Accès au bail, aux reçus et quittances ; aucun accès aux incidents, anomalies ou rapports ultérieurs |
| R117 | Impayé à J+15 et J+30, puis dette contestée mise en pause avec date de réexamen | Tâches humaines créées sans envoi automatique ; aucune relance pendant la pause ; tâche à la date de réexamen |
| R118 | Annuler un séjour court selon la politique acceptée, avec acompte reçu | Occupation libérée ; avoir et demande de remboursement créés ; solde exact ; remboursement approuvé séparément |
| R119 | Le demandeur tente d'approuver son propre remboursement, puis un autre compte habilité approuve et confirme l'exécution manuelle | SELF_APPROVAL_FORBIDDEN, puis approbation ; contre-écriture liée au paiement initial ; dette traitée selon décision explicite |
| R120 | Alerte urgente créée hors ligne avec six photos en attente, réseau à 400 kbit/s et latence 500 ms, puis retour du réseau | L'alerte est reçue avant le premier octet de média ; accusé distinct ; notification en file en moins d'une minute |
| R121 | Écriture rétroactive dans une période clôturée, puis régularisation en période ouverte | Écriture refusée ; régularisation datée dans la période ouverte avec original_value_date et lien vers la période d'origine |
| R122 | Parcours de bail : passage en attente de signatures, annulation avec option et sommes reçues, résiliation motivée, activation sans scan signé | Chaque transition valide produit son effet ; l'annulation libère l'option et traite les sommes ; la résiliation exige pièce et date d'effet ; l'activation sans scan est refusée |

## 5 Parcours de recette utilisateurs

### 5.1 Journée terrain

G3 planifie deux visites ; A1 synchronise le matin puis travaille hors ligne. La première visite est complète avec une fuite ; la seconde est impossible car les clés sont absentes. À la reconnexion, G3 reçoit deux issues distinctes, demande un complément documentaire sur la première, valide l'impossibilité de la seconde et publie les rapports. P1 voit la visite attendue, la fuite, les réserves et la replanification. Aucune visite impossible n'est facturée au tarif complet sans décision explicite.

### 5.2 Location apportée et premier loyer

F1 partage une annonce ; le prospect vérifie son téléphone, réserve, paie un droit de visite selon la politique et confirme sa présence. Il dépose une candidature ; P1 accepte ; G3 prépare le bail et rattache le scan signé. L'état des lieux est confirmé avec réserves sur un point. Le loyer est payé en deux fois, le dépôt sur une référence séparée. Les frais d'agence déclenchent la commission du barème bail lorsque toutes ses conditions sont satisfaites. Le crédit du droit de visite ne compte pas comme un deuxième encaissement.

### 5.3 Travaux et fin de mois

P1 accepte un devis versionné. Une facture fournisseur et un constat de fin rendent la dette payable. G1 prépare le lot ; G2 approuve ; une ligne échoue chez l'opérateur et reste à reprendre, une autre est confirmée. Le relevé propriétaire distingue dette, paiement et somme réservée. Une commission à charge de la conciergerie ne réduit pas le solde de P1. Les frais opérateur et la caution ne sont pas confondus avec les honoraires.

### 5.4 Sortie avec désaccord

Le locataire donne préavis ; le gestionnaire confirme la date et organise l'état des lieux de sortie. Une retenue est proposée avec photos et devis ; le locataire la conteste. Le montant non contesté est restitué après approbation et rapprochement. Le bail se termine, mais le litige et les dettes restent ouverts. Une décision ultérieure régularise la caution sans réécriture du constat ni de l'ancien relevé.

Ces quatre parcours doivent être réalisés avec les utilisateurs représentatifs, sans accès administrateur pour contourner un blocage. Noter les demandes d'aide et le temps réellement passé sur les actions, pas seulement le temps de réponse du serveur.

## 6 Traçabilité des workflows

| Workflow | Lots principaux | Recette de référence |
| --- | --- | --- |
| WF-01 Mise en service | L00, L01, L11 | R001 à R008, R095 à R110, R114 |
| WF-02 Patrimoine et consignes | L02 | R009, R010, R016 |
| WF-03 Planning | L02, L03 | R011 à R014, R022, R023 |
| WF-04 Visite terrain | L03 | R015 à R018, R021 à R030, R108, R120 |
| WF-05 Urgence | L03, L11 | R019, R020, R094, R120 |
| WF-06 Constat et rapport | L04 | R028 à R040, R102 |
| WF-07 Anomalies et travaux | L07 | R041 à R045, R115 |
| WF-08 Annonce et prospect | L08 | R046 à R054, R111, R113, R118 |
| WF-09 Bail et état des lieux | L09 | R054 à R059, R061, R067, R116, R122 |
| WF-10 Loyers | L05, L06, L09 | R060 à R064, R068, R071 à R082, R117, R121 |
| WF-11 Caution | L09, L10 | R064 à R067, R079, R091 |
| WF-12 Facturation et encaissement | L05, L06 | R069 à R082, R099, R106, R110, R119 |
| WF-13 Attribution et commission | L08, L10 | R051, R083 à R087, R112 |
| WF-14 Lots sortants | L05, L10 | R008, R087 à R091, R119 |
| WF-15 Compte rendu et reversement | L10 | R077 à R082, R091 à R093, R121 |
| WF-16 Exploitation | L11, L12 | R094 à R100 |

## 7 Conditions de sortie et mise en production

### 7.1 Portes obligatoires

1. **Périmètre :** tous les domaines inclus dans la spécification sont implémentés et accessibles aux rôles prévus. Aucun test obligatoire n'est déclaré réussi parce que la fonctionnalité a été désactivée.
2. **Métier :** les 122 scénarios ont un résultat documenté ; les écarts sont corrigés ou requalifiés explicitement avec justification. Les quatre parcours utilisateurs ont été validés par les responsables concernés.
3. **Argent :** aucune erreur de solde, double paiement applicatif, perte de mouvement, fuite inter-propriétaire ou approbation contournée. Rapprochement à l'unité monétaire près. Les quatre moyens entrants et les formats sortants réellement utilisés sont validés.
4. **Terrain :** fermeture de l'app, coupure réseau, fichiers incomplets, faible stockage et conflit d'affectation testés sur Android réel. Aucune perte silencieuse ni confirmation de réception fictive.
5. **Sécurité :** isolation testée sur API, médias, jobs, exports et sessions. Scénarios R101 à R110 réussis. MFA, récupération et révocation opérationnelles. Les constats de revue sécurité bloquants sont corrigés.
6. **Décisions :** DEC-02 à DEC-08 et DEC-10 clôturées pour l'organisation lancée ; DEC-01 et DEC-09 traitées par le porteur. Les règles et modèles utilisés ont leurs versions approuvées.
7. **Exploitation :** restauration exercée, procédures testées, contacts d'urgence et astreinte définis, comptes support et finance disponibles, canaux de notification vérifiés.
8. **Formation :** agent sait distinguer local/reçu, gestionnaire sait traiter conflits et anomalies, finance sait rapprocher un lot partiel et une confirmation tardive. Les consignes sont accessibles depuis l'application ou le guide livré.

### 7.2 Classification des défauts

| Gravité | Exemples | Effet |
| --- | --- | --- |
| Critique | Fuite entre organisations, argent dupliqué/perdu, faux acquittement, preuves altérées, approbation contournée | Lancement interdit ; arrêt ciblé en production |
| Majeure | Workflow obligatoire impossible, SMS d'authentification non fonctionnel, scellé invérifiable, rapprochement bloqué | Livraison complète bloquée jusqu'à correction |
| Mineure | Libellé imparfait ou défaut visuel avec parcours utilisable et information exacte | Peut être accepté avec responsable, échéance et preuve d'absence d'impact métier |

Une dérogation ne peut pas transformer un défaut d'isolation ou d'intégrité financière en défaut mineur. Une limitation commerciale ou juridique non décidée reste un blocage de lancement, pas un défaut logiciel à contourner.

### 7.3 Déroulement du lancement

Geler le périmètre de la version ; sauvegarder et vérifier les migrations ; déployer ; exécuter les tests de fumée multi-rôle ; vérifier les comptes/configurations et queues ; ouvrir la première organisation avec tous les modules du périmètre disponibles ; surveiller les premières visites et transactions ; rapprocher les mouvements réels de la journée ; consigner les incidents et leur décision de reprise.

En cas de rollback, préserver les données reçues et arrêter les commandes financières susceptibles de se répéter. Aucun fichier de paiement n'est renvoyé automatiquement. Le retour en service suit la procédure d'incident et une vérification des systèmes externes.

## 8 Informations à reporter dans le procès-verbal

Le procès-verbal de livraison doit identifier la version exacte du code, l'infrastructure, les versions navigateur/appareil, les contrats prestataires, les politiques et modèles métier, les responsables nommés, la date de la campagne, les résultats R001–R122, les anomalies résiduelles acceptées, les mesures de performance, le résultat de restauration et la décision de lancement.

Les métriques commerciales du premier mois — usage sans aide, temps de rapport, consultation des propriétaires, coût de support et engagement payant — servent à évaluer le produit après livraison. Elles ne remplacent pas les conditions de sécurité, de fonctionnement et de rapprochement nécessaires avant le lancement.
