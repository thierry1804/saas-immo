-- Veille V1 — extension du schéma tranche 1.
-- N'altère pas le sens des tables L00 : ajouts, triggers d'immuabilité, RLS.
-- Montants en bigint. EUR = centimes, MGA = ariary. UUID. organization_id partout
-- sauf auth_throttle (empreintes, pas de donnée métier).

ALTER TABLE visit_fact ADD COLUMN IF NOT EXISTS payload_hash text;
ALTER TABLE account ADD COLUMN IF NOT EXISTS failed_attempts integer NOT NULL DEFAULT 0;
ALTER TABLE account ADD COLUMN IF NOT EXISTS locked_until timestamptz;

CREATE TABLE IF NOT EXISTS auth_challenge (
    id              uuid PRIMARY KEY,
    organization_id uuid REFERENCES organization (id),
    account_id      uuid REFERENCES account (id),
    purpose         text NOT NULL CHECK (purpose IN ('mfa', 'otp', 'magic')),
    destination_hash text,
    code_hash       text,
    token_hash      text,
    attempts        integer NOT NULL DEFAULT 0,
    expires_at      timestamptz NOT NULL,
    consumed_at     timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS auth_challenge_token_uidx
    ON auth_challenge (token_hash) WHERE token_hash IS NOT NULL;
CREATE INDEX IF NOT EXISTS auth_challenge_destination_idx
    ON auth_challenge (destination_hash, created_at);

CREATE TABLE IF NOT EXISTS auth_throttle (
    key_hash        text PRIMARY KEY,
    hits            integer NOT NULL,
    window_started  timestamptz NOT NULL,
    blocked_until   timestamptz
);

CREATE TABLE IF NOT EXISTS sync_action_receipt (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    action_id       uuid NOT NULL,
    payload_hash    text NOT NULL,
    result_status   text NOT NULL,
    visit_id        uuid,
    server_event_id uuid NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, action_id)
);

CREATE TABLE IF NOT EXISTS anomaly (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    source_type     text NOT NULL,
    source_id       uuid,
    category        text NOT NULL,
    severity        text NOT NULL,
    status          text NOT NULL,
    note            text NOT NULL,
    assignee_id     uuid,
    target_date     date,
    author_id       uuid NOT NULL REFERENCES account (id),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

CREATE TABLE IF NOT EXISTS quote (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    anomaly_id      uuid NOT NULL,
    version_number  integer NOT NULL,
    supplier_name   text NOT NULL,
    description     text NOT NULL,
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    fees_minor      bigint NOT NULL DEFAULT 0 CHECK (fees_minor >= 0),
    valid_until     date,
    status          text NOT NULL,
    decision        text,
    decided_by      uuid,
    decided_at      timestamptz,
    decision_reason text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    UNIQUE (organization_id, anomaly_id, version_number),
    FOREIGN KEY (organization_id, anomaly_id) REFERENCES anomaly (organization_id, id)
);

CREATE TABLE IF NOT EXISTS work_order (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    quote_id        uuid NOT NULL,
    supplier_name   text NOT NULL,
    status          text NOT NULL,
    invoice_id      uuid,
    control_visit_id uuid,
    executed_at     timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, quote_id) REFERENCES quote (organization_id, id)
);

CREATE TABLE IF NOT EXISTS listing (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    content         jsonb NOT NULL,
    status          text NOT NULL,
    slug            text NOT NULL UNIQUE,
    owner_agreement boolean NOT NULL DEFAULT false,
    published_at    timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

CREATE TABLE IF NOT EXISTS viewing_request (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    listing_id      uuid NOT NULL,
    contact_label   text NOT NULL,
    contact_phone   text NOT NULL,
    slot_start      timestamptz NOT NULL,
    slot_end        timestamptz NOT NULL,
    status          text NOT NULL,
    hold_expires_at timestamptz,
    policy_version  integer NOT NULL DEFAULT 1,
    source          text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    CHECK (slot_end > slot_start),
    FOREIGN KEY (organization_id, listing_id) REFERENCES listing (organization_id, id)
);

-- Candidature locative (rental application).
CREATE TABLE IF NOT EXISTS candidature (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    listing_id      uuid,
    viewing_request_id uuid,
    applicant_label text NOT NULL,
    status          text NOT NULL,
    summary         text,
    decision        text,
    decided_by      uuid,
    decided_at      timestamptz,
    decision_reason text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id)
);

