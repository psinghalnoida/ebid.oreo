<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-62-66/PR-37 (ADWITIX_Master.docx): Tenant API Access -- lets a
// whitelisted Tenant integrate its own systems with the platform as an
// alternative to the portal UI, under the exact same governance
// (BR-13/14, Tenant Admin approval gate) as a portal submission.
//
// BR-64: OAuth2 client-credentials "through the platform's existing
// Auth0 relationship." Auth0 is a paid external vendor requiring its
// own account (same category as the payment gateway/SMS provider,
// D-23) -- this builds a REAL, self-hosted client-credentials flow
// instead (ApiCredentialService), the same substitution pattern
// TotpService already established for BR-04's Auth0/TOTP requirement:
// not a fake stand-in, genuinely hard-scoped per tenant at issuance.
class CreateTenantApiAccess extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE tenant_api_credential_status AS ENUM ('active', 'revoked');
            CREATE TABLE tenant_api_credential (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id           UUID NOT NULL REFERENCES tenant(id),
                client_id           TEXT NOT NULL UNIQUE,
                client_secret_hash  TEXT NOT NULL,
                status              tenant_api_credential_status NOT NULL DEFAULT 'active',
                created_by_party_id UUID REFERENCES party(id),
                created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                revoked_at          TIMESTAMPTZ,
                last_used_at        TIMESTAMPTZ
            );
            CREATE INDEX idx_tenant_api_credential_tenant ON tenant_api_credential (tenant_id, status);

            ALTER TABLE tenant ADD COLUMN webhook_url TEXT;
            ALTER TABLE tenant ADD COLUMN webhook_signing_secret TEXT;

            CREATE TYPE tenant_webhook_delivery_status AS ENUM ('pending', 'delivered', 'failed');
            CREATE TABLE tenant_webhook_delivery (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id       UUID NOT NULL REFERENCES tenant(id),
                event_type      TEXT NOT NULL,
                payload         TEXT NOT NULL,
                status          tenant_webhook_delivery_status NOT NULL DEFAULT 'pending',
                attempts        INTEGER NOT NULL DEFAULT 0,
                last_error      TEXT,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                delivered_at    TIMESTAMPTZ
            );
            CREATE INDEX idx_tenant_webhook_delivery_status ON tenant_webhook_delivery (status, next_attempt_at);
            CREATE INDEX idx_tenant_webhook_delivery_tenant ON tenant_webhook_delivery (tenant_id);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            DROP TABLE IF EXISTS tenant_webhook_delivery;
            DROP TYPE IF EXISTS tenant_webhook_delivery_status;

            ALTER TABLE tenant DROP COLUMN IF EXISTS webhook_signing_secret;
            ALTER TABLE tenant DROP COLUMN IF EXISTS webhook_url;

            DROP TABLE IF EXISTS tenant_api_credential;
            DROP TYPE IF EXISTS tenant_api_credential_status;
        SQL);
    }
}
