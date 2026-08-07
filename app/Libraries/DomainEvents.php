<?php

namespace App\Libraries;

// D-115: a first slice of a real domain-event catalog — named,
// centralized event identifiers, not ad-hoc strings scattered across
// call sites. Publishers fire these via CodeIgniter's own, previously
// unused Events facade (Events::trigger(self::BID_PLACED, $payload));
// consumers subscribe in app/Config/Events.php, with zero knowledge
// of which service published the event.
//
// Deliberately a starting set (5 events, chosen from the Chief
// Architect directive's own examples), not the full business-capability
// catalog the directive eventually wants — every capability (Create
// Auction, Approve Seller, Verify KYC, Generate Settlement, Resolve
// Dispute, ...) publishing its own event. Flagged as a first slice,
// not a completed inventory, in docs/DECISIONS.md D-115.
final class DomainEvents
{
    // Fired when a sale event actually goes live (ListingLifecycleService::approveSaleEvent).
    public const AUCTION_CREATED = 'AuctionCreated';

    // Fired on every accepted bid (BiddingService::placeBid).
    public const BID_PLACED = 'BidPlaced';

    // Fired once a settlement's 4-step gate completes (SettlementService::checkCompletion).
    // Stands in for the directive's own "PaymentReceived" example — no
    // real payment gateway exists yet (a separate, tracked gap), so
    // settlement completion is the closest genuine money-changed-hands
    // milestone this platform has.
    public const SETTLEMENT_COMPLETED = 'SettlementCompleted';

    // Fired when a Tenant Admin approves a KYC dossier (KycService::reviewDossier).
    public const KYC_APPROVED = 'KYCApproved';

    // Fired when a dispute is filed (DisputeService::fileDispute).
    public const DISPUTE_FILED = 'DisputeFiled';

    private function __construct()
    {
        // Constants-only catalog — never instantiated.
    }
}
