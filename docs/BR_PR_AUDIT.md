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

---

## Update — reconciliation pass (D-65 through D-73, plus fresh code verification)

This document stopped being updated after D-64 even though nine more PRs merged (D-65–D-73) — nothing above was wrong, it was just out of date. This pass reconciles it against the current `main` (post `dev`→`main` merge) and independently re-verifies every remaining "open" item directly against the code, not by trusting any prior claim, including this document's own.

### Newly closed since D-64

- **✅ PR-09 (full media pipeline) — CLOSED (D-73).** The two gaps flagged in Section 2 above (synchronous uploads, no autosave) are both closed for real: a genuine background job queue (`media_upload_job` table, `MediaService::enqueueUploads()`/`processJob()`, `php spark process:media-queue`, wired into the scheduler) plus browser-localStorage form autosave. Verified directly: the migration, the queue split, and the spark command all exist and are wired as described.
- **✅ BR-53 (TDS deduction) — CLOSED (D-71).** Rate confirmed by the project owner at 10%. Verified directly: `settlement.tds_rate_percent`/`tds_amount` columns are real, and `SettlementService` genuinely computes and stores them at completion — not just schema, real computation.
- **BR-56 — extended.** Already ✅ closed at Phase 2 (D-57–59) for invoice *generation*; D-72 added real PDF rendering and a history view on top. Verified directly: `dompdf/dompdf` is a real dependency, `InvoiceController` genuinely renders a PDF via it, routes exist (`/account/invoices`, `/account/invoices/{id}/pdf`).
- **BR-06 — partially closed.** Logo/primary-color branding is now real (D-69/Phase 3D) — verified directly: `TenantController` genuinely writes `branding_logo_url`/`branding_primary_color`. **Still not built**: custom-domain Host-header routing — verified directly: no filter or route anywhere references `custom_domain` for actual request routing. The "subdomain" field remains a stored string with no routing behavior behind it.
- Everything else in D-65–D-70 (Seller Management/Consent Audit viewers, Phase 3A account management + transaction pages, Phase 3C browse/search, Phase 3C+ discovery, Phase 3D admin robustness, composite indexes) is real UX/infrastructure built on top of already-closed BRs — genuinely valuable, but not new BR/PR closures in its own right, so not itemized individually here.

### Confirmed still open — independently re-verified against code, not just re-stated

- **PR-04 (Sovereign Rule Revision).** Zero code anywhere (`RuleRevision`/`SovereignRule`/rule-engine search returns nothing). Every business rule remains hardcoded in PHP.
- **BR-46 / PR-10 (AI Listing Pre-Audit, Gemini).** Zero code anywhere — no Gemini client, no env var, not even a stub controller. Gated on a real API key that doesn't exist yet.
- **BR-52 / PR-30 (Chargeback Mitigation).** The only trace in the entire codebase is a rating-consequence *magnitude* for a chargeback event in `RatingService::NAMED_EVENTS` (BR-35's table) — a lookup value for if/when it's ever triggered, not an actual detection/representment workflow. Gated on the real payment gateway.
- **BR-17/18 (KYC verification, multi-address, encrypted banking).** `PartyModel::setKycStatus()` exists but is called from nowhere in the codebase — confirmed dead code. No `KycController`, no document upload path. Genuinely still Tier 4, unwired.
- **BR-55 (Tiered KYC & Enhanced Due Diligence).** Zero code — depends entirely on BR-17, which doesn't exist yet.

### Bottom line

**Five items remain open**, and all five are the same five this document has pointed to since D-64 — nothing new was missed, the reconciliation just confirms the list is still accurate and current:
1. PR-04 — Sovereign Rule Revision (large, no external blocker — a genuine build item)
2. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
3. BR-52 — Chargeback Mitigation (blocked on the real payment gateway)
4. BR-06's custom-domain routing (smaller, no external blocker)
5. BR-17/18/55 — KYC, multi-address, encrypted banking, enhanced due diligence (large, no external blocker, but deliberately deferred since early in the project — Tier 4)

Of these, **PR-04 and BR-06's custom-domain routing are the only "no external blocker, not yet attempted" items** — genuine next candidates if continuing to build. BR-17/18/55 (KYC) is large and was deliberately deferred, not overlooked; picking it up would be a real scope decision, not just clearing a backlog item.

---

## Update — three of the five items closed (D-74, D-75, D-76)

All three of the no-external-blocker items above are now built, merged into `main`, and independently re-verified — including a genuinely fresh checkout of `main` (not a local branch simulation): 58 migrations from zero, the full regression suite (488 assertions across 27 runnable engines, only the pre-existing `test:auditlog`/D-62 gap unrelated to any of this), and a live-HTTP smoke test confirming every affected route responds correctly post-merge.

- **✅ BR-06's custom-domain routing — CLOSED (D-74).** `TenantResolutionFilter` genuinely resolves a tenant from the Host header (exact `custom_domain` match, then `{label}.{platformHost}` subdomain match) on every request; `Home::index()`/`browse()` scope listings to the resolved tenant; the layout injects the tenant's logo/brand color/Terms link. Verified over real HTTP with Chromium, which caught and fixed a genuine bug: `esc($value, 'css')` was silently invalidating the tenant's brand color at the CSS-tokenizer level.
- **✅ PR-04 (Sovereign Rule Revision) — CLOSED (D-75).** `SovereignRuleService` + a Super Admin "Rules & Specifications" UI (`/admin/rules`) genuinely drive five previously-hardcoded thresholds live: BR-43's bid ceiling, BR-27's EMD percent, BR-49's shared high-value threshold (read by both `SettlementService` and `PayoutControlService` from the same rule), and BR-38's shadow-ban/crawl-back thresholds. Every edit is versioned (`sovereign_rule_revision`) and audited (BR-05 hash chain), gated on a mandatory Reason for Modification. No generic rule-expression evaluator was built — a freeform rule is a governance record only, honestly scoped per the original audit's own "rules-engine rewrite" caveat.
- **✅ BR-17/18/55 (KYC, multi-address, encrypted banking, enhanced due diligence) — CLOSED (D-76).** Full onboarding built on top of `party`'s existing Phase-0 schema: entity-type-specific questionnaire, a real AES-encrypted document vault, `party_address` (BR-18's four typed addresses), banking details, and a genuinely live gate (`KycService::requireVerifiedKyc()`) wired at the real user-facing pledge/listing entry points, not the Model layer. BR-55's enhanced-due-diligence threshold is itself a live Sovereign Rule (D-75's module), matching BR-55's own "not fixed by this document" text. Two pieces remain honestly manual rather than fabricated, per the project owner's explicit decision: PAN/GSTIN registry checks and Aadhaar tokenization are SaaS Admin manual actions, gated on real NSDL/GSTN/UIDAI API access that doesn't exist yet. Dossier review was deliberately routed to Super Admin rather than PR-15's literal "Tenant Admin," since KYC is party-level data with no owning tenant (BR-06: buyers are federated globally) — flagged and reasoned in `KycReviewController`'s own doc block.

