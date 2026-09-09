<?php

namespace App\Controllers;

use App\Libraries\ListingLifecycleService;
use App\Libraries\UserAuthContext;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;

class SaleEventController extends BaseController
{
    private ListingLifecycleService $lifecycle;
    private ListingModel $listingModel;
    private SaleEventModel $saleEventModel;
    private BidModel $bidModel;

    public function __construct()
    {
        $this->lifecycle = new ListingLifecycleService();
        $this->listingModel = new ListingModel();
        $this->saleEventModel = new SaleEventModel();
        $this->bidModel = new BidModel();
    }

    // BR-12: attach a Sale Event to an approved (upcoming) listing —
    // Easy Auction (reserve_value) or Buy-Now (expected_value).
    public function createSubmit(string $listingId)
    {
        $sellerId = UserAuthContext::partyId();

        // BR-15: "structurally barred from... attaching Sale Events...
        // under any circumstance."
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($sellerId)) {
            return $this->jsonError(403, 'br15_super_admin_barred', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never attach a Sale Event.');
        }

        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'upcoming') {
            return $this->jsonError(422, 'listing_not_upcoming', 'Listing must be approved (upcoming) before attaching a sale event.');
        }

        $format = $this->input('sale_format') ?: 'easy';
        $ernPrefix = match ($format) {
            'buy_now' => 'BN-',
            'express' => 'EX-',
            default => 'EH-',
        };
        $ern = $ernPrefix . strtoupper(substr($listingId, 0, 8));

        $data = [
            'listing_id' => $listingId,
            'tenant_id' => $listing['tenant_id'],
            'ern' => $ern,
            'sale_format' => $format,
        ];

        if ($format === 'buy_now') {
            $data['expected_value'] = $this->input('expected_value');
        } else {
            $data['reserve_value'] = $this->input('reserve_value');
            $data['result_mode'] = 'instant_close';
        }

