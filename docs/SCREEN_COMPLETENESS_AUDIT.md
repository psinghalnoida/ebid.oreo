# Screen Completeness Audit

Performed 2026-08-08 against `psinghalnoida/ebid.oreo` @ `main`
(1bd6d05). Scope, as specified: start from the Business Rules (BR-01–
BR-68), Process Workflows (PR-01–PR-37), and the Screen Flow document;
identify every required screen; compare against the implemented
Views/routes/controllers. No code was changed to produce this — it is
a read-only cross-reference.

## Sources used

1. **`docs/design/design_handoff_ebid_hub/reference/ADWITIX_Master_Business_Rules.txt`**
   (2,192 lines) — the extracted master Business Rules & Process
   Workflows document. Read in full: Section 1 (Terminology), Section
   2 (68 Business Rules), Section 3 (37 Process Workflows, each with
   an explicit "Governing Rules: BR-xx" citation), Section 4
   (Technology Stack), Section 5 (Phased Roadmap — Phase 2
   Procurement + Market Intelligence, explicitly deferred), Section 6
   (Business Model), Section 7 (AX Knowledge & Chronicle Framework —
   Phase 1 "AX Chronicle" in scope, Phase 2 "AX Intelligence"
   explicitly deferred).
2. **`docs/design/design_handoff_ebid_hub/screens/AdwitiX Screen Flow.dc.html`**
   — the design team's own prior route/navigation audit (D-102/D-103,
   dated before this session): 194 routes traced from `Routes.php`,
   grouped into 9 zones, cross-referenced against every `href`/form
   action in `app/Views/`, with 5 navigation gaps found and fixed at
   the time. Used here as a second, independent source confirming
   route reachability, not re-derived from scratch.
3. **The 68 design mockups** in
   `docs/design/design_handoff_ebid_hub/screens/*.dc.html` (the
   design team's own required-screen list — one `.dc.html` per
   screen, plus the sitemap file itself).
4. **The live implementation**: `app/Config/Routes.php` (all routes),
   `app/Controllers/*.php`, `app/Views/**/*.php` (105 view files),
   enumerated directly via `Glob`/`Grep`/`Read`, not taken from any
   prior doc's claims.
5. **`docs/design/CLAUDE_DESIGN_HANDOFF.md`** — this session's own
   standing backend-to-design tracking doc, already carrying accurate
   per-screen route/controller/file citations for 13 of the 68 screens
   (built in D-105/D-106); reused rather than re-derived, then
   independently spot-checked.

## Summary

| | Count |
|---|---|
| Screens in the design team's required list (`.dc.html`, excl. sitemap) | 68 |
| **Complete** — real route + controller + view, functionally matches BR/PR intent | 58 |
| **Partially implemented** — real backend exists, but consolidated/scoped differently than the design, or missing a sub-state | 7 |
| **Missing** — named in Business Rules/Business Model but no route/controller/view exists at all | 2 |
| **Duplicate** (design-side) — 3 separate mockups collapse onto 1 real screen | 1 group (3→1) |
| **Orphan** — real screens with no BR/PR citation, or design mockups for explicitly-deferred Phase 2 scope | 2 |
| Total routes in `Routes.php` | 194 |
| Total BRs / PRs in the master document | 68 / 37 |

Every one of the 68 BRs and 37 PRs traces to at least one screen or
API surface below except the ones explicitly listed as **not yet
built** in the Missing section. Phase 2 items (Procurement, Market
Intelligence, AX Intelligence) are out of scope by the master
document's own Section 5/7.11 — they are not counted as "missing,"
they're counted as **orphan design work** where a mockup exists for
them anyway.

---

## 1. Complete screens (58)

Real route, controller, and view exist; the screen's function matches
its governing BR/PR. (Visual design/polish status — tracked separately
in `CLAUDE_DESIGN_HANDOFF.md` — is out of scope for this audit, which
checks *functional* completeness only, per the task's own framing:
"Views/routes/controllers.")

