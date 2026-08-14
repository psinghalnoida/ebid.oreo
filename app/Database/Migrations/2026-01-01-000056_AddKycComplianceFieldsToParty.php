<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-17/BR-18/BR-55/PR-15: fills gaps in the party-level KYC schema
// that's existed since Phase 0 (entity_type, PAN/Aadhaar/org fields,
// kyc_status) but was never actually wired to a real onboarding flow.
//
// Compliance flags (PR-15 step 6: "System updates compliance flags
// (PAN/GST/Email/Mobile/Bank Verified)") — mobile_verified_at already
// exists; this adds the rest. No automated PAN/GSTIN registry API or
// UIDAI Aadhaar tokenization service exists in this environment (the
// same category of external dependency as Auth0/Gemini/the payment
// gateway) — confirmed with the project owner, so pan_verified_at/
// gstin_verified_at/aadhaar_verified_at are set by a manual SaaS Admin
// action (KycService::verifyComplianceFlag), not an automated check.
//
// BR-18 banking gap: payout_bank_account_number/ifsc already exist
// (BR-50) but BR-18 additionally names Account Holder Name, Bank Name,
// Branch Name, and an optional UPI ID — added onto the same fields
// rather than a second banking record, since it's the same one party/
// one bank-record design BR-50 already established.
//
// BR-55 enhanced due diligence: a flag pair recording whether a party
// has crossed the (Super-Admin-set, via PR-04's Sovereign Rule module)
// enhanced-due-diligence threshold and whether that's been cleared —
// gates a specific high-value transaction, not the account as a whole.
class AddKycComplianceFieldsToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        // Postgres requires ALTER TYPE ... ADD VALUE outside an explicit
        // transaction in older versions; works fine on Postgres 16
        // (used throughout this project's testing) — same pattern as
        // D-15's AddSuperAdminRole migration.
        $this->db->query("ALTER TABLE party MODIFY COLUMN kyc_status ENUM('pending', 'verified', 'suspended', 'submitted') NOT NULL DEFAULT 'pending';");

        $this->execMulti(<<<SQL
            ALTER TABLE party
                ADD COLUMN pan_verified_at DATETIME(6),
                ADD COLUMN gstin_verified_at DATETIME(6),
                ADD COLUMN aadhaar_verified_at DATETIME(6),
                ADD COLUMN email_verified_at DATETIME(6),
                ADD COLUMN bank_verified_at DATETIME(6),
                ADD COLUMN kyc_verified_by_party_id CHAR(36),
                ADD COLUMN kyc_submitted_at DATETIME(6),
                ADD COLUMN payout_bank_account_holder_name TEXT,
                ADD COLUMN payout_bank_name TEXT,
                ADD COLUMN payout_bank_branch_name TEXT,
                ADD COLUMN payout_bank_upi_id TEXT,
                ADD COLUMN edd_required_at DATETIME(6),
                ADD COLUMN edd_cleared_at DATETIME(6),
                ADD COLUMN edd_cleared_by_party_id CHAR(36),
                ADD CONSTRAINT fk_party_kyc_verified_by_party_id FOREIGN KEY (kyc_verified_by_party_id) REFERENCES party(id),
                ADD CONSTRAINT fk_party_edd_cleared_by_party_id FOREIGN KEY (edd_cleared_by_party_id) REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                DROP FOREIGN KEY fk_party_kyc_verified_by_party_id,
                DROP FOREIGN KEY fk_party_edd_cleared_by_party_id,
                DROP COLUMN pan_verified_at,
                DROP COLUMN gstin_verified_at,
                DROP COLUMN aadhaar_verified_at,
                DROP COLUMN email_verified_at,
                DROP COLUMN bank_verified_at,
                DROP COLUMN kyc_verified_by_party_id,
                DROP COLUMN kyc_submitted_at,
                DROP COLUMN payout_bank_account_holder_name,
                DROP COLUMN payout_bank_name,
                DROP COLUMN payout_bank_branch_name,
                DROP COLUMN payout_bank_upi_id,
                DROP COLUMN edd_required_at,
                DROP COLUMN edd_cleared_at,
                DROP COLUMN edd_cleared_by_party_id;
        SQL);
        // Postgres does not support removing an enum value directly —
        // additive-only, harmless to leave 'submitted' in place.
    }
}