CREATE TABLE IF NOT EXISTS lease (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    label           text NOT NULL,
    status          text NOT NULL,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    rent_minor      bigint NOT NULL CHECK (rent_minor >= 0),
    charges_minor   bigint NOT NULL CHECK (charges_minor >= 0),
    deposit_minor   bigint NOT NULL CHECK (deposit_minor >= 0),
    management_fee_bps integer NOT NULL DEFAULT 0 CHECK (management_fee_bps >= 0 AND management_fee_bps <= 10000),
    start_date      date NOT NULL,
    end_date        date,
    due_day         integer,
    renter_account_id uuid REFERENCES account (id),
    owner_snapshot  jsonb NOT NULL DEFAULT '{}',
    parameters      jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

CREATE TABLE IF NOT EXISTS rent_schedule (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    lease_id        uuid NOT NULL,
    period_start    date NOT NULL,
    period_end      date NOT NULL,
    rent_minor      bigint NOT NULL CHECK (rent_minor >= 0),
    charges_minor   bigint NOT NULL CHECK (charges_minor >= 0),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    status          text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id),
    UNIQUE (organization_id, lease_id, period_start),
    CHECK (period_end > period_start),
    FOREIGN KEY (organization_id, lease_id) REFERENCES lease (organization_id, id)
);

CREATE TABLE IF NOT EXISTS invoice (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    number          text,
    client_owner_id uuid,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    status          text NOT NULL CHECK (status IN ('draft', 'issued')),
    source_type     text NOT NULL,
    source_id       uuid,
    total_minor     bigint NOT NULL CHECK (total_minor >= 0),
    lines           jsonb NOT NULL DEFAULT '[]',
    issued_at       timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, client_owner_id) REFERENCES owner (organization_id, id)
);

CREATE UNIQUE INDEX IF NOT EXISTS invoice_number_uidx
    ON invoice (organization_id, number) WHERE number IS NOT NULL;

CREATE TABLE IF NOT EXISTS credit_note (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    invoice_id      uuid NOT NULL,
    number          text NOT NULL,
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    reason          text NOT NULL,
    author_id       uuid NOT NULL REFERENCES account (id),
    lines           jsonb NOT NULL DEFAULT '[]',
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id),
    UNIQUE (organization_id, number),
    FOREIGN KEY (organization_id, invoice_id) REFERENCES invoice (organization_id, id)
);

CREATE TABLE IF NOT EXISTS payment_attempt (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    invoice_id      uuid NOT NULL,
    provider        text NOT NULL,
    method          text NOT NULL,
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    status          text NOT NULL CHECK (status IN ('pending', 'confirmed', 'failed')),
    idempotency_key text,
    external_ref    text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, invoice_id) REFERENCES invoice (organization_id, id)
);

CREATE UNIQUE INDEX IF NOT EXISTS payment_attempt_idem_uidx
    ON payment_attempt (organization_id, idempotency_key) WHERE idempotency_key IS NOT NULL;

CREATE TABLE IF NOT EXISTS payment (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    attempt_id      uuid NOT NULL,
    invoice_id      uuid NOT NULL,
    provider        text NOT NULL,
    external_transaction_id text NOT NULL,
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    confirmed_at    timestamptz NOT NULL,
    confirmation_source text NOT NULL CHECK (confirmation_source = 'simulated_adapter'),
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id),
    UNIQUE (organization_id, attempt_id),
    UNIQUE (organization_id, provider, external_transaction_id),
    FOREIGN KEY (organization_id, attempt_id) REFERENCES payment_attempt (organization_id, id),
    FOREIGN KEY (organization_id, invoice_id) REFERENCES invoice (organization_id, id)
);

CREATE TABLE IF NOT EXISTS ledger_entry (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    source_type     text,
    source_id       uuid,
    justification   text NOT NULL,
    correction_of   uuid,
    author_id       uuid,
    posted_at       timestamptz NOT NULL,
    lines           jsonb NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id)
);