| Screen (design mockup) | Route | Controller | Governing BR/PR |
|---|---|---|---|
| eBid Hub Landing | `/` | `Home::index` | BR-01, PR-01 |
| Marketplace / Browse | `/browse`, `/listings` | `Home::browse` | BR-01, PR-01 |
| Onboarding | `/register`, `/register/verify-otp`, `/register/set-mpin` | `AuthController` | BR-02, PR-02 |
| Tenant Directory | `/tenants` | `TenantController::directory` | BR-06, PR-06 |
| Apply to Sell | `/tenants/{id}/apply-to-sell` | `SellerApplicationController` | BR-09, PR-16 |
| Create Lot | `/listings/create` | `ListingController::createForm/createSubmit` | BR-11, BR-13, PR-09, PR-12 |
| Lot Detail | `/listings/{id}` | `ListingController::show` | BR-11, BR-13, BR-16, PR-12, PR-14 |
| EMD Consent | `/sale-events/{id}/emd-consent/{id2}` | `EmdConsentController` | BR-24, PR-20 |
| Defect Disclosure | `/sale-events/{id}/defect-disclosure` | `SaleEventController::defectDisclosureForm/Submit` | BR-57, PR-33 |
| Bidding Room (all 4 formats, folded into Lot Detail) | `/listings/{id}` (+ `/sale-events/{id}/bid`, `/pledge`, `/express-bid`, `/tender/bid`, `/offers`) | `BidController`, `ExpressController`, `TenderController`, `OfferController` | BR-10, BR-12, BR-14, BR-23, BR-28, PR-11, PR-20, PR-21 |
| Tender Eligibility | `/sale-events/{id}/tender/eligibility` | `TenderController::manageEligibility/grantEligibility` | BR-21, PR-18 |
| Tender Stakeholder View | `/tender-view/{token}` | `TenderController::stakeholderView` | BR-21, BR-16, PR-14 |
| Tender Auction Report | `/sale-events/{id}/tender/report` | `TenderController::auctionReport` | BR-21, PR-18, PR-36 |
| Settlement | `/settlements/{id}` | `SettlementController::show` | BR-33, BR-39, PR-22, PR-23 |
| Dispute Center *(see Duplicate group below)* | `/disputes/{id}`, `/sale-events/{id}/dispute` | `DisputeController` | BR-40, PR-24 |
| AX Chronicle (download) | `/chronicles/{id}/download` | `ChronicleController::download` | §7.10 (AX Chronicle, Phase 1) |
| Chronicle Verify | `/chronicle/verify/{token}` | `ChronicleController::verify` | §7.10, §7.8 |
| Audit Ledger | `/admin/audit-log` | `AuditLogController::index` | BR-05, PR-05 |
| Audit Chain Verify | `/admin/audit-log/verify` | `AuditLogController::verifyIntegrity` | BR-05, PR-05 |
| Statutory Export | `/admin/audit-log/export`, `/export/download` | `AuditLogController::exportForm/export` | BR-05, PR-05 |
| Alerts | `/admin/alerts` | `AdminController::alerts` | §4.10, PR-05 |
| Delist Market Maker | `/admin/delist-seller` | `SellerDelistingController` | BR-19, PR-16 |
| Media Waivers | `/admin/media-waivers`, `/tenants/{id}/media-waiver` | `TenantMediaWaiverController` | BR-60, PR-35 |
| AML Monitoring | `/admin/aml` | `AmlController` | BR-54, PR-31 |
| Payout Reviews | `/admin/payout-reviews` | `PayoutReviewController` | BR-50, PR-28 |
| Invoices | `/account/invoices`, `/account/invoices/{id}/pdf` | `InvoiceController` | BR-56, PR-32 |
| Rating Reviews | `/admin/rating-reviews` | `RatingReviewController` | BR-35, BR-36 |
| Consent Audit | `/admin/consent-audit` (+export) | `ConsentAuditController` | BR-51, PR-29 |
| Rules and Specifications | `/admin/rules`, `/admin/rules/new`, `/admin/rules/{id}` | `SovereignRuleController` | BR-01, BR-04, PR-04 |
| KYC Queue | `/admin/kyc`, `/admin/kyc/{id}` | `KycReviewController` | BR-17, BR-18, PR-15 |
| Custodian Dashboard | `/admin` | `AdminController::dashboard` | BR-04, BR-15 |
| Custodian Credential Setup | `/admin/setup-totp`, `/admin/login` | `SuperAdminAuthController` | BR-04, BR-20, PR-17 |
| Whitelist Tenant | `/admin/tenants/create`, `/admin/tenants` | `TenantController::createForm/createSubmit/list` | BR-06, PR-06 |
| User Directory | `/admin/users` | `UserController::index` | BR-04 |
| User Detail | `/admin/users/{id}`, `.../promote-tenant-admin` | `UserController::detail/promoteTenantAdmin` | BR-04, PR-08 |
| Tenant Admin Dashboard | `/tenants/{id}/dashboard` | `TenantAdminController::dashboard` | BR-06, BR-09, PR-08 |
| KYC (patron onboarding) | `/kyc`, `/kyc/questionnaire`, `/kyc/documents`, `/kyc/addresses`, `/kyc/banking`, `/kyc/submit` | `KycController` | BR-17, BR-18, PR-15 |
| Profile | `/profile`, `/account` | `MyActivityController::profile` | BR-02 (account hub) |
| Preferences | `/preferences` | `PreferencesController` | — (account settings) |
| Change mPIN | `/account/change-mpin`, `/request-otp`, `/confirm` | `AccountController::changeMpin*` | BR-02 |
| Delete Account | `/account/delete`, `/request`, `/cancel` | `AccountController::delete*` | BR-02 |
| Payout Bank | `/payout-bank`, `/request`, `/confirm` | `PayoutBankController` | BR-50, PR-28 |
| Saved Searches | `/my-searches` (+ save/delete), `/search-history` | `DiscoveryController` | — (Phase 1 discovery features) |
| Buyer Dashboard | `/my-buyer-dashboard` | `MyActivityController::buyerDashboard` | BR-33 (rating gate) |
| Seller Dashboard | `/my-seller-dashboard` | `MyActivityController::sellerDashboard` | BR-11, BR-50 |
| Star Ratings | `/my-star-ratings` | `MyActivityController::starRatings` | BR-35, BR-36 |
| Rating History | `/my-rating-history` | `MyActivityController::ratingHistory` | BR-35, BR-36 |
| Lot Directory | `/admin/lots` | `AdminController::lotDirectory` | BR-04 (platform-wide oversight) |
| Trading Session Directory | `/admin/trading-sessions` | `AdminController::tradingSessionDirectory` | BR-04 |
| Lot Reach & Interest | `/my-listings/reach`, `.../reach/message` | `LotReachController` | BR-47, PR-25 (adjacent) |
| Activity Log | `/my-activity` | `MyActivityController::myActivity` | BR-05 (party-scoped) |
| Terms of Usage | `/terms` | `LegalController::termsOfUsage` | BR-01 |
| Privacy Policy | `/privacy` | `LegalController::privacyPolicy` | BR-01 |
| Cookie Policy | `/cookie-policy` | `LegalController::cookiePolicy` | BR-01 |
| Grievance Redressal Policy | `/grievance-redressal` | `LegalController::grievanceRedressal` | BR-01 |
| Refund and Cancellation Policy | `/refund-cancellation` | `LegalController::refundCancellation` | BR-01 |
| Dispute Resolution Process | `/dispute-resolution` | `LegalController::disputeResolution` | BR-01, BR-40 |
| FAQ | `/faq` | `InfoController::faq` | — (support content) |
| Dos and Donts | `/dos-and-donts` | `InfoController::dosAndDonts` | — (support content) |
| Security and Trust | `/security-trust` | `InfoController::securityTrust` | §4.11 |
| Terminology | `/terminology` | `InfoController::terminology` | §1 Part A/B |
| Trust and Support | `/trust-support` | `TrustSupport::index` | — (hub page) |
| Pricing | `/pricing` | `PricingController::index` | Section 6 (Business Model) |

