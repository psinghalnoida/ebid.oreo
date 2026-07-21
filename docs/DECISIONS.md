# Technical Decision Log

Same discipline as BR-01 (Discuss First & Rationale Archive) in the governing
BR/PR document, applied to build/infra decisions rather than business rules.
Every decision below was discussed and confirmed with the Super Admin
(Piyush Singhal) before being acted on. Nothing here is assumed or defaulted
silently.

---

### D-01: Vertical-slice build order over full-breadth scaffold

**Decision:** Build one complete, working sale format end-to-end before
starting the next, rather than a shallow skeleton across all formats at once.

**Order:** Phase 0 (shared foundation: data model, EMD engine, rating engine,
listing lifecycle, tenant model, auth) → Easy Auction (first full slice) →
remaining formats (Buy-Now, Express, Tender) → admin surfaces (Tenant Admin,
Super Admin) → Phase 2 items (Reverse Auction / procurement) per the
roadmap's own phasing.

**Rationale:** Easy Auction touches the most shared plumbing (listing
lifecycle, EMD gate, Dynamic Time bidding, H1/H2/H3 cascade, dual-NOC
settlement, rating update), so completing it first proves out Phase 0 and
makes every subsequent format faster to add. A shallow build across
everything at once produces something buggy everywhere rather than solid
anywhere.

---

### D-02: Deployment target — i2k2 dedicated server, not GCP

**Decision:** The original BR/PR document's Section 3 tech stack specifies
Google Cloud Run, Cloud SQL, and Secret Manager. The actual deployment target
is a pre-existing i2k2 dedicated server (IP 103.25.128.136), not GCP.

**Adaptation:**
| Original (GCP)            | Actual (i2k2, self-managed)              |
|----------------------------|-------------------------------------------|
| Cloud Run                  | Docker Compose running directly on the server |
| Cloud SQL                  | PostgreSQL container on the same server    |
| Secret Manager             | `.env` file on the server, git-ignored     |
| Cloud Build → Cloud Run    | GitHub → manual pull + `docker compose up` on server |
| Cloudflare CDN              | Unchanged — works in front of any origin server |

**Rationale:** The dedicated server was already provisioned before this
project's tooling discussion; re-platforming to GCP was not evaluated as
necessary given the server's specs (6 vCPU / 8GB RAM / 400GB SSD) comfortably
meet the platform's Phase 1 needs.

---

### D-03: OS — Ubuntu 22.04 LTS (server as-provisioned), not reimaged

**Decision:** Server was found running CentOS 7 (end-of-life since June
2024) but had already been reimaged to Ubuntu 22.04 LTS by the time of
confirmation. Accepted as-is — no further reimage to 24.04.

**Rationale:** CentOS 7 was unacceptable (no security patches — unsuitable
for a platform holding buyer EMD funds in escrow). Ubuntu 22.04 LTS carries
standard security support until April 2027, with full compatibility across
the entire planned stack (Docker, Node.js, PostgreSQL, Redis, Nginx,
Certbot). Forcing a further reimage to 24.04 was judged unnecessary friction
given 22.04 is not end-of-life.

**Follow-up action:** Enable Ubuntu Pro (free tier, up to 5 machines) on the
server to extend security coverage (ESM) to April 2032, at zero cost. Plan a
`do-release-upgrade` to 24.04 before April 2027 as routine maintenance, not
an urgent blocker.

---

### D-04: Deploy boundary — no AI-direct writes to the production server

**Decision:** Claude (in any form — this chat, or Claude Code) never writes,
edits, or executes anything directly on the production server, even when
technical SSH access exists.

**Flow:** Claude writes code locally / in a Claude Code project → commits
pushed to GitHub → the human with SSH access on the project owner's side
pulls from GitHub and runs the deploy step manually.

**Rationale:** Ensures every production change is a deliberate, visible,
human-executed action with a git-commit audit trail — consistent with the
platform's own audit-trail principle (BR-05) applied to the build process
itself, not just the running application.

---

### D-05: GitHub as the deployment handoff layer

**Decision:** GitHub repo `psinghalnoida/ebid.oreo` (private) is the single
handoff point between AI-written code and server deployment.

**Branching:** `main` = production (only updated via deliberate merge from
`dev`, approved by the project owner). `dev` (a.k.a. `testing`) = active
development branch.

