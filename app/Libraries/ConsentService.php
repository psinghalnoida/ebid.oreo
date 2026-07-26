<?php

namespace App\Libraries;

class ConsentService
{
    private const TERMS_VERSION = '2026-07-v1';

    public function recordEmdPledgeConsent(string $partyId, string $relatedReferenceId, float $amount, string $forfeitureConsequenceText, ?string $ipAddress = null): array
    {
        $consentText = "I understand that pledging ₹" . number_format($amount, 2)
            . " as Earnest Money Deposit for this bid means: {$forfeitureConsequenceText}";

        return $this->record($partyId, 'emd_pledge', $relatedReferenceId, $consentText, $ipAddress);
    }

    public function recordRegistrationConsent(string $partyId, ?string $ipAddress = null): array
    {
        return $this->record($partyId, 'registration_terms', null,
            'I accept the Terms of Usage and Privacy Policy.', $ipAddress);
    }

    private function record(string $partyId, string $consentType, ?string $relatedReferenceId, string $consentText, ?string $ipAddress): array
    {
        $db = \Config\Database::connect();
        $id = Uuid::v4();
        $db->table('consent_event')->insert([
            'id' => $id,
            'party_id' => $partyId,
            'consent_type' => $consentType,
            'terms_version' => self::TERMS_VERSION,
            'related_reference_id' => $relatedReferenceId,
            'consent_text_shown' => $consentText,
            'ip_address' => $ipAddress,
        ]);

        (new AuditLogService())->log('consent.recorded', $partyId, [
            'consentType' => $consentType, 'termsVersion' => self::TERMS_VERSION, 'relatedReferenceId' => $relatedReferenceId,
        ]);

        return $db->table('consent_event')->where('id', $id)->get()->getRowArray();
    }

    public function hasEmdPledgeConsent(string $partyId, string $relatedReferenceId): bool
    {
        $db = \Config\Database::connect();
        return $db->table('consent_event')
            ->where('party_id', $partyId)
            ->where('consent_type', 'emd_pledge')
            ->where('related_reference_id', $relatedReferenceId)
            ->countAllResults() > 0;
    }
}
