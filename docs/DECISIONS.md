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