**Rationale:** Directly enforces D-04 — merging `dev` → `main` becomes the
explicit human decision point that gates what reaches production.

---

### D-06: UI design direction — Modern Marketplace Minimal

**Decision:** After reviewing three concept directions (Auction House
Editorial, Industrial Premium, Modern Marketplace Minimal), the project
owner selected **Modern Marketplace Minimal** — off-white background,
near-black text, emerald accent, Sora typeface, soft rounded cards and pill
buttons — as the visual direction for the landing page and auction page.

**Rationale:** Chosen over the platform's earlier "salvage manifest"
aesthetic (graphite/paper/amber/teal/rust, Inter + IBM Plex Mono) in favor of
a more polished, ecommerce-familiar look.

**Note:** Per D-01-adjacent reasoning, backend/business logic development is
sequenced ahead of UI polish. Screens may be built functionally plain first
and restyled to this direction afterward without touching business logic,
since frontend and backend are fully decoupled (BR-10's Listing-vs-Sale-Event
separation extends naturally to this UI/logic separation).

---

### D-07: Repository reset

**Decision:** The `ebid.oreo` repository was fully reset (history wiped, not
just files deleted) after pre-existing "OREO" architecture docs and database
schema content of unconfirmed origin were found in it.

**Rationale:** That content's provenance and the decisions behind it could
not be confirmed with the project owner, and building on undocumented,
unreviewed decisions would violate the BR-01 discipline this entire project
is built around. A clean slate was judged safer than attempting to reconcile
unexplained prior work.

**Note:** A separate, unrelated public repository (`Ebid-Hub`, an Android /
Google AI Studio scaffold referencing a Gemini API key) was also discovered
during this process and confirmed **not** to be part of this project. It is
not touched by any decision in this log.

---

### D-08: Rating engine — OPEN ITEM: Shadow Banning threshold unconfirmed

**Status:** ⚠️ NOT CONFIRMED — placeholder value in use, needs Super Admin decision.

**Context:** BR-38 states Shadow Banning applies at "a further threshold"
below the Crawl-Back trigger (2.0★), without stating the number. Unlike
Crawl-Back's clean-transaction ladder (3/5/8, settled in prior project
work) and the ₹50k deposit-override floor (also settled), no specific
Shadow Banning threshold has been confirmed with the Super Admin.

**Current placeholder in code:** 1.5★ (`SHADOW_BAN_THRESHOLD` in
`ratingService.js`) — chosen only to keep the engine testable, not as a
business decision.

**Action required:** Confirm the actual Shadow Banning threshold with the
Super Admin before this is treated as final. Until confirmed, do not rely
on 1.5★ in any downstream decision (UI copy, tenant communication, etc.).

---

### D-09: Trust & Support hub restyled to Modern Marketplace Minimal

**Decision:** The Trust & Support hub (FAQ, Dos & Don'ts, Fee Schedule,
Refund Policy, Dispute Resolution, ToS, Privacy, Grievance Redressal,
Security & Trust, Contact Us — built in an earlier session with a different
design system) is restyled into Modern Marketplace Minimal (off-white,
near-black, emerald, Sora), matching the landing/auction pages.

**Rationale:** The two designs conflicted; project owner confirmed this
direction should apply site-wide rather than maintaining two visual
languages.

---

### D-10: Stack pivot — Node.js/Express/React → CodeIgniter 4 (PHP), server-rendered views

**Decision:** At Arpit's (the project owner's SSH/deployment contact)
request, the backend and frontend stack changes from Node.js/Express with
a separate React SPA to **CodeIgniter 4 (PHP)**, using **CodeIgniter's own
server-rendered views** rather than a separate frontend application.

**Rationale (as relayed):** Arpit's operational comfort is with a PHP/LAMP-
style stack on i2k2 hosting, which runs natively via Apache/Nginx +
PHP-FPM without a separate Node process to manage.

**Cost, confirmed with project owner before proceeding:** the EMD engine
and rating engine built and tested in Node.js (39 passing assertions
across both, against real PostgreSQL data) do not run under PHP and must
be fully rewritten. The 9 SQL migration files are reusable (standard
PostgreSQL DDL), pending porting into CodeIgniter's own migration format.
The Node/React skeleton itself (Docker Compose targeting a Node container,
the Vite/React frontend) is superseded and not carried forward.

