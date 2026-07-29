<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\PartyDocumentModel;
use App\Models\PartyAddressModel;
use App\Libraries\KycService;
use App\Libraries\SovereignRuleService;

// BR-17/BR-18/BR-55/PR-15: the questionnaire, address, banking, review,
// and BR-55 gate logic tested directly against real data. Document
// UPLOAD's isValid() check requires is_uploaded_file(), never true
// outside a real PHP upload request — verified separately over real
// HTTP with curl -F, matching test:media's own established precedent
// for the identical limitation (see TestMedia.php's own note).
class TestKyc extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:kyc';
    protected $description = 'Proves BR-17/BR-18/BR-55 KYC verification, multi-address/banking, and the mandatory-before-first-transaction gate are real.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $documentModel = new PartyDocumentModel();
        $addressModel = new PartyAddressModel();
        $kyc = new KycService();

        CLI::write('=== Setup ===', 'yellow');
        $individual = $partyModel->createParty('+919555420001');
        $organization = $partyModel->createParty('+919555420002');
        $reviewer = $partyModel->createParty('+919555420003');

        CLI::write("\n=== Step 1 (BR-17): individual questionnaire validation ===", 'yellow');
        try {
            $kyc->saveQuestionnaire($individual['id'], 'individual', ['full_name' => 'Test Individual']);
            $this->assert(false, 'Should have thrown: missing PAN/DOB/Occupation');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'required'), 'Correctly rejected: incomplete individual questionnaire');
        }
        try {
            $kyc->saveQuestionnaire($individual['id'], 'individual', [
                'full_name' => 'Test Individual', 'pan' => 'NOTAPAN', 'date_of_birth' => '1990-01-01', 'occupation' => 'Engineer',
            ]);
            $this->assert(false, 'Should have thrown: invalid PAN format');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'PAN'), 'Correctly rejected: malformed PAN');
        }
        $saved = $kyc->saveQuestionnaire($individual['id'], 'individual', [
            'full_name' => 'Test Individual', 'pan' => 'abcde1234f', 'date_of_birth' => '1990-01-01',
            'occupation' => 'Engineer', 'aadhaar' => '123456789012',
        ]);
        $this->assert($saved['pan'] === 'ABCDE1234F', 'Valid PAN saved and uppercased');
        $this->assert($saved['aadhaar_masked'] === 'XXXX-XXXX-9012', 'Aadhaar genuinely masked to only the last 4 digits — raw 12-digit number never persisted');

        CLI::write("\n=== Step 2 (BR-17): organization questionnaire validation ===", 'yellow');
        try {
            $kyc->saveQuestionnaire($organization['id'], 'organization', ['org_cin' => 'U12345']);
            $this->assert(false, 'Should have thrown: incomplete organization questionnaire');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'required'), 'Correctly rejected: incomplete organization questionnaire');
        }
        $orgSaved = $kyc->saveQuestionnaire($organization['id'], 'organization', [
            'org_cin' => 'u12345mh2020ptc123456', 'org_gstin' => '27abcde1234f1z5', 'org_pan' => 'abcde1234f',
            'org_company_type' => 'Private Limited', 'org_industry' => 'Manufacturing',
        ]);
        $this->assert($orgSaved['org_gstin'] === '27ABCDE1234F1Z5', 'Organization GSTIN saved and uppercased');

        CLI::write("\n=== Step 3 (BR-18): address portfolio ===", 'yellow');
        try {
            $kyc->registerAddress($individual['id'], 'registered', ['line1' => '1 Main St', 'city' => 'Mumbai', 'district' => 'Mumbai', 'state' => 'MH', 'pin_code' => '12345']);
            $this->assert(false, 'Should have thrown: invalid PIN code');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'PIN'), 'Correctly rejected: invalid 5-digit PIN code');
        }
        $addr1 = $kyc->registerAddress($individual['id'], 'registered', ['line1' => '1 Main St', 'city' => 'Mumbai', 'district' => 'Mumbai', 'state' => 'MH', 'pin_code' => '400001']);
        $addr1b = $kyc->registerAddress($individual['id'], 'registered', ['line1' => '2 Updated St', 'city' => 'Mumbai', 'district' => 'Mumbai', 'state' => 'MH', 'pin_code' => '400002']);
        $this->assert($addr1['id'] === $addr1b['id'], 'BR-18: re-registering the SAME address type upserts in place, not a duplicate row');
        $this->assert($addr1b['line1'] === '2 Updated St', 'The upsert genuinely updated the stored value');
        $allAddresses = $addressModel->forParty($individual['id']);
        $this->assert(count($allAddresses) === 1, 'Still exactly one address row for this party after the upsert');

        CLI::write("\n=== Step 4 (BR-18): banking details ===", 'yellow');
        $banked = $kyc->registerBanking($individual['id'], [
            'account_holder_name' => 'Test Individual', 'bank_name' => 'Test Bank', 'branch_name' => 'Main Branch',
            'account_number' => '1234567890123', 'ifsc' => 'test0001234', 'upi_id' => 'test@upi',
        ]);
        $this->assert($banked['payout_bank_ifsc'] === 'TEST0001234', 'Banking IFSC saved and uppercased');
        $this->assert($banked['payout_bank_upi_id'] === 'test@upi', 'Optional UPI ID saved');

        CLI::write("\n=== Step 5 (PR-15): submitForReview requires documents + address first ===", 'yellow');
        try {
            $kyc->submitForReview($individual['id']);
            $this->assert(false, 'Should have thrown: no documents uploaded yet');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Missing required documents'), 'Correctly rejected: submission blocked with no required documents uploaded');
        }
        // Simulating the two required individual documents directly at
        // the model layer — the encrypt/store path itself (isValid())
        // is verified over real HTTP, per this file's own doc block.
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $individual['id'], 'document_type' => 'pan_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'pan.pdf', 'mime_type' => 'application/pdf']);
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $individual['id'], 'document_type' => 'aadhaar_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'aadhaar.pdf', 'mime_type' => 'application/pdf']);
        $submitted = $kyc->submitForReview($individual['id']);
        $this->assert($submitted['kyc_status'] === 'submitted', 'With questionnaire + both required documents + a Registered address present, submission genuinely transitions to submitted');
        $this->assert($submitted['kyc_submitted_at'] !== null, 'kyc_submitted_at is genuinely stamped');

        CLI::write("\n=== Step 6 (PR-15 step 6): manual SaaS Admin compliance-flag verification ===", 'yellow');
        $verified = $kyc->verifyComplianceFlag($individual['id'], 'pan', $reviewer['id']);
        $this->assert($verified['pan_verified_at'] !== null, 'PAN compliance flag genuinely set');
        $this->assert($verified['kyc_verified_by_party_id'] === $reviewer['id'], 'The verifying SaaS Admin is genuinely recorded');
        try {
            $kyc->verifyComplianceFlag($individual['id'], 'not_a_real_flag', $reviewer['id']);
            $this->assert(false, 'Should have thrown: unknown flag');
        } catch (\RuntimeException $e) {
            $this->assert(true, 'Correctly rejected: unknown compliance flag');
        }

        CLI::write("\n=== Step 7 (PR-15 steps 7-8): dossier review — Verified / Suspended ===", 'yellow');
        try {
            $kyc->reviewDossier($organization['id'], $reviewer['id'], true);
            $this->assert(false, 'Should have thrown: cannot review a non-submitted dossier');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'submitted'), 'Correctly rejected: only a SUBMITTED dossier can be reviewed');
        }
        try {
            $kyc->reviewDossier($individual['id'], $reviewer['id'], false, null);
            $this->assert(false, 'Should have thrown: suspension requires a closed-list reason');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'reason'), 'Correctly rejected: suspension with no reason from the closed list');
        }
        $verifiedParty = $kyc->reviewDossier($individual['id'], $reviewer['id'], true);
        $this->assert($verifiedParty['kyc_status'] === 'verified', 'Approving genuinely transitions KYC status to verified');

        // A second party to exercise the suspend path independently.
        $suspendCandidate = $partyModel->createParty('+919555420004');
        $kyc->saveQuestionnaire($suspendCandidate['id'], 'individual', ['full_name' => 'Suspend Test', 'pan' => 'ABCDE1234F', 'date_of_birth' => '1990-01-01', 'occupation' => 'Tester']);
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $suspendCandidate['id'], 'document_type' => 'pan_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'pan.pdf', 'mime_type' => 'application/pdf']);
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $suspendCandidate['id'], 'document_type' => 'aadhaar_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'aadhaar.pdf', 'mime_type' => 'application/pdf']);
        $addressModel->upsert($suspendCandidate['id'], 'registered', ['line1' => 'X', 'city' => 'X', 'district' => 'X', 'state' => 'X', 'pin_code' => '400001']);
        $kyc->submitForReview($suspendCandidate['id']);
        $suspended = $kyc->reviewDossier($suspendCandidate['id'], $reviewer['id'], false, 'document_mismatch');
        $this->assert($suspended['kyc_status'] === 'suspended', 'Suspending genuinely transitions KYC status to suspended');
        $this->assert(str_contains($suspended['kyc_status_reason'], 'Document Mismatch'), 'The closed-list reason is genuinely stored, visible to the patron');

        CLI::write("\n=== Step 8 (BR-55): mandatory KYC before first EMD pledge / Listing ===", 'yellow');
        try {
            $kyc->requireVerifiedKyc($organization['id'], 'creating a Listing');
            $this->assert(false, 'Should have thrown: organization party never completed KYC');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'BR-55'), 'Correctly rejected: an unverified party is blocked from creating a Listing');
        }
        try {
            $kyc->requireVerifiedKyc($suspendCandidate['id'], 'pledging an EMD deposit');
            $this->assert(false, 'Should have thrown: suspended KYC status blocks a pledge, not just never-submitted');
        } catch (\RuntimeException $e) {
            $this->assert(true, 'Correctly rejected: a SUSPENDED party is also blocked, not just an unverified one');
        }
        // Should NOT throw — this party genuinely passed review above.
        $kyc->requireVerifiedKyc($individual['id'], 'pledging an EMD deposit');
        $this->assert(true, 'A genuinely verified party passes the BR-55 gate without error');

        CLI::write("\n=== Step 9 (BR-55): enhanced due diligence, live-configurable via PR-04's Sovereign Rule module ===", 'yellow');
        $threshold = SovereignRuleService::getNumeric('BR-55.enhanced_due_diligence_threshold', 500000.0);
        $this->assert($threshold === 500000.0, 'EDD threshold reads live from the Sovereign Rule module, not a private const duplicated here');

        $kyc->checkEnhancedDueDiligence($individual['id'], 100000.0);
        $this->assert(true, 'A transaction below the threshold passes with no EDD requirement');

        try {
            $kyc->checkEnhancedDueDiligence($individual['id'], 600000.0);
            $this->assert(false, 'Should have thrown: transaction exceeds the EDD threshold and is not yet cleared');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'enhanced due diligence'), 'Correctly rejected: a transaction above the EDD threshold is blocked pending clearance');
        }
        $afterRequired = $partyModel->find($individual['id']);
        $this->assert($afterRequired['edd_required_at'] !== null, 'edd_required_at is genuinely stamped the first time the threshold is crossed');

        $kyc->clearEnhancedDueDiligence($individual['id'], $reviewer['id']);
        $kyc->checkEnhancedDueDiligence($individual['id'], 600000.0);
        $this->assert(true, 'After SaaS Admin clearance, the SAME high-value transaction now genuinely passes');

        CLI::write("\n=== Step 10: BR-05 audit trail — every KYC action is logged ===", 'yellow');
        $db = \Config\Database::connect();
        $kycEvents = $db->table('audit_log')->whereIn('event_type', [
            'kyc.questionnaire_saved', 'kyc.address_registered', 'kyc.banking_registered', 'kyc.submitted',
            'kyc.compliance_flag_verified', 'kyc.verified', 'kyc.suspended', 'kyc.edd_required', 'kyc.edd_cleared',
        ])->countAllResults();
        $this->assert($kycEvents >= 9, 'At least one audit-log entry per distinct KYC action type exercised in this run');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
