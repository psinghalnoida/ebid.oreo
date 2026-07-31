<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// ADWITIX_Master.docx (D-87/D-88): BR-08/BR-09/BR-31-34/BR-56/BR-12 and
// PR-06/PR-32 rewritten. Replaces the flat-0.5%-SaaS-plus-tenant-
// adjustable-0.5%-5%-band model with a single, platform-wide,
// non-tenant-adjustable declining Success Fee schedule (BR-31, in
// EmdService::calculateSuccessFee()) plus a per-Sale-Event Fee Payer
// Election (BR-32). Seller-Pays has no real-time collection mechanism
// (BR-33 keeps the platform out of the 100% sale-value flow) --
// resolved per the project owner's explicit direction: bill the
// Tenant monthly instead (tenant_fee_ledger + tenant_monthly_invoice),
// restricted to non-CoCo-Starter tenants who have a real billing
// relationship to invoice against.
class SuccessFeeAndFeePayerElection extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE tenant_subscription_tier AS ENUM ('coco_starter', 'tsx_launch', 'tsx_growth', 'tsx_enterprise');
            ALTER TABLE tenant ADD COLUMN subscription_tier tenant_subscription_tier NOT NULL DEFAULT 'coco_starter';

            ALTER TABLE tenant DROP COLUMN IF EXISTS buyer_fee_percent;
            ALTER TABLE tenant DROP COLUMN IF EXISTS saas_fee_percent;
            ALTER TABLE listing DROP COLUMN IF EXISTS buyer_fee_percent_override;

            CREATE TYPE sale_event_fee_payer AS ENUM ('buyer_pays', 'seller_pays');
            ALTER TABLE sale_event ADD COLUMN fee_payer sale_event_fee_payer NOT NULL DEFAULT 'buyer_pays';

            CREATE TYPE tenant_fee_ledger_status AS ENUM ('unbilled', 'billed');
            CREATE TABLE tenant_fee_ledger (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id       UUID NOT NULL REFERENCES tenant(id),
                settlement_id   UUID NOT NULL REFERENCES settlement(id),
                sale_event_id   UUID NOT NULL REFERENCES sale_event(id),
                amount          NUMERIC(14,2) NOT NULL,
                status          tenant_fee_ledger_status NOT NULL DEFAULT 'unbilled',
                invoice_id      UUID,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX idx_tenant_fee_ledger_tenant_status ON tenant_fee_ledger (tenant_id, status);

            CREATE TYPE tenant_monthly_invoice_status AS ENUM ('pending', 'paid');
            CREATE TABLE tenant_monthly_invoice (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id           UUID NOT NULL REFERENCES tenant(id),
                invoice_number      TEXT NOT NULL UNIQUE,
                period_start        TIMESTAMPTZ NOT NULL,
                period_end          TIMESTAMPTZ NOT NULL,
                total_amount        NUMERIC(14,2) NOT NULL,
                gst_amount          NUMERIC(14,2) NOT NULL,
                status              tenant_monthly_invoice_status NOT NULL DEFAULT 'pending',
                generated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
                paid_at             TIMESTAMPTZ,
                paid_by_party_id    UUID REFERENCES party(id)
            );
            CREATE INDEX idx_tenant_monthly_invoice_tenant ON tenant_monthly_invoice (tenant_id, status);

            ALTER TABLE tenant_fee_ledger ADD CONSTRAINT fk_tenant_fee_ledger_invoice
                FOREIGN KEY (invoice_id) REFERENCES tenant_monthly_invoice(id);
        SQL);

        // BR-56: the invoice is now issued directly BY the platform (the
        // Success Fee is 100% platform revenue, D-87/D-88) TO whichever
        // party paid it under that session's Fee Payer Election — never
        // by/to the Tenant any more. Postgres requires ALTER TYPE ... ADD
        // VALUE to run as its own statement, not combined with other DDL
        // in the same transaction/query — same pattern as D-15's
        // AddSuperAdminRole migration. The old 'tenant_to_buyer'/
        // 'saas_to_tenant' values are kept for historical invoices;
        // Postgres cannot drop enum values.
        $this->db->query("ALTER TYPE invoice_type ADD VALUE IF NOT EXISTS 'platform_to_buyer';");
        $this->db->query("ALTER TYPE invoice_type ADD VALUE IF NOT EXISTS 'platform_to_seller';");
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE tenant_fee_ledger DROP CONSTRAINT IF EXISTS fk_tenant_fee_ledger_invoice;
            DROP TABLE IF EXISTS tenant_monthly_invoice;
            DROP TABLE IF EXISTS tenant_fee_ledger;
            DROP TYPE IF EXISTS tenant_monthly_invoice_status;
            DROP TYPE IF EXISTS tenant_fee_ledger_status;

            ALTER TABLE sale_event DROP COLUMN IF EXISTS fee_payer;
            DROP TYPE IF EXISTS sale_event_fee_payer;

            ALTER TABLE listing ADD COLUMN buyer_fee_percent_override NUMERIC(4,2);
            ALTER TABLE tenant ADD COLUMN saas_fee_percent NUMERIC(4,2) NOT NULL DEFAULT 0.50;
            ALTER TABLE tenant ADD COLUMN buyer_fee_percent NUMERIC(4,2) NOT NULL DEFAULT 5.00;

            ALTER TABLE tenant DROP COLUMN IF EXISTS subscription_tier;
            DROP TYPE IF EXISTS tenant_subscription_tier;
        SQL);
    }
}
