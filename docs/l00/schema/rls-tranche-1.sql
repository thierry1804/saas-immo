-- Veille L00 — RLS tranche 1
-- Prérequis : tranche-1.sql appliqué
-- Contexte applicatif : SET LOCAL app.organization_id = '<uuid>';

-- Rôle applicatif recommandé (non propriétaire des tables) :
-- CREATE ROLE veille_app NOINHERIT LOGIN;
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO veille_app;
-- Les migrations s'exécutent avec un rôle distinct propriétaire.

CREATE OR REPLACE FUNCTION app_current_organization_id()
RETURNS uuid
LANGUAGE sql
STABLE
AS $$
    SELECT NULLIF(current_setting('app.organization_id', true), '')::uuid;
$$;

-- Tables sans organization_id : account (identité globale) — pas de RLS org ;
-- l'accès métier passe toujours par membership.

ALTER TABLE organization ENABLE ROW LEVEL SECURITY;
ALTER TABLE membership ENABLE ROW LEVEL SECURITY;
ALTER TABLE device ENABLE ROW LEVEL SECURITY;
ALTER TABLE refresh_token ENABLE ROW LEVEL SECURITY;
ALTER TABLE owner ENABLE ROW LEVEL SECURITY;
ALTER TABLE property ENABLE ROW LEVEL SECURITY;
ALTER TABLE property_ownership ENABLE ROW LEVEL SECURITY;
ALTER TABLE access_instruction ENABLE ROW LEVEL SECURITY;
ALTER TABLE mandate ENABLE ROW LEVEL SECURITY;
ALTER TABLE checklist_template ENABLE ROW LEVEL SECURITY;
ALTER TABLE checklist_version ENABLE ROW LEVEL SECURITY;
ALTER TABLE checklist_item ENABLE ROW LEVEL SECURITY;
ALTER TABLE tariff_version ENABLE ROW LEVEL SECURITY;
ALTER TABLE recurrence ENABLE ROW LEVEL SECURITY;
ALTER TABLE mission ENABLE ROW LEVEL SECURITY;
ALTER TABLE assignment_history ENABLE ROW LEVEL SECURITY;
ALTER TABLE visit_attempt ENABLE ROW LEVEL SECURITY;
ALTER TABLE visit_fact ENABLE ROW LEVEL SECURITY;
ALTER TABLE checklist_answer ENABLE ROW LEVEL SECURITY;
ALTER TABLE media_asset ENABLE ROW LEVEL SECURITY;
ALTER TABLE evidence_seal ENABLE ROW LEVEL SECURITY;
ALTER TABLE review_decision ENABLE ROW LEVEL SECURITY;
ALTER TABLE report ENABLE ROW LEVEL SECURITY;
ALTER TABLE report_version ENABLE ROW LEVEL SECURITY;
ALTER TABLE publication_receipt ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_event ENABLE ROW LEVEL SECURITY;
ALTER TABLE outbox_event ENABLE ROW LEVEL SECURITY;
ALTER TABLE consumer_receipt ENABLE ROW LEVEL SECURITY;
ALTER TABLE idempotency_record ENABLE ROW LEVEL SECURITY;

-- Politique générique : isolation stricte par organization_id
DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'membership', 'device', 'refresh_token', 'owner', 'property',
        'property_ownership', 'access_instruction', 'mandate',
        'checklist_template', 'checklist_version', 'checklist_item', 'tariff_version',
        'recurrence', 'mission', 'assignment_history', 'visit_attempt', 'visit_fact',
        'checklist_answer', 'media_asset', 'evidence_seal', 'review_decision',
        'report', 'report_version', 'publication_receipt',
        'audit_event', 'outbox_event', 'consumer_receipt', 'idempotency_record'
    ]
    LOOP
        EXECUTE format(
            'CREATE POLICY org_isolation ON %I
             FOR ALL
             USING (organization_id = app_current_organization_id())
             WITH CHECK (organization_id = app_current_organization_id())',
            t
        );
    END LOOP;
END $$;

-- organization : l'id courant uniquement
CREATE POLICY org_self ON organization
    FOR ALL
    USING (id = app_current_organization_id())
    WITH CHECK (id = app_current_organization_id());

-- Force RLS même pour le propriétaire de table en session app (selon config)
-- ALTER TABLE ... FORCE ROW LEVEL SECURITY; à activer pour le rôle app.

COMMENT ON FUNCTION app_current_organization_id() IS
    'Lit app.organization_id (SET LOCAL par requête/transaction). NULL hors contexte = aucun accès RLS.';
