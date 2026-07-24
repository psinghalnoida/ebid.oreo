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
that Postgres generates via its own `DEFAULT gen_random_uuid()` — this
caused real failures during testing. Every model's create method instead:
1. Generates the UUID in PHP: `$id = \App\Libraries\Uuid::v4();`
2. Includes `'id' => $id` in the insert data
3. Includes `'id'` in the model's `$allowedFields` array

Follow this pattern for any new table/model — see any existing Model's
`create*()` method for a working example.

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

Real navigation/account pages a normal user would expect on day one —
generic logout for regular users, "My Listings", "My Bids/Purchases", a
user profile page, and a proper browse-all-listings page with filters
(the landing page only shows the 12 most recent active listings). Also:
a page for Super Admin to view/edit an *existing* tenant (only creation
exists), TOTP recovery if a Super Admin loses their device, HTTP routes
for the already-built-and-tested listing edit and emergency-stop logic
(see `docs/SITE_MAP.md` for the full list), KYC data collection (Tier 4),
video/document upload and transcoding for listings (PR-9's full spec,
deferred by the project owner's decision), and a real payment gateway/SMS
provider (still stubbed across every format's `devFundEmd`/
`dev-fund-emd-*`/`pledge` endpoints — connects post-deployment).

## Production web server (Apache/Nginx)

Point the web server's document root at `public/`, not the project root —
this is a CodeIgniter requirement, keeps `app/`, `system/`, `.env` etc.
outside the publicly-servable directory.
