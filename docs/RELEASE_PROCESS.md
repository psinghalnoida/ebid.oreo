# Release Process — how work here reaches the i2k2 server

Written because Piyush builds features in this repo but does not run
the i2k2 server himself — Arpit deploys there. Piyush has limited
hands-on ops experience and explicitly does not want an experimental
push here to ever risk the live site. This process is designed around
that one constraint above all else: **nothing in this repo can affect
the live site until Arpit deliberately decides to run one script.**

This is not a new deployment model — it extends two decisions already
on record in `docs/DECISIONS.md`:

- **D-04**: Claude never writes, edits, or executes anything directly
  on the production server, even when SSH access exists. Every
  production change is a deliberate, human-executed action.
- **D-05**: GitHub is the single handoff point between AI-written code
  and the server. (Its original `dev`/`main` split is stale — see the
  warning at the top of `README.md` — this repo has worked directly
  off short-lived feature branches into `main` for a long time now;
  this process formalizes what's already the actual practice.)

The one-time server bootstrap (PHP, Nginx, MySQL, the Node WebSocket
sidecar, systemd units, cron, SSL) is already fully documented in
`README.md`'s **"Deployment Guide — i2k2 Server"** (Steps 1–17) — this
doc does not repeat that. What's been missing is the *ongoing update*
half: once the server is already running, how does a new batch of
work actually get onto it safely, repeatably, without Arpit having to
reconstruct the right sequence of commands from memory each time.
That's what this doc and `scripts/deploy-i2k2.sh` are for.

## The three zones

```
 ┌─────────────────┐      ┌──────────┐      ┌────────────────┐      ┌───────────────┐
 │ feature branches │ ──▶  │   main   │ ──▶  │  release tag    │ ──▶  │ i2k2 server    │
 │ (build & test)    │ PR  │ (stable) │ tag  │  + release note │ run  │ (Arpit, via    │
 └─────────────────┘      └──────────┘      └────────────────┘ script│  deploy-i2k2.sh)│
   experiment freely        always deployable   "this exact commit    only Arpit's own
   here, zero live risk     but NOT live yet     is what's ready"      action makes it live
```

1. **Feature branches** — all new work happens here (already this
   repo's convention, e.g. `updates-by-piyush`). Nothing here can ever
   touch the live site.
2. **`main`** — the merge target once a feature is real, tested (the
   `test:*` regression suite green, real HTTP verification done — the
   existing convention throughout `docs/DECISIONS.md`), and
   documented. `main` should always be in a *working* state, but
   merging to `main` is **not** a deploy and does **not** touch i2k2
   by itself — same boundary D-04 already draws, just made explicit
   as a repeatable process instead of a one-off rule.
3. **A release tag** — the actual "this is ready to go live" signal,
   cut deliberately, separate from whatever `main`'s HEAD happens to
   be. Arpit only ever deploys a tag, never a moving branch — he's
   never guessing which commit is safe.

## Cutting a release (Piyush's side)

When a batch of merged work on `main` is ready to ship:

1. Confirm `main` is green: the regression suite passed, real HTTP
   verification was done (already this repo's standing practice).
2. Pick the next version number — `vMAJOR.MINOR.PATCH`, doesn't need
   to be strict semver, just consistent:
   - PATCH: bug fix, no schema/behavior change.
   - MINOR: new feature, additive.
   - MAJOR: anything Arpit needs a heads-up on before deploying (a new
     required `.env` variable, a migration that isn't purely
     additive, a behavior change existing tenants would notice).
3. Add an entry to `docs/RELEASES.md` — short, plain-English,
   **Arpit-facing**. Not the verbose engineering write-up in
   `docs/DECISIONS.md` — just: what changed, whether migrations are
   needed, whether any new `.env` variable needs setting first.
4. Tag it and push the tag:
   ```
   git tag -a v1.1.0 -m "short summary"
   git push origin v1.1.0
   ```
5. Tell Arpit the tag name and point him at the `docs/RELEASES.md`
   entry. That's the entire handoff — no shared credentials, nothing
   reaching into his server on its own.

## Deploying a release (Arpit's side)

```bash
cd /var/www/ebid.oreo
sudo ./scripts/deploy-i2k2.sh v1.1.0
```

The script (real paths/commands lifted directly from README.md's
Steps 7–13, not guessed):
1. `git fetch --tags` then `git checkout <tag>` — refuses to run
   against a dirty working tree or an unknown tag.
2. `composer install --no-dev --optimize-autoloader`.
3. `php spark migrate --all`.
4. `sudo systemctl restart php8.2-fpm` — **required**, not optional:
   PHP-FPM's opcache otherwise keeps serving the *previous* release's
   compiled bytecode until restarted, so skipping this step means the
   deployed files are new but the running code isn't.
5. If anything under `realtime/` changed in this release, also
   `cd realtime && sudo npm install && sudo systemctl restart
   ebidhub-realtime` (the live-bidding WebSocket sidecar from
   README.md Step 13) — the script diffs the previous tag against the
   new one and tells Arpit explicitly whether this step is needed for
   *this* release, rather than him having to remember.
6. Prints a short post-deploy checklist (same spirit as README.md's
   Step 17: hit `/`, hit `/admin/login`, open two tabs on a live
   listing and confirm a bid placed in one updates the other).

It does **not** touch Nginx config, DNS, SSL, cron, or the database
user/schema-level setup — those are one-time (README.md Steps 5, 12,
14, 15) and this script assumes they're already done.

## Rollback

```bash
cd /var/www/ebid.oreo
sudo ./scripts/deploy-i2k2.sh <previous-good-tag>
```

Same script, just pointed at the last known-good tag — deploying
*is* rolling back, there's no separate rollback procedure. If a
release's migration wasn't purely additive (dropped a column, changed
a type), the `docs/RELEASES.md` entry for that release must say so
explicitly and give the reverse-migration step — the deploy script
does not attempt to reverse a migration automatically, since guessing
wrong there is worse than doing nothing.

## Protecting `main` from an accidental direct push

One-time, ~2 minutes, on GitHub itself (not achievable through this
session's tools) — the concrete thing that stops "I pushed something
that broke everything" from being possible at all, closest to the
live site:

1. GitHub → this repo → **Settings → Branches → Add branch protection
   rule**.
2. Branch name pattern: `main`.
3. Enable **"Require a pull request before merging."**
4. Enable **"Require status checks to pass before merging"** and
   select the existing `Regression suite` check (from
   `.github/workflows/tests.yml`, already running the 39-suite
   `test:*` regression on every push).
5. Save.

After this, a direct push to `main` — including an accidental one —
is rejected outright. Every change goes through a PR that can't merge
unless the automated regression is green. `main` being safe was
already true in spirit (nothing on `main` alone touches i2k2 — see
D-04 above), this just makes it true mechanically too, so a mistake
can't even reach the first of the three gates between it and the live
site.

## What's genuinely still open

Everything else needed to finish `scripts/deploy-i2k2.sh` is already
answered by README.md's existing guide (SSH access, no Docker —
native PHP-FPM/Nginx/MySQL/systemd, the exact restart commands, the
`/var/www/ebid.oreo` path). The one thing worth confirming directly
with Arpit once, not because it blocks this from working today, but
because it affects how much this whole process can be trusted:

- **What commit is the i2k2 server actually running right now?**
  `docs/RELEASES.md`'s `v1.0.0` baseline entry tags the current `main`
  HEAD as the start of this process, but doesn't assume that's
  necessarily byte-for-byte what's live already. Run `git log
  --oneline -1` on the server and compare — if it doesn't match
  `v1.0.0`, reconcile once (either fast-forward the server to `v1.0.0`
  via the deploy script, or re-tag `v1.0.0` at whatever commit is
  actually running) so every release after this one is a trustworthy
  diff against a known-true baseline.
