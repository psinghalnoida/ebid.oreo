# eBid Hub

Multi-tenant B2B/B2C salvage and surplus auction platform, built on
CodeIgniter 4 (PHP) with server-rendered views.

**Super Admin:** Piyush Singhal
**Deployment contact:** Arpit (SSH/server access, i2k2)

## What this is

Four sale formats (Easy, Buy-Now, Express live; Tender not yet built), a
tested EMD escrow engine, a four-score rating system with Crawl-Back
recovery, real Tenant Admin role-based access control, and a full
Trust & Support content section — all built and verified against real
PostgreSQL data before ever being pushed.

## Start here

- **`SETUP.md`** — installation, environment configuration, what's built
  and what isn't, and the exact convention new models must follow (UUID
  primary keys need to be generated explicitly — see the note in there).
- **`docs/DECISIONS.md`** — every architectural and technical decision
  made on this project, with its reasoning, in chronological order (D-01
  through the present). If you're wondering *why* something was built a
  particular way, this is where to look before assuming it's a mistake.

## Quick start

```bash
composer install
cp env .env
# edit .env — see SETUP.md for the required database and app.baseURL settings
php spark migrate
php spark serve
```

Visit `/` for the landing page, `/trust-support` for the FAQ/legal hub,
`/register` to create an account.

## Verifying the build

```bash
php spark test:cascade    # EMD engine, H1/H2/H3 cascade, full-failure handling
php spark test:rating     # Rating engine, Crawl-Back, forced-neutral
php spark test:lifecycle  # Listing lifecycle, archive-and-recreate
php spark test:auth       # BR-02 mobile/OTP/mPIN flow
php spark test:buynow     # Buy-Now offers, BR-42 trust-over-price
php spark test:express    # Express Auction, PR-11 3rd-pledge trigger
```

These are real, permanent test commands (not throwaway scripts) — rerun
any of them after making a change to confirm nothing broke.

## Provisioning a Tenant Admin

There's no Super Admin panel yet, so this is done via CLI:

```bash
php spark grant:tenant-admin <mobile_number> <tenant_id>
```

## Known gaps before production use

Every dev-only stand-in (payment gateway simulation, time-skip helpers,
missing seller/admin ownership checks on a couple of endpoints) is
explicitly flagged with a `⚠️ DEV-ONLY` comment in the code itself, and
explained in `docs/DECISIONS.md`. Search the codebase for `DEV-ONLY`
before deploying anywhere real users can reach it.