### Bottom line (superseded by the re-audit below — kept for history)

**Two items remain open — both externally gated, nothing more to build without external access:**
1. **BR-46 — AI Pre-Audit** (blocked on a real Gemini API key)
2. **BR-52 — Chargeback Mitigation** (blocked on the real payment gateway)

There is no remaining "no external blocker" item on this list. Further progress requires the project owner to supply one of the two external dependencies above.

---

## Update — full re-audit against the replacement doc (BR-01–66 / PR-01–37)

The project owner supplied a replacement governing document (D-77, see `docs/source-documents/README.md`) — 5 new BRs (62–66) and 1 new PR (37), a substantially more decided Tech Stack section, and Phase 2 now explicit. This pass is a systematic re-check, not a re-statement of the prior "two items" conclusion above: every one of the 66 BRs and 37 PRs was checked for real code coverage (`grep -rl "BR-XX\b" app/`), not assumed from a prior document's claims — the same method D-43/D-64/D-73's passes used, applied fresh against the new range.

**Six real, previously-unflagged gaps found** (all pre-date the new document — they're gaps in the *original* BR-01–61 range that no prior audit pass caught, not something the new document introduced):

- **BR-15 (Sovereign Isolation — Super Admin Non-Participation).** Zero enforcement anywhere. The Super Admin is supposed to be structurally barred from bidding, listing, or otherwise participating — nothing in `BiddingService`, `ListingController`, or any bid/offer/listing entry point checks for or blocks a Super Admin party ID.
- **BR-07 (Salvage Asset Categorization & Scope Restriction).** The listing `category` field (`app/Views/listing/create.php`) is unrestricted free text (`<input type="text">`), not validated against BR-07's own 8-item permitted closed list. Nothing stops listing new retail-consumer goods, which BR-07 explicitly prohibits. The one file referencing "BR-07" in the codebase (`tenant_create.php`'s "Tenant Class (BR-07)" label) is a mislabeled comment — that field is governed by BR-09's tenant model, not BR-07 — so there is genuinely zero real BR-07 enforcement, not even a partial one.
- **BR-19 / PR-16 (Polymorphic Role Mapping's compliance-lockout cascade).** BR-19's own rationale text promises "automated cross-role lockout if a compliance flag is revoked" — e.g. a seller delisted for fraud on one role should have every other role automatically locked too. No such cascade exists; each role's suspension/delisting is handled independently.
- **BR-32 (Adjustable Buyer Fee & Escrow Deduction).** `buyer_fee_percent` exists only on the `tenant` table — a Tenant Admin can change their tenant's blanket default fee, but BR-32's actual text is "adjust the buyer's transaction fee **on any active listing**," implying per-listing/per-sale-event granularity. No such override exists; there is no listing- or sale-event-level fee column anywhere.
- **BR-41 (Seller Rating Visibility Pre-Bid).** `seller_star_rating` is shown in the `browse.php` discovery grid but not on `listing/show.php` — the actual page a buyer views and bids from. BR-41 specifically requires it visible "throughout the live event," which the detail/bidding page is, and the grid summary isn't a substitute.
- **PR-08 (Tenant Admin Onboarding & Promotion).** No Super Admin web UI exists to search the user directory and promote a buyer to Tenant Admin — only `spark grant:tenant-admin`, a CLI-only command. This one is already honestly self-flagged in the command's own comment ("Interim CLI bootstrap tool. No Super Admin panel exists yet...") — it just never made it into this audit document's tracked list until now. BR-44's auto-demote-prior-admin logic that PR-08 depends on **is** genuinely implemented inside that command (verified in `GrantTenantAdmin.php`); only the web UI itself is missing.

**Two items checked and confirmed already built** despite the new Tech Stack section calling them out (i.e., the platform is already ahead of what the document asks for here):

- **Audit-log DB-permission hardening (Tech Stack §3.6):** "no update or delete grant exists for any application role... including Super Admin's own operational database credentials." Genuinely true today — `2026-01-01-000028_CreateAuditLog.php` explicitly `REVOKE`s UPDATE/DELETE/TRUNCATE on `audit_log` from the application's own DB role and grants only INSERT/SELECT.
- **BR-08 (Turnover-Based SaaS Monetization):** the flat 0.5% SaaS fee is genuinely computed at both forfeiture (`EmdService::calculateForfeitureAllocation`) and settlement (`EmdService::calculateSettlementFee`, default `$saasFeePercent = 0.5`) — just never tagged with a "BR-08" comment anywhere, which is why the coverage grep initially read as zero.

**Two items checked and confirmed already satisfied by design**, no gap:

- **BR-30 (Format-Specific Inspection Rules):** satisfied by construction — Express genuinely has no inspection window (nothing was built, matching the rule), and Easy/Buy-Now's 60-minute grace window is BR-14's already-built mechanism, which BR-30 explicitly delegates to.
- **BR-37 (Rating Portability Across Tenants):** `star_rating`/`seller_star_rating` live on the `party` table itself, not any tenant-scoped table — structurally portable by design, satisfying BR-37 without needing a dedicated feature.

**New in this document, not yet built (expected — this is new scope, not a missed gap):**

- **BR-62–66 / PR-37 (Tenant API Access)** — an entirely new module. Zero code anywhere, as expected for a feature that didn't exist in the prior document.
- **Server-time integrity (Tech Stack §3.10)** — NTP sync verification and drift alerting. No code anywhere. This one is genuinely new: the prior Tech Stack document didn't specify it, so it wasn't a gap before, but it's now a real, unbuilt requirement.
- **Independent third-party security audit (Tech Stack §3.11)** — an organizational/procurement requirement (hire an external firm before go-live), not something to write code for.

**Unchanged from the prior pass:**

- **BR-46 (AI Pre-Audit)** — still blocked on a real Gemini API key.
- **BR-52 (Chargeback Mitigation)** — the payment gateway is no longer undecided (the new Tech Stack names **SabPaisa**), but a live chargeback/representment workflow still needs real gateway API credentials to integrate against, not just a vendor name.

### Bottom line (superseded by the fifth-pass re-audit below — kept for history)

**Ten items now tracked as open** — six real gaps in already-shipped BRs, one entirely new module, one new tech-stack requirement, and the two pre-existing external-vendor blockers:

1. BR-15 — Super Admin non-participation enforcement (no external blocker)
2. BR-07 — category closed-list enforcement (no external blocker)
3. BR-19 / PR-16 — compliance-lockout cascade (no external blocker)
4. BR-32 — per-listing buyer fee override (no external blocker)
5. BR-41 — seller rating on the listing detail page (no external blocker, small)
6. PR-08 — Tenant Admin promotion web UI (no external blocker, small — the underlying BR-44 logic already exists)
7. BR-62–66 / PR-37 — Tenant API Access (no external blocker, large — a whole new module)
8. Server-time integrity / NTP drift monitoring (no external blocker)
9. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
10. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials — vendor now named, credentials still needed)

