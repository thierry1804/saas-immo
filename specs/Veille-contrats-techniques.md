# Veille — Contrats techniques de développement

Version 2.1 — 22 novembre 2026. À lire avec la [spécification fonctionnelle](Veille-specification-fonctionnelle.md) et le [plan de recette](Veille-plan-developpement-recette.md).

Ces contrats fixent les comportements observables et les invariants à implémenter. Ils ne prétendent pas constituer une implémentation existante ou une intégration prestataire déjà vérifiée. Les états indiqués en anglais sont les valeurs persistées ; les interfaces affichent des libellés français.

## 1 Architecture et frontières

### 1.1 Architecture de référence

Monolithe modulaire Laravel pour API, règles métier, tâches et génération documentaire ; PostgreSQL pour données transactionnelles ; Redis pour files et cache non autoritatif ; stockage objet privé compatible S3 ; client Next.js avec surfaces adaptées aux rôles, dont une PWA agent. Une seule base de code backend et une seule base de code frontend suffisent. Aucun microservice n'est requis pour cette version.

Versions des runtimes et dépendances : sélectionner au démarrage des versions maintenues, les verrouiller dans les fichiers de dépendances et documenter les politiques de mise à jour. Cette spécification n'impose pas un numéro de version non vérifié.

Modules et propriété des données :

| Module | Responsabilité | Commandes interdites aux autres modules |
| --- | --- | --- |
| Identity | Comptes, appartenances, appareils, sessions, capacités | Modifier directement un rôle ou contourner l'organisation courante |
| Property | Propriétaires, logements, accès, checklists et versions | Modifier une version de consigne déjà figée |
| Fieldwork | Missions, affectations, tentatives, captures, synchronisation | Écrire directement completed sans décision de contrôle |
| Evidence | Manifeste, scellés, amendements, publication | Remplacer un objet scellé ou un PDF publié |
| Maintenance | Anomalies, devis, ordres, contrôles | Déclencher un paiement sans dette validée |
| Lettings | Contacts, opportunités, candidatures, baux, disponibilité | Activer un bail malgré une occupation ferme concurrente |
| Billing | Factures, appels, avoirs, reçus/quittances | Marquer payé sans affectation de paiement réelle |
| Payments | Tentatives, paiements, remboursements, prestataires, rapprochement | Mettre à jour un solde métier par simple callback non vérifié |
| Ledger | Transactions équilibrées et soldes calculés | Accepter une écriture déséquilibrée ou multidevise sans ventilation |
| Payouts | Bénéficiaires, lots et réservations de fonds | Payer une ligne réservée ailleurs ou inconnue chez l'opérateur |
| Referrals | Attribution, barèmes, commissions, litiges | Acquérir une commission sans fait générateur et preuve de ses conditions |
| Communications | Notifications, modèles et tentatives d'envoi | Annuler un événement métier en cas d'échec e-mail/SMS |

Les modules communiquent par services applicatifs et événements métier explicites. Éviter les déclencheurs implicites dispersés qui généreraient des paiements ou documents à chaque simple sauvegarde d'un modèle.

### 1.2 Transaction et publication d’événements

Une commande valide ses droits et sa version, prend les verrous nécessaires, modifie l'agrégat et inscrit audit + outbox dans la même transaction PostgreSQL. Un worker publie ensuite les événements de l'outbox. Chaque consommateur déduplique par event_id + nom du consommateur. Le système vise un **effet métier unique malgré des livraisons répétées**, pas une livraison réseau exactement une fois.

Un événement contient event_id, organization_id, aggregate_type, aggregate_id, aggregate_version, event_type, occurred_at serveur, actor_id, correlation_id, causation_id et payload minimal versionné. Les photos, secrets, pièces d'identité et codes OTP ne figurent jamais dans les payloads de journal ou d'observabilité.

### 1.3 Isolation

Toutes les tables métier portent organization_id, y compris les tables enfants. Les relations utilisent des clés étrangères composites (organization_id, id) lorsqu'elles lient deux objets métier. Les UUID seuls ne constituent pas une autorisation.

L'organisation est résolue depuis l'appartenance authentifiée, jamais acceptée sur la seule foi du JSON client. Les workers héritent explicitement de l'organisation de l'événement ; les tâches de traitement global itèrent par organisation. Les sessions de base applicatives ne sont pas propriétaires des tables protégées. Des politiques de sécurité par ligne PostgreSQL apportent une seconde barrière, avec contexte transactionnel et tests de réutilisation des connexions. Les migrations utilisent un rôle distinct.

Les clés d'objets médias incluent organisation et identifiant opaque. Chaque URL temporaire est délivrée après contrôle métier, durée initiale cinq minutes. Les listes de recherche, exports, compteurs et erreurs ne doivent pas révéler l'existence d'un objet d'une autre organisation.

## 2 Modèle de données

### 2.1 Conventions

