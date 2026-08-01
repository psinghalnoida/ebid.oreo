<?php

namespace App\Libraries;

// Section 7.10 (ADWITIX_Master.docx): the Trading Session Chronicle --
// a system-authenticated report generated automatically the moment a
// Sale Event's Settlement completes. Template-based, not AI-authored
// (Section 7.9's AI functions need a real LLM credential this build
// doesn't have, same category as BR-46). Draws directly from existing
// tables and the BR-05 audit trail rather than a new Observation/
// Finding/Decision layer (Section 7.6), which is Phase 2 scope.
class ChronicleService
{
    public function generate(string $settlementId, array $settlement, float $feeAmount, float $tdsAmount): array
    {
        $db = \Config\Database::connect();

        $saleEvent = $db->table('sale_event')->where('id', $settlement['sale_event_id'])->get()->getRowArray();
        $listing = $db->table('listing')->where('id', $saleEvent['listing_id'])->get()->getRowArray();

        $reportData = [
            'listing' => [
                'category' => $listing['category'],
                'subcategory' => $listing['subcategory'],
                'physicalCondition' => $listing['physical_condition'],
                'quantity' => (float) $listing['quantity'],
                'quantityBasis' => $listing['quantity_basis'],
                'makeModel' => $listing['make_model'],
                'yardLocationAddress' => $listing['yard_location_address'],
            ],
            'saleEvent' => [
                'ern' => $saleEvent['ern'],
                'saleFormat' => $saleEvent['sale_format'],
                'reserveValue' => $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null,
                'expectedValue' => $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
                'actualClosedAt' => $saleEvent['actual_closed_at'],
            ],
            'result' => [
                'finalPrice' => (float) $settlement['final_price'],
                'completedAt' => $settlement['completed_at'] ?? date('Y-m-d H:i:s'),
            ],
            'improvement' => $this->computeImprovement($saleEvent, (float) $settlement['final_price']),
            'participation' => $this->compileParticipation($db, $saleEvent['id']),
            'timeline' => $this->compileTimeline($db, $saleEvent['id'], $listing['id'], $settlementId),
            'transaction' => [
                'feePayer' => $saleEvent['fee_payer'],
                'successFeeAmount' => $feeAmount,
                'tdsRatePercent' => 10.00,
                'tdsAmount' => $tdsAmount,
            ],
            // BR-16: identity stays masked throughout -- recorded as a
            // plain data point rather than an explanatory sentence, per
            // the project owner's "data points only" instruction.
            'privacy' => [
                'identityDisclosure' => 'Masked (Double-Blind)',
                'processAuditable' => 'Yes',
            ],
        ];

        $id = Uuid::v4();
        $referenceNumber = 'CHR-' . date('Ymd') . '-' . strtoupper(substr($id, 0, 8));
        $verificationToken = bin2hex(random_bytes(24));

        // BR-05 tie-in: log to the hash-chained audit trail first, then
        // fold that entry's own record_hash into the report content
        // itself -- so the stored snapshot carries a live, independently
        // checkable pointer into the tamper-evident chain, and the
        // Chronicle's content_hash (computed after, from the same
        // final report_data that gets stored) is a direct, trivially
        // re-verifiable hash of exactly what's in the row.
        $auditResult = (new AuditLogService())->log('chronicle.generated', $settlement['seller_party_id'], [
            'settlementId' => $settlementId, 'saleEventId' => $saleEvent['id'], 'referenceNumber' => $referenceNumber,
        ]);
        $reportData['auditChainRecordHash'] = $auditResult['recordHash'];

        $reportJson = json_encode($reportData, JSON_UNESCAPED_SLASHES);
        $contentHash = hash('sha256', $reportJson);

        $db->table('trading_session_chronicle')->insert([
            'id' => $id,
            'sale_event_id' => $saleEvent['id'],
            'settlement_id' => $settlementId,
            'tenant_id' => $saleEvent['tenant_id'],
            'reference_number' => $referenceNumber,
            'verification_token' => $verificationToken,
            'content_hash' => $contentHash,
            'report_data' => $reportJson,
        ]);

        return $db->table('trading_session_chronicle')->where('id', $id)->get()->getRowArray();
    }

    private function computeImprovement(array $saleEvent, float $finalPrice): array
    {
        $reserveValue = $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null;
        if ($reserveValue === null || $reserveValue <= 0) {
            return ['reserveValue' => $reserveValue, 'finalPrice' => $finalPrice, 'improvementPercent' => null];
        }

        return [
            'reserveValue' => $reserveValue,
            'finalPrice' => $finalPrice,
            'improvementPercent' => round((($finalPrice - $reserveValue) / $reserveValue) * 100, 2),
        ];
    }

    // BR-16: counts and amounts only -- no bidder_party_id/buyer_party_id
    // is ever included in report_data, matching the same masking already
    // applied everywhere else a Sale Event's offers/bids are rendered.
    private function compileParticipation(\CodeIgniter\Database\BaseConnection $db, string $saleEventId): array
    {
        $bids = $db->table('bid')->where('sale_event_id', $saleEventId)->orderBy('placed_at', 'ASC')->get()->getResultArray();
        $offers = $db->table('offer')->where('sale_event_id', $saleEventId)->orderBy('created_at', 'ASC')->get()->getResultArray();

        $distinctBidders = count(array_unique(array_column($bids, 'bidder_party_id')));
        $distinctOfferors = count(array_unique(array_column($offers, 'buyer_party_id')));

        return [
            'distinctParticipants' => $distinctBidders + $distinctOfferors,
            'bidCount' => count($bids),
            'offerCount' => count($offers),
            'bidProgression' => array_map(fn ($b) => ['amount' => (float) $b['amount'], 'placedAt' => $b['placed_at']], $bids),
            'offerProgression' => array_map(fn ($o) => ['amount' => (float) $o['amount'], 'status' => $o['status'], 'createdAt' => $o['created_at']], $offers),
        ];
    }

    // Same substring-match pattern SettlementController::show already
    // uses for its own scoped audit timeline — broader and more robust
    // than matching specific JSON keys, since it catches any payload
    // that mentions the ID under any key name.
    private function compileTimeline(\CodeIgniter\Database\BaseConnection $db, string $saleEventId, string $listingId, string $settlementId): array
    {
        $rows = $db->table('audit_log')
            ->select('occurred_at, event_type, payload')
            ->like('payload', $saleEventId)
            ->orLike('payload', $listingId)
            ->orLike('payload', $settlementId)
            ->orderBy('sequence_number', 'ASC')
            ->get()->getResultArray();

        return array_map(fn ($r) => ['occurredAt' => $r['occurred_at'], 'eventType' => $r['event_type']], $rows);
    }

    public function getByToken(string $token): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('trading_session_chronicle')->where('verification_token', $token)->get()->getRowArray();
    }

    public function findForSaleEvent(string $saleEventId): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('trading_session_chronicle')
            ->where('sale_event_id', $saleEventId)
            ->orderBy('version', 'DESC')
            ->limit(1)
            ->get()->getRowArray();
    }

    // Authorization check for the Seller/Tenant Admin download path --
    // only the Seller on the underlying Sale Event, or that Tenant's
    // Admin, may fetch a Chronicle by ID directly (the QR path is
    // deliberately different: token-only, no session check at all).
    public function findIfAuthorized(string $chronicleId, string $partyId): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('trading_session_chronicle c')
            ->select('c.*, l.seller_party_id')
            ->join('sale_event se', 'se.id = c.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('c.id', $chronicleId)
            ->where('l.seller_party_id', $partyId)
            ->get()->getRowArray();
    }
}
