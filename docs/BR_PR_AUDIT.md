# eBid Hub — Complete BR/PR Audit (Phase 1 Scope)

Cross-referenced against the full BR-01 to BR-61 / PR-01 to PR-31+ document and everything actually built (D-01 through D-43). Organized by what actually matters most first.

---

## 1. Genuinely major gaps — not built at all

### BR-05 / PR-05 — Immutable Audit Trail (hot/cold tiering, hash-chaining, Log Reader)
**What's specified**: An append-only, tamper-evident audit trail — every record cryptographically hash-chained to the previous one, so retroactive tampering is detectable even with raw database access. Logs under 1 year stay "hot" in Postgres; older logs compress and move to "cold" cloud storage, retained 5 years minimum. A unified Log Reader merges both tiers, visible to Super Admin only.
**What exists**: Ordinary `created_at`/`updated_at` timestamps on individual tables. No dedicated audit_log table, no hash chaining, no hot/cold tiering, no Log Reader UI, no 5-year retention policy.
**Why it matters**: This underpins BR-58's statutory bookkeeping export too — that's blocked until this exists.

### BR-38 — Crawl-Back & Shadow Banning (rehabilitation system)
**What's specified**: A buyer below 2★ enters Crawl-Back — restricted to a tenant's "Low" value bracket until a defined number of clean transactions restores 3★. Below a further threshold, graduated visibility suppression (Shadow Banning) applies — not a hard block, just reduced platform-driven visibility. A platform-wide 1★ floor applies, raisable via a standing-deposit formula.
**What exists**: A single placeholder threshold value (D-08, still unconfirmed) sitting unused in `RatingService`. No value-bracket restriction, no visibility suppression, no standing-deposit formula — this entire rehabilitation mechanism is unbuilt.

### BR-23 / CLV Matching — buyer preferences and filtering
**What's specified**: Buyers set preferred categories, comfort inspection locations, and a budget range — driving personalized recommendations and notifications.
**What exists**: Nothing — no preference storage, no filtering beyond the basic category/format filters added in D-40's Browse page.

### BR-48 / PR-26 — Live Ticker
**What's specified**: A personalized, WebSocket-driven scrolling feed — a fixed panel showing the buyer's EMD balance, prioritizing their own active bids, filling remaining space with CLV interest matches, with Shadow Ban-aware behavior.
**What exists**: D-42 built genuine real-time *price updates on a listing page* — a real and working piece of the underlying mechanism — but not this specific personalized, cross-auction ticker experience. Depends on BR-23 (unbuilt) for the interest-match half anyway.

### PR-04 — Sovereign Rule Revision (live rules engine)
**What's specified**: A Super Admin UI to view, edit, and version business rules/thresholds directly, with a mandatory audit comment per change, live-updating application behavior.
**What exists**: Every business rule is hardcoded in PHP. Changing any threshold (the 150% ceiling, the 10% EMD baseline, anything) requires a code change and redeploy, not an admin action.

### BR-47 / PR-25 — Related Auctions
**What's specified**: Sellers group multiple independent listings sharing an origin (e.g., a flood-affected lot) into a browsable strip, each item still fully independent transactionally.
**What exists**: Nothing.

### BR-49 / PR-27 — High-Value Disposal Reporting
**What's specified**: Any completed sale over ₹10,00,000 automatically generates an RV-vs-final-price variance record, surfaced to both Tenant Admin and Super Admin, no manual trigger.
**What exists**: Nothing — no threshold check, no auto-generated record.

### BR-46 / PR-10 — AI Listing Pre-Audit (Gemini)
**What's specified**: An optional, advisory Gemini-powered check before submission — completeness score, title suggestions, missing B2B metadata flags.
**What exists**: Nothing.

### BR-24 — Shipping attribution
**What's specified**: Seller toggles shipping on/off at listing time, sets Fixed or Variable cost; buyer can always self-collect at no shipping cost.
**What exists**: Nothing — no shipping fields anywhere on a listing.

### BR-61 — Seller Standing Review
**Known and already flagged** (D-23/D-30) as deferred to Tier 4. Confirmed still unbuilt — the system-initiated review consolidating CBS violations and dispute outcomes into one periodic case.