UUID pour les identifiants métier, created_at et updated_at UTC, version entier croissant sur agrégats modifiables. Les dates civiles utilisent un type date ; le fuseau est porté par l'organisation/logement et figé dans les règles de récurrence. Les bornes d'occupation sont des instants ou dates normalisées, toujours [début, fin[.

Les montants sont stockés en bigint dans l'unité contractuelle de l'application : EUR en centimes, MGA en ariary entiers. L'adaptateur prestataire convertit explicitement si son API attend un autre format ; cette convention est testée par opérateur. Dans les JSON financiers, amount_minor est une chaîne décimale d'entier pour éviter les pertes de précision JavaScript. currency prend EUR ou MGA. Taux et pourcentages utilisent décimaux exacts, jamais des flottants binaires ; arrondi à la dernière étape monétaire.

Les statuts ne sont pas modifiables par un PATCH générique ; seules les commandes de transition peuvent les changer. Les champs de solde sont des projections recalculables, pas une source de vérité indépendante.

### 2.2 Identités et patrimoine

| Entité | Champs métier minimaux | Contraintes |
| --- | --- | --- |
| organization | nom, état, fuseau, identité de facturation, politiques/version, contacts urgence | État setup/active/suspended/closed |
| account | nom, téléphone/e-mail normalisés et vérifiés, secrets hachés, état | Identité globale ; aucun accès métier par défaut |
| membership | organization_id, account_id, rôles, permissions, état | Unique organisation + compte |
| device | account_id, identifiant, clé publique si utilisée, inscrit/révoqué le, dernière synchro | Un appareil appartient à un compte ; réauthentification au changement |
| owner | compte lié facultatif, identité, contacts, préférences | Données métier par organisation |
| property | owner_id courant, adresse, quartier, coordonnées, fuseau, état, caractéristiques | Un bénéficiaire propriétaire courant dans V1 |
| property_ownership | property_id, owner_id, dates, motif, droits historiques | Pas de transfert rétroactif automatique |
| access_instruction | property_id, contenu chiffré, version, fenêtre, contact | Endpoint dédié ; exclu des exports ordinaires |
| mandate | owner_id, property_id, droits, plafonds/devise, dates, document/version | Validation juridique extérieure enregistrée, pas déduite du fichier |
| checklist_template | nom, type, état | Modèle logique |
| checklist_version | template_id, numéro, publié le, minimum photos, paramètres GPS | Immuable après publication |
| checklist_item | version_id, stable_item_key, ordre, libellé, consigne, photo référence, contraintes | Identifiant stable pour entrée/sortie ; version distincte pour évolution |
| tariff_version | property_id ou contrat, type, montant, monnaie, dates | Copie figée au moment de la mission |

### 2.3 Terrain et maintenance

| Entité | Champs minimaux | Contraintes |
| --- | --- | --- |
| recurrence | property_id, règle, fuseau, agent, template_version, exceptions, actif | Une occurrence unique par recurrence_id + scheduled_start |
| mission | logement, type, créneau, état, révision affectation, consignes/tarif figés, selected_visit_id | Une tentative retenue au plus ; délai de retard dérivé |
| assignment_history | mission_id, agent_id, revision, valid_from, motif | Historique conservé ; ancienne révision détectable |
| visit_attempt | mission_id, agent, device, revision, type, local_visit_id, état réception, issue | Unique organisation + local_visit_id ; plusieurs tentatives possibles |
| visit_fact | visit_id, action_id, séquence, type, charge utile, heures, réception | Événements client dédupliqués ; pas de mutation après scellement |
| checklist_answer | visit_id, stable_item_key, version, réponse, note | Une réponse finale par point dans le manifeste |
| media_asset | propriétaire métier, storage_key/version, taille, type, sha256, état | Identifiant opaque ; upload non finalisé jamais visible comme preuve |
| evidence_seal | visit_id, manifest_version, manifest_hash, signature, key_id, received_at, parent_seal_id | Insertion seulement ; appendices séparés |
| review_decision | mission_id, visit_id, décision, drapeaux résolus, motifs, auteur | Une sélection active ; décisions conservées |
| report_version | rapport logique, version, seal_ids, contenu snapshot, pdf_asset_id, état, publication | PDF et contenu immuables après publication |
| publication_receipt | report_version_id, pdf_hash, manifest_hashes, signed_at, signature | Le hash PDF n'est pas inclus dans le PDF lui-même |
| anomaly | property_id, source_type/id, catégorie, gravité, état, responsable, date cible | Source peut être visite, locataire ou saisie gestionnaire |
| anomaly_observation | anomaly_id, source, note, médias, auteur | Nouvelles observations, pas écrasement de la source |
| quote_version | anomaly_id, fournisseur, description, montants/devise, honoraires, validité | Acceptation rattachée à une version précise |
| approval | objet/version, décision, acteur, mandat éventuel, date | Unicité décision active sur la version |
| work_order | quote_version_id, fournisseur, état, facture, control_visit_id | Payable seulement après contrôle accepté |

### 2.4 Location et commercialisation

| Entité | Champs minimaux | Contraintes |
| --- | --- | --- |
| contact | nom, téléphone normalisé, canal, consentements | Déduplication assistée interne, possibilité de téléphone partagé |
| opportunity | contact_id, property_id, source, état, attribution | Une opportunité active par couple sauf exception motivée |
| listing | property_id, version contenu/photos approuvées, état, slug opaque, accord propriétaire | Aucun code d'accès ou adresse exacte public |
| visit_request | opportunity_id, agent, créneau, état, hold_expires_at, policy_version | Réservation ferme sans chevauchement agent |
| occupancy | property_id, source_type/id, start, end, nature, état, expiry | Contrainte d'exclusion sur périodes fermes incompatibles |
| short_stay | property_id, voyageur, dates, prix/devise, politique, état | Source d'occupation et d'échéances |
| rental_application | opportunity_id, état, pièces, synthèse, décision, dates | Accès limité ; option logement après acceptation |
| renter | compte lié éventuel, identité minimale, coordonnées | Jamais nommé tenant dans le schéma |
| lease | property_id, owner_snapshot, document_template_id + version, paramètres, dates, montant/devise, état, scan signé | Activation atomique avec occupation ; historique des parties |
| lease_party | lease_id, renter_id, type, solidarité, dates | Plusieurs occupants ; le solde demeure celui du bail |
| lease_amendment | lease_id, version, changements, effet, scan, validation | Immuable une fois validé |
| lease_notice | lease_id, auteur, réception, règle/version, date proposée/validée, document | Motif obligatoire si dérogation au calcul |
| inventory | lease_id, entry/exit, visit_id, état, entrée de référence | Comparaison via stable_item_key ; ajouts/retraits explicités |
| inventory_ack | inventory_id/version, partie, méthode, preuve, date, réserves | Chaque confirmation liée au hash du constat |
| dispute | objet/type/id, auteur, montant concerné éventuel, état, décision | Ne gèle que l'objet/montant concerné |

### 2.5 Argent et apporteurs

| Entité | Champs minimaux | Contraintes |
| --- | --- | --- |
| receivable | débiteur, bénéficiaire, source, compte destinataire, monnaie, montant, échéance, pause de relance (motif, date de réexamen, auteur) | Objet payable commun ; total des affectations contrôlé |
| rent_due | lease_id, début/fin période, version, lignes, receivable_id | Pas de double émission par période/version ; ajustements séparés |
| invoice | client, numéro, monnaie, état, dates, profil fiscal/version, total | Numéro attribué à émission ; lignes figées |
| invoice_line | invoice_id, source/type/id, montant, taxes, quantité, libellé snapshot | Source facturée une fois sauf ajustement lié |
| credit_note | invoice_id, série/numéro, lignes, motif, receivable_adjustment | N'efface pas le paiement ; solde crédit distinct |
| payment_attempt | receivable_id, provider_account, idempotency_key, external_ref, état, montant/devise | Une tentative non résolue active par objet sauf déblocage tracé |
| payment | compte réception, payeur, origine, external_transaction_id, brut/devise, confirmé le | Unique compte prestataire + transaction externe ; immuable |
| payment_allocation | payment_id, receivable_id, montant, référence de contre-affectation | Somme affectée ≤ disponible ; même organisation et monnaie |
| refund | payment_id, montant, motif, décision, payout_line_id ou référence prestataire | Somme remboursements ≤ montant remboursable |
| receipt_document | nature, série, numéro, paiement/échéance, version, remplace_id, fichier | Réédition non destructive ; aucune confusion reçu/quittance |
| deposit_case | lease_id, attendu/devise, état, politique/version | Montants dérivés des mouvements et retenues |
| deposit_deduction | deposit_id, motif, inventaire ou dette liée, justificatif, montant, décision | Gel sur poste contesté |
| provider_account | organisation, prestataire, marchand, monnaie, comptes liés, secrets référencés | Aucune clé secrète dans le payload client |
| ledger_account | monnaie, catégorie, bénéficiaire/propriétaire, compte réel éventuel | Un compte une monnaie ; fonds séparables par propriétaire |
| journal_transaction | événement source, date valeur, date comptable, correction_de, justification | Débit total = crédit total par monnaie ; validation atomique |
| journal_line | transaction_id, account_id, debit_minor, credit_minor, dimensions | Une seule colonne positive ; somme contrôlée |
| bank_statement_line | compte, date, référence, montant, monnaie, import_hash | Import idempotent ; rapprochements conservés |
| reconciliation | ligne externe, mouvements internes, montants affectés, état | Pas d'utilisation répétée du même montant de relevé |
| beneficiary_version | bénéficiaire, coordonnées, monnaie, vérifié/approuvé par, dates | Deux comptes distincts ; version figée dans lot |
| payout_batch / payout_line | compte payeur, monnaie, état, approbations / dette, bénéficiaire/version, montant, référence | Réservation de dette et de fonds ; résultat par ligne |
| fund_reservation | dette, compte, propriétaire, lot/ligne, montant, état | Création/libération transactionnelle avec verrou solde |
| owner_statement | propriétaire, monnaie, période, version, snapshot, validation/publication | Rapprochable au journal ; corrections ultérieures liées |
| fx_conversion | comptes source/cible, montants, taux décimal, frais, justificatif, approbation | Conversion externe réelle seulement ; journal dans les deux monnaies |
| referrer | compte, charte/version/date, état | Pas de lecture des autres apporteurs |
| attribution_claim | opportunity ou property, referrer, origine, vérification, expiry, arbitrage | Une attribution éligible active par objet |
| commission_rule_version | fait, base, calcul, plafond, partage, garantie, conditions, frais à charge de | Immuable après utilisation |
| commission | bénéficiaire, fait source, règle/version, base, montant/devise, état, garantie_fin | Unique règle + fait + bénéficiaire ; pas de double acquisition |

Tables transversales : audit_event, outbox_event, consumer_receipt, idempotency_record, notification, notification_attempt, scheduled_task, exception_task, retention_policy, retention_hold, erasure_request et document_sequence, plus :

| Table | Contenu et contraintes |
| --- | --- |
| inbox_event | Webhooks prestataires : corps brut, preuve de signature ou de corroboration, compte prestataire résolu, identifiant d'événement externe, état de traitement ; unique compte prestataire + identifiant d'événement |
| async_operation | operation_id, type (export, génération PDF, import), demandeur, état, résultat (asset ou erreur), expiration ; suivi par l'URL des réponses 202 |
| action_token | Jeton opaque stocké haché, objet visé, portée, expire_at, révoqué le, dernier usage ; jetons de prospect expirant 30 jours après le créneau ou la clôture de la demande |
| push_subscription | device_id, endpoint, clés, état, dernière réussite ; supprimée à la révocation de l'appareil |
| document_template | Type (bail, avenant, préavis, quittance, reçu), version, contenu, état de validation (DEC-05, DEC-06), publié le ; immuable après usage | Toutes les tables métier portent l'organisation même si la colonne n'est pas répétée dans les tableaux.

La reprise initiale utilise import_batch (source, empreinte, mapping/version, date de bascule, état, approbations) et import_row (référence externe, données validées, résultat, objet créé). Une clé unique organisation + système source + référence externe déduplique les objets repris. Les transactions d'ouverture utilisent une référence d'import unique, des comptes d'ouverture validés et des lignes équilibrées. Un paiement ou document historique porte une provenance imported ; il ne déclenche pas les événements commerciaux d'une nouvelle opération. L'API expose POST /imports/preview, POST /imports/{id}/validate et POST /imports/{id}/apply, avec droits métier et approbation financière distincte pour les soldes ; un lot appliqué ne peut pas être rejoué comme nouveau.

Le champ eligible_at de attribution_claim est fixé par le serveur au succès de vérification et arbitre la priorité ; created_at seul ne suffit pas. commission stocke earned_at et guarantee_ends_at calculé à partir de la règle copiée. Le profil de collecte figure dans le snapshot de mission et distingue photos obligatoires de contrôle, présence prospect et confirmations d'état des lieux.

## 3 API et commandes

### 3.1 Convention commune

Base /api/v1. Authentification web par session sécurisée avec protection CSRF ; l'agent utilise un appareil enregistré et une session renouvelable. HTTPS obligatoire. Chaque route privée résout identité, appartenance, permission, périmètre d'objet puis garde d'état.

Les GET de listes sont paginés par curseur (limite par défaut 50, maximum 200). Les exports lourds sont des tâches asynchrones. Les filtres sont explicitement autorisés ; aucune expression SQL ou champ arbitraire fourni par le client.

Les créations et commandes POST exigent Idempotency-Key. Exceptions : les webhooks prestataires (déduplication par inbox, section 6.3), les routes /auth/* (limitées par challenge et par débit) et POST /agent/sync/actions (déduplication par action_id). Les modifications d'agrégat exigent expected_version. Une réutilisation de clé avec un corps différent retourne 409 IDEMPOTENCY_KEY_REUSED ; une version périmée retourne 409 VERSION_CONFLICT avec version courante et moyens de reprise sans fuite de données. Les suppressions métier deviennent des commandes d'archivage.

Réponse réussie : data, meta.request_id, meta.server_time, meta.resource_version. Une commande acceptée pour traitement asynchrone retourne 202 avec operation_id et URL de suivi ; cela ne signifie pas que le traitement est terminé.

Erreur : error.code, error.message français, error.field_errors, error.retryable, error.request_id. Codes HTTP : 400 schéma illisible, 401 session absente/expirée, 403 action interdite sur objet visible, 404 objet inconnu ou extérieur au périmètre, 409 conflit métier/concurrence, 422 champs invalides, 429 limitation, 503 indisponibilité temporaire.

Codes métier minimaux : INCOMPLETE_EVIDENCE, ASSIGNMENT_CONFLICT, MISSING_MEDIA, CHECKLIST_VERSION_MISMATCH, VISIT_ALREADY_SELECTED, OCCUPANCY_CONFLICT, SLOT_TAKEN, QUOTE_SUPERSEDED, PAYMENT_UNRESOLVED, CURRENCY_MISMATCH, INSUFFICIENT_AVAILABLE_FUNDS, BENEFICIARY_NOT_APPROVED, SELF_APPROVAL_FORBIDDEN, LEGAL_PROFILE_NOT_VALIDATED, DOCUMENT_NOT_READY.

### 3.2 Commandes de référence

Les routes ci-dessous décrivent le contrat fonctionnel à transcrire en OpenAPI dans le dépôt avant implémentation. Les opérations GET correspondantes doivent appliquer exactement les mêmes périmètres ; aucune route de mise à jour libre d'un état n'est ajoutée.

| Méthode et route | Acteur | Entrée essentielle | Résultat / événement |
| --- | --- | --- | --- |
| POST /auth/otp/request et /verify | Public limité | Canal, intention, challenge, code | Session/appareil ou refus ; réponses anti-énumération |
| POST /auth/password et /mfa | Gestionnaire/admin | Identifiants puis second facteur | Session avec capacités |
| POST /auth/magic/request et /exchange | Public limité | Adresse puis jeton à usage unique | Session du compte concerné |
| POST /devices/{id}/revoke | Compte ou gestionnaire habilité | Motif | Révocation et audit |
| POST /devices et /devices/{id}/pin | Agent ou apporteur après OTP vérifié | Identifiant d'appareil, clé publique si utilisée, PIN à six chiffres, abonnement push facultatif | Appareil inscrit ; un nouvel appareil impose un nouvel OTP |
| POST /organizations/{id}/memberships | Gestionnaire | Compte invité, rôles/permissions autorisés | Invitation |
| POST /referrers/invitations et /referrer/charter/accept | Gestionnaire habilité puis apporteur | Téléphone à vérifier ; version de charte | Apporteur invité ; charte acceptée et datée |
| POST /properties | Gestionnaire | Champs WF-02 | Logement créé |
| PATCH /properties/{id} | Gestionnaire | Champs autorisés, expected_version | Nouvelle version courante, histoire préservée |
| POST /checklists/{id}/versions | Gestionnaire | Points et contraintes | Version brouillon |
| POST /checklist-versions/{id}/publish | Gestionnaire | expected_version | Version immuable publiée |
| POST /missions et /recurrences | Gestionnaire | Logement, agent, créneau/règle, checklist_version | Mission(s) créées |
| POST /missions/{id}/reassign | Gestionnaire | Agent, motif, expected_version | Révision d'affectation |
| POST /missions/{id}/cancel ou /interrupt | Gestionnaire | Motif, expected_version | État et événement, aucune preuve effacée |
| GET /agent/sync | Agent | Cursor, device_id | Changements autorisés et tombstones |
| POST /agent/sync/actions | Agent | Lot d'actions défini en section 4 | Résultats par action, pas d'ACK fictif global |
| POST /media/uploads | Rôle autorisé | Objet parent, taille, MIME, sha256 | upload_id et paramètres limités |
| POST /media/uploads/{id}/complete | Même rôle | Liste des parties, hash final | Objet vérifié ou rejeté |
| POST /visits/{id}/finalize | Agent | Manifeste local, version, hash | Reçu complet et scellé, ou liste des manquants |
| POST /visits/{id}/amendments | Auteur autorisé | Motif et nouvelles données/pièces | Nouveau scellé lié |
| POST /missions/{id}/review | Gestionnaire | visit_id, issue, décisions sur drapeaux, version | Mission completed/unable ou complément |
| POST /reports/{id}/publish | Gestionnaire | Version exacte, expected_version | Publication et notifications |
| POST /reports/{id}/exports | Destinataire autorisé | Type PDF/claim_bundle et périmètre | Opération asynchrone |
| GET /verify/{opaque_code} | Public limité | Code ; hash soumis facultatif | Métadonnées minimales et correspondance |
| POST /anomalies | Agent/locataire/gestionnaire | Source, catégorie, gravité, note/médias | Anomalie ouverte, urgence si nécessaire |
| POST /anomalies/{id}/triage ou /resolve ou /reopen | Gestionnaire | Motif, pièces, expected_version | Transition contrôlée |
| POST /anomalies/{id}/quotes | Gestionnaire | Fournisseur, lignes, validité | Nouvelle version de devis |
| POST /quote-versions/{id}/decision | Propriétaire/mandataire | Décision, version, mandat éventuel | Acceptation/refus définitif de cette version |
| POST /work-orders | Gestionnaire | Devis accepté | Ordre et demande de contrôle |
| POST /work-orders/{id}/declare-execution | Gestionnaire | Date d'exécution, pièces | in_progress puis awaiting_control ; mission de contrôle créée |
| POST /work-orders/{id}/validate-completion | Gestionnaire | Constat de contrôle et facture | Dette fournisseur payable |
| POST /listings/{id}/publish | Gestionnaire | Version, accord propriétaire | Page publique |
| GET /public/listings/{slug}/slots | Public limité | Intervalle borné | Créneaux sans données privées d'agents |
| POST /public/listings/{slug}/visit-requests | Prospect vérifié | Contact, créneau, source, politique acceptée | Réservation temporaire ou conflit |
| POST /visit-requests/{id}/confirm ou /reschedule ou /cancel | Porteur du lien/gestionnaire | Jeton restreint, choix, version | État, libération/réservation atomique |
| POST /visit-requests/{id}/presence | Agent + challenge prospect | Challenge contextualisé ou motif différé | Présence verified/unverified |
| POST /applications | Candidat/gestionnaire | Opportunité, pièces | Dossier candidat |
| POST /applications/{id}/decision | Propriétaire/mandataire | Accept/refuse, motif interne, version | Option ou refus |
| POST /leases | Gestionnaire | Candidature, parties, paramètres/version | Bail draft |
| POST /leases/{id}/submit-for-signature ou /cancel ou /terminate | Gestionnaire | Document généré ; motif et pièce pour cancel et terminate ; date d'effet ; expected_version | awaiting_signatures ; cancelled (option libérée, sommes reçues traitées) ; terminated |
| POST /leases/{id}/activate | Gestionnaire | Scan validé, expected_version | Bail, occupation, échéances et mission d'entrée |
| POST /leases/{id}/amendments ou /notices | Gestionnaire | Document et date d'effet | Avenant/préavis versionné |
| POST /inventories/{id}/acknowledgements | Partie ou collecte agent | Hash version, méthode, challenge/signature, réserves | Confirmation contextualisée |
| POST /inventories/{id}/disputes | Partie autorisée | Point, motif, pièces | Contestation liée |
| POST /leases/{id}/end | Gestionnaire | Date réelle, clés, état des lieux ou justification | Fin d'occupation et tâches de solde |
| POST /invoices/drafts et /invoices/{id}/issue | Gestionnaire finance | Sources puis validation de version | Facture numérotée |
| POST /invoices/{id}/credit-notes | Gestionnaire finance | Lignes, motif | Avoir et ajustement de dette |
| POST /receivables/{id}/payment-attempts | Payeur autorisé | Moyen, montant, monnaie | Redirection/challenge externe ou état pending |
| POST /payment-attempts/{id}/unblock | Finance habilitée | Motif, vérification auprès du prestataire | Nouvelle tentative autorisée sur l'objet ; audit |
| POST /receivables/{id}/dunning-pause | Gestionnaire location ou finance | Motif, date de réexamen | Relances suspendues ; recontrôle du solde à la date de réexamen |
| POST /payments/manual et /payments/{id}/approve | Préparateur puis approbateur | Compte réel, justificatif, montant, référence | Paiement confirmé et journal |
| POST /payments/{id}/allocations | Finance | Échéances/montants, version | Affectations atomiques et reçus |
| POST /payments/{id}/refund-requests | Finance | Montant, motif | Demande à approuver |
| POST /refund-requests/{id}/approve ou /reject | Finance distinct du demandeur, authentification forte récente | Décision, motif, expected_version | Remboursement approuvé ou refusé ; exécution et confirmation par la voie manuelle ou le prestataire |
| POST /payments/{id}/reversals | Finance | Preuve du prestataire (rejet, contestation), motif | Contre-écriture liée ; dette réévaluée selon décision explicite |
| POST /webhooks/payments/{provider}/{account_ref} | Prestataire vérifié | Événement signé ou preuve vérifiable | Accusé après persistance inbox |
| POST /deposits/{id}/settlements et /approve | Finance | Retenues, preuves, remboursements | Décompte et dettes validées |
| POST /referrer/claims | Apporteur | Contact/logement, preuve d'intérêt | Revendication en attente de vérification |
| POST /attributions/{id}/arbitrate | Gestionnaire habilité | Décision, pièces, motif | Attribution et effets contrôlés |
| POST /commission-rules/versions | Gestionnaire habilité | Fait, calcul, garantie, conditions | Barème versionné |
| POST /commissions/{id}/disputes ou /resolve | Bénéficiaire/gestionnaire | Motif puis décision | Gel ou résolution |
| POST /beneficiaries/{id}/versions et /approve | Finance distincts | Coordonnées puis vérification | Version autorisée |
| POST /payout-batches et /{id}/approve | Finance distincts | Dettes puis version figée | Réservation puis approbation |
| POST /payout-batches/{id}/export ou /submit | Finance | Version et format / référence dépôt | Fichier ou état submitted |
| POST /payout-lines/{id}/reconcile | Finance habilité | Résultat, référence et preuve | Résultat de ligne, paiement si confirmé |
| POST /statements/imports et /reconciliations | Finance | Fichier/profil puis correspondances | Import dédupliqué et rapprochements |
| POST /owner-statements/generate et /{id}/publish | Finance | Période, propriétaire, monnaie | Relevé figé et publié |
| POST /fx-conversions et /{id}/approve | Finance distincts | Deux montants/comptes, frais, justificatif | Conversion documentée et journal |
| POST /retention/requests et /{id}/approve | Référent habilité | Périmètre, base, motif | Demande contrôlée de purge/gel |

GET privés nécessaires : missions, rapports, anomalies, baux, échéances, cautions, commissions, factures, relevés, journal et lots. Les routes /owner/me, /renter/me et /referrer/me déduisent leurs filtres des liens métier, jamais d'un owner_id ou renter_id arbitraire accepté du client.

### 3.3 Exemple de commande de paiement

~~~json
{
  "receivable_id": "86da2e52-cb53-457d-aa31-9536890495f9",
  "expected_version": 4,
  "method": "mobile_money",
  "provider": "mvola",
  "amount_minor": "250000",
  "currency": "MGA"
}
~~~

Le serveur résout le compte marchand depuis l'objet payable, recontrôle le montant dû et renvoie attempt_id, status, next_action et server_time. Le client ne choisit pas un compte marchand ni une URL de retour arbitraire. Le téléphone du payeur n'est pas conservé dans les journaux de requêtes.

## 4 Protocole hors ligne et synchronisation

### 4.1 Contrat local

IndexedDB contient quatre ensembles séparés : cache de références, brouillons/événements, blobs et file d'envoi. Une écriture de réponse et son événement d'envoi sont atomiques dans une transaction locale. L'interface ne montre « sauvegardé » qu'après succès de cette transaction. Une exception quota ou disque laisse l'action visible comme non sauvegardée.

Une visite est créée avec un UUID local stable. Chaque action possède action_id UUID, local_visit_id, device_id, sequence_number par visite, mission_id, assignment_revision, snapshot_version, type, captured_at_device, clock_anchor_id facultatif et payload_version. Les numéros de séquence servent à détecter des actions absentes ; ils ne prouvent pas une chronologie non falsifiée.

Les états locaux sont draft, closed_pending_sync, uploading, blocked, acknowledged. Les états serveur de réception sont receiving, complete, conflict et quarantined. L'état de contrôle métier est séparé. La suppression des copies locales d'un dossier n'est autorisée qu'après accusé final complete ou réception conservatoire confirmée accompagnée d'une décision de conservation explicite ; un simple ACK d'action ne suffit pas.

Le stockage persistant est demandé, son résultat affiché dans le diagnostic. La disponibilité estimée est vérifiée avant téléchargement de références et avant capture ; aucune garantie de survie à une suppression des données navigateur. Les détails de quotas dépendent du navigateur. [Référence MDN](https://developer.mozilla.org/en-US/docs/Web/API/Storage_API/Storage_quotas_and_eviction_criteria).

### 4.2 Autorisation locale

Une connexion en ligne donne un paquet d'autorisation limité à l'appareil et aux missions téléchargées, valable 48 h. L'agent peut commencer ces missions dans cette période. Une tentative commencée peut être enregistrée et clôturée localement après expiration, mais aucune nouvelle mission ne peut être commencée sans renouvellement. Cette autorisation facilite le travail hors ligne ; elle n'est pas une attestation de l'heure réelle de capture.

Au retour en ligne, une session expirée impose une réauthentification sans effacer les données locales. Une révocation interdit l'usage normal ; une procédure de récupération contrôlée permet à un gestionnaire d'autoriser le dépôt conservatoire de dossiers déjà produits, vers une quarantaine inaccessible aux propriétaires. L'ancienne autorisation locale ne suffit pas à contourner la révocation serveur.

La déconnexion avec des données en attente avertit et verrouille le profil local ; elle ne supprime pas silencieusement les données. Un autre compte sur le même téléphone ne voit pas ces données. L'application évite le stockage de pièces locatives dans le cache agent. Le chiffrement local et le verrouillage réduisent l'exposition, sans présenter une PWA sur un téléphone compromis comme un coffre inviolable.

### 4.3 Échange de synchronisation

1. GET /agent/sync fournit server_time, cursor opaque, missions autorisées, versions des snapshots, références de médias à télécharger et tombstones. La liste initiale inclut 48 h futures et les missions ouvertes affectées ; les actions locales non acquittées ne sont jamais effacées parce qu'une mission n'apparaît plus.
2. Le client envoie les actions par lots de 100 maximum et 1 Mo maximum hors blobs. Le serveur répond pour chaque action : accepted, duplicate, conflict, retryable_error ou rejected avec code. Le lot peut être partiellement appliqué ; le client ne renvoie que ce qui n'est pas acquitté, avec les mêmes identifiants. Les actions de priorité P0 du catalogue de la section 4.3.1 (alerte urgente) partent seules, dans un premier lot, dès qu'un réseau est disponible, avant tout blob ou reprise d'upload ; un upload en cours est suspendu après la partie courante.
3. Un numéro manquant peut être demandé par le serveur. Une action dépendante attend sa dépendance ; le serveur ne finalise pas une visite dont le manifeste nécessite des faits absents.
4. Les blobs sont envoyés séparément avec reprise par parties de 1 Mo. Le serveur valide taille et hash final ; la position atteinte n'est pas déduite uniquement du compteur client. Un upload expiré peut être réinitialisé pour le même media_id sans doublon logique.
5. POST /visits/{id}/finalize fournit le manifeste local et les media_id/hash attendus. Si incomplet : 409 MISSING_MEDIA avec liste autorisée. Aucune notification de rapport n'est créée.
6. Le serveur vérifie les bytes effectivement reçus, construit le manifeste canonique, scelle et commit la réception complète, l'audit et l'outbox. La réponse fournit seal_id, hash, complete_received_at, status et éventuels drapeaux métier.
7. En cas de réponse perdue après commit, le renvoi de la même finalisation retourne le même reçu. Un manifeste différent sous la même clé produit un conflit ; le client doit proposer un amendement distinct.
8. Le client vérifie que tous les fichiers attendus figurent dans le reçu. Il marque acknowledged. Les copies de médias acquittées sont conservées localement sept jours au maximum, sauf action de nettoyage utilisateur plus tôt ; les instructions d'accès expirent indépendamment, au plus tard à 48 h ou au prochain contact constatant une révocation.

~~~json
{
  "device_id": "7f6786d7-52ee-4a42-9b97-cef26ef26afd",
  "actions": [{
    "action_id": "ad0aa3b7-47af-4ab2-ac53-758da63135ed",
    "local_visit_id": "03ba387b-81e8-4f13-92f9-2c4bcdf58cb2",
    "mission_id": "f44a56df-8258-4ca6-bca3-3ae35e416a91",
    "assignment_revision": 3,
    "snapshot_version": 2,
    "sequence_number": 4,
    "type": "checklist_answer_recorded",
    "captured_at_device": "2026-11-22T07:15:00Z",
    "payload_version": 1,
    "payload": {"item_key": "living_room_windows", "answer": "conform", "note": null}
  }]
}
~~~

### 4.3.1 Catalogue des types d'actions

Chaque action de POST /agent/sync/actions porte un type du catalogue ci-dessous et un payload_version. Le schéma JSON de chaque payload est publié dans l'OpenAPI. Un type ou une version inconnus produisent un résultat rejected pour cette action seulement, jamais le rejet du lot entier.

| Type | Payload essentiel | Priorité d'envoi |
| --- | --- | --- |
| urgent_alert_raised | Gravité, description du danger, mission_id ou property_id, position facultative, indicateur « contact d'urgence appelé » | P0, avant tout blob |
| visit_started | Heure appareil, ancre d'horloge, position/précision/instant de mesure | P1 |
| position_recorded | Position, précision en millimètres, instant de mesure, contexte (arrivée, fin, photo) | P1 |
| checklist_answer_recorded | item_key stable, réponse, note, motif si non applicable | P1 |
| photo_captured | media_id, item_key, sha256, taille, MIME, traitement appliqué (recadrage, floutage) ; les octets suivent par upload | P1 pour les métadonnées, P2 pour les octets |
| anomaly_reported | Catégorie, gravité, note, media_id liés | P1 (P0 si la gravité est urgente) |
| visit_impossible_declared | Motif codé, note, media_id facultatifs | P1 |
| prospect_presence_recorded | Challenge SMS résolu ou motif de report | P1 |
| inventory_ack_collected | Version et hash du constat, partie, méthode, preuve (OTP ou signature), réserves | P1 |
| clock_anchor_observed | Ancre de temps serveur, mesure monotone locale | P1 |
| visit_closed_local | Manifeste local, hash, nombre d'actions et de médias attendus | P1 |

Le serveur applique la même priorité à ses files de traitement : une action P0 reçue crée l'anomalie urgente et met les notifications en file avant tout autre traitement du même lot.

### 4.4 Arbitrage des conflits

~~~mermaid
sequenceDiagram
    participant A as App agent
    participant L as Base locale
    participant S as API Veille
    participant O as Stockage prive
    participant G as Gestionnaire
    A->>L: Enregistrer faits et photos
    L-->>A: Sauvegarde locale confirmee
    Note over A,S: Reseau disponible et session autorisee
    A->>S: Actions avec identifiants stables
    S-->>A: Resultat par action
    A->>O: Parties de fichiers avec reprise
    A->>S: Finaliser avec manifeste
    alt Fichier manquant ou hash incorrect
        S-->>A: Liste des elements a reprendre
    else Dossier complet
        S->>S: Verifier, sceller et commit
        S-->>A: Recu complet et identifiant du scelle
        A->>L: Marquer acquitte
        S-->>G: Tache de relecture
    end
~~~

| Situation | Conservation et réponse | Décision métier |
| --- | --- | --- |
| Réaffectation pendant absence réseau | Accepter dépôt conservatoire si auteur antérieurement autorisé ; état conflict | Gestionnaire retient le constat pertinent ou crée une mission liée ; pas de second achèvement automatique |
| Checklist publiée après téléchargement | Utiliser snapshot figé de la mission | Les nouvelles consignes ne rendent pas rétroactivement les réponses invalides |
| Mission annulée avant réception | Conserver dossier et heure déclarée, conflit visible | Gestionnaire valide le déplacement ou le classe sans facturation ; historique inchangé |
| Deux appareils pour une mission | Deux tentatives distinctes | Une seule sélection atomique ; l'autre reste consultable comme non retenue |
| Upload partiel puis redémarrage | Reprendre parties manquantes | Aucun scellé avant complétude |
| GPS absent/hors rayon | Collecter avec drapeau | Acceptation motivée ou impossibilité ; pas de position fabriquée |
| Session expirée | Mettre file en pause ; conserver local | Réauthentification avant transmission |
| Appareil révoqué | Pas d'upload normal | Procédure de récupération en quarantaine, approbation gestionnaire |
| Incident urgent reçu en retard | Alerter avec mention réception différée | Ne jamais prétendre une alerte envoyée à l'heure de capture |

### 4.5 Reprise et mises à jour

Les retries réseau emploient une attente exponentielle avec aléa : environ 2, 5, 15, 30 et 60 secondes, puis maximum cinq minutes. Le bouton synchroniser relance immédiatement si la connexion est valide. Une erreur 4xx métier bloque l'élément sans boucle de retry ; un 401 demande connexion. Après cinq erreurs techniques consécutives, afficher l'élément et l'action de support.

Le service worker utilise une version de cache et un schéma IndexedDB migrable. Une mise à jour incompatible ne s'active pas pendant une visite ouverte ou avant migration sûre des dossiers en attente. Un test de migration avec file non vide est obligatoire. Background Sync est une optimisation ; la recette doit réussir avec synchronisation explicite au premier plan.

## 5 Médias scellés et versions documentaires

### 5.1 Chaîne de traitement

Capture caméra → orientation normalisée → recadrage/floutage choisi → compression contrôlée → confirmation agent → fichier final de constat → hash SHA-256 → stockage local → upload → vérification serveur des bytes → manifeste → scellé. Après hash final, toute modification impose un nouveau media_id et un nouveau hash.

Les originaux bruts non retenus ne sont pas requis et ne sont pas exportés comme s'ils avaient été conservés. Les métadonnées utiles sont enregistrées explicitement ; les EXIF ne sont pas une source de vérité. Le serveur crée des vignettes distinctes, jamais à la place des fichiers scellés. Les documents déposés sont contrôlés par type réel, taille et analyse de contenu malveillant avant d'être rendus accessibles.

Chaque photo porte heure appareil, position déclarée si disponible, précision, instant de cette position et indicateur de fraîcheur. Une position vieille de plus de 60 secondes est marquée stale ; une position d'arrivée réutilisée n'est pas présentée comme une nouvelle mesure. Les doublons SHA-256 sont recherchés uniquement dans l'organisation et ne constituent qu'un signal de revue.

### 5.2 Manifeste et attestation Veille

Un manifeste contient schema_version, organization_id, mission_id, visit_id, agent_id, device_id, assignment_revision, checklist snapshot/hash, faits et réponses finaux, horodatages déclarés, positions/précisions, anomalies initiales et liste ordonnée de médias avec identifiant, MIME, taille et hash. Les coordonnées sont des entiers en 10^-7 degré et les précisions en millimètres ; les dates sont des chaînes UTC normalisées. Le schéma exclut tout nombre non fini.

Le JSON est canonisé selon JCS avant calcul SHA-256 ; l'ordre des tableaux est spécifié par identifiant stable pour médias/points et séquence pour événements. Une implémentation JCS testée est utilisée, pas un simple JSON.stringify supposé portable. [RFC 8785](https://www.rfc-editor.org/info/rfc8785/).

Le serveur produit une attestation signée Ed25519 contenant manifest_hash, seal_id, server_received_at, schema_version et signing_key_id. La clé privée est séparée du stockage de données, gérée par un service de secrets ; les anciennes clés publiques restent disponibles pour vérifier les anciennes attestations. La rotation est testée. Cela authentifie une attestation émise par Veille ; cela n'empêche pas à elle seule un opérateur Veille malveillant de créer une nouvelle attestation.

Les droits applicatifs interdisent UPDATE/DELETE sur constats et scellés validés. Le stockage objet est versionné, avec protection contre l'écrasement et droits séparés de purge. Une politique de verrouillage objet éventuelle doit rester compatible avec les durées approuvées ; ne pas configurer une rétention irréversible non validée.

### 5.3 Horodatage

Trois données sont distinctes : heure appareil déclarée, heure de réception serveur, ancre de temps serveur obtenue lors d'un échange précédent. Une différence réception/capture n'est pas une mesure fiable du délai si l'horloge appareil est inconnue. L'interface affiche les deux dates et « envoyé en différé » quand connu ; elle qualifie tout délai calculé comme estimé.

Un changement d'horloge détecté pendant une session ou un ordre temporel impossible déclenche clock_suspect. La mesure monotone peut aider au sein d'une même session mais ne garantit pas la date absolue après redémarrage. L'envoi différé de deux heures ne suffit jamais, seul, à déclarer une heure fausse.

L'horodatage tiers RFC 3161 n'est pas livré. Un tel service atteste une existence de données avant une date, pas la véracité de l'observation ; aucune promesse juridique ne doit être déduite de son ajout futur. [RFC 3161](https://www.rfc-editor.org/info/rfc3161/).

### 5.4 Publication et vérification

Un rapport référence des seal_id précis et fige le texte de synthèse, les décisions et le template de rendu. Après génération, les bytes du PDF sont stockés une seule fois ; des dates aléatoires de génération ne doivent pas modifier un PDF lors d'un simple téléchargement.

Le reçu de publication signé porte les hashes PDF et manifestes. La page publique permet comparaison de hash sans transfert obligatoire du PDF. Codes aléatoires d'au moins 128 bits, pas de recherche par adresse ou téléphone, limitation de débit et absence d'indexation. Réponse : published, superseded, withdrawn, expired ou unavailable ; « correspondance de fichier » reste distinct de ces statuts.

Un retrait pour confidentialité bloque les accès ordinaires à la version concernée et affiche withdrawn. Une version expurgée est un nouveau document lié ; le hash original n'est pas réutilisé. La suppression contrôlée ne modifie pas rétrospectivement les bytes d'un export déjà remis à un destinataire.

## 6 Argent et rapprochement

### 6.1 Modèle de journal opérationnel

Le journal interne est en partie double par monnaie, même si l'application n'est pas un outil de comptabilité fiscale. Les comptes minimaux distinguent : trésorerie bancaire/mobile disponible, créance sur prestataire de paiement non réglée, suspens, dette envers propriétaire par propriétaire, dette de caution par bail, dette fournisseur, dette commission, revenu de prestation/gestion et frais de paiement. Les comptes externes propriétaire sont des comptes de suivi séparés, exclus de la disponibilité de la conciergerie.

Chaque transaction comptable validée contient au moins deux lignes, une justification et un événement source unique. Somme débits = somme crédits dans chaque monnaie. Une correction inverse tout ou partie par nouvelle transaction liée. Une pièce avec paiement ne peut être comptée simultanément via deux chemins (facture de travaux et retenue du relevé) : un même événement source possède un seul posting_set.

Exemple de référence en MGA, hors taxes et frais opérateur, destiné à tester le journal opérationnel et à faire valider le plan de comptes :

| Événement | Débit | Crédit |
| --- | --- | --- |
| Encaissement loyer 1 000 000 pour propriétaire A | Trésorerie disponible 1 000 000 | Dette propriétaire A 1 000 000 |
| Dépôt de garantie 500 000 reçu | Trésorerie disponible 500 000 | Dette de caution du bail 500 000 |
| Honoraires de gestion acquis 100 000 | Dette propriétaire A 100 000 | Revenu gestion 100 000 |
| Travaux validés à charge propriétaire 200 000 | Dette propriétaire A 200 000 | Dette fournisseur 200 000 |
| Paiement fournisseur confirmé 200 000 | Dette fournisseur 200 000 | Trésorerie disponible 200 000 |
| Reversement propriétaire confirmé 700 000 | Dette propriétaire A 700 000 | Trésorerie disponible 700 000 |

Après ces mouvements, la dette propriétaire A est nulle, la caution reste due pour 500 000 et la trésorerie vaut 600 000, dont 500 000 non distribuables. Un solde bancaire positif ne permet donc pas de payer arbitrairement 600 000 à un autre propriétaire.

Si l'encaissement arrive d'abord chez un prestataire, le premier débit est « créance prestataire ». Le versement net débite trésorerie et frais, et crédite cette créance. Les frais sont imputés au payeur contractuel prévu, sans réduire silencieusement le montant réputé payé par le client.

### 6.2 Affectations et remboursements

Verrouiller paiement et receivables dans un ordre stable pour affecter. Disponible à affecter = montant confirmé - remboursements confirmés - affectations nettes - montants gelés/réservés pertinents. Le remboursement d'une somme déjà affectée exige contre-affectation et décision sur la dette : maintien ou avoir/ajustement. Un solde négatif involontaire est refusé.

La réception de fonds ne prouve pas leur destination. Une référence non reconnue va au suspens. Un payeur portant plusieurs baux impose une sélection métier si la référence ne permet pas de trancher. La règle « plus ancien d'abord » s'applique uniquement dans le bail identifié et la monnaie correspondante.

Pour les espèces, la caisse est un compte réel de l'organisation, distinct du compte mobile. Un dépôt ultérieur en banque est un transfert de trésorerie, pas un second paiement locataire.

### 6.3 Adaptateurs de prestataires

Interface commune : initiatePayment, getPaymentStatus, verifyWebhook, normalizeTransaction, listOrImportSettlements et getCapabilities. Le remboursement automatisé n'est exposé que si un prestataire testé le supporte ; le chemin manuel approuvé reste disponible. Les capacités de décaissement API sont désactivées en V1.

Chaque adaptateur documente compte marchand, format des montants, clés idempotentes acceptées, signature/challenge de webhook, délais et limites de polling, statuts terminaux, confirmations tardives, frais, versement, remboursement, dédoublonnage et format du relevé. Les tests de contrat doivent utiliser des fixtures officielles ou vérifiées auprès du prestataire, puis un test réel de faible montant autorisé par l'organisation.

La route webhook vérifie le corps brut et les preuves disponibles, résout le compte marchand, enregistre dans une inbox durable dédupliquée puis accuse réception. Le worker traite ensuite sous verrou. Si un fournisseur n'offre pas de signature, la notification ne confirme rien à elle seule : une interrogation authentifiée serveur à serveur doit corroborer la transaction. Une liste IP ne constitue pas l'unique preuve.

### 6.4 Tentatives et confirmations tardives

Pour un prestataire autorisant cette fréquence, polling initial à 30 s jusqu'à 10 min ; ensuite toutes les 5 min jusqu'à 1 h, puis toutes les heures jusqu'à 72 h. Respecter les limites spécifiques de l'adaptateur. À 72 h, le dossier passe en exception manuelle avec état unknown conservé ; les callbacks restent recevables. Aucun retry de débit automatique sur timeout.

Une confirmation succeeded déjà appliquée ignore un doublon exact. Un événement failed reçu après succeeded ne défait pas le paiement : une opération explicite de reversal/chargeback est nécessaire. En cas d'états contradictoires, interroger la source et créer une tâche. Un événement tardif après failed_confirmed est conservé et vérifié ; si un mouvement réel existe, l'enregistrer, même s'il exige une régularisation.

Unicité financière durable sur organization_id + provider_account_id + external_transaction_id. Les clés idempotentes API générales sont conservées au moins 30 jours ; les identifiants d'événements, paiements, séries documentaires et écritures restent protégés pendant toute la conservation métier afin qu'un rejeu ancien ne recrée pas d'argent.

### 6.5 Lots et solvabilité

La création de lot verrouille les dettes et comptes de disponibilité. La réservation est persistante, liée à chaque ligne. Deux lots concurrents ne peuvent réserver la même dette ni dépasser les fonds disponibles pour le bénéficiaire concerné. L'approbateur doit différer de created_by et avoir une authentification forte de moins de cinq minutes.

Un changement de coordonnées bénéficiaire après approbation ne modifie pas le lot : il faut annuler avant soumission, recréer avec la nouvelle version et refaire approuver. Une ligne au résultat unknown reste réservée jusqu'à rapprochement. Un paiement sortant réel dupliqué est enregistré comme tel, signalé et régularisé ; il n'est pas masqué par une contrainte applicative.

### 6.6 Conversion et clôture

La conversion externe relie deux jeux équilibrés via comptes de passage par monnaie. Le taux enregistré est informatif pour reconstruire l'opération ; les montants source et destination effectivement constatés sont autoritatifs. Les frais ont leurs lignes propres. Toute différence entre montant prévu et reçu est explicitée avant approbation.

La clôture définit organisation + période + journal. Une écriture rétroactive dans une période clôturée est refusée ; une régularisation utilise une date comptable ouverte et garde original_value_date. La restitution du relevé se reconstruit depuis un cutoff transactionnel figé, pour éviter un PDF dont les lignes changent durant sa génération.

## 7 Authentification sécurité et données

### 7.1 Sessions et jetons

Les liens magiques utilisent au moins 128 bits aléatoires, sont stockés hachés, à usage unique, expirent après 15 min et sont échangés par action explicite POST pour éviter la consommation par un robot d'aperçu d'e-mail. Les OTP expirent après 5 min, maximum cinq essais par challenge ; un renvoi invalide l'ancien challenge. Les messages d'erreur ne révèlent pas si le compte existe.

Sessions propriétaire/locataire : durée absolue sept jours, inactivité 24 h ; accès à une décision sensible (coordonnées, approbation financière) exige authentification récente. Sessions gestionnaire/admin : durée absolue 12 h, inactivité 30 min, second facteur obligatoire. Agent : session serveur renouvelable sept jours sur appareil enregistré, mais autorisation de nouvelle activité hors ligne limitée à 48 h. Apporteur : session sept jours, inactivité 24 h. Les durées sont des paramètres de sécurité versionnés, pas des liens publics permanents.

PIN et mots de passe sont hachés avec une fonction adaptée telle qu'Argon2id, paramètres mesurés sur l'infrastructure. Le PIN n'est pas le seul facteur d'inscription d'un nouvel appareil. Après cinq échecs d'une combinaison compte/appareil, temporisation de 15 min ; limitation complémentaire par IP et challenge, sans blocage global permettant un déni de service trivial. Récupération de compte avec téléphone changé nécessite vérification organisation et journal.

Cookies HttpOnly/Secure/SameSite approprié, CSRF sur commandes de session, CSP restrictive, absence de jetons de session dans les URL ordinaires, protection des uploads et contrôle des noms de fichiers. Les logs ne contiennent pas les corps d'authentification, jetons, signatures manuscrites ni données de carte. Les saisies carte se font sur la page du prestataire ; Veille ne reçoit pas le numéro ni le cryptogramme.

### 7.2 Droits et données locales

Les contrôles de rôle sont complétés par la relation métier : propriétaire historique/courant, partie au bail, agent affecté, bénéficiaire de commission. Les changements de propriété n'ouvrent pas les factures d'un ancien propriétaire au nouveau. Le bail et ses quittances restent accessibles au locataire selon la politique contractuelle après départ, sans lui ouvrir les incidents futurs du logement.

La synchronisation ne télécharge que les données nécessaires : consignes, coordonnées utiles, checklist et références de la mission. Pas de loyers, pièces d'identité ou barèmes de commissions sur l'app agent. Les codes d'accès sont servis par endpoint protégé et ne sont jamais copiés dans les snapshots de preuve.

### 7.3 Conservation et suppression

Une politique versionnée associe catégorie de données, base/justification validée, événement de départ, durée, rôle approbateur, exclusions et sort des sauvegardes. Tant qu'une politique réelle n'est pas validée, le lancement avec données personnelles est bloqué (DEC-04).

Le processus technique de purge exécute : inventaire ciblé → contrôle des gels → validation → révocation des accès → suppression objets et dérivés → réduction/suppression des enregistrements concernés → reçu de purge minimal. Ce reçu ne recopie pas les données effacées. La preuve cryptographique et les manifests contenant des identifiants sont inclus dans l'analyse de conservation, pas considérés anonymes par défaut.

Les migrations et restaurations ne ressuscitent pas une donnée purgée : un registre restreint des opérations à rejouer est conservé selon la politique validée. Les moyens de chiffrement et la gestion des clés doivent permettre la restauration des données encore autorisées ; détruire une clé partagée n'est pas une purge ciblée acceptable.

### 7.4 Menaces minimales à couvrir

Tests requis contre accès direct à un UUID d'une autre organisation, URL média réutilisée après expiration, accès à un PDF remplacé/retiré, webhook rejoué, callback forgé, double clic de paiement, insertion d'un rôle privilégié dans un payload, export sans habilitation, injection HTML dans les notes/PDF, fichier déguisé, OTP deviné, upload sans droit ou sur un objet parent non autorisé et modification de bénéficiaire pendant approbation. L'absence de sélecteur de galerie est vérifiée dans l'interface ; elle ne prouve pas au serveur l'origine caméra d'un fichier envoyé par un client compromis. La fraude par téléphone compromis ou photo d'écran est signalée comme limite, pas prétendument résolue. Chacune de ces menaces correspond à un scénario R101 à R110 du plan de recette.

## 8 Tâches planifiées et files d’exception

| Tâche | Fréquence initiale | Clé d'unicité / préconditions | Résultat en échec |
| --- | --- | --- | --- |
| Générer occurrences | Quotidienne à 00 h 10 locale | recurrence_id + occurrence ; horizon 30 j | Tâche opérations, aucune occurrence dupliquée |
| Compléter échéancier | Quotidienne à 00 h 20 locale | bail + période/version ; bail actif | Tâche finance ; pas d'appel incomplet |
| Expirer réservations de créneau | Chaque minute | État encore provisoire et échéance atteinte | Reprise idempotente ; ne libère pas un créneau confirmé |
| Expirer options candidature | Chaque heure | Option non convertie/prolongée | Notification gestionnaire et disponibilité recalculée |
| Détecter démarrage non reçu | Toutes les 5 min | Mission ouverte, seuil franchi, alerte non créée | Indicateur technique de retard du job |
| Escalader urgences | Chaque minute | Urgence reçue, pas d'accusé depuis 10 min | Contact secours et exception si SMS échoue |
| Relancer relecture | Chaque heure | Dossier complet > 24 h, tâche non résolue | File opérations |
| Émettre appels et relances | Chaque heure dans fenêtre locale | Solde, pause, échéance et clé de relance revérifiés | Exception communication, aucune nouvelle dette |
| Rappeler visites prospect | Chaque minute | Créneau confirmé, version courante et heure due | Marquer obsolète si rendez-vous modifié |
| Rapprocher paiements inconnus | Selon adaptateur | Tentative non résolue, compte valide | Backoff puis tâche manuelle à 72 h |
| Rendre commissions payables | Chaque heure | Garantie échue et conditions encore vraies | Pas de paiement automatique |
| Générer synthèses mensuelles | Premier jour à 06 h locale | Propriétaire + mois + version de sources | Relance asynchrone, pas de doublon publié |
| Générer relevés propriétaire | À la demande après rapprochement ; rappel mensuel | Cutoff stable, période et monnaie | Brouillon bloqué si écarts |
| Contrôler séquences et équilibre | Quotidien | Lecture des journaux et documents émis | Alerte finance/technique, gel ciblé si corruption |
| Nettoyer uploads abandonnés | Quotidien | > 7 j sans finalisation et non référencé par dossier en réception active | Marquer nettoyage ; retry upload possible depuis copie locale |
| Purger selon politique | Quotidien | Expiration, absence de gel et autorisation requise | Rapport de purge partielle, aucune suppression silencieuse |

Les tâches planifiées ne doivent pas dépendre d'une requête utilisateur pour fonctionner. Les files urgences et paiements ont une priorité distincte des PDF et exports. Les dead letters ont un responsable et une action de reprise idempotente depuis le back-office habilité.

## 9 Exploitation sauvegarde et mise en production

### 9.1 Mesures et alertes

Mesurer latence API par route, erreurs 5xx, âge des messages en file, tentatives inconnues, écarts de rapprochement, dossiers en réception sans progrès, générations PDF échouées, notifications rejetées et couverture des sauvegardes. Les dimensions organization_id sont accessibles à l'exploitation, sans contenu personnel dans les métriques.

Alertes initiales : file urgence/paiement âgée > 60 s pendant 5 min ; erreur 5xx > 2 % sur 5 min avec au moins 50 requêtes ; absence de sauvegarde conforme au RPO ; incohérence d'équilibre immédiate ; échec répété de prestataire créant des paiements unknown. Des seuils différents peuvent être validés après mesure, avec historique de configuration.

### 9.2 Sauvegarde et reprise

PostgreSQL : sauvegarde complète quotidienne et archivage continu des journaux permettant une restauration à un point dans le temps ; stockage objet versionné et sauvegarde indépendante dans la fenêtre RPO. Objectifs de référence : perte maximale d'une heure de données serveur acquittées, reprise en quatre heures. Le choix concret d'hébergement doit prouver qu'il respecte ces objectifs.

L'exercice de restauration vérifie cohérence base/objets, manifests et hashes, clés de déchiffrement/signature, journaux équilibrés, outbox et inbox. Au redémarrage, suspendre les sorties d'argent, rejouer idempotemment les événements et rapprocher les comptes externes avant reprise. Un rollback applicatif ne déclenche jamais la réémission d'un débit ou d'un fichier de paiement.

### 9.3 Procédures d’incident

| Incident | Première action | Condition de reprise |
| --- | --- | --- |
| Prestataire entrant indisponible | Suspendre nouvelles initiations du prestataire, conserver callbacks et polling autorisés | Santé vérifiée et tentatives unknown rapprochées |
| Résultats d'un lot inconnus | Geler lignes et réservations ; consulter compte réel | Confirmation par ligne, aucune relance globale |
| Dossier local bloqué | Conserver données ; diagnostic appareil puis réauthentification/reprise | Accusé complet vérifié ; aucune purge avant |
| Fuite d'un lien ou d'un compte | Révoquer jeton/session, limiter objet, préserver audit | Analyse et restauration d'accès suivant procédure données |
| PDF erroné publié | Retirer si exposition privée ; sinon publier correction liée | Destinataires notifiés et ancien statut visible |
| Corruption/incohérence journal | Geler commandes financières concernées et préserver preuves | Rapprochement, correction contrôlée et validation finance |
| Restauration générale | Mode maintenance, sorties gelées, contrôle des purges et outbox | Recette de reprise et validation exploitation/finance |

### 9.4 Déploiement unique

Développement, recette et production sont isolés ; aucun compte marchand réel n'est utilisable depuis un environnement de développement. Les fixtures prestataires, téléphones et e-mails de recette sont explicitement identifiés. Les migrations suivent une stratégie compatible avec les versions clientes encore en file hors ligne ; une API de synchronisation versionnée ancienne reste acceptée pendant la fenêtre de migration convenue.

Avant production : migrations répétées sur copie autorisée/anonymisée ou données synthétiques, sauvegarde vérifiée, restauration exercée, paquet de déploiement versionné, documentation d'exploitation, comptes opérateurs approuvés et campagne de recette close. Un essai de terrain préalable en environnement de recette ne constitue pas une livraison partielle.

## 10 Contrats à produire dans le dépôt de développement

Ces éléments sont les premiers livrables techniques à dériver de la spécification, pas des décisions métier laissées libres :

1. OpenAPI complète des routes listées, schémas JSON validés et catalogue de codes d'erreur.
2. Migrations avec clés étrangères composites, contraintes d'exclusion, unicités et droits sur tables immuables.
3. Diagrammes d'état exécutables ou tests de transition pour chaque agrégat ; pas de changement d'état par contrôleur non protégé.
4. Schéma versionné du manifeste, fixtures de canonisation/signature et vérification interopérable.
5. Catalogue d'événements et outbox/inbox, clés d'idempotence, commandes de rejeu et files d'exception.
6. Adaptateurs testés pour les quatre moyens intégrés : carte EUR et trois opérateurs mobiles MGA, directement ou via agrégateur couvrant effectivement leurs capacités.
7. Fixtures du journal, affectations, garanties et conversions ; propriétés d'équilibre et absence de double effet vérifiées.
8. Procédures d'installation PWA, de support agent, de rapprochement quotidien, de clôture et de restauration.
9. Catalogue des types d'actions de synchronisation (section 4.3.1) avec schémas versionnés et jeux d'essais de lots mixtes contenant une alerte urgente.
10. Liste des exceptions à l'idempotence (section 3.1) reportée dans l'OpenAPI.

La livraison est complète seulement lorsque ces contrats sont implémentés et la [recette](Veille-plan-developpement-recette.md) exécutée avec des résultats conservés.