Items 1, 2, 3, 5, and 6 are all genuinely small — each is a single, bounded fix, not a redesign. Item 4 is small-to-medium (one new column + one edit path). Item 7 is the only large item with no external blocker.

## Update — fifth full pass, post-fix verification + one new gap (D-85)

Re-audited from scratch against the same governing document (re-extracted
directly from the current `.docx` in `docs/source-documents/`, byte-for-byte
identical to the copy D-77 recorded — confirmed via `diff`, not assumed).
All 66 BRs and 37 PRs checked for real code coverage (`grep -rl "BR-XX\b"
app/` per item, cross-referenced against the current merged `main`), not
assumed from this document's own prior "closed" claims.

**Items 1–6 above (BR-15, BR-07, BR-19/PR-16, BR-32, BR-41, PR-08) and
item 8 (Server Time Integrity) confirmed genuinely merged into `main`** —
spot-checked each one's real implementation artifact directly (e.g.
`grep -rl "BR-15: the Super Admin holds" app/`, `ls app/Libraries/
ComplianceLockoutService.php`), not just trusted from the PRs' own
descriptions. All present. Item 7 (BR-62–66/PR-37, Tenant API) confirmed
still genuinely zero code anywhere (`grep -rl "BR-6[2-6]\b" app/` → no
hits) — still open, still the only large item with no external blocker.

