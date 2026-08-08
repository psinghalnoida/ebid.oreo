# Handoff: every screen still pending, design or build

Updated 2026-08-07 (originally 2026-08-04) — written for pickup by
Claude Design, from the `psinghalnoida/ebid.oreo` repo (AdwitiX).
Companion piece to the inbound handoff at
`docs/design/design_handoff_ebid_hub/` (branch
`design/ebid-hub-handoff`, 53 screens). Read its own `README.md` first
(design philosophy, token system, terminology map) — this doc doesn't
repeat any of that, only what's different, additional, or changed.

## Status as of this update

| | Count |
|---|---|
| Screens with a design (the 53-screen package) | 53 |
| Of those 53 — reviewed &amp; decided (palette/type/header locked in) | 1 (Landing) |
| Of those 53 — decision in progress | 1 (Onboarding) |
| Of those 53 — not yet reviewed, backend ready to design against | 51 |
| Screens with real backend but **no design at all** (§1 below) | 6 |
| Screens with **neither a design nor a consolidated backend** | **0** — all 6 built (D-106, §2 below) |
| **Total distinct screens tracked** | **65** |
| **Actually built into the live app's real views so far** | **0** *(design-wise — see note)* |

Note on "0 built": that's 0 screens carrying the *new* design system's
actual visual treatment. Two rounds of backend gap-closing happened
since the last version of this doc — **Lot Reach &amp; Interest** (D-105)
and, this round, the **6 screens that had neither a design nor a
consolidated backend at all** (D-106: Buyer Dashboard, Seller
Dashboard, Rating History, Star Ratings, Lot Directory, Trading
Session Directory). All 7 now have real, tested backends and are
unblocked for design — §2 gives the real field/route spec for each.
None of the 65 tracked screens are blocked on missing functionality
anymore; everything left is purely visual design work.

Every screen in §1 below is **fully functional, tested, production
logic** — real controllers, real services, real database tables,
exercised by real `spark test:*` suites with passing assertions.
What's missing is purely the visual design: today each one renders as
a plain, undesigned page (inline styles, no card system, no type
scale) — functionally correct, visually unstyled.

## Pending screens — full itemized list (66)

Every screen below has a real, tested backend (nothing here is
blocked on missing functionality) and a design-reference `.dc.html`
file already exists for it in `docs/design/design_handoff_ebid_hub/screens/`.
What's pending is the review/lock-in pass against this doc's real
field specs and the Landing-page decisions above — not new mockup
creation. Only **Landing** (not listed below) is fully reviewed and
locked in.

**Entry & dashboards (6)** — Onboarding *(decision in progress — see
above)*, KYC, Custodian Dashboard, Tenant Admin Dashboard, Buyer
Dashboard, Seller Dashboard

**Core trading (3)** — Marketplace, Lot Detail, Bidding Room

**Ledgers & Chronicle (5)** — AX Chronicle, Lot Chronicle, Audit
Ledger, Audit Chain Verify, Chronicle Verify

**Governance & compliance (11)** — Alerts, Delist Market Maker,
Statutory Export, Media Waivers, AML Monitoring, Payout Reviews,
Invoices, Rating Reviews, Consent Audit, Rules and Specifications, KYC
Queue

**Disputes (4)** — Dispute Center, Custodian Dispute Review, TSX
Master Dispute Review, Dispute Resolution Process

**Account management (9)** — Profile, Preferences, Payout Bank,
Rating History, Star Ratings, Change mPIN, Delete Account, Saved
Searches, Activity Log

**Seller/admin operational (9)** — Create Lot, Lot Approval, Lot
Directory, Lot Reach & Interest, Trading Session Directory, User
Directory, User Detail, Apply to Sell, Tenant Directory

**Tender-specific (4)** — Tender Concierge Console, Tender
Eligibility, Tender Stakeholder View, Tender Auction Report

**Settlement / transaction steps (3)** — Settlement, EMD Consent,
Defect Disclosure

**Custodian/tenant onboarding (2)** — Custodian Credential Setup,
Whitelist Tenant

**Policy / legal (12)** — Privacy Policy, Cookie Policy, Terms of
Usage, Terms and Privacy, Refund and Cancellation Policy, Grievance
Redressal Policy, Security and Trust, Trust and Support, Dos and
Donts, FAQ, Terminology, Pricing

## Update — Screen Completeness Audit (D-117–D-120)

