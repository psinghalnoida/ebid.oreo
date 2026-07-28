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
            'title' => 'Confirm Your Deposit — eBid Hub',
            'saleEvent' => $saleEvent, 'amount' => $baseline, 'action' => $action,
        ]);
    }

    public function confirm(string $saleEventId, string $action)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

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

        $forfeitureText = 'if the auction closes and you fail to complete your obligation, this deposit is forfeited — allocated to the Tenant, SaaS, and (where applicable) the seller per the platform\'s standard forfeiture rules.';

        (new ConsentService())->recordEmdPledgeConsent(
            $partyId, $saleEventId, $baseline, $forfeitureText, $this->request->getIPAddress()
        );

        // ⚠️ Dev-only: the real payment gateway (BR-52) isn't connected
        // yet, so there's no genuine gateway reference/UTR to store. This
        // optional field lets BR-54's shared-external-reference AML
        // pattern (AmlMonitoringService) actually be exercised end-to-end
        // before that gateway exists — never shown or asked for once a
        // real gateway is wired in, since it will supply this itself.
        $devGatewayReference = trim((string) $this->request->getPost('dev_gateway_reference')) ?: null;

        match ($action) {
            'easy_or_tender' => $this->fundStandard($saleEventId, $partyId, $baseline, $devGatewayReference),
            'buy_now' => $this->fundOffer($saleEventId, $partyId, $baseline, $devGatewayReference),
            'express' => $this->fundExpress($saleEventId, $partyId, $devGatewayReference),
            default => throw new \RuntimeException("Unknown consent action: {$action}"),
        };

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    private function fundStandard(string $saleEventId, string $partyId, float $baseline, ?string $gatewayReference = null): void
    {
        $emdHoldModel = new EmdHoldModel();
        $existing = $emdHoldModel->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$existing || $existing['status'] !== 'held') {
            $emdHoldModel->createHold($saleEventId, $partyId, 'van', $baseline, $gatewayReference);
            (new \App\Libraries\AuditLogService())->log('emd.held', $partyId, [
                'saleEventId' => $saleEventId, 'amount' => $baseline, 'channel' => 'van',
            ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        }
    }

    private function fundOffer(string $saleEventId, string $partyId, float $baseline, ?string $gatewayReference = null): void
    {
        $this->fundStandard($saleEventId, $partyId, $baseline, $gatewayReference);
    }

    private function fundExpress(string $saleEventId, string $partyId, ?string $gatewayReference = null): void
    {
        (new \App\Libraries\ExpressAuctionService())->pledgeReserve($saleEventId, $partyId, $gatewayReference);
    }
}
