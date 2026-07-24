<?php

namespace App\Libraries;

use App\Models\SaleEventModel;

class TenderBiddingService
{
    private SaleEventModel $saleEventModel;
    private BiddingService $bidding;
    private TenderService $tender;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
        $this->bidding = new BiddingService();
        $this->tender = new TenderService();
    }

    public function isBiddingOpen(array $saleEvent): bool
    {
        if ($saleEvent['scheduled_start_at'] === null || $saleEvent['scheduled_end_at'] === null) {
            return false;
        }
        $now = new \DateTimeImmutable();
        return $now >= new \DateTimeImmutable($saleEvent['scheduled_start_at'])
            && $now < new \DateTimeImmutable($saleEvent['scheduled_end_at']);
    }

    public function placeBid(string $saleEventId, string $bidderPartyId, float $amount): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'tender') {
            throw new \RuntimeException('TenderBiddingService is only for Tender sale events');
        }
        if (!$this->tender->isEligible($saleEventId, $bidderPartyId)) {
            throw new \RuntimeException('Only buyers the seller has approved as eligible may bid on this Tender.');
        }
        if (!$this->isBiddingOpen($saleEvent)) {
            throw new \RuntimeException(
                $saleEvent['scheduled_start_at'] === null
                    ? 'This Tender has no schedule set yet.'
                    : (new \DateTimeImmutable() < new \DateTimeImmutable($saleEvent['scheduled_start_at'])
                        ? 'This Tender has not started yet.'
                        : 'This Tender has closed.')
            );
        }

        $result = $this->bidding->placeBid($saleEventId, $bidderPartyId, $amount);

        $this->applyIncrementHalvingIfNeeded($saleEventId);
        $this->applyAntiSnipeExtensionIfNeeded($saleEventId);

        return $result;
    }

    public function applyIncrementHalvingIfNeeded(string $saleEventId): void
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if ($saleEvent['increment_halved_at'] !== null || $saleEvent['bid_increment_amount'] === null) {
            return;
        }
        $triggerMinutes = (int) ($saleEvent['dynamic_time_trigger_minutes'] ?? 10);
        $scheduledEnd = new \DateTimeImmutable($saleEvent['scheduled_end_at']);
        $triggerThreshold = $scheduledEnd->modify("-{$triggerMinutes} minutes");

        if (new \DateTimeImmutable() >= $triggerThreshold) {
            $updates = [
                'bid_increment_amount' => round((float) $saleEvent['bid_increment_amount'] / 2, 2),
                'increment_halved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $this->saleEventModel->update($saleEventId, $updates);
            (new RealtimeBroadcastService())->broadcast($saleEventId, 'dynamic_time_update', $updates);
        }
    }

    public function applyAntiSnipeExtensionIfNeeded(string $saleEventId): void
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if ($saleEvent['anti_snipe_trigger_minutes'] === null || $saleEvent['scheduled_end_at'] === null) {
            return;
        }

        $now = new \DateTimeImmutable();
        $currentEnd = new \DateTimeImmutable($saleEvent['scheduled_end_at']);
        $triggerMinutes = (int) $saleEvent['anti_snipe_trigger_minutes'];
        $extensionMinutes = (int) ($saleEvent['dynamic_time_extension_minutes'] ?? 2);

        $triggerThreshold = $currentEnd->modify("-{$triggerMinutes} minutes");
        if ($now < $triggerThreshold) {
            return;
        }

        $candidateNewEnd = $now->modify("+{$extensionMinutes} minutes");
        if ($candidateNewEnd > $currentEnd) {
            $updates = ['scheduled_end_at' => $candidateNewEnd->format('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            $this->saleEventModel->update($saleEventId, $updates);
            (new RealtimeBroadcastService())->broadcast($saleEventId, 'dynamic_time_update', $updates);
        }
    }
}
