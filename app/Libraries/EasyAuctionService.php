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
        if ($now >= $triggerThreshold && $now < $currentEnd) {
            $newEnd = $currentEnd->modify("+{$extensionMinutes} minutes");
            $this->saleEventModel->update($saleEventId, [
                'scheduled_end_at' => $newEnd->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return $this->saleEventModel->find($saleEventId);
        }
        return $saleEvent;
    }
}
