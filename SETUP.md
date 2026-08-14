# eBid Hub — CodeIgniter 4 Setup

This replaces the earlier Node.js/React skeleton (see docs/DECISIONS.md D-10).
Standard CodeIgniter 4 project structure — nothing exotic, `composer install`
works normally here since your server has real internet access (this was
only a problem in Claude's sandboxed dev environment, which blocks Packagist
— see D-11 for why that doesn't affect you).

## Local / server setup

```bash
composer install
cp env .env
```

Edit `.env` and set at minimum:
```
CI_ENVIRONMENT = development   # (production once live)

database.default.hostname = localhost
database.default.database = ebidhub
database.default.username = ebidhub_app
database.default.password = <real password>
database.default.DBDriver = Postgre
database.default.port = 5432
database.default.charset = utf8

# IMPORTANT: must match the actual domain this app is served from, or
# redirects (login, listing creation, bidding, etc.) will point at the
# wrong host. Empty string is NOT valid — CodeIgniter rejects it outright.
app.baseURL = 'https://yourdomain.com/'
# For local testing against `php spark serve` on the default port:
# app.baseURL = 'http://localhost:8080/'
```

## Run locally

```bash
php spark serve
```
Visit http://localhost:8080 — should show the landing page.
Visit http://localhost:8080/trust-support — should show the Trust & Support hub.

## What's built so far

This section stays deliberately concise — for full detail, see
`docs/DECISIONS.md` (every decision, in order, with reasoning) and
`docs/SITE_MAP.md` (every real page, organized by who can reach it).

- **All four sale formats** (Easy, Buy-Now, Express, Tender) — fully
  built, tested, and reachable through real HTTP pages, not just service
  layer. Tender specifically includes interest registration, seller
  eligibility approval, Terms of Sale/document publishing, manual EMD
  audit logging, seller-flexible bid increments with dual-window Dynamic
  Time, a full manual post-auction review workflow (provisional winner,
  extension, rejection with cascade to the next bidder, confirmation),
  and genuine no-login stakeholder access via a random token.
- **EMD escrow, cascade, and forfeiture** — `EmdService`,
  `BiddingService`, `CascadeService` — BR-27/28/34/43.
- **Four-score rating system** — `RatingService` — upgrade/downgrade/
  Crawl-Back/forced-neutral (⚠️ Shadow Banning threshold still
  unconfirmed — see D-08).
- **Listing lifecycle** — `ListingLifecycleService` — BR-13/14 status
  transitions, archive-and-recreate, grace-window edits, emergency stop.
  ⚠️ Both the material-edit and emergency-stop logic are fully built and
  tested but currently have **no HTTP route** — see `docs/SITE_MAP.md`.
- **Settlement** — dual-NOC + mandatory rating gate, stall resolution.
- **Dispute Resolution Framework** — filing, evidence, category-based
  ruling authority, appeal.
- **Scheduled-job automation** — grace windows, Express's countdown,
  offer lapse, settlement stall-flagging, Easy Auction's own schedule —
  all genuinely automatic once the cron entry (below) is installed.
- **Real Super Admin** (TOTP 2FA, separate login path), **Tenant Admin**
  (role-scoped authorization), **seller approval gate** (BR-09), and
  **conflict-of-interest blocks** (BR-21/22) — see the dedicated section
  below for provisioning.
- **Real marketplace landing page** — live listings, real category
  counts, not placeholder content.
- `app/Commands/` — fifteen real, permanent `spark test:*` commands
  (254+ assertions total, zero known failures) — see Step 10 of the
  deployment guide in `README.md` for the full list. Rerun any of them
  after a change to confirm nothing broke.
- `app/Controllers/AuthController.php` — BR-02 mobile/OTP/mPIN flow,
  3-strike lockout → OTP reset. ⚠️ OTP is shown on-screen in dev mode
  since the SMS provider is still stubbed — must be removed before
  production.

## Important convention for any NEW model you add

CodeIgniter's Model `insert()` cannot reliably retrieve a UUID primary key
generated at the database level (originally Postgres's own `DEFAULT
gen_random_uuid()`; no DB-side default exists at all since the MySQL
migration, see `docs/DECISIONS.md` — this convention already made that
migration trivial for every table's primary key) — this caused real
failures during testing. Every model's create method instead:
1. Generates the UUID in PHP: `$id = \App\Libraries\Uuid::v4();`
2. Includes `'id' => $id` in the insert data
3. Includes `'id'` in the model's `$allowedFields` array

Follow this pattern for any new table/model — see any existing Model's
`create*()` method for a working example.

## Important convention for any NEW feature you build — audit logging (BR-05)

This is not optional polish; it's a standing requirement, the same
weight as the UUID convention above. Every state-changing action that
falls into one of these four categories **must** call `AuditLogService`
before returning — decide this at design time, not as an afterthought
once the feature already works:

1. **Financial events** — anything that creates, releases, forfeits, or
   adjusts a real sum of money (EMD, fees, settlement amounts).
2. **Authority decisions** — anything only a Tenant Admin or Super Admin
   can do (approve/reject a listing, rule on a dispute, grant a role,
   suspend a seller).
3. **Access grants** — anything that gives a party a capability they
   didn't have before (Tender eligibility, a stakeholder link, a role).
4. **Irreversible or high-consequence state transitions** — emergency
   stop, a listing's material edit (archive-and-recreate), a settlement
   completing.

If a new action doesn't clearly fall into one of these four, it
probably doesn't need logging — routine reads, page views, and browsing
don't belong in the audit trail. When genuinely unsure, log it; a
false positive here is far cheaper than a missing record later.

**The standard pattern** — this is the whole cost of doing it right:

```php
(new \App\Libraries\AuditLogService())->log(
    'category.specific_action',   // e.g. 'emd.held', 'listing.approved', 'dispute.ruled'
    $actorPartyId,                 // who did this — null ONLY for genuinely system-triggered events (the scheduler)
    ['relevant' => 'context'],     // whatever a future investigator would actually need to see
);
```

Three things that have each caused a real bug when skipped, found the
hard way — check all three before considering a new integration done:

- **Does the calling method actually have the real actor's identity
  available to it, or is it silently missing?** This exact gap was
  found and fixed three separate times (D-47's listing approve/reject,
  D-48's offer acceptance and emergency stop) — a method that "obviously"
  should know who's calling it sometimes doesn't, because nobody thought
  to pass it through. Check the actual call chain, don't assume.
- **Never put a secret, token, or credential value in the payload.**
  The audit log itself is a real target — logging the stakeholder token
  string alongside "a link was generated" would turn the log into the
  very credential it's supposed to be auditing access to (see
  `TenderService::generateStakeholderLink` for the pattern: log that
  access was granted and by whom, never the token itself).
- **Test against a realistic, multi-source chain, not just your own
  isolated new test.** `verifyChainIntegrity()` walks the *entire*
  table — D-46 found two genuine bugs (a timestamp round-trip issue, a
  JSONB reformatting issue) that were completely invisible in an
  isolated 3-record test and only surfaced once real application data
  from other features accumulated in the same table. Run the full
  `test:auditlog` suite after the full regression, not in isolation,
  before considering new wiring verified.

`docs/DECISIONS.md` entries D-45 through D-49 are the full worked
history of this — read them if a new case doesn't obviously fit the
checklist above.

- **`app/Controllers/ListingController.php`, `SaleEventController.php`,
  `BidController.php` + `app/Views/listing/*`** — the Easy Auction flow,
  real browser-clickable pages: create a listing, submit/approve, attach
  an Easy Auction, approve it, fund EMD, place bids. Verified end-to-end
  over real HTTP down to the database (see D-14). ⚠️ Several endpoints
  are still explicit dev-only stand-ins — the grace-window timer can be
  force-frozen (real automatic timing exists via the scheduler, see
  below, but a manual override remains for testing) and EMD funding is
  simulated pending the real payment gateway. Tenant Admin authorization
  itself is real, not a stand-in (D-17). Each dev-only marker is clearly
  flagged in the code (`grep -rn "DEV-ONLY" app/`) — review before
  production use.

- **`app/Filters/TenantAdminFilter.php` + `app/Libraries/AuthorizationService.php`
  + `app/Models/PartyRoleModel.php`** — real BR-09 Tenant Admin
  authorization, replacing the dev-only approve/reject shortcuts from
  before. A logged-in party must actually hold the `tenant_admin` role
  for a listing's specific tenant to approve/reject it — enforced with a
  403 response otherwise, verified over real HTTP. A real Super Admin
  panel exists (D-29), but granting the Tenant Admin role specifically
  is still a deliberate CLI-only step, not self-service:
  ```
  php spark grant:tenant-admin <mobile_number> <tenant_id>
  ```

- **`app/Controllers/OfferController.php` + `OfferModel`/`OfferService`
  + extended `listing/show.php`** — Buy-Now is now a complete, real
  format: submit an offer, seller accepts (with mandatory reason if not
  the highest, BR-42), EMD top-up/refund on acceptance (BR-29). Verified
  end-to-end over real HTTP down to the database. `OfferController::accept`
  now correctly verifies the caller is actually the listing's seller
  (D-22 — this was flagged as a real gap in D-19 and closed shortly after).

- **`app/Controllers/ExpressController.php` + `ExpressAuctionService`
  + extended `listing/show.php`** — Express Auction is complete: the
  automatic "launches on the 3rd distinct buyer pledge" mechanic (PR-11)
  genuinely works — verified via direct database reads that bidding
  stays closed after 1-2 pledges and opens automatically, with no
  admin/seller action, exactly on the 3rd. Reuses `sale_event`'s existing
  `scheduled_start_at`/`scheduled_end_at` columns rather than new schema.

## Deployment gate — D-23 (supersedes D-18) — FULLY MET, and superseded by further work

All three tiers of D-23's corrected deployment gate are complete:
**Tier 1** (D-24/25/26 — media, settlement, seller rating), **Tier 2**
(D-27/28 — dispute resolution, scheduled jobs), **Tier 3** (D-29 — real
Super Admin TOTP auth, tenant onboarding, conflict-of-interest blocks).
**Since then, the full Tender Auction format was also built end-to-end**
(D-34 through D-38 — foundation, bidding mechanics, post-auction review,
corrections applied back to Easy/Express, and the real HTTP layer). See
`docs/DECISIONS.md` for the full detail behind each.

## Super Admin — provisioning and first login

The panel itself is real (D-29) — but *granting* the role is deliberately
still a CLI-only step, not self-service, by design:

```bash
php spark grant:super-admin <mobile_number>
```

That party must then be logged in normally once (`/login`) to visit
`/admin/setup-totp` and enroll a real authenticator app (Google
Authenticator, Authy, etc. — enter the shown secret manually, no QR
code). Confirm with the 6-digit code from the app. After that, Super
Admin access is only reachable via the SEPARATE `/admin/login` — mobile +
mPIN + a valid TOTP code, all three required.

## Scheduled jobs — REQUIRED for the platform's timers to actually work

Every time-based trigger (BR-14 grace windows, Express's 1-hour bidding
countdown, Buy-Now's 3-day offer lapse, BR-39 settlement stall-flagging)
depends on this cron entry actually being installed on the server. Without
it, these only advance via manual dev-force actions — the logic is real
and tested, but nothing calls it automatically until this is set up.

```bash
crontab -e
```

Add this line (adjust the path to match where the repo actually lives):
```
* * * * * cd /var/www/ebid.oreo && php spark run:scheduler >> /var/log/ebidhub-scheduler.log 2>&1
```

This runs every minute. Verify it's working:
```bash
tail -f /var/log/ebidhub-scheduler.log
```
You should see output like `Grace periods frozen: 0` etc. every minute
once it's running — zero counts are expected most of the time; non-zero
counts confirm it's genuinely processing real expired timers, not just
running silently.

**Update (D-32):** Easy Auction now has a real seller-set schedule and
genuine Dynamic Time anti-sniping — the limitation that used to be noted
here (no defined bidding-end mechanism) has been resolved. The scheduler
correctly auto-closes an Easy Auction once its schedule genuinely ends.

## Not yet built

Updated after a real production-readiness audit (D-104) — most of what
used to be listed here (logout, My Listings, My Bids/Purchases, the
profile page, a filterable Browse page, tenant view/edit, TOTP backup
codes, listing-edit/emergency-stop routes, video/document upload) has
since been built; see `docs/DECISIONS.md` D-99–D-104 and
`docs/SITE_MAP.md` for what's actually live today. What's genuinely
still not built, verified by reading the code rather than re-asserting
old notes:

- **A real payment gateway** — EMD funding is simulated across every
  format's `devFundEmd`/`dev-fund-emd-*`/`pledge` endpoints; real seller
  payouts need this too (`account/earnings.php` says so explicitly).
  Connects post-deployment once real gateway credentials exist.
- **A real SMS provider** — `AuthService::requestOtp()` generates and
  rate-limits a real OTP correctly but never sends it; it's shown
  on-screen. Must be removed once a provider is wired in.
- **BR-46's AI Listing Pre-Audit** — fully built, inert until a real
  `GEMINI_API_KEY` is supplied (see `docs/BR_PR_AUDIT.md`).

## Production web server (Apache/Nginx)

Point the web server's document root at `public/`, not the project root —
this is a CodeIgniter requirement, keeps `app/`, `system/`, `.env` etc.
outside the publicly-servable directory.