        // BR-12: Easy Auction runs on a seller-set schedule (start/end),
        // not an automatic system timer the way Express does — the
        // seller chooses when their own auction runs.
        if ($format === 'easy') {
            $startAt = $this->input('scheduled_start_at');
            $endAt = $this->input('scheduled_end_at');
            if (!$startAt || !$endAt) {
                return $this->jsonError(422, 'missing_schedule', 'Easy Auction requires both a start and end date/time.');
            }
            if (strtotime($endAt) <= strtotime($startAt)) {
                return $this->jsonError(422, 'invalid_schedule', 'The end time must be after the start time.');
            }
            $data['scheduled_start_at'] = date('Y-m-d H:i:s', strtotime($startAt));
            $data['scheduled_end_at'] = date('Y-m-d H:i:s', strtotime($endAt));

            // Optional buyer inspection window, Easy Auction only.
            $inspectionStart = $this->input('inspection_window_start');
            $inspectionEnd = $this->input('inspection_window_end');
            if ($inspectionStart && $inspectionEnd) {
                if (strtotime($inspectionEnd) <= strtotime($inspectionStart)) {
                    return $this->jsonError(422, 'invalid_inspection_window', 'The inspection window end must be after its start.');
                }
                $data['inspection_window_start'] = date('Y-m-d H:i:s', strtotime($inspectionStart));
                $data['inspection_window_end'] = date('Y-m-d H:i:s', strtotime($inspectionEnd));
            }

            // D-34 correction: seller selects 2-5% of Reserve Value as
            // the bid increment.
            $incrementPercent = (float) ($this->input('increment_percent') ?: 2);
            if ($incrementPercent < 2 || $incrementPercent > 5) {
                return $this->jsonError(422, 'invalid_increment', 'Bid increment must be between 2% and 5% of Reserve Value.');
            }
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * ($incrementPercent / 100), 2);
        }

        // D-34 correction: Express gets an automatic 2% increment plus
        // the 10-minute halving window.
        if ($format === 'express') {
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * 0.02, 2);
        }

        // BR-12/BR-14: Tender is restricted exclusively to Company Shop
        if ($format === 'tender') {
            try {
                (new \App\Libraries\TenderService())->validateCompanyShopOnly($listing['tenant_id']);
            } catch (\RuntimeException $e) {
                return $this->jsonError(403, 'tender_not_allowed', $e->getMessage());
            }

            $startAt = $this->input('scheduled_start_at');
            $endAt = $this->input('scheduled_end_at');
            if (!$startAt || !$endAt) {
                return $this->jsonError(422, 'missing_schedule', 'Tender requires both a start and end date/time.');
            }
            if (strtotime($endAt) <= strtotime($startAt)) {
                return $this->jsonError(422, 'invalid_schedule', 'The end time must be after the start time.');
            }
            $data['scheduled_start_at'] = date('Y-m-d H:i:s', strtotime($startAt));
            $data['scheduled_end_at'] = date('Y-m-d H:i:s', strtotime($endAt));

            // Seller's total flexibility — a direct rupee amount, not a
            // percentage (confirmed distinct from Easy/Express).
            $data['bid_increment_amount'] = (float) ($this->input('bid_increment_amount') ?: 0) ?: null;

            // The two confirmed, distinct windows.
            $data['dynamic_time_trigger_minutes'] = 10;  // increment halving
            $data['anti_snipe_trigger_minutes'] = 2;      // clock extension
            $data['dynamic_time_extension_minutes'] = 2;
        }

        // BR-38: a seller in flush-out state may only list within their
        // permitted value range — the mirrored seller-side ladder.
        $sellerValue = $data['reserve_value'] ?? $data['expected_value'] ?? null;
        if ($sellerValue !== null) {
            $tenant = (new \App\Models\TenantModel())->find($listing['tenant_id']);
            $ceiling = (new \App\Libraries\RatingService())->getTransactionCeiling($sellerId, 'seller_star_rating', $tenant);
            if ($ceiling !== null && (float) $sellerValue > $ceiling) {
                return $this->jsonError(403, 'br38_ceiling_exceeded',
                    'BR-38: your seller account is currently restricted to listings valued up to ₹' . number_format($ceiling, 2) . '.'
                );
            }
        }

        // BR-47: Related Auctions is available on Buy-Now, Easy, and
        // Tender only — Express's fast/no-review nature doesn't suit
        // grouped browsing. Also: every item in a group must share the
        // same format throughout.
        if (!empty($listing['related_group_id'])) {
            if ($format === 'express') {
                return $this->jsonError(422, 'br47_express_not_grouped',
                    'BR-47: Related Auctions is not available on Express — its fast, no-review format doesn\'t suit grouped browsing.'
                );
            }
            $otherGroupMember = $this->listingModel
                ->where('related_group_id', $listing['related_group_id'])
                ->where('id !=', $listingId)
                ->first();
            if ($otherGroupMember) {
                $otherSaleEvent = $this->saleEventModel->where('listing_id', $otherGroupMember['id'])->first();
                if ($otherSaleEvent && $otherSaleEvent['sale_format'] !== $format) {
                    return $this->jsonError(422, 'br47_format_mismatch',
                        "BR-47: every item in this related group must share the same sale format — the group is already using " . strtoupper($otherSaleEvent['sale_format']) . '.'
                    );
                }
            }
        }

        // BR-31/32 (D-87/D-88): Fee Payer Election -- Buyer-Pays (default)
        // or Seller-Pays. Not offered on Tender. Seller-Pays is
        // restricted to non-CoCo-Starter tenants.
        if ($format !== 'tender') {
            $feePayer = $this->input('fee_payer') === 'seller_pays' ? 'seller_pays' : 'buyer_pays';
            if ($feePayer === 'seller_pays') {
                $tenant = (new \App\Models\TenantModel())->find($listing['tenant_id']);
                if ($tenant['subscription_tier'] === 'coco_starter') {
                    return $this->jsonError(403, 'br32_seller_pays_unavailable',
                        'BR-32: Seller-Pays is not available on a CoCo Starter TSX -- upgrade to a paid TSX tier to offer Seller-Pays on this Trading Session.'
                    );
                }
            }
            $data['fee_payer'] = $feePayer;
        }

        $saleEvent = $this->saleEventModel->createSaleEvent($data);

        // BR-13: listing moves to active once a sale system is attached
        $this->listingModel->transitionStatus($listingId, 'active');

        return $this->apiResponse(['saleEvent' => $saleEvent], null, 201);
    }

    // BR-09: Tenant Admin approval — access enforced by the
    // jwtTenantAdmin route filter (resource type 'saleEvent').
    public function approve(string $saleEventId)
    {
        try {
            $this->lifecycle->approveSaleEvent($saleEventId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'approve_failed', $e->getMessage());
        }
        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }

    // BR-57: mandatory for Express specifically, since no inspection
    // window exists — the seller's only accountability mechanism.
    public function defectDisclosureSubmit(string $saleEventId)
    {
        $sellerId = UserAuthContext::partyId();

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'express') {
            return $this->jsonError(404, 'not_found', 'Sale event not found.');
        }

        $this->saleEventModel->update($saleEventId, [
            'defect_disclosure_known_damage' => $this->input('known_damage') ?: 'None disclosed.',
            'defect_disclosure_missing_components' => $this->input('missing_components') ?: 'None disclosed.',
            'defect_disclosure_nonfunctional_aspects' => $this->input('nonfunctional_aspects') ?: 'None disclosed.',
            'defect_disclosure_completed_at' => date('Y-m-d H:i:s'),
        ]);

        (new \App\Libraries\AuditLogService())->log('sale_event.defect_disclosure_completed', $sellerId, [
            'saleEventId' => $saleEventId,
        ]);

        return $this->apiResponse([
            'saleEvent' => $this->saleEventModel->find($saleEventId),
            'message' => 'Defect disclosure completed — the listing can now be approved.',
        ]);
    }

    // ⚠️ DEV-ONLY: BR-14's real 60-minute grace window can't be waited out
    // in a live demo/test session — this forces the freeze immediately.
    // Must not exist in a production build; the real transition is
    // time-based via a scheduled job. Gated behind jwtTenantAdmin,
    // consistent with other administrative actions, though the
    // underlying time-skip mechanism itself remains a stand-in.
    public function devForceFreeze(string $saleEventId)
    {
        $this->saleEventModel->update($saleEventId, [
            'grace_period_ends_at' => date('Y-m-d H:i:s', time() - 1),
        ]);
        $this->lifecycle->freezeAfterGrace($saleEventId);
        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }

    // BR-14: withdraws all bids, releases all EMD, mandatory audited
    // reason. Access is enforced by the jwtTenantAdmin route filter.
    public function emergencyStop(string $saleEventId)
    {
        $reason = $this->input('reason');
        if (!$reason) {
            return $this->jsonError(422, 'reason_required', 'BR-14: a reason is required to emergency-stop a sale event.');
        }
        try {
            $this->lifecycle->emergencyStop($saleEventId, $reason, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'emergency_stop_failed', $e->getMessage());
        }
        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }
}
