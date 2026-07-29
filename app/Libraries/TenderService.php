<?php

namespace App\Libraries;

use App\Models\SaleEventModel;
use App\Models\TenantModel;
use App\Models\TenderInterestModel;
use App\Models\TenderEligibilityModel;
use App\Models\TenderDocumentModel;
use App\Models\TenderStakeholderTokenModel;
use App\Models\ListingModel;

class TenderService
{
    private SaleEventModel $saleEventModel;
    private TenantModel $tenantModel;
    private TenderInterestModel $interestModel;
    private TenderEligibilityModel $eligibilityModel;
    private TenderDocumentModel $documentModel;
    private TenderStakeholderTokenModel $tokenModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
        $this->tenantModel = new TenantModel();
        $this->interestModel = new TenderInterestModel();
        $this->eligibilityModel = new TenderEligibilityModel();
        $this->documentModel = new TenderDocumentModel();
        $this->tokenModel = new TenderStakeholderTokenModel();
        $this->listingModel = new ListingModel();
    }

    public function validateCompanyShopOnly(string $tenantId): void
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant || $tenant['tenant_class'] !== 'company_shop') {
            throw new \RuntimeException('BR-12: Tender Auction is restricted exclusively to Company Shop tenants.');
        }
    }

    public function registerInterest(string $saleEventId, string $partyId): array
    {
        // BR-15: structurally barred from participating under any
        // circumstance — blocked at the earliest Tender touchpoint, not
        // just at the eventual bid.
        if ((new AuthorizationService())->isSuperAdmin($partyId)) {
            throw new \RuntimeException('BR-15: the Super Admin holds a non-participatory regulatory role and may never participate in a sale event.');
        }

        $this->requireTenderEvent($saleEventId);
        if ($this->interestModel->hasRegisteredInterest($saleEventId, $partyId)) {
            throw new \RuntimeException('You have already registered interest in this Tender.');
        }
        return $this->interestModel->registerInterest($saleEventId, $partyId);
    }

    public function grantEligibility(string $saleEventId, string $partyId, string $sellerId): array
    {
        $saleEvent = $this->requireTenderEvent($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if ($listing['seller_party_id'] !== $sellerId) {
            throw new \RuntimeException('Only the listing\'s seller may grant Tender eligibility.');
        }
        if ($this->eligibilityModel->isEligible($saleEventId, $partyId)) {
            throw new \RuntimeException('This party is already eligible for this Tender.');
        }

        $source = $this->interestModel->hasRegisteredInterest($saleEventId, $partyId) ? 'interest' : 'direct';
        $result = $this->eligibilityModel->grantEligibility($saleEventId, $partyId, $source, $sellerId);

        (new AuditLogService())->log('tender.eligibility_granted', $sellerId, [
            'saleEventId' => $saleEventId, 'grantedToPartyId' => $partyId, 'source' => $source,
        ]);

        return $result;
    }

    public function isEligible(string $saleEventId, string $partyId): bool
    {
        return $this->eligibilityModel->isEligible($saleEventId, $partyId);
    }

    public function publishDocument(string $saleEventId, string $sellerId, string $documentType, string $title, ?string $filePath, ?string $descriptionText): array
    {
        $saleEvent = $this->requireTenderEvent($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if ($listing['seller_party_id'] !== $sellerId) {
            throw new \RuntimeException('Only the listing\'s seller may publish Tender documents.');
        }
        $result = $this->documentModel->createDocument([
            'sale_event_id' => $saleEventId, 'uploaded_by_party_id' => $sellerId,
            'document_type' => $documentType, 'title' => $title,
            'file_path' => $filePath, 'description_text' => $descriptionText,
        ]);
        (new AuditLogService())->log('tender.document_published', $sellerId, [
            'saleEventId' => $saleEventId, 'documentType' => $documentType, 'title' => $title,
        ]);
        return $result;
    }

    public function getDocuments(string $saleEventId): array
    {
        return $this->documentModel->findForSaleEvent($saleEventId);
    }

    public function generateStakeholderLink(string $saleEventId, string $creatorPartyId, ?string $label = null): array
    {
        $this->requireTenderEvent($saleEventId);
        $token = $this->tokenModel->createToken($saleEventId, $creatorPartyId, $label);
        // BR-05: the token VALUE itself is deliberately excluded from the
        // payload — the audit log records that access was granted and by
        // whom, not a reusable credential that would let anyone reading
        // the log itself gain the same access being audited.
        (new AuditLogService())->log('tender.stakeholder_link_generated', $creatorPartyId, [
            'saleEventId' => $saleEventId, 'label' => $label,
        ]);
        return $token;
    }

    public function resolveStakeholderToken(string $token): array
    {
        $tokenRow = $this->tokenModel->findByToken($token);
        if (!$tokenRow) {
            throw new \RuntimeException('This stakeholder link is invalid or has been revoked.');
        }
        return $this->saleEventModel->find($tokenRow['sale_event_id']);
    }

    // BR: Tender's EMD is manual/offline, but always audited — either a
    // real amount + where it was paid, or an explicit reason why not.
    // Also creates the corresponding emd_hold record (amount possibly 0)
    // so the existing, already-tested BiddingService EMD gate continues
    // to work unmodified — eligibility to bid is gated on this hold
    // existing at all, not on its amount being non-zero.
    public function logManualEmd(string $saleEventId, string $partyId, ?float $amount, ?string $locationNote, ?string $noEmdReason, string $loggedByPartyId): array
    {
        $this->requireTenderEvent($saleEventId);
        if (!$this->eligibilityModel->isEligible($saleEventId, $partyId)) {
            throw new \RuntimeException('EMD can only be logged for an eligible participant.');
        }

        $logModel = new \App\Models\TenderEmdLogModel();
        $entry = $logModel->logEmd($saleEventId, $partyId, $amount, $locationNote, $noEmdReason, $loggedByPartyId);

        $emdHoldModel = new \App\Models\EmdHoldModel();
        $existing = $emdHoldModel->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$existing) {
            $emdHoldModel->createHold($saleEventId, $partyId, 'manual_offline', (float) $entry['amount']);
        }

        (new AuditLogService())->log('emd.held', $loggedByPartyId, [
            'saleEventId' => $saleEventId, 'forPartyId' => $partyId, 'amount' => (float) $entry['amount'],
            'channel' => 'manual_offline', 'locationNote' => $locationNote, 'noEmdReason' => $noEmdReason,
        ]);

        return $entry;
    }

    public function getEmdLog(string $saleEventId): array
    {
        return (new \App\Models\TenderEmdLogModel())->findForSaleEvent($saleEventId);
    }

    private function requireTenderEvent(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'tender') {
            throw new \RuntimeException('This is not a Tender Auction sale event.');
        }
        return $saleEvent;
    }
}