CREATE TABLE IF NOT EXISTS beneficiary (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    owner_id        uuid,
    label           text NOT NULL,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    coordinates     jsonb NOT NULL DEFAULT '{}',
    status          text NOT NULL CHECK (status IN ('draft', 'approved')),
    approved_by     uuid,
    approved_at     timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, owner_id) REFERENCES owner (organization_id, id)
);

CREATE TABLE IF NOT EXISTS payout_batch (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    beneficiary_id  uuid NOT NULL,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    status          text NOT NULL CHECK (status IN ('prepared', 'approved', 'cancelled')),
    prepared_by     uuid NOT NULL REFERENCES account (id),
    prepared_at     timestamptz NOT NULL,
    preparation_reason text NOT NULL,
    approved_by     uuid REFERENCES account (id),
    approved_at     timestamptz,
    approval_reason text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, beneficiary_id) REFERENCES beneficiary (organization_id, id)
);

CREATE TABLE IF NOT EXISTS commission (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    lease_id        uuid,
    beneficiary_account_id uuid REFERENCES account (id),
    beneficiary_label text NOT NULL,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    base_minor      bigint NOT NULL CHECK (base_minor >= 0),
    amount_minor    bigint NOT NULL CHECK (amount_minor >= 0),
    rate_bps        integer,
    status          text NOT NULL,
    earned_at       timestamptz NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, lease_id) REFERENCES lease (organization_id, id)
);

CREATE TABLE IF NOT EXISTS notification (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    account_id      uuid REFERENCES account (id),
    channel         text NOT NULL,
    template        text NOT NULL,
    payload         jsonb NOT NULL DEFAULT '{}',
    status          text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id)
);

CREATE TABLE IF NOT EXISTS document_sequence (
    organization_id uuid NOT NULL REFERENCES organization (id),
    series          text NOT NULL,
    last_value      bigint NOT NULL,
    PRIMARY KEY (organization_id, series)
);

CREATE TABLE IF NOT EXISTS async_operation (
    id              uuid PRIMARY KEY,
    organization_id uuid NOT NULL REFERENCES organization (id),
    type            text NOT NULL,
    requester_id    uuid,
    status          text NOT NULL,
    result          jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, id)
);

CREATE OR REPLACE FUNCTION veille_forbid_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'immutable' USING ERRCODE = 'integrity_constraint_violation';
END;
$$;

CREATE OR REPLACE FUNCTION veille_ledger_balanced()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    line jsonb;
    debit bigint := 0;
    credit bigint := 0;
    d bigint;
    c bigint;
    n int := 0;
BEGIN
    IF jsonb_typeof(NEW.lines) <> 'array' THEN
        RAISE EXCEPTION 'UNBALANCED_ENTRY';
    END IF;
    FOR line IN SELECT value FROM jsonb_array_elements(NEW.lines)
    LOOP
        n := n + 1;
        IF line ? 'currency' AND line->>'currency' IS DISTINCT FROM NEW.currency THEN
            RAISE EXCEPTION 'CURRENCY_MISMATCH';
        END IF;
        d := COALESCE((line->>'debit_minor')::bigint, 0);
        c := COALESCE((line->>'credit_minor')::bigint, 0);
        IF d < 0 OR c < 0 OR (d > 0 AND c > 0) THEN
            RAISE EXCEPTION 'UNBALANCED_ENTRY';
        END IF;
        debit := debit + d;
        credit := credit + c;
    END LOOP;
    IF n < 2 OR debit <> credit OR debit = 0 THEN
        RAISE EXCEPTION 'UNBALANCED_ENTRY';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION veille_payout_no_self_approval()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.approved_by IS NOT NULL AND NEW.approved_by = NEW.prepared_by THEN
        RAISE EXCEPTION 'SELF_APPROVAL_FORBIDDEN';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION veille_invoice_amounts_frozen()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.status <> 'draft' AND (
        NEW.total_minor IS DISTINCT FROM OLD.total_minor
        OR NEW.currency IS DISTINCT FROM OLD.currency
        OR NEW.lines IS DISTINCT FROM OLD.lines
    ) THEN
        RAISE EXCEPTION 'immutable';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION veille_visit_locked_when_sealed()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    visit uuid;
    org uuid;
    row_data record;
