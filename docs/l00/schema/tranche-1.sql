-- Veille L00 — Schéma SQL tranche 1 (Identity, Property, Fieldwork, Evidence)
-- PostgreSQL 16+ ; montants futurs en bigint ; UUID métier
-- Source : specs/Veille-contrats-techniques.md §2

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ---------------------------------------------------------------------------
-- Identity
-- ---------------------------------------------------------------------------

CREATE TABLE organization (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name            text NOT NULL,
    status          text NOT NULL CHECK (status IN ('setup', 'active', 'suspended', 'closed')),
    timezone        text NOT NULL DEFAULT 'Indian/Antananarivo',
    billing_identity jsonb NOT NULL DEFAULT '{}',
    policies_version integer NOT NULL DEFAULT 1,
    emergency_contacts jsonb NOT NULL DEFAULT '[]',
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1
);

CREATE TABLE account (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    display_name    text NOT NULL,
    email_normalized text,
    phone_normalized text,
    email_verified_at timestamptz,
    phone_verified_at timestamptz,
    password_hash   text,
    status          text NOT NULL CHECK (status IN ('active', 'locked', 'disabled')),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT account_contact_chk CHECK (email_normalized IS NOT NULL OR phone_normalized IS NOT NULL)
);

CREATE UNIQUE INDEX account_email_uidx ON account (email_normalized) WHERE email_normalized IS NOT NULL;
CREATE UNIQUE INDEX account_phone_uidx ON account (phone_normalized) WHERE phone_normalized IS NOT NULL;

CREATE TABLE membership (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    account_id      uuid NOT NULL REFERENCES account (id),
    roles           text[] NOT NULL DEFAULT '{}',
    permissions     text[] NOT NULL DEFAULT '{}',
    status          text NOT NULL CHECK (status IN ('invited', 'active', 'suspended', 'revoked')),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, account_id)
);

CREATE TABLE device (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    account_id      uuid NOT NULL REFERENCES account (id),
    device_identifier text NOT NULL,
    public_key      text,
    pin_hash        text,
    registered_at   timestamptz NOT NULL DEFAULT now(),
    revoked_at      timestamptz,
    last_sync_at    timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, device_identifier)
);

CREATE TABLE refresh_token (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    account_id      uuid NOT NULL REFERENCES account (id),
    device_id       uuid REFERENCES device (id),
    token_hash      text NOT NULL UNIQUE,
    expires_at      timestamptz NOT NULL,
    revoked_at      timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------------
-- Property
-- ---------------------------------------------------------------------------

CREATE TABLE owner (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    account_id      uuid REFERENCES account (id),
    identity        jsonb NOT NULL DEFAULT '{}',
    contacts        jsonb NOT NULL DEFAULT '{}',
    preferences     jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id)
);

CREATE TABLE property (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    current_owner_id uuid NOT NULL,
    address         text NOT NULL,
    neighborhood    text,
    lat_e7          integer,
    lon_e7          integer,
    timezone        text NOT NULL,
    status          text NOT NULL,
    characteristics jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, id),
    FOREIGN KEY (organization_id, current_owner_id)
        REFERENCES owner (organization_id, id)
);

CREATE TABLE property_ownership (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    owner_id        uuid NOT NULL,
    valid_from      date NOT NULL,
    valid_to        date,
    reason          text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id),
    FOREIGN KEY (organization_id, owner_id) REFERENCES owner (organization_id, id)
);