**One new, previously-unflagged gap found**, missed by all four prior
passes (D-43, D-64, D-73, D-77) and not introduced but also not caught by
this session's own BR-32 work:

- **BR-31 (Dual-Fee Billing & Zero-Seller Model).** The text is explicit: the buyer's transaction fee is "adjustable at the Tenant Admin's discretion within a fixed band: a floor of 0.5%... and a ceiling of 5%... which may never be exceeded." Neither `TenantController::createSubmit()`/`editSubmit()` (the tenant-wide default) nor `TenantModel` validates the posted `buyer_fee_percent` against this band at all — a Tenant Admin can currently set it to any value, including 0%, negative, or far above 5%. The per-listing override built this session for BR-32 (`ListingController::updateFeeOverride()`) has the same gap: it validates 0–100 (a basic sanity bound against `EmdService::calculateSettlementFee()` throwing on a negative refund) but not the tighter 0.5–5 band BR-31 actually specifies. Sellers already pay a genuine, verified 0% (no fee-deduction code references a seller fee anywhere) — that half of BR-31 is satisfied by omission; only the band-enforcement half is missing.

**Everything else spot-checked this pass** (every item with a
suspiciously low or zero `grep` hit count that wasn't already an
explained case): BR-20 (Super Admin credential isolation — real,
substantive re-enrollment gate, not a stray comment), BR-49/PR-27
(High-Value Disposal — a genuine `high_value_disposal_record` table
insert exists, not just a threshold check), BR-45 (photo count 5–50 —
real `MIN_PHOTOS`/`MAX_PHOTOS` consts enforced in `MediaService`). All
confirmed solid.

### Bottom line (superseded by D-87/D-88 — kept for history)

**Ten items still tracked, now with one substitution**: BR-31's fee-band
validation gap replaces BR-15/BR-07/BR-19/BR-32/BR-41/PR-08/Server-Time
Integrity, all six of which are now closed and merged.

1. BR-31 — buyer-fee band (0.5%–5%) never validated server-side, tenant-wide or per-listing (no external blocker, small)
2. BR-62–66 / PR-37 — Tenant API Access (no external blocker, large — a whole new module)
3. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
4. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

Item 1 is a single, bounded fix (two validation checks, no schema
change). Item 2 is the only large item with no external blocker.

## Update — D-87/D-88: governing document replaced, commission model rebuilt

