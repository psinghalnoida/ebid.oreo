<?php

namespace App\Controllers;

use App\Libraries\OfferService;
use App\Libraries\EmdService;
use App\Libraries\UserAuthContext;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\ListingModel;

class OfferController extends BaseController
{
    private OfferService $offers;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->offers = new OfferService();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->listingModel = new ListingModel();
    }

    public function devFundEmd(string $saleEventId)
    {
        $buyerId = UserAuthContext::partyId();

        // BR-15: structurally barred from pledging under any
        // circumstance — checked before the EMD is ever held, not just
        // at the later offer.
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($buyerId)) {
            return $this->jsonError(403, 'br15_super_admin_barred', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never pledge an EMD deposit.');
        }

        // BR-55: full KYC verification is mandatory before a User's
        // first EMD pledge, with no lower-value exemption.
        try {
            (new \App\Libraries\KycService())->requireVerifiedKyc($buyerId, 'pledging an EMD deposit');
        } catch (\RuntimeException $e) {
            return $this->jsonError(403, 'kyc_required', $e->getMessage());
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $baseline = EmdService::calculateBaselineEmd('buy_now', (float) $saleEvent['expected_value'], null);

        $existing = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerId);
        if (!$existing || $existing['status'] !== 'held') {
            // BR-55: enhanced due diligence above the live threshold —
            // gates this specific pledge, not the whole account.
            try {
                (new \App\Libraries\KycService())->checkEnhancedDueDiligence($buyerId, $baseline);
            } catch (\RuntimeException $e) {
                return $this->jsonError(403, 'edd_required', $e->getMessage());
            }
            $this->emdHoldModel->createHold($saleEventId, $buyerId, 'van', $baseline);
            (new \App\Libraries\AuditLogService())->log('emd.held', $buyerId, [
                'saleEventId' => $saleEventId, 'amount' => $baseline, 'channel' => 'van',
            ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        }

        return $this->apiResponse(['emdHold' => $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerId)]);
    }

    public function submit(string $saleEventId)
    {
        $buyerId = UserAuthContext::partyId();

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $amount = (float) $this->input('amount');

        try {
            $offer = $this->offers->submitOffer($saleEventId, $buyerId, $amount);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'offer_failed', $e->getMessage());
        }

        return $this->apiResponse(['offer' => $offer], null, 201);
    }

    public function withdraw(string $offerId)
    {
        $reason = $this->input('reason') ?: 'Buyer withdrew';

        try {
            $offer = $this->offers->withdrawOffer($offerId, $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'withdraw_failed', $e->getMessage());
        }

        return $this->apiResponse(['offer' => $offer]);
    }

    // BR-09/BR-42: this decision belongs to the SELLER specifically, not
    // the Tenant Admin (unlike listing/sale-event approval elsewhere) —
    // enforced here by re-checking listing ownership against the caller.
    public function accept(string $saleEventId, string $offerId)
    {
        $sellerId = UserAuthContext::partyId();

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            return $this->jsonError(404, 'not_found', 'Sale event not found.');
        }
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if (!$listing || $listing['seller_party_id'] !== $sellerId) {
            return $this->jsonError(403, 'forbidden', 'BR-42: only the listing\'s seller may accept an offer on it.');
        }

        $reason = $this->input('reason') ?: null;

        try {
            $offer = $this->offers->acceptOffer($saleEventId, $offerId, $reason, $sellerId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'accept_failed', $e->getMessage());
        }

        return $this->apiResponse(['offer' => $offer]);
    }
}
