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
  `test:lifecycle` — real, runnable test suites (69 assertions total,
  zero failures) that exercise the above against a real database. Kept in
  the project as ongoing verification tooling, not just one-off scripts —
  rerun anytime after a change to confirm nothing broke.

## Important convention for any NEW model you add

CodeIgniter's Model `insert()` cannot reliably retrieve a UUID primary key
that Postgres generates via its own `DEFAULT gen_random_uuid()` — this
caused real failures during testing. Every model's create method instead:
1. Generates the UUID in PHP: `$id = \App\Libraries\Uuid::v4();`
2. Includes `'id' => $id` in the insert data
3. Includes `'id'` in the model's `$allowedFields` array

Follow this pattern for any new table/model — see any existing Model's
`create*()` method for a working example.

## Not yet built

Auth (BR-02 mobile/OTP/mPIN), real HTTP API routes/controllers wiring the
above services together, and the remaining sale formats beyond what the
tests exercise (Buy-Now/Express/Tender specific flows — Easy Auction is the
most thoroughly tested so far).

## Production web server (Apache/Nginx)

Point the web server's document root at `public/`, not the project root —
this is a CodeIgniter requirement, keeps `app/`, `system/`, `.env` etc.
outside the publicly-servable directory.
