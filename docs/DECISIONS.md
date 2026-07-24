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
---

### D-21: Deployment gate expanded beyond D-18 — five additional gaps to close before deployment

**Decision:** Following a gap analysis after D-20 (deployment-ready per the
original D-18 criterion), the project owner decided to close five
additional gaps before deploying, rather than deploying with them
outstanding and closing them afterward as D-18 originally implied.

**Gaps being closed, in build order:**
1. **Buy-Now offer-acceptance ownership check** — `OfferController::accept`
   currently lacks a check that the caller is actually the listing's
   seller (flagged as a known gap in D-19). Small fix, same pattern as
   the existing `TenantAdminFilter`.
2. **Scheduled-job infrastructure** — none of the time-based triggers
   (BR-14 grace window, Express's 1-hour countdown, Buy-Now's 3-day offer
   lapse) run automatically; they only advance via dev-only force
   endpoints. The underlying logic is already built and tested — this
   closes the gap between "logic exists" and "logic runs on a schedule."
3. **Settlement / NOC / dual-rating flow (BR-33)** — no real HTTP flow
   exists for both parties to confirm a completed sale and trigger
   ratings. The rating engine itself (`RatingService`) is fully built and
   tested (D-08 note on Shadow Banning threshold still applies) — this
   connects it to real routes, the same kind of gap auth and Easy Auction
   had before D-13/D-14 closed them.
4. **Real Super Admin panel + Super Admin auth (BR-04)** — replacing the
   `grant:tenant-admin` CLI stand-in with actual tenant creation and
   admin management screens, plus Super Admin's separate Auth0/TOTP login
   path (not yet started at all).
5. **Tender Auction format** — the fourth and last sale format, previously
   deprioritized (Company Shop exclusive, lowest business priority per the
   original roadmap) — now being built to the same standard as the other
   three.

