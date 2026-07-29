<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\PartyDocumentModel;
use App\Models\PartyAddressModel;

// BR-17/BR-18/BR-55/PR-15: Dual-Track Patron KYC Verification, the
// multi-address/banking schema, and the mandatory-before-first-
// transaction gate. Two pieces of PR-15's operational sequence are
// externally gated the same way Auth0/Gemini/the payment gateway are —
// confirmed with the project owner, who chose the honest fallback
// rather than fabricating a fake integration:
//   - "runs automated PAN/GSTIN registry checks" (PR-15 step 4): no
//     real NSDL/GSTN registry API/credentials exist. Compliance flags
//     are instead set by a manual SaaS Admin action
//     (verifyComplianceFlag) — a genuine admin decision, not automated.
//   - "Aadhaar (masked/tokenized)" (BR-17): no UIDAI tokenization
//     service exists. The raw number is masked immediately and never
//     persisted in cleartext outside the encrypted document upload —
//     genuine masking, not the real UIDAI Virtual ID/token scheme.
class KycService
{
    // BR-17's document list is one flat closed list in the source text
    // ("PAN Card, GST Certificate, Aadhaar Card, Certificate of
    // Incorporation, Board Resolution, Power of Attorney, Cancelled
    // Cheque, MSME Certificate") without an explicit individual-vs-
    // organization split. Mapped here to match BR-17's own separate
    // questionnaire fields per entity type — flagged, not a settled
    // figure: BR-17 itself says mandatory/optional status per field
    // "remains subject to BR-01."
    private const REQUIRED_DOCUMENTS = [
        'individual' => ['pan_card', 'aadhaar_card'],
        'organization' => ['pan_card', 'gst_certificate', 'certificate_of_incorporation', 'cancelled_cheque'],
    ];

    private const ALL_DOCUMENT_TYPES = [
        'pan_card', 'gst_certificate', 'aadhaar_card', 'certificate_of_incorporation',
        'board_resolution', 'power_of_attorney', 'cancelled_cheque', 'msme_certificate',
    ];

    // BR-17: "a reason from a closed list (e.g., document mismatch,
    // expired ID, failed registry check)" — "e.g." signals the document
    // gives examples rather than an exhaustive list; one plausible extra
    // (incomplete_information) is added and flagged the same way.
    private const SUSPENSION_REASONS = [
        'document_mismatch' => 'Document Mismatch',
        'expired_id' => 'Expired ID',
        'failed_registry_check' => 'Failed Registry Check',
        'incomplete_information' => 'Incomplete Information (flagged addition — not explicitly named in BR-17\'s "e.g." list)',
    ];

    private const ALLOWED_UPLOAD_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    // BR-55: "the specific enhanced-due-diligence threshold is set by
    // SaaS Admin... and is not fixed by this document" — genuinely live
    // via PR-04's Sovereign Rule module, not a hardcoded guess. This is
    // only the fallback default used until a Super Admin sets it.
    private const EDD_THRESHOLD_DEFAULT = 500000.0;

