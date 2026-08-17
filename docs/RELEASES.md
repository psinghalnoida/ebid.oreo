# Releases

Arpit-facing changelog — one entry per tagged release, written in
plain English for someone deploying the app, not reading its code.
See `docs/RELEASE_PROCESS.md` for how/when these get cut, and
`scripts/deploy-i2k2.sh` for exactly what running one does. For the
full engineering write-up behind any change, see `docs/DECISIONS.md`
(referenced by D-number below where relevant).

Template for a new entry:

```
## vX.Y.Z — <date>

**What changed:** one or two sentences, plain English.

**Migrations needed:** yes/no — if yes, `php spark migrate --all`
(same as always) covers it, unless noted otherwise.

**New environment variables / config:** none, OR list them exactly as
they need to appear in `.env`.

**Realtime sidecar affected:** yes/no — the deploy script detects this
automatically by diffing `realtime/` against the previous tag, this
line is just so Arpit knows what to expect before running it.

**Anything Arpit should watch for after deploying:** none, OR a
specific thing to check.

**Rollback notes:** safe to roll back to the previous tag with no
extra steps, OR a specific caveat.
```

---

## v1.0.0 — 2026-08-16

**What changed:** this is the first tagged release — no prior tags
existed, so this covers everything on `main` up through two features
built this session that are **confirmed not yet live**: real HTTP
crawling of the actual running site (`salvage.claimsmitra.com`,
resolving to `103.25.128.136` — the exact i2k2 server IP documented in
`README.md`) found `/admin/forgot-mpin` returning a genuine `404`
there, meaning the server is currently running a commit that predates
both of the changes below.

1. **Custodian mPIN bootstrap + forgot-mPIN recovery** (D-125) — a
   `php spark bootstrap:custodian` CLI command that seeds the known
   Super Admin/Custodian account (real bcrypt mPIN, not a hardcoded
   login-check bypass), plus a real dual-channel (mobile OTP + email
   OTP) self-service mPIN recovery flow at `/admin/forgot-mpin`, new
   for the Custodian login path specifically.
2. **Pricing screen wired from the design handoff package** (D-126) —
   `/pricing` now serves the actual Claude Design mockup
   (`Pricing.dc.html`) instead of the earlier interim document, with a
   real, working Success Fee calculator verified against the live
   `EmdService` bracket math.

**Migrations needed:** yes. `php spark migrate --all` — D-125 needs no
new migration (reuses existing `party`/`otp` tables), but running the
full set is always the safe default and covers anything else this
server hasn't caught up on yet.

**New environment variables / config:** none required for the app to
run. One functional gap, not a blocker: `EmailNotificationService`
(D-125's forgot-mPIN email leg) will attempt a real send via
`Config\Services::email()` and fail closed if no real SMTP is
configured in `.env` yet — the mobile-OTP leg and the on-screen
dev-mode fallback still work either way, so this isn't a "deploy
blocker," just a known incomplete piece. See `docs/DECISIONS.md` D-125
for the exact config keys once real SMTP credentials exist.

**Realtime sidecar affected:** no — neither D-125 nor D-126 touch
anything under `realtime/`.

**Anything Arpit should watch for after deploying:** run `php spark
bootstrap:custodian` once, manually, after this deploy — it's not run
automatically by the deploy script (seeding an account is a
deliberate action, not a passive migration). Confirm with Piyush
before running it if there's any doubt about whether the Custodian
account should already exist on this server.

**Rollback notes:** safe to roll back to the commit this server was
already running with no extra steps — neither change here is a
breaking schema change.

---

## v1.1.0 — 2026-08-17

**What changed:**

1. **Temporary email-OTP toggle for Custodian login** (D-128) — for
   testing the login flow without an authenticator app set up yet.
   Off by default; only takes effect if you deliberately add
   `admin.twoFactorMode = email_otp` to this server's `.env`. See the
   "D-128 — testing without an authenticator app set up yet" note in
   this README's Step 16 for exactly how to turn it on/off.
2. **`php spark seed:demo-data`** (D-129) — one command that fills the
   marketplace with a realistic demo tenant, 20 demo users, and 8
   listings across all 3 self-service sale formats, with real bids/
   offers already placed. Not run automatically — a deliberate,
   reversible action (`--undo` removes it cleanly) for whenever you
   want the site to have real-looking content to test/demo against
   instead of an empty marketplace.

**Migrations needed:** yes. `php spark migrate --all` — one new
migration (`AddAdminLoginEmailOtpPurpose`) for D-128's OTP purpose.

**New environment variables / config:** none required. Optional:
`admin.twoFactorMode = email_otp` in `.env`, only if/when you want the
email-code login path active — see point 1 above. Leaving it unset
keeps the normal, secure TOTP-required login exactly as it's always
been.

**Realtime sidecar affected:** no.

**Anything Arpit should watch for after deploying:** nothing runs
automatically for either change — the email-2FA toggle needs a
deliberate `.env` edit + `php8.2-fpm` restart to take effect, and the
demo data needs `php spark seed:demo-data` run by hand if/when it's
wanted on this server. Neither happens on its own just by deploying
this tag.

**Rollback notes:** safe to roll back to `v1.0.0` with no extra steps.
If `seed:demo-data` was run on this server, running `php spark
seed:demo-data --undo` first (before rolling back) is the cleanest way
to remove the demo tenant/listings/users, though it isn't required —
they're clearly marked (`DEMO — ` prefix) and harmless to leave in
place either way.