**Explicitly NOT included in this expanded gate** (per the project
owner's own sequencing choice): filling in the legal document blank
fields (Gap 7 — requires real values only the project owner can supply,
not a coding task) and a third-party security audit (Gap 9 — an external
procurement action, not something more code produces). Neither blocks
deployment by itself under this decision, but both remain open items.

**Rationale:** deploying with a genuinely large, connected feature area
(admin/settlement/scheduling) still missing was judged worse than a
longer pre-deployment build phase, given this is a fintech-adjacent
platform.
---

### D-22: Gap 4 closed — Buy-Now offer acceptance now enforces seller ownership

**Decision:** `OfferController::accept` now verifies the logged-in party
is actually the listing's seller before allowing offer acceptance —
closing the gap flagged in D-19 and scheduled in D-21.

**Verified over real HTTP:** a registered non-seller ("attacker") attempting
to accept an offer on someone else's listing receives **403** with the
exact message "BR-42: only the listing's seller may accept an offer on
it." The real seller's identical request succeeds (303), confirmed in the
database that the offer's status actually transitioned to `accepted`.

**Full regression: all 121 assertions across all six engines still pass.**

**Remaining from D-21:** scheduled-job infrastructure, settlement/NOC/
dual-rating flow, Super Admin panel + auth, Tender Auction.
---

### D-23: Deployment gate re-scoped based on complete BR/PR audit — supersedes D-21

**Decision:** Following a full audit against all 61 BRs and 36 PRs (not
just the 5 gaps previously identified), the deployment gate is
re-scoped. D-21's list is superseded by this decision.

**Context that triggered this:** the audit surfaced that no listing photo/
media upload exists anywhere in the application — despite BR-11, BR-45,
BR-59, and BR-60 all treating listing photos as mandatory — which is a
more fundamental gap than anything on D-21's list. This was not
previously flagged as a gap because it was never explicitly checked
against the full BR/PR document until now.

**New deployment gate — Tiers 1, 2, and 3, in this order:**

**Tier 1 (build first — the core journey is broken without these):**
1. Listing media upload (BR-11, BR-45, BR-59, BR-60)
2. Settlement — Dual-NOC & Mandatory Rating Gate (BR-33, PR-22)
3. Stall Resolution (BR-39, PR-23) — ships alongside #2
4. Seller rating visible pre-bid (BR-41) — small fix, bundled in opportunistically

**Tier 2 (the safety net once real deposits/goods are involved):**
5. Dispute Resolution Framework (BR-40, PR-24)
6. Scheduled-job infrastructure (closes Gap 3 from D-21)

**Tier 3 (needed to operate as a real, multi-tenant business):**
7. Super Admin panel + auth (BR-04) — replaces the `grant:tenant-admin`
   CLI stand-in
8. Tenant onboarding workflow (BR-06, BR-07)
9. Conflict-of-interest blocks (BR-21, BR-22)

**Explicitly deferred past this deployment** (project owner's decision):
- Tender Auction format (Gap 8 from D-21 — unchanged, stays lowest priority)
- Tier 4 items: full audit trail/hot-cold tiering (BR-05), KYC verification
  flow (BR-17), buyer preferences/CLV (BR-23), shipping (BR-24), GST
  invoicing (BR-56), AML screening (BR-54), Express defect disclosure
  (BR-57), AI listing pre-audit (BR-46), Seller Standing Review (BR-61)
- **Payment gateway and SMS provider integration** — explicitly deferred
  to AFTER this deployment. The project owner's stated plan: deploy first
  with the existing dev-only EMD-funding stubs and on-screen OTP display
  still in place, then connect the real PG and SMS providers against the
  live deployed site and test that integration there, rather than
  building it in isolation beforehand.

**Nature of this deployment, per the project owner's own framing:** "a
working site with most infrastructure complete," kept "strictly for
testing purposes" initially — i.e., an internal/controlled deployment,
not a public launch. This is consistent with real money not yet being
able to move (PG still stubbed) and OTP still being visible on-screen
(SMS still stubbed) even after Tiers 1-3 are complete.
---

### D-24: Tier 1 Item 1 — Listing media upload (BR-11, BR-45, BR-59, BR-60) — the single biggest gap, now closed

**Decision:** Real photo upload now exists — the single most customer-
breaking gap identified in the full BR/PR audit. Sellers can upload
photos (5-50, BR-11), designate a primary photo, and choose between
Verified and Certified-by-Seller media tiers (BR-59) at listing creation.
`ListingLifecycleService::submitForApproval` now enforces the 5-photo
minimum — a listing genuinely cannot be submitted for review without it.

**Honest limitations, not silently glossed over:**
1. **BR-45's GPS/timestamp capture is best-effort, not guaranteed.**
   BR-45 describes automatic capture "at the moment of capture," which on
   a native mobile app means automatic EXIF/sensor data. This is a WEB
   application — GPS is only captured if the browser's Geolocation API
   is available and the user grants permission, submitted as ordinary
   form fields, not verified device sensor data. This is a real gap
   between what BR-45 describes and what a web app can actually
   guarantee, flagged explicitly rather than treated as equivalent.
2. **BR-59's "genuine photo, not stock imagery" requirement is NOT
   code-enforced anywhere.** Detecting whether an uploaded photo is a
   real photo of the actual item versus stock/generated imagery would
   require computer-vision fraud detection — out of scope. This remains
   a trust/audit-time concern, same as it would be for any platform
   without a dedicated CV pipeline.
3. **Verified tier (inspector-captured) is recorded but not enforced.**
   Selecting "Verified" at listing time doesn't currently trigger any
   real inspection workflow or block seller self-upload — the actual
   in-person inspection remains a real-world process outside what this
   build enforces.

**Two real bugs found and fixed during this build:**
1. **Postgres boolean strings are truthy in PHP.** This driver returns
   Postgres booleans as literal `'t'`/`'f'` strings, and PHP treats the
   non-empty string `"f"` as truthy. `ListingMediaModel::findForListing`
   was returning `is_primary` as this raw string, causing the listing
   page to show **every** photo as "PRIMARY" regardless of the actual
   value. Fixed by explicitly casting to real PHP booleans on retrieval.
   This bug class was already known and correctly handled in
   `RatingService`/`TestRating` — it just hadn't been applied
   consistently to this new model. Worth checking any future boolean
   field the same way.
2. **The pre-existing `test:lifecycle` suite broke** once the BR-11 gate
   was added — it had never accounted for a photo requirement since that
   requirement didn't exist when it was written. Fixed by setting a
   simulated media count directly via the model (real file uploads
   aren't practical inside a CLI test), while the real upload path is
   separately verified via genuine HTTP multipart requests with real
   JPEG files (see verification below), not just the CLI test.

**Verified over real HTTP with genuine JPEG files (not empty/fake
files):** uploaded 3 photos (below the 5-photo minimum) — submission for
approval correctly blocked with the exact BR-11 message. Uploaded 3 more
(6 total) — submission then succeeded. Confirmed a non-owner attempting
to upload photos to someone else's listing receives 403. Confirmed
switching the primary photo via `setPrimary` correctly demotes the prior
primary — verified directly in the database, not just the rendered page,
specifically because the boolean bug above had already fooled a
page-level check once in this same session.

**Full regression: all 121 assertions across all six engines still pass.**
---

### D-25: Tier 1 Items 2 & 3 — Settlement/Dual-NOC/Rating Gate (BR-33) and Stall Resolution (BR-39)

**Decision:** A sale can now actually finish. Previously a sale event
could reach `closed_sold` with no real way to formally close — no
mechanism for both parties to confirm the physical transaction, rate
each other, or release/deduct from the buyer's EMD. This closes that gap.

**New pieces:** `settlement` table, `SettlementModel`, `SettlementService`
(dual-NOC confirmation, mandatory bidirectional rating, auto-completion,
fee deduction on success, BR-39 stall flagging and forced-neutral
resolution), `SettlementController`, real HTTP routes, and a settlement
detail view.

**A real, previously-undiscovered gap fixed as part of this build:**
`CascadeService::processTopupPaid` (the Easy/Express "winner pays"
handler) never actually closed the `sale_event` or created any way to
reach settlement — Easy and Express auctions had **no path to formal
closure at all**, even before this Tier's work began. This wasn't on any
previous gap list because nothing had tested that far down the flow.
Fixed alongside settlement creation itself.

**BR-33's fee deduction on a successful sale was never built before now**
— only the BR-34 forfeiture math (for a *default*) existed.
`EmdService::calculateSettlementFee` is new, and deliberately reuses the
same `emd_hold` columns the forfeiture math uses (`forfeited_to_tenant_
amount`/`forfeited_to_saas_amount`) for the fee split — same shape of
data, different real-world cause (a successful sale, not a default),
documented clearly in the model to avoid confusion later.

**Rating mechanism note:** this codebase's rating engine works via
relative upgrade/downgrade deltas (BR-35/36), not a direct "set to N
stars" input. Settlement ratings are mapped onto this: a "good" outcome
applies a modest automatic upgrade (BR-36 — no approval needed); a
"problem" outcome initiates a downgrade through the EXISTING BR-36
approval-gated flow, with a mandatory reason — it does not apply
immediately, consistent with every other downgrade already in this
codebase.

**Real bugs found and fixed during this build:**
1. **Accidentally deleted `EmdHoldModel::markReleased`** while adding the
   new `markSettled` method — a `str_replace` replaced the method instead
   of adding alongside it. Caught immediately by grepping for callers
   before it could break the three other places that depend on it
   (`ListingLifecycleService`, `OfferService` x2).
2. **`checkCompletion` only transitioned `'pending'` → `'completed'`**,
   so a settlement force-resolved out of `'stalled'` status could never
   actually reach `'completed'` — the status guard blocked it. Caught by
   a failing test assertion, not a silent gap.

**Verified over real HTTP, not just `spark` tests:** ran the complete
flow — listing → photos → approval → Buy-Now offer → acceptance →
settlement auto-created → all four steps confirmed by the correct
parties → settlement reaches `completed`. Confirmed directly in the
database: fee math exactly correct (₹58,000 sale, 5% fee = ₹2,900 split
₹2,610 tenant / ₹290 SaaS, buyer refund ₹3,100 from a ₹6,000 hold).
Stall flagging and forced-neutral resolution verified via `spark
test:settlement` (backdating a settlement's `created_at` to simulate 8
days passing, since a real 7-day wait isn't practical to test live).

**Full regression: 142 assertions across all seven engines, zero failures.**

**Note on the 7-day stall threshold:** not explicitly quantified in the
retrieved BR/PR text — a reasonable default, flagged the same way the
OTP-attempt limit was in `AuthService`, not treated as a settled business
rule requiring no further confirmation.

**Remaining from D-23:** Tier 1 Item 4 (seller rating visible pre-bid —
small, still open), Tier 2 (Dispute Resolution Framework, scheduled-job
infrastructure — this settlement's stall-flagging and BR-14's timers all
still require manual/dev-only triggering), Tier 3 (Super Admin panel,
tenant onboarding, conflict-of-interest blocks).
---

### D-27: Tier 2 Item 1 — Dispute Resolution Framework (BR-40)

**Decision:** The full Dispute Resolution Framework now exists — filing,
evidence, ruling with REAL execution (not just recorded outcome labels),
and a one-level appeal to Super Admin. This was the largest single gap
identified in the original BR/PR audit (D-23).

**A real dependency surfaced and resolved deliberately, not silently:**
BR-40 requires a Super Admin to rule on `buyer_non_response` disputes and
hear all appeals — but Super Admin auth/panel is Tier 3, planned to come
*after* Tier 2. Rather than block Dispute Resolution on Tier 3, a minimal
Super Admin **authorization** concept was built now (`party_role` with
`role='super_admin'`, `tenant_id=NULL`, granted via
`php spark grant:super-admin`) — explicitly flagged everywhere it appears
in code as **NOT** BR-04's real Auth0/TOTP Super Admin login path, which
remains genuinely deferred. This is the same kind of interim stand-in as
`grant:tenant-admin` has always been, applied consistently.

**New pieces:** `dispute`/`dispute_evidence` tables, `DisputeModel`,
`DisputeEvidenceModel`, `DisputeService` (the substantial piece — filing
with category-based routing, evidence collection, ruling that actually
executes its outcome by reusing `SettlementService`/`EmdHoldModel`/
`RatingService` rather than duplicating logic, and appeal), plus real
HTTP routes and views.

**Ruling outcomes genuinely execute, verified specifically because this
matters:** `order_forfeiture` actually calls the same BR-34 forfeiture
allocation math already tested for cascade defaults — confirmed via
database read that a buyer's EMD hold was marked `forfeited` with the
allocation correctly summing to the full held amount, not just a status
label. `rating_consequence` actually calls `RatingService::
initiateDowngrade` and self-approves it at the correct BR-36 tier (Tenant
Admin ruling → Tenant Admin approval; Super Admin ruling → both approval
tiers, since Super Admin outranks the dual-gate) — confirmed the seller's
actual `seller_star_rating` decreased, not just an event being recorded.

**Known simplifications, flagged rather than hidden:**
1. **The precise per-category filing-window trigger event isn't specified
   precisely enough in the source document to implement five different
   anchors confidently** — one consistent anchor (the sale_event's
   `actual_closed_at`) is used for the 7-day window instead. The 7-day
   figure itself is carried from the plain-language guide, which itself
   flags it as "not independently reconfirmed" — not a settled figure.
2. **Evidence is text-only in this pass** — no file/photo attachment for
   dispute evidence (`MediaService` exists for listings but wasn't
   extended here to keep scope contained). A real limitation for disputes
   that would benefit from photographic evidence (e.g., condition_delivery).
3. **An appeal ruling records the final decision but does NOT
   automatically reverse whatever the original ruling already executed**
   (a forfeiture already processed, a rating already changed). If an
   appeal overturns the original ruling, reversing its real-world effects
   is a manual admin action, not automated. Flagged directly in the
   service code, not discovered later.
4. **Standing Review (BR-40's sixth category, BR-61) deliberately
   excluded from the category enum** — it's system-initiated (not
   user-filed) and BR-61 itself isn't built (Tier 4), so including a
   category with no system to trigger it would have been misleading.

**Real bugs found and fixed BEFORE they shipped** (caught during writing/
testing, not after):
1. A malformed tenant lookup in the forfeiture execution branch (passed
   an array where a tenant ID string was expected) — caught before the
   first test run.
2. `executeRuling` referenced `$dispute['ruled_by_party_id']`, which is
   only saved to the database *after* `executeRuling` runs — always null
   at the point it was read. Fixed by passing the ruler's ID as an
   explicit parameter instead of relying on a not-yet-persisted field.
3. **A pre-existing bug from D-24**, unrelated to this feature but found
   while testing it: `ListingController::submitForApproval` had no
   try/catch around the BR-11 photo-count check, unlike every other
   controller action — so a listing without enough photos crashed with a
   raw 500 error instead of a friendly redirect message. This bug existed
   since D-24 and was never caught because D-24's own test always
   uploaded enough photos first. Fixed here.

**Verified over real HTTP, not just `spark` tests:** ran the complete
flow — listing with photos → approval (by the actual granted Tenant
Admin, not the seller) → Buy-Now sale → dispute filed by buyer → evidence
from both sides → Tenant Admin ruling (rating_consequence) → seller
appeals → Super Admin rules on the appeal → dispute reaches `closed`.
Confirmed directly in the database: `status=closed`,
`ruling_outcome=rating_consequence`, `ruling_authority_type=tenant_admin`,
appeal ruling recorded.

**Full regression: 160 assertions across all eight engines, zero failures.**

**Remaining from D-23:** Tier 2's second item (scheduled-job
infrastructure), then Tier 3 (Super Admin panel + REAL auth — this build
made the authorization gap even more visible, since a real login/2FA
path for Super Admin is now clearly needed, not just role membership;
tenant onboarding; conflict-of-interest blocks).
---

### D-28: Tier 2 Item 2 — scheduled-job infrastructure — Tier 2 fully closed

**Decision:** Every time-based trigger that previously required a manual
"dev-force" action now has a real automation path — `SchedulerService`,
callable via `php spark run:scheduler`, intended to run every minute via
a real cron entry (documented in `SETUP.md`).

**What this actually automates:**
1. BR-14 grace window expiry (Easy/Buy-Now) — auto-freezes to `active`.
2. **Express's bidding-window expiry auto-initiating the cascade** — this
   was a genuine, previously-undiscovered gap: nothing, not even a
   dev-force action, had ever automatically started the cascade when
   Express's 1-hour window ended. `devForceCloseBidding` only expired the
   window itself; something still had to separately call
   `CascadeService::initiateCascade`, and until this build, nothing did.
3. Buy-Now's 3-day offer auto-lapse (`OfferService::lapseStaleOffers`,
   built in D-19 but never wired to anything automatic until now).
4. BR-39 settlement stall-flagging (`SettlementService::
   flagStalledSettlements`, same situation — built in D-25, unwired
   until now).

**Honest limitation, not fixed by this and not fixable by scheduling
alone:** Easy Auction was never given a defined "bidding ends at time X"
mechanism anywhere in this codebase — only Express got an explicit
countdown (the pledge-triggered window). The scheduler cannot close an
Easy Auction's bidding phase automatically because no such trigger point
exists to check against. This is a separate, real gap from what
scheduling itself closes — flagged explicitly in both this log and
`SETUP.md` rather than left for Arpit to discover by confusion later.

**Idempotency verified, not assumed:** the Express-cascade path
specifically checks whether H1's bid already has a `topup_required_by`
set before initiating the cascade again — confirmed via test that running
the scheduler twice in a row on the same expired event only processes it
once. This matters because a cron running every minute WILL see the same
expired record on every single run until its status changes; without
this guard, cascade would have been re-initiated dozens of times before
anyone paid.

**Verified against real data:** all four categories tested with genuinely
backdated timestamps (grace period, Express window, stale offer) rather
than just calling the methods with fresh data — confirming the actual
time-comparison logic works, not just that the methods execute.

**Full regression: 173 assertions across all nine engines, zero failures.**

**Tier 2 (D-23) is now fully closed**: Dispute Resolution Framework
(D-27) and scheduled-job infrastructure (D-28) are both built, tested,
and verified.

**Remaining from D-23: Tier 3** — Super Admin panel + REAL auth (BR-04,
distinct from the minimal role-check stand-in built in D-27), tenant
onboarding workflow, conflict-of-interest blocks (BR-21/22).
---

### D-29: Tier 3 — Super Admin real auth (BR-04), tenant onboarding (BR-06/07), conflict-of-interest blocks (BR-21/22) — Tier 3 fully closed, D-23's full gate now met

**Decision:** All three remaining Tier 3 items are built and verified.
This is the last tier of D-23's corrected deployment gate — all of D-23
(Tiers 1, 2, and 3) is now complete.

**BR-04 — real Super Admin authentication, not just a role check:**

A genuine, cryptographically-correct TOTP (RFC 6238) implementation —
compatible with Google Authenticator, Authy, and any standard
authenticator app. **Explicitly flagged substitution:** BR-04 names
"Auth0/TOTP" — Auth0 is a paid external vendor requiring its own account
setup, the same category of dependency as the payment gateway and SMS
provider (both deferred, D-23). TOTP itself is an open standard requiring
no vendor. This delivers genuine 2FA; if Auth0 specifically is needed
later (SSO, centralized management), it can sit alongside or replace this
layer — this is not a fake stand-in.

**Verified the TOTP math is actually correct, not just self-consistent:**
cross-checked `TotpService`'s output against a second, independently
written implementation of the same algorithm — both produced identical
codes for the same secret and time window. This matters because TOTP has
several places a subtle bug (byte-packing order, truncation offset,
base32 padding) could silently produce codes that never validate against
a real authenticator app while still passing tests that only check
self-consistency.

**A real security tightening, not just role storage:** `SuperAdminFilter`
previously (as built in D-27, as an interim stand-in) only checked
`party_role` membership from a REGULAR session — meaning any session
where that party was logged in normally would satisfy it, defeating
BR-04's "separate login path" requirement. Now it requires a distinct
session marker (`super_admin_totp_verified_at`) set only by the real
`/admin/login` flow. Verified over real HTTP: a regular user who never
went through `/admin/login` is redirected away from `/admin`, not shown
the dashboard, even though they hold the `super_admin` role in the
database.

**BR-06/07 — tenant onboarding:** real Super-Admin-gated UI
(`TenantController`, `/admin/tenants/create`) replacing every prior raw
database insert used throughout testing since Phase 0. Tenant creation
IS the whitelisting act per BR-06.

**BR-21/22 — conflict-of-interest blocks:** a listing's own assigned
inspector, and a tenant's own Tenant Admin, are now genuinely blocked
from bidding, offering, or pledging on listings within their scope —
enforced in `AuthorizationService::hasConflictOfInterest`, wired into all
three "commit to buying" entry points (`BiddingService`, `OfferService`,
`ExpressAuctionService`). Verified a genuinely unrelated buyer is NOT
blocked — confirming the check is scoped correctly, not overly broad.

**A real bug caught and fixed, same class as a prior one (D-13):**
`PartyModel`'s `allowedFields` didn't include the new `totp_secret`/
`totp_enabled_at` columns, so `beginTotpSetup`'s update silently failed —
caught immediately by the test suite, not discovered later. Worth noting
this is the second time this exact class of bug (a new column added to a
migration without the corresponding model update) has occurred on this
project — a pattern worth being more careful about on any future schema
addition.

**Verified over real HTTP, the complete flow, not just the service
layer:** registered an account → granted `super_admin` via CLI → visited
the real `/admin/setup-totp` page and extracted the actual secret shown
→ computed a valid code independently → confirmed setup via the real
form → logged in via the separate `/admin/login` form with mPIN + the
computed code → reached the real `/admin` dashboard, confirmed by page
content, not just a redirect status.

**Full regression: 185 assertions across all ten engines, zero failures.**

**Known simplification, flagged, not hidden:** the TOTP secret is stored
in plain text in the database for now — a real production deployment
should encrypt it at rest using CodeIgniter's Encryption service. Noted
in the migration file itself, not just this log.

---

## D-23's full deployment gate is now met — Tiers 1, 2, and 3 all complete

| Tier | Status | Decisions |
|---|---|---|
| Tier 1 | ✅ Complete | D-24, D-25, D-26 |
| Tier 2 | ✅ Complete | D-27, D-28 |
| Tier 3 | ✅ Complete | D-29 |

This closes out the corrected deployment gate established in D-23 after
the full BR/PR audit. Per the project owner's own framing, this
deployment is intended to remain internal/testing-only initially, with
real payment gateway and SMS integration connected and tested against
the live deployed site afterward — both remain deliberately stubbed, not
overlooked.
---

### D-30: Pre-deployment repository audit — duplicate/residual/dead file sweep

**Decision:** A full, systematic audit of the repository was run before
deployment, per the project owner's explicit request to check for
"duplicate and non-relevant or residual entries" and ensure the code
"deploys effortlessly." This went beyond a visual scan — several checks
were done programmatically.

**Checks performed and results:**

1. **Every route verified against real controller methods** (62 routes,
   programmatically checked, not spot-checked) — all valid, none dangling.
2. **Every controller confirmed reachable by at least one route** (16
   controllers) — none orphaned.
3. **No Node.js leftovers, no `.env` committed, no backup/temp/debug
   files** (`var_dump`/`print_r`/`dd()` swept for and found clean).
4. **Migration sequencing verified** — 17 migrations, sequential, no
   gaps or duplicate numbers.
5. **`composer.json` re-verified** — correct project metadata, valid JSON.

**A systematic check for the exact `allowedFields` bug pattern found
twice before (D-13, D-29) — run across every model, not just the two
previously caught instances.** This surfaced several candidates; each was
individually judged rather than blanket-fixed:

- **False positives, correctly left alone:** `placed_at`, `held_at`,
  `granted_at`, `whitelisted_at` are DB-default timestamps the
  application never writes directly — no bug. `saas_fee_percent` and
  `emd_percent` are CHECK-constrained to fixed values (0.50% and 10%
  respectively) — deliberately not application-settable, matching
  BR-08/BR-27. `dynamic_time_trigger_minutes`/`dynamic_time_extension_
  minutes`/`intensity_mode_active` correspond to a Dynamic Time bidding
  extension feature that was never actually built as application logic
  (noted here as a genuine, separate unbuilt feature — not this audit's
  scope to fix).
- **Real fix applied:** `PartyModel` was missing `archived_at` — despite
  a comment in the same file explicitly saying "archived_at handled
  manually" — and all nine `org_*` organization/KYC fields (`org_cin`,
  `org_gstin`, `org_pan`, etc.). Neither is exercised by any currently
  built feature (no party-deactivation flow, no KYC data-entry flow
  exist yet), so this was a **dormant** bug, not an active one — but the
  exact same silent-failure pattern that has now bitten this project
  three times (D-13's `mobile_verified_at`, D-29's `totp_secret`, and
  this). Fixed preemptively before either feature gets built and
  discovers it the hard way.

**Genuine residual files removed:**
- `app/Views/welcome_message.php` — CodeIgniter's stock starter view,
  confirmed via grep to be referenced nowhere except its own displayed
  content. This was believed removed back in D-16 but evidently never
  actually landed on GitHub — found still present during this audit.
- Five stale `.gitkeep` placeholder files sitting in directories that are
  now genuinely populated (`Migrations`, `Models`, `Filters`,
  `Libraries`) — harmless but pure clutter. `.gitkeep` files in
  genuinely-still-empty directories (`Seeds`, `ThirdParty`, `Helpers`)
  were correctly left in place.

**Explicitly investigated and confirmed NOT a problem:** `app/Views/
errors/html/*` and `app/Views/errors/cli/*` initially appeared orphaned
(no explicit `view()` call references them in any controller) — verified
these are CodeIgniter's own internal error-page templates, invoked
automatically by the framework's exception handler, not through normal
application `view()` calls. Correctly left untouched; removing them would
have broken graceful error handling in production.

**Verified nothing broke:** full regression (185 assertions across all
ten test suites) plus a fresh real-HTTP smoke test (`/`, `/trust-support`,
`/login` all returning 200) run after every change in this audit, not
just at the end.
---

### D-31: BR-09 seller approval gate + Tenant Admin dashboard — built directly from source text, no interpretation required

**Decision:** Following the project owner's explicit "no deviation" instruction, both items were built strictly from confirmed BR/PR source text, quoted directly rather than paraphrased from memory.

**BR-09 (exact text):** "The Tenant Admin... holds exclusive authority to
upgrade a buyer to a Seller — scoped strictly to that tenant's own
storefront. A seller upgraded on one tenant has no automatic selling
rights on another. If a seller's account is suspended, all of their
active listings on that tenant are instantly suspended pending review."

**This was a real, previously-unenforced gap**: any registered user could
create a listing on any tenant directly. The listing itself needed
Tenant Admin approval, but nothing gated *who could attempt to list* on a
given tenant in the first place. Now genuinely gated — new
`seller_application` table, `SellerApplicationService` (apply/approve/
reject), and `ListingController::createSubmit` checks `isApprovedSeller`
before allowing creation at all.

**All three parts of BR-09's text were implemented, not just the happy
path**: (1) apply/approve/reject, (2) tenant-scoping enforced via a
`UNIQUE(party_id, tenant_id)` constraint — one application per tenant,
no bleed-through rights, and (3) the suspension cascade — revoking a
seller's status now genuinely suspends every active listing they have on
that specific tenant (`SellerApplicationService::suspendSeller`),
requiring a new `suspended` listing status added to the enum since it
didn't previously exist.

**Verified over real HTTP, both directions**: a buyer attempting to
create a listing before approval is redirected to `/apply-to-sell` with
the exact BR-09 message. After applying and the real Tenant Admin
approving via the real dashboard, the identical listing-creation request
succeeds. Confirmed a stranger (not that tenant's admin) gets 403
attempting to view the dashboard or act on applications.

**Tenant Admin dashboard**: no single BR/PR mandates an exact layout —
built directly from the authorities BR-09/BR-13/BR-40/BR-39/PR-13 already
assign to Tenant Admin (listing approval, sale event approval, seller
applications, dispute rulings, stalled settlement resolution), surfaced
in one real screen at `/tenants/{id}/dashboard#rather than inventing
new authority. Verified counts genuinely reflect live data — tested that
submitting a real seller application changes the dashboard's count from
0 to 1, not a hardcoded display.

**Full regression: 185 assertions across all ten engines, zero failures.**

---

## Still outstanding from this round — explicitly not yet built

Per the discussion before this build began, three items remain, each
with a specific reason it wasn't included in this pass:

1. **Tender Auction** — clarified with the project owner (invitation via
   buyer-directory search, H1-wins selection, manual/offline EMD) but not
   yet built. Next in queue.
2. **PR-9's full Media Upload spec** — a newly-discovered gap, not one of
   the four originally flagged items. What's built (D-24) covers photos
   and the 5-50 count gate; missing against PR-9's actual text: video/
   document upload, WebP transcoding (300KB target), a background upload
   queue, and browser-localStorage autosave. Raised for the project
   owner's decision on priority, not silently deferred.
3. **Easy Auction's "missing timer"** — re-examined against BR-12's exact
   text ("scheduled open bidding at RV... seller's choice at listing").
   This reframes the original finding: BR-12 doesn't describe an
   automatic system-driven close time the way Express's countdown works
   — it implies the SELLER sets a schedule (start/end) as a listing
   parameter, which was never built as a field at all, rather than there
   being a broken automatic timer. Needs the project owner's confirmation
   on this reframing before building anything, since building an
   automatic close mechanism that isn't actually what BR-12 describes
   would itself be a deviation.
---

### D-32: Easy Auction seller-set schedule + Dynamic Time anti-sniping (BR-12)

**Decision:** Confirmed with the project owner that BR-12's "scheduled
open bidding at RV... seller's choice at listing" means the seller sets
their own start/end schedule — not a broken automatic system timer, which
was the original framing before rereading the source text. Additionally
confirmed: the actual close is governed by Dynamic Time (anti-sniping),
not a hard cutoff — a bid landing close to the deadline pushes it back.

**A second discovery of the same unused-columns pattern**: while building
this, found `dynamic_time_trigger_minutes`/`dynamic_time_extension_
minutes` already existed on `sale_event` since Phase 0 (defaults 10/2
minutes) but had never been wired to any logic — flagged during the D-30
audit as an unbuilt feature, now actually implemented. Also found these
same columns were missing from `SaleEventModel::allowedFields` — the
identical silent-failure pattern caught three times before (D-13, D-29,
D-30) — checked and fixed proactively this time, before writing any
logic that would need to write to them, rather than discovering it via a
failed test.

**New piece:** `EasyAuctionService` — mirrors `ExpressAuctionService`'s
structure. Wraps the already-tested `BiddingService` with (1) a bidding-
window gate (blocks bids before the seller's start time or after the end
time) and (2) Dynamic Time: any bid landing within
`dynamic_time_trigger_minutes` of the current end pushes it back by
`dynamic_time_extension_minutes` — can repeat indefinitely if bids keep
landing close to the (moving) deadline.

**Backward compatibility deliberately preserved**: any `sale_event`
created before this feature existed has no schedule set at all — treated
as always-open rather than retroactively blocked, since breaking existing
data would be a bigger problem than not gating it.

**Scheduler extended, including a case that would otherwise hang
forever**: `processExpiredEasyAuctions()` auto-initiates the cascade once
an Easy Auction's schedule genuinely ends — but an Easy Auction that
received ZERO bids before its schedule ended needed separate handling,
since the cascade logic assumes at least one bid exists. Added an
explicit path resolving a zero-bid expired auction to
`cycle_ended_unsold` (an existing enum value, previously never actually
used) — without this, a zero-bid auction would have sat in `active`
status indefinitely, never picked up by anything.

**Verified rigorously, including negative and idempotency cases, not
just the happy path:** bidding correctly blocked before the seller's
start time and after the end time; a bid within the trigger window
genuinely pushes the deadline by exactly the configured amount (not an
arbitrary push); a bid FAR from the deadline does NOT trigger an
extension (confirming the check isn't overly aggressive); the zero-bid
scheduler path resolves correctly; running the scheduler twice on an
already-cascaded event doesn't re-trigger or reset anything; a legacy
sale_event with no schedule at all still allows bidding.

**Two debugging detours during verification, both confirmed as test-
script mistakes, not product bugs**: (1) a `psql` `RETURNING` clause
output got contaminated with the trailing `INSERT 0 1` status line when
captured via a shell variable, corrupting a UUID used in a later query —
fixed by isolating just the first output line; (2) called
`dev-force-freeze` with the seller's session cookie instead of the
Tenant Admin's — correctly rejected with 403, exactly as D-17 intended,
not a flaw in the gate.

**Full regression: 196 assertions across all eleven engines, zero failures.**

**Still outstanding from the original five-item round**: Tender Auction
(clarifications confirmed, not yet built) and PR-9's full Media Upload
spec (explicitly deferred per the project owner's decision — noted as a
known gap, not silently dropped).
---

### D-33: Real marketplace landing page — live data, not the bare hero placeholder

**Decision:** The landing page was a deliberate minimal placeholder since
D-11 (proving the CI4 rewrite rendered a real page), never revisited once
the team moved to building business logic. The project owner correctly
flagged it as not looking like a real marketplace. Rebuilt using the
richer mockup design that existed before the framework rewrite
(`ebid-hub-modern-marketplace.html`) — same visual language, but now
genuinely wired to live data instead of static/fake content.

**What's now real, not hardcoded:**
- Hero product card shows the most recent genuinely active listing
  (photo, category, real current price) — or a graceful "be the first on
  the yard" empty state if nothing is live yet.
- A "Live Right Now" grid of up to 12 real active sale events.
- Category tiles show actual counts from the database
  (`GROUP BY category`), not the mockup's placeholder numbers
  (1,204 / 618 / 973 etc.).
- The hero stat "Live Right Now" reflects a genuine `COUNT()` of active
  sale events.

**Deliberately kept static**: the "How Selling Works" format explainer
and the trust/rating explanation section — these describe platform
features, not live transactional data, the same way most real e-commerce
sites have static "how it works" content alongside live product grids.
Also updated to say "three sale formats live today" with Tender visually
marked "Coming soon" rather than claiming four are available, since
Tender isn't built yet.

**A real bug caught by testing the actual fresh-install case, not just
the happy path with data already populated**: the query joining a
listing's primary photo failed with a 500 error — `pg_query(): ERROR:
column "true" does not exist` — CodeIgniter's query builder was
auto-escaping the raw boolean literal `true` in the join condition as a
quoted column identifier. Fixed by explicitly disabling escaping for that
join clause. This would have broken the landing page immediately on a
genuinely fresh production database with zero listings — exactly the
state Arpit's first deployment will actually be in — caught specifically
because the empty-database case was tested first, not skipped in favor
of the more visually interesting populated case.

**Verified twice, deliberately in this order**: first against a
completely fresh, empty database (confirming both empty states render
correctly — this is what a real first deployment looks like), then
against real populated data end-to-end (confirming the actual grid,
prices, and category counts genuinely reflect the database, not
hardcoded values).

**Full regression: 196 assertions across all eleven engines, zero failures.**
---

### D-34: Tender Auction foundation — interest registration, eligibility, documents, stakeholder access

**Decision:** This session's Tender Auction spec-gathering surfaced real
corrections needed to already-shipped Easy and Express Auction logic,
not just new Tender requirements — logged here in full since "no
deviation" means these need to be tracked as genuine defects, not folded
quietly into a new feature's scope.

**Real corrections identified, not yet applied (Tender was built first,
per the project owner's explicit choice, so further Tender-building could
surface still more corrections before touching Easy/Express in one pass):**

1. **Easy Auction (D-32) is missing a minimum bid increment entirely.**
   The actual Tech Stack Specification's bid-processing engine text states:
   "must exceed current price by at least the required increment (halved
   during Dynamic Time, per Intensity Mode)" — a general platform
   mechanic I never built for Easy. Confirmed: seller selects between
   2%-5% of Reserve Value at creation; the increment halves once when the
   already-existing 10-minute Dynamic Time trigger fires, and stays
   halved (does not re-halve on further clock extensions).

2. **Easy Auction's clock-extension math is wrong.** Currently calculates
   `new_end = current_end + extension`. Confirmed correct math is
   `new_end = MAX(current_end, bid_time + extension)` — extending from
   the bid's own timestamp, never from the current end, and never moving
   the end time earlier than it already was. Confirmed via a worked
   boundary example: a bid landing exactly at the edge of the trigger
   window should produce no change to the end time at all; the current
   code would wrongly extend it regardless.

3. **Express Auction (D-20) is also missing a bid increment.** Confirmed:
   fixed 2% of Reserve Value, calculated automatically (no seller
   choice), halves during a 10-minute-before-end window — this window is
   entirely new, Express currently has no late-stage behavior at all. The
   fixed 1-hour countdown itself remains correct as originally built —
   it does not extend, matching the original design.

These three corrections are logged now and will be applied to Easy and
Express in a dedicated pass once Tender's remaining layers (bidding
mechanics, post-auction workflow) are also fully specified — per the
project owner's explicit sequencing choice, to avoid fixing the shared
foundation twice.

**What's actually built in this session — Tender's foundation layer:**

- `tender_interest` — buyers opt in by registering interest (BR-12: "the
  event... Buyers wanting to participate register their interest").
- `tender_eligibility` — the seller's real whitelist of who may bid,
  tracking whether each approved party came from the interest pool or
  was added directly (both explicitly confirmed as valid paths).
- `tender_document` — Terms of Sale, required documents, and EMD
  information published as part of setting up the event, before buyers
  can meaningfully be approved.
- `tender_stakeholder_token` — genuine read-only access for insurer/
  insured/surveyor via a random 48-character token in a URL, no platform
  account required, confirmed explicitly by the project owner rather than
  building a full auth system these external parties would never use.
- **BR-12/BR-14 enforced at creation, not just assumed**: `SaleEventController`
  now validates the tenant is genuinely `company_shop` class before
  allowing a Tender sale event to be created at all.

**A real bug found and fixed during verification, not dismissed as
flakiness:** the full regression suite failed intermittently on a
`sale_event.ern` uniqueness collision — traced down carefully rather than
assumed to be test-environment noise, and found to be genuine: both
`TestDispute.php` (D-27, testing Tender's exclusion from Dispute
Resolution) and this session's new `TestTenderFoundation.php` hardcoded
the identical ERN string `TEST-TENDER-001`. Invisible when either test
ran alone; guaranteed to collide the moment both ran in the same database
session — exactly what the full regression does. Fixed by renaming one
test's identifier; then swept all twelve test files for any other
duplicate ERN/subdomain/mobile-number strings before considering this
closed, rather than assuming this was the only instance.

**Full regression: 210 assertions across all twelve engines, zero failures.**

**Verified over spark tests, not yet real HTTP** — this foundation layer
has real service-level tests (14 assertions covering the Company Shop
restriction, interest/eligibility flows including the wrong-seller and
already-eligible rejection cases, document publishing authorization, and
stakeholder token generation/resolution/rejection) but no HTTP
controller/routes/views built yet — that's the next layer, alongside
Tender's bidding mechanics (seller-flexible increment, the two-window
Dynamic Time behavior) and the post-auction manual review/rejection
workflow.
---

### D-35: Tender bidding mechanics — increment, dual-window Dynamic Time, and manual EMD audit trail

**Decision:** Built and precisely verified the exact mechanics confirmed
in the previous round's detailed clarification — including a critical
near-miss bug caught during this build that would have broken deployment
on any fresh server if it had shipped.

**A genuinely serious bug found and fixed, not glossed over:** while
tracking down what looked like test flakiness, discovered a real
duplicate migration file (`2026-01-01-000020_CreateTenderBiddingAndReview.php`)
sitting alongside the correct one, both numbered migration 020, both
attempting to create `tender_emd_log` with DIFFERENT, incompatible check-
constraint logic (the stale file used `amount IS NULL` for the no-EMD
case; the current, correct design uses `amount = 0 NOT NULL`, matching
the actual column definition). This file was apparently left over from
an earlier draft within this same session and never cleaned up. On a
provably empty, freshly-created database, `php spark migrate` failed
outright — meaning **this would have broken Arpit's first deployment on
the real server**, not just a sandbox inconvenience. Found only because
the failure was treated as worth root-causing rather than dismissed as
environment noise; confirmed by checking `\dt` for zero relations,
observing the failure persisted anyway, and tracing it to the duplicate
file by grepping for every migration referencing `tender_emd_log`. The
stale file has been deleted entirely.

**What's built and precisely verified:**

1. **Bid increment enforcement** — now a real check inside the shared
   `BiddingService::placeBid` (gated on `bid_increment_amount` being set,
   so it's backward-compatible with every sale_event created before this
   existed). A bid below the required increment is rejected with the
   exact shortfall shown.

2. **Increment halving — 10 minutes before scheduled end, exactly once.**
   Verified: the increment is unchanged before the window, correctly
   halves the first time a bid lands inside it, and — critically — stays
   at the halved value on a second bid inside the same window, not
   re-halving. `increment_halved_at` is the persistence guard.

3. **Anti-snipe extension — matching the worked example precisely.**
   `new_end = MAX(current_end, bid_time + extension)`, not `current_end +
   extension`. Verified against a controlled scenario reproducing the
   exact numbers discussed (a bid landing shortly before a deadline
   extends to bid-time-plus-extension, not deadline-plus-extension), and
   separately verified the boundary case: a bid landing at the *exact*
   edge of the anti-snipe window correctly leaves the end time
   unchanged, rather than blindly extending regardless.

4. **Manual/offline EMD with a mandatory audit trail, enforced at the
   database level, not just in application code.** A real `CHECK`
   constraint on `tender_emd_log` requires either (amount > 0 AND a
   payment location is recorded) OR (amount = 0 AND a reason is
   recorded) — there is no code path that can insert a row satisfying
   neither. Verified all three cases: a real amount without a location
   note is rejected; a waived EMD without a reason is rejected; EMD
   cannot be logged for a party who isn't even eligible to bid.

**Design note on why `bid_increment_amount` reuses the same column name
across formats**: rather than a Tender-specific field, this was added to
`sale_event` generically, since Easy and Express also need this same
field once their corrections (identified in D-34, not yet applied) are
made — avoiding three near-duplicate columns for what's structurally the
same concept, populated differently per format's own rules.

**Full regression: 224 assertions across all thirteen engines, zero
failures** — including a fully clean, continuous run from a freshly
reset database, specifically to rule out any residual doubt after the
migration collision was found.

**Still remaining for Tender**: the post-auction workflow — H1 declared
provisional, extension requests, Tenant-Admin-mediated rejection on
behalf of insurer/insured/surveyor with cascade to H2/H3, final
confirmation, auction reporting, and archival. No real HTTP routes/
controllers/views exist yet for anything in D-34 or D-35 either — both
layers are currently service-layer only, verified via `spark` tests.
---

### D-36: Tender post-auction review workflow — the manual, flexible process fully implemented

**Decision:** Built the complete post-auction workflow confirmed across
the earlier detailed clarification — provisional winner declaration,
buyer-requested/admin-granted extensions, Tenant-Admin-mediated rejection
on behalf of insurer/insured/surveyor with cascade to the next eligible
bidder, final confirmation, auction reporting, and archival (handled via
existing terminal sale_event statuses, not a new status — `closed_sold`
and `cycle_ended_unsold` already represent "no longer active").

**Two more leftover files from the same earlier abandoned draft found
and fixed, not just the migration from D-35**: `TenderReviewModel.php`
already existed on disk with the exact same missing-`allowedFields`
pattern now caught five times on this project — missing `extension_
granted_by_party_id`, which my current migration includes but the old
draft's model didn't know about. Fixed before it could cause a silent
write failure. A broader sweep for any other stray files from that same
draft found nothing further.

**What's built and rigorously verified — the full multi-round cascade,
not just a single happy path:**

1. **Manual, seller-triggered closure** — `closeBiddingAndDeclareProvisional`
   requires the actual listing's seller, not just any logged-in party.
   Creates round 1, provisional, naming the genuine current H1.
2. **Extension** — logged with a reason, no auto-expiry, gated to Tenant
   Admin only (confirmed: insurer/insured/surveyor aren't platform users,
   Tenant Admin acts on their behalf exclusively).
3. **Rejection cascades correctly to the next ELIGIBLE bidder specifically
   — not just next-highest bid, and never back to someone already
   rejected.** Verified across two full rejection rounds (H1 rejected →
   correctly moves to H2, not back to H1; H2 rejected → correctly moves
   to H3, explicitly confirmed it does NOT loop back to H1). The rejected
   party's EMD hold is released, not left dangling.
4. **Confirmation hands off into the exact same Settlement/dual-NOC gate
   every other format uses** — not a separate, parallel closing
   mechanism. Verified a real `Settlement` record is created naming the
   *actual* confirmed winner (buyerC, after two rounds of rejection), not
   the original H1 (buyerA) — the price and buyer correctly reflect where
   the process actually ended up, not where it started.
5. **Full cascade failure** (every eligible bidder rejected, nobody
   left) correctly resolves to `cycle_ended_unsold` rather than being
   left in an undefined or stuck state.
6. **Auction report** — participants, eligibility, full bid history, the
   EMD audit log, and every review round, all pulled from real data.

**Two more real bugs caught during this build, both fixed immediately:**
1. `generateAuctionReport` queried `bid.created_at`, a column that
   doesn't exist — the actual column is `placed_at`. Caught by the test
   actually exercising the report method, not assumed correct.
2. `round_number` compared with PHP's strict `===` against a literal
   integer failed — the same Postgres-integer-returned-as-string pattern
   already seen with booleans (D-24). Checked whether this affects real
   product code (`TenderReviewService`, controllers) — it doesn't, only
   the test's own assertions needed fixing — but flagged as the same
   general class of gotcha worth remembering for any future integer
   comparison against database-sourced values.

**Also caught: a mobile-number collision this time, not an ERN one** —
`TestTenderReview.php`'s buyer numbers collided with `TestScheduler.php`
(D-28). The collision sweep from D-34 only covered ERN and subdomain
strings at the time; re-run now to also cover mobile numbers, confirming
this file is clean against all thirteen other test files.

**Full regression: 245 assertions across all fourteen engines, zero
failures**, verified in one clean, continuous run from a freshly reset
database.

**What remains before Tender is genuinely complete end-to-end**: real
HTTP routes/controllers/views for everything built across D-34, D-35, and
D-36 — all of it is currently service-layer only, proven via `spark`
tests but not yet reachable through an actual browser. Also still
outstanding: a real page for stakeholders to view via their token (the
token generation/resolution mechanism exists and is tested, but no
live-auction-state view has been built for it to render yet), and the
three Easy/Express corrections identified in D-34 (still not yet applied).
---

### D-37: D-34's three corrections applied to Easy and Express — the D-23/D-34 correction backlog is now fully closed

**Decision:** All three corrections flagged in D-34 (discovered while
gathering Tender's exact specification) are now applied, verified, and
consistent with Tender's proven implementation of the same underlying
mechanics.

**1. Easy Auction's clock-extension math corrected** — was
`current_end + extension`, now `MAX(current_end, bid_time + extension)`,
matching Tender's confirmed formula exactly.

**2. Easy Auction now has a real bid increment** — seller selects 2-5%
of Reserve Value at creation (enforced server-side, a submission outside
that range is rejected). Halves once in the same shared 10-minute window
that also governs the clock extension — Easy uses ONE window for both
behaviors, confirmed distinct from Tender's two-window design.

**3. Express Auction now has a real bid increment** — fixed 2% of
Reserve Value, calculated automatically, no seller input. Halves once in
a 10-minute-before-end window that didn't exist for Express at all
before. Critically, **Express's clock itself was verified to still NOT
extend** — the fixed 1-hour countdown remains exactly as originally
designed; only the increment behavior was added. This was specifically
tested, not assumed: a bid placed inside the halving window was
confirmed to leave `scheduled_end_at` completely unchanged while still
correctly halving the increment.

**A real, expected regression caught and properly fixed, not just
patched around**: applying the corrected clock-extension math broke two
assertions in the original D-32 test (`test:easyschedule`), because that
test's own expected values were written against the OLD, buggy formula.
Traced to two things needing correction in the test itself: (1) the test
scenario used a 5-minute-out deadline, which was inside the OLD single-
window model's trigger range but does NOT need clock extension under the
corrected math (`bid_time + 2min` doesn't exceed a 5-minute-out deadline)
— fixed by using a 1-minute-out deadline that genuinely triggers
extension; (2) the expected new-end-time calculation itself still used
`old_end + extension` instead of `bid_time + extension` — fixed to match
the corrected formula precisely, not just loosened to pass.

**Full regression: 254 assertions across all fifteen engines, zero
failures**, in one clean, continuous run from a freshly reset database.

**This closes the correction backlog opened in D-34.** All three items
identified there are now applied. Combined with D-36, this represents
Tender's core logic (foundation, bidding, review) plus the retroactive
fixes to Easy and Express — all now internally consistent with each
other, using the same `bid_increment_amount`/`increment_halved_at`
columns and the same corrected extension math across all three formats
that support it.

**Still remaining, unchanged from D-36**: real HTTP routes/controllers/
views for all of Tender (D-34/35/36 are service-layer only), and a real
stakeholder-facing view for the token-based read-only access (the
generation/resolution mechanism exists and is tested, but nothing renders
for it to display yet).
---

### D-38: Tender's real HTTP layer — the complete workflow, verified genuinely end-to-end over real HTTP

**Decision:** Built the full HTTP layer (routes, `TenderController`,
views) for everything constructed across D-34/35/36/37 — interest
registration, eligibility management (both paths), Terms of Sale/document
publishing, manual EMD logging, bidding, stakeholder read-only access,
and the complete post-auction review workflow. Tender is now genuinely
reachable through a browser, not just proven at the service layer.

**Verified with the real, complete journey, not a shortcut**: registered
seller/Tenant Admin/two buyers → seller applied and was approved on a
genuine Company Shop tenant → created and approved a listing with real
photos → attached a Tender with a seller-chosen increment and real
schedule → one buyer registered interest and was approved from that
list, the second was added directly by mobile number (both paths,
confirmed distinct) → seller published Terms of Sale → Tenant Admin
manually logged EMD for both buyers (one with a real amount, one waived
with a reason) → both buyers bid → seller manually closed bidding,
declaring the correct provisional winner → Tenant Admin rejected that
result with a reason → **verified the cascade correctly moved to the
actual next-highest bidder, not arbitrarily** → Tenant Admin confirmed
the new winner → **verified the final `current_price` and settlement
record reflect the confirmed winner's bid, not the original provisional
winner's** → confirmed the stakeholder view renders with zero login,
showing both bid amounts with no bidder identities, exactly as BR-16
requires.

**Three real mistakes made and corrected during this verification, each
instructive:**

1. **A `curl -d` vs `--data-urlencode` encoding bug** — sending a mobile
   number with a literal `+` via plain `-d` silently turned it into a
   space, causing a party lookup to fail with no visible error in the
   test's own output (both the success and failure paths return the same
   303 status). Caught by checking the database directly rather than
   trusting the HTTP status code alone — the exact same category of
   mistake made and caught earlier in this project's history, worth
   remembering as a recurring trap specifically with `+`-prefixed phone
   numbers in form-encoded test data.
2. **A premature rejection accidentally closed out a real sale event**
   — because eligibility hadn't finished propagating correctly (a
   consequence of mistake #1), only one bidder existed at rejection
   time, so the cascade correctly found nobody left and resolved to
   `cycle_ended_unsold` — genuinely correct product behavior, but not the
   scenario intended to be tested. Required setting up a second, clean
   sale event to properly verify the multi-bidder cascade over HTTP.
3. **Forgot the manual EMD logging step before attempting to bid**, on
   the second sale event — the same omission already caught once while
   writing `TestTenderReview.php`, now repeated in manual HTTP testing.
   Confirms this project's EMD gate is being applied consistently and
   correctly (it blocked exactly when it should have), and that this
   specific setup step is easy to forget — worth flagging clearly in
   `SETUP.md`/documentation for whoever operates this for real.

**Full regression: 254 assertions across all fifteen engines, zero
failures**, confirming the new HTTP layer introduced no service-level
regressions.

**This closes out the entire "no deviation" Tender build** — foundation
(D-34), bidding mechanics (D-35), post-auction review (D-36), the
Easy/Express correction backlog (D-37), and now the real HTTP layer
(D-38) making all of it genuinely usable. Combined with D-33's real
marketplace landing page, the platform now has all four sale formats
(Easy, Buy-Now, Express, Tender) reachable, tested, and verified through
actual HTTP requests, not just service-layer proof.
---

### D-39: Pre-deployment repository cleanup — a genuinely significant regression found and fixed

**Decision:** Full documentation and structural audit for "one-shot
deployment" readiness, per the project owner's explicit request before
closing further gaps. This went well beyond a cosmetic pass.

**The most significant finding: `README.md`'s full deployment guide had
been silently lost since D-24, and nobody — including this session —
noticed until now.** The complete 16-step i2k2 deployment guide
(including the critical PHP 8.2 PPA fix, without which `composer install`
fails outright on Ubuntu 22.04's default PHP 8.1) was added at commit
`32adaa4`. The very next commit that touched `README.md`, `c972d24`
(D-24), **silently overwrote it with an older, simpler cached version** —
almost certainly a stale local copy carried over during that round's
file-copying, not a deliberate change. This means for every commit since
D-24, anyone consulting `README.md` for deployment steps would have found
a generic four-line "quick start" instead of the actual guide — missing
the PHP version fix specifically, which would have caused a real,
confusing failure on the actual i2k2 server. Confirmed via `git show
32adaa4:README.md` that the full 293-line guide genuinely existed before
being lost. Restored in full, then updated throughout to reflect
everything built since (all four sale formats, real Super Admin, 22
migrations instead of 11, fifteen test commands instead of six).

**A second, separate class of drift found**: `SETUP.md` had accumulated
multiple stale and, in one case, genuinely self-contradictory claims —
one section correctly described the real Super Admin TOTP panel (D-29)
while an earlier section in the *same file* still said "no admin panel
exists yet." Also stale: "Tender not yet built" (built, D-34-38),
"Tenant Admin authorization... dev-only stand-in" (real since D-17),
`OfferController::accept`'s missing ownership check (closed in D-22, but
the warning about it was never removed), and Easy Auction's "no defined
bidding-end mechanism" limitation (resolved in D-32). Every one of these
was corrected — not just noted, but rewritten to reflect the actual
current, verified state — and the stale "What's built so far" section
listing 9 tables and 4 test commands was replaced with an accurate
summary pointing to `docs/DECISIONS.md` and the new `docs/SITE_MAP.md`
for full detail, rather than trying to re-describe everything inline
(which is exactly the kind of content that goes stale fastest).

**Structural/code audit, same rigor as D-30, at the current scale:**
- All 79 route→controller-method references verified programmatically —
  every one resolves to a real method, no dangling references.
- Every controller confirmed reachable by at least one route.
- A full `allowedFields`-vs-actual-schema sweep across every model (the
  bug pattern that has now bitten this project five separate times) —
  no new gaps found beyond the already-known, deliberately-excluded
  columns (DB-default timestamps, CHECK-constrained fixed values).
- Migration sequence re-verified — 22 migrations, no gaps, no duplicate
  numbers (specifically checked given D-35 found a genuine duplicate
  migration file earlier this session).
- File placement confirmed to follow CodeIgniter convention throughout
  (Controllers/Libraries/Models each flat within their type, as the
  framework expects) — no files sitting in the wrong place.

**Final verification — a genuine one-shot deployment simulation, not
just a read-through**: built a fresh copy of the repository, restored
`vendor/` and `.env` (simulating a real `composer install` +
configuration step, since Claude's sandbox itself cannot reach Packagist
— see D-11), reset the database completely, ran every migration from
zero, and ran the complete test suite. **254 assertions, all fifteen
suites, zero failures, on a genuinely fresh setup** — not a reused,
already-migrated database.

**New file**: `docs/SITE_MAP.md` — every real, working route in the
application, organized by who can access it, with an honest, explicit
list of what's built-but-unreachable (the listing edit and emergency-stop
logic specifically) versus what's genuinely not built yet versus what's
deliberately deferred. Referenced from both `README.md` and `SETUP.md`
going forward instead of duplicating page-by-page detail in three places.
---

### D-40: Navigation gaps closed — logout, My Listings, My Activity, Profile, Browse, and wiring up two fully-built-but-unreachable features

**Decision:** Closed every navigation gap flagged in `docs/SITE_MAP.md`
(D-38's audit). All six items verified over real HTTP, not just
service-layer tests, since navigation is inherently a UI/routing concern.

**A worse gap than initially documented, found while fixing it**: the
header nav didn't just lack session-awareness — it always showed "Log
In" with a literal `href="#"` regardless of actual login state, and the
same for "List an Asset". Neither link ever worked. Fixed to be genuinely
session-aware: shows My Listings/My Activity/Profile/Log Out when
authenticated, Log In when not — verified both states over real HTTP.

**What's now real:**
1. **Logout** (`AuthController::logout`) — was missing entirely; only
   Super Admin had a logout route.
2. **My Listings** — a seller's own listings, real query, joined to
   whatever sale event each has.
3. **My Activity** — bids, offers, and settlements/purchases, all
   genuinely queried per logged-in party.
4. **Profile** — mobile number, both rating scores, KYC status, straight
   from the party record.
5. **Browse** — a real all-listings page with category and format
   filters, distinct from the landing page's 12-item preview.
6. **Listing edit and Emergency Stop** — `ListingLifecycleService::
   requestMaterialEdit`/`emergencyStop` were fully built and tested
   since early in the project but had zero HTTP route. Both now wired,
   both re-verified with real access control (a stranger blocked from
   editing someone else's listing with 403; a seller — not Tenant Admin —
   blocked from emergency-stopping with 403; a missing reason correctly
   rejected rather than silently accepted).

**Real bugs caught during this build, not before:**
1. **A bug in my own new controller code** — `ListingController::
   editSubmit` initially accessed `$result['id']` on
   `requestMaterialEdit`'s return value, but the actual structure is
   `$result['newListing']['id']` (a nested array, confirmed by reading
   the service method directly rather than assuming). Caught before
   testing, by checking the actual return shape first.
2. **A real, previously-latent query bug surfaced by the new Browse
   page**: `select('DISTINCT l.category')` — CodeIgniter's query builder
   auto-escapes each token it identifies as a column, and mis-parsed
   `DISTINCT` itself as a column identifier, producing `SELECT
   "DISTINCT" "l"."category"` — a syntax error. Fixed using CI4's proper
   `distinct()` method instead of embedding the keyword in the select
   string. The same category of escaping issue as the `true`-literal bug
   found in D-33's landing page work, now recognized as a recurring
   pattern with this specific query builder rather than a one-off.

**Verified thoroughly over real HTTP**: session-aware header in both
states; My Listings/Activity/Profile all showing genuine per-party data;
Browse working with no filter, a category filter, and a format filter;
listing edit correctly archiving the original (`archived_at` set,
`superseded_by_listing_id` pointing at the new record — confirmed the
`status` column deliberately stays unchanged, since archival is tracked
via the timestamp field, not a status transition, matching the existing
pattern elsewhere in the schema) while creating a genuinely updated new
listing; emergency stop correctly setting `status=cancelled` with the
real reason stored.

**Full regression: 254 assertions across all fifteen engines, zero
failures.**

**Site map gaps still remaining, unchanged**: Super Admin cannot view/
edit an existing tenant (only creation exists), no TOTP recovery path,
no tenant discovery/directory page for browsing shops before applying to
sell — plus everything already listed as deliberately deferred (KYC,
full media spec, payment gateway/SMS).
---

### D-41: TOTP recovery, dual-channel mPIN reset, and the final two site-map items

**Decision:** Closed the last three items from `docs/SITE_MAP.md`, plus
the account-recovery discussion that preceded this build.

**TOTP recovery — CLI-based, as agreed after discussion.** Explored and
ruled out relying on Google/Microsoft Authenticator's own cloud sync —
correctly identified as entirely outside this platform's visibility or
control (the server only ever sees a 6-digit code, never which app or
sync state produced it). Also discussed and scoped down a richer
email+mobile+secret-question design once it became clear this only
applies to Super Admin (1-2 people), not the general user base — the
richer design would have been disproportionate to the actual risk being
solved. Landed on `php spark reset-totp <mobile>`, matching this
project's established pattern for sensitive bootstrap actions
(`grant:super-admin`, `grant:tenant-admin`). Verified: genuinely clears
`totp_secret`/`totp_enabled_at` in the database (not just a UI message),
and correctly refuses to act on any party who isn't actually a Super
Admin.

**Dual-channel mPIN reset — email + mobile together, per the project
owner's explicit request.** Scope confirmed during discussion: this is
specifically for the project owner's own account (hardcoded default
email `psinghalnoida@gmail.com`), not a general feature for every user.
`php spark set-recovery-email <mobile> [email]` sets it, matching the
same CLI-bootstrap pattern. Once set, mPIN reset requires **both** a
mobile OTP and an email OTP, submitted together — verified specifically
that the mobile code alone is correctly rejected with an explicit "both
required together" message, not silently accepted. Accounts with no
recovery email keep the exact original mobile-only behavior — verified
unchanged.

**Two real bugs found and fixed while building this — both from
assumptions about the existing schema that turned out wrong:**
1. `otp_verification.purpose` is a strict Postgres ENUM, not free text —
   the new `mpin_reset_email` value caused a genuine 500 error on the
   very first real test. Same class of fix as D-27's `party_role_type`
   enum extension — additive migration, not a rebuild.
2. `otp_verification.mobile_number` was `VARCHAR(13)`, sized exactly for
   `+91XXXXXXXXXX` phone numbers — too narrow for an email address.
   Widened via migration rather than building a separate table, since
   the column's actual role is "whatever channel identifier this OTP was
   sent to," which email addresses fit the same conceptual role as.

**Email sending itself is honestly dev-stubbed**, same pattern as SMS
throughout this entire project — the OTP is shown on-screen rather than
actually emailed, clearly flagged in code comments, pending a real
SMTP/transactional email service connected post-deployment.

**Tenant view/edit for Super Admin** — was create-only since D-29;
now a real detail/edit page exists (`/admin/tenants/{id}`), gated behind
the same `superAdmin` filter as tenant creation. Deliberately did not
make `tenant_class`/`subdomain` editable through this form — changing
either affects existing listings and links in ways that need a
deliberate decision, not a quick form field.

**Tenant discovery directory** (`/tenants`) — public, no login required,
lists every whitelisted tenant with a direct "Apply to Sell" link.
Closes the gap where a seller previously needed to already know a
tenant's ID before they could apply. Linked from the header nav ("Sell").

**Full regression: 254 assertions across all fifteen engines, zero
failures**, verified fresh after both enum/schema fixes.

**This closes every item from `docs/SITE_MAP.md`'s gap list.** Remaining
work is now entirely the items that were already known and explicitly
deferred: `dev`→`main` merge (done, see the merge that preceded this
session), legal document blanks (waiting on real values from the project
owner), a real security audit (external engagement), and the actual
i2k2 server deployment itself.
