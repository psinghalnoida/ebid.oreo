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
| Screens with **neither a design nor a consolidated backend** (§2 below) | 6 |
| **Total distinct screens tracked** | **65** |
| **Actually built into the live app's real views so far** | **0** *(design-wise — see note)* |

Note on "0 built": that's 0 screens carrying the *new* design system's
actual visual treatment. One item changed since the last version of
this doc — **Lot Reach &amp; Interest** (one of the original 53) had no
real backend behind it at all as of the last audit; it does now (D-105,
below). It still needs its real visual design applied — same as the
other 51 not-yet-reviewed screens in the package — but it's no longer
blocked on missing functionality. Section 3 gives the real field/route
spec to design against.

Every screen in §1 below is **fully functional, tested, production
logic** — real controllers, real services, real database tables,
exercised by real `spark test:*` suites with passing assertions.
What's missing is purely the visual design: today each one renders as
a plain, undesigned page (inline styles, no card system, no type
scale) — functionally correct, visually unstyled.

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

# §2 — 6 screens with neither a design nor a consolidated backend

Found in a follow-up audit (D-104/D-105) that checked every one of the
53-package's screens against real routes, not just the 6 in §1.
**Different in kind from §1**: these aren't "just needs a screen" —
the underlying functionality is either scattered across several real
pages with no single consolidated view, or (in one case, already
resolved — see §3) didn't exist as backend logic at all. Flagging
these here so design work doesn't start on them assuming a backend
that isn't there; each needs a product scoping decision before design,
the same way Lot Reach &amp; Interest did before D-105 built it.

| Screen (53-package name) | What exists today instead | Real gap |
|---|---|---|
| **Buyer Dashboard** | `/my-bids`, `/my-offers`, `/my-purchases`, `/profile`, `/account/earnings` — 5 separate real pages | No single consolidated dashboard exists to design against |
| **Seller Dashboard** | `/my-listings`, `/my-sales`, `/account/earnings`, `/payout-bank`, `/account/invoices` — 5 separate real pages | Same problem, seller side |
| **Rating History** | Ratings shown inline on profile/listing pages only | No dedicated history page/route exists |
| **Star Ratings** | Same | No dedicated page/route exists |
| **Lot Directory** (Custodian-facing, platform-wide, filterable, all tenants) | Only per-tenant pending-approval queues on the Tenant Admin Dashboard | Super Admin has no way to browse every listing across every tenant |
| **Trading Session Directory** (Custodian-facing, same idea for sale events) | Same | No platform-wide sale-event browse exists for Super Admin |

None of these are in progress. Bring them up with the project owner
before starting design work on any of them — each is a real product
decision (build a new consolidated page? extend an existing one?) not
just a visual one.

---

# §3 — Lot Reach & Interest: now has real backend, ready to design

Was one of §2's problems until D-105 (2026-08-07) built it — flagged
here specifically since it's the one screen that moved from "no
backend" to "ready for real design" since the last version of this
doc. The design brief below reflects what's **actually implemented and
tested** (`spark test:listingreach`, 29/29 assertions, plus a full real
HTTP click-through — login, view a listing, favorite it, send a bulk
message, receive it in a real inbox, mark it read), not a re-statement
of the original mockup's intent.

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

## Getting this into Claude Design

This file lives at `docs/design/CLAUDE_DESIGN_HANDOFF.md` on branch
`claude/pr09-media-pipeline-qytiw5` (PR #41) in
`psinghalnoida/ebid.oreo`. To pick it up:

1. From claude.ai/design, open (or create) the AdwitiX project.
2. Point it at this file — either paste its contents directly, or if
   the project already has GitHub access, read it straight from this
   path/branch.
3. Design §1's 6 screens, plus §3 (Lot Reach & Interest, now unblocked)
   as `.dc.html` files in the same format as the existing 53-screen
   package, using the same design philosophy already established (see
   `docs/design/design_handoff_ebid_hub/README.md`). **Hold off on §2**
   until the project owner has made a scoping call on each.
4. Push the result back the same way the original 53 arrived — a
   branch pushed to this repo (e.g. `design/ebid-hub-handoff-part2`),
   picked up here for review the same screen-by-screen way as the
   first batch.