A formal Screen Completeness Audit ran against the live app
(`docs/SCREEN_COMPLETENESS_AUDIT.md`), cross-referencing every
Business Rule/Process Workflow/the Screen Flow document against
`Routes.php`/Controllers/Views. Two findings worth relaying here
before more design time goes into the affected screens:

- **Dispute Center / Custodian Dispute Review / TSX Master Dispute
  Review are one real screen, not three.** The live app implements all
  three of these as a single template (`app/Views/dispute/show.php`,
  served by `DisputeController::show` at `/disputes/{id}`) with
  role-conditional rendering — the filing/respondent party sees their
  own view, a Tenant Admin ruling on the original dispute sees the
  ruling form, and a Super Admin ruling on an appeal sees the appeal
  form, all from the same route and the same underlying data. This is
  a deliberate architectural choice (single source of truth, no
  duplicated markup), not an oversight. Recommend designing this as
  **one screen with role-conditional states** rather than three
  separate mockups, so effort isn't spent maintaining three designs
  for what ships as one page. (`dispute/file.php`, the filing form at
  `/sale-events/{id}/dispute`, is a genuinely separate fourth screen —
  unaffected by this note.)
- **Three §1 screens below have moved since this doc's last version**:
  Settlement now has a real "View Chronicle" in-browser link alongside
  its PDF download (D-119); Tender Eligibility, Tender Stakeholder
  View, and Tender Auction Report are unchanged. Worth a quick
  cross-check against the live app before finalizing pixel details,
  same as always — this doc describes the field/route spec, not a
  frozen wireframe.

Also fixed since the audit: BR-52/PR-30 Chargeback Handling now has a
real screen (`/admin/chargebacks`, plus a "Dispute This Charge" dev
entry point on the listing page) — not yet in the 53-screen design
package or the pending-list above; treat it as a new addition on the
same footing as the §1/§2 screens below when scoping design work next.
Lot Approval (the design package's existing mockup) is now backed by
one real consolidated queue at `/tenants/{id}/verification` (both
pending Lots and pending Trading Session approvals on one page,
retitled "Lot & Trading Session Approval" to match) rather than the
split dashboard-tiles/inline-buttons pattern it replaced.

### Review of the Batch 7 delivery (Dispute consolidation, Chargeback Handling, Settlement link)

Checked against the real backend. The Dispute consolidation, the
Settlement link relabel, and leaving Lot Approval unchanged are all
correct — genuinely re-derived from `DisputeService`'s real
authorization logic, not just relabeled. Two things in
`Chargeback Handling.dc.html` don't match the real backend as it
exists today, worth fixing before this one gets built for real:

1. **No manual "Submit for Representment" step exists.** The design
   has cases sit in an `assembled` state with an admin action to move
   them to `submitted`. The real `ChargebackService::fileChargeback()`
   assembles evidence *and* reaches `represented` in one atomic call —
   there's no real payment-gateway integration to submit to yet (same
   accepted external-dependency gap as everywhere else EMD/payments
   touch this app), so there's nothing for a "Submit" button to
   trigger. Either remove that intermediate state/button, or keep it
   as a deliberately-designed-ahead affordance for once a real gateway
   exists — but if kept, label it as such so it isn't mistaken for
   something already wired.
2. **No way to decline the rating penalty.** The integrity-flagged
   path's "Log Account-Integrity Event" button always logs with the
   penalty applied. The real `ChargebackService::reviewIntegrityFlag()`
   takes a real, supported "apply the rating consequence or not"
   choice — a SaaS Admin declining the penalty (e.g., a genuine
   gateway error, no real misconduct) is a real, recorded outcome, not
   an edge case to omit. Add the choice (e.g. a checkbox or two
   distinct buttons) before this one gets built for real.

## Decisions already locked in (screen 1 of the 53, Landing)

Apply these to all 6 screens below for consistency — don't re-derive:

- **Palette**: adopt the *new* hex values from the 53-screen package
  (gold `#C9974C`, navy `#1E2761`, ink `#1E2233`, muted
  `#565B72`/`#9A9FB5`, bg `#F3F4F8`, line `#DDE0EA`) — not the app's
  current live tokens.
- **Typography**: Georgia/Cambria serif for headlines, Inter for body,
  IBM Plex Mono for numbers/labels/eyebrows.