### PR-28/29/30/31 — Payout verification, Terms/consent audit trail, chargeback representment, AML monitoring
All four unbuilt. PR-29's per-pledge consent logging in particular is worth flagging — right now there's no recorded acknowledgment of the specific forfeiture consequence at the moment a buyer pledges EMD.

---

## 2. Built, but narrower than the actual spec — needs correction, not new construction

### BR-21 / PR-18 — Asset-level role exclusion
**Spec**: blocks three *distinct* bound roles — Surveyor, Yard Inspector, and Physical Custodian — any of whom could be a different person from the others.
**Built**: only a single `inspector_party_id` field is checked. If a listing has a separate surveyor or custodian bound to it, they are currently **not** blocked from bidding. This is a real conflict-of-interest gap, not just a naming mismatch.

### PR-17 — Super Admin's own TOTP re-enrollment (self-service)
**Spec**: Super Admin submits a credential-change request while their *current* TOTP still works, confirms with it, and re-enrolls a new device — no server access needed.
**Built** (D-43... actually D-29/D-41): only the "device is already lost, no working TOTP at all" fallback via `reset-totp` (CLI, requires Arpit). The self-service "I still have my old device, let me switch to a new one" path was never built — every TOTP change currently requires server access, even routine ones.

### BR-35 — Graduated rating event tables
**Spec**: a detailed table of named events, each with a specific point value (small 0.1-0.3★, moderate 0.4-0.7★, large 0.8★+), asymmetric caps (positive movement capped lower than negative).
**Built**: `RatingService` has upgrade/downgrade/approval-gating logic, but I have not verified it implements this *specific, granular table* of named events rather than simpler fixed increments — worth a dedicated review pass against the actual table.

### BR-06 — Tenant branding and custom domains
**Spec**: logo/brand-color upload, custom domain mapping, Host-header-based white-label routing.
**Built**: tenant creation (name, class, subdomain string, fee %) exists, but no branding asset upload and no actual Host-header routing logic — the "subdomain" field is currently just a stored string, not something that changes what a visitor sees.

### PR-09 — Media upload pipeline
**Spec**: background worker queue for bulk uploads (non-blocking), browser-localStorage autosave of in-progress forms.
**Built**: D-43 closed the *compression* half of this gap for real (genuine WebP/video transcoding). Uploads are still synchronous (the page waits during compression) and there's no autosave — both still open.

---

## 3. Confirmed correctly built, matching the actual spec

Auth (BR-02/03), Tenant Admin delegation (BR-09) including the seller-suspension cascade, EMD escrow segregation and per-format routing (BR-25/26/27), the H1→H2→H3 cascade and forfeiture allocation (BR-28/34), Buy-Now EMD adjustment (BR-29), format-specific inspection rules (BR-30), dual-NOC settlement (BR-33), rating approval-gating (BR-36), rating portability (BR-37), stall resolution (BR-39), the Dispute Resolution Framework's core (BR-40, minus the Standing Review category which depends on BR-61), seller rating pre-bid visibility (BR-41), Buy-Now trust-over-price discretion (BR-42), the 150% anti-jacking ceiling (BR-43), single Tenant Admin limit (BR-44), the two-tier media standard (BR-59), and — as of D-43 — real server-side media compression (BR-45's compression half specifically).

---

## 4. Deliberately deferred, not oversights

- **Payment gateway, SMS provider, bank verification** — explicitly stubbed throughout, connects post-deployment (your own earlier decision)
- **KYC (BR-17/18)** — Tier 4, deferred
- **Phase 2 entirely** — Reverse Auction/Procurement, Market Intelligence price guides — the document itself scopes these out of Phase 1

---

## Honest summary

The **transactional core is genuinely solid** — everything money touches (EMD, cascade, settlement, disputes, ratings' approval gates) matches the spec closely. The gaps cluster in two places: **trust/safety mechanisms that only bite at scale** (audit trail, Shadow Banning, Standing Review — things that matter more once there's real transaction volume to police) and **discovery/convenience features** (CLV matching, Live Ticker, Related Auctions, AI pre-audit — things that make the platform pleasant to use, not things that make a transaction fail if missing).

If I had to pick where real risk sits: **BR-05's audit trail** is the one I'd prioritize first among the unbuilt items — everything else in this list is either a UX gap or a growth-stage safety net, but a tamper-evident audit trail is the kind of thing that's much harder to retrofit convincingly after real transactions have already happened without it.
