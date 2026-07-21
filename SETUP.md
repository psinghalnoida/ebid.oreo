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

- `app/Controllers/Home.php` → `app/Views/landing.php` — landing page
- `app/Controllers/TrustSupport.php` → `app/Views/trust_support.php` — Trust
  & Support hub (⚠️ card copy is placeholder structure only — awaiting
  legally-reviewed final policy text before it's real)
- `app/Views/layouts/main.php` — shared base layout (Modern Marketplace
  Minimal design system: off-white/near-black/emerald, Sora typeface)
- **`app/Database/Migrations/`** — all 9 tables (party, tenant, party_role,
  listing, sale_event, bid, emd_hold, rating_event, + rating state columns),
  ported from the earlier Node prototype. Run via `php spark migrate`.
  Tested including full rollback (`php spark migrate:rollback`).
- **`app/Models/`** — Party, Tenant, Listing, SaleEvent, Bid, EmdHold,
  RatingEvent — CodeIgniter Model classes wrapping the schema above.
- **`app/Libraries/`** — the actual business logic, each traceable to
  specific BR references:
  - `EmdService.php` — BR-27 baseline calc, BR-29 Buy-Now adjustment,
    BR-34 forfeiture allocation math
  - `BiddingService.php` — BR-27 live EMD gate, BR-43 anti-jacking ceiling
  - `CascadeService.php` — BR-28 H1/H2/H3 baton-pass and full-cascade-failure
  - `RatingService.php` — BR-35/36/38/39 upgrade/downgrade/Crawl-Back/
    forced-neutral (⚠️ Shadow Banning threshold still unconfirmed — see D-08)
  - `ListingLifecycleService.php` — BR-13/14 status transitions,
    archive-and-recreate, grace-window edits, emergency stop
  - `Uuid.php` — small helper; CodeIgniter's Model layer needs the UUID
    primary key supplied explicitly on insert (see note below)
- **`app/Commands/`** — `php spark test:cascade`, `test:rating`,
  `test:lifecycle`, `test:auth` — real, runnable test suites (89 assertions
  total, zero failures) that exercise the above against a real database.
  Kept in the project as ongoing verification tooling, not just one-off
  scripts — rerun anytime after a change to confirm nothing broke.
- **`app/Controllers/AuthController.php` + `app/Views/auth/*`** — BR-02
  auth flow, real browser-reachable pages: `/register` → OTP verify → mPIN
  setup, and `/login` with the 3-strike lockout → OTP reset flow. Verified
  via real HTTP requests, not just the service layer. ⚠️ OTP is shown
  on-screen in dev mode since the SMS provider is still stubbed — must be
  removed before production.

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
  are explicitly dev-only stand-ins for pieces not yet built (Tenant Admin
  authorization, the real 60-minute grace-window timer, a real payment
  gateway) — each is clearly marked in the code and MUST be
  removed/replaced before production use. See D-14 for the full list.

- **`app/Filters/TenantAdminFilter.php` + `app/Libraries/AuthorizationService.php`
  + `app/Models/PartyRoleModel.php`** — real BR-09 Tenant Admin
  authorization, replacing the dev-only approve/reject shortcuts from
  before. A logged-in party must actually hold the `tenant_admin` role
  for a listing's specific tenant to approve/reject it — enforced with a
  403 response otherwise, verified over real HTTP. Since there's no Super
  Admin panel yet to grant this role through a UI, use:
  ```
  php spark grant:tenant-admin <mobile_number> <tenant_id>
  ```

- **`app/Controllers/OfferController.php` + `OfferModel`/`OfferService`
  + extended `listing/show.php`** — Buy-Now is now a complete, real
  format: submit an offer, seller accepts (with mandatory reason if not
  the highest, BR-42), EMD top-up/refund on acceptance (BR-29). Verified
  end-to-end over real HTTP down to the database. ⚠️ `OfferController::accept`
  is gated only by login, not by a check that the caller is actually the
  listing's seller — must be added before production use (see D-19).

- **`app/Controllers/ExpressController.php` + `ExpressAuctionService`
  + extended `listing/show.php`** — Express Auction is complete: the
  automatic "launches on the 3rd distinct buyer pledge" mechanic (PR-11)
  genuinely works — verified via direct database reads that bidding
  stays closed after 1-2 pledges and opens automatically, with no
  admin/seller action, exactly on the 3rd. Reuses `sale_event`'s existing
  `scheduled_start_at`/`scheduled_end_at` columns rather than new schema.

## Deployment gate — see D-23 (supersedes D-18)

D-18's original gate (Easy/Buy-Now/Express working) was met, but a full
BR/PR audit afterward surfaced bigger gaps (no photo upload, no
settlement flow, no dispute resolution) — D-23 established a corrected,
tiered gate. Current status: **Tier 1 complete** (D-24/25/26), **Tier 2
complete** (D-27 Dispute Resolution, D-28 scheduled-job infrastructure).
**Tier 3 remaining**: Super Admin panel + real auth, tenant onboarding,
conflict-of-interest blocks.

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

**Known limitation, not fixed by this:** Easy Auction was never given a
defined "bidding ends at time X" mechanism anywhere in this codebase —
only Express got an explicit countdown. The scheduler cannot close an
Easy Auction automatically because no such trigger point exists to check
against. This is a separate, real gap from what scheduling itself closes.

## Not yet built

Tender format (Company Shop exclusive, lower priority per the roadmap), a
real Super Admin panel (`grant:tenant-admin`/`grant:super-admin` spark
commands are stand-ins), Super Admin's separate Auth0/TOTP login path
(BR-04), tenant onboarding workflow, conflict-of-interest blocks (BR-21/22),
a real payment gateway (still stubbed across every format's
`devFundEmd`/`dev-fund-emd-*`/`pledge` endpoints), and Easy Auction's
missing bidding-end trigger (see limitation above).

## Production web server (Apache/Nginx)

Point the web server's document root at `public/`, not the project root —
this is a CodeIgniter requirement, keeps `app/`, `system/`, `.env` etc.
outside the publicly-servable directory.
