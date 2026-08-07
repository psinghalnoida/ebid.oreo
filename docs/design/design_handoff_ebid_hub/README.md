# Handoff: eBid Hub — Full Platform Design

2026-08-03 — Handoff package created for push to `psinghalnoida/ebid.oreo` and pickup by Claude Code.

## Overview
eBid Hub is a B2B e-auction/liquidation marketplace. This package covers the full designed surface: marketing/landing, onboarding & KYC, Buyer/Seller/Custodian/Tenant Admin dashboards, Marketplace + Lot Detail + Bidding Room (core trading flow), the 4 Sale Systems (Easy Auction, Express Auction, Buy Now, Tender Auction), governance/compliance screens (AML, KYC Queue, Payout Reviews, Invoices, Rating Reviews, Consent Audit, Statutory Export, Media Waivers, Delist Market Maker, Rules & Specifications, Alerts), ledgers (AX Chronicle, Lot Chronicle, Audit Ledger), dispute flows, and policy/legal pages.

## About the Design Files
The `screens/*.dc.html` files are **design references built in HTML** (Design Components) — high-fidelity prototypes of look, content, and interaction behavior. They are **not production code to copy verbatim**. The task is to recreate these designs in the target codebase's real stack (React/Vue/etc., whatever `ebid.oreo` already uses, or the best framework choice if the repo is greenfield), using that stack's component patterns, state management, and API layer — not to ship the raw HTML/inline-styled markup.

Each `.dc.html` file is self-contained: inline styles throughout (no external stylesheet), a template section and a small JS "logic" class driving state/derived values. Read both halves of each file to get exact copy, exact conditional logic, and exact styling values.

## Fidelity
**High-fidelity.** Colors, type, spacing, copy, and interaction states are final-intent, not placeholder. Recreate pixel-close using the target app's real component library; do not restyle "to taste."

## Business Rules — Source of Truth
`reference/ADWITIX_Master_Business_Rules.txt` is the extracted text of the master business-rules document (glossary + numbered BR-## rules) that the trading logic (Bidding Room, Sale Systems, EMD, H1 award, ceilings) was built against. Treat this as the authoritative spec whenever a screen's behavior needs disambiguation beyond what's visible in the HTML — the numbered BR-## clauses are cross-referenced in comments/copy across the trading screens.

Key rules baked into the Bidding Room design (verify against the reference doc when implementing logic server-side):
- 4 Sale Systems: **Easy Auction** (sealed bids + inspection window), **Express Auction** (sealed bids, no inspection, fast-moving, starts once 3 EMDs pledged), **Buy Now** (fixed-price offer against Expected Value, Market Maker discretion, seller identity masked until EMD pledged), **Tender Auction** (invitation-only, Concierge-run, sealed offers, no public listing).
- **EMD (Earnest Money Deposit)** is mandatory before bidding, non-revisable once pledged (except Buy Now which allows revision until sale), sized at 10% of Reserve Value (auctions) or 10% of Expected Value (Buy Now).
- **No trader ever sees another trader's bid amount or identity** in any format — only their own bid and their own H1 ("highest bidder") / not-H1 status.
- **Winner = H1 (highest valid bid) only**, in Easy/Express/Tender — Star Rating plays NO role in who wins an auction. Star Rating only factors into Market Maker discretion in **Buy Now**.
- **150% bid ceiling**: no bid may exceed 150% of the current high bid — blocks fat-finger/price-jacking entries (auction formats only, not Buy Now).
- **Baton-pass**: if H1 defaults on payment, award passes to H2, then H3, before the lot is cancelled.
- Reserve Value (RV) is shown to the bidder for Easy/Express/Tender (floor price, not the seller's identity-revealing figure); Expected Value (EV) is shown only for Buy Now.

## Screens / Views
All screens live in `screens/`. Notable ones:

- **eBid Hub Landing.dc.html** — marketing entry point.
- **Onboarding.dc.html, KYC.dc.html** — signup + verification.
- **Buyer Dashboard / Seller Dashboard / Custodian Dashboard / Tenant Admin Dashboard.dc.html** — role-specific home screens.
- **Marketplace.dc.html** — lot browse/filter/sort/search.
- **Lot Detail.dc.html** — format-aware bid entry point, match tags, related lots.
- **Bidding Room.dc.html** — the core trading screen. Ticker header, breadcrumb, image gallery with tabs, 3-column desktop layout (asset/details left, bid panel center, related right) collapsing to single-column mobile with a bottom tab bar (Overview/Bid/Documents/Related). Format switch prop (`EASY`/`EXPRESS`/`BUYNOW`/`TENDER`) drives all conditional copy/logic — read the JS class fully before reimplementing.
- **AX Chronicle.dc.html / Lot Chronicle.dc.html / Audit Ledger.dc.html** — immutable event-log style ledgers per sale/lot.
- **Governance & Compliance**: Alerts, Delist Market Maker, Statutory Export, Media Waivers, AML Monitoring, Payout Reviews, Invoices, Rating Reviews, Consent Audit, Rules and Specifications, KYC Queue — each has an "i" info popup citing the governing rule and a link back to its originating dashboard.
- **Dispute Center.dc.html / Custodian Dispute Review.dc.html / TSX Master Dispute Review.dc.html / Dispute Resolution Process.dc.html** — dispute lifecycle.
- **Profile.dc.html, Preferences.dc.html, Payout Bank.dc.html, Rating History.dc.html, Star Ratings.dc.html** — account management.
- **Create Lot.dc.html, Lot Approval.dc.html, Lot Directory.dc.html, Lot Reach & Interest.dc.html, Trading Session Directory.dc.html, User Directory.dc.html** — seller/admin operational screens.
- Policy/legal: Privacy Policy, Cookie Policy, Terms of Usage, Terms and Privacy, Refund and Cancellation Policy, Grievance Redressal Policy, Security and Trust, Trust and Support, Dos and Donts, FAQ, Terminology, Pricing.
- **AdwitiX Screen Flow.dc.html** — a visual sitemap/flow diagram linking all screens together; use it to understand navigation and IA before implementing routing.

For any given screen's exact layout, colors, typography, and copy, read the file directly — inline styles carry all of the design detail (no separate stylesheet to cross-reference).

## Interactions & Behavior
Each `.dc.html`'s JS class documents its own state machine (form validation, conditional panels, tab switching, responsive collapse breakpoints). The Bidding Room is the most stateful screen — trace `renderVals()` and the format-metadata object there for the full conditional logic (H1 status, ceiling validation, EMD calc, seller masking, guide chips) before reimplementing the bid flow.

## Design Tokens
No centralized token file — colors/spacing/type are inlined per-file. When implementing in the real codebase, extract a token set (colors, spacing scale, radii, shadows, font stack) by diffing the recurring inline values across screens rather than re-deriving per screen.

## Assets
No external image/icon assets — screens use inline SVG or placeholder blocks where real photography/logos are expected (e.g. lot images in Bidding Room/Lot Detail galleries). Replace placeholders with real asset pipeline in the target app.

## Files
- `screens/*.dc.html` — all 54 designed screens (see list above).
- `reference/ADWITIX_Master_Business_Rules.txt` — extracted master business-rules document (glossary + BR-## numbered rules) governing the trading logic.

## Next Steps for the Developer
1. Push this folder into `ebid.oreo` (e.g. under `design/handoff/` or a docs branch).
2. Point Claude Code at the repo; have it read this README + the reference doc first, then each screen file as it implements the corresponding real component/page.
3. Confirm the target stack (framework, routing, state/data layer) is decided before implementation — this package doesn't presume one.
