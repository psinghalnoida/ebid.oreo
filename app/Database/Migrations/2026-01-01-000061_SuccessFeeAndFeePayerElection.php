<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
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
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE tenant ADD COLUMN subscription_tier ENUM('coco_starter', 'tsx_launch', 'tsx_growth', 'tsx_enterprise') NOT NULL DEFAULT 'coco_starter';

            ALTER TABLE tenant DROP COLUMN buyer_fee_percent;
            ALTER TABLE tenant DROP COLUMN saas_fee_percent;
            ALTER TABLE listing DROP COLUMN buyer_fee_percent_override;
            ALTER TABLE sale_event ADD COLUMN fee_payer ENUM('buyer_pays', 'seller_pays') NOT NULL DEFAULT 'buyer_pays';
            CREATE TABLE tenant_fee_ledger (

                id              CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                settlement_id CHAR(36) NOT NULL,

                sale_event_id CHAR(36) NOT NULL,

                amount          NUMERIC(14,2) NOT NULL,

                status          ENUM('unbilled', 'billed') NOT NULL DEFAULT 'unbilled',

                invoice_id      CHAR(36),

                created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_tenant_fee_ledger_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_tenant_fee_ledger_settlement_id FOREIGN KEY (settlement_id) REFERENCES settlement(id),

                CONSTRAINT fk_tenant_fee_ledger_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id)
            );
            CREATE INDEX idx_tenant_fee_ledger_tenant_status ON tenant_fee_ledger (tenant_id, status);
            CREATE TABLE tenant_monthly_invoice (

                id                  CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                invoice_number      VARCHAR(255) NOT NULL UNIQUE,

                period_start        DATETIME(6) NOT NULL,

                period_end          DATETIME(6) NOT NULL,

                total_amount        NUMERIC(14,2) NOT NULL,

                gst_amount          NUMERIC(14,2) NOT NULL,

                status              ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',

                generated_at        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                paid_at             DATETIME(6),

                paid_by_party_id CHAR(36),

                CONSTRAINT fk_tenant_monthly_invoice_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_tenant_monthly_invoice_paid_by_party_id FOREIGN KEY (paid_by_party_id) REFERENCES party(id)
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
        $this->db->query("ALTER TABLE invoice MODIFY COLUMN invoice_type ENUM(
            'tenant_to_buyer', 'saas_to_tenant', 'platform_to_buyer', 'platform_to_seller'
        ) NOT NULL;");
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE tenant_fee_ledger DROP CONSTRAINT IF EXISTS fk_tenant_fee_ledger_invoice;
            DROP TABLE IF EXISTS tenant_monthly_invoice;
            DROP TABLE IF EXISTS tenant_fee_ledger;
            ALTER TABLE sale_event DROP COLUMN fee_payer;
            ALTER TABLE listing ADD COLUMN buyer_fee_percent_override NUMERIC(4,2);
            ALTER TABLE tenant ADD COLUMN saas_fee_percent NUMERIC(4,2) NOT NULL DEFAULT 0.50;
            ALTER TABLE tenant ADD COLUMN buyer_fee_percent NUMERIC(4,2) NOT NULL DEFAULT 5.00;

            ALTER TABLE tenant DROP COLUMN subscription_tier;
        SQL);
    }
}
