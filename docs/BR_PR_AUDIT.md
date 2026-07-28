# eBid Hub — Complete BR/PR Audit (Phase 1 Scope)

Cross-referenced against the full BR-01 to BR-61 / PR-01 to PR-36 document and everything actually built (D-01 through D-55). Organized by what actually matters most first.

**Note on this document's history**: Section 1 below was the original audit (D-43). Several items it originally listed as major gaps have since been closed — kept here with a clear ✅ marker and a pointer to the decision that closed them, rather than deleted, so the history of what was found and when stays intact. A second, deeper pass through BR-50–BR-61 specifically (which the original audit under-covered) is appended near the end of this document under "Update — second full pass."

---

## 1. Genuinely major gaps at the time of the original audit — current status

### ✅ BR-05 / PR-05 — Immutable Audit Trail — **CLOSED (D-45 through D-49)**
Hash-chained, tamper-evident, verified against a genuine simulated attack (a raw superuser bypassing the application entirely). Wiring covers authentication, bidding, listing decisions, settlements, disputes, admin actions, the full EMD lifecycle, emergency stops, and scheduler runs. **Still open**: cold-tier archival (blocked on real Google Cloud credentials) and the export capability described in BR-58 (see the second-pass section below).

### ✅ BR-38 — Crawl-Back & Shadow Banning — **CLOSED (D-50, D-51)**
Full rehabilitation ladder built and enforced — not just tracked: a restricted buyer is genuinely blocked from bidding above their Low bracket, Shadow Banned sellers are genuinely excluded from Browse, and confirmed-fraud delisting is a real, platform-wide, Super-Admin-only action.

### ✅ BR-23 / CLV Matching — **CLOSED (D-52)**
Buyer preferences (categories, comfort states, budget range) built and driving real match computation.

