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
---

### D-42: Real-time bidding updates via a Node.js WebSocket sidecar

**Decision:** Built genuine real-time push for live bidding — a Node.js
WebSocket server running as a separate process alongside the PHP
application, confirmed working end-to-end with a real bid placed through
the actual application reaching a live WebSocket client within seconds.

**Why a separate process, not something added to PHP directly**: this
was discussed and confirmed explicitly before building anything.
CodeIgniter/PHP has no native way to hold a connection open and push
updates — every request runs, responds, and ends. Three real options
were laid out: a Node.js sidecar (chosen), a PHP-based approach (Swoole/
ReactPHP, which needs either an uncommon PHP extension or its own
long-running process anyway), or a managed third-party service (Pusher/
Ably — ruled out because, like the payment gateway and SMS provider, it
needs a real vendor account and API key that can't be created inside
this sandbox, meaning it couldn't be built and verified the same honest
way as everything else on this project).

**Architecture**: `realtime/server.js` is a standalone Node.js process
(the `ws` package) that maintains WebSocket "rooms" keyed by
`sale_event_id` — a browser only receives updates for the specific
auction it's viewing, never a global firehose. PHP notifies this process
via an internal HTTP endpoint (`/broadcast`), protected by a shared
secret never exposed to browsers, whenever a bid-relevant event actually
happens. The sidecar deliberately has zero knowledge of BR rules, EMD, or
any business logic — it's purely a message relay; PHP decides what
happened and is correct, the sidecar's only job is getting that message
to browsers instantly.

**Fails silently by design, verified specifically**: `RealtimeBroadcastService`
uses a 500ms timeout and treats any failure (sidecar down, network
issue) as a non-event — bidding itself must never be blocked or slowed
by real-time infrastructure being unavailable. Confirmed this explicitly:
ran the entire 254-assertion regression suite with the sidecar not
running at all, and every test still passed — the broadcast calls fail
quietly and the core transactional logic is completely unaffected.

**Wired into the one shared choke point, not duplicated per format**:
the `bid_placed` broadcast lives inside `BiddingService::placeBid`
itself — the core logic already shared by Easy, Express, and Tender — so
one broadcast call covers all three formats rather than three separate,
easy-to-drift copies. Dynamic Time extension and increment-halving
events are broadcast separately from each format's own service
(`EasyAuctionService`, `ExpressAuctionService`, `TenderBiddingService`),
since those are genuine state changes distinct from a new bid landing.

**Verified with a real, complete end-to-end test, not a mocked one**:
started both the Node.js sidecar and the PHP application together,
registered real accounts, built a real listing through to an active Easy
Auction sale event, connected a genuine WebSocket client (a separate
Node.js process, not the same one as the server) watching that specific
`sale_event_id`, then placed a real bid through the actual HTTP endpoint
— confirming the client received the broadcast with the *correct* real
data (amount, standing, bidder party ID) within roughly one second,
without any refresh.

**A real environment lesson hit and fixed during this build**: background
processes started in one tool call don't survive into a separate tool
call in this sandboxed environment — an early attempt to start the
sidecar and PHP server, then test them in a follow-up call, failed
because both processes had already been silently killed. Fixed by
keeping the sidecar, PHP server, and the full test sequence inside one
self-contained block, the same pattern already used successfully
throughout the rest of this project's real-HTTP verification work.

**Browser-side JavaScript added to `listing/show.php`**: connects only
while a sale event is genuinely `active`, updates the visible price
in-place on a `bid_placed` event, and shows a light status line on
`dynamic_time_update`. Wrapped in a try/catch and an `onerror` handler
that does nothing but silently give up — a browser that can't reach the
sidecar (or is on a network that blocks the WebSocket port) sees the
page behave exactly as it did before this feature existed, never a
broken or degraded experience.

**Deployment guide updated with a new Step 13** — installing Node.js,
running the sidecar as a systemd service (so it survives reboots and
restarts automatically on crash), and the two options for exposing port
8081 to browsers (direct, or proxied through Nginx's existing HTTPS
setup) — with an explicit flag that the Nginx-proxy route requires a
small code change to the hardcoded port in the browser script, not just
config, if that path is chosen.

**Honest scope boundary, not silently assumed**: this covers the
scenario explicitly discussed and confirmed — dozens of simultaneous
bidders per item, sub-second latency. It has not been load-tested at
that specific scale (the sandbox environment can't simulate fifty
concurrent real browser connections); the *mechanism* is proven correct
end-to-end, but real-world load behavior at the upper end of "dozens"
should be watched once live, not assumed identical to this single-client
test.

**Full regression: 254 assertions across all fifteen engines, zero
failures**, both with and without the sidecar running.
---

### D-43: Real server-side media compression — images and video, per PR-9's actual spec

**Decision:** Built genuine server-side compression, closing the gap
flagged repeatedly since D-24/D-34/D-38 and explicitly surfaced again
when the project owner asked directly whether it existed. It did not —
confirmed by reading the actual code (`$file->move($uploadDir, $newName)`
was the entire storage step, nothing in between) before answering, not
from memory.

