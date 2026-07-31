<?php

namespace App\Libraries;

class EmdService
{
    // PR-04/D-75: was a hardcoded const, now the Super Admin's live,
    // versioned "BR-27.emd_percent" rule. 0.10 is the fallback until
    // that rule is seeded — the exact original value, so this rewiring
    // changes nothing until a Super Admin actually edits it.
    private const EMD_PERCENT_DEFAULT = 0.10; // BR-27: flat 10% across all formats
    private const ERROR_PREFIX = 'EMD_CALCULATION_ERROR';

    private static function emdPercent(): float
    {
        return SovereignRuleService::getNumeric('BR-27.emd_percent', self::EMD_PERCENT_DEFAULT);
    }

    // BR-27: EMD Baseline Calculation Protocol
    public static function calculateBaselineEmd(string $saleFormat, ?float $expectedValue, ?float $reserveValue): float
    {
        if ($saleFormat === 'buy_now') {
            if (!$expectedValue || $expectedValue <= 0) {
                throw new \RuntimeException(self::ERROR_PREFIX . ': Buy-Now requires a positive expected_value');
            }
            return self::round2($expectedValue * self::emdPercent());
        }
        if ($saleFormat === 'tender') {
            // BR: Tender's EMD is manual/offline — no automated percent
            // gate. The actual audit trail (amount + payment location,
            // or a logged reason if none) is tracked separately via
            // TenderService::logManualEmd, not enforced here.
            return 0.0;
        }
        if ($saleFormat === 'express' || $saleFormat === 'easy') {
            if (!$reserveValue || $reserveValue <= 0) {
                throw new \RuntimeException(self::ERROR_PREFIX . ": {$saleFormat} requires a positive reserve_value");
            }
            return self::round2($reserveValue * self::emdPercent());
        }
        if ($saleFormat === 'tender') {
            throw new \RuntimeException(self::ERROR_PREFIX . ': Tender uses manual offline EMD (BR-26), not this calculator');
        }
        throw new \RuntimeException(self::ERROR_PREFIX . ": unknown sale_format \"{$saleFormat}\"");
    }

    // BR-29: signed delta — positive = top-up owed, negative = refund owed
    public static function calculateBuyNowAdjustment(float $heldAmount, float $finalAcceptedPrice): float
    {
        $requiredAmount = self::round2($finalAcceptedPrice * self::emdPercent());
        return self::round2($requiredAmount - $heldAmount);
    }

    // BR-28: recalculate H1's EMD against the actual closing value at top-up
    public static function calculateCascadeTopupOwed(float $heldAmount, float $closingValue): float
    {
        $requiredAmount = self::round2($closingValue * self::emdPercent());
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

    // BR-31 (ADWITIX_Master.docx Section 5.4/D-87/D-88): single,
    // platform-wide, non-tenant-adjustable Success Fee, declining by
    // final sale value. Replaces the old flat-0.5%-SaaS-plus-tenant-
    // adjustable-0.5%-5%-band model — the Tenant Admin no longer sets any
    // fee rate at all (BR-09). The >₹10Cr band is described as
    // "negotiable" in the master doc, but since the Tenant Admin has no
    // rate-setting authority left, a negotiated rate is necessarily an
    // off-platform/manual arrangement, not an editable field here — 0.50%
    // is what the platform's own calculator actually charges.
    private const SUCCESS_FEE_MINIMUM = 500.0;

    public static function calculateSuccessFee(float $finalPrice): float
    {
        $fee = self::round2($finalPrice * self::successFeeRate($finalPrice));
        return $fee < self::SUCCESS_FEE_MINIMUM ? self::SUCCESS_FEE_MINIMUM : $fee;
    }

    private static function successFeeRate(float $finalPrice): float
    {
        if ($finalPrice <= 1000000) return 0.02;
        if ($finalPrice <= 5000000) return 0.015;
        if ($finalPrice <= 20000000) return 0.01;
        if ($finalPrice <= 100000000) return 0.0075;
        return 0.005;
    }

    // BR-34: Forfeited EMD Allocation. $saleValue is the price the
    // defaulting party would have paid had they not defaulted (the bid/
    // offer amount, or the sale event's winning current_price) — the
    // Success Fee bracket (BR-31) is looked up against that value, not
    // against $forfeitedAmount itself (which is only the 10% EMD, BR-27).
    // BR-32: the fee amount is identical regardless of that session's Fee
    // Payer Election, so no feePayer parameter is needed here — the fee
    // always comes out of the defaulting party's own forfeited EMD, since
    // a default means there is no completed sale and no seller proceeds
    // to draw from either way.
    public static function calculateForfeitureAllocation(
        float $forfeitedAmount,
        float $saleValue,
        bool $isFullCascadeFailure
    ): array {
        if ($isFullCascadeFailure) {
            // BR-28: seller excluded entirely; the whole forfeited amount
            // is retained by the platform.
            return ['platformAmount' => $forfeitedAmount, 'sellerAmount' => 0.0];
        }

        // Guarded against exceeding what was actually forfeited — only a
        // theoretical case given BR-27's 10% EMD baseline vs. BR-31's
        // 0.50%-2.00% schedule, not an expected real-world outcome.
        $successFee = min(self::calculateSuccessFee($saleValue), $forfeitedAmount);
        $sellerAmount = self::round2($forfeitedAmount - $successFee);
        return ['platformAmount' => $successFee, 'sellerAmount' => $sellerAmount];
    }

    // BR-32/BR-33: fee deduction on a SUCCESSFUL Buyer-Pays settlement
    // (distinct from calculateForfeitureAllocation, which is for a
    // DEFAULT, and from Seller-Pays, which doesn't touch the EMD at all —
    // see SettlementService::checkCompletion). The buyer pays the seller
    // the full sale value directly and offline (BR-33) — the platform's
    // cut comes only from the buyer's held EMD, with the remainder
    // refunded. The Success Fee (BR-31) is 100% platform revenue now; the
    // Tenant no longer shares in it (superseding the old tenant/SaaS
    // split, D-87/D-88).
    public static function calculateSettlementFee(float $finalPrice, float $heldAmount): array
    {
        $feeTotal = self::calculateSuccessFee($finalPrice);
        $buyerRefund = self::round2($heldAmount - $feeTotal);

        if ($buyerRefund < 0) {
            // Held EMD didn't cover the fee — should be effectively
            // impossible now (EMD is a flat 10%, the Success Fee tops out
            // at 2%), but flagged rather than silently producing a
            // negative refund.
            throw new \RuntimeException(
                'EMD_CALCULATION_ERROR: held EMD (' . $heldAmount . ') is insufficient to cover the Success Fee (' . $feeTotal . ')'
            );
        }

        return ['saasAmount' => $feeTotal, 'buyerRefund' => $buyerRefund];
    }

    private static function round2(float $n): float
    {
        return round($n, 2);
    }
}
