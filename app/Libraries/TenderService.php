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
        return $this->eligibilityModel->grantEligibility($saleEventId, $partyId, $source, $sellerId);
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
        return $this->documentModel->createDocument([
            'sale_event_id' => $saleEventId, 'uploaded_by_party_id' => $sellerId,
            'document_type' => $documentType, 'title' => $title,
            'file_path' => $filePath, 'description_text' => $descriptionText,
        ]);
    }

    public function getDocuments(string $saleEventId): array
    {
        return $this->documentModel->findForSaleEvent($saleEventId);
    }

    public function generateStakeholderLink(string $saleEventId, string $creatorPartyId, ?string $label = null): array
    {
        $this->requireTenderEvent($saleEventId);
        return $this->tokenModel->createToken($saleEventId, $creatorPartyId, $label);
    }

    public function resolveStakeholderToken(string $token): array
    {
        $tokenRow = $this->tokenModel->findByToken($token);
        if (!$tokenRow) {
            throw new \RuntimeException('This stakeholder link is invalid or has been revoked.');
        }
        return $this->saleEventModel->find($tokenRow['sale_event_id']);
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