`ADWITIX_Master.docx` replaced `eBid_Hub_Unified_BR_PR.docx` as the
canonical governing document (D-87), rewriting BR-08/09/31–34/56/12
and PR-06/32 around a new Section 5 Business Model. Item 1 above
(BR-31's buyer-fee band) is **moot, not fixed** — BR-31 no longer
describes a tenant-adjustable 0.5%–5% band at all; it's now a single,
platform-wide, non-tenant-adjustable declining schedule
(`EmdService::calculateSuccessFee()`), so there is no band left to
validate. D-88 built the full replacement: the new Success Fee
schedule, the Fee Payer Election (`sale_event.fee_payer`), and the
monthly Tenant-billing mechanism (`TenantBillingService` +
`tenant_fee_ledger`/`tenant_monthly_invoice`) that collects a
Seller-Pays fee given the platform never touches the seller's 100%
sale-value proceeds directly. Full detail in `docs/DECISIONS.md` D-88.

### Bottom line (superseded by D-89 — kept for history)

**Three items tracked** — BR-31's gap is superseded (see above), not
counted separately:

1. BR-62–66 / PR-37 — Tenant API Access (no external blocker, large — a whole new module)
2. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
3. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

Item 1 is the only large item with no external blocker.

## Update — D-89: BR-62-66/PR-37 built

Item 1 above is built: OAuth2 client-credentials (self-hosted
substitution for the named Auth0 dependency, same pattern as BR-04's
TotpService), BR-66 tier-gated push/pull endpoints reusing the exact
same portal governance checks, BR-63 tenant-scoped visibility, and
PR-37's webhook events wired into the real lifecycle trigger points.
Full detail in `docs/DECISIONS.md` D-89.

### Bottom line (current)

**Two items left, both external-credential blocks — nothing left with
an internal-only blocker**:

1. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
2. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

## Update — D-90: BR-67 rollout gap found, master doc restructured (docs-only)

Raised directly by the project owner: BR-67 (Branded Terminology Layer)
was checked against the live application for the first time — no prior
audit pass had ever verified the half of BR-67's own text that isn't
about the data model ("Front-end copy... render the branded term").
Confirmed via direct `grep` across `app/Views/`: only four view files
use any branded term at all, and none do so systematically. This is a
real, previously-unflagged gap, now tracked below. `ADWITIX_Master.docx`
itself was restructured the same day (new Section 1: Terminology, full
section renumbering) — documentation only, no code changed by that
part. Full detail in `docs/DECISIONS.md` D-90.

### Bottom line (current)

**Three items — one newly surfaced, no internal-only blocker on any of them:**

1. **BR-67 — Branded Terminology Layer, live-UI rollout** (no external blocker, medium — apply the TSX/Market Maker/Trader/etc. mapping consistently across the portal's views, not just the four files that happen to use it today)
2. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
3. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

Item 1 is the only item left with no external blocker.

## Update — D-92: BR-67 live-UI rollout built

The one item left with no external blocker is now built. A new
`tsx_term()` helper (`app/Helpers/terminology_helper.php`) is the
single source of truth for BR-67's 7-row mapping; all 37 view files
found to use any of the 7 mapped technical terms as visible text —
not just the 4 that happened to already use branded terms — now
render through it, including the SaaS admin console
(`app/Views/admin/*`, 17 files), consistent with `public/pricing.html`
already branding that role "Custodian". Verified with a new
`test:terminology` CLI suite (22 assertions) plus real HTTP checks
against a running server confirming the branded terms actually render.
Full detail in `docs/DECISIONS.md` D-92.

### Bottom line (superseded below — kept for history)

**Two items left, both external-credential blocks — nothing left with
an internal-only blocker**:

1. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
2. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

## Update — D-93: independent counter-audit against a fresh PDF export

The project owner supplied a freshly-exported PDF and asked for a
genuine two-directional check: is everything except BR-46/BR-52
actually built, and is everything decided actually written into the
document. Confirmed via `pdftotext` extraction and a fresh `grep -rl
"BR-XX\b" app/` sweep across all 68 BRs, not by trusting this
document's own prior claims. Two real, previously-untracked findings
came out of it, both now fixed:

- **BR-65 (API Versioning Policy)** — the text explicitly bars a
  visible version number; the API shipped as `/api/v1/...` anyway.
  D-89 never actually addressed BR-65 despite building the rest of
  BR-62–66. **Fixed** — routes renamed to `/api/...`, verified over
  real HTTP.
- **BR-68 (Visual Identity)** — matched exactly on `pricing.html`
  (the only surface that existed when it was checked), but the live
  portal used an unrelated older palette. The project owner confirmed
  this should be app-wide, the same call made for BR-67. **Fixed** —
  `layouts/main.php`'s token values repainted to BR-68's palette and
  typography, verified over real HTTP.

Also found: **BR-53's TDS rate** was confirmed by the project owner at
10% back in D-71, computed correctly in code ever since, but the
document text still read "not fixed by this document" — never carried
forward through the D-77 document replacement. **Fixed** — document
text updated to state the confirmed 10% rate, via the docx skill.

Full detail in `docs/DECISIONS.md` D-93.

### Bottom line (current)

**Two items left, both external-credential blocks — nothing left with
an internal-only blocker, same two items as every pass since D-92**:

1. BR-46 — AI Pre-Audit (blocked on a Gemini API key)
2. BR-52 — Chargeback Mitigation (blocked on real SabPaisa API credentials)

## Update — D-94: Section 7 (AX Knowledge & Chronicle Framework) added, Phase 1 built

New scope, not a gap in an existing BR/PR — doesn't change the bottom
line above. The project owner supplied a concept paper; it's now
Section 7 of `ADWITIX_Master.docx`, with only 7.10 (the Trading
Session Chronicle — a system-authenticated, QR-verifiable report per
completed Sale Event) as active Phase 1 scope, built and verified
(`test:chronicle`, 22 assertions, plus real HTTP checks of the public
QR verification page and the certified PDF). Everything else in
Section 7 (Case/Asset entities, the other Chronicle types, Contributors,
the full Information Classification taxonomy, the other Dossier types,
the full Access & Visibility model, AI-authored narrative) is Section
7.11 — explicitly Phase 2, the same treatment already given Procurement
and Market Intelligence. Full detail in `docs/DECISIONS.md` D-94.

## Update — D-98: BR-46 built end to end, inert until a Gemini key lands

BR-46 (AI Listing Quality Pre-Audit) is no longer "zero code anywhere"
— every piece of it is built and verified except the one thing that
genuinely needs an external credential: a live call to Gemini itself.
Portal button, dedicated Tenant API endpoint (extending BR-46's own
"seller may trigger" text to cover Tenants integrating via the API,
per the project owner's explicit direction), tier gating identical to
Lot push, and the real Gemini REST contract are all real and exercised
by `test:aiaudit` (9/9) plus real HTTP against both the portal and API
surfaces. `GeminiPreAuditService::evaluate()` fails honestly — a plain
"not currently available" message, before any network attempt — when
`GEMINI_API_KEY` is unset, exactly as it is in this environment today;
it never fabricates a result. Full detail in `docs/DECISIONS.md` D-98.

**Still genuinely blocked, unchanged**: the model actually has to
return something for this to work end to end — that part cannot be
exercised without a real key, same honest limitation BR-52's
chargeback-detection code already carries.

## Update — D-99: BR-/PR- jargon swept from the live portal

Not a build-gap item — a UI/UX pass, first of a joint review the
project owner asked to start ("let's look together" on the live
product rather than a fixed brief). D-96/D-97 removed internal
BR-/PR- citations from the Chronicle report specifically; this found
and fixed the same pattern across the rest of the customer- and
Tenant-Admin-facing portal (27 view files), and along the way found
two real access-control gaps (permission-gated buttons rendered to
everyone, not just authorized roles) in `listing/show.php`, now fixed.
Full detail in `docs/DECISIONS.md` D-99.

## Update — D-100: platform renamed from "eBid Hub" to AdwitiX throughout

Same UI/UX review, second finding: the live portal's own name was
still "eBid Hub" — header, page titles, footer, TOTP issuer, legal
documents — the working/demo name from before the AdwitiX branding
(shield icon, full logo) was finalized. Corrected across 38 files;
the header/footer/favicon now use the real AdwitiX shield icon and
wordmark rather than invented placeholder text. Full detail in
`docs/DECISIONS.md` D-100.

## Update — D-101: shared design-system foundation, proved out on Home/Profile/KYC

Not a BR/PR build-gap item — a UI/UX foundation pass. A repo-wide
check found exactly one responsive `@media` rule across the entire
portal; every other screen had zero mobile treatment, including the
header nav itself (which visually broke on every page at phone
width). Added a real spacing/elevation/color-accent token system and
shared component classes (`.card`, `.field`, `.badge`, `.grid-2/3/4`,
responsive nav) to `layouts/main.php`, then proved the system out on
three pages: Home (bolder hero/format-card treatment), Profile
(reorganized from one unsorted row of 11 buttons into labelled
groups), KYC (fixed a real bug — Individual/Organization fields were
both always visible regardless of selection — plus card grouping).
The other ~75 view files still use the old inline-style pattern;
rolling the system out further is flagged as follow-up work, not yet
requested. Full detail in `docs/DECISIONS.md` D-101.

## Update — D-102: navigation-gap audit — 5 flagged items already wired, 3 genuine gaps closed

Not a BR/PR build-gap item. The project owner reported five specific
navigation gaps (logout, My Listings, My Bids, account/profile page,
searchable Browse); investigation confirmed all five were already
fully wired (verified via a real registered-account browser session,
not just a code read). The systematic audit that followed — every
static route cross-referenced against every actual link in the
codebase — found three routes genuinely unreachable from anywhere in
the app: `/cookie-policy`, `/account/invoices`, and the "File a
Dispute" form. All three now have a real entry point, confirmed
working over real HTTP. Full detail, screenflow diagram, and the
evidence table in `docs/DECISIONS.md` D-102.

## Update — D-103: Emergency Stop wired, Tenant Admin dashboard entry point wired

A deeper zone-by-zone sweep (auction, negotiate, reports, disputes,
settlement, TSX, tender, listing/event pages) found two more genuine
gaps, reported before fixing per instruction. BR-14 Emergency Stop
had zero UI trigger despite being fully built and tested — added to
the listing page, real end-to-end verified (real Tenant Admin login,
real click, sale event genuinely flipped to `cancelled` in the
database). The bigger one: the entire Tenant Admin dashboard zone had
no entry point anywhere after login — every sub-page linked back to
the dashboard, nothing linked to it. Added a header-nav link, shown
only to parties who actually administer a tenant, verified via a real
login and click resolving to the correct dashboard. Full detail in
`docs/DECISIONS.md` D-103.

## Update — D-104: production-readiness audit — real CSRF, real CSP, CI pipeline, backup script, stale docs fixed

Not a BR/PR build-gap item — a production-readiness pass, requested
directly: audit the whole platform for what's missing to run
full-fledged on the project owner's own cloud server, then close
whatever doesn't need external credentials. Five real gaps closed:
CSRF protection (was fully disabled repo-wide — added to all 90 POST
forms across 46 views, two real corruption/exit-code bugs found and
fixed along the way, verified with a real 403-reject/200-accept HTTP
round trip), a real Content-Security-Policy scoped to what the app
actually uses rather than generic defaults (was off entirely — a real
headless-browser check caught a first-pass regression that raw HTTP
headers didn't show, fixed and re-verified at zero real violations), a
GitHub Actions CI pipeline running all 35 suites on every push/PR
(didn't exist — building it surfaced and fixed a genuine
fixture-collision bug between two test commands that would have made
CI permanently red), a real `pg_dump`+media backup script with
retention pruning (no backup strategy existed anywhere), and two
stale docs (`SETUP.md`'s "Not yet built" list and `README.md`'s
unmerged-PR warning, both years out of date) corrected against what's
actually in the codebase today. Full detail in `docs/DECISIONS.md`
D-104.

## Update — D-105: Lot Reach & Interest built end to end (net-new feature, not a BR/PR item)

Not a BR/PR item — a net-new feature the project owner asked to be
built after reviewing the design handoff package's "Lot Reach &
Interest" screen and finding it had no backend anywhere. Real reversed
CLV matching (listing → matched buyers, extending
`ClvMatchingService`'s existing buyer-facing direction), real
per-listing view/favorite tracking, and a real in-app bulk-messaging
system with a genuine buyer-facing inbox — no external SMS/email
dependency, delivery is a real database-backed inbox page. `test:
listingreach`, 29/29 assertions, plus a full real HTTP click-through
(login → view listing → send message → real inbox delivery → mark
read), each step checked against real database state. Full detail in
`docs/DECISIONS.md` D-105. Also produced a further finding: 6 more
design-package screens (Buyer/Seller Dashboard, Rating History, Star
Ratings, Lot Directory, Trading Session Directory) have **neither** a
design nor a consolidated backend — recorded in
`docs/design/CLAUDE_DESIGN_HANDOFF.md` §2, flagged for a product
scoping decision, not built here.

## Update — D-106: the 6 no-mockup screens built (not a BR/PR item)

Not a BR/PR item — the scoping decision D-105's writeup said the 6
screens above needed. The project owner's instruction was explicit:
"tackle the 6 no-mockup screens next." All 6 built: new
`DashboardService` (Buyer/Seller Dashboard — a real consolidation of
each buyer/seller's existing separate pages, not a new source of
truth), `RatingEventModel::findForParty()` (Rating History — reads the
BR-35/BR-36 audit trail that already existed but was never shown back
to the party it happened to), a dedicated Star Ratings page (reads the
existing rating/shadow-ban/Crawl-Back fields), and new
`AdminDirectoryService` (Lot Directory / Trading Session Directory —
the real gap: the Custodian had no way to browse listings/sale events
platform-wide across every Tenant, only per-tenant pending queues
existed). No new migrations — every screen reads tables that already
existed. New suite `test:partydashboards`, 31/31 assertions, plus a
full real HTTP click-through for all 6 (real register→OTP→mPIN flow,
real TOTP-gated Super Admin login generating a genuine 6-digit code
from a real enrolled secret, correct content and correct auth-guarding
confirmed for every screen). Full detail in `docs/DECISIONS.md` D-106.
`docs/design/CLAUDE_DESIGN_HANDOFF.md` §2 updated: the "no backend at
all" list is now empty.

## Update — D-107: BR-65 formally amended, API now versioned (`/api/v1/`)

Not a repo-audit finding — a direct architecture-policy directive from
the project owner, checked against the codebase before acting on it
rather than assumed compliant. It directly conflicted with BR-65's own
text in `ADWITIX_Master.docx` (*"The API is not exposed to Tenants
with a visible version number"*) — the same wording D-93 already once
confirmed and built to. Surfaced the conflict and the two ways to
resolve it (header-based versioning vs. formally reversing BR-65); the
project owner chose to reverse it. `ADWITIX_Master.docx`'s BR-65 text
rewritten in place (Super-Admin-confirmed reversal, same precedent as
BR-53's TDS rate and BR-68's app-wide scope), all 6 Tenant API routes
renamed to `/api/v1/...`. Verified real, not assumed: `test:tenantapi`
25/25 (route-path-insensitive by design, confirms business logic
untouched), plus real HTTP — old path `/api/oauth/token` → clean 404
(no alias left behind), new `/api/v1/oauth/token` → real controller
response, `/api/v1/listings/{id}` unauthenticated → real 401 from
`ApiAuthFilter`, proving the filter reattached correctly. Full 36-suite
regression clean. Full detail in `docs/DECISIONS.md` D-107.

## Update — D-108: corrected a wrong claim about the WebSocket layer, then extended it to Buy-Now

Self-correction, not a new finding by someone else: sizing the Chief
Architect directive's retrofit items, I reported "no WebSocket layer
exists." That was wrong — a real sidecar (`realtime/server.js`, D-42,
extended D-52 for the Live Ticker) already existed; I'd only searched
`app/`, `composer.json`, and `public/`, missing the separate top-level
`realtime/` directory. Caught and corrected it myself before building
anything on the wrong premise, and re-verified the existing sidecar
genuinely still works with a real WebSocket client, not just trusted
the historical record.

Confirmed real coverage gaps once the audit was accurate: bids across
all three formats are broadcast, but **Buy-Now offers had zero
WebSocket coverage** — `OfferService` never called
`RealtimeBroadcastService`. Closed that gap: `offer_submitted`
(amount-free, to anyone watching the listing page) and `offer_received`
(the real amount, private, to the seller's own channel) on submit;
`offer_accepted` (public, matching the existing precedent that a
closed sale's winning amount is public) and `ticker_bid_update` (to the
winning buyer) on acceptance. Reused the existing per-party channel
every logged-in user already holds open (no new sidecar code, no
second connection). Found a real, pre-existing, unrelated privacy gap
while designing this — `ListingController::show()` renders every
Buy-Now offer's real amount to any visitor, not just the seller — left
unfixed (out of scope for this task) but confirmed the new broadcasts
don't add to it. Verified with a real three-client WebSocket test
against real `OfferService` calls, not mocked; full 36-suite regression
clean; `test:buynow` still 16/16. Full detail in `docs/DECISIONS.md`
D-108.

### Bottom line (superseded by D-109 — kept for history)

**Still four items with no path forward without the project owner
supplying something external — all four are genuinely build-complete,
this is a credentials gap, not a build-effort gap**:

1. BR-46 — AI Listing Pre-Audit, fully built, genuinely inert pending a Gemini API key
2. BR-52 — Chargeback Mitigation, fully built, blocked on real SabPaisa API credentials
3. A real payment gateway — EMD funding is simulated across every sale format; connects post-deployment
4. A real SMS provider — OTP is generated/rate-limited correctly but only ever shown on-screen, never sent

**No screens remain blocked on missing backend or product scoping.**
Every screen tracked in `docs/design/CLAUDE_DESIGN_HANDOFF.md` — the
original 53-screen design package plus the 6 no-mockup screens closed
out by D-106 — now has a real, tested backend. What's left everywhere
else is purely visual design work, not build work.

**Real-time (WebSocket) coverage, tracked precisely as of D-108, not
assumed complete**: bids (Easy/Express/Tender) and now Buy-Now offers
are covered. Settlement, Dispute, Rating, EMD cascade defaults, and
Admin actions still have zero broadcast coverage — each is real,
unbuilt scope, not an oversight to silently assume is fine.

**One real, pre-existing gap surfaced but deliberately not fixed**:
`ListingController::show()` renders every submitted Buy-Now offer's
real amount and status to any visitor of the listing page, not just
the seller — found while designing D-108's broadcasts, confirmed not
made worse by them, but the underlying page-level access control issue
itself is still open and needs its own decision.

## Update — D-109: WebSocket coverage extended to Settlement

Next item in the WebSocket retrofit after D-108's Buy-Now offers, per
explicit direction. `SettlementService::checkCompletion()` — the one
private method every settlement action (`confirmSellerNoc`,
`confirmBuyerNoc`, `submitRating` for both roles, and
`forceResolveStalled`) funnels through — now broadcasts a
`settlement_updated` event (full current gate state, not just a delta)
to both the buyer's and the seller's own party channel every time it
runs. Unlike Buy-Now's public listing page, a settlement has no "any
visitor" audience, so this deliberately never touches a sale_event
room — only the two parties' own private channels, reusing the same
per-party channel every logged-in user already holds open (no new
sidecar code, no second connection — same reuse precedent as D-108).

Client side reuses D-108's CustomEvent relay pattern in
`layouts/main.php`; `settlement/show.php` triggers a brief banner then
a full page reload rather than a DOM patch — deliberate, since this
page has several server-rendered blocks (invoices, TDS, Trading
Session Chronicle, the stalled-state panel) that only appear under
specific conditions, and re-deriving that logic in JS would duplicate
business/rendering logic the architecture directive explicitly warns
against.

Verified with a real two-client WebSocket test against a real
`SettlementService` driven through all four steps (not mocked): both
the buyer's and seller's channels received all four
`settlement_updated` broadcasts with correctly incrementing state,
ending at `status: completed`. Full regression: 36/37 real suites
clean (`test:settlement` 23/23); the sole non-pass is the same
pre-existing `test:auditlog` DB-naming gap, not a regression. Full
detail in `docs/DECISIONS.md` D-109.

### Bottom line (current)

**Still four items with no path forward without the project owner
supplying something external — all four are genuinely build-complete,
this is a credentials gap, not a build-effort gap**:

1. BR-46 — AI Listing Pre-Audit, fully built, genuinely inert pending a Gemini API key
2. BR-52 — Chargeback Mitigation, fully built, blocked on real SabPaisa API credentials
3. A real payment gateway — EMD funding is simulated across every sale format; connects post-deployment
4. A real SMS provider — OTP is generated/rate-limited correctly but only ever shown on-screen, never sent

**No screens remain blocked on missing backend or product scoping.**
Every screen tracked in `docs/design/CLAUDE_DESIGN_HANDOFF.md` — the
original 53-screen design package plus the 6 no-mockup screens closed
out by D-106 — now has a real, tested backend. What's left everywhere
else is purely visual design work, not build work.

**Real-time (WebSocket) coverage, tracked precisely as of D-109, not
assumed complete**: bids (Easy/Express/Tender), Buy-Now offers, and now
Settlement (dual-NOC + ratings + completion) are covered. Dispute,
Rating (outside the settlement flow), EMD cascade defaults, and Admin
actions still have zero broadcast coverage — each is real, unbuilt
scope, not an oversight to silently assume is fine.

**One real, pre-existing gap surfaced but deliberately not fixed**:
`ListingController::show()` renders every submitted Buy-Now offer's
real amount and status to any visitor of the listing page, not just
the seller — found while designing D-108's broadcasts, confirmed not
made worse by D-108 or D-109, but the underlying page-level access
control issue itself is still open and needs its own decision.

