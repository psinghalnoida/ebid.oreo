<?php

namespace App\Libraries;

use App\Models\SaleEventModel;

class EasyAuctionService
{
    private SaleEventModel $saleEventModel;
    private BiddingService $bidding;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
        $this->bidding = new BiddingService();
    }

    public function isBiddingOpen(array $saleEvent): bool
    {
        if ($saleEvent['scheduled_start_at'] === null || $saleEvent['scheduled_end_at'] === null) {
            return true;
        }
        $now = new \DateTimeImmutable();
        return $now >= new \DateTimeImmutable($saleEvent['scheduled_start_at'])
            && $now < new \DateTimeImmutable($saleEvent['scheduled_end_at']);
    }

    public function placeBid(string $saleEventId, string $bidderPartyId, float $amount): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'easy') {
            throw new \RuntimeException('EasyAuctionService is only for Easy Auction sale events');
        }
        if (!$this->isBiddingOpen($saleEvent)) {
            throw new \RuntimeException(
                new \DateTimeImmutable() < new \DateTimeImmutable($saleEvent['scheduled_start_at'])
                    ? 'This Easy Auction has not started yet.'
                    : 'This Easy Auction has closed.'
            );
        }

        $result = $this->bidding->placeBid($saleEventId, $bidderPartyId, $amount);
        $this->applyDynamicTimeIfNeeded($saleEventId);
        return $result;
    }

    // BR-12/D-32 correction (D-34): fixed to extend from the bid's own
    // timestamp, not the current end — previously calculated
    // current_end + extension, which could over-extend. Also now applies
    // BR-27's general increment-halving requirement (found in the actual
    // Tech Stack Specification, missed entirely in the original D-32
    // build) — Easy uses ONE shared window for both behaviors, unlike
    // Tender's two separate windows.
    public function applyDynamicTimeIfNeeded(string $saleEventId): ?array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['scheduled_end_at'] === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $currentEnd = new \DateTimeImmutable($saleEvent['scheduled_end_at']);
        $triggerMinutes = (int) ($saleEvent['dynamic_time_trigger_minutes'] ?? 10);
        $extensionMinutes = (int) ($saleEvent['dynamic_time_extension_minutes'] ?? 2);
        $triggerThreshold = $currentEnd->modify("-{$triggerMinutes} minutes");

        if ($now < $triggerThreshold) {
            return $saleEvent; // not inside the window at all yet
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        // Increment halves once, stays halved — same guard pattern as Tender.
        if ($saleEvent['increment_halved_at'] === null && $saleEvent['bid_increment_amount'] !== null) {
            $updates['bid_increment_amount'] = round((float) $saleEvent['bid_increment_amount'] / 2, 2);
            $updates['increment_halved_at'] = date('Y-m-d H:i:s');
        }

        // Clock extends from the BID's timestamp, never earlier than the
        // current end — the corrected math (was current_end + extension).
        $candidateNewEnd = $now->modify("+{$extensionMinutes} minutes");
        if ($candidateNewEnd > $currentEnd) {
            $updates['scheduled_end_at'] = $candidateNewEnd->format('Y-m-d H:i:s');
        }

        $this->saleEventModel->update($saleEventId, $updates);
        return $this->saleEventModel->find($saleEventId);
    }
}
