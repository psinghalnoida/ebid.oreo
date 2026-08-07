<?php

namespace App\Controllers;

use App\Libraries\EmdService;
use App\Libraries\ConsentService;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;

class EmdConsentController extends BaseController
{
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
    }

    public function form(string $saleEventId, string $action)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $baseline = EmdService::calculateBaselineEmd(
            $saleEvent['sale_format'],
            $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
            $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null
        );

        return view('emd/consent', [
            'title' => 'Confirm Your Deposit — AdwitiX',
            'saleEvent' => $saleEvent, 'amount' => $baseline, 'action' => $action,
        ]);
    }

    public function confirm(string $saleEventId, string $action)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        // BR-15: structurally barred from pledging under any
        // circumstance — checked before the EMD is ever held.
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($partyId)) {
            return redirect()->to('/')->with('error', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never pledge an EMD deposit.');
        }

        // BR-55: full KYC verification is mandatory before a User's
        // first EMD pledge, with no lower-value exemption.
        try {
            (new \App\Libraries\KycService())->requireVerifiedKyc($partyId, 'pledging an EMD deposit');
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }

        if ($this->request->getPost('confirmed') !== '1') {
            return redirect()->to("/sale-events/{$saleEventId}/emd-consent/{$action}")
                ->with('error', 'You must explicitly confirm before your deposit is pledged.');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $baseline = EmdService::calculateBaselineEmd(
            $saleEvent['sale_format'],
            $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
            $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null
        );

        // BR-55: enhanced due diligence above the live threshold — gates
        // this specific pledge, not the whole account.
        try {
            (new \App\Libraries\KycService())->checkEnhancedDueDiligence($partyId, $baseline);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }

        $forfeitureText = 'if the auction closes and you fail to complete your obligation, this deposit is forfeited — allocated to the Tenant, SaaS, and (where applicable) the seller per the platform\'s standard forfeiture rules.';

        (new ConsentService())->recordEmdPledgeConsent(
            $partyId, $saleEventId, $baseline, $forfeitureText, $this->request->getIPAddress()
        );

        match ($action) {
            'easy_or_tender' => $this->fundStandard($saleEventId, $partyId, $baseline),
            'buy_now' => $this->fundOffer($saleEventId, $partyId, $baseline),
            'express' => $this->fundExpress($saleEventId, $partyId),
            default => throw new \RuntimeException("Unknown consent action: {$action}"),
        };

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    private function fundStandard(string $saleEventId, string $partyId, float $baseline): void
    {
        $emdHoldModel = new EmdHoldModel();
        $existing = $emdHoldModel->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$existing || $existing['status'] !== 'held') {
            $emdHoldModel->createHold($saleEventId, $partyId, 'van', $baseline);
            (new \App\Libraries\AuditLogService())->log('emd.held', $partyId, [
                'saleEventId' => $saleEventId, 'amount' => $baseline, 'channel' => 'van',
            ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        }
    }

    private function fundOffer(string $saleEventId, string $partyId, float $baseline): void
    {
        $this->fundStandard($saleEventId, $partyId, $baseline);
    }

    private function fundExpress(string $saleEventId, string $partyId): void
    {
        (new \App\Libraries\ExpressAuctionService())->pledgeReserve($saleEventId, $partyId);
    }
}