- **Header**: avatar+initials with a color-coded KYC-status pill when
  logged in; pill-segmented nav (Marketplace/Browse/Sell/Trust &
  Support).
- **No new marketing-style sections** — these are all functional/
  transactional screens, not landing-page content; keep them
  utilitarian, matching the density of Bidding Room / Dispute Center
  in the 53-screen package, not the Landing page's editorial tone.
- **Terminology**: Trader = Buyer, Market Maker = Seller, TSX Master =
  Tenant Admin, Custodian = Super Admin — same mapping as the 53-screen
  package's own reference doc.

---

# §1 — 6 screens with real backend, no design at all

## 1. Settlement

**Route**: `/settlements/{id}` · **Real file**: `app/Views/settlement/show.php`
**Controller/Service**: `SettlementController` / `SettlementService`

The page every completed sale lands on, across **all four formats** —
this is the one screen every Trader and Market Maker eventually sees.
Not designed anywhere in the 53-screen package (checked — only
incidental mentions in Alerts, FAQ, Profile, Terminology, never its
own screen).

**Real state machine, not a static page** — a settlement only
completes once all four of these are true, and the UI needs to show
partial progress honestly:

1. Seller confirms receipt of payment ("Seller NOC")
2. Buyer confirms receipt of goods ("Buyer NOC")
3. Buyer rates the seller (Market Maker★)
4. Seller rates the buyer (Trader★)

Each of the four is its own action, gated to the correct party only
(seller can't confirm the buyer's NOC and vice versa) — the page
needs to render differently depending on which of the four the
*current viewer* still owes vs. has already done vs. is waiting on the
other side for.

