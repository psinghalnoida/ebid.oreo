<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
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
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tenant_api_credential (

                id                  CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                client_id           VARCHAR(255) NOT NULL UNIQUE,

                client_secret_hash  TEXT NOT NULL,

                status              ENUM('active', 'revoked') NOT NULL DEFAULT 'active',

                created_by_party_id CHAR(36),

                created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                revoked_at          DATETIME(6),

                last_used_at        DATETIME(6),

                CONSTRAINT fk_tenant_api_credential_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_tenant_api_credential_created_by_party_id FOREIGN KEY (created_by_party_id) REFERENCES party(id)
            );
            CREATE INDEX idx_tenant_api_credential_tenant ON tenant_api_credential (tenant_id, status);

            ALTER TABLE tenant ADD COLUMN webhook_url TEXT;
            ALTER TABLE tenant ADD COLUMN webhook_signing_secret TEXT;
            CREATE TABLE tenant_webhook_delivery (

                id              CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                event_type      TEXT NOT NULL,

                payload         TEXT NOT NULL,

                status          ENUM('pending', 'delivered', 'failed') NOT NULL DEFAULT 'pending',

                attempts        INTEGER NOT NULL DEFAULT 0,

                last_error      TEXT,

                created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                next_attempt_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                delivered_at    DATETIME(6),

                CONSTRAINT fk_tenant_webhook_delivery_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id)
            );
            CREATE INDEX idx_tenant_webhook_delivery_status ON tenant_webhook_delivery (status, next_attempt_at);
            CREATE INDEX idx_tenant_webhook_delivery_tenant ON tenant_webhook_delivery (tenant_id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            DROP TABLE IF EXISTS tenant_webhook_delivery;
            ALTER TABLE tenant DROP COLUMN webhook_signing_secret;
            ALTER TABLE tenant DROP COLUMN webhook_url;

            DROP TABLE IF EXISTS tenant_api_credential;
        SQL);
    }
}