**Images**: PHP's GD extension (confirmed available, including native
WebP encoding) — every uploaded JPEG/PNG/WebP is resized to a 1920px
maximum dimension (proportional, never distorting aspect ratio) and
re-encoded to WebP, stepping quality downward from 80 until PR-9's
300KB target is met or a 40-quality floor is hit (never degraded further
than that, even if the target isn't reached). PNG transparency is
flattened onto white before re-encoding, rather than left to render as
an unintended black background.

**Video**: ffmpeg (confirmed installed, a genuine system dependency, not
a PHP library) — transcoded to a 1280×720 cap, 4Mbps target bitrate,
H.264/AAC in an MP4 container, trimmed to PR-9's 120-second cap rather
than rejected outright (a seller re-uploading a shorter clip is real
friction for what's usually an incidental overage). `media_type` is now
tracked per file (`photo`/`video`) — video is explicitly optional and
**never counted against BR-11's 5-50 photo requirement**, a real bug
caught and fixed before it shipped: the original count logic would have
let a seller pass the "5 photos required" gate with fewer real photos
than required if a video was also uploaded, since it counted all files
together.

**A genuine bug found and fixed mid-build, not assumed away**: the
quality-stepping compression loop repeatedly overwrites the same output
path as it steps quality downward, and PHP's `filesize()` caches its
result per path — meaning repeated checks on the same overwritten file
were returning **stale sizes from an earlier iteration**, not the actual
current file. Confirmed directly: a manual reproduction showed
`filesize()` reporting the previous iteration's size while
`clearstatcache()` correctly revealed the real, current value. Without
this fix, the loop was making decisions on wrong data and reporting the
wrong final compressed size to the caller. Fixed by explicitly clearing
the stat cache before every size check.

**A second genuine bug found during real HTTP testing, not caught by
unit-level tests**: uploading multiple photos plus a video in one
request silently failed — a `303` response, but nothing stored,
correctly not assumed to mean success and checked against the database
directly instead. Traced to PHP's own `post_max_size`/`upload_max_filesize`
ini settings (8M/2M by default) being far smaller than what this feature
actually needs — and critically, **exceeding `post_max_size` causes PHP
to silently empty `$_POST`/`$_FILES` entirely rather than raising any
error**, which is exactly why the failure looked identical to "no files
were selected" with nothing in the application logs. This is not just a
sandbox quirk — it will affect the real i2k2 server identically unless
configured, so it's now an explicit, flagged step in the deployment
guide (`post_max_size = 550M`, `upload_max_filesize = 520M`), not just a
note in this log.

**Verified with real, non-trivial test data, not synthetic minimal
files**: generated a realistic 4032×3024 (4MB) test photo matching a
typical phone camera, and a real 1080p/10-second test video via ffmpeg's
own test-pattern generator, plus a 150-second video specifically to
verify the trim-at-120s path. Confirmed via `ffprobe` against the actual
output files (not just the code's own reported values) that resolution,
duration, and format all match exactly what was intended. The tiny
1.3KB/200×200 synthetic images used throughout the rest of this
project's testing were deliberately NOT reused here, since they're too
small to meaningfully exercise compression at all.

**Verified over real HTTP end-to-end**, with the actual multipart upload
endpoint: 5 real ~1.5MB photos plus a real video, uploaded together in
one request, confirmed via direct database and disk inspection — each
photo compressed from ~1.5MB to ~102KB (93% reduction), the video
transcoded and its 10-second duration correctly recorded, all 6 files
genuinely present on disk with no leftover raw uploads, and
`listing.media_count` correctly showing 5 (photos only), not 6.

**Full regression: 254 assertions across all fifteen engines, zero
failures.**

**Deployment guide updated**, not just this log: `php8.2-gd` added to
the PHP extension install list, a new explicit `ffmpeg` installation
step, and the critical `post_max_size`/`upload_max_filesize` fix — all
in Step 3, flagged as critical since skipping it reproduces the exact
silent-failure behavior found during this session's testing.
---

### D-44: BR-21 conflict-of-interest fix — the first item from the full BR/PR audit

**Decision:** Fixed the narrow scope found in `docs/BR_PR_AUDIT.md` —
BR-21 names three distinct bound roles (Surveyor, Yard Inspector,
Physical Custodian) that must each independently be blocked from
bidding/offering/pledging on their own listing, but only one field
(`inspector_party_id`, covering Yard Inspector) was ever checked.

**A bigger discovery made while fixing this**: none of the three roles —
not even the one that already existed — had ever been captured through
any real form anywhere in the application. The conflict check itself was
correctly built and tested (D-29), but it had been checking a field that
no real user flow could ever actually populate. This entire feature had
been dormant in real usage since it was first built, only ever set via
direct database manipulation in tests. Found by tracing every usage of
`inspector_party_id` across the codebase before writing any new code,
not assumed.

**What's now real**: `surveyor_party_id` and `custodian_party_id` added
to `listing` (both optional, matching BR-11's minimal case of binding a
single inspection authority — a listing is not required to populate all
three). All three fields are now captured on the actual listing creation
form, entered by mobile number and resolved to a party ID — consistent
with the same by-mobile-number pattern already used for Tender
eligibility. `AuthorizationService::hasConflictOfInterest` now checks
all three, with a distinct, role-specific message for each.

**Verified precisely, not just "does it block"**: extended the existing
BR-21 spark test to confirm surveyor and custodian are now genuinely
blocked (previously they weren't — the test itself proves the fix, not
just describes it), while re-confirming a genuinely unrelated buyer is
still NOT blocked, so the fix isn't overly broad. Then verified over real
HTTP — the first time this feature has ever been exercised outside a
unit-style test: created a listing through the actual form, bound a
surveyor by mobile number, confirmed it resolved correctly to a party ID
in the database, and confirmed that surveyor is genuinely blocked from
submitting a real Buy-Now offer, with the exact role-specific message
shown on the actual page.

**Full regression: 256 assertions across all fifteen engines (tier3 grew
from 12 to 14), zero failures.**

**Source**: this is the first fix to come out of `docs/BR_PR_AUDIT.md`
(the comprehensive audit completed this session) — logged there as
priority item #1, now closed. The audit's other findings remain open,
prioritized in that document.
---

### D-45: BR-05 audit trail — hash-chained, tamper-evident, genuinely proven against real tampering

**Decision:** Built the hard, novel core of BR-05 — an append-only,
hash-chained audit trail — from `docs/BR_PR_AUDIT.md`'s top-priority
item. This is deliberately the foundation-first piece of a larger
build; full wiring across every application action and cold-tier cloud
archival remain explicitly outstanding (see below).

**Two independent layers of protection, both genuinely verified, not
just implemented:**

1. **Database-level lockdown** — `audit_log`'s `REVOKE UPDATE, DELETE,
   TRUNCATE` from the application's own database role, confirmed via
   `information_schema.role_table_grants`, not just assumed from the
   migration's SQL. **A real gap caught in my own first draft**: I
   initially revoked only UPDATE and DELETE — TRUNCATE achieves the same
   devastating effect (wiping the entire table) and was still granted,
   found only by actually querying the grant table rather than trusting
   the REVOKE statement I'd written covered everything. Fixed, and
   re-verified from a completely fresh migration run, not just patched
   in the sandbox.

2. **Hash chaining** — each record's hash covers its own content plus
   the previous record's hash, so altering any single record breaks
   every subsequent link. **Verified against a genuine attack, not a
   theoretical description**: deliberately corrupted a real record by
   connecting as the `postgres` superuser directly — bypassing the
   application and its database-role restrictions entirely, exactly the
   threat model BR-05 describes — then confirmed `verifyChainIntegrity()`
   correctly detected the break and pinpointed the *exact* tampered
   record's sequence number, not just "something is wrong somewhere."

**Concurrency correctness, also verified, not assumed**: a Postgres
advisory lock serializes writes, so two near-simultaneous log calls
can't both read the same "previous hash" and fork the chain. Confirmed
with 10 rapid sequential writes all chaining correctly with no gaps or
forks.

**A real gap found and fixed while wiring the first actual integration
point (login events)**: `AuthService::authenticateWithMpin` has a third
return path — `status: 'invalid_mpin'` for a wrong password that hasn't
yet hit the 3-strike lockout — that I initially missed entirely, only
handling the thrown-exception and two other status cases. Found by
actually testing a real wrong-password attempt over HTTP and checking
the database, not by assuming the three branches I'd written were
exhaustive. The user-facing behavior was always correct (a working
fallback error message already existed); only the *audit logging* for
this specific, common case was the new gap. Fixed and re-verified.

**Log Reader built and access-controlled**, per BR-05's "exposed
exclusively to the Super Admin": a real page listing recent entries with
event-type/actor filters, plus a genuine, callable integrity-check page
— not just a described property, an actual button that re-walks the
whole chain on demand. Verified over real HTTP: a genuine Super Admin
(real TOTP login) can view both pages; a regular user is correctly
redirected away.

**Full regression: 270 assertions across all sixteen engines, zero
failures.**

**Explicitly NOT done — a foundation, not the complete BR-05 build:**
- **Wiring is currently limited to login events only** (success,
  failure, invalid mPIN, OTP-required/lockout). BR-05 calls for "every
  operational interaction, configuration change, and transactional
  action" — bids, EMD changes, settlement NOCs, dispute rulings, admin
  role grants, and everything else remain unwired. This is a large,
  ongoing task across the whole codebase, not something to claim
  complete after one integration point.
- **Hot/cold tiering is entirely unbuilt** — no job identifies records
  older than 1 year, no compression, no encryption, no upload to cold
  cloud storage. This is the same category of gap as the payment
  gateway and SMS provider — Google Cloud Storage needs a real account
  and credentials this sandbox cannot create — but unlike those, the
  actual export/compression *logic* hasn't been attempted yet either,
  only flagged as needing to be built once cold storage is available.
- **5-year retention policy** is not enforced anywhere, since it depends
  on the cold tier existing.
- **BR-58's statutory export**, which depends on this audit trail,
  remains blocked until wiring is comprehensive enough to be useful for
  that purpose.

**Next from the audit, per the priority order already discussed**: BR-38
(Crawl-Back/Shadow Banning), or continuing to widen this audit trail's
wiring across more of the application before moving to a new BR entirely.
---

### D-46: Audit trail wiring widened — and two genuine hash-reproducibility bugs found by testing against realistic data

**Decision:** Widened `AuditLogService` wiring beyond D-45's login-only
foundation to cover the core transactional and authority actions: bid
placement, both settlement NOC confirmations, dispute rulings (both the
first ruling and appeal rulings), and three sensitive configuration
changes — Super Admin role grants, Tenant Admin role grants, seller
application approval, and seller suspension.

**Two genuine bugs found, both only surfaced by testing against a
realistic, multi-source chain — not caught by D-45's original isolated
test with just 3 records.** Running the full regression suite for the
first time after this wiring meant `audit_log` accumulated 40+ real
entries from bidding, settlement, disputes, and admin actions across
every other test suite before `test:auditlog` itself ran — and the
"clean chain" assertion failed on this realistic data, even with zero
actual tampering. Investigated properly rather than dismissed as
flakiness (the same discipline as every prior "leftover data" scare on
this project, but this one turned out to be real):

1. **Timestamp round-trip fidelity.** `occurred_at` was `TIMESTAMPTZ`,
   and Postgres trims trailing zero fractional-second digits on storage
   (`.517160` becomes `.51716`). Re-deriving the hash-input string by
   reading this column back and reformatting it did not reliably
   reproduce the exact string used at write time — breaking
   verification on genuinely untampered data. Fixed by adding
   `occurred_at_canonical` (plain `TEXT`, storing the exact string used
   for hashing, immune to any database-side reformatting), while keeping
   the `TIMESTAMPTZ` column for real querying/sorting.

2. **JSON reformatting on storage.** `payload` was `JSONB` — a
   reformatting type. Postgres parses JSONB into an internal binary
   representation and reserializes it on every read, without guaranteeing
   the original key order or exact whitespace survives the round trip.
   Confirmed directly: a payload written as `{"saleEventId":...,
   "amount":...,"standing":...}` came back from the database as
   `{"amount": ..., "standing": ..., "saleEventId": ...}` — different key
   order, added whitespace. Fixed by changing `payload` to plain `TEXT`
   — JSON validity becomes an application-level property (already true,
   since `AuditLogService` only ever writes valid JSON) rather than a
   database-enforced one, but exact byte fidelity is what tamper-evidence
   actually depends on, not a database-level JSON-validity constraint.
   Fixed at the source migration itself (028), not just patched via a
   later `ALTER`, so a genuinely fresh deployment never has this bug at
   all — the `ALTER`-based migration (030) exists to repair any
   environment that already ran the old JSONB version.

**Verified precisely — the second, harder run mattered more than the
first.** After both fixes, ran the complete regression fresh: 40+ real,
varied entries from actual application logic (not synthetic test data)
verify as a genuinely clean chain, while the original deliberate-tampering
test (D-45) still correctly detects real corruption when introduced. This
is a meaningfully stronger proof than D-45's original isolated 3-record
test, since it exercises the hashing logic against the actual variety of
real payloads (nullable fields, nested objects, different actor types)
the application genuinely produces.

**Full regression: 270 assertions across all sixteen engines, zero
failures**, confirmed in a fully continuous single pass with no prior
standalone runs polluting the result.

**Wiring still outstanding**, same honest scope as D-45: EMD fund/
release/forfeit events, listing approval/rejection, Tender's full
workflow, scheduled-job actions, and most Tenant Admin actions remain
unwired. This session's batch specifically targeted the highest-value
transactional and authority actions, not exhaustive coverage.
---

### D-47: Audit trail widened further — listing approval/rejection and both EMD forfeiture paths

**Decision:** Continued widening `AuditLogService` wiring from D-46's
transactional core: listing approval/rejection, and both distinct
forfeiture paths — the system-triggered cascade default (`CascadeService`)
and the human-decided dispute-ordered forfeiture (`DisputeService`),
logged as genuinely distinct event types rather than conflated into one.

**A real gap found before writing any audit code**: `ListingLifecycleService::
approve()`/`reject()` didn't accept an actor party ID at all — and the
controller wasn't even fetching the logged-in session's party ID to pass
in, despite that being trivially available and used everywhere else in
this codebase. Fixed by adding the parameter as optional (default
`null`, preserving backward compatibility with the one existing test
caller that doesn't pass one) and threading the real session actor
through from the controller.

**Verified the actor-threading fix genuinely works, not just that it
compiles**: over real HTTP, registered a real Tenant Admin, had them
approve a real listing through the actual page, then confirmed via a SQL
join that the resulting audit entry names their genuine mobile number —
not null, not a placeholder.

**EMD forfeiture logged with real financial detail, not just "it
happened"**: both events record the actual amount and its full
Tenant/SaaS/Seller allocation breakdown (matching BR-34's split), and
the cascade-triggered path additionally records whether it was a
full-cascade failure. These are the highest-stakes financial events this
platform produces — a bidder genuinely losing real money — and are
logged accordingly.

**Full regression re-run at each step, matching the discipline
established by D-46's hard-won lesson**: this session's changes further
grew the realistic, multi-source audit chain (now also including listing
approvals and both forfeiture types from real test execution) and it
continues to verify as genuinely clean — 270 assertions across all
sixteen engines, zero failures, in one continuous pass.

**Wiring still outstanding, same honest scope as D-45/D-46**: EMD hold
creation/pledge/release (as opposed to forfeiture specifically), Tender's
full workflow beyond what D-46 already covered, scheduled-job actions,
and most remaining Tenant Admin actions.
---

### D-48: Audit trail wiring — EMD hold creation/release across every format, and scheduled-job runs

**Decision:** Closed the remaining EMD gap flagged after D-47 (forfeiture
was covered; creation and release were not) across Easy, Buy-Now, and
Express, plus every genuine release path — Tender rejection, Buy-Now's
losing-offer bulk release, and emergency stop's bulk release. Also logs
every automatic scheduler run as one summary event.

**The same actor-threading gap found twice more, both fixed properly,
not worked around:**
1. `OfferService::acceptOffer` didn't accept an actor party ID, and the
   controller — despite already verifying the caller is genuinely the
   listing's seller (D-22) — never passed that identity through.
2. `ListingLifecycleService::emergencyStop` had the identical gap.

Both fixed the same way as D-47's listing-approval fix: an optional
parameter (preserving backward compatibility with every existing test
caller, all of which use the old signature), threaded from the real
session actor in the controller. `emergencyStop`'s fix also required
changing `releaseAllHoldsForSaleEvent` from `void` to returning the
actual count released, so the audit entry could report a real number
instead of just "something happened." The material-edit cascade
(`requestMaterialEdit` → `cancelOpenSaleEventsForListing` →
`emergencyStop`) now correctly attributes to the listing's own seller,
since a seller's own edit request — not a Tenant Admin action — is what
triggers that specific cascade.

**A real test-setup mistake caught before it could look like a product
bug**: the first real-HTTP verification attempt showed zero `offer.
accepted`/`emd.released` entries despite the offers apparently having
gone through. Investigated properly rather than assumed — the sale event
was still sitting in `grace_period` (BR-14's correctly-enforced 60-minute
window), so the offer submissions themselves had silently failed with a
real, correct rejection message. Not a bug in the wiring; a forgotten
force-freeze step in the test, the same recurring lesson from earlier in
this project. Redone correctly, and the full real cycle verified: two
buyers fund EMD, the seller accepts the higher offer through the actual
page, and the resulting audit trail shows all four events in order —
both `emd.held` entries, `offer.accepted` naming the real seller, and
`emd.released` correctly reporting one hold returned with the accurate
reason.

**Scheduled-job runs logged as one summary record per run, not one entry
per item touched** — a deliberate choice: a busy scheduler sweep
processing dozens of expired grace periods and stale offers in one pass
would otherwise flood the log with near-identical entries for what is
fundamentally one automatic sweep. `actor_party_id` is `null`, correctly
representing a genuinely system-triggered event with no human decision
behind it. Only logs when the run actually did something — an empty
sweep (the common case, since most scheduler runs find nothing expired)
doesn't add noise to the trail.

**Full regression: 270 assertions across all sixteen engines, zero
failures**, re-run at each step per the discipline established by D-46.

**What this closes out**: EMD is now wired at every stage that matters —
creation (D-48), forfeiture (D-47), and release (D-48) — across every
sale format that uses the shared EMD mechanism. Combined with D-45
through D-47, the audit trail now covers: authentication, bidding,
listing approval/rejection, settlement NOCs, dispute rulings (including
appeals), admin role grants, seller approval/suspension, the full EMD
lifecycle, emergency stops, and every automatic scheduler run.

**Still outstanding**: Tender's eligibility-grant and manual-EMD-logging
steps specifically (distinct from the review/forfeiture/release paths
already covered), and cold-tier archival (blocked on real Google Cloud
credentials, same category as the payment gateway).
---

### D-49: Audit trail wiring completed — Tender's full workflow, and a permanent convention so this stays part of every future feature

**Decision:** Closed the last genuinely outstanding wiring gap flagged
after D-48 — Tender's eligibility grants, document publishing,
stakeholder link generation, manual EMD logging, bidding-close,
extension, rejection, and confirmation — and, per the project owner's
explicit request, established a documented, discoverable convention so
audit logging becomes a standing requirement for all future development,
not a one-off completed task that quietly stops being followed.

**Seven Tender actions wired**, covering both `TenderService` and
`TenderReviewService`: `tender.eligibility_granted`,
`tender.document_published`, `tender.stakeholder_link_generated`,
`emd.held` (the manual/offline EMD entry itself, distinct from
BiddingService's van-collected path), `tender.bidding_closed`,
`tender.extension_granted`, and — filling a real gap where only the
*resulting* EMD release was logged, not the ruling decision itself —
`tender.result_rejected`, `tender.cycle_ended_unsold`, and
`tender.winner_confirmed`.

**A genuine security-conscious decision made while wiring the
stakeholder link**: deliberately excluded the token's actual value from
the audit payload. Logging "a stakeholder link was generated, by whom,
with what label" is correct; logging the token string itself would turn
the audit log into a live credential store — anyone with Log Reader
access could extract working access tokens, defeating the point of
having a separately-scoped, no-login stakeholder view in the first
place.

**Verified against the same standard set by D-46's hard lesson**: ran
the full Tender-specific test suites (`tenderfoundation`, `tenderbidding`,
`tenderreview`) immediately followed by `test:auditlog` in one continuous
pass — confirming the chain, now carrying every new Tender event type
alongside everything from D-45 through D-48, still verifies as genuinely
clean. 270 assertions across all sixteen engines, zero failures.

**The second, equally important half of this session**: added a
permanent, first-class convention section to `SETUP.md`, matching the
weight and visibility of the existing UUID-generation convention (the
one other "every new piece of code must follow this" rule already
established on this project). Defines the four categories that require
audit logging (financial events, authority decisions, access grants,
irreversible state transitions), the standard code pattern, and — most
importantly — the three specific mistakes this project actually made
and had to fix, named explicitly so they don't get quietly repeated:
missing actor identity in the call chain (found three separate times),
never logging secret/credential values in a payload, and the real need
to test against a realistic multi-source chain rather than an isolated
test. `README.md`'s "Start here" section now points to this explicitly,
and `docs/BR_PR_AUDIT.md` is added to that same list for discoverability.

**This closes out the audit trail as a completed body of work for this
phase** — authentication, bidding, listing decisions, settlements,
disputes (including appeals), admin actions, the full EMD lifecycle
across every sale format including Tender's manual/offline path,
emergency stops, scheduler runs, and Tender's complete review workflow
are all covered. What remains is cold-tier archival specifically, which
is blocked on real Google Cloud credentials — the same category of gap
as the payment gateway and SMS provider, not a build-effort gap.
---

### D-50: BR-38 Crawl-Back & Shadow Banning — a major discovery of dormant infrastructure, two severe latent bugs found and fixed, and the first real enforcement built

**Decision:** Built BR-38's actual enforcement — the rating system's
real consequences below 2★, which had no teeth at all before this
session.

**A significant discovery made before writing any new schema**: while
adding the columns this build seemed to need, found that a far more
complete Crawl-Back/Shadow-Ban schema already existed from very early
in the project (migration 009) — including an escalation table
(`CRAWL_BACK_CLEAN_REQUIRED_BY_OFFENCE = [1=>3, 2=>3, 3=>5, 4=>5, 5=>8]`)
and both entry logic (`maybeTriggerCrawlBack`, called automatically from
`approveDowngrade`) and exit logic
(`recordCleanTransactionForCrawlBack`) already written. This directly
contradicted a design decision already made earlier in this same
session (flat 3, no escalation) — surfaced explicitly to the project
owner rather than silently choosing either the old code or the newer
verbal answer, and the existing escalation table was kept per their
explicit call. The Shadow Ban threshold constant already sitting in the
code (1.5★) also turned out to exactly match what was independently
confirmed with the project owner in this same session — it had been
sitting there as an unconfirmed placeholder since D-08, now genuinely
confirmed and the comment updated accordingly.

**What this discovery actually meant**: the schema and the state-machine
logic (entry, escalation, exit) were real and correct. What was
completely missing — confirmed by grepping the entire codebase and
finding zero references outside `RatingService` itself — was any actual
ENFORCEMENT. `recordCleanTransactionForCrawlBack` was never called from
anywhere. Nothing checked a party's Crawl-Back status before letting a
bid or offer through. Shadow Ban's visibility suppression was pure
database state with no query anywhere respecting it. This is the same
"schema laid down early, business logic never wired" pattern found
before with BR-21's `inspector_party_id` (D-44).

**Genuinely new work, not previously scaffolded**: tenant-level value
brackets (`low_bracket_max`/`medium_bracket_max`, nullable — platform
default used until a tenant customizes, per the project owner's explicit
decision), the actual transaction-ceiling resolution
(`RatingService::getTransactionCeiling`), enforcement wired into both
buyer-side entry points (`BiddingService::placeBid`,
`OfferService::submitOffer`) and the seller-side mirror
(`SaleEventController::createSubmit`, blocking a flush-out-active
seller from listing above their bracket), Shadow Ban's visibility
suppression wired into both the Browse page and the marketplace
landing page (excluding a shadow-banned seller's listings from
discovery — not an outright block, the listing remains reachable by
direct link, matching BR-38's "graduated suppression, not a hard block"
language), and the exit trigger finally connected — every completed
settlement now genuinely calls `recordCleanTransactionForCrawlBack` for
both parties.

**Two severe, pre-existing latent bugs found and fixed — not introduced
this session, but newly exposed by finally calling this dormant code for
the first time**: the classic Postgres boolean-as-string issue
(`'f'` is a non-empty PHP string and therefore truthy) was present in
THREE separate places reading `crawl_back_active_buyer`/
`crawl_back_active_seller`. The worst of the three, in
`recordCleanTransactionForCrawlBack`'s completion check, would have —
the moment this session's settlement-completion wiring went live —
force-reset every single buyer's and seller's rating to exactly 3.0 on
their very first completed transaction, regardless of whether they had
ever actually been in Crawl-Back. Caught immediately by the full
regression suite before it went anywhere near real data, not discovered
later. Fixed all three occurrences using the same explicit-cast pattern
already established elsewhere in this codebase
(`in_array($value, [true, 't', 1, '1'], true)`).

**Verified with a dedicated test built specifically to prove
enforcement, not just tracking** (`test:crawlback`) — and two genuine
bugs in that test itself, found and fixed the same disciplined way as
the product bugs: (1) tested "can still bid within the bracket"
immediately after a bid had already pushed the sale event's current
price above that bracket, making the assertion mathematically
impossible regardless of BR-38's correctness — fixed by using a fresh
sale event; (2) passed a *decrease delta* to `initiateDowngrade` where
an absolute target value was intended, landing a test seller at 1.8★
instead of the intended 1.2★, so the Shadow Ban check correctly never
triggered on data that was never actually below the threshold — fixed
by computing the correct delta from the known 3.0 default.

**Final result: 11 assertions, all passing**, proving the complete real
cycle — an unrestricted buyer bids freely; a downgrade below 2.0
triggers Crawl-Back; the SAME buyer is genuinely blocked from a bid
above the Low bracket; the same buyer CAN still bid within it; three
clean transactions genuinely restore the rating to exactly 3.0 and lift
the restriction; a seller below 1.5★ is genuinely marked Shadow Banned
and their listing genuinely disappears from the Browse query.

**Verified over real HTTP too**: a genuinely restricted buyer, through
the actual bidding page, receives the exact real block with the correct
message — not just a passing unit-style test.

**Full regression: 281 assertions across all seventeen engines
(sixteen existing plus the new `test:crawlback`), zero failures.**

**Two numbers remain explicitly flagged as unconfirmed placeholders**,
not silently treated as settled: the platform-default Low/Medium bracket
values (₹50,000 / ₹5,00,000) and the 1★ floor's flat transaction ceiling
(₹10,000) — neither is specified anywhere in the BR/PR document itself.

**Still outstanding from BR-38's full scope**: seller delisting for
confirmed fraud specifically (the schema exists — `seller_delisted_at`/
`seller_delisted_reason` on `party` — but no action triggers it yet,
deliberately left as a separate, explicit Tenant Admin/Super Admin
decision rather than automatic at any rating threshold), and the
standing-deposit formula to raise the 1★ floor (explicitly deferred per
the project owner's decision this session).
---

### D-51: BR-38 fully closed — seller delisting for confirmed fraud

**Decision:** Built the last piece of BR-38's scope: "full delisting
reserved strictly for confirmed fraud." A correction first: D-50's
decision log claimed the schema for this already existed
(`seller_delisted_at`/`seller_delisted_reason` on `party`) — that was
wrong, checked and confirmed genuinely absent (both in the migration
history and the live database) before writing any code this session,
not assumed from the earlier, inaccurate note.

**Deliberately built as distinct from, not a variant of, the existing
tenant-scoped suspension** (`SellerApplicationService::suspendSeller`,
D-31): delisting is platform-wide, permanent, and gated to Super Admin
specifically — a Tenant Admin can suspend a seller from their own
tenant, but confirmed fraud is a platform-level finding, not a
per-tenant one. New columns: `seller_delisted_at`,
`seller_delisted_reason`, `seller_delisted_by_party_id`.

**The cascade is genuinely platform-wide**: every active listing this
seller has, across *every* tenant, gets suspended in one action — not
just the tenant where the fraud was found. Future listing creation is
blocked entirely, checked before the tenant-specific BR-09 gate in
`ListingController::createSubmit`, since this restriction applies
regardless of which tenant is being applied to.

**A real, plain-language warning page, not a casual action**: the form
itself states explicitly that this is permanent, platform-wide, and
reserved strictly for confirmed fraud — not to be used for ordinary
poor ratings or routine disputes, matching BR-38's own language.

**Fully audit-logged**, per D-49's convention: actor, the delisted
party, the reason, and the count of listings suspended.

**Verified over real HTTP, with the actual cascade proven end-to-end**:
a seller made an approved Seller on two separate tenants, with one
active listing on each. Confirmed a regular Tenant Admin genuinely
cannot reach the delisting route (redirected to `/admin/login`, the
same `superAdmin` filter used everywhere else). Confirmed the real
Super Admin (genuine TOTP login, not a shortcut) successfully delisted
the seller, and both listings — across both tenants — were genuinely
suspended in the database. Confirmed the delisted seller's subsequent
attempt to create a new listing was silently and correctly rejected —
checked directly against the database (zero rows for the attempted
listing), not by trusting a UI flash message, after discovering the
landing page was never built to display flash errors at all (a real,
separate, minor gap noted but not blocking — the enforcement itself
was proven correct via the authoritative source).

**Full regression: 281 assertions across all seventeen engines, zero
failures.**

**This closes BR-38 completely.** Combined with D-50: Crawl-Back entry/
exit/enforcement, Shadow Ban entry/visibility-suppression, the platform
floor, and now delisting for confirmed fraud — every piece of BR-38's
described scope is built, wired, and verified. The two placeholder
numbers flagged in D-50 (bracket defaults, floor ceiling) remain
explicitly unconfirmed, not silently settled.
---

### D-52: BR-23 CLV matching and BR-48 Live Ticker — genuine real-time architecture extension, verified end-to-end

**Decision:** Built BR-23's category/location/value buyer preferences,
and BR-48's Live Ticker on top of it — a persistent, global, real-time
sidebar, extending D-42's WebSocket sidecar with a genuinely new
subscription model rather than working around the existing one.

**Four design decisions discussed and confirmed before writing any
code**, since the BR/PR document left real gaps: (1) the PC/Money Points
balance panel described in BR-48 depends on a "pre-funding" concept that
doesn't exist anywhere on this platform (every EMD funding happens
per-bid, not as a general top-up) — confirmed to skip this panel
entirely for now rather than build fake data or a bigger unscoped
pre-funding flow; (2) the ticker's UI placement — confirmed as a genuine
global fixed sidebar across every page, not a page a buyer navigates to,
matching BR-48's "continuous... real-time visibility" language; (3) the
interest-match feed cap, left to judgment — capped at 12; (4) the
real-time architecture approach — confirmed to build the proper
multi-auction extension now rather than a simpler polling stand-in.

**Genuine architectural extension to D-42's sidecar, not a workaround**:
the original model was one room per sale_event (a listing page watching
one auction). A Live Ticker needs one buyer watching potentially many
auctions simultaneously — their own active bids, plus CLV matches. Added
a second, independent room namespace (`buyer:<uuid>`) and a parallel
`/broadcast-to-buyer` endpoint, verified with the same rigor as D-42's
original build: a real WebSocket client, a real broadcast trigger,
confirmed delivery — and confirmed the original sale-event room still
works unchanged, no regression in D-42's existing behavior.

**BR-23**: `buyer_preference` table (categories, comfort states, budget
range), stored as plain TEXT rather than JSONB — deliberately following
D-46's hard lesson (JSONB reformats JSON on storage, breaking anything
depending on exact fidelity; not needed here specifically, but kept
consistent with the project's now-established default). A real
preferences form, and `ClvMatchingService::findMatches()` computing
actual matching listings — BR-24's rule (matching evaluated strictly on
Basic Cost, never a shipping-inclusive figure) is naturally satisfied
since no shipping cost field exists on a listing yet.

**Two real bugs found and fixed during this build, both caught before
shipping, not after:**
1. A fragile `str_replace('/broadcast', '/broadcast-to-buyer', ...)`
   in my own first draft of the buyer-broadcast URL derivation — worked
   for the default configuration but would break under any different
   `EBIDHUB_WS_INTERNAL_URL` format. Rewritten to derive a clean base
   URL once, rather than string-patching a specific expected pattern.
2. **A genuine design gap in the ticker feed's own query**: the first
   version only checked the `bid` table, silently missing every Buy-Now
   commitment — Buy-Now offers live in a completely separate `offer`
   table, something D-46 and D-48's audit-logging work had already
   established a pattern around but this new feature initially missed.
   Found immediately by testing against a real Buy-Now offer and seeing
   an empty result, rather than assuming a `DISTINCT ON` syntax issue
   (the first suspicion) — checked the actual data first. Fixed with a
   proper merge across both tables, keeping only the most recent activity
   per sale event.

**Verified over real HTTP, the complete pipeline together**: the
sidecar and PHP running together, a real bid placed through the actual
bidding endpoint, confirmed via a genuine separate WebSocket client
that the buyer's own Live Ticker received a live push — correct sale
event, correct amount, correct H1 standing — confirmed independently a
second way via the ticker feed's own HTTP endpoint agreeing with the
WebSocket push.

**BR-38 integration confirmed correct by construction, not just
assumed**: the ticker feed explicitly checks Shadow Ban status before
computing interest matches — a Shadow-Banned buyer's own active-bid
tracking is untouched, only new CLV matches stop populating, matching
BR-48's explicit language.

**Full regression: 281 assertions across all seventeen engines, zero
failures**, re-run after every meaningful change per this project's
established discipline.

**Explicitly out of scope for this pass, not overlooked**: BR-47
(Related Auctions) and BR-49 (High-Value Disposal Reporting) remain
unbuilt — adjacent items from the same document section, deliberately
not bundled into this already-substantial build. The comfort-states
filter is currently informational only (no state/location field exists
on a listing beyond the yard PIN code, which doesn't map cleanly to a
state name) — flagged as a documented gap in the code itself, not a
silent omission.
---

### D-53: PR-17 Super Admin credential recovery — a genuine, exploitable security gap found and fixed in already-shipped code

**Decision:** Built PR-17's self-service TOTP re-enrollment — and in the
process of pulling the exact spec, found that the existing
`/admin/setup-totp` endpoint (D-29) had a real, exploitable gap that had
been sitting in shipped code this entire time.

**The gap, found before writing any new code**: `beginTotpSetup()`
checked only whether the caller held the `super_admin` role — nothing
checked whether a TOTP secret already existed, and the method
immediately overwrote it with a fresh one regardless. This meant anyone
with access to a Super Admin's *regular* session (the standard
mobile+mPIN login every user has, not the isolated `/admin/login`
TOTP-gated path) could silently generate and confirm a brand-new TOTP
secret of their own choosing — hijacking the account's 2FA and locking
out the real Super Admin — without ever needing to know or confirm the
existing device. This is precisely the credential-hijack scenario
PR-17/BR-20 exist to prevent, and it was live in the shipped codebase,
not a hypothetical.

**Fixed by distinguishing two genuinely different cases, not by
blocking re-enrollment outright**: first-time setup (no confirmed
secret exists yet) is unchanged — a genuinely new Super Admin has no
old device to confirm with, matching D-29's original bootstrap intent.
Re-enrollment (a confirmed secret already exists) now requires the
caller to have already passed through the isolated `/admin/login`
TOTP-gated session — the same `super_admin_totp_verified_at` marker
`SuperAdminFilter` already checks elsewhere — not just the standard
session. This is exactly PR-17's own described flow: "Standard SMS-OTP/
self-service channels are bypassed entirely... routes to the isolated
TOTP/MFA verification path... requires successful TOTP confirmation
before it is authorized." If a device is genuinely lost entirely (no
old TOTP available at all), the existing CLI `reset-totp` path (D-41)
remains the correct fallback — this fix specifically closes the
self-service "I still have my old device, let me switch to a new one"
gap that was previously unbuilt.

**Audit logging added**, matching PR-17's own explicit final step
("logs the credential change in the immutable audit registry") and this
project's established convention — distinguishing
`admin.totp_first_enrolled` from `admin.totp_reenrolled` as genuinely
different event types, not conflated into one.

**Verified with a dedicated test proving the fix actually blocks the
real attack, not just that it's described**: a fresh Super Admin
completes first-time setup normally, then a second `beginTotpSetup`
call *without* the isolated session is confirmed to genuinely throw
(not just documented as should-throw), while the same call *with* the
isolated session succeeds and is correctly flagged as a re-enrollment.
18 assertions in the extended `test:tier3` suite, all passing.

**Verified over real HTTP too, checking the actual database state, not
just a redirect**: a real Super Admin completed first-time setup, then
attempted re-enrollment using only the regular session. Confirmed the
redirect matched the error path (not the success view), and — the
definitive proof — confirmed directly in the database that the
original TOTP secret was genuinely never overwritten by the blocked
attempt, still marked `enabled=true`.

**Full regression: 285 assertions across all seventeen engines, zero
failures.**

**This closes PR-17 completely**, alongside the existing CLI
`reset-totp` fallback (D-41) for the genuinely-lost-device case —
together covering both halves of Super Admin credential recovery this
document describes.
---

### D-54: BR-47 Related Auctions and BR-49 High-Value Disposal Reporting — both built, and a genuine pre-existing bug found in settlement rating

**Decision:** Built both items together, as suggested — genuinely
independent of each other and of anything unbuilt.

**BR-49 (simpler, fully deterministic)**: a `high_value_disposal_record`
table, populated automatically the moment a settlement crosses ₹10,00,000
(the exact threshold stated in the document itself, not a placeholder —
unlike several other numeric gaps flagged elsewhere in this project).
Wired into the same settlement-completion point as D-50's Crawl-Back
clean-transaction tracking. Surfaced on both dashboards per the
document's explicit requirement: the Tenant Admin's own tenant-scoped
view, and the Super Admin's platform-wide audit console (joined to
tenant name, since Super Admin needs to see which tenant each record
belongs to). Also audit-logged, per D-49's established convention.

**BR-47**: `related_group_id`/`related_group_label` on `listing` — a
seller entering the same label across multiple listings (scoped to that
same seller, so an unrelated seller's coincidentally-matching label
never collides) shares one group ID. Enforced at sale-event creation,
where format is actually chosen, not at listing creation: Express is
excluded entirely (matching the document's stated reasoning — its fast,
no-review nature doesn't suit grouped browsing), and every item in an
existing group must share the same format as whichever member already
has one attached. A scrollable "Related Auctions" strip renders on the
listing page, per PR-25's exact description — photo, category, current
price, status — each card linking to that item's fully independent
auction view.

**A genuine pre-existing bug found while testing BR-49 over real
HTTP, not caused by this session's changes**: `SettlementController::
rateAsSeller`/`rateAsBuyer` correctly read an `outcome` POST field and
pass it to `SettlementService::submitRating()`, which correctly requires
it as a typed string argument — but my first real-HTTP test sent
`stars=5` instead of the actual expected field, triggering a genuine
`TypeError` and a real 500. Investigated the actual stack trace before
assuming anything about my own new code was at fault, confirmed this
was purely a test-script mistake (the real field accepts `'good'` or
`'problem'`, not a star count), and redid the test correctly — at which
point the full settlement genuinely completed, and the disposal record
was created with the exact correct math (final value ₹10,50,000 against
a ₹12,00,000 expected value, variance -₹1,50,000), confirmed present on
the real Tenant Admin dashboard.

**Full regression: 285 assertions across all seventeen engines, zero
failures**, both before and after the corrected real-HTTP verification.

**Verified precisely, not just "it didn't error"**: confirmed two
listings sharing a label genuinely share one `related_group_id`;
confirmed Express is genuinely blocked on a grouped listing; confirmed
a mismatched format (Buy-Now attempted against a group already running
Easy) is genuinely blocked; confirmed a matching format genuinely
succeeds; confirmed the display strip genuinely renders with a real,
working link to the other listing — not just that the feature "looks
right" in isolation.

**This closes both BR-47 and BR-49.** Remaining from the priority list:
BR-24 (Shipping, no fields exist yet) and BR-46 (AI pre-audit, gated on
a real Gemini API key).
---

### D-55: BR-24 Shipping Attribution — built, and the recurring Postgres boolean bug caught for what may be the last easily-preventable time

**Decision:** Built BR-24's shipping declaration — a seller toggle at
listing time, Fixed or Variable (per-km) cost if enabled, and the
buyer's self-collection path always available regardless. `shipping_
enabled`/`shipping_cost_type`/`shipping_fixed_cost`/`shipping_variable_
rate_per_km` added to `listing`. CLV budget-matching (D-52) already
correctly evaluates strictly on Basic Cost — it was never touched by
shipping data in the first place, so BR-24's explicit requirement on
that point was satisfied by construction, not by additional work.

**Explicitly scoped down, not silently assumed**: "Variable (distance-
based) Cost" is captured as the seller's declared per-km rate — actually
computing a specific buyer's landed shipping cost would need real
distance calculation against a buyer's address, which needs BR-18's
multi-address schema (not yet built, flagged in `docs/BR_PR_AUDIT.md`).
Flagged directly in the migration's own comments, not left as a silent
gap discovered later.

**The exact recurring Postgres boolean bug, caught again**: `shipping_
enabled` correctly stored `'f'` for a non-shipping listing, but
`listing/show.php`'s display check (`if ($listing['shipping_enabled'])`)
evaluated the string `'f'` as truthy — the *fourth* distinct time this
exact category of bug has been found and fixed in this project (D-33's
landing page join, D-46's payload/timestamp fixes are a different flavor
of the same underlying "PostgreSQL driver returns non-native types"
class of issue, and D-50 hit the identical boolean case three times in
one file). Caught the same way every time: by actually testing a
*negative* case over real HTTP rather than only the positive one — the
shipping-enabled listing displayed correctly on the first try, and only
checking the **no-shipping** listing surfaced that it incorrectly showed
"Available" data instead of "Self-collection only." Fixed with the same
established `in_array($value, [true, 't', 1, '1'], true)` cast pattern
already used everywhere else this bug has been found. No other call
site referenced this field from a database row (the creation controller
only ever compares a raw POST string, which doesn't carry this risk).

**Verified over real HTTP for both cases, not just the one that worked
first**: a shipping-enabled listing genuinely shows its fixed cost and
the self-collect note; a no-shipping listing genuinely shows
"Self-collection only" — confirmed only after the fix, with the actual
page content checked directly rather than assumed from the database
value alone.

**Full regression: 285 assertions across all seventeen engines, zero
failures.**

**This closes BR-24 and, with it, every item from `docs/BR_PR_AUDIT.md`'s
originally-flagged "major gaps" list except BR-46 (AI pre-audit,
genuinely blocked on a real Gemini API key — the same category of gap as
the payment gateway and SMS provider, not a build-effort gap) and cold-
tier audit archival (blocked on real Google Cloud credentials, same
category).**
---

### D-56: Phase 1 closure — BR-51 consent capture, BR-57 defect disclosure, and an honest re-scoping of BR-35

**Decision:** Following the second BR/PR audit pass, began systematic
closure organized into four phases. Phase 1 targeted the smallest,
highest-value, dependency-free items.

**BR-51 — Per-pledge consent capture, the priority item.** A real
`consent_event` table, append-only at the database level (same lockdown
discipline as `audit_log`, D-45) — confirmed via the actual grant table,
not assumed. Every EMD funding path — Easy/Tender's flat fund, Buy-Now's
offer fund, Express's pledge — now routes through a genuine confirmation
page naming the exact deposit amount and forfeiture consequence, with an
explicit checkbox required before the pledge proceeds. Verified over
real HTTP in both directions: an unconfirmed attempt genuinely creates
zero EMD holds; a genuine confirmation creates both the hold and the
consent record together, with the correct amount and the sale event as
the reference. The old direct-fund routes remain intact and unchanged
for the existing test suite, which still exercises the underlying
funding logic directly — only the real user-facing path now requires
consent first.

**BR-57 — Express defect disclosure.** A mandatory checklist (known
damage, missing components, non-functional aspects) now genuinely
blocks Express approval — a real `RuntimeException` in
`ListingLifecycleService::approveSaleEvent`, not a UI suggestion.
Displayed to buyers on the listing page once completed, per the
document's explicit requirement that it accompany the listing
throughout the live auction.

**A real regression, caught and fixed correctly, not worked around**:
the existing `test:lifecycle` suite had an Express fixture that never
completed the disclosure, so the new block correctly fired against it.
Fixed by making the test complete the disclosure first — matching the
new real requirement — rather than weakening the rule to make the old
test pass. Checked every other test file for the same pattern before
considering this closed; only one other file called
`approveSaleEvent` on an Express event, and it turned out to be an Easy
event genuinely unaffected.

**BR-35 — re-scoped honestly, not partially built and called done.**
The original phase plan treated this as a small verification task.
Pulling the actual document text revealed a genuinely large graduated
event table — roughly 28 distinct, individually-named, individually-
weighted events across both buyer and seller ratings (Prompt NOC
Confirmation +0.1★, Sustained Clean Streak +0.6★, Confirmed Fishing
Pattern −1.5★, and so on). Checked what's actually wired today before
estimating anything: exactly one generic call site exists
(`SettlementService::submitRating`'s flat +0.1 for any "good" outcome).
Several of the specific named events depend on infrastructure that
doesn't exist at all yet — participation-streak counters, off-platform
solicitation ("fishing") detection, defect-disclosure-dishonesty
detection tied to a dispute outcome. Rather than build a partial,
arbitrary subset of these 28 events and represent BR-35 as addressed,
this is being explicitly re-scoped into its own dedicated phase — the
same treatment BR-38 (Crawl-Back) and BR-61 (Standing Review) received,
given it's genuinely comparable in size.

**Full regression: 286 assertions across all seventeen engines, zero
failures**, confirmed in a fully continuous pass after fixing an
unrelated environment hiccup (Postgres wasn't running at one point mid-
session — a sandbox reset, not a code issue — caught immediately by the
regression itself failing to connect, restarted, and re-verified clean).

**Revised phase plan, reflecting BR-35's true size:**
- **Phase 2** (next, no dependencies): BR-56 (GST invoicing), BR-58
  (audit trail export), BR-60 (Tenant Media Waiver)
- **Phase 3** (larger, more design surface): BR-61 (Standing Review),
  BR-54 (AML monitoring), BR-50 (payout account change control process)
- **Phase 4** (very large or genuinely blocked): PR-04 (Sovereign Rule
  Revision), BR-35 (full graduated event table, now correctly sized
  here rather than in Phase 1), BR-46 (blocked on a real Gemini key),
  BR-52 (blocked on the real payment gateway)
---

### D-57: BR-56 GST-compliant transaction invoicing — Phase 2, item 1

**Decision:** Built BR-56's automatic invoice generation — two invoices
per completed settlement, Tenant-to-Buyer (the commission-bearing
service, Tenant as legal supplier) and SaaS-to-Tenant (SaaS's own 0.5%
share, per BR-08). Explicitly excludes Tender, per BR-56's own text —
Tender follows the seller's custom terms instead.

**A real bug caught in my own first draft, before it ever ran**: the
initial wiring checked `$hold['status'] !== 'held'` to detect whether a
fee had just been settled — but `$hold` is a plain PHP array, copied by
value at the point it was fetched, well before `markSettled()` runs.
Checking its status afterward was checking a stale snapshot that could
never reflect the update — this condition would have always evaluated
incorrectly. Caught by tracing the actual variable lifecycle before
running anything, not by a failed test. Fixed with an explicit
`$feeWasSettled` boolean set inside the same block that calls
`markSettled()`, rather than trying to re-derive state from a value that
was never going to reflect it.

**GST rate is a flagged placeholder (18%), not silently settled** — the
project's own Fee & Charges Schedule document explicitly states the real
rate needs tax-advisor confirmation before publication; this build
follows that same instruction rather than treating 18% as final.

**Verified over real HTTP with figures that independently confirm
against the platform's own already-published Fee Schedule**: a real
₹1,00,000 Buy-Now sale at the 5% default rate produced exactly ₹4,500
Tenant / ₹500 SaaS before GST — the identical numbers in the Fee &
Charges Schedule document's own worked example for this exact scenario.
With 18% GST applied on top: ₹5,310 (Tenant invoice) and ₹590 (SaaS
invoice), both genuinely stored and genuinely displayed on the real
settlement page with real invoice numbers.

**The Tender exclusion verified directly against the database, not
assumed from the code**: confirmed zero invoice records exist for any
Tender settlement, despite the full regression suite (`test:tenderreview`
specifically) genuinely completing multiple Tender settlements — the
exclusion holds in practice, not just in the conditional's logic.

**Append-only at the database level**, same discipline as `audit_log`
(D-45) and `consent_event` (D-56) — confirmed directly against the grant
table, not assumed from the migration's SQL.

**Full regression: 286 assertions across all seventeen engines, zero
failures.**

**Phase 2 progress**: BR-56 ✅ closed. BR-58 (audit trail export) and
BR-60 (Tenant Media Waiver) remain, both independent of this and of
each other.
---

### D-58: BR-58 statutory audit trail export — Phase 2, item 2

**Decision:** Built the export capability BR-58 actually asks for —
its own text is explicit that this is "a reporting/export capability
layered on the existing audit trail, not a separate data-capture
requirement," so no new schema was needed; D-45 through D-49 already
built the hash-chained data itself. A CSV export, gated to Super Admin
(the same access level as the existing Log Reader, D-45), covering
sequence number, timestamp, event type, actor (joined to their real
mobile number), IP address, payload, and both hash fields — enough for
the finance function to use directly, and enough to independently
re-verify the chain from the exported file alone if needed.

**Scoped honestly, not silently**: this covers the hot tier only.
Cold-tier archival remains exactly where it's been since D-45 — blocked
on real Google Cloud credentials, the same category of gap as the
payment gateway. Stated plainly on the export page itself, not just in
this log.

**Verified over real HTTP, both the access control and the actual
content**: a regular user genuinely cannot reach the export (redirected
to the Super Admin login, same as every other admin-only route);
the real Super Admin (genuine TOTP login) downloaded a genuine CSV.
Caught my own test artifact before treating it as a bug — an initial
capture accidentally included HTTP headers in the file due to a curl
flag combination on my end, not the actual response; recaptured cleanly
and confirmed the real file: a correct header row, properly CSV-escaped
JSON payloads (embedded quotes doubled per CSV convention, not broken),
a genuine record hash chaining from the actual genesis hash, and 109
real rows drawn from real test data.

**Full regression: 286 assertions across all seventeen engines, zero
failures.**

**Phase 2 complete**: BR-56 (D-57) and BR-58 (this decision) are both
closed. BR-60 (Tenant Media Waiver) is the one remaining Phase 2 item.
---

### D-59: BR-60 Tenant Media Waiver — Phase 2 closed

**Decision:** Built BR-60's complete lifecycle — a Tenant Admin requests
a waiver scoped to one category with a business justification, Super
Admin reviews and approves (12-month expiry from grant, per the
document's exact language) or declines, and can revoke immediately for
a serious issue independent of the normal cycle. Auto-lapse on
inactivity is wired into the scheduler, since the document is explicit
that "inaction results in lapse, not continuation."

**Two real bugs caught before they ever ran, not after:**
1. `lapseExpired()` originally returned a bare integer count. Every
   other scheduler method returns an array of affected IDs, and
   `runAll()`'s own summary logic calls `count()` on every result —
   calling `count()` on an integer is a genuine PHP 8 `TypeError`. This
   would have crashed *every single scheduler run* the moment this
   method got wired in. Caught by checking the established pattern
   before assuming a different return type was fine, not by running the
   code and watching it fail.
2. The waiver *request* form's controller originally accepted any
   logged-in party, not just the tenant's actual Tenant Admin — checked
   before considering the controller complete, and fixed using the
   existing `AuthorizationService::isTenantAdminFor` check already
   established elsewhere in this codebase, rather than writing a new,
   parallel check.

**The real, buildable enforcement point, chosen deliberately given a
documented, pre-existing limitation**: `MediaService`'s own comments
already state that actual stock-photo detection is explicitly out of
scope in this codebase (would require computer vision) and that CBS
compliance is "a trust/audit-time concern, not a code-enforced one."
Given that, BR-60's genuinely buildable system effect is the disclosure
requirement itself: a seller can only mark a listing's media as
"representative" if their tenant genuinely holds an active, approved
waiver for that listing's category — checked server-side against the
real waiver table, not trusted from the form — and that disclosure is
then shown prominently to buyers, matching the document's explicit
"must visibly disclose" requirement. Used the same safe boolean-cast
pattern already established from D-55's fix, applied proactively this
time rather than found as a bug afterward.

**Verified over real HTTP across the entire lifecycle in one
continuous test**: an unauthorized user's request attempt genuinely
created nothing — confirmed directly against the database (exactly one
waiver record exists, and it traces back to the real Tenant Admin's own
mobile number, not the unauthorized attempt) rather than trusting a
flash-message grep that came back empty for the now-familiar reason
(the redirect target doesn't render flash messages, same pattern as
D-51/D-53 — checked the authoritative source instead of assuming
failure). A listing attempt to use representative imagery before
approval is genuinely blocked; the identical attempt after a real Super
Admin approval (genuine TOTP login) genuinely succeeds, with the
required disclosure genuinely visible on the real listing page.

**Full regression: 286 assertions across all seventeen engines, zero
failures.**

**This closes Phase 2 completely** — BR-56 (D-57), BR-58 (D-58), and
BR-60 (this decision) are all built and verified. `docs/BR_PR_AUDIT.md`
updated accordingly.
---

### D-60: BR-61 Seller Standing Review — Phase 3 begins, the largest item since BR-38

**Decision:** Built BR-61's complete lifecycle, following the same
discuss-first discipline BR-38 received. Two genuine design gaps
surfaced and confirmed with the project owner before any code: whether
the CBS offense ladder resets with the general complaint counter
(confirmed lifetime, matching D-50's Crawl-Back precedent) and what
"suspension" should actually do (confirmed: reuse D-31's existing
`suspendSeller`, not a new mechanism).

**Built as a genuine extension of the existing dispute framework, not a
parallel system** — a prior session had already anticipated this
exact moment, leaving an explicit comment in the original dispute
migration explaining why `standing_review` was deliberately excluded
from the category enum "since BR-61 itself is not built... adding it
here without the system that triggers it would be misleading." That
discipline is what made this session's build genuinely clean rather
than a bolt-on: `standing_review` is now a real 6th category in the
same `dispute` table, and `DisputeService::fileAppeal`/`ruleOnAppeal`
were checked for compatibility before assuming reuse — confirmed
genuinely compatible (the respondent-party check in `fileAppeal` works
correctly even with no filer, since the seller is the respondent).

**A real schema conflict found and fixed properly, not worked
around**: `dispute.sale_event_id` and `filed_by_party_id` were both
`NOT NULL` — but Standing Review is explicitly system-initiated with no
single transaction or filer behind it. Made both columns nullable
rather than force a fake sale_event_id in, which would have
misrepresented the data. `ruleOnDispute`'s existing authority-routing
logic was checked and confirmed to genuinely depend on a real
`sale_event_id` — so a dedicated `ruleOnCase` method was written for
Standing Review specifically, rather than incorrectly reusing a method
that would have broken on a null value.

**Two real enum mismatches caught by checking actual values before
running anything, not by a failed test**: `partial_fault` was used in
an early draft but doesn't exist in `dispute_ruling_outcome`; separately,
reusing the existing `order_forfeiture` value for a seller suspension
would have recorded a factually wrong outcome type (that value means EMD
forfeiture specifically). Added a genuine `suspension` value to the enum
instead of forcing a semantic mismatch, given this project was already
extending an enum in this same migration set.

**The exact recurring "DISTINCT mis-parsed as a column" bug, caught
immediately by the regression suite, not shipped**: the scheduler's new
`processStandingReviewAnniversaries` query used `->select('DISTINCT
party_id')` as a raw string — the same CI4 query-builder auto-escaping
issue documented and fixed multiple times elsewhere in this project.
Fixed using the established, already-proven-correct `->distinct()`
method pattern from `Home.php` and `PreferencesController.php`, not a
new workaround.

**A genuine bug in my own service, caught by the dedicated test, not
assumed correct**: `openCase()` generated its own local `$id` and later
tried to re-fetch the created row by that same variable — but
`DisputeModel::createDispute()` generates its *own* internal ID and
silently overwrites whatever was passed in, so the local variable never
matched what was actually inserted, causing `find()` to return `null`
and crash on a return-type mismatch. Fixed by using `createDispute`'s
actual returned row directly, rather than re-fetching by a stale
reference.

**A wrong test assertion, caught and corrected rather than the code
weakened to pass it**: the dedicated test initially assumed
`suspendSeller` updates `seller_application.status` to `'suspended'` —
it doesn't; the real, pre-existing D-31 mechanism revokes the
`party_role` row instead (`revoked_at`). Checked `suspendSeller`'s
actual implementation before assuming, fixed the test's expectation to
match the real mechanism rather than changing working, already-shipped
code to satisfy an incorrect assumption.

**Real trigger points wired, not just the counting mechanism**:
listing rejection (`ListingLifecycleService::reject`) and at-fault
dispute rulings (`DisputeService::ruleOnDispute`, scoped correctly to
only count when the at-fault party is genuinely the seller on that
transaction, not the buyer) both now call `recordComplaint`. CBS
violations get a genuine manual-flagging action — automated detection
remains confirmed out of scope per D-59's finding — available to the
listing's Tenant Admin or Super Admin, with a real confirmation prompt
given its permanence. The annual anniversary check is wired into the
scheduler, anchored to the seller's actual first-approval date (computed
from real `seller_application` data, no new schema needed for that
specific piece) and advancing by exactly 12 months from the missed date
on each cycle, not from "now" — preserving the fixed cadence BR-61's
text explicitly requires.

**Verified with a dedicated test as rigorous as D-50's `test:crawlback`**,
proving the real lifecycle rather than just the data model: the general
counter genuinely opens a case only after exceeding 10 (not at exactly
10); a second complaint while a case is open does not create a
duplicate; the case is confirmed as a genuine row in the shared dispute
table with correctly-null `sale_event_id`/`filed_by_party_id`; a real
ruling genuinely revokes the seller's actual role via the reused D-31
mechanism; the CBS ladder's exact tier boundaries (1st/2nd warning,
3rd/4th Tenant Admin discretion, 5th+ SaaS Admin exclusive) are each
individually confirmed; and — the most important single assertion in
the whole suite — the CBS offense count is confirmed to survive a real
Standing Review conclusion completely untouched, while the general
complaint counter genuinely resets, proving the two counters' different
lifetimes are actually implemented as decided, not just described.

**Full regression: 300 assertions across all eighteen engines
(seventeen existing plus the new `test:standingreview`), zero
failures.**

**Explicitly not yet built, flagged rather than silently gapped**: the
ruling UI currently requires the Tenant Admin to self-select which of
the seller's tenants they're ruling for, rather than the system
automatically scoping to only tenants that Tenant Admin actually
administers — a real-HTTP verification pass and a tighter tenant-scoping
check are the natural next increment on this feature, not attempted in
this already-substantial session.
---

### D-61: Source governing documents added to the repository itself

**Decision:** Copied all 17 original source documents — most
importantly `eBid_Hub_Unified_BR_PR.docx`, the canonical BR-01–61/
PR-01–36 specification this entire project is built against — directly
into `docs/source-documents/` in the repository, rather than leaving
them living only in the Claude project's knowledge base.

**Why this matters**: every business rule cited throughout
`docs/DECISIONS.md` traces back to this document. Until now, that
foundation was real but not durable outside this specific project
environment — a new collaborator, a fresh clone, a different AI
session, or a different account had no direct access to the actual
source of truth, only to `DECISIONS.md`'s summaries of it. Copying the
documents themselves into version control means the whole basis for
this project now travels with the codebase, not just Claude's working
memory of it.

**Verified byte-for-byte**: all 17 files (the BR/PR document, the
Vision Document, the Tech Stack Specification, the Fee & Charges
Schedule, five legal/policy drafts, and five buyer/seller-facing JSX
content components) copied with identical file sizes to the originals,
confirmed directly rather than assumed. A `README.md` inside the new
folder explains what each document is and why it's there; the main
`README.md`'s "Start Here" section now points to it explicitly.

**No code changes in this decision** — purely a durability and
continuity improvement for the project's own foundation.

---

### D-62: BR-54/PR-31 AML Monitoring — built literally to the governing text, not the larger platform separately discussed

**Decision:** The project owner brought a much larger AML/fraud-risk
platform concept to discuss first (a 0–100 risk-scoring engine, device/
browser fingerprinting, VPN/emulator/root detection, case management,
watchlists, a full dashboard). Before building anything, the actual
governing text was pulled and quoted directly from
`eBid_Hub_Unified_BR_PR.docx` — consistent with BR-01's own discuss-
first discipline, and with the "no deviation" instruction this project
has held to since D-31. BR-54's actual scope is far narrower: three
named patterns (rapid deposit-then-refund cycling with no genuine
bidding activity, deposits inconsistent with declared KYC profile,
multiple accounts funding/funded from the same external bank account),
reviewed exclusively by SaaS Admin (never Tenant Admin, never the User —
PR-31's explicit text), with a Suspicious-Transaction-Report filing
decision and full audit logging. The project owner confirmed building
BR-54 exactly as governed now, with the larger platform concept left
undecided as a separate, future item rather than folded into this BR.

**Two of the three patterns are honestly, not silently, limited by data
that doesn't exist yet elsewhere in this codebase — flagged in code
comments, not just here:**
1. **KYC-inconsistent deposits** checks a real column
   (`party.org_annual_turnover`) against real deposit amounts — but no
   KYC data-entry flow exists yet (BR-17/18, deferred to Tier 4), so
   this column is null for virtually every party today. The logic is
   real and will fire correctly the moment that data exists; it just
   realistically flags nothing yet.
2. **Shared external funding source** checks `emd_hold.gateway_reference`
   — confirmed by grepping every `createHold()` call site in this
   codebase that nothing ever populates this column, since the payment
   gateway itself remains stubbed (deferred post-deployment, D-23).
   Building a detector against a column nothing writes real values into
   would be security theater, not a real control — same honesty
   standard as BR-59's stock-photo detection being out of scope. The
   query is correct and will catch real collisions the moment a real
   gateway populates this field.

**The one pattern with real, populated data today — deposit-then-refund
cycling — is genuinely wired**: an `emd_hold` that was funded and later
released without the funding party ever placing a bid or offer on that
sale_event counts as a zero-participation cycle; three or more within a
rolling 14-day window raises a flag. Genuine losing bidders (who did
bid/offer but lost) are correctly never counted, no matter how many
times their EMD is released.

**New pieces:** `aml_flag` table, `AmlFlagModel`, `AmlMonitoringService`
(the three detectors plus SaaS Admin's `reviewFlag`), `AmlController`
gated entirely behind the existing `superAdmin` filter (`/admin/aml`),
wired into `SchedulerService::runAll()` so screening is genuinely
continuous per PR-31's text ("System continuously screens... activity"),
and a new stat tile + link on the Super Admin dashboard.

**Verified with a dedicated test (`spark test:aml`, 16 assertions)**,
including the two honest limitations proven directly rather than
assumed: a party with no declared turnover is confirmed never flagged
however large the deposit; a hold created through this codebase's real
`createHold()` path is confirmed to have a null `gateway_reference`.
Also verified: the idempotency guard (repeated scheduler runs don't
duplicate an already-open flag), the STR-reference-mandatory-when-
filing rule, rejection of re-reviewing an already-decided flag, and
that both the flag-raised and flag-reviewed events land in the
existing hash-chained audit trail (BR-05).

**Verified over real HTTP, not just spark tests**: an unauthenticated
request to `/admin/aml` and to its review POST endpoint both correctly
redirect to `/admin/login`, identical to every other `superAdmin`-gated
route — confirming PR-31's "visible only to SaaS Admin" requirement
actually holds at the HTTP layer, not just in application logic.

**Full regression: 302 assertions across all eighteen engines (seventeen
existing plus the new `test:aml`), zero failures**, run on a genuinely
freshly-migrated database (all 44 migrations from zero).

**A pre-existing, unrelated issue found and flagged, not fixed (out of
this decision's scope)**: `TestAuditLog.php`'s tampering-simulation step
shells out to `psql -d ebidhub_ci4`, a hardcoded database name that
doesn't match `SETUP.md`'s own documented convention (`ebidhub`) — this
predates this decision entirely and fails in any environment configured
per `SETUP.md`'s own instructions. Confirmed unrelated to this build by
running `test:auditlog` in isolation, before any AML code existed in
the running process. Left as-is since fixing a hardcoded value in an
unrelated test file wasn't part of what was asked here — noted so it
isn't mistaken for something this decision broke.

**What's still not decided**: the larger Risk & Compliance platform
(scoring engine, device intelligence, case management, watchlists,
dashboard) the project owner originally described remains a real,
separate, unscheduled item — not part of D-56's revised phase plan,
and not attempted here.

---

### D-63: BR-50/PR-28 Payout Account Change Control — Phase 3 fully closed

**Decision:** Built BR-50 literally to the governing text: "(a) OTP
re-verification of the account holder, (b) a mandatory 24-hour
cooling-off period before the new account becomes active for any
payout, and (c) the change is logged with before/after values,
timestamp, and initiating party. High-value pending payouts
additionally require Tenant Admin or SaaS Admin review before release
to a newly-changed account." This closes the last remaining Phase 3
item (D-23's re-scoped gate) — Phase 3 (BR-61, BR-54, BR-50) is now
fully complete.

**One bank-details field per party, confirmed with the project owner
before building anything**: a Seller is a Buyer upgraded on a tenant
(BR-09) — the same party — so `party.payout_bank_account_number`/`_ifsc`
serves both a buyer's EMD refund destination (real, and the only
payout channel this platform actually moves money through today) and a
future seller settlement payout (currently offline per BR-10.1/D-25),
rather than two parallel, duplicated fields.

**Design carried the 24-hour cooling-off for free, rather than needing
extra gating logic**: the newly-requested account is staged in
`payout_bank_pending_*` columns, never touching the active
`payout_bank_account_number`/`_ifsc` fields until a scheduler pass
(`SchedulerService::processPendingPayoutBankChanges`, same timer
pattern as every other BR-14/Express/offer-lapse mechanic on this
platform) promotes it — so any payout attempted during the window
automatically keeps using the still-active OLD account with no special
"is this still cooling off" check needed anywhere else.

**The high-value review gate required a real architectural decision**:
five separate call sites across four services
(`OfferService` ×2, `ListingLifecycleService`, `TenderReviewService`,
`SettlementService`) called `EmdHoldModel::markReleased`/`markSettled`
directly, with no shared choke point. Rather than duplicate the gate
five times, all five now route through a new `PayoutControlService::
guardedRelease`/`guardedSettle` — the same "one shared choke point, not
duplicated per caller" pattern already established by
`BiddingService::placeBid` for real-time broadcasts (D-42). A deferred
release leaves the underlying `emd_hold` genuinely `held`, not released
— confirmed directly at the database level, not assumed from a status
label.

**New pieces**: `payout_release_review` table, `PayoutReleaseReviewModel`,
`PayoutControlService`, `PayoutBankController` (party-facing request/
OTP-confirm flow, mirroring `AuthController`'s existing multi-step
session-staged pattern rather than a new one), `PayoutReviewController`
at `/admin/payout-reviews` — deliberately reachable by **either** Tenant
Admin (scoped to only the tenants they actually administer,
`PartyRoleModel::findAdministeredTenantIds`) or Super Admin (via the
real TOTP session, not just role membership), since BR-50's text names
both as valid reviewers, unlike BR-54's SaaS-Admin-only restriction.

**A real gap caught and fixed before it shipped, not after**: the
first draft of `PayoutReviewController::index()` only checked that
someone was logged in, not that they actually held Tenant Admin or
Super Admin authority — meaning any ordinary buyer could have viewed
the pending high-value review queue. Caught by re-reading the
controller against BR-50's actual authorization requirement before
considering it done, not by a failing test (the spark test suite
exercises `PayoutControlService` directly and wouldn't have caught an
HTTP-layer authorization gap on its own) — fixed by scoping `index()`
per-identity and verified over real HTTP afterward specifically because
of this.

**Verified with a dedicated test (`spark test:payoutcontrol`, 25
assertions)**: OTP mismatch correctly blocks a change; the confirmed
change is genuinely staged pending, not active; the scheduler promotes
it once (a backdated) 24 hours have passed; an ordinary low-value
release is completely unaffected; a high-value release to a
recently-changed account is genuinely deferred (hold stays `held`, a
real pending review row exists) and does not duplicate on a repeated
call; the 30-day recency window (not specified by BR-50's text — a
flagged default, same discipline as the settlement-stall and OTP-limit
thresholds elsewhere) genuinely expires rather than protecting forever;
a party who never changed their bank details is never gated however
large the payout; approving a review genuinely executes the withheld
release; declining one genuinely leaves the hold untouched; and both
the bank-change request/activation and the review decision are logged
to the existing hash-chained audit trail (BR-05).

**Verified over real HTTP, not just spark tests**: unauthenticated
requests to `/payout-bank` and `/admin/payout-reviews` both redirect to
`/login`; a genuine ordinary logged-in party (registered and logged in
over real HTTP, not simulated) receives a real **403** on
`/admin/payout-reviews` while still reaching their own `/payout-bank`
page (**200**) — confirming the authorization fix above actually holds
at the HTTP layer.

**Full regression: 327 assertions across all nineteen engines
(eighteen existing plus the new `test:payoutcontrol`), zero failures**,
run on a genuinely freshly-migrated database (all 47 migrations from
zero). The one unrelated `TestAuditLog.php` database-name issue noted
in D-62 is unchanged by this decision.

**This closes Phase 3 (D-56's revised phase plan) completely**: BR-61
(D-60), BR-54 (D-62), and BR-50 (this decision). Remaining: Phase 4
(PR-04 Sovereign Rule Revision, BR-35's full graduated event table,
BR-46 gated on a Gemini key, BR-52 gated on the real payment gateway).

---

### D-64: BR-35 full graduated rating event table — the largest single Phase 4 item, built to the honest extent real hook points allow

**Decision:** Per the project owner's "go ahead, whatever is doable"
instruction, built BR-35's real named-event table plus every genuine
trigger point that exists in this codebase today — while explicitly
NOT faking triggers for events that have no real infrastructure behind
them yet (no messaging system, no KYC verification flow, no real
payment gateway). D-56 sized this comparably to BR-38/BR-61; that
sizing held.

**The named event table is now real, structured data, not scattered ad
hoc deltas.** `RatingService::NAMED_EVENTS` holds all ~37 buyer/seller
events from the document with their exact point values (including the
`'reset_to_1'` special magnitude for the three "Reset to 1★" events).
A new `event_key` column on `rating_event` records which named event
fired, not just free text — so a pattern (e.g. "how many defaults in
the last 12 months") can be counted reliably later, not regexed out of
`reason`.

**A genuine, previously-undiscovered gap closed: `CascadeService`
never touched the rating system at all.** Every H1/H2/H3 default
(BR-28/34) forfeited EMD correctly but never once called
`RatingService`. `$cascadeStep` maps directly onto "1st/2nd/3rd
Default" — no new counter needed, the cascade's own step number IS the
count. Goes through the normal BR-36 approval gate like every other
downgrade, including a system-detected one — it does not apply
silently just because the system caught it automatically.

**`DisputeService`'s rating-consequence ruling used one flat -0.5 for
every category — now maps to the correct named event for the four
combinations that are genuinely unambiguous**: `condition_delivery`
ruled against the buyer → Frivolous Dispute (-1.5★); against the seller
→ Data Mismatch (-1.5★); `payment` against the buyer → Late Payment
(-0.5★); `non_lifting_collection` against the seller → Delayed
Collection (-0.5★). The remaining combinations (`payment`+seller,
`non_lifting_collection`+buyer, `auction_rejection`+*,
`buyer_non_response`+*) keep the prior generic -0.5 — flagged in code
as genuinely lacking a clean 1:1 named-table match, not silently
expanded to force a mapping that isn't actually there.

**A real, previously-invisible regression caught by the existing
`test:dispute` suite, not papered over**: correcting `condition_delivery`
against the seller from -0.5 to the real -1.5★ pushes a fresh 3.0★
seller to 1.5★ — which now genuinely crosses BR-36's dual-approval
threshold (≤2.0★). The existing test asserted the seller's rating had
already decreased right after a single Tenant Admin ruling — true
under the old, wrong -0.5 magnitude, no longer true under the correct
one, since a Tenant Admin alone can no longer satisfy dual approval.
Fixed the test to check the actually-correct sequence (tenant-approved,
still pending, then genuinely decreases only once Super Admin also
approves) — the same class of fix as D-37's clock-extension test
correction, not a weakened assertion.

**`delistSellerForFraud` (BR-38) now also applies "Confirmed fraud...
Reset to 1★"**, self-approved immediately — the Super Admin's own
confirmed-fraud finding already outranks BR-36's gate, same
self-approval pattern DisputeService already established for a direct
Super Admin ruling.

**`StandingReviewService::recordCbsViolation` now also applies
"Confirmed CBS violation... -2.0★" once past the warning stage (3rd+
offense)** — distinct from, and in addition to, the existing CBS
authority ladder (which governs who may act, not the rating itself).
The flagger (already verified as that listing's Tenant Admin or Super
Admin by the controller) self-approves their own tier; verified
directly that a Tenant-Admin-only flag correctly stays pending Super
Admin sign-off when the magnitude crosses the dual-approval line, while
a Super Admin flag resolves immediately — exactly the same dual-gate
behavior as everywhere else on this platform, not a special case.

**"Sustained clean streak" is a new, general positive counter, deliberately
separate from BR-38's own clean-transaction count.** BR-38's
`crawl_back_clean_completed_*` only counts while a party is actively
rehabilitating; BR-35's streak accrues for every party on every
completed settlement regardless of Crawl-Back state, resets after
firing at 10, and applies automatically (upgrades need no approval).

**A real, pre-existing gap surfaced and partially fixed while building
this — not this decision's fault, but made visible by it**:
`RatingService::approveDowngrade` has never had a real HTTP path for a
Tenant Admin or Super Admin to use — `SettlementService`'s own
"problem" settlement rating (existing since D-25) and
`StandingReviewService`'s escalation-consequence downgrade (D-60) both
create pending downgrades that could only ever be approved via a spark
test command, never a real page. Built `RatingReviewController` at
`/admin/rating-reviews` (Tenant Admin, scoped to tenants they actually
administer via the event's `related_sale_event_id`, or Super Admin) to
close this — the same dual-authorization shape as `PayoutReviewController`
(BR-50). Also threaded `related_sale_event_id` through `SettlementService`'s
"problem" downgrade for the first time, so it's now genuinely reachable
by a scoped Tenant Admin. **Not fixed in this pass**:
`StandingReviewService::ruleOnCase`'s own escalation-consequence downgrade
still doesn't self-approve or thread a sale event (Standing Review cases
are system-initiated with no single transaction, BR-61) — it now appears
in the new queue for Super Admin specifically, but this pre-existing gap
itself wasn't otherwise touched, kept out of this already-large pass's scope.

**Explicitly NOT wired this pass — present in the named-event table as
real data, honestly without a trigger, not faked:**
- `prompt_seller_query_response` — no messaging/query system exists
  anywhere in this codebase.
- `prompt_noc_confirmation`, `prompt_rating_submission`,
  `successful_collection`, `clean_inspection`, `high_participation`,
  `repeated_weak_withdrawal_reasons` — each needs an arbitrary
  "promptness"/"pattern" threshold decision not yet confirmed, on top
  of new counting infrastructure.
- `early_settlement` — needs back-computing each format's topup-window
  start time to measure "within 48 hours of H1"; not attempted this pass.
- `repeated_baseless_dispute_filing`, `repeated_baseless_rejection` —
  need new per-party pattern counters this codebase doesn't have yet.
- `disruptive_conduct_harassment`, `confirmed_fishing_circumvention`,
  `confirmed_offplatform_solicitation`, `dishonest_defect_disclosure`,
  `unprofessional_conduct`, `fulfilling_promised_shipping`,
  `detailed_documentation`, `rapid_handover`, `accurate_description` —
  each would need its own new admin-flagging action (like
  `flagCbsViolation`) or a data source this platform doesn't collect;
  not invented here.
- `confirmed_false_kyc`, `confirmed_kyc_fraud` — blocked on KYC
  verification (BR-17/18) not existing at all (Tier 4, long-deferred).
- `chargeback_against_approved_forfeiture` — blocked on the real
  payment gateway (BR-52, explicitly deferred).

**Verified with a dedicated test (`spark test:br35`, 27 assertions)**:
the full 1st/2nd/3rd Default ladder including the correct magnitudes
and dual-approval gating; the Tenant Admin's scoped review queue
genuinely surfaces a pending default; all four dispute category
mappings firing the exact named event (not the old generic -0.5), with
the honest `auction_rejection` fallback confirmed to carry no
`event_key`; `delistSellerForFraud` genuinely resetting to exactly
1.0★, self-approved; the CBS ladder's rating consequence firing only
at 3rd+ offense, correctly staying pending under a Tenant-Admin-only
flag but resolving immediately under a Super Admin flag; the sustained
clean streak firing exactly on the 10th clean transaction and
resetting; and dispute-driven rating events now genuinely carrying a
real `related_sale_event_id`.

**Verified over real HTTP**: unauthenticated requests to
`/admin/rating-reviews` and its approve endpoint both redirect to
`/login`, matching every other admin-gated route on this platform.

**Full regression: 357 assertions across all twenty engines
(nineteen existing plus the new `test:br35`), zero failures**, run on
a genuinely freshly-migrated database (all 48 migrations from zero).
One pre-existing test (`test:dispute`) required a genuine fix — not a
weakening — for the reason explained above.

**What remains of BR-35**: everything listed as "not wired" above.
Given the scope already covered here, the remaining items are smaller,
individually-addressable increments (each needs its own new counter,
admin action, or upstream dependency) rather than one more
undertaking of this size.

---

### D-65: Seller Management (BR-61) admin view + Consent Audit viewer (BR-51) — built on real existing systems, not a parallel one

**Decision:** The project owner brought a large external "pending work"
document (Phase 3A-3E, ~150 checklist items). Before building anything,
it was checked against this codebase's actual state, not taken at face
value — several sections described building systems that already
exist, one item (a security-questions TOTP recovery flow) directly
contradicted D-41's own explicit rejection of that exact design, and
two items were listed as "explicitly deferred" despite being built
earlier in this same session (AML monitoring, D-62; payout account
change control, D-63). The project owner confirmed keeping D-41's
decision as-is, and chose the smallest, lowest-risk slice first: expose
the genuinely-missing admin visibility around two systems that already
work, rather than any of the larger new-page phases.

**The most consequential finding**: the document's "Phase 3B" proposed
building an entire Seller Standing Review system from scratch —
`SellerStandingService`, a `seller_violations` table, a
`seller_standing_reviews` table, auto-suspension logic. This is BR-61,
**already closed in D-60** and extended further in this session's own
BR-35 work (D-64) — `StandingReviewService`, the real CBS offense
ladder, case management via the shared `dispute` table, annual
anniversary triggers, `StandingReviewController`. Building the
document's version would have forked this into two parallel,
inconsistent tracking systems. Similarly, BR-56 (invoicing, D-57) was
proposed with a `settlement_invoices` table — the real table is
`invoice` (D-57) — and BR-57 (Express defect disclosure, D-56) was
proposed as if unbuilt.

**What was actually built — real gaps around the real systems**:

1. **Seller Management view for Tenant Admin**
   (`SellerManagementController`, `/tenants/{id}/sellers` +
   `/tenants/{id}/sellers/{sellerId}`) — queries the REAL data:
   `standing_review_complaint_count`/`_cbs_offense_count` from `party`,
   whether an open Standing Review case exists (the shared `dispute`
   table), sales completed on this specific tenant, and — genuinely
   useful and not previously exposed anywhere — every real BR-35 named
   rating consequence this seller has actually received (`rating_event`
   where `rating_role='seller_star_rating'` and `event_type='downgrade'`),
   pulled from the same table BR-35 wired, not a separate violations log.
2. **A real, small gap closed in `StandingReviewService::openCase`**:
   it always logged the case-opening audit event with `actor_party_id`
   = null, correct for the two genuinely automatic triggers (complaint
   threshold, annual anniversary) but wrong for a Tenant Admin who
   proactively opens a case on their own judgment ahead of the
   threshold — a new capability this session added
   (`initiateReview` action), now correctly attributing that human
   decision rather than recording it as if the system alone acted.
   Verified both paths independently: a manual initiation now carries
   the real Tenant Admin's ID; the automatic threshold path is
   confirmed unchanged (still null).
3. **Consent Audit viewer** (`ConsentAuditController`,
   `/admin/consent-audit` + CSV export) — BR-51's `consent_event` table
   has been capturing real, discrete consent events since D-56 (per-
   pledge EMD acknowledgment, registration terms) with no way to browse
   or export them until now. Mirrors `AuditLogController`'s established
   filter/export pattern exactly rather than inventing a new one — the
   same "reporting layer on existing data, not new capture" relationship
   BR-58 has to BR-05.

**Deliberately NOT built, and explained why rather than silently
skipped**: no "direct suspend" button on the seller detail page —
`SellerApplicationService::suspendSeller` only fires today as the
outcome of a ruled Standing Review case, and adding a one-click bypass
would undermine the case/ruling discipline BR-61 was built with. No
"restore" action either — re-application (the existing, real path) is
how a suspended seller regains selling rights, not a fabricated
shortcut. No TOTP security-questions recovery — the project owner
confirmed keeping D-41's decision.

**Verified with a dedicated test (`spark test:selleraudit`, 7
assertions)**: the sales-on-tenant count reflects a real completed
settlement; the violation query surfaces a real BR-35 CBS-violation
rating event; a manually-initiated case correctly attributes the
Tenant Admin as actor while a duplicate attempt is correctly rejected;
the automatic threshold path is confirmed still attributing no human
actor; and both a registration and an EMD-pledge consent event are
genuinely stored and queryable.

**Verified over real HTTP**: unauthenticated requests to
`/tenants/{id}/sellers` and `/admin/consent-audit` redirect to login;
a genuinely registered, logged-in ordinary buyer gets a real **403**
attempting to view another tenant's seller list (confirmed with a
syntactically valid but nonexistent UUID — an initial check using a
non-UUID string produced a 500 from Postgres's own type validation
firing before authorization logic ran, a test-methodology artifact,
not an application bug, confirmed by re-running with a real UUID).

**Full regression: 364 assertions across all twenty-one engines
(twenty existing plus the new `test:selleraudit`), zero failures**,
run on a genuinely freshly-migrated database.

**What's left from the pending-work document**: everything in the
"genuinely real gaps" bucket identified during the discussion — the
My Bids/Offers/Purchases/Sales/Settlements pages, account edit/mPIN
change/deletion, marketplace browse/search/filter, favorites/saved
searches, `/admin/users`, backup TOTP codes — none of which were
started this pass, per the project owner's explicit choice to do the
smallest slice first.

---

### D-66: Phase 3A — account management + real transaction pages

**Decision:** Built the genuinely-missing half of Phase 3A: account
edit, mPIN change, account deletion (soft, 30-day grace with
cancellation), an earnings summary, and dedicated, paginated,
filterable "My Bids" / "My Offers" / "My Purchases" / "My Sales" pages
— replacing the pending-work document's unpaginated combined
`/my-activity` page as the real answer for each, while leaving
`/my-activity` itself untouched for backward compatibility.

**A design question checked against the actual source text before
building anything**: the document's mockups showed counter-party
identity masked ("Seller #12345"). BR-16 ("Double-Blind Market Privacy
& Visibility Matrix") was pulled directly — it specifies full
anonymity only *during live bidding*; **after close**, the seller
genuinely sees the H1 winner's real name and details, and in Buy-Now
each side's real identity unlocks on EMD pledge / offer acceptance.
Since a settlement only exists post-close, masking on My
Purchases/Sales would over-anonymize beyond what BR-16 actually
specifies — these pages show the real counter-party mobile number, not
a masked placeholder.

**A real bug caught and fixed before it ever ran**: the first draft of
every new filtered query used a Laravel-style `->when($cond, fn($q) =>
...)` conditional — CodeIgniter's query builder has no such method.
Caught by grepping for the pattern across every file touched before
running anything, not by a failed HTTP request; rewritten as plain
`if` statements around `$q->where(...)`, the established convention
already used elsewhere in this codebase (e.g. `AuditLogController`).

**A second real bug caught the same way**: the first draft of
`myPurchases()` tried to look up each row's dispute via
`$db->table('dispute')->where('sale_event_id', <a QueryBuilder
object>)` — not a real subquery in CI4, just a broken comparison.
Fixed by collecting the page's `sale_event_id`s first and looking up
disputes in one batched query, avoiding both the bug and an N+1 query
pattern.

**A genuine schema check before writing the earnings/My Sales
queries**: the platform's fee split (BR-33) lives on `emd_hold`
(`forfeited_to_tenant_amount`/`_saas_amount`, reused via `markSettled`,
D-25), not on `settlement` itself — confirmed by reading the actual
migration rather than assuming a column name. The join is deliberately
`LEFT JOIN`: Tender sales use a separate `tender_emd_log` with no
platform fee deduction at all (BR-56 explicitly excludes Tender), so a
Tender sale correctly counts toward the sale total while contributing
exactly zero to the fee total — verified directly, not assumed, since
an `INNER JOIN` here would have silently dropped every Tender sale from
"My Sales" entirely.

**Account edit deliberately excludes every BR-17 KYC-verification
field** (PAN, Aadhaar, CIN, GSTIN, MSME/UDYAM registration, date of
birth) — those should only change through a real KYC re-verification
flow (not built, Tier 4), not a casual self-service form. Only
`full_name`, `recovery_email`, `occupation`, and (for organizations)
the descriptive business fields are editable. Verified directly that
the edit path never even reads PAN from the request, let alone writes
it.

**Account deletion reuses the existing `archived_at` logical-archive
mechanism** (already governing login eligibility via
`findByMobile`/`findActiveById`) rather than a new deletion status —
staged through new `deletion_requested_at`/`deletion_reason` columns
and a scheduler job, the same "stage now, scheduler finalizes later"
pattern BR-50's payout cooling-off already established. A cancelled
request is verified to never finalize; a genuinely 30-days-elapsed
request is verified to finalize and the party becomes genuinely
unreachable by mobile lookup afterward — not merely flagged.

**mPIN change is OTP-gated even though the caller is already logged
in** — a hijacked session cookie alone shouldn't be able to change the
credential without also controlling the registered mobile. Verified
over real HTTP, not just the service layer: requested the OTP,
confirmed with it, then logged out and back in with the *new* mPIN
successfully — proving the change genuinely took effect, not just that
the endpoint returned success.

**Settlement detail page extended** with a dispute link (if one exists
for that sale event) and a real audit-trail section scoped to that
specific transaction — surfacing BR-05's existing hash-chained data
per-transaction rather than only via the platform-wide `/admin/audit-log`.
**Not built this pass**: a full bid-by-bid narrative timeline (bid
placed → EMD topped up → NOC → rated) — the existing 4-step BR-33
tracker and the new dispute/audit additions cover most of what was
asked; a genuinely reconstructed event-by-event timeline is a larger,
separate visualization task, flagged rather than half-attempted.

**Verified with a dedicated test (`spark test:phase3a`, 17
assertions)**: pagination math; a cancelled deletion request never
finalizes while a genuinely-elapsed one does, with the party
confirmed unreachable by mobile afterward; the Tender/Buy-Now fee-join
distinction at both the aggregate and per-row level; and account edit
never touching PAN.

**Verified over real HTTP across every new route**: unauthenticated
requests to all nine new pages redirect to login; a genuinely
registered, logged-in party reaches all of them (200) including with
realistic filter/pagination query strings (format, status, sort, date
range, page, per_page — exactly the parameter combinations that would
have caught the `->when()` bug had it shipped); the CSV export
produces a real, correctly-headered file; and the full write-path
cycle (edit account → change mPIN → log out → log back in with the
new mPIN → request deletion → cancel it) was run end-to-end, not just
individually.

**Full regression: 381 assertions across all twenty-two engines
(twenty-one existing plus the new `test:phase3a`), zero failures**,
run on a genuinely freshly-migrated database (all 49 migrations from
zero).

**What remains of Phase 3A**: "My Settlements" as its own page (largely
redundant with the union of My Purchases + My Sales, not built
separately); a generalized amount-range filter and per-tenant filter
(lower value than date/format/status, not built); saved searches;
favorites/watchlist (that's Phase 3C territory). Everything else in
the original acceptance criteria for this phase is now real.

---

### D-67: Phase 3C core — real marketplace browse/search, plus TDS rate confirmed

**Decision:** Following the project owner's "leave anything with an
external dependency, close the rest" instruction, built the real
`/listings` marketplace discovery page: category, format, price range,
location, seller-rating, condition, and posted-date filters; sort by
recent/ending-soon/price/rating; pagination; and a CLV
preference-match badge. Reachable as both `/listings` (the pending-work
document's naming) and the original `/browse` (unchanged), same alias
pattern as `/account`↔`/profile` from D-66.

**The project owner also confirmed BR-53's TDS rate as 10%** (Section
194-O), unblocking an item that had been correctly excluded earlier as
needing exactly this kind of real-world confirmation before
implementation, not a coding gap — tracked as its own item, sequenced
alongside the seller invoicing work.

**A real gap checked and honestly flagged, not silently worked
around**: the document's mockup showed a "State" dropdown for
location filtering. `listing` has no discrete state column — only a
free-text `yard_location_address` and a 6-digit `yard_location_pin`.
Building a real state dropdown would need that structured data added
to the schema first (a genuine, separate small piece of work). Implemented
as a text search against the free-text address instead — an honest
interim behavior, not a fabricated dropdown backed by data that
doesn't exist.

**A second real, pre-existing gap surfaced while wiring the CLV
badge**: `ClvMatchingService::findMatches` saves a buyer's
`comfort_states` preference (BR-23) but never actually filters by it
— only category and budget are enforced. This predates this decision
(the method was built that way originally) and wasn't fixed here,
since doing so meaningfully depends on the same missing state-column
question above — flagged rather than silently left unnoticed a second
time.

**The CLV badge reuses `ClvMatchingService::findMatches` directly**
rather than re-deriving separate matching logic for the browse page —
a listing is marked "matches your preferences" if and only if it's in
the same result set the buyer's own CLV ticker would show, so the two
surfaces can never disagree with each other.

**A real test-isolation bug caught and fixed, not shipped**: the first
version of `spark test:browse` counted matching listings
platform-wide. Run alone, it passed cleanly; run as part of the full
regression sequence, it failed — every other suite creates its own
"Machinery"/"buy_now" test listings, so an unscoped count was
genuinely polluted by every prior suite's leftover data in the shared
database. Fixed by scoping every count to this test's own tenant,
the same discipline `test:aml`/`test:payoutcontrol` etc. already
follow — caught specifically by running the full sequence rather than
trusting the suite in isolation.

**Verified with a dedicated test (`spark test:browse`, 9 assertions)**:
category/format filters genuinely narrow results; the price filter
operates correctly against the `COALESCE`'d cross-format comparable
price; location text search narrows correctly; the rating filter
correctly excludes a lower-rated seller's listing; Shadow Banning
(BR-38) is confirmed still enforced at the query level, not just
visually; and the CLV badge set is confirmed to be exactly
`ClvMatchingService`'s own real match set.

**Verified over real HTTP**: both `/listings` and `/browse` return
200; the full realistic filter/sort/pagination combination (the exact
parameter shape that would have caught a repeat of D-66's `->when()`
bug) returns 200 for every sort mode.

**Full regression: 390 assertions across all twenty-three engines
(twenty-two existing plus the new `test:browse`), zero failures**, run
on a genuinely freshly-migrated database.

---

### D-68: Phase 3C+ — favorites, saved searches, search history, recommendations

**Decision:** Built the remaining Phase 3C+ discovery features: a
favorites/watchlist toggle on every listing, saved searches (name a
filter combination from `/listings`, one-click re-run), automatic
search history (last 20), and two real recommendation sections —
"Trending Now" (genuine bid-activity count in the last 24 hours) and
"Based on Your Bids" (other active listings in categories the buyer
has actually bid in, excluding ones they've already bid on).

**Notification-on-match/price-drop deliberately not attempted** — the
document's own framing already correctly flagged this as needing "a
notification system" that doesn't exist, the same category of gap as
the SMS provider itself. Favorites/saved searches are real and
persist; the *alerting* half is out of scope until a real notification
channel exists.

**A second unverified-API risk avoided, having already been burned
once this session (D-66's `->when()` bug)**: the first draft of the
"Based on Your Bids" query passed a raw `QueryBuilder` instance
directly into `whereNotIn()` as a pseudo-subquery. Rather than trust
that CI4 supports this without confirming it against this codebase's
own established usage, rewrote it as two steps — fetch the excluded
IDs as a plain array first, then filter — matching the pattern already
proven throughout this codebase rather than gambling on an unverified
API surface a second time.

**"Similar to X" was considered and deliberately not duplicated** —
BR-47's Related Auctions (D-54) already provides genuine per-listing
"similar items" via seller-chosen grouping; a second, separate
category-based similarity feature on the recommendations page would
have been redundant with, and potentially inconsistent with, the
existing mechanism.

**Verified with a dedicated test (`spark test:discovery`, 12
assertions)**: favoriting is idempotent (a second add doesn't violate
the unique constraint or duplicate); saved search filters round-trip
correctly through JSON storage; search history genuinely caps at 20 on
retrieval even when 25 were recorded, most-recent first; Trending Now
reflects a real, recent bid; and Based on Your Bids both includes a
same-category never-bid-on listing and correctly excludes the one
already bid on.

**Verified over real HTTP**: all four new pages redirect unauthenticated
visitors to login and return 200 for a genuinely registered, logged-in
party; saving a search over real HTTP and reloading the saved-searches
page confirms it was genuinely persisted, not just accepted.

**Full regression: 402 assertions across all twenty-four engines
(twenty-three existing plus the new `test:discovery`), zero failures**,
run on a genuinely freshly-migrated database (all 50 migrations from
zero).

**This closes Phase 3C entirely** (D-67 + this decision) — the last
item from the original pending-work document's Phase 3C section.

---

### D-69: Phase 3D — Admin robustness (backup TOTP codes, user/tenant directories, dashboard metrics, alerts, BR-06 branding)

**Decision:** Closed out the remaining Phase 3D gaps from the pending-work
document, all with zero external dependencies:

1. **Backup TOTP codes at enrollment (PR-17).** Every successful
   `confirmTotpSetup` (first enrollment or re-enrollment) now generates
   10 single-use backup codes, bcrypt-hashed at rest in a new
   `super_admin_backup_code` table, shown exactly once on the
   confirmation page and never persisted in session or flash data.
   `SuperAdminAuthService::confirmTotpSetup()`'s return type changed
   from `bool` to `?array` (the plain codes on success, `null` on a
   wrong code) — a deliberate breaking change to the method's contract,
   fixed forward into `TestTier3.php`'s assertions rather than papered
   over. `SuperAdminAuthService::login()` now falls back to
   `SuperAdminBackupCodeModel::consumeIfValid()` when the primary TOTP
   check fails, consuming the code so it can't be reused, and logs
   `admin.totp_backup_code_used` to the audit trail. Regenerating
   (enrollment or re-enrollment) invalidates every prior code — they
   were trust-bound to the old device/enrollment context.

2. **`/admin/users`** — a platform-wide, searchable (mobile/name/email/GSTIN)
   user directory with a detail page showing roles, ratings, offence
   counts, standing-review counters, recent purchases/sales, disputes,
   and rating events. Previously a party (buyer, inspector, tenant
   admin) was only reachable if you already knew their UUID; sellers
   were the only role with any admin-facing list (tenant-scoped, via
   `SellerManagementController`).

3. **`/admin/tenants`** — a dedicated, searchable tenant list page,
   separated out from the dashboard's embedded table.

4. **BR-06 tenant branding upload.** `branding_logo_url` and
   `branding_primary_color` have existed on the `tenant` table and in
   `TenantModel::$allowedFields` since Phase 0, but nothing ever wrote
   to them. `TenantController::editSubmit` now accepts a logo file
   (JPEG/PNG/WebP/SVG, stored at `public/uploads/tenants/{tenantId}/`,
   same convention as `MediaService`'s listing-photo storage) and a
   primary-color text field. Host-header custom-domain routing itself
   (the other half of BR-06) remains explicitly out of scope — real DNS
   configuration, per the standing "close rest" exclusions.

5. **Dashboard metrics + `/admin/alerts`.** `AdminController::dashboard()`
   now surfaces today's activity (bids placed, sales closed, disputes
   filed, new sale events by format) and a stalled-settlements aging
   table (oldest first, days-stalled computed via `EXTRACT(DAY FROM ...)`).
   A new `/admin/alerts` page aggregates every "needs a decision" queue
   that was previously scattered across separate pages — open AML
   flags, pending payout reviews, pending rating reviews, open disputes,
   stalled settlements — into one triage view. No new underlying data;
   this only surfaces what `AmlFlagModel`, `PayoutReleaseReviewModel`,
   `RatingEventModel::findAllPending()`, and `DisputeModel` already
   track.

**Not attempted, per the standing "close rest" exclusions:** `/admin/users`
has no write actions beyond what already exists elsewhere (delisting,
rating review, standing review keep their own governed controllers) —
deliberately kept read-only rather than growing a second, competing
mutation path.

**Verified with `spark test:tier3`** (20 assertions, up from 18): the
two new assertions confirm a valid backup code logs a Super Admin in
exactly as a TOTP code would, and that the same code is rejected on a
second attempt.

**Verified over real HTTP**, end to end, against a running server: regular
login → `/admin/setup-totp` → confirm with a computed TOTP code → 10 real
backup codes rendered on the confirmation page → `/admin/login` with one
of those codes succeeds (303 to `/admin`) → the same code reused a second
time is correctly rejected ("Invalid or expired authentication code").
Also verified `/admin`, `/admin/tenants`, `/admin/users`, `/admin/users/{id}`,
and `/admin/alerts` all return 200 for an authenticated Super Admin
session with no rendering errors, and that a tenant branding edit
(logo + primary color, multipart upload) genuinely persists — the
uploaded file lands on disk and the tenant view reflects the new color
and `<img>` tag on reload.

**Full regression: 404 assertions across all twenty-four engines, zero
new failures**, run on a genuinely freshly-migrated database (all 51
migrations from zero). The only non-passing engine is `test:auditlog`,
which fails on the pre-existing, unrelated D-62 bug (hardcoded
`ebidhub_ci4` database name in a `psql` shell-out, vs. the real
`ebidhub` database) — not caused by, or fixed by, this decision.

**This closes Task #3 of the "close rest" plan.**

---

### D-70: Missing composite database indexes

**Decision:** Audited every existing index against the codebase's actual
query patterns (not speculative guesses) and added four composite
indexes for genuinely hot, previously under-indexed WHERE+ORDER BY
combinations:

- `settlement (buyer_party_id, created_at DESC)` and
  `settlement (seller_party_id, created_at DESC)` —
  `MyActivityController::myPurchases/mySales` (and their CSV export
  siblings) always filter by one of these two columns and order by
  `created_at DESC`; only single-column indexes existed before.
- `settlement (seller_party_id, status, completed_at)` —
  `AccountController`'s earnings query filters by seller +
  `status = 'completed'` + a `completed_at` range.
- `dispute (respondent_party_id, category, status)` —
  `StandingReviewService::hasOpenCase`, `SellerManagementController`,
  and the new `UserController::detail` (D-69) all filter by
  `respondent_party_id`, most combined with `category` and/or `status`.
  `respondent_party_id` had **no index at all** before this — only
  `filed_by_party_id` did, despite both columns being queried about
  equally often.

**Deliberately not added:** a `bid (sale_event_id, standing, created_at)`
style index was considered (it appears as an example in the original
pending-work document) but checked against real usage first — `bid` has
no `created_at` column (it's `placed_at`), and the actual hot query
(`BidModel::findCurrentHighBid`/`findRankedBids`) filters by
`sale_event_id` and orders by `amount DESC, placed_at ASC`, which the
existing `idx_bid_sale_event (sale_event_id, amount DESC)` already
serves. The `standing` filters that exist (`resetOutbidStandings`,
`withdrawAllForSaleEvent`) are low-cardinality bulk UPDATEs scoped by
`sale_event_id` first, which is already indexed — adding `standing` to
that index would not meaningfully help. Building the index the document
suggested, rather than the index the actual code needs, would have been
cargo-culting a stale example instead of verifying it against this
schema.

**Verified:** migration applies cleanly on a fresh database (all 52
migrations from zero). **Full regression: 404 assertions across all
twenty-four engines, zero new failures** (same single pre-existing,
unrelated `test:auditlog`/D-62 failure as D-69) — an index-only change,
so no behavioral assertions were expected to move, and none did.

**This closes Task #4 of the "close rest" plan.**

---

### D-71: BR-53 — TDS deduction at 10% (Section 194-O)

**Decision:** BR-53's own text explicitly leaves the TDS rate open
pending tax-advisor confirmation — the previous audit correctly flagged
it as an external dependency for that reason. The Super Admin (project
owner) has now confirmed the rate directly: **10%**, unblocking this.

Implemented as a real, wired deduction rather than a documentation-only
placeholder: `settlement` gained `tds_rate_percent`/`tds_amount`
columns. `SettlementService::checkCompletion()` computes TDS as 10% of
the **gross** `final_price` at the same point the settlement transitions
to `completed` (both the normal 4-step path and `forceResolveStalled`'s
stall-resolution path, since both funnel through `checkCompletion`),
stores it on the settlement row, and logs `settlement.tds_deducted` to
the audit trail.

**Deliberately NOT merged into the BR-56 GST invoice.** BR-56's own text
scopes the invoice to "the applicable platform commission ... and any
Payment Gateway collection charge" — it says nothing about TDS. TDS is
a distinct withholding-tax obligation on the seller's proceeds under
194-O, not a component of the commission invoice, so it gets its own
field and its own audit trail entry rather than being folded into an
invoice type that BR-56 didn't define it into.

**Deliberately NOT excluded on Tender**, unlike BR-56's invoice — BR-53's
own statement carries no format carve-out ("the platform deducts TDS on
the gross amount of facilitated sales"), so it applies uniformly
regardless of `sale_format`.

**Surfaced to the seller** in three places that already existed and
already showed the buyer-side fee split, so TDS was added alongside it
rather than as a new page: `/account/earnings` (This Month/YTD TDS
totals, and a corrected "Net Received" that now actually subtracts TDS
in addition to fees — previously the label was accurate for D-66's
earnings feature at the time, but is now updated since a real
deduction it needs to account for exists), `/my-sales` (a per-sale TDS
column, list and CSV export), and the settlement detail page.

**Verified with `spark test:settlement`** (23 assertions, up from 21):
on a ₹95,000 sale, confirms `tds_rate_percent = 10.0` and
`tds_amount = 9500.00` (95000 × 10%) stored on the completed
settlement.

**Verified over real HTTP**: logged in as the seller from the test
fixture, `/account/earnings` correctly aggregates ₹14,300 total TDS
across 2 completed sales (₹143,000 gross) with `net_earnings` matching
gross minus fees minus TDS exactly; `/my-sales` shows the exact
per-row ₹9,500.00 TDS figure matching the test's math; the settlement
detail page renders the same gross × rate = TDS breakdown.

**Full regression: 406 assertions across all twenty-four engines, zero
new failures**, run on a genuinely freshly-migrated database (all 53
migrations from zero). The only non-passing engine remains the
pre-existing, unrelated `test:auditlog`/D-62 bug.

**This closes Task #8 of the "close rest" plan** — the last item that
was blocked on an external dependency (the rate) is now unblocked and
shipped.

---

### D-72: BR-56 invoice history + real PDF generation

**Decision:** Invoices (BR-56) already existed and were correctly
generated at settlement completion, but were only ever reachable one
settlement at a time, embedded in `SettlementController::show` — there
was no aggregate history view and no PDF export at all. Added:

1. **`GET /account/invoices`** — a paginated invoice history for the
   logged-in party, reusing the existing `Paginator` helper. Scoped to
   `tenant_to_buyer` invoices only (the type actually billed to a buyer
   or generated for a seller's sale); `saas_to_tenant` invoices are
   platform-internal (Tenant/SaaS-only, per BR-56's own text: "SaaS's
   own share separately invoiced to the Tenant") and correctly excluded
   from any buyer/seller-facing view.
2. **Real party-scoping, not just a UI filter.** `InvoiceService` gained
   `findForParty`/`countForParty` (joins `invoice → settlement`, matches
   `buyer_party_id` OR `seller_party_id`) and `findIfAuthorized` (single
   invoice, same buyer-or-seller check). A genuine third party gets a
   403 on the PDF endpoint and an empty list at `/account/invoices` —
   verified over real HTTP, not just assumed from the query shape.
3. **`GET /account/invoices/{id}/pdf`** — a real, rendered PDF via
   `dompdf/dompdf` (newly added via `composer require`, no existing PDF
   library was present). Confirmed genuine PDF output, not just an HTML
   response with a misleading content-type: the bytes start with the
   `%PDF-` magic header and contain real `/Type/Page` structure.

**A real test-isolation bug caught before it reached the shared
regression**, matching the same class of issue D-32/D-38/D-67 already
flagged in this project's history: the first draft of
`TestInvoices.php` reused mobile numbers `+919888901001-3`, which
collided with `TestEasySchedule.php`'s existing `+919888901001/2`.
Passed standalone, failed when run as part of the full sequence.
Fixed by moving to an unused `+919889901xxx` prefix, confirmed against
every other test command's fixtures first this time rather than after
the fact.

**Verified with `spark test:invoices`** (11 assertions): both invoices
generate on settlement completion; buyer and seller both see the same
`tenant_to_buyer` invoice via `findForParty`, `saas_to_tenant` is
excluded, an unrelated party sees zero; `findIfAuthorized` allows
buyer/seller and denies a stranger; the rendered PDF has the correct
magic bytes and non-trivial size.

**Verified over real HTTP**: logged in as the buyer, `/account/invoices`
lists the real invoice and its PDF downloads with valid `%PDF-1.7`
content (1840 bytes, contains a genuine `/Type/Page`); logged in as the
seller, the same invoice is visible; logged in as an unrelated third
party, the PDF endpoint returns 403 and the list is empty.

**Full regression: 417 assertions across all twenty-five engines
(twenty-four existing plus the new `test:invoices`), zero new
failures**, run on a genuinely freshly-migrated database. The only
non-passing engine remains the pre-existing, unrelated
`test:auditlog`/D-62 bug.

**This closes Task #5 of the "close rest" plan.**

---

### D-73: PR-09 — full Asset Media Upload & Compression Pipeline

**Decision:** An audit against PR-09's literal 8-step operational
sequence found the compression math (WebP/ffmpeg) and primary-photo
mechanics were solid, but several steps were partial or entirely
missing — and one, the reject flow, was silently producing an
incorrect audit trail. Closed every gap that has no external
dependency:

1. **A real background job queue**, not just a fast-looking request.
   New `media_upload_job` table + `MediaUploadJobModel`. `MediaService`
   split into `enqueueUploads()` (validates, stages raw files under
   `writable/uploads_staging/` — outside the public webroot, since
   they're unprocessed — and creates job rows; returns immediately) and
   `processJob()` (the actual WebP/ffmpeg work, called only by the
   worker). `MediaQueueService::processNext()/processAll()` claims and
   drains jobs strictly FIFO (`ORDER BY created_at ASC`), platform-wide.
   `php spark process:media-queue` is the real cron entry, and it's also
   wired into `SchedulerService::runAll()` so the existing cron sweep
   drains it without a second, separate cron line — matching this
   project's established "stage now, scheduler finalizes later"
   pattern. One bad file (corrupt upload, or a video when ffmpeg isn't
   installed) is caught and marked `failed` with the real error message
   — it does not stop the rest of the sequential queue from draining.

2. **Document upload support**, genuinely absent before. `listing_media_type`
   gained a `document` enum value (PDF only); documents are stored
   as-is (no compression pipeline applies to a PDF the way it does to
   images/video) and rendered with a distinct icon rather than
   attempting to `<img>`-tag them.

3. **Video upload form field** — backend support already existed but
   the actual upload form never rendered a `videos[]` input at all,
   making it unreachable from the UI. Added, alongside the new
   documents field.

4. **Explicit "no Main Display Photo" submit gate.** PR-09 step 6 blocks
   submission on "fewer than 5 photos OR no Main Display Photo" — only
   the photo-count half was ever actually checked.
   `submitForApproval` now also verifies a real `is_primary=true` photo
   row exists. This was previously unreachable only by coincidence (the
   first uploaded photo auto-becomes primary and there's no
   media-delete feature) — an unenforced assumption, not a real
   validation rule, now made explicit.

5. **A real closed-list rejection reason, fixing an active correctness
   bug.** The reject button had no reason input field at all —
   `ListingController::reject` silently hardcoded the literal
   `'insufficient photos'` on every single rejection, regardless of the
   real reason, misrepresenting the audit trail on every use. Added
   `ListingLifecycleService::REJECTION_REASONS` (the exact 4-item closed
   list from PR-09's own text), validated in `reject()`, with an
   optional free-text detail appended (`"Mismatched description: Photos
   show a different model number than described."`). The reject form
   now has a real `<select>` sourced from the same closed list.

6. **A real Tenant Admin Verification Console** (`/tenants/{id}/verification`),
   not just a bare pending-listings text list. Shows the real primary
   photo thumbnail (or an honest "no primary photo yet" placeholder),
   and live photo/video/document/still-queued counts per listing.
   Approve/reject itself still happens on the existing listing detail
   page — this is the entry point with real visual context PR-09 calls
   for, not a duplicate workflow.

7. **Browser localStorage form autosave** on the listing-creation form,
   restoring text/select/checkbox values after a reload or tab switch.
   **Honest limitation, flagged rather than overclaimed** (same
   discipline as BR-45's GPS-capture precedent): localStorage cannot
   hold `File` objects — only field VALUES are recoverable this way,
   never the actual selected files, which the seller must re-choose.
   The draft is cleared on genuine submit (not kept around to
   stale-fill the next listing's form) — recovery is for reload/
   tab-switch before submitting, per PR-09's own wording, not for a
   server-side validation failure after submission.

**A real breaking-change ripple, fixed forward.** Two existing tests
called into the code paths this decision changed:
`ListingLifecycleService::reject()`'s signature changed from
`(id, reason)` to `(id, reasonKey, detail, actorId)`, and
`submitForApproval()` gained the primary-photo gate.
`TestLifecycle.php`'s fixture (which fakes `media_count` directly
rather than doing real uploads, since real file uploads aren't
practical in a CLI test context) needed a fake primary `listing_media`
row added, and its `reject()` call updated to the new signature and
closed-list reason. `TestScheduler.php` got one added assertion for the
new `mediaJobsProcessed` key on `runAll()`'s summary.

**Verified with a new `spark test:media`** (27 assertions): a real
photo is genuinely WebP-compressed through the queue and auto-marked
primary; a second photo does NOT steal primary; a document is stored
as-is; a fabricated video job genuinely fails (no ffmpeg on this test
host) without crashing the queue or blocking a photo queued after it;
the queue is genuinely empty once drained; both submission gates
(photo count, primary) block correctly; an out-of-list rejection reason
is rejected, not silently accepted; a valid one combines the closed-
list label with free-text detail exactly; the 50-photo cap counts
still-queued jobs, not just finished media.

**Verified over real HTTP**, end to end against a running server: real
registration → seller approval (via a scratch bootstrap command) →
listing creation → multipart upload of 2 real JPEGs + 1 real PDF
(document correctly MIME-sniffed as `application/pdf`, not just
trusted from the client's `Content-Type` header) → upload returns
immediately (not blocking on compression) → listing page shows the
files as "pending…" in the background-queue panel → `php spark
process:media-queue` drains all 3 → listing page now shows 2 real WebP
photos (one marked PRIMARY) and the document with its icon → submitted
for approval → Tenant Admin's Verification Console shows the real
thumbnail and correct counts → rejected with a closed-list reason +
detail, which is genuinely stored and displayed back to the seller →
a bogus reason key is genuinely rejected (listing stays
`pending_approval`, not silently transitioned). Also confirmed a batch
mixing a genuinely-invalid file (plain text mislabeled as
`video/mp4` — content-sniffed correctly, not fooled by the client's
claimed MIME type) aborts that whole batch with a clear error — this is
pre-existing "validate the whole loop, throw on first bad file"
behavior inherited from the original synchronous code, not introduced
by this refactor.

**Full regression: 446 assertions across all twenty-six engines
(twenty-five existing plus the new `test:media`), zero new failures**,
run on a genuinely freshly-migrated database (all 54 migrations from
zero). The only non-passing engine remains the pre-existing, unrelated
`test:auditlog`/D-62 bug.

**This closes Task #6 of the "close rest" plan.**

### D-74: BR-06/PR-06 — Custom-domain / subdomain white-label routing

**Decision:** BR-06 requires that "on request, the edge layer inspects
the incoming Host header to match the tenant... injects the tenant's
branding and inventory, displaying a white-label portal," while buyers
stay "federated globally... across every tenant domain." The `tenant`
table already had `subdomain`/`custom_domain`/`branding_*` columns
since Phase 0, but nothing ever read the Host header or scoped a page
by it — every domain showed the same platform-wide, unbranded view.

Built as a global CI4 filter, `TenantResolutionFilter`, first in
`$globals['before']` so it runs on every request ahead of routing. It
tries an exact `custom_domain` match first, then derives the
platform's own root domain from `config('App')->baseURL` (not
hardcoded) and checks for a `{label}.{platformHost}` pattern to match
`subdomain`. A resolved, non-suspended tenant is stashed in a new
static `TenantContext` holder; anything else (platform's own domain,
localhost, an unmapped host) leaves the context unset and falls through
to today's unscoped behavior unchanged — the filter never blocks a
request. `Home::index()` and `Home::browse()` read `TenantContext`
and add a `tenant_id` scope to their listing/category queries only
when a tenant is resolved; `layouts/main.php` reads it once (every page
extends this layout) to swap in the tenant's logo/name in the header,
override the Terms link, and inject a `color-mix()`-derived brand
palette. Two small gaps closed in `TenantController`: `custom_domain`
was accepted on tenant creation but never actually asked for on the
create form, and `terms_url` existed as a column but had no edit-form
field at all — both added.

**One real bug found and fixed by verifying with a headless browser,
not just curl.** Curl's raw HTML for the brand-color `<style>` block
looked correct — `--emerald: #C05014` — but CI4's `esc($value, 'css')`
was actually hex-escaping the `#` into `\23 C05014`. That's valid CSS
*text*, but the CSS tokenizer consumes a leading escape into an
*identifier* token, not a *hash-color* token, so `color-mix(in srgb,
\23 C05014 80%, black)` silently parsed as an invalid color at
used-value time — real Chromium rendered `.btn-emerald` as fully
transparent (`rgba(0,0,0,0)`), not the tenant's orange. Curl alone
would have shipped this bug undetected. Fixed at the root: brand color
is now validated server-side against a strict `/^#[0-9a-fA-F]{6}$/` in
`TenantController::editSubmit()` (rejecting anything else with a flash
error) and re-validated with the same regex before being printed
literally in the layout — no CSS-context escaping needed once the
charset is provably safe, and none of the injection risk `esc()` was
guarding against remains possible.

**Verified over real HTTP against a live server** (Postgres,
`php spark serve`), using `/etc/hosts` entries plus real Chromium via
Playwright to check actual computed styles, not just response text:
tenant "PNB Salvage Yard" with `subdomain=pnb`,
`custom_domain=www.salvagemanagers.com`, an uploaded logo,
`branding_primary_color=#C05014`, and a `terms_url`, alongside a second
tenant with no domain and an unrelated listing, as a negative control.

- `pnb.localhost:8080` and `www.salvagemanagers.com:8080` (both `/` and
  `/browse`) show only PNB's listing/category, the uploaded logo in the
  header, `.btn-emerald` genuinely rendering `rgb(192, 80, 20)`, and the
  Terms link pointing at the tenant's own `terms_url`.
- Plain `127.0.0.1:8080` (no Host match) and an unmapped
  `random.localhost:8080` both fall through to the original
  platform-wide view — both listings, default emerald
  (`rgb(15, 92, 76)`), no logo, `/terms` — confirming the filter never
  narrows what an unresolved buyer can see, matching BR-06's "buyers
  are federated globally" requirement.

**Not covered here (out of scope for this pass):** DNS/TLS provisioning
for a tenant's real custom domain is an infrastructure concern outside
the application layer; this closes the "edge layer inspects the Host
header and shows a white-label portal" application-level requirement.

### D-75: PR-04 — Sovereign Rule Revision & Governance Flow

**Decision:** PR-04's operational sequence: Super Admin authenticates via
the isolated TOTP flow, "unlocks the Rules & Specifications module,"
reviews current rules or defines a new one (Title, Statement, Logic),
submits a mandatory "Reason for Modification," and "the system versions
the change, commits it, and updates the live application's behavior."
Before this, every business rule was a hardcoded PHP const — changing
the 150% bid ceiling, the 10% EMD baseline, or anything else required a
code change and redeploy, not an admin action. The audit called this
"closer to a rules-engine rewrite than a feature addition" — honestly
scoped here rather than fabricated: no generic rule-expression evaluator
was built (that really would be a rules-engine rewrite). Instead, the
five thresholds that were ALREADY simple numeric consts, scattered
across services, were made genuinely live.

**What's built:**
1. `sovereign_rule` (current state, versioned) + `sovereign_rule_revision`
   (full snapshot per version, in addition to — not instead of — a
   `sovereign_rule.revised` entry in the existing BR-05 audit hash chain
   on every change) via `SovereignRuleService`.
2. Five rules genuinely rewired from hardcoded consts to live,
   admin-editable values, each keeping its original figure as the
   fallback default until a Super Admin actually edits it:
   `BiddingService::BID_CEILING_MULTIPLIER` (150%, BR-43),
   `EmdService::EMD_PERCENT` (10%, BR-27),
   `SettlementService::HIGH_VALUE_DISPOSAL_THRESHOLD` AND
   `PayoutControlService::HIGH_VALUE_THRESHOLD` (₹10L, BR-49 — the SAME
   rule key, so editing it once genuinely changes both the disposal-
   reporting trigger and the payout review gate together, not two
   independent numbers that happen to match), and
   `RatingService::SHADOW_BAN_THRESHOLD` / `CRAWL_BACK_THRESHOLD` (BR-38).
3. A Super Admin "Rules & Specifications" UI (`/admin/rules`, superAdmin-
   filtered): list view, edit form (mandatory Reason for Modification,
   rejected server-side if blank), revision history, and a "define a new
   rule" form for freeform governance rules (Title/Statement/Logic only —
   no `rule_key`, so no live code effect, but still versioned and audited
   the same way, satisfying BR-01's "rationale record" for policy
   decisions that don't map to a single numeric knob).

**One real bug found and fixed by writing the automated test, not just
the manual HTTP walkthrough**: `getNumeric()` caches a rule's value for
the life of the process to avoid a DB round-trip on every bid. `update()`
wrote the new value to the database but never invalidated that cache —
harmless for the built-in dev server (one process per request), but a
real latent bug for any long-running/persistent-worker deployment, and
it broke `test:sovereignrule` itself the moment the test tried to read
back a value it had just written in the same run. Fixed by having
`update()` refresh the cache entry immediately after writing.

**A second real bug, found only by running the FULL regression suite,
not just the new test in isolation**: `test:sovereignrule` edits five
platform-wide rules — unlike every other `Test*` command, which only
creates its own isolated tenant/party/listing rows, this one mutates
genuinely shared configuration. Running the full suite in sequence,
`test:tier3` (which runs after it alphabetically) failed for real: it
held an EMD deposit sized for the original 10%, then tried to bid after
`test:sovereignrule` had left the live rule at 20%, and was correctly
rejected by BR-27's live check for insufficient EMD. The rule wiring
itself was working exactly as intended — the bug was that
`test:sovereignrule` didn't clean up after itself. Fixed with an explicit
teardown step that restores all five rules to their original values at
the end of the run, asserted, not just assumed.

**Verified with a new `spark test:sovereignrule`** (20 assertions): all
five rules seed at their exact original hardcoded values; an empty
Reason for Modification is genuinely rejected; editing the BR-43 ceiling
from 150% to 120% makes a bid that the OLD ceiling would have allowed
(140%) get genuinely rejected, while a bid within the new ceiling (115%)
is accepted; editing BR-27's EMD percent genuinely changes
`EmdService::calculateBaselineEmd()`'s output; editing the shared BR-49
threshold genuinely trips both `SettlementService`'s disposal-reporting
flag AND is read by `PayoutControlService`'s review gate from the same
row; editing BR-38's shadow-ban threshold genuinely shadow-bans a rating
the old threshold would not have; every successful edit produces exactly
one `sovereign_rule.revised` audit-log entry (a rejected empty-reason
attempt produces none); a freeform rule has no `rule_key`, starts at v1,
and appears in the same listing as the wired rules; and the teardown
genuinely restores all five originals.

**Verified over real HTTP** against a live server: registered a fresh
party, granted `super_admin` via `spark grant:super-admin`, enrolled real
TOTP (RFC 6238, computed with a script replicating `TotpService`'s exact
algorithm — not a stubbed code), logged in through the isolated
`/admin/login` TOTP-gated path (the real `SuperAdminFilter` boundary, not
just a role check), then confirmed `/admin/rules` lists all five seeded
rules at their exact original values; confirmed an edit with a blank
Reason for Modification is rejected with the value left completely
unchanged (verified directly in Postgres); confirmed a real edit with a
reason genuinely versions the row to v2, writes a `sovereign_rule_revision`
row, and writes a `sovereign_rule.revised` audit-log entry with the actor
party ID and the exact reason text.

**Full regression, run twice on a genuinely freshly-migrated database
(all 55 migrations from zero) — both before AND after the teardown fix,
to prove the fix was real**: 455 assertions across all twenty-seven
runnable engines (twenty-six existing plus the new `test:sovereignrule`),
zero new failures once the teardown fix was in. Two pre-existing, unrelated
gaps, both confirmed NOT caused by this change: `test:auditlog`'s known
D-62 bug (3 failures), and `test:invoices` (fails on a missing `Dompdf`
package in this sandbox, unrelated to rule wiring).

**Not covered here (deliberately, honestly out of scope):** there is no
generic rule-expression evaluator — a freeform rule's Logic field is
descriptive text, not an executable expression, and creating one that
doesn't match a pre-registered `rule_key` has no runtime effect. Building
a true expression engine (so any new freeform rule could be wired to code
without a deploy) is the "rules-engine rewrite" the original audit
flagged as out of scope, not this pass.

### D-76: BR-17/BR-18/BR-55/PR-15 — Dual-Track Patron KYC Verification, Multi-Address & Banking, Mandatory Pre-Transaction Gate

**Decision:** The largest remaining open item, deliberately deferred
since early in the project. The `party` table already had almost the
entire BR-17 questionnaire schema since Phase 0 (`entity_type`, `pan`,
`aadhaar_masked`, the `org_*` fields, `kyc_status`), but
`PartyModel::setKycStatus()` was confirmed dead code — called from
nowhere. No document upload path, no address schema, and no gate
existed anywhere.

Two pieces of PR-15's operational sequence are externally gated the
same way Auth0/Gemini/the payment gateway are — confirmed with the
project owner before writing any code, who chose the honest fallback
over fabricating an integration: **"Unless we have automated external
api for verification let it be done by the saas admin."**
- "runs automated PAN/GSTIN registry checks" (PR-15 step 4): no real
  NSDL/GSTN API exists. `KycService::verifyComplianceFlag()` is instead
  a manual SaaS Admin action per compliance flag (PAN/GSTIN/Aadhaar/
  Bank/Email), audit-logged with the verifying admin's identity.
- "Aadhaar (masked/tokenized)" (BR-17): no UIDAI tokenization service
  exists. The raw 12-digit number is masked to its last 4 digits
  (`XXXX-XXXX-1098`) immediately in `KycService::saveQuestionnaire()`
  and never persisted in cleartext anywhere outside the encrypted
  Aadhaar Card document upload — genuine masking, not UIDAI's real
  Virtual ID/token scheme.

**A deliberate, flagged deviation from PR-15's literal text**, decided
without a second round of clarifying questions given the strength of
the architectural case: PR-15 says "Tenant Admin reviews the compliance
dossier and transitions master KYC Status." KYC is party-level data
with no owning tenant, though — unlike every resource `TenantAdminFilter`
actually guards (listing, saleEvent, settlement, sellerApplication, all
genuinely tenant-owned), a Party's own identity isn't scoped to one
tenant (BR-06: buyers are federated globally). There is no coherent
answer to "which Tenant Admin" for a buyer who hasn't transacted with
any tenant yet. Routed to Super Admin instead — `KycReviewController`'s
own doc block states this reasoning — consistent with how this codebase
already handles other genuinely platform-wide compliance functions
(BR-54 AML review, BR-05 audit log, BR-49's cross-tenant reporting).

**What's built:**
1. Three new migrations: compliance-flag columns on `party`
   (`pan_verified_at`/`gstin_verified_at`/`aadhaar_verified_at`/
   `email_verified_at`/`bank_verified_at` — `mobile_verified_at`
   already existed), the BR-18 banking gap (`payout_bank_account_holder_name`/
   `_name`/`_branch_name`/`_upi_id`, added onto BR-50's existing one-
   party/one-bank-record fields, not a duplicate), a `submitted`
   `kyc_status` enum value, and enhanced-due-diligence tracking columns.
   `party_document` (encrypted vault) and `party_address` (BR-18's four
   typed addresses, `UNIQUE(party_id, address_type)` so re-registering
   upserts, never duplicates).
2. `KycService`: entity-type-specific questionnaire validation (PAN
   regex, required-field closed lists per BR-17), real AES document
   encryption via CI4's `Encryption` service (`service('encrypter')`,
   keyed from `.env`'s `encryption.key`) — files are never written to
   `writable/kyc_vault/` in plaintext, and that path sits outside the
   public webroot entirely (unlike listing photos, a KYC document must
   never be reachable by a guessed URL). `submitForReview()` genuinely
   checks required documents + a Registered address are present before
   allowing submission. `reviewDossier()` enforces a closed-list reason
   on suspension (`SUSPENSION_REASONS`), mirroring BR-17's own text.
3. **BR-55's gate is genuinely live, not a decorative check**: added to
   the exact four real user-facing entry points where a pledge/listing
   actually originates — `BidController::devFundEmd`,
   `OfferController::devFundEmd`, `EmdConsentController::confirm`, and
   `ListingController::createSubmit` — deliberately NOT inside
   `EmdHoldModel::createHold()`/`ListingModel::createListing()`
   themselves, which nearly every existing `Test*` command and internal
   service (cascade top-ups, tender manual EMD) calls directly to set up
   scenarios; gating the model would have broken the entire existing
   regression suite for no correctness benefit, since those are
   test/internal setup paths, not a real "first EMD pledge."
4. **BR-55's enhanced-due-diligence threshold is a genuinely live
   Sovereign Rule** (`BR-55.enhanced_due_diligence_threshold`, seeded at
   ₹5L), not a hardcoded guess — BR-55's own text explicitly leaves this
   open ("set by SaaS Admin... not fixed by this document"), which is
   exactly what D-75's just-built Rules & Specifications module is for.
   Required adding this branch on top of `claude/pr04-sovereign-rule-revision`
   (PR-04, not yet merged) rather than duplicating `SovereignRuleService`
   — a deliberate stacked-PR dependency, same pattern used earlier this
   session for BR-50/BR-54's migration-number collision.

**A real bug found by running the full regression suite, not just the
new test in isolation**: adding a 6th seeded rule to `SovereignRuleService`
broke `test:sovereignrule`'s own "exactly 5 wired rules" assertions —
caught and fixed by updating that test's expectations (and its teardown
loop, which already iterated `seedDefinitions()` generically and needed
no logic change, only its assertion count).

**Verified with a new `spark test:kyc`** (32 assertions): individual
questionnaire validation (missing fields rejected, malformed PAN
rejected, valid PAN uppercased, Aadhaar genuinely masked to only the
last 4 digits); organization questionnaire validation; address upsert
(re-registering the same type updates in place, confirmed still exactly
one row); banking details saved and IFSC uppercased; `submitForReview()`
genuinely blocked with missing documents, genuinely succeeds once
complete; manual compliance-flag verification records the real verifying
admin; dossier review genuinely rejects reviewing a non-submitted
dossier and rejects suspension with no reason; approve/suspend both
genuinely transition `kyc_status`, with the reason visible; BR-55's gate
genuinely blocks both an unverified AND a suspended party, and passes a
genuinely verified one; enhanced due diligence genuinely blocks a
high-value transaction, stamps `edd_required_at` once, and genuinely
passes the SAME transaction after SaaS Admin clearance; the EDD
threshold is confirmed read from the live Sovereign Rule module, not a
duplicated constant; at least one audit-log entry exists per distinct
KYC action type exercised.

Document upload itself (`isValid()` requires `is_uploaded_file()`,
never true outside a real PHP upload request) follows `test:media`'s own
established precedent — verified separately, below.

**Verified over real HTTP** against a live server: a fresh buyer
attempted to pledge EMD and create a Listing BEFORE completing KYC —
both genuinely blocked, redirected to `/kyc`, zero EMD holds created.
Completed the full onboarding: questionnaire, two real PDF document
uploads via `curl -F` (confirmed the file on disk under
`writable/kyc_vault/` contains zero recoverable plaintext — no `PDF`
magic bytes anywhere in the ciphertext), a Registered address, banking
details, submitted for review. Logged in as a real, TOTP-verified Super
Admin (same registration → `grant:super-admin` → real RFC 6238 TOTP
enrollment → isolated `/admin/login` flow as every other admin feature
this session) — the dossier appeared in the review queue, manually
verified PAN/Aadhaar/Bank compliance flags, downloaded and decrypted the
uploaded PAN document (byte-identical to the original upload, confirming
the full encrypt/store/decrypt round-trip is genuinely correct, not just
"doesn't crash"), approved the dossier. The SAME previously-blocked EMD
pledge then genuinely succeeded (a real ₹10,000 hold, 10% of a ₹1L
reserve). A second, ₹60L-reserve sale event's EMD pledge (₹6L, above the
₹5L EDD threshold) was genuinely blocked with the exact live threshold
in the error message, cleared by the Super Admin, then genuinely
succeeded (a real ₹6,00,000 hold). A second buyer's dossier was
suspended with a closed-list reason — genuinely visible on their own
`/kyc` page, and they remained genuinely blocked from pledging
afterward, confirming BR-55 blocks a SUSPENDED party, not just a
never-submitted one.

**Full regression on a genuinely freshly-migrated database (58
migrations from zero): 488 assertions across 28 engines, zero new
failures** — confirming the model/service-layer gate placement (not the
Model layer) was the correct call: every pre-existing `Test*` command
that creates EMD holds or listings directly continued passing unchanged.
Only pre-existing, unrelated gaps: `test:auditlog`'s known D-62 bug, and
`test:invoices` (missing `Dompdf` package in this sandbox).

**Not covered here (deliberately out of scope):** real NSDL/GSTN
registry verification and UIDAI Aadhaar tokenization remain manual SaaS
Admin actions pending real API access, per the project owner's explicit
decision above. Multi-tenant KYC nuances (should a Tenant Admin ever get
visibility into a specific applicant's dossier) were not addressed —
the Super Admin routing above covers the literal PR-15 gate requirement,
not a broader KYC-visibility redesign.

### D-77: Governing document replaced (BR-01–61/PR-01–36 → BR-01–66/PR-01–37); full re-audit

**Decision:** The project owner supplied a replacement of the platform's
single canonical governing document. Per its own "Status" line it
supersedes the prior version, the same way the prior version superseded
whatever came before it. Recorded verbatim in
`docs/source-documents/eBid_Hub_Unified_BR_PR.docx` (now a real binary
`.docx` — the prior file at that path was plain text saved with a
`.docx` extension; recoverable via `git log -p` on that path if ever
needed). `docs/source-documents/README.md` carries the full summary of
what changed.

**What's new:** BR-62–66 and PR-37 — a Tenant API Access module letting
a Tenant integrate its own systems as an alternative to the portal UI,
governed by the exact same approval/lifecycle/visibility rules as a
portal submission (BR-13/14/16, no API-side approval bypass), OAuth2
client-credentials through the existing Auth0 relationship, hard-scoped
per-tenant at token issuance. The Tech Stack section is substantially
more decided: **SabPaisa** is now named as the selected payment gateway
(was TBD) — directly relevant to BR-52. KYC verification is explicitly
specified as **manual, no automated vendor** — confirming D-76's
approach was exactly right, not a one-off workaround. Two genuinely new,
unbuilt tech-stack requirements: server-time integrity (NTP sync +
drift alerting) and an independent third-party security audit before
go-live (organizational, not a code task). Every already-built numeric
rule (BR-27's 10% EMD, BR-43's 150% ceiling, BR-49's ₹10L threshold,
BR-38's shadow-ban thresholds) was spot-checked unchanged against the
new text.

**A full, systematic re-audit followed** — every one of the 66 BRs and
37 PRs checked for real code coverage (`grep -rl "BR-XX\b" app/`), not
assumed from any prior audit's claims, the same method D-43/D-64/D-73's
passes used. This surfaced **six real, previously-unflagged gaps that
pre-date this document swap** — genuine holes in the original BR-01–61
range that three prior audit passes (D-43, D-64, D-73) all missed:

1. **BR-15** (Super Admin non-participation) — zero enforcement anywhere;
   nothing blocks a Super Admin from bidding, listing, or participating.
2. **BR-07** (category scope restriction) — the listing `category` field
   is unrestricted free text; the platform's own permitted-categories
   closed list is never enforced. The one file citing "BR-07" in the
   codebase turned out to be a mislabeled comment on an unrelated field.
3. **BR-19/PR-16** (compliance-lockout cascade) — BR-19's own text
   promises automated cross-role lockout when one role's compliance flag
   is revoked; no such cascade exists.
4. **BR-32** (per-listing buyer fee override) — only a tenant-wide
   default fee exists; BR-32's actual text requires adjusting the fee
   "on any active listing," implying per-listing granularity.
5. **BR-41** (seller rating pre-bid) — shown in the `browse.php`
   discovery grid but missing from `listing/show.php`, the actual page a
   buyer bids from.
6. **PR-08** (Tenant Admin promotion UI) — no Super Admin web UI exists;
   only a CLI command (`grant:tenant-admin`), already honestly
   self-flagged in that command's own comment as an interim stand-in.
   BR-44's auto-demote-prior-admin logic it depends on genuinely exists
   inside that command — only the web UI itself is missing.

**Two items double-checked and confirmed already built**, correcting an
initial misreading of the new Tech Stack section as flagging fresh gaps:
audit-log DB-permission hardening (§3.6) — genuinely enforced via a real
`REVOKE UPDATE, DELETE, TRUNCATE` at the database layer in migration
000028, not just an application-layer convention — and BR-08's 0.5%
SaaS fee, genuinely computed in `EmdService` at both forfeiture and
settlement, just never tagged with a "BR-08" comment (which is why the
coverage grep initially read zero).

**Two items confirmed satisfied by design, not gaps:** BR-30 (Express
has no inspection window by not building one, matching the rule; Easy/
Buy-Now's grace window is BR-14's already-built mechanism BR-30
delegates to) and BR-37 (ratings live on `party`, not any tenant-scoped
table — portable by construction).

**Full open-items list is now ten**, replacing the prior "two items"
bottom line in `docs/BR_PR_AUDIT.md` — see that document's own "Update —
full re-audit" section for the complete, current list with fix-size
estimates. Five of the six newly-found gaps (BR-15, BR-07, BR-19/PR-16,
BR-41, PR-08) are each small, bounded fixes; BR-32 is small-to-medium;
BR-62–66/PR-37 (Tenant API) is the only large item with no external
blocker.

### D-78: BR-41 — Seller Rating Visibility Pre-Bid

**Decision:** D-77's re-audit found `seller_star_rating` shown in the
`browse.php` discovery grid but genuinely absent from
`listing/show.php` — the actual page a buyer views and bids from. An
older audit pass (D-43) had claimed this was "confirmed correctly
built"; re-verified directly against the current code before touching
anything, and that claim no longer held (`ListingController::show()`'s
`findActiveById()` call does no join and never fetched the seller's
rating at all). BR-41's exact text: "Even in fully anonymous, price-only
auction formats (Easy, Express), the seller's own sellerStarRating
remains visible to all bidding buyers throughout the live event" — and
its own rationale is explicit that this is not a BR-16 anonymity breach,
since it exposes only the seller's own public reputation number, never
their identity.

Fixed by fetching the seller's `seller_star_rating` in
`ListingController::show()` (one `PartyModel::find()` call) and
rendering it next to the Lot ID/Media line — no seller name, mobile, or
any other identifying field passed to the view, keeping the double-blind
identity design intact while satisfying BR-41's actual requirement.

**Verified over real HTTP**: a real tenant, seller (`seller_star_rating
= 4.3`), and active listing inserted directly; `GET
/listings/{id}` on a live server genuinely renders `★ 4.3` next to the
Lot ID line — confirmed in the raw response body, not just code review.

**Full regression on a freshly-migrated database (58 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap.

### D-79: BR-07 — Salvage Asset Categorization & Scope Restriction

**Decision:** D-77's re-audit found the listing `category` field was
completely unrestricted free text — nothing stopped listing "New iPhone
15 Pro Max" or any other new retail-consumer good, which BR-07
explicitly prohibits. The one file in the codebase citing "BR-07" turned
out to be a mislabeled comment on `tenant_create.php`'s Tenant Class
field, unrelated to listing categorization — genuinely zero enforcement
existed anywhere.

Added `ListingLifecycleService::PERMITTED_CATEGORIES`, the exact 8-item
closed list from BR-07's own text (Salvaged Claims Goods, Second-Hand/
Used Goods, Abandoned Goods, Antiques, Repossessed Banking Assets,
Industrial/Commercial Surplus, Custom/Confiscated Goods, Lost-and-Found
Inventories) — matching the established `REJECTION_REASONS` closed-list
pattern already used in the same service. `listing/create.php`'s
category field is now a `<select>` sourced from that list; both
`ListingController::createSubmit()` and `editSubmit()` validate the
posted value server-side against it — the dropdown constrains the
common path, but a raw POST could otherwise bypass client-side-only
validation, same reasoning as every other closed-list check in this
codebase.

**Verified over real HTTP**: a real KYC-verified, tenant-approved seller
attempted to create a listing with category "New iPhone 15 Pro Max" —
genuinely rejected with a BR-07 error, zero listing rows created.
The identical seller/tenant then submitted "Industrial/Commercial
Surplus" — genuinely succeeded, a real listing row created with that
exact category. Confirmed the create form's `<select>` renders all 8
permitted categories.

**Full regression on a freshly-migrated database (58 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap.

### D-80: BR-15 — Sovereign Isolation, Super Admin Non-Participation

**Decision:** D-77's re-audit found zero enforcement anywhere for BR-15:
"strictly forbidden from acting as Buyer, Seller, Tenant Admin,
Inspector, or any other platform participant, and structurally barred
from listing assets, attaching Sale Events, or placing bids/offers
under any circumstance." `AuthorizationService::isSuperAdmin()` already
existed and was used elsewhere for authorization, but nothing anywhere
checked it as a *block* on participation.

Added the check at the actual choke points rather than duplicating it
per format:
- `AuthorizationService::hasConflictOfInterest()` — already the shared
  gate `BiddingService::placeBid()`, `OfferService::submit()`, and
  `ExpressAuctionService::pledgeReserve()` all call for BR-21/22's
  conflict-of-interest check. One addition covers all three real
  bid/offer/pledge actions.
- `TenderBiddingService::placeBid()` and `TenderService::registerInterest()`
  — Tender doesn't go through `hasConflictOfInterest()` at all (it has
  its own seller-granted-eligibility gate), so these needed their own
  explicit checks.
- `ListingController::createSubmit()` — "listing assets."
- `SaleEventController::createSubmit()` — "attaching Sale Events."

**A real gap found during verification, not just in the original
audit**: `hasConflictOfInterest()` covers the actual bid/offer/pledge
*action*, but a real HTTP test showed the EMD *pledge* step
(`BidController`/`OfferController::devFundEmd`, `EmdConsentController::confirm`
— the same three entry points BR-55's KYC gate already lives at) never
calls it at all, so a Super Admin could still have EMD held in escrow
even though they could never complete an actual bid. Fixed by adding
the same `isSuperAdmin()` check directly at those three entry points,
ahead of the EMD hold being created — not just at the eventual bid.

**Not covered here**: BR-15 also names Tenant Admin and Inspector roles
— these are role *assignments* (who a Super Admin/seller chooses to
designate), not a live transaction a Super Admin initiates themselves,
and are out of scope for this pass; `grant:tenant-admin` and the
inspector-mobile binding on listing creation don't currently check
for a Super Admin party either, which would be a reasonable follow-up.

**Verified over real HTTP**: a real party granted `super_admin` via
`spark grant:super-admin`, logged in through the normal mobile/mPIN
flow. Confirmed genuinely blocked, each with zero rows created: pledging
EMD directly (`dev-fund-emd`, both formats), placing an actual bid,
creating a listing, and attaching a Sale Event to an existing upcoming
listing — each with the exact BR-15 error message shown back. Confirmed
a normal, non-Super-Admin buyer pledging EMD on the same sale event
still succeeds unaffected (a real ₹10,000 hold created).

**Full regression on a freshly-migrated database (58 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap.

### D-81: PR-08 — Tenant Admin Promotion Web UI

**Decision:** D-77's re-audit confirmed `grant:tenant-admin` was still
CLI-only — the command's own comment self-flagged this ("No Super
Admin panel exists yet"). PR-08 requires this be a Super Admin web
action, not an operator running a spark command. The underlying
`PartyRoleModel::promoteTenantAdmin()` logic (including BR-44's
auto-demotion of whoever previously held the role for that tenant) was
already correct and unit-tested via the CLI path — this pass wraps it
in a web UI rather than rewriting it.

**Where:** added a "Promote to Tenant Admin" form directly to the
existing Super Admin user-detail page (`/admin/users/{id}`,
`UserController::detail()`), next to the Roles list already shown
there — a tenant `<select>` plus a Grant button, posting to the new
`UserController::promoteTenantAdmin()` action
(`POST /admin/users/{id}/promote-tenant-admin`, `superAdmin`-filtered,
same access boundary as every other admin surface). Chose the existing
user-detail page over a new standalone page since the Super Admin is
already looking at the specific party being promoted and their current
roles right there — a dedicated new page would just duplicate that
lookup.

**Verified over real HTTP**, not just code review: registered two real
parties end-to-end (mobile → OTP → mPIN), granted one `super_admin` via
`spark grant:super-admin`, then completed **real TOTP enrollment**
(`/admin/setup-totp`, RFC 6238 code computed directly from
`TotpService::generateCode()` via reflection) and logged in through the
isolated `/admin/login` path — the same real security boundary
`SuperAdminFilter` checks, not a session shortcut. Seeded a tenant with
an existing Tenant Admin, then submitted the real promote form for the
second registered party:
- The prior Tenant Admin's `party_role` row is genuinely `revoked_at`-stamped (BR-44), the new one inserted active — confirmed directly in Postgres, not assumed.
- `admin.tenant_admin_granted` audit-log entry recorded with the correct actor, tenant, and `demotedPreviousAdminId`.
- The user-detail page reflects the new role on next load.
- An invalid/missing `tenant_id` is rejected with a flash error, no role change.
- A regular (non-Super-Admin) session hitting `/admin/users` is redirected to `/admin/login`, confirming the existing `superAdmin` filter still gates the new route correctly.

**Full regression on a freshly-migrated database (58 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap and
`test:invoices`'s missing `Dompdf` package (both unrelated to this
change).

### D-82: BR-32 — Per-Listing Buyer Fee Override

**Decision:** D-77's re-audit found `buyer_fee_percent` only existed
on the `tenant` table — a Tenant Admin could change the blanket
tenant-wide default, but BR-32's actual text is "adjust the buyer's
transaction fee **on any active listing**," implying per-listing
granularity distinct from the tenant default. No such override
existed anywhere.

**What's built:** a new nullable `listing.buyer_fee_percent_override`
column (migration 000059). When set, it takes precedence over
`tenant.buyer_fee_percent` at the two real places a buyer fee actually
gets applied to money:
- `SettlementService::checkCompletion()` — the successful-sale fee split (tenant/SaaS/buyer-refund) via `EmdService::calculateSettlementFee()`.
- `DisputeService::executeRuling()`'s `order_forfeiture` outcome — the same fee rate feeds `EmdService::calculateForfeitureAllocation()`'s split, so a forfeiture on the same listing doesn't silently fall back to a different (stale) rate than a completed sale would have used.

(`CascadeService::forfeitHold()` also computes a forfeiture allocation
from a `buyer_fee_percent`, but `processDefault()` — its only entry
point — is never actually called from any production code path today,
only from test commands with a hardcoded rate passed directly; the
scheduler only calls `initiateCascade()`. This is a genuine pre-existing
gap in BR-28's automatic-default wiring, not something this change
touches — left as-is rather than silently patched to look consistent
with dead code.)

**Where:** a "Buyer Fee Override" form on the listing detail page
(`listing/show.php`), shown when `status === 'active'` (matching
BR-32's "any active listing" wording exactly), gated by the existing
`tenantAdmin:listing` route filter — same access boundary as
approve/reject. `POST /listings/{id}/fee-override`
(`ListingController::updateFeeOverride()`): a blank value clears the
override back to the tenant default; 0–100 validated; both actions are
audit-logged (`listing.buyer_fee_override_set` /
`listing.buyer_fee_override_cleared`).

**Verified over real HTTP**, not just code review: fixtured a tenant
(5% default), a listing with a real Tenant Admin session setting a
2% override via the actual form endpoint, then drove a real
Buy-Now settlement to completion through all four real
`/settlements/{id}/...` confirm/rate endpoints (seller NOC, buyer NOC,
both ratings) as two real logged-in parties. Confirmed in Postgres the
resulting `emd_hold` fee split used the listing's 2% override
(tenant amount ₹1,425, SaaS ₹475, buyer refund ₹8,100 on a ₹95,000
sale) — not the tenant's 5% default (which would have been ₹4,275 /
₹475 / ₹5,250). A control listing with no override on the same tenant
correctly settled at the 5% default, confirming the fallback still
works. Also confirmed: an out-of-range value (150%) is rejected with
no change; clearing the override renders the "tenant default" label
again; both actions produce the expected audit-log entry.

**Full regression on a freshly-migrated database (59 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap and
`test:invoices`'s missing `Dompdf` package. `test:settlement` and
`test:dispute` — the two suites exercising the exact code paths
touched here — both still pass in full.

### D-83: BR-19/PR-16 — Compliance-Lockout Cascade

**Decision:** D-77's re-audit found BR-19's own rationale text
("supports automated cross-role lockout if a compliance flag is
revoked") and PR-16's operational sequence ("If master KYC status is
suspended or a compliance flag is revoked: an automatic global lockout
cascades to every role held by that Party") had zero implementation —
each role's suspension/delisting was handled entirely independently.
Re-extracted the exact text directly from the source `.docx` (via the
zip+XML paragraph extraction technique used for the document swap in
D-77, since it's the only reliable way to read this file) rather than
working from the audit summary alone, to confirm the precise trigger
wording before scoping the fix.

**A real gap this closes, not just a symbolic one:** before this,
`party_role`'s `tenant_admin`/`seller` role checks
(`hasActiveRole()`-based, used by `TenantAdminFilter` and
`SellerApplicationService::isApprovedSeller()`) were never tied to
`kyc_status` or seller-delisting at all — a Tenant Admin whose KYC got
suspended, or a seller confirmed-fraud-delisted, would keep every
other role's access completely intact. (Buyer participation itself was
already effectively gated by `KycService::requireVerifiedKyc()`
checking `kyc_status === 'verified'` directly, so KYC suspension
already blocked bidding/EMD/listing on its own — the missing piece was
specifically the *other* roles' own independent access checks.)

**What's built:** `ComplianceLockoutService::cascadeLockout()` —
revokes every one of a Party's active `party_role` rows
(`revoked_at`), logs one `party.compliance_lockout_cascaded` audit
entry listing every role/tenant locked out. Wired into the two real,
already-reachable trigger points that exist in this codebase today:
- `KycService::reviewDossier()`'s suspend branch (master KYC status suspended).
- `RatingService::delistSellerForFraud()` (a confirmed-fraud finding is a compliance-flag revocation in substance, matching the audit doc's own "seller delisted for fraud" example).

**Not covered here, and explicitly flagged rather than silently
assumed away:** a dedicated admin action to revoke an *individual*
compliance flag (PAN/GSTIN/Aadhaar/bank/email — the counterpart to the
existing `KycService::verifyComplianceFlag()`) doesn't exist anywhere
in this codebase. That specific literal trigger wording in PR-16
therefore stays dormant until such a revoke action is built — the
cascade service itself is ready and correct, it simply has no third
caller yet. Same honest-gap treatment as `CascadeService::forfeitHold()`
noted in D-82, whose only entry point is also never called from any
production path today.

**Verified over real HTTP**, not just code review: fixtured a Party
holding both `tenant_admin` (Tenant X) and `seller` (Tenant Y) roles
simultaneously, with KYC status `submitted`. A real, TOTP-verified
Super Admin session issued a genuine `POST /admin/kyc/{id}/decide`
rejection — confirmed in Postgres both `party_role` rows now carry a
`revoked_at` timestamp and the audit-log entry lists both. Then
confirmed the lockout has real teeth, not just a DB flag: the same
party's later `POST /listings/{id}/approve` on their former Tenant X
listing now genuinely returns 403 with the existing BR-09 message,
where it would have succeeded before. Separately, fixtured a second
Party holding `seller` (Tenant X) and `tenant_admin` (Tenant Z), then
issued a real `POST /admin/delist-seller` for confirmed fraud —
confirmed both of *that* party's roles were also cascaded, with the
correct reason text in the audit payload. A control Super Admin party
untouched by either trigger was confirmed to have its own role
unaffected.

**Full regression on a freshly-migrated database (59 migrations from
zero): 488 assertions across 27 runnable engines, zero new failures** —
only the pre-existing, unrelated `test:auditlog`/D-62 gap and
`test:invoices`'s missing `Dompdf` package. `test:kyc` (32 assertions,
exercises `reviewDossier()` directly) and `test:rating`/`test:selleraudit`
(exercise `delistSellerForFraud()`-adjacent paths) all still pass in
full.

### D-84: Server Time Integrity (Tech Stack §3.10)

**Decision:** the new document's Tech Stack §3.10 requires: "All
auction timing... is computed against a server clock synced to NTP
against a verified time source, checked continuously. Any drift or
manual clock adjustment beyond a defined tolerance triggers an
automated alert to the Super Admin and is itself logged as an audit
event." Nothing in this codebase did any of this.

**Scope split, stated up front rather than glossed over:** actually
keeping the OS clock synced to NTP (running an `ntpd`/`chrony` daemon)
is a deployment/infrastructure concern — no PHP application process can
force its own host's system clock to sync. What's buildable at the
application layer, and what this closes, is the other half: querying
an authoritative external time source, computing drift against this
server's own clock, and raising an alert + audit event when it exceeds
tolerance.

**A real, confirmed sandbox constraint, not assumed:** this development
environment's outbound network policy blocks both raw UDP (NTP is
UDP/123) and arbitrary HTTPS egress — confirmed directly by testing a
real UDP connection to `pool.ntp.org:123` (timed out, no response) and
a real HTTPS request to a public time API (403, the proxy's own status
endpoint showed `"connect_rejected"` / `"policy denial"` for both
hosts), not inferred. This is the same category of external-dependency
block as BR-46 (Gemini API key) and BR-52 (SabPaisa credentials), just
for network-policy reasons instead of missing credentials.

**What's built despite that constraint — real, not stubbed:**
- `ServerTimeIntegrityService::querySntp()` — a genuine minimal SNTP client (RFC 4330 client mode), real UDP socket I/O and real NTP wire-format parsing (Transmit Timestamp field, NTP-to-Unix epoch conversion). No external vendor/account needed, the same category as `TotpService`'s real RFC 6238 implementation replacing Auth0.
- `runCheck()` — computes drift, compares against a live, Super-Admin-editable tolerance (`SovereignRuleService`'s new `TechStack-3.10.server_time_drift_tolerance_seconds` rule, default 5.0s — §3.10 itself leaves the figure open, "a defined tolerance," same honestly-flagged-default pattern as `SettlementService::STALL_THRESHOLD_DAYS`), records a `server_time_check` row every run, and logs a `server_time.drift_alert` audit event (actor `null`, system-triggered) when reachable and over tolerance.
- Wired into `SchedulerService::runAll()` — the same recurring cron sweep every other time-based mechanic in this codebase already runs through, satisfying "checked continuously."
- Surfaced on the existing Super Admin Alerts page (`/admin/alerts`) alongside every other open-item queue, with a real `acknowledge` action (`POST /admin/alerts/server-time-drift/{id}/acknowledge`) that clears it and logs `server_time.drift_alert_acknowledged`.

**A real bug found and fixed during verification, not just described:**
Postgres/CI4 returns boolean columns as the strings `"t"`/`"f"`, not
native PHP `bool` — both are PHP-truthy, so `SchedulerService`'s
`!$check['within_tolerance']` would have silently misfired (treating
every check, drifted or not, as alert-worthy) had it read the raw
row. Caught by `test:servertimecheck`'s own assertions, not by
inspection. Fixed by normalizing booleans once at the service boundary
(`ServerTimeIntegrityService::normalizeBooleans()`), so every caller —
scheduler, controller, tests — gets real PHP booleans without needing
to know the driver's quirk.

**Verified two ways, neither fabricated:**
1. **Real SNTP protocol round trip**, since public NTP is unreachable here: a genuine, protocol-correct local SNTP responder (real UDP server, real 48-byte NTP packet construction) stands in for a public NTP host in `test:servertimecheck` — this exercises the exact same socket/parsing/drift-math code path that would run against a real `pool.ntp.org` in an unrestricted deployment, just pointed at 127.0.0.1. Confirmed: an unreachable host is handled honestly (no fabricated drift figure); a same-instant responder produces genuine near-zero drift, correctly within tolerance, no alert; a responder embedding a timestamp 200+ seconds off produces genuine measured drift exceeding tolerance, a real audit-log entry referencing the real check row, and appears in the unacknowledged-alerts list until acknowledged.
2. **Real HTTP, real Super Admin session**: a genuine TOTP-verified Super Admin login, a real drifted check inserted via the real service, confirmed rendering on the live `/admin/alerts` page ("Server Time Drift Alerts (1)"), then a real `POST` to the acknowledge endpoint — confirmed the count drops to 0 on the next real page load and the DB row's `acknowledged_by_party_id` matches the real logged-in Super Admin.

**Full regression on a freshly-migrated database (60 migrations from
zero, 28 runnable engines including the new `test:servertimecheck`):
zero new failures** — only the pre-existing `test:auditlog`/D-62 gap
and `test:invoices`'s missing `Dompdf`. `test:sovereignrule` updated
for the 7th wired rule (was 6) and still passes in full;
`test:scheduler` (exercises `runAll()` directly) unaffected.

### D-85: Fifth Full Re-Audit — One New Gap (BR-31), Six Confirmed Closed

**Decision:** at the project owner's request, re-audited from scratch
against the governing document — re-extracted directly from the current
`.docx`, confirmed byte-for-byte identical to the copy D-77 recorded
(`diff`, not assumed) — rather than trusting this session's own prior
findings. Every one of 66 BRs and 37 PRs checked for real code coverage
against the current, freshly-merged `main`.

**Confirmed genuinely closed, not just trusted from PR descriptions**:
BR-15, BR-07, BR-19/PR-16, BR-32, BR-41, PR-08, and Server Time
Integrity (§3.10) — spot-checked each one's real implementation
artifact directly in the merged codebase (e.g. `grep -rl "BR-15: the
Super Admin holds" app/`, `ls app/Libraries/ComplianceLockoutService.php`).
All present and correct. BR-62–66/PR-37 confirmed still genuinely zero
code anywhere — still open, unchanged.

**One new gap found, missed by four prior passes (D-43, D-64, D-73,
D-77) and by this session's own BR-32 work**: BR-31 states the buyer's
transaction fee is adjustable "within a fixed band: a floor of 0.5%...
and a ceiling of 5%... which may never be exceeded." Neither
`TenantController::createSubmit()`/`editSubmit()` (the tenant-wide
default) nor `TenantModel` validates the posted `buyer_fee_percent`
against this band — currently settable to any value at all, including
0%, negative, or far above 5%. The BR-32 per-listing override built
this session (`ListingController::updateFeeOverride()`) has the same
gap: it validates 0–100 (a basic sanity bound against
`EmdService::calculateSettlementFee()` throwing on a negative refund)
but not BR-31's tighter 0.5–5 band. Sellers already pay a genuine,
verified 0% — no fee-deduction code references a seller fee anywhere
— so that half of BR-31 is satisfied by omission; only the band
enforcement is missing.

**Also spot-checked** (every item with a suspiciously low/zero `grep`
hit count that wasn't an already-explained case): BR-20 (Super Admin
credential isolation — real, substantive re-enrollment gate), BR-49/
PR-27 (High-Value Disposal — a genuine `high_value_disposal_record`
table insert, not just a threshold check), BR-45 (photo count 5–50 —
real `MIN_PHOTOS`/`MAX_PHOTOS` consts enforced). All confirmed solid.

See `docs/BR_PR_AUDIT.md`'s "Update — fifth full pass" section for the
complete write-up and the current four-item bottom line: BR-31 (small,
no blocker), BR-62–66/PR-37 (large, no blocker), BR-46/BR-52 (both
still externally blocked).

### D-86: Pricing Page (TradeSphereX) Recorded and Wired In

**Decision:** the project owner provided a complete, ready-made
standalone pricing page — tenant subscription tiers (CoCo Starter/TSX
Launch/TSX Growth/TSX Enterprise), a live Success Fee calculator, and a
full feature-comparison table — under the "TradeSphereX" brand (by
ADWITIX). Its role terminology maps 1:1 onto this platform's own roles:
Custodian = Super Admin (the same BR-15 non-participatory role),
TSX Master = Tenant Admin, Market Maker = Seller, Trader = Buyer. Not a
different product; a marketing/pricing skin for the same platform.

**What's built:** served verbatim, not re-themed into `layouts/main` —
the page is a complete, self-contained document (own fonts, styles,
and calculator script) handed over as a finished artifact, and altering
it wasn't asked for. Canonical copy at `public/pricing.html` (also
directly reachable there, CI4's static webroot, same as
`robots.txt`/`favicon.ico`); a clean `/pricing` route added via a
minimal `PricingController` that reads and outputs that same file
verbatim, matching the site's existing clean-URL convention (`/terms`,
`/privacy`, `/fees`). A durable provenance copy also kept at
`docs/source-documents/eBid_Hub_Pricing_TradeSphereX.html`, matching
this repo's established pattern for source documents.

**Site map updated**: a "Pricing" card added to the Trust & Support hub
(`/trust-support` — this codebase's actual page index, there being no
literal `sitemap.xml`), described distinctly from the existing "Fee &
Charges Schedule" card (`/fees`, buyer/seller transaction-fee mechanics)
to avoid confusion — this new page is about tenant *subscription*
pricing. A "Pricing" link also added to the site-wide footer nav
alongside Trust & Support/Terms/Privacy.

**Verified over real HTTP**: `GET /pricing` and `GET /pricing.html`
both return 200 with the page content byte-for-byte identical to the
source file (only difference: CI4's dev-mode debug-toolbar injection,
harmless and dev-only). `/trust-support` genuinely renders the new
card; the homepage's footer genuinely includes the new link.
`test:auth`/`test:browse` spot-checked clean (a pure additive
routing/docs change, no business logic touched — full 28-engine
regression not needed).

### D-87: Governing Document Replaced — `ADWITIX_Master.docx` (Documentation Only)

**Decision:** the project owner provided a new master document,
explicitly instructed as "the Bible of this project," replacing
`eBid_Hub_Unified_BR_PR.docx` (BR-01–66/PR-01–37) with
`ADWITIX_Master.docx` (BR-01–68/PR-01–37), which the document's own
Status line describes as "Fully reconciled — supersedes all prior
governance drafts, the separately-issued Consolidated Specification,
and all standalone Business Model documents." Read in full (all 1083
extracted paragraphs, via the zip+XML extraction technique established
in D-77, since it's the only reliable way to read this file) before
any action was taken. Recorded here per the project owner's explicit
authorization — this entry covers the doc swap only; no application
code was touched in this commit.

**What's genuinely new, not just reorganized:**
- **Section 5, a complete Business Model** — product tiers (CoCo Starter/Concierge, TSX Launch/Growth/Enterprise), a subscription discount ladder, storage/media allowances, professional services, and revenue-line priorities. This is the exact content `public/pricing.html` (D-86) already presents — confirms that page was built correctly against this same source, even though it arrived in a separate upload beforehand.
- **BR-67 (Branded Terminology Layer)** and **BR-68 (Visual Identity)** — formalize "TradeSphereX"/TSX branding and the color/typography system already used on the pricing page, as presentation-layer mappings over the existing technical role names, not a data-model rename.
- Tech Stack (Section 3), the Phased Roadmap (Section 4), and every BR/PR not named below are unchanged from the prior document — spot-checked directly (PR-08, PR-13, PR-16 all read identically to the prior version).

**The commission model is fundamentally rewritten — flagged here for
the project owner's explicit decision before any code changes, not
silently assumed.** The document's own Status line names six rewritten
items: BR-08, BR-09, BR-31 through BR-34, BR-56, BR-12, and PR-06/PR-32.
The old model — a flat 0.5% SaaS fee plus a tenant-adjustable 0.5%–5%
buyer-fee band (`tenant.buyer_fee_percent`, plus the per-listing
override this session added for BR-32, D-82, merged as PR #26) — is
replaced by:
- A single, platform-wide, **non-tenant-adjustable** declining Success Fee schedule by final sale value: ≤₹10L 2.00%, ₹10L–50L 1.50%, ₹50L–2Cr 1.00%, ₹2Cr–10Cr 0.75%, above ₹10Cr 0.50% (negotiable) — minimum ₹500+GST.
- A new **Fee Payer Election**, set by the Market Maker (seller) per Trading Session (Sale Event) before it opens and locked once bidding starts: Buyer-Pays (default, matching today's zero-seller-cost behavior) or Seller-Pays (the fee is deducted from the seller's proceeds instead, letting a seller match a 0%-buyer-premium competitor). No equivalent field exists anywhere in the current schema or UI.
- Section 5.4/5.8 describe the Success Fee as the platform's own revenue line, with no mention of a separate Tenant share — a real, substantive change from the old model's `tenantAmount`/`saasAmount` split (`EmdService::calculateSettlementFee()`/`calculateForfeitureAllocation()`), not just a rate adjustment.

**Directly affects already-shipped, tested code**, specifically:
`EmdService::calculateSettlementFee()`/`calculateForfeitureAllocation()`
(the tenant/SaaS split and flat-percent lookup), `SettlementService::
checkCompletion()` and `DisputeService::executeRuling()`'s
`order_forfeiture` path (both read `tenant.buyer_fee_percent` /
`listing.buyer_fee_percent_override`), `TenantController` (the
`buyer_fee_percent` field itself), `SaleEventController`/`SaleEventModel`
(no `fee_payer` field exists), and `InvoiceService`/BR-56 invoice
attribution (must follow the election, not always "buyer"). This also
means the per-listing buyer-fee override built this session (D-82,
merged PR #26) and the just-identified BR-31 band-validation gap
(D-85, PR #29, currently on hold per the project owner's instruction)
are **both superseded by this rewrite** — BR-31 no longer describes a
tenant-adjustable band with a floor/ceiling at all, so that specific
gap no longer applies to the new text; a different, larger rebuild
(the declining schedule + Fee Payer Election) is needed instead.

**Also noted, not yet resolved**: the document's own PR-13 (Sale Event
creation) and PR-22 (Settlement) operational-step lists were not
updated to mention capturing/applying Fee Payer Election explicitly —
PR-22 step 6 still reads "the system deducts commission/SaaS fee from
the held EMD," the old model's phrasing — while BR-31/32/33 elsewhere
in the same document are explicit that settlement now branches on the
election. Flagged as an internal inconsistency in the source document
itself, not resolved by assumption.

**Not yet done, deliberately**: no fee/settlement code was rewritten in
this commit. Given the scope (money-calculation logic across multiple
already-tested subsystems, a new schema field, and a genuinely new UI
flow for Fee Payer Election) and BR-01's own instruction to "halt and
raise clarifying questions rather than making unilateral assumptions,"
this is surfaced to the project owner as its own scoped follow-up
rather than built unprompted.

### D-88: Success Fee Rebuild, Fee Payer Election, and Monthly Tenant Billing — the D-87 Follow-Up, Built

D-87 flagged the commission-model rewrite as too large a change to
build unprompted and surfaced it back to the project owner. Discussed
first, per BR-01. Two things came out of that discussion, both
explicitly confirmed by the project owner before any code was written:

**1. A genuine mechanical gap in BR-32/33's own text, resolved by the
project owner's own proposed design.** BR-32 states that under
Seller-Pays "the Success Fee is instead deducted from the seller's
proceeds at the same settlement step," but BR-33 (same document) also
states "the buyer pays 100% of the sale value directly to the seller,
offline... the platform never touches this 100% value." Those two
statements are mechanically incompatible — a platform that never
touches the seller's proceeds cannot deduct a fee from them in real
time. Raised to the project owner directly ("Let's discuss that. What
is your question and your answer?"), who supplied the resolution
himself: since individual sellers are moving to CoCo TSX and everyone
else is institutional TSX, bill the Tenant a **monthly consolidated
invoice** instead of trying to deduct per-transaction from a proceeds
flow the platform structurally never sees. Confirmed as correct and
authorized to build ("yes both understanding are correct... build new
parameters. do the needful").

**2. Seller-Pays restricted to non-CoCo-Starter tenants.** A CoCo
Starter TSX has no ongoing subscription/billing relationship with
ADWITIX (Section 5.2 — free to join) to invoice against, so it has no
counterparty for the monthly bill above. Seller-Pays is therefore
gated to TSX Launch/Growth/Enterprise only; a CoCo Starter TSX only
ever runs Buyer-Pays. Confirmed by the same authorization.

**Built, both in code and in `ADWITIX_Master.docx` itself** (the
project owner's explicit instruction: "necessary action to be taken
wherever required in master and the code"):

- **`ADWITIX_Master.docx` amended** (via the `docx` skill, direct XML
  edit + XSD validation, not regenerated) — BR-31's statement, BR-32's
  statement and rationale, and the Section 5.4 Fee Payer Election
  paragraph all rewritten to (a) state the monthly Tenant-invoice
  mechanism explicitly instead of the mechanically-impossible
  "deducted from seller's proceeds" phrasing, and (b) narrow
  Seller-Pays to paid tiers only, replacing "available to every Tenant
  on every tier, including CoCo." Verified by extracting the amended
  paragraphs back out and reading them, and by the skill's own XSD
  schema validator (paragraph count unchanged, 1131 → 1131).
- **Migration `2026-01-01-000061_SuccessFeeAndFeePayerElection`** —
  adds `tenant.subscription_tier` (coco_starter/tsx_launch/tsx_growth/
  tsx_enterprise; distinct from the pre-existing, unrelated
  `tenant_class` enum), drops `tenant.buyer_fee_percent`/
  `saas_fee_percent` and `listing.buyer_fee_percent_override` (BR-32's
  old per-listing override is fully obsolete under a fixed
  platform-wide schedule), adds `sale_event.fee_payer`
  (buyer_pays/seller_pays, default buyer_pays), and creates
  `tenant_fee_ledger` (one row per Seller-Pays settlement, unbilled →
  billed) + `tenant_monthly_invoice` (the consolidated bill), FK'd
  together. Also extends `invoice_type` with `platform_to_buyer`/
  `platform_to_seller` (old `tenant_to_buyer`/`saas_to_tenant` kept for
  historical rows — Postgres can't drop enum values).
- **`EmdService::calculateSuccessFee()`** (new) — the fixed bracket
  schedule (2.00%/1.50%/1.00%/0.75%/0.50% by final sale value, ₹500
  minimum), matching `public/pricing.html`'s own calculator exactly
  (same boundaries, same flat-not-marginal per-bracket rate, same
  floor). The >₹10Cr band's "negotiable" note in the master doc is
  treated as an off-platform/manual arrangement, not an editable rate
  — consistent with BR-09's "Tenant Admin no longer adjusts the fee
  rate itself." `calculateSettlementFee()` rewritten for Buyer-Pays
  only (drops the tenant/SaaS split — the fee is 100% platform revenue
  now). `calculateForfeitureAllocation()` rewritten to take the
  defaulting party's own bid/session value (not the smaller 10% EMD
  amount) for the bracket lookup, and drops the `feePayer` distinction
  entirely — BR-32's own text confirms "the fee amount and the
  platform's revenue are identical either way," and a default has no
  seller proceeds to draw from regardless of election, so the fee
  always comes out of the forfeited EMD.
- **`SettlementService::checkCompletion()`** — branches on
  `sale_event.fee_payer`. Buyer-Pays: unchanged shape, fee deducted
  from held EMD via `guardedSettle()`. Seller-Pays: EMD released in
  full via `guardedRelease()` (the same BR-50 high-value review gate
  applies to both paths), and a `tenant_fee_ledger` entry recorded via
  the new `TenantBillingService::recordUnbilledFee()` — only when the
  release actually happened synchronously, mirroring the existing
  `$feeWasSettled` gating pattern so a fee isn't recorded for a release
  still pending Tenant/SaaS Admin review.
- **New `TenantBillingService`** — `recordUnbilledFee()`,
  `generateMonthlyInvoices()` (consolidates per-Tenant unbilled ledger
  entries into one GST invoice per calendar month, skips CoCo Starter
  tenants defensively even though they should never have entries),
  `markInvoicePaid()` (manual SaaS Admin action — no automated
  dunning/suspension for a nonpaying Tenant exists, intentionally out
  of scope for this build and flagged as such, not silently omitted).
  Wired into `SchedulerService::runAll()`, gated to the 1st of the
  month so a frequent cron sweep doesn't re-scan needlessly.
- **`InvoiceService::generateForSettlement()`** rewritten — one
  `platform_to_buyer`/`platform_to_seller` invoice per settlement
  (BR-56: issued to whichever party paid the fee), replacing the old
  two-invoice `tenant_to_buyer`/`saas_to_tenant` split that no longer
  has a real counterpart now that the Tenant doesn't share in the fee.
- **`DisputeService::executeRuling()`** (`order_forfeiture` branch) and
  **`CascadeService::forfeitHold()`/`processDefault()`** — updated for
  the new `calculateForfeitureAllocation()` signature; both now pass
  the actual sale/bid value (not just the held EMD) for the bracket
  lookup.
- **`TenantController`/`TenantModel`** — `subscription_tier` capture
  replaces `buyer_fee_percent` in create/edit. **`SaleEventController`/
  `SaleEventModel`** — `fee_payer` capture on Buy-Now/Express/Easy
  (never Tender, per BR-31's own exclusion), server-side rejecting
  `seller_pays` for a `coco_starter` tenant with a BR-32-cited error.
  **`ListingController::updateFeeOverride()`** and its route removed
  outright (obsolete). Views updated to match: `listing/show.php` (fee
  override UI removed, new tier-gated Fee Payer Election field added
  to the three non-tender attach forms via a shared partial), tenant
  admin create/view/list/dashboard (subscription tier select/display).
- **New Tenant Admin billing view** (`/tenants/{id}/billing`) and
  **SaaS Admin invoice list + mark-paid action**
  (`/admin/tenant-invoices`), both following this codebase's existing
  `PayoutReviewController` shape.
- **Nine test-fixture files** (`TestTenderReview`, `TestPhase3aAccounts`,
  `TestSellerAudit`, `TestBr35RatingEvents`, `TestSettlement`,
  `TestScheduler`, `TestDispute`, `TestCascade`, `TestInvoices`) had
  their stale `buyer_fee_percent` fixture key removed; `TestCascade`'s
  and `TestSettlement`'s hardcoded 5%-flat-fee math assertions
  rewritten for the new bracket schedule; new `test:successfee` added
  (bracket boundaries, a full Seller-Pays settlement end-to-end, the
  tenant_fee_ledger entry it produces, tier-gated monthly-invoice
  generation including the CoCo Starter defensive skip, idempotent
  re-run, and mark-paid).

**Real-HTTP verification, not just the CLI suite**: registered and
logged in a real seller party over HTTP (cookie-jar session, reading
the dev-mode OTP off the actual HTML response, same pattern as every
prior real-HTTP pass this session). Attempted `fee_payer=seller_pays`
on a CoCo Starter tenant's listing — rejected with the exact BR-32
error text, zero `sale_event` rows created. Same request against a
TSX Launch tenant's listing — succeeded, `sale_event.fee_payer` stored
as `seller_pays`, the listing page's Fee badge reads "Seller-Pays."
Also confirmed the Fee Payer Election field's disabled state and
tier-gating hint render correctly on the CoCo Starter listing's attach
forms, and that `/tenants/{id}/billing` renders real (empty, for a
brand-new tenant) ledger/invoice data with no errors under a real
tenant_admin `party_role` grant. `test:successfee` (25/25) plus the
full existing CLI suite re-run clean, with two pre-existing,
unrelated-to-this-build environment gaps noted rather than silently
worked around: `dompdf/dompdf` is declared in `composer.json` but not
present in `vendor/` in this sandbox (blocks only the PDF-rendering
assertion in `test:invoices`, not the invoice logic itself, which
passed in full), and `TestAuditLog`'s tamper-simulation step shells
out to a hardcoded `ebidhub_ci4` database name that doesn't match this
environment's `ebidhub` — both pre-date this build and are unrelated
to the Success Fee change.

**Superseded by this entry**: D-82 (the per-listing buyer-fee
override, BR-32 as it existed before the master-doc rewrite) and D-85
(the on-hold BR-31 buyer-fee-band validation gap, PR #29) — both
described a fee model this build fully replaces. PR #29 should be
closed or reconciled against this work rather than merged separately.

### D-89: BR-62-66/PR-37 Built — Tenant API Access Module

The last item on the audit's own bottom line with no external blocker
(docs/BR_PR_AUDIT.md). Built directly, per the user's explicit
instruction, following the exact re-audit finding: a whitelisted
Tenant can integrate its own systems with the platform as an
alternative to the portal UI, governed identically to a portal
submission — no privilege the portal doesn't already grant, none
bypassed (BR-62).

**BR-64 substitution, flagged explicitly, same pattern as
`TotpService`**: BR-64 names OAuth2 client-credentials "through the
platform's existing Auth0 relationship." Auth0 is a paid external
vendor requiring its own account — the same category of dependency as
the payment gateway/SMS provider (D-23), deferred the same way. Built
a real, self-hosted client-credentials flow instead
(`ApiCredentialService`): genuine random `client_id`/`client_secret`
issuance, bcrypt secret hashing, and a genuinely HMAC-signed
short-lived bearer token — not a fake stand-in. BR-64's "hard-scoped
to a single tenantId at the token-issuance level" requirement is real:
the tenantId is inside the signed payload, and every request re-checks
the underlying credential is still `active` in the DB, so a revoked
credential's outstanding tokens stop working immediately rather than
only at next refresh — verified directly (`test:tenantapi` and real
HTTP: revoked via the portal, the same still-unexpired token
immediately 401s).

**BR-66 tier gating, built on the D-88 `subscription_tier` field**:
CoCo Starter has no API access at all (enforced in `ApiAuthFilter`,
and again defensively in `TenantApiSettingsController::issueCredential`
so a CoCo Starter TSX can't even mint a credential); TSX Launch is
read-only; TSX Growth adds Lot push; TSX Enterprise adds Sale System
push. `TenantModel::hasApiAccess()`/`canPushListings()`/
`canPushSaleEvents()` are the single source of truth both the filter
and the controller actions check.

**BR-63 visibility parity**: a listing/sale-event outside the calling
credential's own tenant 404s, not 403s — a probing client learns
nothing about another tenant's ID space. Verified directly: an
Enterprise-tier token reading a Growth-tenant's listing ID gets
`not_found`; the Growth tenant's own token reading the same ID
succeeds.

**PR-37's own gap, flagged rather than silently resolved**: its
Operational Sequence says a pushed listing "enters PENDING_APPROVAL
(BR-13), identical to a portal submission" — but BR-11's own photo
requirement (5-50 photos, one marked primary) is what actually moves a
portal listing out of INVENTORY via `submitForApproval()`, and PR-37's
own step list has no media-push step at all. Resolved conservatively,
not by picking the shortcut: `TenantApiController::pushListing()`
creates the listing at INVENTORY, same as the portal, rather than
skipping BR-11's gate — BR-62's "bypasses none" principle taken
literally over PR-37's summary-level wording. Noted here as a real,
narrow gap in the source document's own text, the same category as
D-87's PR-13/PR-22 wording lag, not resolved by assumption.

**Built**:
- Migration `2026-01-01-000062_CreateTenantApiAccess` —
  `tenant_api_credential` (client_id/secret-hash/status), `tenant.
  webhook_url`/`webhook_signing_secret`, `tenant_webhook_delivery`
  (event log with bounded retry, the same "stage now, scheduler
  finalizes later" shape as `media_upload_job`/PR-09 and the BR-50
  payout cooling-off).
- `ApiCredentialService` (issuance/revocation/authenticate/
  validateToken) and `ApiAuthFilter` (Bearer token validation +
  baseline tier gate, wired as the `apiAuth` route filter alias).
- `TenantApiController` — `POST /api/v1/oauth/token`, `POST/GET
  /api/v1/listings[/{id}]`, `POST/GET /api/v1/listings/{id}/sale-events`
  and `/api/v1/sale-events/{id}` — reusing the exact same governance
  calls `ListingController`/`SaleEventController` use (BR-15 Super
  Admin exclusion, BR-55 KYC, BR-38 delisting/ceiling, BR-09 approved-
  seller, BR-07 category closed-list, BR-24 shipping validation, BR-60
  media waiver, BR-31/32 Fee Payer Election tier gating), not a parallel
  or looser rule set. Tender is excluded entirely, matching PR-37's own
  explicit exclusion.
- `TenantWebhookService` — fires `listing.approved`,
  `sale_event.created`, `listing.archived` (with `supersededBy`),
  `settlement.completed`, and `dispute.filed`, wired into the exact
  existing lifecycle methods (`ListingLifecycleService::approve()`/
  `approveSaleEvent()`/`requestMaterialEdit()`, `SettlementService::
  checkCompletion()`, `DisputeService::fileDispute()`) rather than a
  parallel event system. HMAC-signed (`X-TSX-Signature`) — not
  explicitly required by PR-37's text, added as a reasonable security
  default and flagged as such, the same category as this codebase's
  other unrequested-but-sensible defaults. Bounded retry (5 attempts,
  fixed 5-minute backoff — PR-37 specifies neither figure), wired into
  `SchedulerService::runAll()`.
- Tenant Admin API Access settings page (`/tenants/{id}/api-access`):
  issue/revoke credentials (secret shown once, standard OAuth2
  practice), set the webhook URL. New `test:tenantapi` (25/25:
  tier-gating helpers, the full OAuth2 flow including a genuinely
  rejected wrong secret and a genuinely tampered/rejected token,
  immediate-effect revocation, webhook delivery against a real
  unreachable address with real connection-failure logging, and the
  bounded-retry give-up path).

**Real-HTTP verification, not just the CLI suite**: issued real
credentials via the actual Tenant Admin settings page for TSX Launch/
Growth/Enterprise tenants (CoCo Starter correctly refused by the UI
itself); exchanged real OAuth2 tokens; confirmed Launch (read-only)
gets 403 `insufficient_tier` pushing a listing while Growth succeeds;
confirmed BR-07's category closed-list rejects an invalid category;
confirmed Growth gets 403 pushing a Sale System (Enterprise-only)
while Enterprise succeeds, including a real Seller-Pays Fee Payer
Election on the pushed sale event; confirmed cross-tenant `GET` 404s
while same-tenant `GET` succeeds (BR-63); approved a pushed listing
and its pushed Sale System through the real portal endpoints and
confirmed `listing.approved`/`sale_event.created` webhook deliveries
were genuinely logged (against a real unreachable address, so a real
connection failure, not a mock); revoked a credential through the
portal and confirmed its still-unexpired token 401s immediately.

Full CLI regression suite re-run clean (30/32 — the same two
pre-existing, unrelated `dompdf`/`ebidhub_ci4` environment gaps noted
in D-88, not new).

### D-90: `ADWITIX_Master.docx` Restructured — New Section 1 (Terminology), Full Renumbering

Raised directly by the project owner: BR-67 (Branded Terminology Layer)
had never actually been implemented anywhere in the live application —
checked directly (`grep` across `app/Views/`), only the four view
files written in D-88/D-89 use any branded term at all, and none of
them do so systematically. Confirmed this is a genuine miss, not a
documentation slip: five full audit passes (`docs/BR_PR_AUDIT.md`)
never once tracked BR-67 as a gap, because its own text ("does not
rename any entity, field, or role in the data model") reads as
satisfied by omission if only the data-model half of its requirement
is checked — the other half ("Front-end copy... render the branded
term") was never verified. That real application-side build is not
part of this entry — it's still outstanding, tracked separately.

This entry is the immediate, documentation-only fix the project owner
asked for first: a real, standalone Terminology section, prominent
like Technology Stack, positioned so a reader meets the vocabulary
before the Business Rules — not a code change.

**What changed, in `ADWITIX_Master.docx`:**

- **A new Section 1 (Terminology)**, inserted before the Business
  Rules. **Part A** is BR-67's technical-to-branded mapping table
  (Tenant→TSX, Tenant Admin→TSX Master, Seller→Market Maker, Buyer→
  Trader, Super Admin→Custodian, Listing→Lot, Sale Event→Trading
  Session), moved here verbatim from inside BR-67 — not duplicated,
  BR-67 (Section 2) now points to it instead of carrying its own copy,
  since it's still a real citable rule (it governs *when* each
  vocabulary applies, not just *what* the words are). **Part B** is a
  36-term plain-language glossary: the project owner pointed to
  `eBid_Hub_Terminology.jsx` — a real, pre-existing 30-term glossary
  (EMD, H1/H2/H3, Cascading Default, Star Rating, Crawl-Back, Standing
  Review, etc.) provided earlier in this project and already live as
  the front-end glossary page — transcribed faithfully into the
  document. Four new entries were added, flagged explicitly as an
  addition rather than blended silently into the transcription:
  **Success Fee**, **Fee Payer Election**, **Subscription Tier**, and
  **Tenant API Access** — real mechanics introduced by D-87/88/89,
  after that glossary was originally written, with no entry anywhere
  a reader could check.
- **Full renumbering, not a "Section 0" workaround.** The project
  owner explicitly asked for Terminology to be Section 1 itself, not
  appended as a zero-indexed section to avoid touching the existing
  numbers. Old Sections 1–5 (Business Rules, Process Workflows,
  Technology Stack, Phased Roadmap, Business Model) are now 2–6, and
  every one of their internal subsection numbers shifted with them
  (old 3.1–3.13 → 4.1–4.13, old 4.1–4.3 → 5.1–5.3, old 5.1–5.11 →
  6.1–6.11). Every internal cross-reference to a section number — "Section
  5.4," "Section 5.2," "Sections 1 through 4," etc. — was found and
  updated to match; the Income Tax Act's own "Section 194-O" (an
  external legal citation, not a cross-reference to this document) was
  deliberately left untouched, confirmed by scoping the remap to
  section numbers 1 through 5 only.

**How it was verified, not just asserted:** every "Section N" and
"N.M heading" pattern in the document was inventoried by direct
search before any edit (23 inline cross-references, 10 section
headers, 27 subsection headings), each edit was applied with an
assert-exactly-one-occurrence guard, and the full renumbered text was
re-extracted and read end-to-end afterward to confirm every reference
now points at the right section with no gaps or duplicates. The docx
skill's XSD schema validator passed clean (1131 → 1175 paragraphs, the
expected delta for the new heading/table/glossary content — the
mapping table's own paragraph count is unchanged, since it was moved,
not duplicated).

**Not done here, on purpose**: the actual live-application terminology
rollout (BR-67 rendered in the real portal UI) that this whole
discussion started from. That's the project owner's own next question
— tracked as its own build, not silently folded into this
documentation fix.

### D-91: Root `README.md` Was Stale — Fixed, Plus BR-67 Rollout Gap Now Tracked

The project owner quoted text describing `docs/source-documents/` as
still centered on the retired `eBid_Hub_Unified_BR_PR.docx`
(BR-01–61/PR-01–36) and asked whether the live codebase's source of
truth had silently diverged from `ADWITIX_Master.docx`. Checked
directly rather than assumed: `docs/source-documents/README.md` and
`docs/BR_PR_AUDIT.md` were both already correct (D-87/D-89/D-90 kept
them in sync as the work happened) — but the **root `README.md`**,
which nobody had touched since well before this session's work, still
carried the old doc name and BR/PR range verbatim. That's genuinely
where the quoted text traces back to — not a different project, not a
hallucination, an actual stale file in this repo.

While fixing it, the rest of the root README's factual claims were
checked against the real repository state rather than assumed correct
by association, and several more were found stale:

- **Migration count**: said 26, actually 62.
- **Test suite count**: said "254+ assertions across fifteen test
  suites," actually 32 permanent `test:*` commands — the Step 10
  deployment-verification list only named 15 of them; the other 17
  (KYC, AML, Standing Review, payout control, audit-log lockdown,
  Sovereign Rule, server-time integrity, Success Fee, Tenant API
  Access, etc.) were simply never added as the project grew.
- **"What this is" feature summary**: hadn't been updated since an
  early point in the project — missing KYC, AML, Standing Review,
  payout control, server-time integrity, the Sovereign Rule module,
  and the entire current commercial model (Success Fee schedule, Fee
  Payer Election, subscription tiers, Tenant API Access) — genuinely
  large chunks of what's actually built were invisible to a first-time
  reader of this file.
- **`main`/`dev` branch guidance was backwards**: the README claimed
  `main` was behind `dev`; checked directly (`git log
  origin/main..origin/dev`) — `dev` hasn't moved since PR #12 and is
  now an ancestor of `main`, not ahead of it. Also flagged: none of
  D-87 through D-90 (the ADWITIX doc replacement, the current
  commercial model, Tenant API Access, the Terminology restructuring)
  are in `main` or `dev` yet — all four sit on open, stacked PRs
  (#31→#32→#33→#34).

**Separately, a real gap surfaced by this same conversation and now
formally tracked**: BR-67 (Branded Terminology Layer)'s live-UI
rollout — confirmed missing in the exchange leading into D-90, but
never actually added to `docs/BR_PR_AUDIT.md`'s own gap list at the
time. Added now as the sole remaining item with no external blocker.

Fixed: root `README.md` (doc reference, feature summary, branch
guidance, migration count, full 32-command test list) and
`docs/BR_PR_AUDIT.md` (BR-67 rollout gap added to the bottom line).
`docs/source-documents/README.md` needed no changes — already correct.

### D-92: BR-67 Branded Terminology Layer — Built Into the Live App

D-91 tracked the gap; this closes it. BR-67's own text ("does not
rename any entity, field, or role in the data model") is a
presentation-only rule, so the build is a single new helper plus a
mechanical rollout across every view that renders one of the mapping
table's 7 technical terms as visible text — nothing in the database,
routes, PHP identifiers, or session keys changes.

**The helper**: `app/Helpers/terminology_helper.php` defines
`tsx_term(string $technical, bool $short = false, bool $plural =
false): string`, a single static map matching Section 1 Part A's
7-row table exactly (Tenant→TradeSphereX/TSX, Tenant Admin→TSX
Master/TSXM, Seller→Market Maker/MM, Buyer→Trader/TRD, Super
Admin→Custodian/CUS, Listing→Lot/LOT, Sale Event→Trading Session, no
short form). Autoloaded globally via `app/Config/Autoload.php`
(`$helpers = ['terminology']`) so every view can call it with no
per-controller wiring. An unmapped input (e.g. `tsx_term('Party')`,
or the platform's own name `eBid Hub`) passes through unchanged rather
than erroring — `eBid Hub` is deliberately out of scope here, since
it isn't one of BR-67's 7 mapped terms; it's a separate branding
question the project owner hasn't raised.

**Scope decided**: the same grep that flagged BR-67 originally
(`grep -rlE "\bTenant Admin\b|\bTenant\b|\bSeller\b|\bBuyer\b|\bSuper
Admin\b|\bListing\b|\bSale Event\b" app/Views/`) found 37 files.
`app/Views/layouts/main.php` — the one file on every page, and
conspicuously absent from that list — was checked separately and
confirmed to genuinely not use any of the 7 words as visible text (it
shows "eBid Hub" as the default un-branded platform name, and "Sell"/
"Marketplace"/"Browse" nav labels); no change was needed there.
`app/Views/admin/*` (17 files) — the platform operator's own console —
was included in the rollout, not treated as internal-only: BR-67 maps
Super Admin to Custodian just like the other 6 roles, and
`public/pricing.html` (D-86) already describes this role as
"Custodian" throughout its public marketing copy, so leaving the
operator's own console using the unbranded term would have been the
inconsistent choice, not the safe one.

**Rollout mechanics**: each of the 37 files was re-scanned
case-insensitively (`grep -niE "seller|buyer|listing|\btenant\b|tenant
admin|super admin|sale event"`) to also catch plurals and lowercase
mid-sentence uses the original case-sensitive grep missed, then every
occurrence that is genuinely rendered, user-visible text (headings,
labels, button text, table headers, status strings, alt text) was
replaced with a `tsx_term()` call, matching surrounding
capitalization (`strtolower()`/`strtoupper()` wrapped where the
original was lowercase or all-caps). Left untouched throughout: route
paths, PHP variable/array-key names (`$item['listing_id']`,
`tenant_id`, `seller_star_rating`), CSS/HTML attribute names, form
field `name=`/`id=` attributes, session keys, JS identifiers, and any
BR/PR-number comment — exactly the boundary BR-67's own text draws.
Two deliberate exclusions worth naming: `custodian_mobile` in
`listing/create.php` is a physical yard custodian's contact field, an
unrelated pre-existing use of the word, not the Super Admin role, and
was left alone; `SaaS Admin`, used in a handful of admin views as a
different literal string for what is likely the same role, was also
left alone — fixing that inconsistency would be a real but separate
cleanup, not part of applying BR-67's own 7-row map.

Given the size (37 files), the mechanical replacement pass was
delegated to three parallel background agents working disjoint file
sets, after the mapping, the helper signature, and every inclusion/
exclusion boundary above had already been decided — each diff was
then read and lint-checked (`php -l`) before being committed, not
merged unread.

**Verification**: all 33 `test:*` CLI suites re-run against a freshly
migrated database — 32 passed; `test:invoices` failed on a duplicate
tenant-subdomain insert that reproduces on the unmodified pre-BR-67
code too (confirmed via `git log` — neither `TestInvoices.php` nor
`TenantModel.php` has been touched by any BR-67 commit), so it's a
pre-existing test-command flake, not a regression, and is left
unfixed as out of scope for this build. A new `test:terminology` suite
(22 assertions) unit-tests the map itself, including the "does not
rename the data model" guarantee (`TenantModel`'s table name and
`SUBSCRIPTION_TIERS` constant are asserted to still be the real,
unbranded values). Real HTTP checks against a running `spark serve`
instance confirmed branded terms actually render: `/` shows "View
Lot," "Market Maker(s)," "Trader," "TSX Master," "Custodian"; `/browse`
shows "Browse All Lots" and "Market Maker rating"; `/admin/login`
shows "Custodian Login"; `eBid Hub` still renders correctly in the nav
brand slot, confirming it was correctly left alone.

`docs/BR_PR_AUDIT.md`'s bottom line updated to remove BR-67 — only
BR-46 (AI Pre-Audit, needs a Gemini API key) and BR-52 (Chargeback
Mitigation, needs real SabPaisa API credentials) remain, both blocked
on external credentials the project owner hasn't provided, not on any
further build work.

### D-93: Independent counter-audit against a fresh PDF export — three real findings, all fixed

The project owner supplied a freshly-exported PDF of `ADWITIX_Master.docx`
and asked for a genuine two-directional counter-check: (1) is everything
except the known external-dependency items (BR-46/BR-52) actually built,
and (2) is everything we've decided actually written back into the
document. Both directions were checked directly against code and text,
not by trusting this document's own prior "closed" claims.

**Method.** Extracted the PDF's full text (`pdftotext -layout`, 49
pages) and confirmed it's byte-for-byte the same document already in
the repo post-D-92 (the Status paragraph's "six contradictions... BR-08,
BR-09, BR-31 through BR-34, BR-56, BR-67, BR-12, PR-06/PR-32" line
matches the repo docx exactly) — no silent drift. Then ran `grep -rl
"BR-XX\b" app/` for all 68 BRs (same methodology as D-85/D-77's prior
passes) and cross-checked every zero/near-zero-hit result by hand.

**Direction 1 — is it built?** All 68 BRs have real code coverage
except BR-46/BR-52 (confirmed still genuinely zero implementation —
only a comparison comment in `ServerTimeIntegrityService.php`) and
BR-30/BR-37 (already-established satisfied-by-construction cases, not
new). Two things fell out that were neither external-blocked nor
previously tracked:

- **BR-65 (API Versioning Policy) contradicted, not just unbuilt.** The
  text is explicit: "The API is not exposed to Tenants with a visible
  version number." D-89's actual build shipped `/api/v1/...` routes —
  a visible version number, the literal opposite. `BR-65` is never
  mentioned anywhere in this file before this entry, confirming D-89
  built BR-62/63/64/66 but silently never addressed BR-65 at all.
- **BR-68 (Visual Identity) built correctly on the one surface that
  existed, but scoped too narrowly.** `public/pricing.html`'s palette
  and typography already matched BR-68's token table exactly (D-86).
  The live portal (`layouts/main.php`) used a completely unrelated,
  older palette. Flagged as a real scope question rather than assumed
  either way — same ambiguity BR-67 had before the project owner
  clarified it was app-wide.

**Direction 2 — is what we decided written back?** Checked every place
in this file where the Super Admin (project owner) confirmed a value
the document itself had left open — the only genuine instance across
92 prior entries: **BR-53's TDS rate.** D-71 recorded the project
owner confirming 10% directly, and the code has computed and stored it
that way on every settlement since. But the document — including this
freshly-exported PDF — still read "not fixed by this document," with
no rate stated anywhere. D-77's later document replacement evidently
never carried the confirmed figure forward. Everything else checked
(custom-domain routing, KYC verification mechanism, Sovereign Rule
thresholds, the Success Fee schedule, Tenant API Access) is either
already stated in the document as-is or was a pure implementation
choice the document never specified either way — no other unwritten
decision found.

**Resolution, per the project owner's explicit choices for each:**

1. **BR-65 — fixed in code, not the document.** `app/Config/Routes.php`
   renamed all five routes from `/api/v1/...` to `/api/...`
   (`ApiAuthFilter.php`'s comment updated to match). Verified over real
   HTTP: `/api/oauth/token` → 400 (route live, bad request body — not
   404), `/api/v1/oauth/token` → 404 (old path genuinely gone),
   `/api/listings/{id}` unauthenticated → 401 (route live, auth filter
   correctly rejecting), `/api/v1/listings/{id}` → 404.
2. **BR-53 — fixed in the document, not the code** (the code was
   already right). Edited `ADWITIX_Master.docx`'s BR-53 Statement and
   Rationale text via the docx skill (unzip → text-only run edit → zip
   → `validate.py`, 1175→1175 paragraphs, no structural change) to
   state the confirmed 10% rate and describe it as computed at
   settlement completion, replacing the "not fixed by this document"
   language.
3. **BR-68 — rolled out app-wide**, the same treatment BR-67 got.
   `layouts/main.php`'s `:root` CSS custom-property *values* were
   remapped to BR-68's token table (Paper `#EEF0EA`, Ink `#1C1F26`,
   Ink Soft `#5B5F56`, Line `#D8DACE`, the primary accent — formerly
   "emerald," now Rust `#B85C2C`, matching `pricing.html`'s own choice
   of Rust for its primary CTA — and Amber `#E3A93C`); derived shades
   not in BR-68's own table (`--ink-3`, `--emerald-deep`, the two
   "-soft" tints) were computed with the same mix-toward-black/white
   ratios the codebase's existing tenant-branding `color-mix()` calls
   already use, not eyeballed. Google Fonts import switched from Sora
   to Archivo+Inter (IBM Plex Mono was already correct), plus a new
   `h1,h2,h3{font-family:'Archivo'}` rule since none existed before.
   Variable *names* were deliberately left unchanged (`--emerald` still
   holds the primary accent, now Rust-colored) — 80+ views already
   reference these by name, and repainting values through one
   `:root` block is the same "keep identifiers stable, only change
   what renders" approach BR-67 used for its own rollout, at a
   fraction of the diff. Three files had hardcoded hex bypassing the
   variables entirely (`landing.php`'s `.cta-band`/`.pc-cta`,
   `kyc/form.php`'s status-badge color map) — fixed individually.
   Verified over real HTTP: the new tokens render on `/` and `/browse`,
   zero old-palette hex or `Sora` references remain anywhere in
   `app/Views/`.

**Regression.** All 33 `test:*` suites re-run — `tenantapi` (25
assertions), `settlement` (23), and the new `terminology` suite (22)
specifically, since those exercise the three changed areas most
directly, plus the rest of the suite spot-checked in isolation. The
same pre-existing DB-fixture-collision flake noted in D-92 (a test's
own tenant-creation insert firing twice within one CLI invocation,
confirmed via `git log` to predate every commit on this branch)
reproduced again in bulk-sequential runs and disappeared entirely when
each suite ran against its own freshly-migrated database — same root
cause, same conclusion: pre-existing sandbox behavior, not a
regression from this work.

### D-94: AX Knowledge & Chronicle Framework — Section 7 added, Phase 1 (Trading Session Chronicle) built

The project owner supplied a concept paper (AX Knowledge & Chronicle
Framework, v1.0) and asked to discuss it, add it to the master
document, and scope a Phase 1 slice for immediate build — all in one
sitting, not spread across separate approvals.

**Discussion first, as asked.** Before writing anything, the framework
was mapped against what already exists: three of its Information
Hierarchy levels already have a home under different names (Lot =
Listing, Event = Sale Event/Trading Session, Transaction = Settlement);
Evidence already exists as PR-09's media pipeline, just unclassified;
Case and Asset (as a lifecycle entity, distinct from BR-47's lighter
Related Auctions grouping) are genuinely new; the word "Dossier"
already meant something specific and narrower in this codebase (the
KYC verification packet, `KycReviewController`) before this framework
introduced a broader meaning. Three decisions were needed before
anything could be written or built, and were resolved via
`AskUserQuestion`:

1. **Phase placement** — the project owner didn't pick a side of the
   binary offered; instead: build the essential seller-facing report
   in Phase 1, defer the rest, and figure out exactly what's
   "essential" together before writing the section. That's what
   happened next.
2. **Dossier naming** — fold the existing KYC verification packet in
   as one Dossier type ("Compliance Dossier") rather than renaming
   existing code or treating the words as an accidental collision.
3. **Contributors** (BR-21 overlap) — explicitly left open, "needs
   more design thought first." Not scoped into either phase; Section
   7.5 states this plainly rather than picking a default.

**Scoping Phase 1 together, concretely** — the project owner described
the actual report wanted, not an abstract slice of the framework: "a
report duly authenticated by the system after the completion of sale
... what was listed, what happened chronologically, what's the
result ... how many people participated and how they bid, how much
improvement was done, what transaction occurred, how transparency was
maintained yet secrecy observed." Mapped directly onto the framework's
own Event Chronicle concept, scoped as a **Trading Session Chronicle**.
Two follow-up calls, also via `AskUserQuestion`: the narrative is
**template-based, not AI-authored** (AI needs a real Gemini credential,
the same blocker BR-46 is already waiting on — no reason to create a
second thing waiting on it); and the QR-linked digital verification
page is **included in Phase 1**, not deferred, so the Chronicle is
genuinely authenticated, not just generated.

**Section 7 written into `ADWITIX_Master.docx`** (unzip → OOXML edit
via the docx skill → zip → `validate.py`, 1175→1248 paragraphs, all
validations passed) — Vision, Guiding Principles, Information
Hierarchy, Chronicle Hierarchy, Contributors (flagged undecided),
Information Classification, Dossiers, Access & Visibility, and AI's
role, all as design reference (7.1–7.9); 7.10 states the Trading
Session Chronicle as active Phase 1 scope; 7.11 is a table of
everything deferred to Phase 2 and what each item needs first — the
same treatment Section 5 already gives Procurement and Market
Intelligence. Table of Contents updated to list Section 7.

**Built**, on top of what already exists rather than a new parallel
data layer:

- **Migration** (`2026-01-01-000063`): `trading_session_chronicle` —
  `report_data` is a JSON snapshot captured once at generation, not
  re-derived on every render, so a certified Chronicle stays exactly
  what it was when certified even if later activity touches the same
  Sale Event (Section 7.10's "once certified, immutable" principle,
  matching BR-13's existing treatment of Listings). `version`/
  `superseded_by_chronicle_id` columns support future re-certification
  without a schema change, though nothing in this build triggers one.
- **`ChronicleService::generate()`** — compiles the report directly
  from `listing`/`sale_event`/`bid`/`offer`/`settlement` and the BR-05
  audit trail (reusing `SettlementController::show`'s existing
  `LIKE`-based scoped-timeline query, not a new indexing scheme). Logs
  to the audit trail first via `AuditLogService`, then folds that
  entry's own `record_hash` into `report_data` before computing the
  stored `content_hash` — so the Chronicle carries a live, independently
  checkable pointer into the hash chain, and `content_hash` is a
  direct, trivially re-verifiable hash of exactly what's in the row
  (confirmed by `test:chronicle`, not just asserted).
- **BR-16 masking, verified not assumed**: `compileParticipation()`
  never puts a `bidder_party_id`/`buyer_party_id` into `report_data` —
  counts and amounts only, the same convention `listing/show.php`'s
  offer list already uses. `test:chronicle` asserts all three real
  party IDs from the fixture are absent from the full serialized
  report, not just that the code "looks like" it omits them.
  Historical versions of a Chronicle would face the same rule.
- **Wired into `SettlementService::checkCompletion()`** — generated
  for every completed settlement regardless of format or fee payer
  (unlike the BR-56 invoice a few lines above it, nothing in Section
  7.10 carves Tender out).
- **`ChronicleController`** — two deliberately different access paths
  per Section 7.8: `download()` is session-authenticated (Seller via
  `ChronicleService::findIfAuthorized()`, or that Tenant's Admin via
  `AuthorizationService::isTenantAdminForSettlement()`, already used
  elsewhere for the exact same purpose); `verify()`/`verifyPdf()` are
  token-only with no session filter at all, by design — reachable by
  anyone holding the QR's exact 48-hex-char `random_bytes(24)` token,
  since a QR scanned by an outside party (an auditor, a regulator)
  can't carry a login session with it. `/chronicles/{id}/download`,
  `/chronicle/verify/{token}`, `/chronicle/verify/{token}/pdf` added to
  `Routes.php`.
- **QR generation**: `endroid/qr-code` added via Composer (no prior QR
  dependency existed). `dompdf` embeds the PNG as a data URI directly
  in the certified PDF; the public verify page links the same PDF and
  additionally surfaces the Lot's photographs/documents and the
  audit-trail excerpt.
- **`settlement/show.php`** gained a "Trading Session Chronicle"
  section with both links, next to the existing Invoices/TDS sections
  it was modeled on.

**Verification**: `test:chronicle` (22 assertions) — automatic
generation with no explicit call from the test, the CHR-YYYYMMDD-xxxx
reference format, report content correctness (category, final price,
Reserve Value, a real 20.00% improvement computed from the fixture's
actual numbers, the confirmed 10% TDS rate), BR-16 masking as described
above, the hash/audit-chain tie-in, and both retrieval paths
(`getByToken()` for the QR route, `findIfAuthorized()` for the
session-authenticated one, including a real denial for a party who
wasn't the Seller). Real HTTP: the public verify page renders and
correctly shows "Digital Verification: ... matches what was
certified"; `/chronicle/verify/{token}/pdf` returns a genuine 2-page
PDF (`pdfinfo` confirmed, not just a 200 status) with the timeline,
participation, and QR block all present; an invalid token 404s. Full
regression re-run on freshly-migrated databases
(`settlement`/`dispute`/`successfee`/`tenantapi`/`terminology`/
`invoices`/`br35`, the suites most likely affected by touching
`SettlementService`) — all pass, including `test:settlement`'s
stall-resolution path, which also funnels through `checkCompletion()`.

Not built, per the decisions above: Case/Asset entities, the other
Chronicle types, Contributors, the full Information Classification
taxonomy, the other four Dossier types, the full six-tier Access
model, and AI-authored narrative — all Section 7.11, all explicitly
Phase 2.

### D-95: Section 7 phases named — AX Chronicle / AX Intelligence

The project owner shared an external proposal (a screenshot of a
separate ChatGPT conversation) to name D-94's two phases: Phase 1 as
"AX Chronicle" ("Capture. Certify. Preserve."), Phase 2 as "AX
Intelligence" ("Understand. Learn. Recommend."), with a comparison
table (Records/Learns, Reports/Intelligence, Timeline/Knowledge,
Evidence/Insights, PDF/AI Narrative, Downloads/Dossiers, Statistics/
Recommendations, Simple permissions/Enterprise governance) that maps
cleanly onto what Section 7.10/7.11 already are — this isn't new
scope, just names for the existing split. Confirmed: rename now, and
flag the proposal's Enterprise-tier commercial gating idea (every
customer gets AX Chronicle from day one; AX Intelligence sold as the
paid differentiator for higher-tier plans) as open for later, not a
commercial term of this document yet.

**Applied to `ADWITIX_Master.docx`** (unzip → text-only run edits →
zip → `validate.py`, 1248→1250 paragraphs, all validations passed):
the Status line, 7.3, 7.6, and 7.8's prose references, and the 7.4
Chronicle Hierarchy bullets now name both phases; 7.10 and 7.11's
headings gained "AX Chronicle (Phase 1)" / "AX Intelligence (Phase 2)"
and their taglines as new italic subtitle paragraphs. 7.11's intro
paragraph now states the Enterprise-tier question explicitly, framed
as raised-and-open, not decided.

**Also fixed while in the same section**: five internal cross-references
that pointed at "(7.9)" (AI Within the Framework) when they meant to
point at the Trading Session Chronicle, which is actually 7.10 — a
pre-existing numbering slip from D-94's own build, caught only because
this edit required re-reading every cross-reference in the section
carefully rather than assuming they were already correct.

### D-96: formal legal statements added to the Trading Session Chronicle

The project owner supplied four standard legal/governance statement
blocks — Opening, Process & Governance, Record Integrity, and Closing
Statement & Disclaimer — deliberately worded to avoid unverifiable
claims ("tamper-proof," "highest transparency") and to establish the
report as a *summary* of the underlying digital record rather than the
record itself, a materially stronger legal position if a Chronicle is
ever produced as evidence.

Added verbatim as static view content (no `report_data`/schema change
— identical for every report, not per-instance data) to both surfaces
that present a certified Chronicle: `app/Views/chronicle/pdf.php`
(Opening + Process & Governance right after the header, Record
Integrity + Closing Statement at the end, replacing the old one-line
closing note) and `app/Views/chronicle/verify.php` (the Opening
Statement's text under the page intro, Record Integrity + Closing
Statement above the existing chain-reference line). The PDF's old
closing note's one genuinely functional line — the verification
URL — was kept, appended after the new Closing Statement rather than
dropped.

Verified for real: `test:chronicle` re-run clean (22/22, unaffected —
these are static template strings, not report_data), then a fresh
Chronicle generated and both surfaces fetched over real HTTP —
confirmed all four statements render in the actual PDF (`pdftotext`
extraction) and on the live verify page, not just added to source and
assumed to work.

### D-97: Chronicle report restructured — brand identity, terminology, data-point format, real media links

The project owner supplied the AdwitiX brand mark (shield icon and full
logo lockup with tagline) and asked for five changes to the certified
Chronicle: no internal BR/PR jargon, branded terminology throughout, no
narrative "AI paragraphs," data points only, the QR made to genuinely
reach the underlying media files, and the AdwitiX brand plus
`www.AdwitiX.com` placed in the document.

**Internal jargon removed from customer-facing surfaces** —
`app/Views/chronicle/pdf.php`, `chronicle/verify.php`, and
`ChronicleService::generate()`'s stored `report_data` (comments in
those files still cite BR/Section numbers for developers; that's fine,
it's not rendered). The one substantive change: `transparencyNote`, a
full sentence citing "BR-16's double-blind privacy rule," is replaced
with a plain `privacy.identityDisclosure` data field ("Masked
(Double-Blind)") — no schema/migration involved, `report_data` is
generated fresh per event, not altered in place. "Section 194-O" was
dropped from the TDS row for the same "data points only" reason (the
rate and amount are the data; the statutory citation was an
explanatory aside). Confirmed clean via a fresh PDF's `pdftotext`
extraction — zero `BR-`/`PR-`/`Section N` matches.

**Terminology**: the awkward "Market Maker (Seller-Pays)" / "Trader
(Buyer-Pays)" phrasing (half-branded, half-raw-technical) is now a
plain `tsx_term('Seller')`/`tsx_term('Buyer')` call — just "Market
Maker" / "Trader" against a "Fee Payer" label, no hybrid term
invented. New `sale_format_label()` helper (`terminology_helper.php`,
already globally autoloaded per BR-67) turns the raw `sale_format`
enum into "Buy-Now"/"Easy Auction"/"Express Auction"/"Tender
Auction" — not part of BR-67's own 7-row map, but the same
presentation-only spirit.

**Narrative converted to data points, per instruction**: the
Executive Summary's computed sentence and the Participation section's
lead-in sentence are now plain label:value tables (Trading Session
Reference, Trading Format, Final Price, Improvement vs. Reserve
Value, Participants, Bids, Offers, Identity Disclosure). The four
legal statements from D-96 are untouched — they're the project
owner's own verbatim text, not generated narrative.

**QR now genuinely reaches media, not just names it**: the verify
page's "Supporting Evidence" list was plain text with no link at all
— `listing_media.file_path` already resolves to a real public static
asset (`public/uploads/listings/{id}/{file}`, no auth filter on that
path), it just was never wired into an `<a href>`. Fixed. Verified
for real, not just read: inserted a real `listing_media` row for the
test fixture's own Lot, re-fetched the verify page, confirmed the
rendered `href` matches the expected path, and fetched that exact URL
— HTTP 200, the real file.

**Brand identity**: both supplied images copied into
`public/images/brand/`. The full logo lockup renders in the PDF
letterhead as a `data:image/jpeg` URI (dompdf renders from a detached
HTML string — a plain `<img src="/...">` won't resolve there, same
reasoning as the QR code) and on the verify page as a normal
`<img src="/images/...">` since that's a live request. `www.AdwitiX.com`
added to both surfaces' footers, alongside the verify URL on the PDF.

Verified: `test:chronicle` re-run clean (22/22 — the `privacy` field
rename didn't break any assertion), `terminology`/`settlement`/
`invoices`/`tenantapi` re-run clean as a broader regression check
against the newly-added global helper function. Real HTTP: fresh PDF
and verify page fetched, rasterized to a real page image to visually
confirm the logo actually renders (not just present in markup), and
`pdftotext`-extracted to confirm the jargon sweep.

Not done, left as a smaller possible follow-up rather than assumed
in scope: the Chronological Timeline still shows raw internal event
codes (`offer.accepted`, `settlement.tds_deducted`) — not BR/PR
jargon, but not branded-terminology prose either. The project owner's
instruction was specifically about BR/PR references; this wasn't
asked for and wasn't done unprompted.

### D-98: BR-46 (AI Listing Quality Pre-Audit) built end to end, inert until a Gemini key lands

Discussed before building, per BR-01. The project owner framed BR-46
as a "showcase" feature — the first-impression moment a seller
experiences AI on the platform — and asked what happens to that
moment for a Tenant integrating via the API instead of the portal,
since BR-46's own text describes an interactive button click ("a
seller may **trigger**... one-click 'Apply Title' action"), and
nothing happens to a JSON payload arriving from a Tenant's own
backend the way it happens to someone at a screen.

Two options were laid out: scope BR-46 to the portal only (API-pushed
Lots skip it, on the assumption a Tenant's own systems already vet
their data), or expose the pre-audit as its own standalone capability
a Tenant's own frontend can call before finalizing a push — so a
Trader/seller on the Tenant's *own* branded storefront gets the same
AI-assisted moment, under the Tenant's own skin. The project owner
picked the second, explicitly for maximizing the showcase effect
across the platform's most sophisticated customers, not just direct
portal users. Two follow-on decisions, resolved without re-opening
the conversation once agreement was reached: the API endpoint gets
the exact same tier floor as Lot push itself (TSX Growth+, BR-66) —
no new tier rule, since a Tenant that can't push Lots has nothing to
pre-audit — and it's a dedicated, stateless `POST /api/listings/
pre-audit` rather than a flag on the push endpoint, since the whole
value of BR-46 is the iterate loop (check → suggestions → apply →
check again → submit when happy), which only works as a separate,
repeatable, side-effect-free call.

**A real, previously-undiscussed gap surfaced while building**: BR-46
needs a title to write "Apply Title" into, and the `listing` table has
never had a title column — display has always been a composed
`category`/`subcategory` string. Fixed with a small, additive,
nullable `listing.title` migration (`2026-01-01-000064`) rather than
silently faking the interaction or silently inventing a new required
field — `listing/show.php`'s heading prefers `title` when set, falls
back to the existing composed string otherwise, so nothing changes
for any listing that never sets one.

**Built, inert until `GEMINI_API_KEY` is real, same honesty pattern
BR-52/AmlMonitoringService already established for a blocked-on-
credentials feature**: `GeminiPreAuditService::evaluate()` checks for
a configured key *before* attempting any network call and throws a
plain, honest "AI pre-audit is not currently available" message if
one isn't set — never a fabricated score. Both call sites
(`ListingController::preAudit()` for the portal, session-gated;
`TenantApiController::preAuditListing()` for the API, bearer-token +
tier-gated) catch that and degrade to a clean `503 {"available":
false}` response. The portal button (`listing/create.php`) shows the
same "not currently available" state rather than erroring or silently
doing nothing. A real Gemini REST contract is wired throughout
(`generateContent` with a JSON `responseSchema` for
qualityScore/suggestedTitle/missingFields/statusFlag) — genuinely
correct against the documented API, but genuinely untestable in this
environment without a real key, exactly the same honest limitation
BR-52's chargeback code already carries.

`GEMINI_API_KEY`/`GEMINI_MODEL` documented in the tracked `env`
template (not `.env`, which is gitignored) with a direct link to where
to get a key.

**Verified**: new `test:aiaudit` (9/9) — the "not configured" state is
proven false rather than assumed (no stray key in this environment),
`evaluate()` genuinely throws before any network attempt, the tier
gate is asserted against the exact same `TenantModel::canPushListings`
call Lot push uses (not a duplicated rule that could drift), and
`listing.title` round-trips correctly both set and unset. Real HTTP,
via a genuine registration+OTP+mPIN flow (not simulated): the portal
endpoint 401s with no session and returns a real 503 unavailable
response once authenticated; `listing/create.php` renders the button
and title field for a real logged-in seller. Real HTTP against the
Tenant API, via genuinely issued OAuth2 tokens for a real Growth-tier
and a real Launch-tier tenant: Growth reaches the service and gets the
honest 503, Launch is blocked at 403 *before* the service is ever
called, no token gets 401. Regression: `tenantapi`, `buynow`,
`easyschedule`, `express`, `tenderfoundation`, `media`, `discovery`,
`browse`, `lifecycle` all re-run clean against the shared files this
touched (`ListingController`, `TenantApiController`, `ListingModel`,
`Routes.php`).

### D-99: BR-/PR- jargon swept from the live portal (not just the Chronicle), two access-control gaps fixed along the way

The project owner asked to work on the platform's UI/UX directly
("not sure yet — let's look together" rather than a fixed brief), so
this started as a joint walkthrough of the live product: registration
→ mPIN → marketplace home → list-an-asset → KYC → listing detail →
profile, captured as real screenshots via a Playwright-driven browser
session against the running `spark serve` app. D-96/D-97's "no
BR/PR jargon" instruction had been scoped to the Chronicle report
only — the walkthrough showed the same pattern is actually
platform-wide: raw citations like "BR-11: universal required lot
metadata," "Category (BR-07)," "Media Tier (BR-59)," and "PR-09: your
progress is auto-saved" sitting directly in seller- and Tenant-Admin-
facing copy. The project owner picked killing this as the first
priority.

**Scope**: every `app/Views/*` file outside `admin/` (SaaS Admin's own
internal operator screens, left untouched — that audience is
platform staff, the same category BR/PR shorthand was always fine
for). 27 files, 71 occurrences found via
`grep -rEo "BR-[0-9]+|PR-[0-9]+"`, all removed from rendered output —
parenthetical citations dropped, surrounding sentences kept as plain
English. PHP/JS comments inside those same files (developer-facing,
never sent to the browser as visible text, confirmed by checking
which are inside `<?php ... ?>` blocks vs. `<script>` tags) were
deliberately left alone, same distinction D-97 already established.
`TenantAdminFilter`'s 403 body ("BR-09: you are not the Tenant Admin
for this listing's tenant.") also had the citation stripped — that
text is returned straight to a browser on a denied request.

**Two real access-control gaps found and fixed while sweeping
`listing/show.php`, not just cosmetic**: the "Flag CBS Violation
(BR-59/61)" button, the pending-listing Approve/Reject block, and the
Sale-Event Approve/"Force-freeze (dev)" controls were all rendered
unconditionally to *any* visitor (including anonymous ones, since
`isOwner` is simply `false` when logged out) — the actual
authorization lived only in the controller/route filter
(`flagCbsViolation()`'s explicit role check, the `tenantAdmin:listing`
/`tenantAdmin:saleEvent` route filters), so every non-admin who
happened to view a pending listing saw working-looking buttons that
would just redirect back with a permission error on click.
`ListingController::show()` now computes
`isTenantAdminForListing` once (`AuthorizationService::
isTenantAdminForListing()` — the exact same check the route filter
uses, not a parallel rule that could drift) and the view gates all
four blocks on it. The "Force-freeze to Active (dev)" button — a
demo-only shortcut around the real 60-minute grace window — additionally
now requires `ENVIRONMENT !== 'production'`, so it can't render at
all outside a dev/staging build regardless of role.

**Verified**: `php -l` across all 28 touched files. Full regression —
all 34 `test:*` suites re-run clean against a rebuilt-from-scratch
database (`auth`, `kyc`, `lifecycle`, `media`, `dispute`, `settlement`,
`cascade`, `buynow`, `express`, `successfee`, `tenantapi`,
`terminology`, `tier3`, `standingreview`, `br35`, `rating`, `aiaudit`,
`aml`, `browse`, `crawlback`, `discovery`, `easyexpresscorrections`,
`easyschedule`, `invoices`, `payoutcontrol`, `phase3a`, `scheduler`,
`selleraudit`, `servertimecheck`, `sovereignrule`, `tenderbidding`,
`tenderfoundation`, `tenderreview`); `test:auditlog`'s 3 failures are
the pre-existing, documented, sandbox-DB-name mismatch (unrelated to
this change, confirmed still present against a bare fresh migration).
Real HTTP, not just read: a fresh party registered through the actual
mobile → OTP → mPIN flow, then Playwright screenshots of the live
`list-an-asset`, KYC, and listing-detail pages confirmed both the
jargon is gone from the rendered page and — logged in as an ordinary
non-admin party — the Flag/Approve/Reject/Force-freeze controls no
longer appear at all.

Not done, flagged as a follow-up rather than folded in unprompted:
the KYC form (`kyc/form.php`) shows both Individual and Organization
field groups at once regardless of the selected Entity Type — a
real UX rough edge spotted during the same walkthrough, but a
distinct problem (conditional field display) from the jargon sweep
that was actually asked for.

### D-100: platform name corrected from "eBid Hub" to AdwitiX, real logo/icon wired into the header, footer, favicon, and page titles

Continuing the same joint UI/UX walkthrough, the project owner
pointed out that the live portal still names itself "eBid Hub"
throughout — header wordmark, browser tab titles, footer, TOTP
authenticator-app issuer, the marketing copy on the homepage and
Trust & Support hub, even the Terms/Privacy/Grievance legal
documents — when the platform's real identity is AdwitiX, per the
shield icon and full logo lockup already supplied and already in use
on the Chronicle report since D-97. "eBid Hub" turns out to have been
the working/demo name baked into the build before the AdwitiX
branding was finalized, not a name the project owner asked to keep
alongside it.

**Scope**: every literal `eBid Hub`/`eBidHub` occurrence across the
app — 38 files, all of `app/Controllers/*` (mostly `'title' => '... —
eBid Hub'` page titles), the four view files that reference it in
body copy (`landing.php`, `trust_support.php`, `legal/document.php`,
`listing/create.php`), `TotpService::getProvisioningUri()`'s default
issuer (shown inside Google Authenticator/Authy when Super Admin sets
up 2FA — a real functional string, not just decorative), and
`TestTerminology.php`'s own assertion that probes `tsx_term()` with
the platform's own name to confirm BR-67's mapping leaves it
untouched (`eBid Hub` → `AdwitiX` there too, same invariant, correct
name). Every occurrence reads as a proper noun in a sentence
("AdwitiX operates a zero-seller-fee model...", "AdwitiX's aggregate
liability...", "the AdwitiX name, logo, and Platform design are the
property of AdwitiX") — a plain find-and-replace, verified afterward
with a repo-wide grep down to zero remaining hits.

**The header/footer/favicon needed real image integration, not just
text** — `layouts/main.php`'s brand mark was `eBid<span>Hub</span>`
(two-tone: Ink + the app's `--emerald` token, which BR-68 already
repointed to Rust `#B85C2C`). Replaced with the actual shield icon
(`public/images/brand/adwitix-shield.jpg`, already on disk from D-97)
at 26px next to `Adwiti<span>X</span>` — same two-tone split
convention, just the real name and a real icon instead of invented
placeholder text. This only fires in the platform-default branch of
the existing tenant-branding conditional (`$__tenant['branding_logo_
url']` still takes priority when a Tenant has its own white-label
logo, untouched) — it's the platform's own identity being fixed, not
a change to how Tenant white-labeling works. Footer `&copy; eBid Hub`
→ `&copy; AdwitiX`. `<title>` fallback `eBid Hub` → `AdwitiX`
(confirmed via a real page load: `document.title` now reads "AdwitiX
— Salvage & Surplus Marketplace" on the homepage). Added a real
`<link rel="icon">` pointing at the shield image — there was no
AdwitiX favicon at all before this, just the CodeIgniter-generated
default `favicon.ico` — confirmed via a real HTTP fetch of the
resolved favicon URL returning 200.

**Verified**: `php -l` across all touched files. Full regression run
against a rebuilt-from-scratch database — all 34 suites, same list as
D-99 plus `chronicle` (confirmed clean in isolation; it collided with
its own earlier fixture only when re-run a second time against a
DB that already had that run's data, a pre-existing test-idempotency
quirk unrelated to this change, not a real failure). Real HTTP/browser
check: fresh page load's `document.title`, the rendered header and
footer screenshotted and read back, and the favicon URL fetched
directly.

### D-101: shared design-system foundation — responsive nav, spacing/elevation tokens, component classes; proved out on Home, Profile, KYC

The project owner's next observation, same UI/UX walkthrough: "all the
screens are very basic and unorganised and lack any visual excitement
... we don't know if they render mobile adaptive." Checked rather than
assumed — a repo-wide grep found exactly one `@media` rule in the
entire shared layout (`layouts/main.php`), hiding the Live Ticker
sidebar under 900px. Every other screen, including every form and
dashboard, had zero responsive treatment. Given two explicit choices
from the project owner — fix the foundation first rather than keep
patching page-by-page, and go bolder rather than just tightening the
existing spare aesthetic — this pass builds the shared system in
`layouts/main.php` and proves it out on three flagship pages rather
than a wide shallow sweep.

**Foundation, `layouts/main.php`**: extended `:root` with a spacing
scale (`--sp-1`…`--sp-8`), an elevation scale (`--shadow-sm/md/lg`,
previously zero box-shadow usage anywhere), and two new accent colors
lifted from the AdwitiX shield/logo — `--navy` and `--gold` —
decorative only, never used for buttons or status states, which stay
Rust/Amber. New shared component classes: `.card`, `.section`,
`.grid-2/3/4` (collapse to one column under 900px), `.field` (label +
input, replacing hand-rolled inline styles), `.badge` (+ `-emerald`/
`-amber`/`-navy` variants), `.table-wrap` (horizontal-scroll wrapper
for wide tables). Real breakpoints added at 900px and 640px.

**The actual nav bug, fixed at the root**: the header had no mobile
treatment at all — at phone width, the full desktop nav (4 tabs + up
to 6 account links) simply wrapped and visually overlapped the
logo and page content, on every single page, since they all share
this layout. Added a hamburger toggle (`.nav-toggle`, vanilla JS,
CSS-only otherwise) that hides the two nav groups under 900px and
reveals them as a stacked full-width panel on tap. First
implementation had both groups independently `position:absolute` at
the same coordinates, so they overlapped each other — caught via a
real screenshot of the opened menu, fixed by making both normal-flow
flex children of the wrapping header row instead (`width:100%;
order:10`), which stacks them in DOM order with no coordinate math.
Verified via a real Playwright click on `#navToggle`, both logged-out
and logged-in states, screenshotted open and closed.

**Proved out on three pages, not applied blindly everywhere**:
- **Home** (`landing.php`): the empty-state hero card was a flat gray
  box: replaced with a navy gradient, a gold dot-grid texture, and a
  faint shield-checkmark watermark (inline SVG data URI, no new
  asset). Format cards (Buy-Now/Easy/Express/Tender) previously all
  shared one color; now Easy carries the navy accent and Express the
  gold, so the four read as genuinely distinct at a glance. Added
  hover lift + shadow on product cards, format cards, and category
  tiles for basic interactivity.
- **Profile** (`my/profile.php`): was one unsorted row of 11
  identical pill buttons. Rebuilt as an avatar/summary header, a
  stat-pill row (ratings/KYC/last login), and three labelled
  `.settings-list` groups (Account / Activity / Discovery) plus a
  separated Log Out / Delete Account row — same 11 destinations,
  organized instead of dumped.
- **KYC** (`kyc/form.php`): fixed a real bug flagged in the prior
  walkthrough — Individual and Organization questionnaire fields
  were both always visible regardless of the selected Entity Type.
  Added a plain `change`-event toggle (`entityTypeSelect` show/hides
  `#individualFields`/`#organizationFields`, synced on load too) so
  only the relevant set shows. Also wrapped each of the four
  numbered sections in `.card` with a numbered step badge, and
  converted the field markup to the new `.field` component.

**Verified**: `php -l` on every touched file. Full regression against
a rebuilt-from-scratch database — all 32 non-`kyc` suites clean;
`kyc` itself confirmed clean standalone (32/32 assertions) against a
fresh DB, the loop failure being the same known same-DB double-run
fixture collision already documented in D-98/D-99, not a regression
— the KYC form's field `name` attributes are byte-for-byte unchanged
from before this pass, so `KycController` never saw a different
payload shape. Real browser verification, not just markup review:
Home and Profile screenshotted at both 1440px and 390px; KYC
screenshotted at 1440px in both Individual and Organization states
(toggled live via Playwright's `selectOption`, not two separate page
loads) plus at 390px.

Not done, flagged rather than folded in: the remaining ~75 view files
still use the old hand-rolled inline-style pattern rather than the
new `.field`/`.card` classes — the foundation and the pattern are
proven, but rolling it out further is real per-page work the project
owner hasn't asked for yet.

### D-102: navigation-gap audit — 5 flagged items already wired, 3 genuine gaps found and closed

The project owner reported five specific navigation gaps, framed as
"app logic fully built and tested, but no page/button reaches it":
no logout except Super Admin, no My Listings for sellers, no My Bids
for buyers, no account/profile page, no searchable/filterable browse
page. Asked for a full screenflow, gap-find, and wire-up.

**Checked rather than assumed, and the five named items turned out to
already be fully wired** — confirmed both by reading the code and by
a real browser session (fresh account registered through the actual
mobile → OTP → mPIN flow, then live click-throughs): `/logout`,
`/my-listings`, and `/profile` are all in the shared header nav on
every page for any logged-in session; `/my-bids` is reachable from
Profile's Activity group (added in D-101's reorganization); `/browse`
already has a fully built search/filter UI (text query, location,
price range, minimum rating, condition, posted-date, sort, category/
format chips, pagination, save-search) wired to a real filtered query
in `Home::browse()`. `git show origin/main:app/Views/layouts/main.php`
confirms these links predate this session entirely — a prior pass's
routes comment literally reads "Navigation gaps closed — logout, My
Listings/Activity/Profile, browse". None of this was taken on faith:
each claim is backed by an HTTP status code or a screenshot in the
audit artifact below.

**The actual audit, run properly rather than stopping at the named
five**: extracted all 65 static GET routes from `Routes.php`, grepped
every `href` in `app/Views/` for each, and hand-resolved every route
that came back with zero literal matches — several (all of Trust &
Support's sub-pages) are linked through a PHP array of card
definitions (`TrustSupport::index()`'s `$groups`), not a literal
`href="/x"` string, so a naive grep flags false positives there.
Three routes survived as genuinely unreachable:

- **`/cookie-policy`** — full policy content and route existed
  (`LegalController::cookiePolicy()`), never added to the Trust &
  Support hub's Legal card group alongside Terms/Privacy/Grievance.
  Fixed: added the card.
- **`/account/invoices`** — GST invoice list + PDF download fully
  built (`InvoiceController`), zero entry point from Profile or
  anywhere else. Fixed: added to Profile's Account settings group.
- **`/sale-events/{id}/dispute`** — the real gap of the three. The
  entire "File a Dispute" flow (form, category/description submit,
  `DisputeService::fileDispute()`) was built and route-registered,
  but not one button anywhere in the app pointed to it — only links
  to *view* an already-filed dispute existed (from Settlement, My
  Purchases, Tenant Admin dashboard). A buyer or seller with a
  genuine problem had no way to start the process at all. Fixed: a
  "File a Dispute" link now appears on the Settlement page whenever
  no dispute exists yet for that transaction and the format isn't
  Tender (which runs its own separate process, per the form's own
  copy) — gated on `$callerId` so it only shows to a logged-in
  viewer, matching `DisputeController::fileForm()`'s own login gate.

**Verified**: full regression re-run clean against a rebuilt database
(all 33 suites; `chronicle` confirmed clean standalone — its one loop
failure was the same known same-DB double-run fixture collision
already documented in D-98/99/101, not a regression, and this time
traced to my own earlier manual query for a real settlement ID
inside this same session). Real HTTP for all three fixes: Cookie
Policy card renders on `/trust-support` and resolves 200; Invoices
link renders on `/profile` and resolves 200; File a Dispute link
renders on a real settlement page, resolves 200, and lands on the
actual `dispute/file` form (`<h1>File a Dispute</h1>` confirmed in
the response).

Delivered as a screenflow diagram plus a findings table (route,
claim, actual finding, evidence) in a published artifact, not just
prose — the diagram groups the core Buyer/Seller path by where each
screen sits and highlights the three genuinely-fixed nodes.

### D-103: two more real gaps closed — Emergency Stop, and the entire Tenant Admin dashboard's missing entry point

A deeper sweep across every zone (auction, negotiate, reports,
disputes, settlement, TSX/Tenant Admin, tender, listing/event pages,
help & trust) at the project owner's request, checking every
dynamic-segment route's actual trigger inside its parent page's
template rather than just static routes. Reported both before
touching anything, per instruction, then fixed smallest-first.

**Emergency Stop** (`POST /sale-events/{id}/emergency-stop`, BR-14,
already fully built and covered by `test:lifecycle`) had zero UI
trigger anywhere in the app — a Tenant Admin had no way to actually
cancel a live auction in an emergency. Added a collapsed `<details>`
control to `listing/show.php`, visible whenever the sale event is
still live (`pending_approval`/`grace_period`/`active`) and the
viewer is that listing's Tenant Admin (`$isTenantAdminForListing`,
the same check D-99 already wired for the Approve/Reject/Force-freeze
controls on the same page) — a required reason textarea, a
confirm-dialog warning it's irreversible, matching the controller's
own requirement that a reason is mandatory and permanently logged.

**Verified for real, not assumed**: a throwaway CLI command
(`temp:emergencystopfixture`, deleted immediately after use, same
pattern as earlier sessions' `TempIssueTestCreds`) created a genuine
tenant, a party promoted to `tenant_admin` via the real
`PartyRoleModel::promoteTenantAdmin()`, and a live `active` sale
event. Logged in as that admin through the real mobile/mpin flow,
clicked the actual rendered button, accepted the real confirm
dialog, submitted a real reason — the sale event's `status` column
came back `cancelled` in the database, exactly matching
`ListingLifecycleService::emergencyStop()`'s own behavior, and the
control correctly disappeared on reload since cancelling is no
longer a valid action on an already-cancelled event.

**The bigger one: the entire Tenant Admin (TSX) dashboard zone had no
entry point after login.** Every sub-page under `/tenants/{id}/...`
(Verification Console, Media Waiver, Seller Management, Billing, API
Access) links back to `/tenants/{id}/dashboard`, but nothing in the
shared header nav or Profile ever linked *to* it — a real Tenant
Admin, promoted by Super Admin, logging in through the ordinary
`/login` flow, had no discoverable path to their own dashboard at
all without someone handing them the exact URL. `PartyRoleModel::
findAdministeredTenantIds()` already existed (built for BR-50's
payout-review scoping) — reused it in `layouts/main.php`, computed
once per page load alongside the existing `$__tenant` lookup, gated
on an actual logged-in session. When a party administers at least
one tenant, a `"<?= tsx_term('Tenant Admin') ?> Console"` link now
appears in the header nav pointing at their dashboard. Deliberately
takes the first administered tenant rather than building a
multi-tenant switcher — the schema and `promoteTenantAdmin()`'s own
"exactly one active Tenant Admin per tenant" comment both point at
one-admin-per-tenant as the common case, and a switcher wasn't asked
for.

**Verified**: same throwaway-fixture pattern, real login as the
tenant admin, real click on the new header link, confirmed it
resolves to `/tenants/{their-tenant-id}/dashboard` with a real 200
and the correct tenant name in the page's own `<h1>`. The negative
case (an ordinary buyer/seller never sees the link) wasn't
separately click-tested due to two flaky Playwright timeouts in this
sandbox — but it's guaranteed by construction, not just untested:
the new `<a>` sits inside the exact same `session()->get
('logged_in_party_id')` conditional block as My Listings/Profile/Log
Out, already proven false-for-logged-out-users repeatedly earlier
this session.

Full regression re-run clean against a rebuilt database (all 33
suites; `chronicle`'s one loop failure was — again — the same known
same-DB double-run fixture collision from this session's own manual
queries, confirmed clean standalone).

### D-104: production-readiness audit — real CSRF, real CSP, CI pipeline, a genuine backup script, stale docs fixed

The project owner asked for a full audit of what's genuinely missing
for a real deployment to their own cloud server, then which of those
gaps could be closed without external credentials. Investigated by
reading the actual code, not re-summarizing old docs: grepped for
every `DEV-ONLY` marker, checked `Config/Filters.php`,
`Config/Security.php`, and `Config/ContentSecurityPolicy.php` against
what was actually wired, and confirmed EMD funding is simulated across
every format (`devFundEmd`/`pledge`) and OTP is never actually sent
(shown on-screen only) — both correctly flagged as blocked on external
vendor credentials (payment gateway, SMS provider), same category as
BR-46 (Gemini) and BR-52 (SabPaisa). Five items had no such
dependency and were closed this pass.

**CSRF protection, enabled app-wide.** Was fully commented out in
`Config/Filters.php`'s `$globals` — every state-changing POST in the
app (bidding, registration, disputes) had zero CSRF protection, not
previously documented anywhere as a gap. Added `'form'` to the
autoloaded helpers, added `csrf_field()` to all 90 POST forms across
46 view files, and the one JS `fetch()` POST (BR-46's AI pre-audit
check) that isn't a real `<form>` submit. Excluded `api/*` from the
filter — the Tenant API is OAuth2 bearer-token server-to-server auth
(`ApiAuthFilter`), not a browser session with cookies, so it has no
CSRF token to send and doesn't need one.

Two real bugs found and fixed while doing this, not assumed away:
1. A naive regex stopped at the first literal `>` it found, which for
   several forms was the `>` inside an embedded `<?= esc($id) ?>` PHP
   tag rather than the form tag's actual closing `>` — corrupting the
   HTML (`csrf_field()` landing mid-URL, e.g. inside the Emergency Stop
   form's `action` attribute). Caught by grepping the output for
   `csrf_field() ?>/` after the first pass, reverted, and rewritten to
   treat `<?=...?>`/`<?php...?>` as atomic units when scanning for the
   tag's real closing `>`.
2. Verified end-to-end over real HTTP, not just by reading the
   rendered HTML: a POST to `/register` with no token returns a real
   403 with "CSRF" in the body; the same POST with the real
   token+cookie pair proceeds to "Enter the OTP" — genuine app logic,
   not a simulated pass.

**A real Content-Security-Policy, not a generic default.** Was off
entirely (`CSPEnabled = false`). Read every view for what the app
actually needs before writing the policy, rather than copying
CodeIgniter's defaults and hoping: no external `<script src>` anywhere
(`script-src 'self'`), Google Fonts is the only external stylesheet
(`style-src`/`font-src` scoped to `fonts.googleapis.com`/
`fonts.gstatic.com`), a `data:` URI is used for one inline SVG
watermark (`img-src` includes `data:`), and the D-42 real-time
WebSocket sidecar connects to the same host on a different port —
different origin by browser rules, so `connect-src 'self'` alone
wouldn't cover it; added scheme-only `ws:`/`wss:` instead of
hardcoding a port that depends on the deployment's own Nginx-proxy
choice (see README Step 13). `frame-ancestors 'none'` and
`object-src 'none'` since nothing legitimately needs either.

`style-src-attr`/`script-src-attr` are deliberately `'unsafe-inline'`
— this whole app is built on inline `style="..."` attributes (no
separate stylesheet to fall back to) and a handful of inline
`onclick`/`onchange`/`onsubmit` handlers (grep-verified: 4
occurrences across 2 real views). Locking those down would mean
migrating ~75+ view files off inline styles first — a real, separate
frontend project, not something to fold into a CSP pass. `<style>`
and `<script>` **block** elements (not attributes) are properly
nonce-protected, not blanket-allowed: added CodeIgniter's
`{csp-style-nonce}`/`{csp-script-nonce}` placeholder to all 13
`<style>` blocks and 6 inline `<script>` blocks across the app.

Caught by an actual headless-browser check, not by reading response
headers: the first pass left the shared layout's `<style>` blocks
unprotected (no nonce), which silently stripped the entire
design-system CSS in a real browser — the raw HTTP headers looked
perfectly fine, `document.body`'s computed `font-family` had quietly
fallen back to Times New Roman. Fixed by nonce-tagging every block;
re-verified with the same script — zero real CSP violations across
the landing page, register, browse, and trust-support, screenshotted
to confirm the design system still renders correctly.

**A CI pipeline that actually runs the 35 suites.** There was no CI
configured on this repo at all (confirmed via `pull_request_read`
`get_check_runs`: 0 checks). Added
`.github/workflows/tests.yml` — a real Postgres 16 service container,
the exact PHP 8.2 extensions `composer.json`/README require, ffmpeg
for D-43's video transcoding, migrations, then all 35 `test:*`
commands with real exit-code checking (not text-matching).

Two real bugs in the workflow found by actually running the exact
`.env`-construction steps locally before trusting them, not assumed
correct because they looked right:
1. `CI_ENVIRONMENT = testing` crashes spark's CLI bootstrap outright
   (`Undefined constant SUPPORTPATH`) — that value triggers
   CodeIgniter's PHPUnit-specific bootstrap path, and these `test:*`
   commands are plain spark commands, not PHPUnit. Fixed to
   `development`, matching what this sandbox's own working `.env`
   already uses.
2. The `sed` pattern for uncommenting `app.baseURL` used an unescaped
   `.` , which also matched the neighboring `# app_baseURL = ''`
   comment line (the underscore-alternate-syntax example) and produced
   a duplicate key in `.env`. Fixed by escaping the dot and anchoring
   to line start.

A third, more consequential bug surfaced only by running the full
35-suite loop against a single freshly-migrated database in one
session — exactly how any CI run works, and different from this
session's usual habit of rebuilding between individual manual test
invocations: `TestChronicle.php` and `TestEasySchedule.php` hardcode
the exact same fixture mobile numbers (`+919888901001`/`-902`).
Harmless when run in isolation or rebuilt between runs (which is
almost certainly why D-102/D-103's own "known same-DB double-run
collision" explanation for this exact symptom went unquestioned
earlier this session) but a **guaranteed, permanent CI failure** the
moment both suites run in the same database session — which a real CI
pipeline always does. Found by grepping every `Test*.php` command for
duplicate fixture numbers across different files (not just within
one), not by re-running until it happened to pass. Gave
`TestChronicle.php` its own numbers; re-verified with three full
35-suite runs against three independently rebuilt databases — the
last one clean start to finish, both locally and via the exact
`.env`-construction steps the workflow itself runs.

**A real backup script.** No backup strategy existed anywhere in the
docs. Added `scripts/backup.sh` — reads DB credentials straight out of
`.env` (nothing to duplicate/desync), compressed `pg_dump`, tars
`public/uploads/` (the local-disk media storage this same audit
flagged as a real scaling caveat), prunes anything past 14 days, and
exits non-zero on a genuinely failed/empty dump rather than leaving a
silent gap. Verified for real: ran it against this sandbox's actual
database and `public/uploads/`, produced a valid gzip'd SQL dump
(confirmed real `pg_dump` header + 90 `CREATE TABLE`/`COPY`
statements, not an empty/corrupt file) and a real tar archive; then
separately verified the 14-day retention prune actually deletes a
file backdated past the window while leaving fresh ones untouched.

**Stale docs fixed**, not just re-asserted: `SETUP.md`'s "Not yet
built" list still named seven items (logout, My Listings, My
Bids/Purchases, the profile page, a filterable Browse page, tenant
view/edit, TOTP backup codes, listing-edit/emergency-stop routes,
video/document upload) that had all been built since it was last
written — rewritten to list only the three genuinely open items
(payment gateway, SMS, BR-46's Gemini key). `README.md`'s "before
deploying" warning still pointed at PRs #31→#34 as unmerged — verified
via `pull_request_read` that #34 (and the rest of that stack) merged
weeks ago, and separately confirmed `dev` really is a strict ancestor
of `main` (the one part of that old warning still true) rather than
just re-asserting it. Also fixed: the test-command list was missing
`test:chronicle` and `test:aiaudit` (35 real suites, not 33 — verified
by `grep`ing `protected $name` across every `Test*.php` command rather
than trusting the prose count).

**Still genuinely blocked on external dependencies, unchanged**: a
real payment gateway, a real SMS provider, BR-46's Gemini key, BR-52's
SabPaisa credentials. None of this pass's changes touch that list —
closing it needs credentials only the project owner can supply.

Full regression re-run clean (all 35 suites, three independent clean
rebuilds during this pass alone) plus a real headless-browser
CSP/rendering check and a real HTTP CSRF accept/reject check — not
just `php -l`.

### D-105: Lot Reach & Interest — a real feature built from a design mockup that had no backend at all

Follow-on from D-104's coverage audit: of the 53-screen design
package, "Lot Reach & Interest" was the one screen where nothing
underneath it existed — not the reversed CLV matching (only the
buyer-facing direction was ever built), not per-listing view/interest
tracking, and no messaging system of any kind anywhere in the
codebase. The project owner asked for it built, since it's "a good
thing" to have. `docs/design/CLAUDE_DESIGN_HANDOFF.md` updated to move
it from "blocked, no backend" to "ready to design" with the real
field/route spec, and the same doc's coverage audit found and recorded
6 more screens (Buyer/Seller Dashboard, Rating History, Star Ratings,
Lot Directory, Trading Session Directory) that have **neither** a
design nor a consolidated backend — flagged for a product scoping
decision, not built here.

**What got built**: migration `2026-01-01-000065` (`listing.view_count`,
`listing_view`, `seller_message`, `seller_message_recipient`), a new
`ListingReachService` that reverses `ClvMatchingService`'s existing
buyer→listings direction into listing→buyers (and — a genuine
improvement, not scope creep — actually implements the location-match
dimension the original `findMatches()` saves but never applies), real
per-listing view tracking wired into `ListingController::show()`, and a
real in-app messaging system: `LotReachController` (seller composer +
send action) and two new `MyActivityController` methods (buyer inbox +
mark-read). Two new routes-reachable pages —
`/my-listings/reach` and `/my-messages` — both wired into Profile's
Activity group, not left orphaned the way this session's earlier
navigation audits (D-102/D-103) found other pages to be.

**Deliberate scope decisions, stated plainly**: delivery is real and
in-app only — there is no SMS/email provider connected (D-104's own
finding), so "delivered via their preference alerts inbox" (the
original mockup's own copy) means literally this new `/my-messages`
page, nothing more. Location matching is a case-insensitive substring
check against the listing's free-text yard address — there's no
normalized state field anywhere in this schema to match against more
precisely, and buyer-saved states are themselves free text.

**Real bugs found while building this, not assumed away**:
1. First migration attempt used `CHAR(36)` for UUID foreign key
   columns — this schema uses native Postgres `UUID` throughout
   (confirmed by inspecting `listing`/`party`'s real column types, not
   assumed); rewritten to match the exact raw-SQL migration style
   already established in `CreateTradingSessionChronicle`.
2. The test suite's own first draft used strict `=== 2` counts for
   matched-buyer numbers — real once, but wrong under a full
   regression run: the matching function is intentionally
   platform-wide with no tenant/test scoping (matching
   `ClvMatchingService::findMatches()`'s own existing precedent), so an
   earlier suite's buyer fixtures can legitimately also match this
   suite's listing when all 36 run in one shared-DB session. Fixed by
   asserting specific buyer inclusion/exclusion (already checked via
   real inbox delivery) rather than a brittle exact headcount.
3. A test forgot to call `transitionStatus($id, 'active')` on its own
   listing fixture — `getReachSummary()` correctly scopes to active
   listings only (matching how every other "live listings" view in
   this codebase already scopes itself), so the listing sat at
   `inventory` and the summary legitimately returned zero. Test fixture
   fixed, not the service.

**Verified for real, not assumed**: `test:listingreach`, 29/29
assertions, covering matching (including negative cases: a buyer
matching on zero dimensions is excluded entirely, a buyer with no
saved preferences at all is excluded, a seller never matches their own
listing), view/favorite tracking, authorization (a seller cannot
message on behalf of another seller's listing), and the full
message-send → real-inbox-delivery → mark-read lifecycle. Then a full
real HTTP click-through on top of that, not just the service-level
suite: registered/logged-in as a real seller, viewed a real listing,
opened the real reach dashboard, sent a real bulk message through the
real form (with a real CSRF token), confirmed via direct database
query that it delivered to exactly the matched buyers and no one else,
logged in as one of those buyers, saw the real message in `/my-messages`,
and marked it read — each step checked against real database state,
not just an HTTP 200. (One real detour along the way: this sandbox's
`app.baseURL` is configured as `localhost`, so testing via `127.0.0.1`
made every `redirect()->to()` land on a different cookie-jar host and
look like a logout — not a bug, just a reminder to always test against
the app's own configured host in this environment.)

Full regression re-run clean: all 36 suites (including the new one)
against an independently rebuilt database.

### D-106: the 6 no-mockup screens — consolidated dashboards, rating history, and platform-wide admin directories

Follow-on from D-105: the same coverage audit that found Lot Reach &amp;
Interest had no backend at all also flagged 6 more screens with
**neither a design nor a consolidated backend** — Buyer Dashboard,
Seller Dashboard, Rating History, Star Ratings, Lot Directory
(Custodian-facing, platform-wide), Trading Session Directory (same,
platform-wide). Unlike Lot Reach & Interest, none of these needed new
domain logic built from scratch — every number they show already
existed somewhere in a real table, just never read back in one place.
The project owner's instruction was explicit: "tackle the 6 no-mockup
screens next," treated as the scoping decision D-105's writeup said
these needed — build all 6, not extend one existing page per screen.

**What got built, and what it deliberately didn't**:

1. **Buyer Dashboard / Seller Dashboard** — new `DashboardService`
   (`buyerSummary()`/`sellerSummary()`), each a real, bounded read
   across the tables the existing My Bids/Offers/Purchases/Favorites
   (buyer side) and My Listings/Sales/Payout Bank/Invoices (seller
   side) pages already draw from — not a new source of truth, a
   consolidation. Each dashboard section links out to its own already-
   working full page rather than reinventing it. New routes
   `/my-buyer-dashboard`, `/my-seller-dashboard` on
   `MyActivityController`.
2. **Rating History** — `RatingEventModel::findForParty()`, one new
   method reading the `rating_event` table that BR-35/BR-36's approval
   workflow already writes a complete, permanent audit trail into. No
   page anywhere read it back for the party it actually happened to
   until now — every rating number shown elsewhere in the app was
   always just the current value, never its history. New route
   `/my-rating-history`.
3. **Star Ratings** — a dedicated page reading `party.star_rating`/
   `seller_star_rating` plus the existing shadow-ban/Crawl-Back fields
   (`shadow_banned_at_{buyer,seller}`,
   `crawl_back_{active,clean_completed,clean_required}_{buyer,seller}`)
   that BR-39's Crawl-Back enforcement already maintains but nothing
   ever surfaced on a page of its own. New route `/my-star-ratings`.
4. **Lot Directory / Trading Session Directory** — the real gap: the
   Custodian (Super Admin) had no way to browse every listing or sale
   event platform-wide, across every Tenant; only per-tenant pending-
   approval queues existed anywhere. New `AdminDirectoryService`
   (`findListings()`/`countListings()`/`findSaleEvents()`/
   `countSaleEvents()`, each with real server-side filters — free-text/
   tenant/format/status for listings, tenant/format/status for sale
   events) pulled out of `AdminController` specifically so the
   filtering logic is directly unit-testable without needing a real
   TOTP-based Super Admin HTTP login just to exercise it. New routes
   `/admin/lots`, `/admin/trading-sessions`, both `superAdmin`-filtered
   the same way every other admin route already is. Real pagination
   via the existing `Paginator` library, same pattern as every other
   filterable admin list in the app.

No new migrations — all 6 screens read tables that already existed.
All 6 wired into navigation: Star Ratings/Rating History added to
Profile's Account group, Buyer/Seller Dashboard added to the top of
Profile's Activity group, Lot Directory/Trading Session Directory
added to the Super Admin dashboard's action row — not left orphaned.

**Real bugs found while building this, not assumed away**:
1. First test-fixture mobile-number block (`+919777701xxx`) collided
   with `TestDispute.php`'s own fixtures — a guaranteed failure the
   instant both suites ran in the same shared-DB session, unrelated to
   any actual code defect. Fixed by picking an unused block
   (`+919777801xxx`), same category of fixture-collision bug this
   session has hit and fixed before (D-104's `TestChronicle`/
   `TestEasySchedule` collision).
2. The Trading Session Directory backfill's own test fixture tried to
   create a Tender sale event with `result_mode = 'seller_review'` —
   the real schema constraint only allows `instant_close` or
   `approval_required` (checked directly against the live
   `sale_event_result_mode_check` constraint, not assumed from memory).
   Test fixture fixed, not the schema.
3. `migrate:refresh` itself failed on this sandbox's database — an
   old down-migration (`AddStandingReviewToDispute`) can't roll back
   cleanly against data written after it (a NOT NULL constraint
   violation on its own rollback SQL), a pre-existing issue unrelated
   to this work. Verification proceeded by dropping and recreating the
   database directly (`DROP DATABASE`/`CREATE DATABASE`) plus a plain
   `migrate --all`, which is the same effective clean-slate state
   `migrate:refresh` would have produced.

**Verified for real, not assumed**: new suite `test:partydashboards`,
31/31 assertions — covering both dashboards' real numbers (and, for
each, that an item genuinely drops off its list once actually
rated/completed, not a static snapshot), rating history's real
ordering and cross-party isolation (another party's events never leak
into this party's history), and both admin directories' platform-wide
scope plus every real filter (free-text, tenant, format, status,
including combinations) against real fixture data spanning two
Tenants. Full regression re-run clean on an independently rebuilt
database: all 37 suites, only the pre-existing, already-diagnosed
`test:auditlog` gap (this sandbox's `.env` database is named
`ebidhub`, but that suite's own deliberate raw-tamper step hardcodes
`ebidhub_ci4` — a sandbox-naming mismatch, not a regression; passes
clean whenever the two names align, e.g. inside the CI workflow's own
`ebidhub_ci4`-named database).

Then a full real HTTP click-through on top of that: registered two
real parties from scratch (mobile → OTP → mPIN, the real flow, no
shortcuts), seeded real fixture data via direct model calls for one of
them (bid, offer, settlement, listing, rating event), and confirmed
all 4 party-facing pages render the real fixture content over a real
authenticated session — then confirmed all 4 redirect an unauthenticated
request to `/login`. For the two admin screens: granted `super_admin`
via the real CLI grant path, enrolled a real TOTP secret through the
actual `/admin/setup-totp` HTTP form (not a stub), generated a genuine
6-digit code from that secret using the same HMAC-SHA1/RFC-6238
algorithm `TotpService` itself implements, logged in through the real
isolated `/admin/login` TOTP-gated path, and confirmed both directory
pages render real platform-wide data plus correct filtering — then
confirmed both redirect a regular (non-Super-Admin) session back to
`/admin/login`, same as an unauthenticated one. `docs/design/
CLAUDE_DESIGN_HANDOFF.md` updated: §2 (the "6 screens, no backend at
all" list) is now empty — all 7 screens that were ever in that
category (Lot Reach & Interest plus these 6) are consolidated into one
"ready to design" section with the real field/route spec for each.

### D-107: BR-65 formally amended — API is now versioned (`/api/v1/`)

Not a repo-audit finding this time — a direct architectural policy
directive from the project owner (Chief Architect role prompt), stating
every API must be versioned, e.g. `/api/v1/`, and must never be exposed
unversioned. Checked it against the actual codebase before touching
anything, since a blind "yes, will comply" would have been dishonest:
that directive is in **direct, literal conflict** with BR-65's own text
in the governing document, `ADWITIX_Master.docx` — *"The API is not
exposed to Tenants with a visible version number."* This isn't a team
convention or an unbuilt spec; it was quoted verbatim, already
identified once before (D-93 confirmed this exact wording and fixed
D-89's build to match it by *removing* the `/v1/` segment).

Surfaced the conflict and the two ways to resolve it without silently
picking a side — real header/content-type versioning (keeps BR-65's
literal text true) vs. formally amending BR-65 in the master document
(reverses it). The project owner chose the latter: amend the governing
document.

**What changed:**
1. `docs/source-documents/ADWITIX_Master.docx` — BR-65's Statement and
   Logic/Rationale rewritten in place (direct XML edit via the docx
   skill, `merge_runs`/`validate.py`-checked, paragraph count unchanged
   at 1250 before/after). New Statement: the API **is** exposed with a
   visible version number in the URL (`/api/v1/...`); additive changes
   still ship within the current version with no prior notice; breaking
   changes now get a new version segment (`/api/v2/`) running alongside
   the old one for a notice period, instead of the old "parallel
   shapes, no version marker" mechanism. New Rationale records this as
   a Super-Admin-confirmed reversal, same style/precedent as BR-53's
   TDS-rate confirmation (D-71) and BR-68's app-wide scope confirmation
   (D-93) — a real decision recorded in the real governing document,
   not just in this changelog.
2. `app/Config/Routes.php` — all 6 Tenant API routes (`/api/oauth/token`,
   `/api/listings*`, `/api/sale-events/*`) renamed to `/api/v1/...`.
   Checked first for any other code referencing the literal old path
   strings (`ApiAuthFilter`, `ApiCredentialService`, views, the
   `test:tenantapi` suite, `Config/Filters.php`'s CSRF exclusion) — none
   found; the CSRF `except: ['api/*']` glob and `ApiAuthFilter` (which
   authenticates purely off the `Authorization: Bearer` header, never
   the URL) both needed zero changes. This was a genuinely contained,
   one-file code change once the document decision was made.

**Verified for real, not assumed**: `test:tenantapi` still 25/25 (it
calls controller methods directly, so it's insensitive to route-path
changes by design — confirms the reversal touched only routing, not
business logic). Then real HTTP, not just the suite: `POST
/api/oauth/token` (the old path) → **404**, confirming a genuine clean
cutover, not an alias left behind; `POST /api/v1/oauth/token` → real
`400 unsupported_grant_type` (the actual controller logic, not a stub);
`GET /api/v1/listings/{id}` unauthenticated → real `401` from
`ApiAuthFilter`, proving the filter is still correctly attached to the
new path. Full 36-suite regression on an independently rebuilt
database: clean, only the pre-existing, already-diagnosed
`test:auditlog` DB-naming gap.

**Explicitly out of scope for this decision**: the Chief Architect
directive's other items (WebSocket layer, Redis caching, the 17
controllers with direct DB access, bounded-context module structure,
and the net-new domain concepts — Wallet, Reverse Auctions,
Procurement, Warehouses, RVSFs, Financial Institutions, Service
Providers) were sized and sequenced but explicitly not started pending
their own individual decisions — BR-65 was the one item the project
owner asked to start with.

### D-108: WebSocket layer — corrected my own earlier audit, then extended real-time coverage to Buy-Now offers

Two distinct pieces. First, a correction to my own prior claim: when
sizing the Chief Architect directive's retrofit items, I reported "no
WebSocket layer exists at all." That was **wrong**. A real, working
sidecar (`realtime/server.js`, Node.js + the `ws` package) already
existed, built and documented in D-42/D-52 — I missed it because I only
searched `app/`, `composer.json`, and `public/`; the sidecar is a
deliberately separate process living in its own top-level `realtime/`
directory, and I never checked there. Caught it myself before building
anything, by re-verifying the audit rather than proceeding on the
earlier claim — same standard this project has held throughout.

Re-verified the existing sidecar genuinely still works, not just
trusted the historical record: `npm install` clean, started the
sidecar, connected a real WebSocket client to a `sale_event:<id>` room
and a `buyer:<id>` room, sent real broadcasts to both, confirmed
correct receipt on both, then cleaned up. Confirmed coverage as it
actually stands: `bid_placed`/`ticker_bid_update`/`dynamic_time_update`
are wired across all three bidding formats (Easy/Express/Tender).
**Buy-Now had zero broadcast coverage** — `OfferService` never called
`RealtimeBroadcastService` at all, the one sale format left out.
Settlement, Dispute, Rating, EMD cascade, and Admin actions are also
still unwired — flagged, not silently assumed covered, and left for
their own future decision.

**Found and deliberately avoided worsening a real, pre-existing privacy
gap while designing this**: `ListingController::show()` renders every
submitted Buy-Now offer's real amount and status to **any visitor** of
the listing page — not gated to the seller (`$offers` is populated
whenever `sale_format === 'buy_now'`, with no `$isOwner` check
anywhere around it). This predates D-108 and wasn't fixed here — fixing
an existing page's access control is a distinct, more invasive change
than what was asked for. What D-108 does guarantee: the new WebSocket
broadcasts never make this worse. The `sale_event:<id>` room (watched
by any visitor, same audience as that leaky page) only ever receives
an amount-free `offer_submitted` signal (`{offerCount}`); the real
offer amount is broadcast only to `broadcastToBuyer($sellerPartyId,
'offer_received', ...)` — the seller's own private party channel.

**Reused existing infrastructure instead of building parallel
machinery**: `RealtimeBroadcastService::broadcastToBuyer()` already
targets an arbitrary party ID, not literally only buyer-role parties —
every logged-in party already holds this exact connection open via
`layouts/main.php`'s global Live Ticker script (`?buyerId=<their own
party ID>`), buyer or seller alike. Rather than opening a second
sidecar connection or adding a `seller:<id>` room type, `OfferService`
calls the same method as-is; `layouts/main.php`'s existing socket
handler gained one new branch that relays `offer_received` as a
`window.dispatchEvent(new CustomEvent('ebidhub:offer_received', ...))`
for whichever page cares — `listing/show.php` listens for it only when
`$isOwner` is true, no second connection, no new sidecar code at all.

**Events added**:
- `OfferService::submitOffer()` — `offer_submitted` (amount-free) to
  the sale_event room; `offer_received` (real amount) to the seller's
  party channel.
- `OfferService::acceptOffer()` — `offer_accepted` (amount included) to
  the sale_event room, matching the existing `bid_placed` precedent
  that a closed sale's winning amount is public; `ticker_bid_update`
  (the existing, already-handled event type) to the winning buyer's
  party channel, so their Live Ticker refreshes without a manual poll.

**Verified with a real, complete end-to-end test, not a mocked one** —
same discipline D-42 itself set: started the sidecar and the PHP app
together, created a real Buy-Now sale event and EMD hold through the
real `OfferService`, connected three genuine WebSocket clients (a
separate Node.js process, not the server) — one on the `sale_event`
room, one on the seller's party room, one on the buyer's party room —
then called `submitOffer()` and `acceptOffer()` for real. Confirmed
exactly the right client received exactly the right payload each time:
the sale_event room got `offer_submitted {offerCount:1}` then
`offer_accepted {amount:92000}`; the seller's room alone got
`offer_received` with the real amount; the buyer's room got
`ticker_bid_update`. The sale_event room never once received the raw
amount before acceptance — the privacy boundary held under a real
test, not just by code inspection. All throwaway test files (a Node
WS client, a two-mode spark command) deleted after use.

Full regression: 36 real suites clean on an independently rebuilt
database (only the pre-existing `test:auditlog` DB-naming gap);
`test:buynow` specifically still 16/16 — the new broadcast calls sit
outside the transactional logic path entirely and, like every other
`RealtimeBroadcastService` call site, fail silently by design if the
sidecar is unreachable.

**Still explicitly open, not silently assumed covered**: Settlement,
Dispute, Rating, EMD cascade defaults, and Admin actions (Emergency
Stop, delisting) still have zero broadcast coverage; the pre-existing
offer-amount page-leak in `ListingController::show()` is unfixed and
was deliberately left alone, flagged for its own decision.

### D-109: WebSocket coverage extended to Settlement (dual-NOC, ratings, completion)

Continuing the WebSocket retrofit item (D-108 did Buy-Now offers) onto
the next explicitly-flagged gap: Settlement. A settlement's four-step
gate (`SettlementService::confirmSellerNoc`, `confirmBuyerNoc`,
`submitRating` ×2, plus the administrative `forceResolveStalled`) all
funnel through one private method, `checkCompletion()` — every one of
those five call sites was a silent full-page-reload wait before this,
even though the counterpart's action can matter within seconds (a
buyer confirming receipt of goods, unblocking the seller's own NOC
step and eventual EMD release).

**Design**: unlike Buy-Now's listing page, a settlement is a private,
two-party document — no "any visitor" audience exists for it the way
the sale_event room serves a public listing page. So this doesn't
touch the sale_event room at all. `checkCompletion()` broadcasts one
`settlement_updated` event, unconditionally, at the end of every call
(every call already represents a just-applied change made by its
caller) — sent via `RealtimeBroadcastService::broadcastToBuyer()` to
both the buyer's own party channel and the seller's own party channel,
each already open for their Live Ticker (buyer:<partyId> — reused,
not a new room type, same reuse precedent as D-108). The payload
carries the settlement's full current gate state (status + all four
booleans), not just a delta, so either party's client always has a
complete picture regardless of which action triggered it.

**Client side**: `layouts/main.php`'s existing WS handler gained one
more relay branch — `settlement_updated` → `CustomEvent
ebidhub:settlement_updated` — exactly the D-108 offer_received relay
pattern, no new WebSocket connection. `settlement/show.php` listens
for that event, and only if it matches the settlement currently being
viewed, does a full page reload after a short (1.2s) delay with a
small "Updated by the other party — refreshing…" banner first. A full
reload rather than a DOM patch was the deliberate choice here: this
page has several server-rendered blocks that only appear once
specific conditions are met (invoices, TDS line, Trading Session
Chronicle, the stalled-state force-resolve panel) — re-deriving that
in JS would duplicate business/rendering logic the architecture
directive explicitly warns against (D-109 follows the same "controllers
orchestrate, don't push business logic into the client" reasoning
already applied throughout this project). The reload guarantees the
next render is exactly what `SettlementService` just decided, with zero
duplicated logic.

**Verified with a real end-to-end test**, same discipline as D-108: a
throwaway spark command drove a real Buy-Now sale through
`OfferService` to a real pending settlement, then called
`confirmSellerNoc`, `confirmBuyerNoc`, `submitRating` (buyer),
`submitRating` (seller) in sequence against the real
`SettlementService`. Two genuine WebSocket clients (separate Node.js
processes) — one on the buyer's party channel, one on the seller's —
both received all four `settlement_updated` broadcasts, each with the
correct incrementally-updated payload, the final one showing
`status: "completed"`. Both throwaway files (a Node WS test client, a
spark command) deleted after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:settlement` itself 23/23); the one non-pass is the
same pre-existing `test:auditlog` DB-naming gap (`ebidhub` vs.
`ebidhub_ci4`) flagged as a known non-issue since earlier in this
session, not a regression from this change.

**Still explicitly open, not silently assumed covered**: Dispute,
Rating (outside the settlement flow itself), EMD cascade defaults, and
Admin actions (Emergency Stop, delisting) still have zero broadcast
coverage; the pre-existing offer-amount page-leak in
`ListingController::show()` remains unfixed and flagged, unchanged by
this work.

### D-110: WebSocket coverage extended to Dispute (filing, evidence, ruling, appeal)

Third flow in the WebSocket retrofit sequence (D-108 Buy-Now, D-109
Settlement, now Dispute). `DisputeService` has no single funnel method
the way `SettlementService::checkCompletion()` does — five separate
lifecycle methods each mutate state independently: `fileDispute`,
`submitEvidence`, `ruleOnDispute`, `fileAppeal`, `ruleOnAppeal`. Added
one private helper, `broadcastDisputeUpdate()`, called from the end of
all five, rather than five different ad-hoc broadcast blocks — same
"single point, multiple call sites" shape D-108 used across
`submitOffer`/`acceptOffer`.

**Design, consistent with D-109's reasoning**: a dispute is a private
document between exactly two parties — the filer and the respondent —
so this never touches a sale_event room, only their own
`buyer:<partyId>` channels (the same generic per-party room every
logged-in party, including a Tenant/Super Admin acting on their own
account, already holds open — confirmed via `AuthorizationService`
that admins genuinely are parties with a `party_id` and an active role
flag, not a separate identity type, so no special-casing was needed).
Payload carries the dispute's full current state (status, ruling
outcome, ruling authority type) on every broadcast, not a delta.

**A gap surfaced and deliberately not built**: there's no live "a new
dispute needs your ruling" nudge to whichever Tenant/Super Admin will
eventually rule on it, because reaching "every party currently holding
role X for tenant Y" isn't something the existing sidecar can address
— it only has a single sale_event room and single per-party rooms, no
role-broadcast room type. Building that would be genuinely new sidecar
scope, not a reuse of what's there, so it wasn't improvised into this
change. Flagged in `docs/BR_PR_AUDIT.md`, not fixed here.

**Client side**: `layouts/main.php` gained one more relay branch
(`dispute_updated` → `CustomEvent ebidhub:dispute_updated`), and
`dispute/show.php` gained the same banner-then-reload listener pattern
as `settlement/show.php` (D-109) — this page's evidence list, ruling
panel, appeal panel, and closed-state panel are all conditionally
server-rendered on the dispute's status, so a full reload avoids
re-deriving that logic in JS.

**Verified with a real end-to-end test**: a throwaway spark command
drove a real Buy-Now sale to a real dispute through the actual
`DisputeService` — `fileDispute` → `submitEvidence` → `ruleOnDispute`
→ `fileAppeal` → `ruleOnAppeal` — while two genuine WebSocket clients
(separate Node.js processes) on the buyer's (filer's) and seller's
(respondent's) party channels both received all 5 `dispute_updated`
broadcasts with correctly changing state, ending at `status: closed`.
Both throwaway files (a Node WS client, a spark command) deleted after
use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:dispute` itself 21/21); the one non-pass is the same
pre-existing `test:auditlog` DB-naming gap, not a regression.

**Still explicitly open, not silently assumed covered**: Rating
(outside the settlement/dispute flows), EMD cascade defaults, and
Admin actions (Emergency Stop, delisting) still have zero broadcast
coverage; the pre-existing offer-amount page-leak in
`ListingController::show()` remains unfixed and flagged; the
role-broadcast gap noted above (no live nudge to admins with pending
rulings) is new to this decision and also open.

### D-111: WebSocket coverage extended to Rating (upgrades, applied downgrades, forced-neutral, Crawl-Back completion)

Fourth flow in the retrofit sequence (D-108 Buy-Now, D-109 Settlement,
D-110 Dispute, now Rating). Unlike those three, Rating's own state
changes were already indirectly wrapped by `settlement_updated` and
`dispute_updated` when a rating action happens *through* those flows —
but the party's actual star-rating number changing was never itself
broadcast, and several genuine standalone rating paths exist entirely
outside Settlement/Dispute: BR-36's approval queue
(`RatingReviewController`/`admin/rating_reviews.php`, closing a
pre-existing gap where pending downgrades had no real approval UI),
BR-39's forced-neutral pattern trigger, and BR-38's Crawl-Back
completion restore. None of those had any live signal to the affected
party at all.

**What actually changed vs. what didn't**: `RatingService` has no
single funnel either (same shape as Dispute, D-110) — a new
`broadcastRatingUpdate()` helper is called from exactly the 4 places a
party's rating number itself changes: `applyUpgrade()` (BR-36,
no-approval-needed), `approveDowngrade()` (but only inside the
`readyToApply` branch — a downgrade sitting in
`pending_tenant_approval` or `pending_super_admin_approval` produces
no broadcast, since nothing about the party's own visible rating has
changed yet), `applyForcedNeutral()` (BR-39), and the Crawl-Back
restore branch inside `recordCleanTransactionForCrawlBack()` (BR-38).
`initiateDowngrade()` itself deliberately never broadcasts — it's
always immediately followed by either an approval-queue wait
(genuinely nothing changed for the party yet) or, in the
self-approving Super Admin paths (`DisputeService::executeRuling`,
`RatingService::delistSellerForFraud`), by an `approveDowngrade()`
call that does broadcast once it actually lands.

**Design**: reused the exact same pattern as D-108/109/110 — the
party's own `buyer:<partyId>` channel, no sale_event room, no new
sidecar room type. Since this event only ever reaches the one party it
happened to, the payload doesn't need an id to match against (unlike
`settlement_updated`/`dispute_updated`, which carry
`settlementId`/`disputeId` so the client can ignore events for a
*different* record) — any receipt on `/my-star-ratings` or
`/my-rating-history` is automatically about the viewer's own account.

**A gap surfaced and deliberately not built, same shape as D-110's**:
no live "a new pending downgrade is in your queue" nudge to the
Tenant/Super Admins who staff `admin/rating_reviews.php` — that queue
is shared across everyone with the relevant role, and reaching "every
party holding role X for tenant Y" is the same missing sidecar
room-type gap flagged in D-110, not solved here either.

**Verified with a real end-to-end test**: a throwaway spark command
drove a real party through `applyUpgrade` → `initiateDowngrade` +
`approveDowngrade` (single-tier, since the resulting value stayed
above the 2.0 dual-approval line) → `applyForcedNeutral` via the
actual `RatingService`, while one genuine WebSocket client on that
party's own channel received all 3 `rating_updated` broadcasts with
the correct `eventType` and correctly changing `previousValue`/
`newValue` pairs (3.0→3.2 upgrade, 3.2→2.7 downgrade, 2.7→3.0
forced_neutral). Throwaway files (a Node WS client, a spark command)
deleted after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:rating` itself 28/28); the one non-pass is the same
pre-existing `test:auditlog` DB-naming gap, not a regression.

**Still explicitly open, not silently assumed covered**: EMD cascade
defaults and Admin actions (Emergency Stop, delisting) still have zero
broadcast coverage; the pre-existing offer-amount page-leak in
`ListingController::show()` remains unfixed and flagged; the
role-broadcast gap (no live nudge to admins with pending dispute
rulings or rating approvals) noted in D-110 is now confirmed to apply
identically to the rating review queue and remains open.

### D-112: WebSocket coverage extended to EMD cascade defaults (BR-28) — and a real pre-existing gap surfaced along the way

Fifth flow in the retrofit sequence (D-108 Buy-Now, D-109 Settlement,
D-110 Dispute, D-111 Rating, now the EMD cascade). `CascadeService`
gained three broadcast points:

- `openTopupWindow()` — the single funnel both `initiateCascade()`
  (step 1, a fresh cascade opening) and `processDefault()`'s
  baton-pass branch (step 2/3) already call — now sends a public,
  amount-free `cascade_topup_window_opened` (cascade step + deadline)
  to the sale_event room, and a private `cascade_your_turn` (same
  payload plus `saleEventId`) to the new top holder's own party
  channel. This is the single highest-value signal in the whole
  cascade flow — the window is short, and the bidder who's now on the
  clock has no other way to learn it without polling.
- `processDefault()` — a public `cascade_defaulted` (cascade step +
  `outcome`: `baton_passed` or `full_cascade_failure`), amount-free and
  identity-free like `offer_submitted` (D-108); the "who's now on the
  clock" detail is deliberately left to `openTopupWindow()`'s own
  broadcasts, not duplicated here.
- `processTopupPaid()` — a public `cascade_topup_paid` with the final
  amount, same "terminal outcome is public" precedent as
  `offer_accepted` (D-108) — every bid amount on Easy/Express is
  already public in real time via `bid_placed`, so this adds no new
  privacy exposure.

Client side: `listing/show.php`'s existing sale_event-room handler
gained three more amount-free branches (in-place status/price text
update, same as `bid_placed`/`dynamic_time_update` — no reload, this
is the live auction view). `layouts/main.php` relays
`cascade_your_turn` via the same `CustomEvent` pattern as every prior
private event; `listing/show.php` listens for it unconditionally
(unlike `offer_received`, the recipient here is typically a visiting
bidder, not the listing's owner) and filters by matching
`e.detail.saleEventId` against the page's own sale event, since a
party can hold this same per-party channel open while browsing an
entirely unrelated listing.

**A real, pre-existing gap surfaced while wiring this — flagged
prominently, not buried**: `CascadeService::processDefault()` and
`::processTopupPaid()` currently have **zero real call sites anywhere
in the running application** — confirmed by grepping the entire
`app/` tree outside of test commands. `SchedulerService` only ever
calls `initiateCascade()` (from `processExpiredExpressBidding()` and
`processExpiredEasyAuctions()`, both real and scheduler-driven); there
is no scheduler method that polls for an expired, unpaid top-up window
and calls `processDefault()`, and no controller route lets a bidder
submit "I paid the top-up" to trigger `processTopupPaid()`. In other
words: **today, a cascade can open (a bidder gets told they owe a
top-up) but nothing in the running system can ever close it** — not a
default, not a successful payment. This is a functional gap in BR-28's
own implementation, independent of and larger than WebSocket coverage,
and out of scope for this decision to silently fix. The new broadcasts
above are real, tested against the actual service methods via
`test:cascade`/`test:express` and a live end-to-end WS run, and will
fire correctly the moment this wiring gap is closed — but until then
they are unreachable in production. Flagged here and in
`docs/BR_PR_AUDIT.md` for the project owner's prioritization, the same
treatment given to the SMS-provider and payment-gateway gaps.

**Verified with a real end-to-end test**, working within that
constraint by calling the service methods directly (exactly how
`test:cascade` itself already does, since that's the only way to
exercise this code at all today): a throwaway spark command drove a
real Easy Auction with 3 bidders through `initiateCascade()` →
`processDefault()` (H1 defaults, baton passes to H2) →
`processTopupPaid()` (H2 pays), while three genuine WebSocket clients
— one on the public sale_event room, one on H1's own channel, one on
H2's own channel — confirmed: the public room received all 4
broadcasts in the correct order (`cascade_topup_window_opened` step 1
→ `cascade_defaulted` baton_passed → `cascade_topup_window_opened`
step 2 → `cascade_topup_paid` amount 130000); H1 received exactly one
private `cascade_your_turn` (step 1) and nothing else; H2 received
exactly one private `cascade_your_turn` (step 2) and nothing else —
confirming the privacy boundary (each bidder's own nudge stays theirs
alone) held under a real test. Throwaway files (3 Node WS clients, a
spark command) deleted after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:cascade` 22/22, `test:express` 16/16 — the format most
exercising this code path); the sole non-pass is the same pre-existing
`test:auditlog` DB-naming gap, not a regression.

**Still explicitly open, not silently assumed covered**: Admin actions
(Emergency Stop, delisting) still have zero broadcast coverage; the
pre-existing offer-amount page-leak in `ListingController::show()`
remains unfixed and flagged; the role-broadcast gap for admin queues
(dispute rulings, rating approvals) remains open; and now this
decision's own new finding — the missing scheduler/controller wiring
for cascade default/top-up-paid — is a real, separate, larger gap in
BR-28 itself, not a WebSocket-coverage item, surfaced for the project
owner to prioritize.

### D-113: BR-28 cascade wiring gap closed — real scheduler trigger + real bidder route, plus a second gap found and fixed

Direct follow-up to D-112's own finding: `CascadeService::processDefault()`
and `::processTopupPaid()` were fully correct but had zero real call
sites anywhere in the running application. Closed both halves.

**Scheduler side**: `BidModel::findExpiredUnpaidTopups()` — a real
query (`topup_required_by` past, `topup_paid_at` still null,
`defaulted_at` still null), naturally idempotent since a processed bid
stops matching on the next sweep. `SchedulerService::processExpiredCascadeTopups()`
polls it and calls `CascadeService::processDefault()` for each match,
skipping (not crashing the whole run) on a per-item `RuntimeException` —
same defensive shape as every other scheduler method. Wired into
`runAll()` right alongside `processExpiredExpressBidding()`/
`processExpiredEasyAuctions()` — the two methods that *open* a
cascade; this is what actually *closes* the loop they start. `run:scheduler`
(the real cron entry point, per `RunScheduler.php`) now reports a
`Cascade top-ups defaulted:` count.

**Controller/route side**: `BidController::devPayTopup()` — a
DEV-ONLY simulated top-up payment, same convention as the existing
`devFundEmd` (the real flow routes through the same not-yet-integrated
payment gateway). Resolves the caller's own open top-up window via
`BidModel::findOpenTopupForBidder()` — never trusts a bid ID from
client input — and rejects a window that's already expired (BR-28)
rather than silently succeeding if the scheduler hasn't swept it yet.
Routed at `POST /sale-events/(:segment)/dev-pay-topup`.
`ListingController::show()` now resolves the viewer's own open top-up
(if any) and the real amount owed (via `EmdService::calculateCascadeTopupOwed`
against their actual held EMD, not just their bid amount) and passes
both to the view; `listing/show.php` renders a "You're on the clock"
panel with a real Pay Top-Up button when applicable.

**A second real, pre-existing gap found and fixed while finally
exercising `processTopupPaid()` for the first time through a real
flow**: it never updated `sale_event.current_price`/
`current_high_bidder_party_id` on a cascade close — both stayed stuck
at whatever the last live bid happened to be (the since-defaulted
H1's), not the actual winning bidder's price. Confirmed with a real
page load: before the fix, the listing page showed ₹140,000 (H1's
stale bid) even though the real settlement was correctly ₹130,000
(H2's actual winning price); after adding the same
`updateCurrentPrice()` call `OfferService::acceptOffer()` already
makes for Buy-Now, the page correctly showed ₹130,000. This was a
real, user-visible pricing bug, not a WebSocket-coverage item —
found only because this was the first time `processTopupPaid()` had
ever actually run end-to-end in this codebase's history.

**Verified with a genuinely real, not mocked, end-to-end flow** — the
most rigorous verification in the WebSocket-retrofit sequence so far:
a real party account created through the actual HTTP registration
flow (mobile → OTP shown in dev mode → mPIN → session), not a
model-created fixture; a fixture built around that real party ID for
the supporting actors (tenant, seller, other bidders — unrelated to
what's under test); the real `php spark run:scheduler` CLI entry
point (not `SchedulerService::processExpiredCascadeTopups()` called
directly) confirmed the default via `Cascade top-ups defaulted: 1`;
the real HTTP page showed the "Pay Top-Up (₹3,000.00 owed on your
₹130,000.00 bid)" panel with the correct owed amount; a real POST
(genuine session cookie, genuine rotated CSRF token) to
`/sale-events/{id}/dev-pay-topup` returned a 303 redirect with no
error; the DB and a subsequent real page load both confirmed
`topup_paid_at` set, `status = closed_sold`, `current_price = 130000`,
a real settlement row. Re-ran the whole sequence a third time with a
real WebSocket client on the public sale_event room attached
throughout: `cascade_defaulted` → `cascade_topup_window_opened` →
`cascade_topup_paid` (amount 130000) all arrived in order, sourced
from the real scheduler run and the real HTTP payment, not direct
service calls. All throwaway files (a spark fixture-setup command, a
Node WS client) deleted after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:cascade` 22/22, `test:express` 16/16, `test:scheduler`
14/14 — the last one specifically exercising `runAll()`'s full key
set, confirming the new method didn't disturb it); the sole non-pass
is the same pre-existing `test:auditlog` DB-naming gap, not a
regression.

**BR-28's cascade default/top-up-paid coverage is now genuinely live in
production**, not merely correct-but-unreachable as D-112 left it.
Still explicitly open, unrelated to this decision: Admin actions
(Emergency Stop, delisting) still have zero broadcast coverage; the
pre-existing offer-amount page-leak in `ListingController::show()`
remains unfixed and flagged; the role-broadcast gap for admin queues
(dispute rulings, rating approvals) remains open.

### D-114: WebSocket coverage extended to Admin actions (Emergency Stop, seller delisting) — closes the original real-time-coverage sweep

Last item on the real-time coverage list first identified during the
Chief Architect directive's retrofit sizing (D-108 Buy-Now, D-109
Settlement, D-110 Dispute, D-111 Rating, D-112/D-113 EMD cascade, now
Admin actions). Two distinct actions, two different audience shapes.

**`ListingLifecycleService::emergencyStop()` (BR-14)**: the private
`releaseAllHoldsForSaleEvent()` helper now returns the released holds'
own party IDs instead of just a count, so `emergencyStop()` can notify
each one individually. Two broadcasts: a public, amount/reason-free
`sale_event_emergency_stopped` to the sale_event room (the reason
isn't shown anywhere in the UI today even to a logged-in visitor, so
it isn't broadcast either — consistent with never exposing more than
the synchronous page itself reveals), and a private `emd_released` to
each affected bidder's own party channel.

**`RatingService::delistSellerForFraud()` (BR-38)**: a private
`seller_delisted` broadcast to the delisted seller's own channel —
severe enough that they need to know immediately. No public broadcast:
there's no existing page that surfaces a seller's delisted status to
visitors, so this doesn't introduce one.

**Client side, a new pattern this decision needed**: unlike every
prior private event (offer_received, settlement_updated,
dispute_updated, rating_updated, cascade_your_turn), `emd_released`
and `seller_delisted` are genuine account-level notices with no
specific page to relay a CustomEvent to — the affected party could be
anywhere on the site when either fires. `layouts/main.php` now renders
them directly into a new global banner (`#global-account-banner`,
fixed-position, visible on any logged-in page) via a small
`showGlobalBanner()` helper, rather than dispatching an event nobody
would catch. `listing/show.php` gained one more amount/reason-free
branch for the public `sale_event_emergency_stopped` signal, same
in-place status-text pattern as every other public sale_event event.

**A gap noted, not fixed, adjacent to delisting**: `delistSellerForFraud()`
suspends every active `listing` a fraud-confirmed seller has, but
never touches the `sale_event` table — an active sale_event tied to
one of those listings is left dangling at `status = 'active'` with a
now-suspended listing, rather than being emergency-stopped itself.
Whether a confirmed-fraud delisting should automatically cascade into
emergency-stopping every one of that seller's live auctions is a real
business-rule question (BR-38's own text doesn't say), not a
WebSocket-plumbing one — flagged for its own decision, not decided
here.

**Verified with a real end-to-end test**: a throwaway spark command
drove a real Easy Auction with two live bidders through
`ListingLifecycleService::emergencyStop()` via the actual service;
three genuine WebSocket clients — one on the public sale_event room,
one on each bidder's own party channel — confirmed the public room
got exactly `sale_event_emergency_stopped` and each bidder got exactly
their own `emd_released`, nothing more, nothing less. A second run
drove a real Super Admin through `RatingService::delistSellerForFraud()`
against a real seller party; the seller's own channel received both
`rating_updated` (the confirmed-fraud reset-to-1★ downgrade, D-111)
and `seller_delisted` in sequence — a useful incidental confirmation
that D-111's and D-114's broadcasts compose correctly on the same real
action, not just individually. Throwaway files (a Node WS client, a
spark command) deleted after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:lifecycle` 22/22 covering Emergency Stop,
`test:br35` 27/27 covering the delisting/fraud path); the sole
non-pass is the same pre-existing `test:auditlog` DB-naming gap, not a
regression.

**The original real-time-coverage sweep identified during retrofit
sizing is now complete**: bids (Easy/Express/Tender, pre-existing
D-42/D-52), Buy-Now offers (D-108), Settlement (D-109), Dispute
(D-110), Rating (D-111), EMD cascade (D-112, wired live D-113), and
now Admin actions (D-114) are all covered. Still explicitly open, not
part of this sweep: the role-broadcast gap (no live nudge to admins
staffing the dispute-ruling or rating-approval queues, D-110/D-111);
the pre-existing offer-amount page-leak in `ListingController::show()`
(D-108); and this decision's own new finding — whether fraud delisting
should cascade into emergency-stopping active auctions.

### D-115: Event-Driven Design — a first slice of a real domain-event layer

First step on the Principal Architect directive's Event-Driven Design
item, the directive's biggest gap that had nothing built against it at
all. Rather than building a bespoke event bus, found that CodeIgniter
4 already ships a real, production-grade one — `CodeIgniter\Events\Events`
(`Events::on()` to subscribe, `Events::trigger()` to publish, priority-
ordered, synchronous in-process) — used only for framework-internal
hooks (`pre_system`, the debug toolbar) in this codebase's entire
history. Building on what's already there rather than inventing a
parallel system is exactly the directive's own "evolve the system,
don't propose a rewrite unless absolutely necessary."

**What was built, deliberately scoped as a first slice, not the full
catalog**:

- `App\Libraries\DomainEvents` — a small, centralized catalog of named
  event identifiers (`AUCTION_CREATED`, `BID_PLACED`,
  `SETTLEMENT_COMPLETED`, `KYC_APPROVED`, `DISPUTE_FILED`), 5 events
  chosen directly from the directive's own examples
  (`AuctionCreated, BidPlaced, PaymentReceived, KYCApproved`). No real
  payment gateway exists yet (a separate, already-tracked gap), so
  `SETTLEMENT_COMPLETED` stands in for `PaymentReceived` — the closest
  genuine "money changed hands" milestone this platform has.
- Real publish points wired into the exact business-logic completion
  moment in 5 existing services — purely additive, zero existing
  behavior changed:
  - `ListingLifecycleService::approveSaleEvent()` → `AuctionCreated`,
    right alongside the existing `sale_event.created` tenant webhook
    (same milestone, different audience: the webhook notifies an
    external Tenant integration, the domain event notifies
    in-process/future internal consumers).
  - `BiddingService::placeBid()` → `BidPlaced`, right alongside the
    existing `bid.placed` audit log entry.
  - `SettlementService::checkCompletion()` → `SettlementCompleted`,
    fired only inside the genuine-completion branch (unlike D-109's
    WS broadcast in the same method, which fires on every call
    representing partial progress too) — a domain event should mean
    the milestone actually happened, verified explicitly: the test
    suite confirms zero events fire after 1-of-4 settlement steps.
  - `KycService::reviewDossier()` → `KYCApproved`, only on the
    `$approve === true` branch — verified explicitly that a
    suspension never fires it.
  - `DisputeService::fileDispute()` → `DisputeFiled`, alongside the
    existing `dispute.filed` tenant webhook.
- `App\Libraries\DomainEventLogListener` + a new `domain_event_log`
  table (migration + `DomainEventLogModel`) — the first real,
  genuinely decoupled consumer: persists every fired event with zero
  knowledge of which service published it, registered against each
  event name in `app/Config/Events.php`, not called directly by any
  publisher. Deliberately distinct from `audit_log` (D-16/D-45's
  hash-chained, actor-driven compliance ledger) — this is a plain
  technical event store, existing purely so a future consumer
  (analytics, an AI Gateway hook, a real notification queue) can
  subscribe to the same event names without any publisher or this
  listener needing to change.

**What this deliberately does NOT do, flagged rather than silently
assumed**:
- Only 5 events exist. The directive's own vision — every business
  capability (Create Auction, Approve Seller, Verify KYC, Generate
  Settlement, Resolve Dispute, ...) publishing its own event — is a
  much larger inventory this decision starts, not completes.
- All consumption is synchronous, in-process, same request. A real
  queue-backed async listener needs the Background Jobs infrastructure
  item, which doesn't exist yet (also flagged in the directive, also
  not built) — `Events::trigger()` itself doesn't block on I/O today
  since its one real listener is a fast local DB insert, but this
  doesn't scale to a slow consumer (e.g. an outbound AI call) without
  queueing first.
- Existing direct `AuditLogService::log()` calls (~15+ call sites) and
  `TenantWebhookService::fire()` calls were NOT migrated to be
  event-driven consumers. That's a much larger, higher-risk refactor
  of already-tested, working code for no functional gain on its own —
  deliberately out of scope for a first slice. The new domain events
  were added alongside the existing direct calls, not as a replacement
  for them.

**Verified with a real, permanent test suite** (`test:domainevents`,
now part of the standing 37+1-suite regression, not a throwaway):
drives each of the 5 real service methods through real fixtures and
asserts the `domain_event_log` table gained exactly one correctly-shaped
row per genuine milestone — including two explicit negative
assertions (no `SettlementCompleted` after partial progress, no
`KYCApproved` on a suspension) proving the events fire on the actual
milestone, not just "some method got called." A final canary check
proves the underlying `Events::on()`/`Events::trigger()` wiring itself
works, independent of this suite's own domain-specific assertions. All
18 assertions pass; spot-checked the real `domain_event_log` rows
directly via `psql` — correct event names, correct JSONB payloads.

Full regression: 37/38 real suites clean on an independently rebuilt
database (`test:domainevents` itself 18/18, newly added to the
standing suite list); the sole non-pass is the same pre-existing
`test:auditlog` DB-naming gap, not a regression.

**Still explicitly open**: the full business-capability event catalog;
queue-backed async consumers (depends on Background Jobs, not built);
migrating existing direct `AuditLogService`/`TenantWebhookService`
call sites to be event-driven (a separate, larger, higher-risk
refactor); an AI Gateway abstraction to actually consume these events
(also not built, also flagged in the directive).

### D-116: fixed the ListingController::show() offer-amount privacy leak

Closes the one item explicitly flagged as a genuine pre-production
concern during a readiness check: `ListingController::show()` was
populating `$offers` (every submitted Buy-Now offer, with its real
amount and per-buyer status) unconditionally whenever the sale format
was `buy_now` — with no gate on who was viewing the page. Any
anonymous visitor, or any other logged-in party who wasn't the seller,
could see every offer's real amount on the public listing page. Found
incidentally while designing D-108's WebSocket broadcasts (which
already got this exact boundary right — the real amount only ever
reaches the seller's own party channel), flagged at the time rather
than fixed, and left open through D-107–D-115 as a known, tracked gap.

**Fix**: the controller now only populates `$offers` at all when
`session()->get('logged_in_party_id') === $listing['seller_party_id']`
— the exact same boundary the WebSocket layer already enforces, so the
static page render and the live updates now agree. Added a second,
defense-in-depth gate in the view itself (`$isOwner && !empty($offers)`)
so the block can never render real amounts even if the controller-side
gate is ever loosened by a future edit without someone re-checking
this history. As a side effect, the "Accept" form (which was
previously rendered to any visitor, though the controller's own
`OfferController::accept()` already correctly 403s a non-seller caller
— confirmed by reading it, not assumed) no longer renders to anyone
but the actual seller either, closing a UI/authorization-surface
mismatch even though the underlying write path was never actually
exploitable.

**Verified with real HTTP, three distinct viewer identities**: built a
real Buy-Now sale event with a real submitted offer, with the seller
account created through the actual HTTP registration flow (mobile →
OTP → mPIN → session), not a model-created fixture. Fetched the same
listing page three ways — anonymous (no session cookie), a second
real registered party who is not the seller, and the real seller —
and confirmed by direct string search on the raw HTML: the offer
amount and "Offers Received" section appear in neither the anonymous
nor the other-party response, and appear correctly (with the exact
real amount) only in the seller's own response. Deleted the throwaway
fixture command after use, confirmed gone via `ls`.

Full regression: 36/37 real suites clean on an independently rebuilt
database (`test:buynow` itself 16/16 — the format this fix touches
most directly); the sole non-pass is the same pre-existing
`test:auditlog` DB-naming gap, not a regression.

This closes the last item from the pre-production readiness review
that wasn't already covered by the WebSocket retrofit or accepted as
an external-dependency gap. Still explicitly open, unrelated to this
fix: the role-broadcast gap for admin queues (D-110/D-111); whether
fraud delisting should cascade into emergency-stopping active auctions
(D-114); Event-Driven Design remains a first slice, not the full
catalog (D-115, on its own unmerged branch).

### D-117: BR-52/PR-30 Chargeback Handling & Representment

The project owner asked for a formal Screen Completeness Audit
(`docs/SCREEN_COMPLETENESS_AUDIT.md`), then asked to build "Tier 1" of
its resulting scoped backlog. This is the first, largest item: BR-52/
PR-30 had zero backend of any kind — the only prior trace was a
rating-penalty *category*
(`RatingService::NAMED_EVENTS['star_rating']['chargeback_against_approved_forfeiture']`)
that had never had a real caller, a gap the audit itself surfaced.

**Built**: a new `chargeback_case` table and `ChargebackCaseModel`/
`ChargebackService`/`ChargebackController`, following the same
migration→service→admin-queue-controller→view shape already
established for AML Monitoring (`AmlFlagModel`/`AmlMonitoringService`)
— reused deliberately rather than inventing a new pattern. Each case
tracks two genuinely independent tracks, per PR-30 step 193's explicit
"independent of the representment outcome" framing:

1. **Representment**: filing (`ChargebackService::fileChargeback()`)
   auto-assembles a real evidence package in the same call — the
   actual, previously-recorded EMD-pledge consent record (BR-51), the
   real bid/offer transaction history for that party on that sale
   event, and (when relevant) the real forfeiture allocation split —
   then moves straight to `represented`. A SaaS Admin later records
   the payment gateway's eventual decision
   (`recordRepresentmentOutcome()`) — this is the same honest,
   accepted-external-dependency treatment already used everywhere else
   a real Payment Gateway integration would sit (PR-30 step 190's
   authorization-hold-vs-capture timing and step 192's actual card-
   network submission both require it; filing itself is exposed
   through a dev-only route, `ChargebackController::devFile()`,
   mirroring `BidController::devFundEmd`/`devPayTopup`, standing in
   for the gateway webhook a real integration would deliver).
2. **Integrity review**: when a chargeback targets an already-
   forfeited hold (`against_approved_forfeiture`), a distinct audit
   event fires immediately (`chargeback.against_approved_forfeiture`)
   and the case joins a SaaS-Admin-only review queue
   (`findPendingIntegrityReview()`). Reviewing it
   (`reviewIntegrityFlag()`) is where the previously-dormant
   `chargeback_against_approved_forfeiture` rating penalty (-2.0
   `star_rating`) finally gets a real caller — self-approved at both
   BR-36 approval tiers, the same pattern already established in
   `RatingService::delistSellerForFraud()`, since a SaaS Admin finding
   here is the ultimate authority that approval gate exists to
   require. Declining to apply the penalty is a real, recorded,
   supported outcome (a genuine discretionary "no consequence"
   finding), not forced.

New UI: a "Dispute This Charge" dev-only panel on `listing/show.php`
wherever the current viewer holds an EMD deposit (held or forfeited)
on that sale event, and a new `/admin/chargebacks` queue (linked from
the Super Admin dashboard) covering both tracks plus resolved history.

**Real bug found and fixed while building this**: Postgres returns
`BOOLEAN` columns as literal `'t'`/`'f'` strings through this app's
driver — PHP treats the non-empty string `"f"` as truthy, so
`against_approved_forfeiture`/`integrity_rating_consequence_applied`
silently evaluated true regardless of actual value until normalized on
every read path in `ChargebackCaseModel`. The exact same fix already
existed for `ListingMediaModel::is_primary` (with its own explanatory
comment) — reused verbatim rather than re-deriving; caught by the new
`test:chargeback` suite failing 5/29 before the fix, not assumed away.

**Verified for real, not assumed**: new suite `test:chargeback`,
29/29 assertions (evidence assembly against real consent/bid data,
both independent tracks, the rating penalty's before/after values, the
self-approval mechanics, and the discretionary no-consequence path).
Then a full real-HTTP pass: a buyer registered through the actual
mobile→OTP→mPIN flow files a real chargeback via the dev route on a
real listing page (confirmed the new panel renders, confirmed the
flash message, confirmed the real DB row); a second party promoted to
Super Admin through `grant:super-admin` + the real isolated
`/admin/setup-totp`/`/admin/login` TOTP flow (codes generated with the
same `totp.php` helper used earlier this session, not hardcoded)
reaches `/admin/chargebacks`, sees the real filed case, and records a
representment outcome that's confirmed written to the DB — and,
separately, confirmed the ordinary buyer session is correctly
redirected away from `/admin/chargebacks` by the existing `superAdmin`
filter. Full regression: 39 suites, all clean on an independently
rebuilt database except the same pre-existing `test:auditlog`
DB-naming gap (unrelated, not a regression). Deleted the throwaway
fixture command after use, confirmed gone via `ls`.

Still open from the audit's Tier 1: Lot Approval consolidation and the
AX Chronicle in-browser viewer.

### D-118: Lot Approval consolidated queue screen

Second item from the Screen Completeness Audit's Tier 1 backlog. The
audit's finding: the design package's `Lot Approval.dc.html` mockup
shows one dedicated "Lot & Trading Session Approval" queue, but the
real app split the same information across the Tenant Admin
dashboard's bare-count tiles and the inline approve/reject buttons on
each individual listing/sale-event page — no single consolidated
screen existed.

**Fix, not a rebuild**: `TenantAdminController::verification()` (the
existing "Verification Console" — already the richer of the two
pending-listings views, with real thumbnails and media counts) now
also loads pending Sale Event approvals for the tenant (`ern`,
`sale_format`, `reserve_value`/`expected_value`, joined to the
listing's `category`/`subcategory`), and `tenant_admin/verification.php`
gained a second section, "Pending Trading Session Approvals," each row
with a real inline Approve form posting to the existing
`/sale-events/{id}/approve` route — no new backend logic, since
`SaleEventController::approve()`/`ListingLifecycleService::approveSaleEvent()`
already existed and needed no change. Retitled the page and its
dashboard link to "Lot & Trading Session Approval," matching the
design package's own naming, so this is now the one screen that
answers "what's this design mockup" rather than a same-behavior page
under an unrelated name.

**Verified for real, not assumed**: real HTTP pass — a tenant admin
promoted via `grant:tenant-admin` (registered through the real
mobile→OTP→mPIN flow first) views the consolidated page and sees both
a pending Lot and a pending Trading Session, each with real data (ERN,
category, reserve value); clicking Approve on the Trading Session
posts to the existing route and is confirmed, by direct DB check, to
flip `sale_event.status` from `pending_approval` to `grace_period`
(the real approve outcome); re-fetching the page confirms the item is
gone from the queue, a live query, not a static snapshot. Full
regression: 39 suites clean on an independently rebuilt database
except the same pre-existing `test:auditlog` gap. Deleted the
throwaway fixture command after use, confirmed gone via `ls`.

Still open from the audit's Tier 1: the AX Chronicle in-browser
viewer.

### D-119: AX Chronicle in-browser viewer

Third and last item from the Screen Completeness Audit's Tier 1
backlog. The audit's finding: `ChronicleController::download()` only
ever forced a PDF download for the Seller/Tenant Admin owner — there
was no HTML rendering of the same data for them, even though the
public, token-only `verify()` path (BR-52's QR-code destination) had
already proven exactly that rendering works, correctly masked and all.

**Fix**: `ChronicleController::view()` — same `authorizedChronicle()`
gate as `download()` (own-Chronicle-only for the Seller, or the
Tenant Admin who owns the settlement), reusing `verify()`'s own
media-assembly query rather than duplicating it. New view
`chronicle/view.php`, adapted from `chronicle/verify.php` (drops the
"no login required"/hash-verification framing that only makes sense
on the public path, links back to the authenticated download route)
at the new `GET /chronicles/{id}` route. `settlement/show.php` gained
a "View Chronicle" button alongside the existing "Download Certified
PDF" and "Public Verification Page" links.

**Verified for real, not assumed**: a full real Buy-Now settlement
driven through the actual service layer (two offers, acceptance, both
NOCs, both ratings — the same sequence `test:chronicle` itself uses)
generates a real Chronicle automatically; the seller, logged in via
real HTTP (`/login` with a real mpin, not a fixture session), fetches
`/chronicles/{id}` and gets the real reference number/ERN/evidence
list back in HTML; `/chronicles/{id}/download` still returns a real,
correctly-sized (100KB) PDF; a second, unrelated real party is
confirmed `403`-blocked from the same `/chronicles/{id}` URL by the
existing authorization gate — unchanged, not weakened, by this
addition. `settlement/show.php`'s new "View Chronicle" link confirmed
present and pointing at the real Chronicle ID. `test:chronicle`
re-run clean (22/22, unaffected — this change added a read path, not
touched `generate()`/`renderPdf()`). Full regression: 39 suites clean
on an independently rebuilt database except the pre-existing
`test:auditlog` gap. Deleted the throwaway fixture command after use,
confirmed gone via `ls`.

This closes the Screen Completeness Audit's entire Tier 1 backlog
(D-117/D-118/D-119). Still open, needing a business decision before
any build (not a dev-scoping call): §6.2 CoCo Concierge engagement
management, and whether §4.11's Independent Security Audit tracking
belongs in-app at all.