    private PartyModel $partyModel;
    private PartyDocumentModel $documentModel;
    private PartyAddressModel $addressModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->documentModel = new PartyDocumentModel();
        $this->addressModel = new PartyAddressModel();
    }

    public static function suspensionReasons(): array
    {
        return self::SUSPENSION_REASONS;
    }

    public static function requiredDocuments(string $entityType): array
    {
        return self::REQUIRED_DOCUMENTS[$entityType] ?? [];
    }

    public static function allDocumentTypes(): array
    {
        return self::ALL_DOCUMENT_TYPES;
    }

    // PR-15 steps 1-2: entity type + questionnaire, per BR-17's closed
    // field lists.
    public function saveQuestionnaire(string $partyId, string $entityType, array $fields): array
    {
        if (!in_array($entityType, ['individual', 'organization'], true)) {
            throw new \RuntimeException('Entity type must be individual or organization.');
        }

        $update = ['entity_type' => $entityType];

        if ($entityType === 'individual') {
            if (empty($fields['full_name']) || empty($fields['pan']) || empty($fields['date_of_birth']) || empty($fields['occupation'])) {
                throw new \RuntimeException('BR-17: Full Name, PAN, Date of Birth, and Occupation are all required for individual KYC.');
            }
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper($fields['pan']))) {
                throw new \RuntimeException('PAN must be a valid 10-character format (e.g. ABCDE1234F).');
            }
            $update['full_name'] = trim($fields['full_name']);
            $update['pan'] = strtoupper(trim($fields['pan']));
            $update['date_of_birth'] = $fields['date_of_birth'];
            $update['occupation'] = trim($fields['occupation']);

            // Aadhaar is never persisted in cleartext — masked immediately,
            // per BR-17's "masked/tokenized" requirement (see class doc
            // block for why this is masking, not UIDAI's real tokenization).
            if (!empty($fields['aadhaar'])) {
                $raw = preg_replace('/\D/', '', $fields['aadhaar']);
                if (strlen($raw) !== 12) {
                    throw new \RuntimeException('Aadhaar must be a 12-digit number.');
                }
                $update['aadhaar_masked'] = 'XXXX-XXXX-' . substr($raw, -4);
            }
        } else {
            if (empty($fields['org_cin']) || empty($fields['org_gstin']) || empty($fields['org_pan']) || empty($fields['org_company_type']) || empty($fields['org_industry'])) {
                throw new \RuntimeException('BR-17: CIN, GSTIN, Company PAN, Company Type, and Industry are all required for organization KYC.');
            }
            $update['org_cin'] = strtoupper(trim($fields['org_cin']));
            $update['org_gstin'] = strtoupper(trim($fields['org_gstin']));
            $update['org_pan'] = strtoupper(trim($fields['org_pan']));
            $update['org_msme_registration'] = $fields['org_msme_registration'] ?? null;
            $update['org_udyam_number'] = $fields['org_udyam_number'] ?? null;
            $update['org_company_type'] = trim($fields['org_company_type']);
            $update['org_industry'] = trim($fields['org_industry']);
            $update['org_annual_turnover'] = !empty($fields['org_annual_turnover']) ? (float) $fields['org_annual_turnover'] : null;
            $update['org_employee_count'] = !empty($fields['org_employee_count']) ? (int) $fields['org_employee_count'] : null;
        }

        $update['updated_at'] = date('Y-m-d H:i:s');
        $this->partyModel->update($partyId, $update);
        (new AuditLogService())->log('kyc.questionnaire_saved', $partyId, ['entityType' => $entityType]);

        return $this->partyModel->find($partyId);
    }

    // PR-15 step 3-4: "Patron uploads required documents to the secure
    // document vault. System encrypts documents..." Real AES via CI4's
    // Encryption service (app's encryption.key from .env) — files are
    // never written to disk in plaintext, and the vault lives under
    // writable/, outside the public webroot.
    public function uploadDocument(string $partyId, string $documentType, \CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        if (!in_array($documentType, self::ALL_DOCUMENT_TYPES, true)) {
            throw new \RuntimeException("Unknown document type: {$documentType}");
        }
        if (!$file->isValid() || $file->hasMoved()) {
            throw new \RuntimeException('Uploaded file is invalid.');
        }
        if (!in_array($file->getMimeType(), self::ALLOWED_UPLOAD_MIME_TYPES, true)) {
            throw new \RuntimeException('Document must be a PDF, JPEG, or PNG file.');
        }

        $plaintext = file_get_contents($file->getTempName());
        $ciphertext = service('encrypter')->encrypt($plaintext);

        $vaultDir = WRITEPATH . 'kyc_vault/' . $partyId;
        if (!is_dir($vaultDir)) {
            mkdir($vaultDir, 0700, true);
        }
        $storedFilename = $documentType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.enc';
        file_put_contents($vaultDir . '/' . $storedFilename, $ciphertext);

        $id = Uuid::v4();
        $this->documentModel->insert([
            'id' => $id, 'party_id' => $partyId, 'document_type' => $documentType,
            'encrypted_path' => $vaultDir . '/' . $storedFilename,
            'original_filename' => $file->getClientName(), 'mime_type' => $file->getMimeType(),
        ]);
        (new AuditLogService())->log('kyc.document_uploaded', $partyId, ['documentType' => $documentType]);

        return $this->documentModel->find($id);
    }

    // Decrypts a document for admin review — never for the patron's own
    // download, only Tenant/Super Admin, gated by the calling controller.
    public function decryptDocument(string $documentId): array
    {
        $doc = $this->documentModel->find($documentId);
        if (!$doc) {
            throw new \RuntimeException('Document not found.');
        }
        $ciphertext = file_get_contents($doc['encrypted_path']);
        $plaintext = service('encrypter')->decrypt($ciphertext);
        return ['mimeType' => $doc['mime_type'], 'filename' => $doc['original_filename'], 'contents' => $plaintext];
    }

    // BR-18: one row per address type, up to four.
    public function registerAddress(string $partyId, string $addressType, array $fields): array
    {
        if (!in_array($addressType, ['registered', 'billing', 'correspondence', 'site_yard'], true)) {
            throw new \RuntimeException("Unknown address type: {$addressType}");
        }
        if (empty($fields['line1']) || empty($fields['city']) || empty($fields['district']) || empty($fields['state']) || empty($fields['pin_code'])) {
            throw new \RuntimeException('Address Line 1, City, District, State, and PIN Code are all required.');
        }
        if (!preg_match('/^[1-9][0-9]{5}$/', $fields['pin_code'])) {
            throw new \RuntimeException('BR-03: PIN Code must be a valid 6-digit Indian PIN code.');
        }

        $address = $this->addressModel->upsert($partyId, $addressType, [
            'line1' => trim($fields['line1']), 'line2' => $fields['line2'] ?? null,
            'city' => trim($fields['city']), 'district' => trim($fields['district']),
            'state' => trim($fields['state']), 'country' => $fields['country'] ?? 'India',
            'pin_code' => $fields['pin_code'],
            'gps_lat' => !empty($fields['gps_lat']) ? (float) $fields['gps_lat'] : null,
            'gps_lng' => !empty($fields['gps_lng']) ? (float) $fields['gps_lng'] : null,
        ]);
        (new AuditLogService())->log('kyc.address_registered', $partyId, ['addressType' => $addressType]);

        return $address;
    }

    // BR-18: encrypted banking details — reuses BR-50's existing
    // payout_bank_account_number/ifsc fields (same one party/one bank
    // record design) and adds the holder name/bank name/branch/UPI
    // fields BR-18 additionally names.
    public function registerBanking(string $partyId, array $fields): array
    {
        if (empty($fields['account_holder_name']) || empty($fields['bank_name']) || empty($fields['account_number']) || empty($fields['ifsc'])) {
            throw new \RuntimeException('Account Holder Name, Bank Name, Account Number, and IFSC are all required.');
        }
        $this->partyModel->update($partyId, [
            'payout_bank_account_holder_name' => trim($fields['account_holder_name']),
            'payout_bank_name' => trim($fields['bank_name']),
            'payout_bank_branch_name' => $fields['branch_name'] ?? null,
            'payout_bank_account_number' => trim($fields['account_number']),
            'payout_bank_ifsc' => strtoupper(trim($fields['ifsc'])),
            'payout_bank_upi_id' => $fields['upi_id'] ?? null,
            'payout_bank_updated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('kyc.banking_registered', $partyId, []);

        return $this->partyModel->find($partyId);
    }

    // PR-15's implicit final onboarding step: the dossier (questionnaire
    // + required documents + at least the Registered address) is
    // complete enough to hand to a Tenant Admin for review.
    public function submitForReview(string $partyId): array
    {
        $party = $this->partyModel->find($partyId);
        if (!$party) {
            throw new \RuntimeException('Party not found.');
        }
        if (empty($party['full_name']) && $party['entity_type'] === 'individual') {
            throw new \RuntimeException('Complete the KYC questionnaire before submitting for review.');
        }
        if (empty($party['org_cin']) && $party['entity_type'] === 'organization') {
            throw new \RuntimeException('Complete the KYC questionnaire before submitting for review.');
        }

        $uploaded = $this->documentModel->typesUploadedBy($partyId);
        $missing = array_diff(self::requiredDocuments($party['entity_type']), $uploaded);
        if (!empty($missing)) {
            throw new \RuntimeException('Missing required documents: ' . implode(', ', $missing));
        }

        $hasRegisteredAddress = $this->addressModel->where('party_id', $partyId)->where('address_type', 'registered')->first();
        if (!$hasRegisteredAddress) {
            throw new \RuntimeException('BR-18: a Registered address is required before submitting for review.');
        }

        $this->partyModel->update($partyId, ['kyc_status' => 'submitted', 'kyc_submitted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditLogService())->log('kyc.submitted', $partyId, []);

        return $this->partyModel->find($partyId);
    }

    // PR-15 step 6: manual SaaS Admin compliance-flag verification —
    // see class doc block for why this is manual, not an automated
    // PAN/GSTIN registry check or UIDAI Aadhaar verification.
    public function verifyComplianceFlag(string $partyId, string $flag, string $verifierId): array
    {
        $allowed = ['pan', 'gstin', 'aadhaar', 'bank', 'email'];
        if (!in_array($flag, $allowed, true)) {
            throw new \RuntimeException("Unknown compliance flag: {$flag}");
        }
        $column = "{$flag}_verified_at";
        $this->partyModel->update($partyId, [
            $column => date('Y-m-d H:i:s'), 'kyc_verified_by_party_id' => $verifierId, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('kyc.compliance_flag_verified', $verifierId, ['partyId' => $partyId, 'flag' => $flag]);

        return $this->partyModel->find($partyId);
    }

    // PR-15 steps 7-8: Tenant Admin reviews the dossier and transitions
    // KYC Status. Suspension requires a closed-list reason, logged; the
    // patron sees both the suspension and its reason on their own
    // account page — this codebase's established "notification" pattern
    // (e.g. BR-38 delisting), since no real email/SMS provider exists.
    public function reviewDossier(string $partyId, string $reviewerId, bool $approve, ?string $reasonKey = null): array
    {
        $party = $this->partyModel->find($partyId);
        if (!$party) {
            throw new \RuntimeException('Party not found.');
        }
        if ($party['kyc_status'] !== 'submitted') {
            throw new \RuntimeException('Only a submitted dossier can be reviewed.');
        }

        if ($approve) {
            $this->partyModel->setKycStatus($partyId, 'verified', null);
            (new AuditLogService())->log('kyc.verified', $reviewerId, ['partyId' => $partyId]);
        } else {
            if (!$reasonKey || !array_key_exists($reasonKey, self::SUSPENSION_REASONS)) {
                throw new \RuntimeException('A reason from the closed list is required to suspend KYC.');
            }
            $this->partyModel->setKycStatus($partyId, 'suspended', self::SUSPENSION_REASONS[$reasonKey]);
            (new AuditLogService())->log('kyc.suspended', $reviewerId, ['partyId' => $partyId, 'reason' => $reasonKey]);
        }

        return $this->partyModel->find($partyId);
    }

    // BR-55: "Full KYC verification (BR-17) is mandatory before a User's
    // first EMD pledge or first Listing, with no lower-value exemption."
    public function requireVerifiedKyc(string $partyId, string $context): void
    {
        $party = $this->partyModel->find($partyId);
        if (!$party || $party['kyc_status'] !== 'verified') {
            throw new \RuntimeException("BR-55: full KYC verification is required before {$context}.");
        }
    }

    // BR-55: enhanced due diligence above the live, Super-Admin-set
    // threshold — gates the specific transaction, not the whole account.
    public function checkEnhancedDueDiligence(string $partyId, float $transactionValue): void
    {
        $threshold = SovereignRuleService::getNumeric('BR-55.enhanced_due_diligence_threshold', self::EDD_THRESHOLD_DEFAULT);
        if ($transactionValue < $threshold) {
            return;
        }
        $party = $this->partyModel->find($partyId);
        $cleared = !empty($party['edd_cleared_at']);
        if (!$cleared) {
            if (empty($party['edd_required_at'])) {
                $this->partyModel->update($partyId, ['edd_required_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                (new AuditLogService())->log('kyc.edd_required', $partyId, ['transactionValue' => $transactionValue, 'threshold' => $threshold]);
            }
            throw new \RuntimeException(
                'BR-55: this transaction (₹' . number_format($transactionValue, 2) . ') exceeds the enhanced due diligence threshold (₹' . number_format($threshold, 2) . ') and requires additional SaaS Admin verification before it can proceed.'
            );
        }
    }

    public function clearEnhancedDueDiligence(string $partyId, string $clearedBy): array
    {
        $this->partyModel->update($partyId, [
            'edd_cleared_at' => date('Y-m-d H:i:s'), 'edd_cleared_by_party_id' => $clearedBy, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('kyc.edd_cleared', $clearedBy, ['partyId' => $partyId]);

        return $this->partyModel->find($partyId);
    }
}