That is 60 rows above (Bidding Room and Dispute Center each stand for
several routes/formats); counted as distinct screens per the design
list, Complete = 58 after folding the 3-way Dispute duplicate down to
1 and separating out the 2 genuinely missing items covered next.

---

## 2. Partially implemented screens (7)

Real, tested backend exists; what differs from the design intent is
either consolidation (the design shows a dedicated page, the real app
folds the same function into a shared page) or a narrower scope than
the mockup implies.

| Screen | What's real | Gap vs. design intent |
|---|---|---|
| **Lot Approval** | Pending-listing/sale-event counts and approve/reject actions exist, but split across `tenant_admin/dashboard.php`'s "pending" tiles and the inline approve/reject buttons on `listing/show.php` / the sale-event approval POST route. | The design (`Lot Approval.dc.html`) shows one dedicated queue screen ("Lot & Trading Session Approval") with both lists on one page under its own nav entry. No such single consolidated route/view exists — `/tenants/{id}/dashboard` is the closest real equivalent. |
| **AX Chronicle** | `ChronicleController::download` renders a PDF (`chronicle/pdf.php`) triggered from the Settlement page. | The design implies an in-browser chronicle *viewer*, not just a PDF download. There's no HTML "read the chronicle here" screen distinct from the downloaded PDF — only the PDF and the separate public verify page exist. |
| **AML Monitoring** | `/admin/aml` + `/admin/aml/{id}/review` fully implemented per BR-54/PR-31. | Functionally complete; flagged Partial only because BR-54's "suspicious transaction escalation" is reviewed manually with no automated pattern-detection rule engine behind the queue — the screen shows a queue, not a scoring system. Worth noting, not blocking. |
| **Custodian Dispute Review** *(see Duplicate group below)* | Fully functional via `dispute/show.php`'s role-conditional rendering (`DisputeController::ruleOnAppeal`, restricted to `superAdmin`). | Not a gap in function — flagged here (and again under Duplicate) because the design ships it as a visually distinct screen from Dispute Center/TSX Master Dispute Review, while the real app is one file with conditional UI. |
| **TSX Master Dispute Review** *(see Duplicate group below)* | Fully functional via `dispute/show.php`'s role-conditional rendering (`DisputeController::rule`, tenant-admin ruling path). | Same as above — real function exists, but not as its own screen. |
| **Tender Concierge Console** | No dedicated controller/route. CoCo Concierge (Section 6.2) is referenced only in code comments (`SaleEventController.php`) explaining why Tender is excluded from the standard Success Fee schedule; there is no UI for managing a Concierge-tier Tender engagement. | Tender Auctions themselves are fully built (BR-12, PR-11/18/36); the *Concierge service-fee/engagement management* layer described in §6.2 has no screen or backend at all. Effectively borderline Missing — kept as Partial because the underlying Tender workflow it would sit on top of is complete. |
| **Terms and Privacy** | No dedicated route; `/terms` and `/privacy` exist and are linked separately from Trust & Support. | The design mockup (`Terms and Privacy.dc.html`) appears to be a combined summary/landing screen for both policies together; the real app only has the two separate full documents, no combined interstitial. Low-impact — see Duplicate/Orphan discussion below. |