CREATE TABLE access_instruction (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    content_encrypted bytea NOT NULL,
    version         integer NOT NULL DEFAULT 1,
    window_start    timestamptz,
    window_end      timestamptz,
    contact         jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

CREATE TABLE mandate (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    owner_id        uuid NOT NULL,
    property_id     uuid NOT NULL,
    rights          jsonb NOT NULL DEFAULT '{}',
    ceilings        jsonb NOT NULL DEFAULT '{}',
    valid_from      date NOT NULL,
    valid_to        date,
    document_ref    text,
    document_version integer,
    legal_validation_ref text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    FOREIGN KEY (organization_id, owner_id) REFERENCES owner (organization_id, id),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

CREATE TABLE checklist_template (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    name            text NOT NULL,
    type            text NOT NULL,
    status          text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX checklist_template_org_id_uidx ON checklist_template (organization_id, id);

CREATE TABLE checklist_version (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    template_id     uuid NOT NULL,
    number          integer NOT NULL,
    published_at    timestamptz,
    min_photos      integer NOT NULL DEFAULT 0,
    gps_parameters  jsonb NOT NULL DEFAULT '{}',
    status          text NOT NULL CHECK (status IN ('draft', 'published')),
    created_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, template_id, number),
    FOREIGN KEY (organization_id, template_id) REFERENCES checklist_template (organization_id, id)
);

CREATE UNIQUE INDEX checklist_version_org_id_uidx ON checklist_version (organization_id, id);

CREATE TABLE checklist_item (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    version_id      uuid NOT NULL,
    stable_item_key text NOT NULL,
    sort_order      integer NOT NULL,
    label           text NOT NULL,
    instruction     text,
    reference_photo_media_id uuid,
    constraints     jsonb NOT NULL DEFAULT '{}',
    UNIQUE (organization_id, version_id, stable_item_key),
    FOREIGN KEY (organization_id, version_id) REFERENCES checklist_version (organization_id, id)
);

CREATE TABLE tariff_version (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid,
    contract_ref    text,
    type            text NOT NULL,
    amount_minor    bigint NOT NULL,
    currency        text NOT NULL CHECK (currency IN ('EUR', 'MGA')),
    valid_from      date NOT NULL,
    valid_to        date,
    created_at      timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id)
);

-- ---------------------------------------------------------------------------
-- Fieldwork
-- ---------------------------------------------------------------------------

CREATE TABLE recurrence (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    rule            text NOT NULL,
    timezone        text NOT NULL,
    agent_id        uuid NOT NULL REFERENCES account (id),
    checklist_version_id uuid NOT NULL,
    exceptions      jsonb NOT NULL DEFAULT '[]',
    active          boolean NOT NULL DEFAULT true,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id),
    FOREIGN KEY (organization_id, checklist_version_id) REFERENCES checklist_version (organization_id, id)
);

CREATE UNIQUE INDEX recurrence_org_id_uidx ON recurrence (organization_id, id);

CREATE TABLE mission (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    property_id     uuid NOT NULL,
    type            text NOT NULL,
    scheduled_start timestamptz NOT NULL,
    scheduled_end   timestamptz,
    status          text NOT NULL,
    assignment_revision integer NOT NULL DEFAULT 1,
    checklist_version_id uuid NOT NULL,
    tariff_snapshot jsonb,
    instructions_snapshot jsonb,
    selected_visit_id uuid,
    recurrence_id   uuid,
    scheduled_occurrence timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    FOREIGN KEY (organization_id, property_id) REFERENCES property (organization_id, id),
    FOREIGN KEY (organization_id, checklist_version_id) REFERENCES checklist_version (organization_id, id),
    FOREIGN KEY (organization_id, recurrence_id) REFERENCES recurrence (organization_id, id)
);

CREATE UNIQUE INDEX mission_org_id_uidx ON mission (organization_id, id);
CREATE UNIQUE INDEX mission_recurrence_occurrence_uidx
    ON mission (organization_id, recurrence_id, scheduled_occurrence)
    WHERE recurrence_id IS NOT NULL AND scheduled_occurrence IS NOT NULL;

CREATE TABLE assignment_history (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    mission_id      uuid NOT NULL,
    agent_id        uuid NOT NULL REFERENCES account (id),
    revision        integer NOT NULL,
    valid_from      timestamptz NOT NULL,
    reason          text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, mission_id, revision),
    FOREIGN KEY (organization_id, mission_id) REFERENCES mission (organization_id, id)
);

CREATE TABLE visit_attempt (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    mission_id      uuid NOT NULL,
    agent_id        uuid NOT NULL REFERENCES account (id),
    device_id       uuid REFERENCES device (id),
    assignment_revision integer NOT NULL,
    type            text NOT NULL,
    local_visit_id  uuid NOT NULL,
    reception_status text NOT NULL,
    issue           text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    version         integer NOT NULL DEFAULT 1,
    UNIQUE (organization_id, local_visit_id),
    FOREIGN KEY (organization_id, mission_id) REFERENCES mission (organization_id, id)
);

CREATE UNIQUE INDEX visit_attempt_org_id_uidx ON visit_attempt (organization_id, id);

CREATE TABLE visit_fact (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    visit_id        uuid NOT NULL,
    action_id       uuid NOT NULL,
    sequence_number integer NOT NULL,
    type            text NOT NULL,
    payload         jsonb NOT NULL,
    device_time     timestamptz,
    received_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, action_id),
    FOREIGN KEY (organization_id, visit_id) REFERENCES visit_attempt (organization_id, id)
);

CREATE TABLE checklist_answer (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    visit_id        uuid NOT NULL,
    stable_item_key text NOT NULL,
    checklist_version_id uuid NOT NULL,
    answer          text NOT NULL,
    note            text,
    UNIQUE (organization_id, visit_id, stable_item_key),
    FOREIGN KEY (organization_id, visit_id) REFERENCES visit_attempt (organization_id, id)
);

