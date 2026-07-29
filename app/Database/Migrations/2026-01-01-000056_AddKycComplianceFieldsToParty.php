<?php

namespace App\Database\Migrations;

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
    public function up()
    {
        // Postgres requires ALTER TYPE ... ADD VALUE outside an explicit
        // transaction in older versions; works fine on Postgres 16
        // (used throughout this project's testing) — same pattern as
        // D-15's AddSuperAdminRole migration.
        $this->db->query("ALTER TYPE kyc_status ADD VALUE IF NOT EXISTS 'submitted';");

        $this->db->query(<<<SQL
            ALTER TABLE party
                ADD COLUMN pan_verified_at TIMESTAMPTZ,
                ADD COLUMN gstin_verified_at TIMESTAMPTZ,
                ADD COLUMN aadhaar_verified_at TIMESTAMPTZ,
                ADD COLUMN email_verified_at TIMESTAMPTZ,
                ADD COLUMN bank_verified_at TIMESTAMPTZ,
                ADD COLUMN kyc_verified_by_party_id UUID REFERENCES party(id),
                ADD COLUMN kyc_submitted_at TIMESTAMPTZ,
                ADD COLUMN payout_bank_account_holder_name TEXT,
                ADD COLUMN payout_bank_name TEXT,
                ADD COLUMN payout_bank_branch_name TEXT,
                ADD COLUMN payout_bank_upi_id TEXT,
                ADD COLUMN edd_required_at TIMESTAMPTZ,
                ADD COLUMN edd_cleared_at TIMESTAMPTZ,
                ADD COLUMN edd_cleared_by_party_id UUID REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party
                DROP COLUMN IF EXISTS pan_verified_at,
                DROP COLUMN IF EXISTS gstin_verified_at,
                DROP COLUMN IF EXISTS aadhaar_verified_at,
                DROP COLUMN IF EXISTS email_verified_at,
                DROP COLUMN IF EXISTS bank_verified_at,
                DROP COLUMN IF EXISTS kyc_verified_by_party_id,
                DROP COLUMN IF EXISTS kyc_submitted_at,
                DROP COLUMN IF EXISTS payout_bank_account_holder_name,
                DROP COLUMN IF EXISTS payout_bank_name,
                DROP COLUMN IF EXISTS payout_bank_branch_name,
                DROP COLUMN IF EXISTS payout_bank_upi_id,
                DROP COLUMN IF EXISTS edd_required_at,
                DROP COLUMN IF EXISTS edd_cleared_at,
                DROP COLUMN IF EXISTS edd_cleared_by_party_id;
        SQL);
        // Postgres does not support removing an enum value directly —
        // additive-only, harmless to leave 'submitted' in place.
    }
}
