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

## Not yet ported from the Node prototype

The SQL migrations (9 files, previously in the Node skeleton's
`backend/src/migrations/`) still need porting into CodeIgniter's own
migration format (`php spark make:migration`) — not done yet, flagged as
next work. The EMD engine, rating engine, and bidding/cascade logic that
were built and tested in Node also need a full PHP rewrite — none of that
JavaScript logic runs under this stack.

## Production web server (Apache/Nginx)

Point the web server's document root at `public/`, not the project root —
this is a CodeIgniter requirement, keeps `app/`, `system/`, `.env` etc.
outside the publicly-servable directory.
