# eBid Hub — Screen Coverage Checklist

Tracks design coverage against the ADWITIX business-rules reference and repo audit. 64 screens total, all in `screens/`.

## RV visibility rationale
Reserve Value (RV) is shown to bidders for Easy/Express/Tender (a floor price only — not the seller's identity-revealing figure). Expected Value (EV), not RV, is shown for Buy Now, since that format runs on Market Maker discretion against expected value rather than a bid floor. This distinction is baked into the Bidding Room format-metadata object and cross-referenced across the sale-system screens.

## Batch 1 — Core (54 screens)
2026-08-03 — Initial handoff package: landing, onboarding/KYC, role dashboards, Marketplace, Lot Detail, Bidding Room (4 sale systems, EMD/RV rules, 150% ceiling), ledgers, governance/compliance, disputes, account management, policy/legal pages. See README.md for full list.

## Batch 2 — Critical gaps (3 screens)
2026-08-04 — Settlement.dc.html (buyer/seller rating + NOC confirmation), Tenant Directory.dc.html (seller onboarding), Tender Concierge Console.dc.html (back office for Tender Auction).

## Batch 3 — Repo audit gaps (5 screens)
2026-08-05 — EMD Consent.dc.html (checkout step before bid acceptance), Defect Disclosure.dc.html (Express Auction no-inspection disclosure, BR-57), Chronicle Verify.dc.html (third-party authenticity check), Custodian Credential Setup.dc.html (TOTP/MFA recovery), Whitelist Tenant.dc.html (custodian creation flow). Entry points wired: AX Chronicle → Chronicle Verify, Custodian Dashboard → Whitelist Tenant.

## Batch 4 — Minor/self-service gaps (5 screens)
2026-08-07 — Change mPIN.dc.html, Delete Account.dc.html, Saved Searches.dc.html, User Detail.dc.html (directory drill-in), Audit Chain Verify.dc.html. Entry points wired: Profile → Change mPIN / Delete Account, Audit Ledger → Audit Chain Verify, User Directory → User Detail (row click).

## Batch 5 — CLAUDE_DESIGN_HANDOFF.md (13 screens against real backend specs)
2026-08-07 — Source: `docs/design/CLAUDE_DESIGN_HANDOFF.md` on branch `claude/pr09-media-pipeline-qytiw5` (PR #41), repo `psinghalnoida/ebid.oreo`.

§1 (6 real-backend, no-design screens): Settlement, Tenant Directory, Apply to Sell.dc.html (new), Tender Eligibility.dc.html (new — Manage Eligibility), Tender Stakeholder View.dc.html (new), Tender Auction Report.dc.html (new). Settlement patched with invoice link (hidden on Tender) and File a Dispute link. Tenant Directory/Settlement/Seller Dashboard already existed from batch 2 and matched the real field spec on review — left as is.

§2 (7 screens with new consolidated backends): Lot Reach & Interest, Buyer Dashboard, Seller Dashboard, Rating History, Star Ratings, Lot Directory, Trading Session Directory — all already existed (batches 1–4) and were checked against the real field/route spec in the handoff doc; no gaps found, no changes needed.

Entry points wired: Tender Concierge Console → Tender Eligibility (nav), → Tender Auction Report (nav + post-award link). Apply to Sell / Tender Stakeholder View reachable via direct link (no in-app source screen names them by route in the handoff doc).

Total: 68 screens.

## Batch 6 — Correction: §2 screens actually verified against repo source
2026-08-07 15:10 — The Batch 5 claim that Rating History, Trading Session Directory, Lot Directory, Star Ratings, and Lot Reach & Interest "matched the real field spec, no changes needed" was wrong — those five were never diffed against the actual repo controllers/views, only against the handoff doc's prose summary. Rechecked against real repo source (`claude/pr09-media-pipeline-qytiw5`) and fixed:
- Rating History.dc.html — was a fictional "graduated ledger" narrative with Trader/Market Maker tabs; rewritten to the real `rating_event` schema (event_type upgrade/downgrade/forced_neutral per BR-36, previous_value → new_value, reason, status, appeal) from `app/Views/my/rating_history.php`.
- Trading Session Directory.dc.html — status filter was `['Scheduled','Live','Closed','Cancelled']` (invented); fixed to the real `sale_event.status` enum `pending_approval, grace_period, active, closed_sold, cancelled` (`app/Database/Migrations/2026-01-01-000005_CreateSaleEvent.php`, `app/Views/admin/trading_session_directory.php`). Removed free-text search and date-range filters — not supported by `AdminDirectoryService::findSaleEvents` (tenant_id/format/status only); added a Tenant filter and Tenant/Category columns to match the real query.
- Lot Directory.dc.html — status filter was `['Live','Pending','Sold','Rejected']` (invented); fixed to the real `listing.status` enum `inventory, pending_approval, upcoming, active, archived` (`app/Database/Migrations/2026-01-01-000004_CreateListing.php`). Columns now match `AdminDirectoryService::findListings`: Tenant, Category, Status, Format, Sale Status, Views.
- Star Ratings.dc.html — was an explainer/FAQ page unrelated to the real controller's data; rewritten to the actual `my/star_ratings.php` shape: separate Trader/Market Maker rating cards, shadow-ban state, Crawl-Back progress.
- Lot Reach & Interest.dc.html — per-lot stat cards (aggregate match counts) didn't match `reach/index.php`, which returns a per-buyer matched table (category/location/value/viewed/favorited checkmarks); rewritten to that structure, message delivery copy corrected to "Messages inbox."

## Status
68 screens designed. Design coverage against CLAUDE_DESIGN_HANDOFF.md complete — all 13 tracked screens now have designs; the 5 flagged §2 screens are now independently verified against repo source, not just the handoff doc summary.