CREATE TABLE media_asset (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    owner_type      text NOT NULL,
    owner_id        uuid NOT NULL,
    storage_key     text NOT NULL,
    storage_version text,
    size_bytes      bigint NOT NULL,
    mime            text NOT NULL,
    sha256          text NOT NULL,
    status          text NOT NULL CHECK (status IN ('uploading', 'verified', 'rejected', 'superseded')),
    created_at      timestamptz NOT NULL DEFAULT now(),
    finalized_at    timestamptz
);

CREATE UNIQUE INDEX media_asset_org_id_uidx ON media_asset (organization_id, id);
CREATE INDEX media_asset_sha_org_idx ON media_asset (organization_id, sha256);

-- ---------------------------------------------------------------------------
-- Evidence
-- ---------------------------------------------------------------------------

CREATE TABLE evidence_seal (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    visit_id        uuid NOT NULL,
    manifest_version integer NOT NULL,
    manifest_hash   text NOT NULL,
    signature       text NOT NULL,
    key_id          text NOT NULL,
    received_at     timestamptz NOT NULL DEFAULT now(),
    parent_seal_id  uuid,
    FOREIGN KEY (organization_id, visit_id) REFERENCES visit_attempt (organization_id, id)
);

CREATE UNIQUE INDEX evidence_seal_org_id_uidx ON evidence_seal (organization_id, id);

CREATE TABLE review_decision (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    mission_id      uuid NOT NULL,
    visit_id        uuid NOT NULL,
    decision        text NOT NULL,
    resolved_flags  jsonb NOT NULL DEFAULT '[]',
    reasons         text,
    author_id       uuid NOT NULL REFERENCES account (id),
    created_at      timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (organization_id, mission_id) REFERENCES mission (organization_id, id),
    FOREIGN KEY (organization_id, visit_id) REFERENCES visit_attempt (organization_id, id)
);

CREATE TABLE report (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    mission_id      uuid NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (organization_id, mission_id) REFERENCES mission (organization_id, id)
);

CREATE UNIQUE INDEX report_org_id_uidx ON report (organization_id, id);

CREATE TABLE report_version (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    report_id       uuid NOT NULL,
    version_number  integer NOT NULL,
    seal_ids        uuid[] NOT NULL DEFAULT '{}',
    content_snapshot jsonb NOT NULL,
    pdf_asset_id    uuid,
    status          text NOT NULL,
    published_at    timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, report_id, version_number),
    FOREIGN KEY (organization_id, report_id) REFERENCES report (organization_id, id),
    FOREIGN KEY (organization_id, pdf_asset_id) REFERENCES media_asset (organization_id, id)
);

CREATE UNIQUE INDEX report_version_org_id_uidx ON report_version (organization_id, id);

CREATE TABLE publication_receipt (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    report_version_id uuid NOT NULL,
    pdf_hash        text NOT NULL,
    manifest_hashes text[] NOT NULL,
    signed_at       timestamptz NOT NULL,
    signature       text NOT NULL,
    opaque_code     text NOT NULL UNIQUE,
    FOREIGN KEY (organization_id, report_version_id) REFERENCES report_version (organization_id, id)
);

-- ---------------------------------------------------------------------------
-- Transversal
-- ---------------------------------------------------------------------------

CREATE TABLE audit_event (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    actor_id        uuid,
    action          text NOT NULL,
    object_type     text NOT NULL,
    object_id       uuid,
    payload         jsonb NOT NULL DEFAULT '{}',
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE outbox_event (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    event_id        uuid NOT NULL UNIQUE,
    aggregate_type  text NOT NULL,
    aggregate_id    uuid NOT NULL,
    aggregate_version integer NOT NULL,
    event_type      text NOT NULL,
    occurred_at     timestamptz NOT NULL,
    actor_id        uuid,
    correlation_id  uuid,
    causation_id    uuid,
    payload         jsonb NOT NULL,
    published_at    timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE consumer_receipt (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    event_id        uuid NOT NULL,
    consumer_name   text NOT NULL,
    processed_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, event_id, consumer_name)
);

CREATE TABLE idempotency_record (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id uuid NOT NULL REFERENCES organization (id),
    key             text NOT NULL,
    request_hash    text NOT NULL,
    response_code   integer NOT NULL,
    response_body   jsonb,
    created_at      timestamptz NOT NULL DEFAULT now(),
    expires_at      timestamptz NOT NULL,
    UNIQUE (organization_id, key)
);

CREATE INDEX idempotency_expires_idx ON idempotency_record (expires_at);
CREATE INDEX outbox_unpublished_idx ON outbox_event (created_at) WHERE published_at IS NULL;
