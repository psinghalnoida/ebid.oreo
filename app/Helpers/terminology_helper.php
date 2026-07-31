<?php

if (! function_exists('tsx_term')) {
    /**
     * BR-67: Branded Terminology Layer. Presentation-only — the return
     * value is display text, never a field name, route segment, session
     * key, or anything else the data model or code touches. This map is
     * the single source of truth for the 7-row table in
     * ADWITIX_Master.docx Section 1, Part A.
     */
    function tsx_term(string $technical, bool $short = false, bool $plural = false): string
    {
        static $map = [
            'Tenant'       => ['TradeSphereX', 'TSX'],
            'Tenant Admin' => ['TSX Master', 'TSXM'],
            'Seller'       => ['Market Maker', 'MM'],
            'Buyer'        => ['Trader', 'TRD'],
            'Super Admin'  => ['Custodian', 'CUS'],
            'Listing'      => ['Lot', 'LOT'],
            'Sale Event'   => ['Trading Session', 'Trading Session'],
        ];

        if (! isset($map[$technical])) {
            return $technical;
        }

        $term = $short ? $map[$technical][1] : $map[$technical][0];

        return $plural ? $term . 's' : $term;
    }
}
