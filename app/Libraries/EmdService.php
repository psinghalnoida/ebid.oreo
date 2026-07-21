<?php

namespace App\Libraries;

class EmdService
{
    private const EMD_PERCENT = 0.10; // BR-27: flat 10% across all formats
    private const ERROR_PREFIX = 'EMD_CALCULATION_ERROR';

    // BR-27: EMD Baseline Calculation Protocol
    public static function calculateBaselineEmd(string $saleFormat, ?float $expectedValue, ?float $reserveValue): float
    {
        if ($saleFormat === 'buy_now') {
            if (!$expectedValue || $expectedValue <= 0) {
                throw new \RuntimeException(self::ERROR_PREFIX . ': Buy-Now requires a positive expected_value');
            }
            return self::round2($expectedValue * self::EMD_PERCENT);
        }
        if ($saleFormat === 'express' || $saleFormat === 'easy') {
            if (!$reserveValue || $reserveValue <= 0) {
                throw new \RuntimeException(self::ERROR_PREFIX . ": {$saleFormat} requires a positive reserve_value");
            }
            return self::round2($reserveValue * self::EMD_PERCENT);
        }
        if ($saleFormat === 'tender') {
            throw new \RuntimeException(self::ERROR_PREFIX . ': Tender uses manual offline EMD (BR-26), not this calculator');
        }
        throw new \RuntimeException(self::ERROR_PREFIX . ": unknown sale_format \"{$saleFormat}\"");
    }

    // BR-29: signed delta — positive = top-up owed, negative = refund owed
    public static function calculateBuyNowAdjustment(float $heldAmount, float $finalAcceptedPrice): float
    {
        $requiredAmount = self::round2($finalAcceptedPrice * self::EMD_PERCENT);
        return self::round2($requiredAmount - $heldAmount);
    }

    // BR-28: recalculate H1's EMD against the actual closing value at top-up
    public static function calculateCascadeTopupOwed(float $heldAmount, float $closingValue): float
    {
        $requiredAmount = self::round2($closingValue * self::EMD_PERCENT);
        $owed = self::round2($requiredAmount - $heldAmount);
        return $owed > 0 ? $owed : 0;
    }

    // BR-28: top-up window per format
    public static function calculateTopupWindow(string $saleFormat, int $cascadeStep, ?\DateTimeImmutable $fromTime = null): \DateTimeImmutable
    {
        $fromTime ??= new \DateTimeImmutable();
        $hoursByFormatAndStep = [
            'express' => [1 => 2, 2 => 2, 3 => 2],
            'easy'    => [1 => 24, 2 => 24, 3 => 24],
        ];
        $hours = $hoursByFormatAndStep[$saleFormat][$cascadeStep] ?? null;
        if ($hours === null) {
            throw new \RuntimeException(self::ERROR_PREFIX . ": no cascade window defined for format={$saleFormat} step={$cascadeStep}");
        }
        return $fromTime->modify("+{$hours} hours");
    }

    // BR-34: Forfeited EMD Allocation
    public static function calculateForfeitureAllocation(
        float $forfeitedAmount,
        float $tenantFeePercent,
        float $saasFeePercent,
        bool $isFullCascadeFailure
    ): array {
        $tenantAmount = self::round2($forfeitedAmount * ($tenantFeePercent / 100));
        $saasAmount = self::round2($forfeitedAmount * ($saasFeePercent / 100));

        if ($isFullCascadeFailure) {
            // BR-28: seller excluded entirely; remainder stays with the platform
            $remainder = self::round2($forfeitedAmount - $tenantAmount - $saasAmount);
            return [
                'tenantAmount' => self::round2($tenantAmount + $remainder / 2),
                'saasAmount' => self::round2($saasAmount + $remainder / 2),
                'sellerAmount' => 0.0,
            ];
        }

        $sellerAmount = self::round2($forfeitedAmount - $tenantAmount - $saasAmount);
        return ['tenantAmount' => $tenantAmount, 'saasAmount' => $saasAmount, 'sellerAmount' => $sellerAmount];
    }

    // BR-33/BR-31: fee deduction on a SUCCESSFUL settlement (distinct from
    // calculateForfeitureAllocation, which is for a DEFAULT). The buyer
    // pays the seller the full sale value directly and offline (BR-10.1)
    // — the platform's cut comes only from the buyer's held EMD, with the
    // remainder refunded. Fee is a percentage of the final sale price,
    // not of the held EMD amount (per the Fee & Charges Schedule).
    public static function calculateSettlementFee(
        float $finalPrice, float $buyerFeePercent, float $heldAmount, float $saasFeePercent = 0.5
    ): array {
        $feeTotal = round($finalPrice * ($buyerFeePercent / 100), 2);
        $saasAmount = round($finalPrice * ($saasFeePercent / 100), 2);
        $tenantAmount = round($feeTotal - $saasAmount, 2);
        $buyerRefund = round($heldAmount - $feeTotal, 2);

        if ($buyerRefund < 0) {
            // Held EMD didn't cover the fee — should be rare (EMD is 10%,
            // default fee is 5%), but not impossible if a tenant sets an
            // unusually high buyer fee. Flagged rather than silently
            // producing a negative refund.
            throw new \RuntimeException(
                'EMD_CALCULATION_ERROR: held EMD (' . $heldAmount . ') is insufficient to cover the settlement fee (' . $feeTotal . ') — this tenant\'s buyer fee percent may be set too high relative to the EMD baseline'
            );
        }

        return ['tenantAmount' => $tenantAmount, 'saasAmount' => $saasAmount, 'buyerRefund' => $buyerRefund];
    }

    private static function round2(float $n): float
    {
        return round($n, 2);
    }
}