**What is NOT changing:** the underlying BR/PR business rules, the
database schema design (party/tenant/listing/sale_event/bid/emd_hold/
rating_event), the i2k2/Ubuntu 22.04 deployment target, the GitHub
handoff flow (D-05), and the no-AI-direct-server-writes boundary (D-04) —
this is a stack change, not a re-opening of those decisions.

---

### D-11: Sandbox verification method for CodeIgniter (network-restricted environment)

**Context (informational, not a project decision):** Claude's sandboxed
dev environment blocks network access to Packagist (`repo.packagist.org`,
`getcomposer.org`), so `composer install`/`composer create-project` cannot
resolve CodeIgniter's dependencies inside that sandbox. This has no effect
on the actual project — Arpit's real server has normal internet access and
`composer install` will work there exactly as it would on any standard
CodeIgniter deployment.

**What was done to verify the delivered code anyway:** rather than deliver
untested code, the framework source and its two runtime dependencies
(laminas/laminas-escaper, psr/log) were pulled directly from their GitHub
source repositories (allowed in the sandbox's network policy) and manually
wired together with a hand-written autoloader, purely to prove the
delivered controllers/views/routes actually execute correctly under a real
CodeIgniter boot — confirmed via HTTP 200 responses and correct dynamic
content rendering on both the landing page and Trust & Support hub. This
verification scaffolding (`vendor/manual-autoload.php` and similar) is
**not** included in the delivered project — the delivered `composer.json`
is the standard, unmodified `codeigniter4/appstarter` manifest, intended to
be installed normally via `composer install`.


---

### D-12: Node.js → CodeIgniter 4 transition completed

**Decision:** All Phase 0 business logic previously built and tested in
Node.js (per D-10) has been ported to CodeIgniter 4 (PHP) and re-verified
against real PostgreSQL data. The transition is now complete — no Node.js
logic remains to be ported.

**What was ported, and how it was verified:**
- 9 SQL migrations → CodeIgniter migration classes (`app/Database/Migrations/`),
  verified via `php spark migrate` AND full `migrate:rollback` (rollback was
  never available in the Node migration runner).
- EMD engine, bidding, and H1/H2/H3 cascade logic → `app/Libraries/EmdService.php`,
  `BiddingService.php`, `CascadeService.php` — re-verified with the same
  full-cascade-failure scenario as the original Node test (21 assertions,
  all passing, including the zero-seller-share rule on triple default).
- Rating engine → `app/Libraries/RatingService.php` — re-verified including
  the dual-approval gate and Crawl-Back restoration (28 assertions, all passing).
- Listing Lifecycle service (BR-13/BR-14) → `app/Libraries/ListingLifecycleService.php`
  — this was never actually finished in Node (the stack pivot happened
  mid-build), so it was built fresh in PHP rather than ported. Verified
  with 20 assertions covering approval/rejection, format-specific grace
  windows, archive-and-recreate, and emergency stop.

**Real bugs found and fixed during the port** (not present in delivered code):
1. CodeIgniter defaults to MySQL's `utf8mb4` charset, which PostgreSQL
   rejects outright — fixed via `database.default.charset = utf8` in `.env`.
2. CodeIgniter's Model layer cannot reliably retrieve a UUID primary key
   generated by Postgres's own `DEFAULT gen_random_uuid()` — every model's
   create method now generates the UUID in PHP and supplies it explicitly
   (see `SETUP.md` for the required convention on any new model).
3. `BiddingService::placeBid()` returned a stale pre-update snapshot of the
   new bid (still showing `standing: 'outbid'` instead of `'h1'`) — the
   same latent bug existed in the original Node `biddingService.js` too,
   but was never caught there because the Node test suite never asserted
   on `.standing`, only `.amount`. Fixed by re-fetching the record after
   all updates instead of returning the pre-update capture.

**Test tooling kept in the project:** `php spark test:cascade`,
`test:rating`, `test:lifecycle` are real CLI commands (not throwaway
scripts), left in `app/Commands/` as ongoing verification tooling —
rerunnable anytime to confirm a change hasn't broken existing behavior.
---

### D-13: Auth (BR-02) built and wired to real, browser-reachable HTTP routes

**Decision:** BR-02's mobile + OTP + mPIN flow is implemented and verified
— both as isolated service logic (`php spark test:auth`, 20 assertions)
and as real HTTP routes a browser can actually click through
(`/register`, `/login`, and the intermediate OTP/mPIN steps), tested via
real HTTP requests against a running server, not just the service layer.

**What's real:**
- `app/Database/Migrations/2026-01-01-000010_CreateOtpVerification.php` —
  OTP storage (not present in the original BR/PR schema design, added as
  a necessary supporting table).
- `app/Libraries/AuthService.php` — OTP generation/verification (BR-02),
  Indian mobile format validation (BR-03), mPIN set/verify, and the exact
  3-consecutive-failure lockout requiring OTP re-verification before reset
  or re-authentication.
- `app/Controllers/AuthController.php` + `app/Views/auth/*` — real,
  session-based multi-step web flow (not a JSON API — consistent with D-10's
  server-rendered-views decision).

**SMS provider is still stubbed** (per the tech stack's open item) — in
development, the OTP is shown directly on-screen with a clearly labeled
"Dev mode" notice. This must be removed/disabled once a real SMS provider
(MSG91/Twilio/Fast2SMS — still TBD) is selected and integrated; the
on-screen OTP display is a development convenience only and would be a
serious security issue in production if left in place.

**Real bugs found and fixed during this build:**
1. `PartyModel`'s `allowedFields` was missing `mobile_verified_at`,
   causing CodeIgniter's mass-assignment protection to silently drop that
   field on update — the write appeared to succeed (no exception) but
   wrote nothing. Caught by a failing test assertion, not silently missed.
2. Testing via `curl -d` with a literal `+` in the mobile number initially
   produced a false failure — `application/x-www-form-urlencoded` treats
   `+` as a space, so `+919876543210` arrived at the server as a
   space-prefixed number missing its country code. This is a test-tooling
   artifact (a real browser form correctly URL-encodes `+` as `%2B`), not
   an application bug — noted here so it isn't mistaken for one if
   encountered again during manual testing.

**Testing note for this environment specifically:** background server
processes (`php -S ... &`) do not persist across separate tool
invocations in Claude's sandbox — full request-flow tests had to be run
as a single atomic command (start server, run all curl requests, stop
server) rather than split across turns. Not relevant to Arpit's real
server, where the PHP-FPM/Apache-Nginx process runs continuously as a
proper service.
---

### D-14: Easy Auction wired to real, browser-clickable HTTP routes — full flow verified

**Decision:** The Easy Auction flow (BR-11/12/13/14/25/27/28) now has real
HTTP routes and views, not just tested service-layer logic. Verified with
a complete end-to-end run over real HTTP: register seller + buyer → create
listing → submit for approval → approve → attach Easy Auction (RV) →
approve sale event → grace period → freeze to active → buyer funds EMD →
buyer places bid → current price and H1 standing correctly reflected, down
to the database level (not just the rendered page).

**New controllers/views:** `ListingController`, `SaleEventController`,
`BidController`, and `app/Views/listing/*`.

**⚠️ Dev-only endpoints — NOT production-ready, clearly marked in code:**
- `ListingController::devApprove` / `devReject` — no real Tenant Admin
  authorization check exists yet (BR-09/21/22 role-based access isn't
  built). Anyone logged in can currently approve/reject any listing.
- `SaleEventController::devApprove` — same caveat.
- `SaleEventController::devForceFreeze` — bypasses BR-14's real 60-minute
  grace window, which can't be waited out in a live test/demo session. The
  real transition is meant to be time-based via a scheduled job (not yet
  built).
- `BidController::devFundEmd` — simulates a cleared EMD payment. BR-26's
  real payment gateway routing (VAN/credit card) is not integrated — the
  gateway provider itself is still a tech-stack open item (TBD).

**These four endpoints must be removed or properly gated before any real
users touch this system.** They exist solely so the tested business logic
could be demonstrated as an actual clickable flow rather than only proven
via `spark` test commands.

**Real bugs found and fixed during this build:**
1. Route ordering/extraction wasn't the issue it first appeared to be —
   the actual cause of an early 404 batch was environment timing (a stale
   server process on a reused port), not a routing bug. Confirmed by
   isolating individual route calls before re-running the full sequence.
2. `Config\App::$baseURL` was hardcoded to `http://localhost:8080/`,
   which only matters when CodeIgniter generates outgoing URLs (redirects,
   `site_url()`) — it does NOT affect incoming route matching, which is
   request-based regardless of this setting. **This still matters for a
   real deployment**: `app.baseURL` must be set via `.env` to the actual
   domain the app is served from, or redirects will point at the wrong
   host. Documented in `SETUP.md`.
3. Confirmed CodeIgniter 4.7.4 rejects an empty-string `baseURL` outright
   (`Config\App::$baseURL "/" is not a valid URL`) — the per-environment
   override must always be a real URL via `.env`, never left blank.
---

### D-15: Trust & Support content pages published — legal docs with confirmed-pending fields

**Decision:** All Trust & Support hub cards now link to real, rendered
pages instead of placeholder text: FAQ, Dos & Don'ts, Security & Trust,
Fee & Charges Schedule (Track 1 — operational content), and Terms of
Usage, Privacy Policy, Grievance Redressal, Refund & Cancellation, Dispute
Resolution, Cookie Policy (Track 2 — legal documents).

**Important context on Track 2:** the source documents in the project's
knowledge base still contain their original "DRAFT — not for publication
until reviewed by qualified counsel" notices and unfilled bracketed
fields (entity name, effective date, Grievance Officer name/contact,
jurisdiction/city, and — for the Cookie Policy — an unmade analytics
tooling decision). This was flagged explicitly to the project owner
before publishing. **The project owner's confirmed decision:** the
structural/substantive content is approved as final; the draft warning
banners should be removed; the still-unfilled fields should remain
visible as clearly-labeled placeholders rather than be invented or
silently hidden.

**Implementation:** each unfilled field renders as a styled "Pending — to
be published" note (`.legal-pending` CSS class) rather than raw
`[to be inserted]` bracket text — same honesty, doesn't look like a
rendering bug to a visitor. This was Claude's suggestion as a middle
ground once the project owner's decision was given, not a unilateral
softening of what "pending" means.

**Action still needed before full legal completeness:** the entity name,
effective date, Grievance Officer details, jurisdiction city, and (for
Cookie Policy specifically) the actual analytics tooling decision all
still need real values supplied and wired in to replace the pending notes.

**Scope note:** the legal document content rendered reflects the
substantial majority of each source document's sections, retrieved from
the project's knowledge base across several searches. Some numbered
sections in the middle of longer documents (e.g., ToS Sections 13-19)
were not individually retrieved/transcribed in this pass — if the project
owner notices a section is missing from a live page, that's why; ask
Claude to pull the remaining sections in a follow-up rather than assuming
they were deliberately omitted.

**Not yet linked from the hub:** a Terminology glossary page
(`/terminology`) was also built with real glossary content, but no card
was added to point to it from the Trust & Support hub yet — accessible
directly by URL only for now.
---

### D-16: D-15 follow-up fixes — Terminology linked from hub, ToS gap filled

**Decision:** Two loose ends from D-15 closed out:
1. Terminology glossary (`/terminology`) now has a card on the Trust &
   Support hub — previously built but only reachable by direct URL.
2. Terms of Usage previously-missing sections retrieved and added:
   Section 4 (Account Security), 5 (Nature of the Platform), 6 (Tenants
   & Shops), 13 (Star Ratings), 14 (Disputes), 15 (Prohibited Conduct),
   16 (Shipping & Delivery), 17 (Content & IP), 18 (Data & Privacy),
   19 (Limitation of Liability). The page now renders 23 total sections
   (verified via HTTP), up from the partial version shipped in D-15.

**Verified:** re-tested over real HTTP — `/terms` now includes "Prohibited
Conduct" (Section 15), hub links to `/terminology`, and `/terminology`
still resolves directly. No regressions to the other 11 pages from D-15.
---

### D-17: Real Tenant Admin authorization — replaces the listing/sale-event dev-only shortcuts from D-14

**Decision:** BR-09's Tenant Admin authority is now genuinely enforced,
not simulated. `ListingController::devApprove/devReject` and
`SaleEventController::devApprove` are replaced with real `approve`/
`reject` methods gated by a new `tenantAdmin` route filter
(`app/Filters/TenantAdminFilter.php`), which checks the logged-in
party actually holds an active `tenant_admin` role (`party_role` table,
BR-19/BR-44) for the specific tenant that owns the target listing/sale
event — not just "any logged-in user."

**New pieces:**
- `app/Models/PartyRoleModel.php` — the `party_role` table had a
  migration since Phase 0 but no model until now.
- `app/Libraries/AuthorizationService.php` — resolves a listing/sale
  event to its tenant and checks the role.
- `app/Filters/TenantAdminFilter.php` — CI4 route filter, returns 403
  if the caller isn't the right tenant's admin.
- `php spark grant:tenant-admin <mobile> <tenant_id>` — interim CLI
  bootstrap tool. No Super Admin panel exists yet to grant this role
  through a UI, so this exists purely so a Tenant Admin can be
  provisioned at all. Should be retired once real Super Admin tooling
  is built.

**What's still a dev-only stand-in (from D-14), now partially addressed:**
- `SaleEventController::devForceFreeze` — still skips BR-14's real
  60-minute grace window (that's a time mechanic, not an authorization
  gap), but is now ALSO gated behind the same `tenantAdmin` filter, so at
  least only a real admin can trigger it.
- `BidController::devFundEmd` — unchanged; this stands in for a missing
  payment gateway integration, a different category of gap entirely, not
  an authorization issue.

**Verified:** real HTTP test — a registered non-admin party attempting
`/listings/{id}/approve` on someone else's listing receives **403**,
confirmed the listing status was unchanged in the database. After
granting the `tenant_admin` role via the new spark command, the identical
request from that party succeeds (303 redirect), confirmed via direct
database read that `listing.status` actually transitioned to `upcoming`.
Full regression: all 89 previously-passing assertions across
cascade/rating/lifecycle/auth still pass — no regressions introduced.
---

### D-18: Deployment gate — Buy-Now and Express Auction must be fully working first

**Decision:** The i2k2 server deployment will not happen until Buy-Now and
Express Auction are built, tested, and demonstrable end-to-end — the same
bar Easy Auction was held to (D-14, D-17). Easy Auction alone is not
sufficient to trigger deployment.

**Rationale (project owner's stated reasoning):** deployment should happen
once there's "complete infra... to fully test and run" these two formats,
not incrementally per-format. This avoids deploying a partially-capable
system and then patching it live.

**Practical effect on build order:** Buy-Now and Express Auction routes/
controllers/views are the next priority, following the same pattern
established for Easy Auction — service-layer logic already exists and is
tested (EmdService already handles 'buy_now' and 'express' baseline
calculation; BiddingService/CascadeService are format-aware), but neither
format has real HTTP routes yet, the same gap Easy Auction had until D-14.
---

### D-19: Buy-Now fully wired to real HTTP routes — BR-42, BR-29, BR-27 verified end-to-end

**Decision:** Buy-Now is now a complete, working sale format — real HTTP
routes, not just tested service logic, matching the bar set for Easy
Auction (D-14) and gated by the same real Tenant Admin authorization
(D-17) for listing/sale-event approval.

**New pieces:**
- `offer` table (migration 11) — a dedicated concept, deliberately NOT
  reusing the `bid` table, since Buy-Now offers don't compete head-to-head
  (no H1/H2/H3) — each stands independently until the seller picks one.
- `OfferModel`, `OfferService` — BR-27 EMD gate (10% of Expected Value),
  BR-42 trust-over-price discretion (seller can accept a non-highest
  offer, but a reason is mandatory when they do), BR-29 EMD adjustment
  (top-up owed if accepted price > EV, refund if below).
- `OfferController` + extended `listing/show.php` — submit an offer,
  withdraw one (reason required, per policy — a 3-day unactioned lapse
  needs no reason, handled separately by `OfferService::lapseStaleOffers()`,
  not yet wired to a scheduler), and the seller's accept UI showing all
  offers with a reason field.
- `SaleEventController::createSubmit` extended to branch on `sale_format`
  rather than being Easy-only.

**Verified end-to-end over real HTTP, not just `spark` tests:** registered
a seller + 2 buyers, created and approved a listing, attached a Buy-Now
event (EV ₹100,000), funded EMD for both buyers, submitted a higher
offer (₹120,000) and a lower one (₹95,000). Confirmed accepting the lower
offer *without* a reason is blocked with the exact BR-42 error shown on
the actual page; accepting it *with* a reason succeeds. Verified in the
database: the higher offer auto-rejected, the accepted offer's reason
logged, `sale_event.current_price` = 95000 (not the EV or the higher
offer), the winning buyer's EMD correctly recalculated to ₹9,500 (10% of
the accepted price, a refund since it closed below EV) and still held
pending settlement, and the losing buyer's EMD released, not forfeited.

**Real bug found and fixed:** the listing page's price display only ever
showed the Expected Value for Buy-Now events, even after a sale closed —
so a ₹95,000 accepted deal still displayed "₹100,000 expected." Fixed to
show the actual accepted price, clearly labeled, once `status = closed_sold`.

**New dev-only stand-in, flagged same as the others:**
`OfferController::accept` is currently gated only by login, not by a
check that the caller actually owns the listing being sold — unlike
listing/sale-event approval (BR-09, Tenant Admin), this decision belongs
to the **seller** specifically (BR-42), and that ownership check doesn't
exist yet. Must be added before production use.

**Not yet wired:** `OfferService::lapseStaleOffers()` (the 3-day
no-reason-needed auto-lapse) exists and works, but nothing calls it on a
schedule — no cron/scheduled-job infrastructure exists yet.
---

### D-20: Express Auction fully wired to real HTTP routes — the last sale format before the D-18 deployment gate

**Decision:** Express Auction is now complete and real — the automatic
"launch on 3rd distinct buyer pledge" mechanic (PR-11) genuinely works
over real HTTP, not simulated. Per D-18, this was the final piece gating
deployment: Easy Auction (D-14), Buy-Now (D-19), and now Express are all
built, tested, and demonstrable end-to-end.

**Key design decision:** Express reuses `sale_event.scheduled_start_at`/
`scheduled_end_at` — columns that existed in the schema since Phase 0 but
were unused — rather than adding new schema. `scheduled_start_at` being
set is what "bidding phase has opened" means; `scheduled_end_at` is the
1-hour run-window deadline. No new migration was needed for this format.

**New pieces:** `ExpressAuctionService` (pledge tracking, exact-3rd-pledge
trigger, bidding-phase gate wrapping the already-tested `BiddingService`),
`ExpressController`, and Express-specific UI in `listing/show.php`
(live pledge counter, pledge button, bid form that only appears once
bidding is genuinely open).

**Verified over real HTTP, not just `spark` tests:** registered a seller
+ 3 buyers, created/approved a listing, attached Express (RV ₹50,000).
Confirmed via direct database read: after 1st and 2nd pledges,
`scheduled_start_at` stayed NULL (bidding correctly not open — also
verified the bid form doesn't even render on the page at this stage).
After the 3rd distinct pledge, `scheduled_start_at` was set automatically
with **no admin/seller action** — confirmed via database read, not just
page appearance. Placed a real bid (₹60,000, displayed correctly),
force-closed the window (Tenant Admin–gated action), then confirmed a
further bid attempt correctly shows "the 1-hour Express bidding window
has closed" on the actual page.

**Test-layer confirmation (spark test:express, 16 assertions) additionally
proved:** a 4th pledge does NOT reset the bidding window once already
triggered, and BR-43's 150% anti-jacking ceiling still applies inside
Express bidding exactly as it does in Easy (same underlying
`BiddingService`, correctly reused rather than reimplemented).

**Full regression: 121 total assertions across all six engines
(cascade/rating/lifecycle/auth/buy-now/express), zero failures.**

**Consistent with every other format built so far, these dev-only
stand-ins exist and are flagged the same way:**
- `ExpressController::pledge` simulates cleared EMD payment — same
  payment-gateway gap as every other format (BidController::devFundEmd,
  OfferController::devFundEmd).
- `ExpressController::devForceCloseBidding` skips the real 1-hour wait —
  gated behind `tenantAdmin`, consistent with the D-17/D-19 pattern, but
  the underlying time-skip itself remains a stand-in pending real
  scheduled-job infrastructure (same gap noted in D-19 for the grace-window
  timer and offer auto-lapse).

**What deployment readiness now looks like:** per D-18, code-side the gate
is met. What deployment itself still requires — server-side database
setup, `composer install` on the real server, `app.baseURL` configuration
— was never blocked by code readiness and remains exactly as described in
`SETUP.md`.