### ✅ BR-48 / PR-26 — Live Ticker — **CLOSED (D-52)**
A genuine global sidebar with a real WebSocket architecture extension (buyer-scoped rooms, distinct from D-42's original sale-event rooms) — verified with a real bid producing a real live push to a buyer's own ticker.

### PR-04 — Sovereign Rule Revision (live rules engine) — **still open**
**What's specified**: A Super Admin UI to view, edit, and version business rules/thresholds directly, with a mandatory audit comment per change, live-updating application behavior.
**What exists**: Every business rule is hardcoded in PHP. Changing any threshold (the 150% ceiling, the 10% EMD baseline, anything) requires a code change and redeploy, not an admin action. Genuinely large — this is closer to a rules-engine rewrite than a feature addition, and hasn't been attempted.

### ✅ BR-47 / PR-25 — Related Auctions — **CLOSED (D-54)**
Listings grouped by seller-chosen label, format-constrained (no Express, must match the group's existing format), a real display strip verified with a working link between grouped items.

### ✅ BR-49 / PR-27 — High-Value Disposal Reporting — **CLOSED (D-54)**
Deterministic, non-discretionary — fires automatically at the exact ₹10L threshold stated in the document, surfaced on both the Tenant Admin and Super Admin dashboards.

### BR-46 / PR-10 — AI Listing Pre-Audit (Gemini) — **still open, gated on you**
Needs a real Gemini API key — same category as the payment gateway and SMS provider. No build-effort blocker, just an external account that doesn't exist yet.

### ✅ BR-24 — Shipping attribution — **CLOSED (D-55)**
Fixed or Variable cost declaration, buyer self-collection always available regardless, correctly kept separate from CLV's Basic-Cost-only matching.

### BR-61 — Seller Standing Review — **still open, now understood far more precisely**
See the second-pass section below — this turned out to be a genuinely distinct, much more detailed mechanism than the original audit captured.

### PR-28/29/30/31 — Payout verification, consent audit trail, chargeback representment, AML monitoring — **still open**
See the second-pass section below for the accurate, expanded detail on each.

---

## 2. Built, but narrower than the actual spec at the time — current status

### ✅ BR-21 / PR-18 — Asset-level role exclusion — **CLOSED (D-44)**
All three distinct bound roles (Surveyor, Yard Inspector, Physical Custodian) are now genuinely checked, each independently, verified over real HTTP with a real bound surveyor blocked from a real offer.

### ✅ PR-17 — Super Admin's own TOTP re-enrollment — **CLOSED (D-53)**
Genuinely closed a real, exploitable credential-hijack gap in already-shipped code, not just the originally-scoped self-service convenience — a regular session could previously overwrite an existing Super Admin's TOTP secret with zero confirmation of the old device.

### ✅ BR-35 — Graduated rating event tables — CLOSED to the extent real hook points allow (D-64)
**Spec**: a detailed table of ~37 named events, each with a specific point value (small 0.1-0.3★, moderate 0.4-0.7★, large 0.8★+), asymmetric caps (positive movement capped lower than negative).
**Built**: the full named-event table now exists as real, structured data (`RatingService::NAMED_EVENTS`), with a real `event_key` column recording which one fired. A genuine, previously-undiscovered gap closed: cascade defaults (BR-28/34) never touched the rating system at all — now wired to the exact 1st/2nd/3rd Default magnitudes. Dispute rulings now map to specific named events for the four category+role combinations that are unambiguous, rather than one flat generic magnitude. `delistSellerForFraud` and confirmed CBS violations (3rd+ offense) now apply their named "Reset to 1★"/"-2.0★" consequences. A new general "sustained clean streak" counter is wired. **Honestly still open** (no fabricated triggers): events needing a messaging system (prompt seller-query response), a KYC flow (confirmed false KYC/KYC fraud), a real payment gateway (chargeback), new pattern-counters (repeated baseless dispute/rejection patterns), or a new admin-flagging action this session didn't build (disruptive conduct, off-platform solicitation, dishonest defect disclosure, and the seller-side "quality" events). See D-64 for the full itemized list.

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

## Honest summary (as of the original audit — see below for current status)

At the time of the original audit, the transactional core was genuinely solid while a cluster of trust/safety and discovery features remained unbuilt. **Every item flagged in this original section has since closed**, except PR-04 (Sovereign Rule Revision), BR-46 (gated on a real Gemini API key), and BR-61 (Seller Standing Review, now far better understood — see below). See the CLOSED markers above for exactly which decision closed each one.

---

## Update — second full pass (this session)

The first audit above was built by searching the document broadly, and it under-covered BR-50 through BR-61 specifically — that range barely featured. A second, deliberately deeper pass through that section found a genuinely substantial amount of scope that was previously either unflagged or flagged with far less detail than the document actually specifies. Everything below is newly captured, not previously listed.

### Genuinely new major gaps — not built, not previously flagged in detail

**BR-51 / PR-29 — Consent Capture & Audit Trail.** The document requires *discrete, timestamped* consent events — not one signup-time checkbox — specifically: registration Terms acceptance, KYC consent, and critically, **a specific forfeiture acknowledgment shown immediately before every single EMD pledge**, naming the exact deposit amount and consequence, that the buyer must explicitly confirm. None of this exists. Every EMD pledge on the platform today happens via a plain "fund EMD" button with no acknowledgment step at all. This is a real gap given how much weight the document places on it as the platform's actual evidentiary defense in a dispute or chargeback.

**BR-56 / PR-32 — Mandatory Transaction Invoicing.** No invoice — GST-compliant or otherwise — is generated anywhere on settlement completion, for any format. The document treats this as automatic and non-optional on Buy-Now, Express, and Easy.

**BR-57 / PR-33 — Express Auction Defect Disclosure.** A mandatory structured checklist (known damage, missing components, non-functional aspects) is supposed to block Express listing submission entirely until completed, specifically *because* Express has no inspection window. No such checklist exists anywhere in the Express listing flow today — a seller can currently list on Express with zero disclosure of any kind.

**BR-61 / PR-36 — Seller Standing Review.** This is a distinct, much broader mechanism than D-51's confirmed-fraud delisting, and genuinely unbuilt as its own thing: every CBS violation, dispute outcome, and rejected auction is supposed to accumulate toward a periodic review — triggered by either an annual anniversary *or* crossing 10 complaints, whichever comes first — with a specific graduated CBS-offense ladder (1st/2nd warning only, 3rd/4th Tenant Admin discretion with mandatory SaaS Admin visibility, 5th+ SaaS Admin exclusive authority), dual Tenant/SaaS review, and three possible outcomes including suspension. None of this accumulation, triggering, or review logic exists. D-51's delisting is correctly scoped to confirmed fraud only and doesn't attempt to cover this.

**BR-60 / PR-35 — Tenant Media Waiver.** The CBS "no stock photography" rule (BR-59, built) has a defined exception path — a Tenant can apply to SaaS Admin for a category-specific waiver, reviewed, 12-month expiry with mandatory active renewal. No request/approval mechanism exists; the CBS prohibition is currently absolute with no waiver path at all.

**BR-58 — Statutory Books Export.** The audit trail itself (BR-05) is genuinely built and tamper-evident (D-45–49) — but the document's actual requirement is an *export* capability for statutory bookkeeping, and no export function of any kind exists yet. The data is there; nothing extracts it.

**✅ BR-50 / PR-28 — Payout Account Change Control — CLOSED (D-63).** Built the control process itself (OTP re-verification, mandatory 24-hour cooling-off before a new account becomes active for any payout, before/after audit logging) independent of a real payment gateway ever connecting. One bank-details field per party serves both buyer refunds (real today) and a future seller settlement payout (still offline per BR-10.1). High-value releases to a recently-changed account are deferred to a real Tenant Admin/SaaS Admin review queue, verified over real HTTP including a genuine 403 for an unauthorized logged-in party.

**✅ BR-54 / PR-31 — AML Transaction Monitoring — CLOSED (D-62).** Built literally to the governing text after the project owner brought a much larger risk/compliance platform concept to discuss first — confirmed as a genuine deviation from this BR's actual scope and left as a separate, unscheduled item rather than built here. All three named patterns are wired against real detection logic; the deposit-then-refund cycling pattern is fully live today, while the KYC-inconsistency and shared-funding-source patterns are honestly limited by upstream data that doesn't exist yet (no KYC data-entry flow, no real payment gateway) — flagged explicitly in code, not silently faked. Flags are visible/reviewable by SaaS Admin only, per PR-31's text, verified at the HTTP layer.

**PR-04 — Sovereign Rule Revision.** Confirmed still unbuilt, carried over from the original audit — every business rule remains hardcoded in PHP, not editable through any admin UI.

### Confirmed correctly deferred, not oversights

**BR-52 / PR-30 (Chargeback Mitigation)** and parts of **BR-55 (enhanced due diligence)** are genuinely blocked on the real payment gateway being connected — the same category as the payment gateway itself, not a build-effort gap. **BR-53 (TDS)** explicitly states its own rate needs confirmation from a tax advisor before implementation — the document itself defers this, not a gap in this build. **BR-55's base KYC requirement** depends on BR-17/18 (KYC), already known and explicitly deferred to Tier 4 since early in this project.

### Honest summary of this pass

The transactional core remains genuinely solid — this deeper pass didn't find anything wrong with what's built, only additional scope that was never built in the first place. The new gaps cluster almost entirely around **compliance and accountability infrastructure that only matters at real operating scale** — invoicing, consent evidencing, the Standing Review pattern-detection system, AML monitoring — rather than anything a single transaction today would actually fail without. If prioritizing from this new list specifically, **BR-51's per-pledge consent capture** is the one I'd flag first: it's small to build, and the document is explicit that it's the platform's actual evidentiary defense in a real dispute — a gap here has real consequences the moment a contested forfeiture happens, not just at scale.

---

## Phase 1 closure (D-56) — ✅ BR-51, ✅ BR-57, BR-35 re-scoped

**✅ BR-51 (consent capture)** and **✅ BR-57 (Express defect disclosure)** are closed — see D-56 for the full detail, including real HTTP verification of both the block and the success path.

**BR-35 was found to be far larger than originally scoped** — pulling the actual document revealed roughly 28 individually-named, individually-weighted graduated rating events, several depending on infrastructure that doesn't exist at all (participation streaks, fishing detection). Only one generic call site exists today. Rather than build a partial subset and call it addressed, this has been moved to Phase 4 as its own dedicated item, sized comparably to BR-38 and BR-61.

## Phase 2 closure (D-57, D-58, D-59) — ✅ complete

**✅ BR-56 (GST-compliant invoicing)** — automatic on Buy-Now/Easy/Express, explicitly excluded on Tender per the document's own text. Verified against the platform's own published Fee Schedule worked example.

**✅ BR-58 (statutory audit trail export)** — a CSV export layered on the existing hash-chained trail (D-45–49), Super Admin only, hot tier only (cold tier remains blocked on real GCS credentials, unchanged since D-45).

**✅ BR-60 (Tenant Media Waiver)** — full request/approve/decline/revoke/auto-lapse lifecycle, verified end-to-end over real HTTP including the unauthorized-request block and the disclosure requirement.

**Revised remaining phases:**
- **Phase 3 — ✅ fully complete**: BR-61 (Standing Review, D-60), BR-54 (AML monitoring, D-62), BR-50 (payout account change control, D-63).
- **Phase 4**: ✅ BR-35 (largely closed, D-64 — see remaining sub-items there), PR-04 (Sovereign Rule Revision, still fully open), BR-46 (gated on a Gemini key), BR-52 (gated on the real payment gateway)

