<?php

namespace App\Controllers;

use App\Libraries\ListingLifecycleService;
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

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // BR-12: attach a Sale Event to an approved (upcoming) listing —
    // Easy Auction (reserve_value) or Buy-Now (expected_value).
    public function createSubmit(string $listingId)
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }

        // BR-15: "structurally barred from... attaching Sale Events...
        // under any circumstance."
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($sellerId)) {
            return redirect()->to("/listings/{$listingId}")->with('error', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never attach a Sale Event.');
        }

        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'upcoming') {
            return redirect()->to("/listings/{$listingId}")->with('error', 'Listing must be approved (upcoming) before attaching a sale event.');
        }

        $format = $this->request->getPost('sale_format') ?: 'easy';
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
            $data['expected_value'] = $this->request->getPost('expected_value');
        } else {
            $data['reserve_value'] = $this->request->getPost('reserve_value');
            $data['result_mode'] = 'instant_close';
        }

        // BR-12: Easy Auction runs on a seller-set schedule (start/end),
        // not an automatic system timer the way Express does — the
        // seller chooses when their own auction runs.
        if ($format === 'easy') {
            $startAt = $this->request->getPost('scheduled_start_at');
            $endAt = $this->request->getPost('scheduled_end_at');
            if (!$startAt || !$endAt) {
                return redirect()->to("/listings/{$listingId}")->with('error', 'Easy Auction requires both a start and end date/time.');
            }
            if (strtotime($endAt) <= strtotime($startAt)) {
                return redirect()->to("/listings/{$listingId}")->with('error', 'The end time must be after the start time.');
            }
            $data['scheduled_start_at'] = date('Y-m-d H:i:s', strtotime($startAt));
            $data['scheduled_end_at'] = date('Y-m-d H:i:s', strtotime($endAt));

            // D-34 correction: seller selects 2-5% of Reserve Value as
            // the bid increment — was missing entirely from the original
            // D-32 build.
            $incrementPercent = (float) ($this->request->getPost('increment_percent') ?: 2);
            if ($incrementPercent < 2 || $incrementPercent > 5) {
                return redirect()->to("/listings/{$listingId}")->with('error', 'Bid increment must be between 2% and 5% of Reserve Value.');
            }
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * ($incrementPercent / 100), 2);
        }

        // D-34 correction: Express gets an automatic 2% increment — was
        // also missing entirely, plus the 10-minute halving window that
        // didn't exist for Express at all before.
        if ($format === 'express') {
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * 0.02, 2);
        }

        // BR-12/BR-14: Tender is restricted exclusively to Company Shop
        if ($format === 'tender') {
            try {
                (new \App\Libraries\TenderService())->validateCompanyShopOnly($listing['tenant_id']);
            } catch (\RuntimeException $e) {
                return redirect()->to("/listings/{$listingId}")->with('error', $e->getMessage());
            }

            $startAt = $this->request->getPost('scheduled_start_at');
            $endAt = $this->request->getPost('scheduled_end_at');
            if (!$startAt || !$endAt) {
                return redirect()->to("/listings/{$listingId}")->with('error', 'Tender requires both a start and end date/time.');
            }
            if (strtotime($endAt) <= strtotime($startAt)) {
                return redirect()->to("/listings/{$listingId}")->with('error', 'The end time must be after the start time.');
            }
            $data['scheduled_start_at'] = date('Y-m-d H:i:s', strtotime($startAt));
            $data['scheduled_end_at'] = date('Y-m-d H:i:s', strtotime($endAt));

            // Seller's total flexibility — a direct rupee amount, not a
            // percentage (confirmed distinct from Easy/Express).
            $data['bid_increment_amount'] = (float) ($this->request->getPost('bid_increment_amount') ?: 0) ?: null;

            // The two confirmed, distinct windows.
            $data['dynamic_time_trigger_minutes'] = 10;  // increment halving
            $data['anti_snipe_trigger_minutes'] = 2;      // clock extension
            $data['dynamic_time_extension_minutes'] = 2;
        }

        // BR-38: a seller in flush-out state may only list within their
        // permitted value range — the mirrored seller-side ladder,
        // enforced the same way as the buyer-side check.
        $sellerValue = $data['reserve_value'] ?? $data['expected_value'] ?? null;
        if ($sellerValue !== null) {
            $tenant = (new \App\Models\TenantModel())->find($listing['tenant_id']);
            $ceiling = (new \App\Libraries\RatingService())->getTransactionCeiling($sellerId, 'seller_star_rating', $tenant);
            if ($ceiling !== null && (float) $sellerValue > $ceiling) {
                return redirect()->to("/listings/{$listingId}")->with('error',
                    "BR-38: your seller account is currently restricted to listings valued up to ₹" . number_format($ceiling, 2) . '.'
                );
            }
        }

        // BR-47: Related Auctions is available on Buy-Now, Easy, and
        // Tender only — Express's fast/no-review nature doesn't suit
        // grouped browsing. Also: every item in a group must share the
        // same format throughout.
        if (!empty($listing['related_group_id'])) {
            if ($format === 'express') {
                return redirect()->to("/listings/{$listingId}")->with('error',
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
                    return redirect()->to("/listings/{$listingId}")->with('error',
                        "BR-47: every item in this related group must share the same sale format — the group is already using " . strtoupper($otherSaleEvent['sale_format']) . '.'
                    );
                }
            }
        }

        $saleEvent = $this->saleEventModel->createSaleEvent($data);

        // BR-13: listing moves to active once a sale system is attached
        $this->listingModel->transitionStatus($listingId, 'active');

        return redirect()->to("/listings/{$listingId}");
    }

    // BR-09: Tenant Admin approval — access enforced by the tenantAdmin
    // route filter (resource type 'saleEvent').
    public function approve(string $saleEventId)
    {
        try {
            $this->lifecycle->approveSaleEvent($saleEventId);
        } catch (\RuntimeException $e) {
            $saleEvent = $this->saleEventModel->find($saleEventId);
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        $saleEvent = $this->saleEventModel->find($saleEventId);
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // BR-57: mandatory for Express specifically, since no inspection
    // window exists — the seller's only accountability mechanism.
    public function defectDisclosureForm(string $saleEventId)
    {
        $sellerId = session()->get('logged_in_party_id');
        if (!$sellerId) return redirect()->to('/login');

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'express') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('sale_event/defect_disclosure', ['title' => 'Defect Disclosure — eBid Hub', 'saleEvent' => $saleEvent]);
    }

    public function defectDisclosureSubmit(string $saleEventId)
    {
        $sellerId = session()->get('logged_in_party_id');
        if (!$sellerId) return redirect()->to('/login');

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $this->saleEventModel->update($saleEventId, [
            'defect_disclosure_known_damage' => $this->request->getPost('known_damage') ?: 'None disclosed.',
            'defect_disclosure_missing_components' => $this->request->getPost('missing_components') ?: 'None disclosed.',
            'defect_disclosure_nonfunctional_aspects' => $this->request->getPost('nonfunctional_aspects') ?: 'None disclosed.',
            'defect_disclosure_completed_at' => date('Y-m-d H:i:s'),
        ]);

        (new \App\Libraries\AuditLogService())->log('sale_event.defect_disclosure_completed', $sellerId, [
            'saleEventId' => $saleEventId,
        ]);

        return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', 'Defect disclosure completed — the listing can now be approved.');
    }

    // ⚠️ DEV-ONLY: BR-14's real 60-minute grace window can't be waited out
    // in a live demo/test session — this forces the freeze immediately.
    // Must not exist in a production build; the real transition is
    // time-based via a scheduled job. Now also gated behind the
    // tenantAdmin filter, consistent with other administrative actions,
    // though the underlying time-skip mechanism itself remains a stand-in.
    public function devForceFreeze(string $saleEventId)
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $this->saleEventModel->update($saleEventId, [
            'grace_period_ends_at' => date('Y-m-d H:i:s', time() - 1),
        ]);
        $this->lifecycle->freezeAfterGrace($saleEventId);
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // Was fully built and tested (BR-14: withdraws all bids, releases all
    // EMD, mandatory audited reason) but had no HTTP route at all until
    // now. Access is enforced by the tenantAdmin route filter.
    public function emergencyStop(string $saleEventId)
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $reason = $this->request->getPost('reason');
        if (!$reason) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', 'BR-14: a reason is required to emergency-stop a sale event.');
        }
        try {
            $this->lifecycle->emergencyStop($saleEventId, $reason, session()->get('logged_in_party_id'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }
}