BEGIN
    IF TG_OP = 'DELETE' THEN
        row_data := OLD;
    ELSE
        row_data := NEW;
    END IF;
    org := row_data.organization_id;
    IF TG_TABLE_NAME = 'media_asset' THEN
        IF row_data.owner_type <> 'visit' THEN
            IF TG_OP = 'DELETE' THEN
                RETURN OLD;
            END IF;
            RETURN NEW;
        END IF;
        visit := row_data.owner_id;
    ELSE
        visit := row_data.visit_id;
    END IF;
    IF EXISTS (
        SELECT 1 FROM evidence_seal s
        WHERE s.organization_id = org AND s.visit_id = visit
    ) THEN
        RAISE EXCEPTION 'immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS payment_immutable ON payment;
CREATE TRIGGER payment_immutable
    BEFORE UPDATE OR DELETE ON payment
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS ledger_entry_immutable ON ledger_entry;
CREATE TRIGGER ledger_entry_immutable
    BEFORE UPDATE OR DELETE ON ledger_entry
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS ledger_entry_balanced ON ledger_entry;
CREATE TRIGGER ledger_entry_balanced
    BEFORE INSERT ON ledger_entry
    FOR EACH ROW EXECUTE FUNCTION veille_ledger_balanced();

DROP TRIGGER IF EXISTS credit_note_immutable ON credit_note;
CREATE TRIGGER credit_note_immutable
    BEFORE UPDATE OR DELETE ON credit_note
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS evidence_seal_immutable ON evidence_seal;
CREATE TRIGGER evidence_seal_immutable
    BEFORE UPDATE OR DELETE ON evidence_seal
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS publication_receipt_immutable ON publication_receipt;
CREATE TRIGGER publication_receipt_immutable
    BEFORE UPDATE OR DELETE ON publication_receipt
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS review_decision_immutable ON review_decision;
CREATE TRIGGER review_decision_immutable
    BEFORE UPDATE OR DELETE ON review_decision
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS payout_no_self_approval ON payout_batch;
CREATE TRIGGER payout_no_self_approval
    BEFORE INSERT OR UPDATE ON payout_batch
    FOR EACH ROW EXECUTE FUNCTION veille_payout_no_self_approval();

DROP TRIGGER IF EXISTS invoice_amounts_frozen ON invoice;
CREATE TRIGGER invoice_amounts_frozen
    BEFORE UPDATE ON invoice
    FOR EACH ROW EXECUTE FUNCTION veille_invoice_amounts_frozen();

DROP TRIGGER IF EXISTS visit_fact_immutable ON visit_fact;
CREATE TRIGGER visit_fact_immutable
    BEFORE UPDATE OR DELETE ON visit_fact
    FOR EACH ROW EXECUTE FUNCTION veille_forbid_mutation();

DROP TRIGGER IF EXISTS visit_fact_locked ON visit_fact;
CREATE TRIGGER visit_fact_locked
    BEFORE INSERT ON visit_fact
    FOR EACH ROW EXECUTE FUNCTION veille_visit_locked_when_sealed();

DROP TRIGGER IF EXISTS checklist_answer_locked ON checklist_answer;
CREATE TRIGGER checklist_answer_locked
    BEFORE INSERT OR UPDATE OR DELETE ON checklist_answer
    FOR EACH ROW EXECUTE FUNCTION veille_visit_locked_when_sealed();

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'anomaly', 'quote', 'work_order', 'listing', 'viewing_request', 'candidature',
        'lease', 'rent_schedule', 'invoice', 'credit_note', 'payment_attempt', 'payment',
        'ledger_entry', 'beneficiary', 'payout_batch', 'commission', 'notification',
        'document_sequence', 'async_operation', 'sync_action_receipt', 'auth_challenge'
    ]
    LOOP
        EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY', t);
        IF NOT EXISTS (
            SELECT 1 FROM pg_policies WHERE schemaname = 'public' AND tablename = t AND policyname = 'org_isolation'
        ) THEN
            EXECUTE format(
                'CREATE POLICY org_isolation ON %I
                 FOR ALL
                 USING (organization_id = app_current_organization_id())
                 WITH CHECK (organization_id = app_current_organization_id())',
                t
            );
        END IF;
    END LOOP;
END $$;