**Once all four land**, the page should show the completed state:
- Success Fee amount actually deducted, and **who paid it** (Fee Payer
  Election — Buyer-Pays deducts from EMD, Seller-Pays bills the
  Tenant instead and releases the buyer's EMD in full)
- TDS: a flat 10% of the gross price, always shown, always deducted
  from the seller's side, every format including Tender
- A link to the generated invoice (**not shown on Tender** — Tender
  settlements follow the seller's own custom terms, no platform
  invoice)
- A link to the Trading Session Chronicle (**always shown, every
  format, no exceptions** — this is the one document that's never
  format-gated)
- Audit trail of the settlement's own timeline

**Stalled-settlement state**: if 7+ days pass with any of the four
steps missing, status flips to `stalled`. A TSX Master viewing it gets
a "Force-resolve" action — applies neutral 3.0★ to whoever never
rated, force-confirms whichever NOC never came, and closes it. Design
this as a visibly different, admin-only state, not a variant of the
normal completed view.

**File a Dispute** — if either party sees a problem, there's a way to
launch a dispute from this same page instead of confirming (routes to
the Dispute Center screen the 53-screen package already designed).

---

## 2. Tenant Directory

**Route**: `/tenants` (public, no login required)
**Real file**: `app/Views/tenants_directory.php` · **Controller**: `TenantController::directory`

A simple public list of every whitelisted Tenant (TradeSphereX) a
prospective Market Maker can apply to sell on. Currently: name, class
badge, and an "Apply to Sell" button per row, nothing more. Real gap:
the 53-screen package's Seller Dashboard mockup has an "Apply to Sell
Elsewhere" **link label** pointing here, but this destination itself
was never designed.

Minimal real fields per tenant: `name`, `tenant_class`, and the
`id` used to build the apply link. No filtering/search exists in the
real backend yet — don't design filter chips that don't have a real
endpoint behind them.

---

## 3. Apply to Sell

**Route**: `/tenants/{id}/apply-to-sell` (GET form, POST submit)
**Real file**: `app/Views/seller/apply.php` · **Controller**: `SellerApplicationController`

Reached from the Tenant Directory above. Genuinely simple, real
states:

- **No existing application**: one button, "Apply to Sell Here" — a
  bare click, no form fields (the applicant's identity/KYC is already
  known from their session).
- **Existing application, pending**: status shown, no action available.
  Design an honest "waiting on the TSX Master" state.
  - Reviewed on the TSX Master's side via `Pending Market Maker
    Applications`/`Review Pending Applications`, already covered in
    the 53-screen package's Tenant Admin Dashboard.
- **Existing application, rejected**: status + the TSX Master's real
  rejection reason shown, and the button to re-apply reappears.

---

## 4. Tender — Manage Eligibility

**Route**: `/sale-events/{id}/tender/eligibility` (TSX Master only)
**Real file**: `app/Views/tender/eligibility.php` · **Controller**: `TenderController::manageEligibility`/`grantEligibility`

Tender is invitation-only — this is the actual gate. Three real
sections on one page:

1. **Traders who registered interest but aren't yet eligible** — each
   row gets an "Approve" action.
2. **Add a Trader directly by mobile number** — a TSX Master can grant
   eligibility to someone who never went through "register interest"
   at all.
3. **Currently-eligible list**, each row tagged with its `source`
   (registered-interest vs. directly-added) — real, distinct data,
   design them as visibly different provenance, not the same badge.

No design in the 53-screen package touches Tender's eligibility gate
at all (grep-verified against every screen — zero matches).

---

## 5. Tender — Stakeholder View

**Route**: `/tender-view/{token}` — **public, no login, random token**
**Real file**: `app/Views/tender/stakeholder_view.php` · **Controller**: `TenderController::stakeholderView`

The one screen in the entire app a genuinely anonymous outsider can
land on — a real, working no-login access pattern (a stakeholder link
the TSX Master generates and shares, e.g. with a client's finance
team who isn't a platform user at all). Deliberately minimal by
design, not by omission: shows the listing category, ERN, and a
**bid-amount-only history** — explicitly no bidder identity, ever,
anywhere on this page (that's a real BR-21/tender confidentiality
rule, not a placeholder to fill in later).

Keep the "no identities, ever" framing visually explicit — the
53-screen package's own Bidding Room design already treats bidder
anonymity as a first-class visual idea (confidential-bid framing);
reuse that same visual language here.

---

## 6. Tender — Auction Report

**Route**: `/sale-events/{id}/tender/report`
**Real file**: `app/Views/tender/auction_report.php` · **Controller**: `TenderController::auctionReport`

The full post-mortem of a Tender auction — TSX Master/relevant
parties facing. Four real, already-populated sections, each backed by
genuine data (verified via `test:tenderreview`, 21/21 assertions):

1. **Eligible participants** — the full roster who could bid
2. **Bid history** — every bid placed, full sequence
3. **EMD log** — every deposit event
4. **Review rounds** — every round of the manual post-auction review,
   including rejections that cascaded the win to the next bidder
   (H1→H2→H3), and each round's outcome

This is a genuinely data-dense report screen — closer in spirit to
the 53-screen package's AX Chronicle / Audit Ledger screens (both
already designed) than to any of the dashboard screens. Look at those
two for the visual pattern to extend here, rather than inventing a new
one.

---

# §2 — 7 screens: had no consolidated backend, now built, ready to design

All 7 of these were flagged in an earlier version of this doc as
having **neither a design nor a consolidated backend** — Lot Reach &amp;
Interest first (D-105, 2026-08-07 morning), then the remaining 6
(D-106, 2026-08-07, same day) after the project owner's explicit
scoping call was "build all 6." **None of these need a further
scoping decision** — each below reflects what's **actually
implemented and tested**, not a re-statement of the original mockup's
intent. Design directly against the real field/route spec given per
screen.

## 1. Lot Reach & Interest

**Seller-facing dashboard** — `GET /my-listings/reach` ·
`LotReachController::index` · `app/Views/reach/index.php` (currently
plain/undesigned — this is the real page to redesign)

- Summary stats across the seller's own active listings: total live
  listings, total **full matches** (buyers matching all three of
  category + location + value), total views.
- Per-listing breakdown: view count, and a table of every buyer
  matching on **at least one** of the three dimensions (a
  zero-dimension match is excluded entirely server-side, not filtered
  client-side) — with per-buyer category/location/value match flags,
  plus real viewed/favorited status.
- A "Message matched buyers" composer per listing — real send, goes to
  every *currently* matched buyer for that listing, re-checked at send
  time (not a stale snapshot).

**Buyer-facing inbox** — `GET /my-messages` ·
`MyActivityController::messages` · `app/Views/my/messages.php`
(also currently plain/undesigned)

- Every message this buyer has received, newest first, each showing
  which listing/category it's about, delivery timestamp, and
  read/unread state (unread rows need a visually distinct treatment —
  the plain page currently just tints the background).
- A "Mark as read" action per unread message.

**Real, deliberate scope boundaries worth knowing before designing**:
location matching is a free-text substring check (a buyer's saved
state name checked against the listing's free-text yard address) —
there's no normalized state field anywhere in this schema, so don't
design a strict "verified location match" badge, this is a best-effort
signal. Delivery is in-app only — there is no real SMS/email provider
connected (see D-104's own audit), so "delivered to their preference
alerts inbox" in the original mockup copy means literally this
`/my-messages` page, not a push notification.

---

## 2. Buyer Dashboard

**Route**: `GET /my-buyer-dashboard` · **Real file**:
`app/Views/my/buyer_dashboard.php` · **Controller/Service**:
`MyActivityController::buyerDashboard` / `DashboardService::buyerSummary`
(currently plain/undesigned — this is the real page to redesign)

A real consolidation, not a new source of truth — every number and row
here is pulled live from the same tables `/my-bids`, `/my-offers`,
`/my-purchases`, and `/my-favorites` already read; each section links
out to that existing full page rather than reinventing it.

- **4 headline stats**: active bids count (bids currently standing H1/
  H2/H3 on a still-open sale event), open offers count (status
  `submitted`), purchases-to-rate count (completed settlements this
  buyer hasn't yet rated the seller on — BR-33's mandatory
  bidirectional rating), favorites count.
- **Active Bids** — up to 5 most recent, each with category, amount,
  and standing (H1/H2/H3), linking to the sale event.
- **Open Offers** — up to 5 most recent (Tender format), category +
  amount.
- **Purchases to Rate** — up to 5, each linking straight into the
  settlement page's rating action — this list is the one section
  worth visually emphasizing (it's an outstanding action, not passive
  info; the plain page currently renders it in a warning color for
  exactly this reason).

Real, tested behavior worth designing around: a purchase drops off
"Purchases to Rate" the instant it's actually rated — it's a live
query against `settlement.buyer_rated_seller_at`, not a static or
cached list (verified in `test:partydashboards`).

---

## 3. Seller Dashboard

**Route**: `GET /my-seller-dashboard` · **Real file**:
`app/Views/my/seller_dashboard.php` · **Controller/Service**:
`MyActivityController::sellerDashboard` / `DashboardService::sellerSummary`
(currently plain/undesigned)

Same consolidation pattern, seller side — pulls from `/my-listings`,
`/my-sales`, `/payout-bank`, and `/account/invoices`.

- **4 headline stats**: active listings count, sales-this-month count,
  sales-this-month value (₹, sum of `final_price` on settlements
  completed since the 1st of the current month), pending settlements
  count (emphasized in a warning color when &gt;0 — same live-list
  behavior as Buyer Dashboard's purchases-to-rate).
- **Payout Bank status** — a real on-file/not-set/change-pending
  tri-state read from `party.payout_bank_account_number` and
  `payout_bank_pending_account_number`; when not set, links straight
  to `/payout-bank` — this is a genuine "you can't get paid yet"
  warning, not decorative.
- **Active Listings** — up to 5, category + real view count (from
  Lot Reach & Interest's `listing.view_count`), linking to the listing.
- **Pending Settlements** — up to 5, category + amount, linking to the
  settlement page — drops off once `status` flips to `completed`, same
  live-query behavior as the buyer side.
- **Recent Invoices** — up to 5, invoice number + total, linking to
  `/account/invoices`.

---

## 4. Rating History

**Route**: `GET /my-rating-history` · **Real file**:
`app/Views/my/rating_history.php` · **Controller/Model**:
`MyActivityController::ratingHistory` / `RatingEventModel::findForParty`
(currently plain/undesigned)

Reads a real, complete, permanent audit trail that already existed
(`rating_event`, built for BR-35/BR-36's approval workflow) but had no
page anywhere showing it back to the party it happened to — every
number on Star Ratings/listing pages was always just the current
value, never the history behind it.

Table, newest first, one row per real event: date, role (Trader★ vs.
Market Maker★ — the two ratings are tracked and shown as fully
independent histories), type (**Upgrade** / **Downgrade** / **Forced
Neutral** — style these three as visually distinct, not shades of the
same badge; Forced Neutral in particular is a BR-39 stalled-settlement
outcome, not a normal rating event and shouldn't look like one), the
actual before→after value change, the human-readable reason text
(real, stored, not a category code), and status (`applied` vs.
`pending_tenant_approval` vs. `pending_super_admin_approval` vs.
`rejected` — BR-36's real approval workflow states) plus, when
appealed, the appeal outcome.

Empty state is real and expected for most parties: every account
starts neutral at 3.0★ with zero events until something changes it.

---

## 5. Star Ratings

**Route**: `GET /my-star-ratings` · **Real file**:
`app/Views/my/star_ratings.php` · **Controller**:
`MyActivityController::starRatings` (currently plain/undesigned)

The "what is my standing right now, and why" screen — Rating History
above is the ledger, this is the current-balance summary, and the two
should read as a clear pair (this page links into that one).

Two independent cards, Trader★ and Market Maker★ — same current
`star_rating`/`seller_star_rating` values already shown elsewhere in
the app, but this is the first page that actually explains them:

- The numeric rating itself (0.0–5.0, one decimal).
- **Shadow-ban state**, per role, independently — if
  `shadow_banned_at_{buyer,seller}` is set, show since when, in a
  visually serious (not merely informational) treatment; if not set,
  show "In good standing."
- **Crawl-Back progress**, when a shadow ban's recovery path is
  active — a real "`{completed}` / `{required}` clean transactions
  completed" counter (`crawl_back_clean_completed_*` /
  `crawl_back_clean_required_*`), which is genuinely incrementing
  data, not a static message — design it as visible progress (a bar or
  counter), not plain text.

---

## 6. Lot Directory

**Route**: `GET /admin/lots` (Custodian/Super Admin only) ·
**Real file**: `app/Views/admin/lot_directory.php` · **Controller/
Service**: `AdminController::lotDirectory` / `AdminDirectoryService`
(currently plain/undesigned)

The gap this closes: the Tenant Admin Dashboard already designed in
the 53-package only ever shows one TSX Master's own pending queue —
the Custodian previously had **no way to browse every listing across
every TradeSphereX**, platform-wide, at all.

- **Filters** (real, server-side, all combinable): free-text search
  (matches category/subcategory/tenant name), tenant, sale format
  (Easy/Express/Buy-Now/Tender), listing status. Don't design filter
  chips beyond these four — nothing else is wired.
- **Table**, one row per listing: tenant name, category/subcategory
  (linking to the real listing page), status, sale format + that
  listing's sale-event status side by side (a listing can exist
  without an active sale event yet — design that as a real, expected
  `—` state, not an error), and real view count.
- **Real pagination** — same `Paginator` pattern as every other
  filterable admin list in the app (page numbers, not infinite
  scroll).

---

## 7. Trading Session Directory

**Route**: `GET /admin/trading-sessions` (Custodian/Super Admin only)
· **Real file**: `app/Views/admin/trading_session_directory.php` ·
**Controller/Service**: `AdminController::tradingSessionDirectory` /
`AdminDirectoryService` (currently plain/undesigned)

Same gap, for sale events (Trading Sessions) rather than listings —
pairs naturally with Lot Directory above; consider designing them as
visibly siblings (shared filter-bar layout, same table density).

- **Filters**: tenant, sale format, sale-event status. No free-text
  search on this one (sale events don't carry their own searchable
  text beyond the ERN, already visible in the table).
- **Table**, one row per sale event: ERN (monospace — it's an
  identifier, treat it like one), tenant name, category, format,
  status, and current value (real `COALESCE` of current price →
  reserve value → expected value, whichever the format actually
  populates — don't design a single "price" field expecting all sale
  events to have the same one populated).
- Same real pagination pattern as Lot Directory.

---

## Getting this into Claude Design

This file lives at `docs/design/CLAUDE_DESIGN_HANDOFF.md` on branch
`claude/pr09-media-pipeline-qytiw5` (PR #41) in
`psinghalnoida/ebid.oreo`. To pick it up:

1. From claude.ai/design, open (or create) the AdwitiX project.
2. Point it at this file — either paste its contents directly, or if
   the project already has GitHub access, read it straight from this
   path/branch.
3. Design all 13 screens — §1's 6, plus §2's 7 — as `.dc.html` files in
   the same format as the existing 53-screen package, using the same
   design philosophy already established (see
   `docs/design/design_handoff_ebid_hub/README.md`). Nothing is on
   hold anymore — every screen tracked in this doc has a real, tested
   backend to design against.
4. Push the result back the same way the original 53 arrived — a
   branch pushed to this repo (e.g. `design/ebid-hub-handoff-part2`),
   picked up here for review the same screen-by-screen way as the
   first batch.