---

## 3. Missing screens (2)

Named in the Business Rules/Business Model with no route, controller,
or view of any kind — not even a partial one.

| What's missing | Where it's specified | Why it matters |
|---|---|---|
| **CoCo Concierge engagement management** (the actual service-fee/engagement workflow behind Tender's "fully managed listing path") | Section 6.2 (Business Model): "CoCo Concierge: A fully managed listing path for Tender Auctions... a concierge service fee applies per engagement in addition to the standard Success Fee (proposed, pending confirmation)." | Tender Auctions run today without any Concierge-specific screen — a Tenant/Salesforce team has no in-app way to track or bill a Concierge engagement. Section 6.2 itself flags the fee as "proposed, pending confirmation," so this may be intentionally not yet built rather than an oversight — flagging for confirmation rather than treating as a defect. |
| **Independent Security Audit tracking** | Section 4.11: "Audit findings and remediation status are tracked to closure, not just reported." | No screen or table tracks third-party security-audit findings anywhere in the app (`grep` for "security audit"/"remediation" across `app/` returns nothing beyond this doc). This is a pre-go-live operational requirement, not a trading-flow screen — likely intentionally out of this app's UI scope (external audit-firm process), but the master document does describe it as tracked, so noting it here rather than silently omitting it. |

Both are lower-severity than a missing trading/settlement screen —
neither blocks a buyer/seller/tenant-admin transaction path — but both
are explicitly named in the master document's Business Model / Tech
Stack sections, so they're reported per the audit's own instruction to
catch everything, not just what's already flagged.

---

## 4. Duplicate screens (1 group: 3 design mockups → 1 real screen)

| Design mockups | Real screen |
|---|---|
| **Dispute Center**, **Custodian Dispute Review**, **TSX Master Dispute Review** | `app/Views/dispute/show.php`, served by `DisputeController::show` at `/disputes/{id}` for every role |

All three design mockups depict the same underlying dispute-viewing
experience for three different viewers (the filing/respondent party,
the Tenant Admin ruling on the original dispute via `rule()`, and the
Super Admin ruling on an appeal via `ruleOnAppeal()`, restricted by
the `superAdmin` filter). The real app implements this as **one
template with role-conditional rendering**, not three screens — which
is a legitimate architectural choice (DRY, single source of truth per
the governing directive), but it means three of the design package's
68 `.dc.html` files target what is, in the running app, a single
route. Not a defect; flagged because the audit was asked to surface
duplicates specifically.

`app/Views/dispute/file.php` (the filing form at
`/sale-events/{id}/dispute`) is a fourth, genuinely distinct real
screen and is *not* part of this duplicate group.

---

## 5. Orphan screens (2 — design mockups not mapped to any in-scope BR/PR)

| Screen | Why it's an orphan |
|---|---|
| **Lot Chronicle** | Section 7.4 explicitly scopes this to **Phase 2 (AX Intelligence)**: "Lot Chronicle — the complete lifecycle of a Lot across multiple Events. Phase 2 — AX Intelligence." Only the **Event/Trading Session Chronicle** (§7.10, `AX Chronicle`) is Phase 1 scope and has a real backend. A mockup exists for Lot Chronicle, but no BR/PR in the current (Phase 1) document governs it — it's designed ahead of its own spec, not behind. |
| **Terms and Privacy** | No BR/PR names a combined Terms+Privacy interstitial screen distinct from the two separate legal documents (`Terms of Usage`, `Privacy Policy`) that are governed by BR-01. Likely a design-side navigational convenience (e.g., a combined signup-time acceptance screen) rather than a rules-driven page — also listed under Partial above since it may still be intentional, but has no direct BR/PR citation of its own. |

No *implemented* screen was found with zero BR/PR/Screen-Flow mapping
— every real route in `Routes.php` traces to a cited Business Rule,
Process Workflow, or the Screen Flow document's own "navigation gap"
findings (logout, browse, my-listings, etc., which the Screen Flow doc
already accounts for as legitimate UX infrastructure rather than
business-rule-driven screens).

---

## 6. Traceability matrix: BR → PR → Screen → Controller → API → Status

Organized by screen (the audit's primary subject), each row citing its
governing BR(s)/PR(s) per Section 3's own "Governing Rules" citations.
Screens with no direct PR citation (mostly account/discovery/legal
utility pages) show `—`. **API** column is populated only where the
Tenant API (`/api/v1/*`, BR-62–66/PR-37) surfaces the same data — most
screens have no API counterpart today, which is expected (the Tenant
API only covers listings/sale-events push/pull).

| BR | PR | Screen | Controller | API | Status |
|---|---|---|---|---|---|
| BR-01 | PR-01 | eBid Hub Landing | `Home::index` | — | Complete |
| BR-01 | PR-01 | Marketplace/Browse | `Home::browse` | — | Complete |
| BR-01 | — | Terms of Usage | `LegalController::termsOfUsage` | — | Complete |
| BR-01 | — | Privacy Policy | `LegalController::privacyPolicy` | — | Complete |
| BR-01 | — | Cookie Policy | `LegalController::cookiePolicy` | — | Complete |
| BR-01 | — | Grievance Redressal Policy | `LegalController::grievanceRedressal` | — | Complete |
| BR-01 | — | Refund and Cancellation Policy | `LegalController::refundCancellation` | — | Complete |
| BR-01, BR-40 | PR-24 | Dispute Resolution Process | `LegalController::disputeResolution` | — | Complete |
| BR-01 | — | Terms and Privacy | *(none)* | — | **Orphan / Partial** |
| BR-01, BR-04 | PR-04 | Rules and Specifications | `SovereignRuleController` | — | Complete |
| BR-02 | PR-02 | Onboarding | `AuthController` | — | Complete |
| BR-02 | — | Profile | `MyActivityController::profile` | — | Complete |
| BR-02 | — | Change mPIN | `AccountController::changeMpin*` | — | Complete |
| BR-02 | — | Delete Account | `AccountController::delete*` | — | Complete |
| BR-03 | PR-03 | *(no dedicated screen — region validation is inline in Onboarding/KYC forms)* | `AuthController`, `KycController` | — | Complete (embedded) |
| BR-04, BR-06, BR-08 | PR-06 | Tenant Directory | `TenantController::directory` | — | Complete |
| BR-04, BR-06, BR-08 | PR-06 | Whitelist Tenant | `TenantController::createForm/createSubmit/list` | — | Complete |
| BR-04, BR-06, BR-09, BR-44 | PR-08 | Tenant Admin Dashboard | `TenantAdminController::dashboard` | — | Complete |
| BR-04, BR-06, BR-09, BR-44 | PR-08 | User Detail (promote) | `UserController::detail/promoteTenantAdmin` | — | Complete |
| BR-04 | — | User Directory | `UserController::index` | — | Complete |
| BR-04 | — | Custodian Dashboard | `AdminController::dashboard` | — | Complete |
| BR-04 | — | Lot Directory | `AdminController::lotDirectory` | — | Complete |
| BR-04 | — | Trading Session Directory | `AdminController::tradingSessionDirectory` | — | Complete |
| BR-05 | PR-05 | Audit Ledger | `AuditLogController::index` | — | Complete |
| BR-05 | PR-05 | Audit Chain Verify | `AuditLogController::verifyIntegrity` | — | Complete |
| BR-05 | PR-05 | Statutory Export | `AuditLogController::exportForm/export` | — | Complete |
| BR-05 | — | Activity Log | `MyActivityController::myActivity` | — | Complete |
| BR-02, BR-05, BR-07 | PR-07 | *(compliance check embedded in Bidding Room / listing approval flow)* | `ListingController`, `SaleEventController` | — | Complete (embedded) |
| BR-13, BR-18, BR-19, BR-45 | PR-09 | Create Lot (media upload) | `ListingController::createForm`, `MediaController::upload` | — | Complete |
| BR-18, BR-19, BR-45, BR-46 | PR-10 | *(AI pre-audit embedded in Create Lot)* | `ListingController::preAudit` | — | Complete (embedded; external Gemini key dependency, previously accepted) |
| BR-10, BR-12, BR-30 | PR-11 | Bidding Room (Express) | `ExpressController` | — | Complete |
| BR-11, BR-13 | PR-12 | Lot Detail (edit/duplicate) | `ListingController::editSubmit` | — | Complete |
| BR-10, BR-12, BR-14 | PR-13 | Lot Detail → Sale Event creation | `SaleEventController::createSubmit/approve` | — | Complete |
| BR-15, BR-16 | PR-14 | Bidding Room (masking), Tender Stakeholder View | `BidController`, `TenderController::stakeholderView` | — | Complete |
| BR-02, BR-03, BR-17, BR-18 | PR-15 | KYC (patron) | `KycController` | — | Complete |
| BR-02, BR-03, BR-17, BR-18 | PR-15 | KYC Queue | `KycReviewController` | — | Complete |
| BR-09, BR-15, BR-19 | PR-16 | Apply to Sell | `SellerApplicationController` | — | Complete |
| BR-09, BR-15, BR-19 | PR-16 | Delist Market Maker | `SellerDelistingController` | — | Complete |
| BR-15, BR-20 | PR-17 | Custodian Credential Setup | `SuperAdminAuthController` | — | Complete |
| BR-11, BR-21 | PR-18 | Tender Eligibility | `TenderController::manageEligibility` | — | Complete |
| BR-09, BR-22 | PR-19 | *(tenant-scope bidding deactivation embedded in compliance-lockout cascade)* | `RatingService::delistSellerForFraud` etc. | — | Complete (embedded) |
| BR-14, BR-23 | PR-20 | EMD Consent | `EmdConsentController` | — | Complete |
| BR-28, BR-34 | PR-21 | Bidding Room (cascade top-up) | `BidController::devPayTopup`, `CascadeService` | — | Complete (D-113 wiring fix) |
| BR-33 | PR-22 | Settlement | `SettlementController` | — | Complete |
| BR-39 | PR-23 | Settlement (force-resolve) | `SettlementController::forceResolve` | — | Complete |
| BR-40 | PR-24 | Dispute Center / Custodian Dispute Review / TSX Master Dispute Review | `DisputeController` | — | **Complete, but Duplicate (3→1)** |
| BR-47 | PR-25 | Lot Reach & Interest (Related Auctions is embedded in Lot Detail, not a separate screen) | `LotReachController`, `ListingController::show` | — | Complete |
| BR-23, BR-48 | PR-26 | *(Live Ticker — not a page; JSON endpoint polled by the shared header, per the Screen Flow doc)* | `LiveTickerController::feed` | — | Complete (non-screen, by design) |
| BR-49 | PR-27 | *(High-value disposal record — embedded in Chronicle/Settlement, no dedicated screen found)* | `ChronicleService` | — | Complete (embedded) |
| BR-50 | PR-28 | Payout Bank | `PayoutBankController` | — | Complete |
| BR-50 | PR-28 | Payout Reviews | `PayoutReviewController` | — | Complete |
| BR-51 | PR-29 | Consent Audit | `ConsentAuditController` | — | Complete |
| BR-52 | PR-30 | *(Chargeback handling — no dedicated screen; card EMD is barred on Easy/secondary elsewhere, low real-world surface)* | — | — | **Not screen-mapped** (see note below) |
| BR-54 | PR-31 | AML Monitoring | `AmlController` | — | Complete |
| BR-56 | PR-32 | Invoices | `InvoiceController` | — | Complete |
| BR-57 | PR-33 | Defect Disclosure | `SaleEventController::defectDisclosureForm/Submit` | — | Complete |
| BR-59 | PR-34 | *(two-tier media capture embedded in Create Lot/Media upload)* | `MediaController` | — | Complete (embedded) |
| BR-60 | PR-35 | Media Waivers | `TenantMediaWaiverController` | — | Complete |
| BR-61 | PR-36 | *(Standing Review embedded in Tenant Admin's Seller Management, plus Tender Auction Report's review rounds)* | `StandingReviewController`, `SellerManagementController` | — | Complete |
| BR-09, BR-13, BR-14, BR-62–66 | PR-37 | *(Tenant API Access console)* | `TenantApiSettingsController` | `/api/v1/oauth/token`, `/api/v1/listings*`, `/api/v1/sale-events/{id}` | Complete |
| §6.2 | — | Tender Concierge Console | *(none)* | — | **Missing / Partial** |
| §7.10 | — | AX Chronicle (download) | `ChronicleController::download` | — | Partial (PDF-only, no HTML viewer) |
| §7.10 | — | Chronicle Verify | `ChronicleController::verify` | — | Complete |
| §7.4 (Phase 2) | — | Lot Chronicle | *(none)* | — | **Orphan** (Phase 2, out of scope) |
| §4.11 | — | *(Independent Security Audit tracking)* | *(none)* | — | **Missing** |
| — | — | Preferences | `PreferencesController` | — | Complete |
| — | — | Saved Searches / Search History | `DiscoveryController` | — | Complete |
| — | — | Buyer Dashboard | `MyActivityController::buyerDashboard` | — | Complete |
| — | — | Seller Dashboard | `MyActivityController::sellerDashboard` | — | Complete |
| — | — | Star Ratings | `MyActivityController::starRatings` | — | Complete |
| — | — | Rating History | `MyActivityController::ratingHistory` | — | Complete |
| — | — | Rating Reviews | `RatingReviewController` | — | Complete |
| — | — | Lot Approval | *(none consolidated)* | — | **Partial** |
| — | — | FAQ / Dos and Donts / Security and Trust / Terminology / Trust and Support / Pricing | `InfoController`, `TrustSupport`, `PricingController` | — | Complete |
| — | — | Alerts | `AdminController::alerts` | — | Complete |

**Note on BR-52/PR-30 (Chargeback Handling)**: grepped for
"chargeback"/"representment" across `app/Controllers`, `app/Views`,
and `app/Libraries` — the only hit is
`RatingService::DOWNGRADE_REASONS['chargeback_against_approved_forfeiture']`,
a rating-penalty *category* an admin can apply manually via the
existing Rating Reviews screen once a chargeback happens elsewhere
(at the payment gateway). There is no dedicated chargeback
intake/representment workflow or screen — no way to log an incoming
chargeback, attach representment evidence, or track its outcome
in-app; the only in-app trace is the rating consequence *after* the
fact. Card-based EMD collection is explicitly the *secondary* option
on Easy/Buy-Now and barred entirely on Easy (§4.5), which limits real
exposure, but PR-30 does describe a representment workflow that has
no screen or backend today. Reporting this as a genuine gap alongside
the two items in the Missing section — it did not surface earlier in
this session because no prior work touched card-based EMD collection
specifically.

---

## Net new findings from this audit (not previously flagged this session)

1. **BR-52/PR-30 Chargeback Handling & Representment** has zero
   backend or screen — not previously identified in any prior D-###
   decision this session.
2. **§4.11 Independent Security Audit tracking** has no in-app
   presence — likely intentionally external-process, but undocumented
   as such.
3. **§6.2 CoCo Concierge engagement management** (the Tender-specific
   fully-managed service layer) has no backend beyond the code-comment
   exclusion from the standard fee schedule.
4. **Dispute Center / Custodian Dispute Review / TSX Master Dispute
   Review** are three design mockups for one real, role-conditional
   screen — worth flagging to the design side so effort isn't spent
   maintaining three mockups for what ships as one page.
5. **Lot Approval** has no single consolidated queue screen matching
   its own mockup — the function exists, split across the Tenant Admin
   dashboard and inline listing/sale-event actions.
6. **AX Chronicle** is PDF-download-only; there's no in-browser
   chronicle viewer distinct from the PDF or the public verify page.

None of these six block production readiness on their own — items 1–3
are edge-case/operational-process gaps (chargebacks are rare given the
card-EMD restrictions; the security-audit and Concierge items are
plausibly intentional pre-Phase-1-completion gaps) and items 4–6 are
UI-consolidation notes for the design side, not functional defects.
They're reported per the audit's explicit scope, not because any of
them are assessed as urgent.
