#!/bin/bash
#
# deploy-i2k2.sh -- run by Arpit, on the i2k2 server itself, to ship a
# tagged release. Never run by Claude (D-04: no AI-direct writes to
# the production server) and never wired to auto-trigger from GitHub
# Actions -- this is a deliberate, human-executed step every time, by
# design. See docs/RELEASE_PROCESS.md for the full workflow this is
# one piece of; see README.md's "Deployment Guide -- i2k2 Server" for
# the one-time server bootstrap this assumes already happened (PHP
# 8.2-FPM, Nginx, MySQL, the realtime/ WebSocket sidecar as a systemd
# unit, cron, SSL -- none of that is redone here).
#
# Usage (from the app root, /var/www/ebid.oreo):
#   sudo ./scripts/deploy-i2k2.sh v1.1.0
#
# What it does, in order:
#   1. Refuses to run against an uncommitted/dirty working tree, or a
#      tag that doesn't exist -- fails loud and early rather than
#      guessing.
#   2. git fetch --tags && git checkout <tag>.
#   3. composer install --no-dev --optimize-autoloader.
#   4. php spark migrate --all.
#   5. Restarts php8.2-fpm -- required, not optional: PHP-FPM's
#      opcache otherwise keeps serving the PREVIOUS release's compiled
#      bytecode even after the files on disk have changed.
#   6. If anything under realtime/ changed between the previously
#      deployed tag and this one, also npm install + restarts the
#      ebidhub-realtime systemd unit (the live-bidding WebSocket
#      sidecar, README.md Step 13) -- most releases won't touch this,
#      so most deploys skip this step automatically.
#   7. Records the deployed tag to .last-deployed-tag (used by step 6
#      on the *next* run to know what changed) and prints a short
#      post-deploy checklist to run by hand.
#
# Rollback is the same command, pointed at the previous good tag --
# see docs/RELEASE_PROCESS.md's Rollback section for the one caveat
# (a non-additive migration needs a manual reverse step, called out in
# that release's docs/RELEASES.md entry if it applies).

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

TAG="${1:-}"
if [ -z "$TAG" ]; then
  echo "Usage: $0 <tag>   e.g. $0 v1.1.0" >&2
  exit 1
fi

echo "==> Deploying $TAG to $APP_ROOT"

# --- Step 1: refuse to run against a dirty tree or unknown tag ------
if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: working tree at $APP_ROOT has uncommitted changes." >&2
  echo "       Resolve or discard them (git status) before deploying -- a" >&2
  echo "       deploy should never silently overwrite something server-side." >&2
  exit 1
fi

git fetch --tags --quiet

if ! git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "ERROR: tag '$TAG' not found after fetching." >&2
  echo "       Confirm it was pushed: git ls-remote --tags origin | grep $TAG" >&2
  exit 1
fi

PREVIOUS_TAG=""
if [ -f .last-deployed-tag ]; then
  PREVIOUS_TAG="$(cat .last-deployed-tag)"
fi

# --- Step 2: checkout the tag ----------------------------------------
echo "==> git checkout $TAG"
git checkout --quiet "$TAG"

# --- Step 3: dependencies ---------------------------------------------
echo "==> composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader

# --- Step 4: migrations -------------------------------------------------
echo "==> php spark migrate --all"
php spark migrate --all

# --- Step 5: restart PHP-FPM (opcache) -----------------------------------
echo "==> systemctl restart php8.2-fpm"
systemctl restart php8.2-fpm

# --- Step 6: restart the realtime sidecar only if it actually changed ---
REALTIME_CHANGED=0
if [ -n "$PREVIOUS_TAG" ] && git rev-parse "$PREVIOUS_TAG" >/dev/null 2>&1; then
  if ! git diff --quiet "$PREVIOUS_TAG" "$TAG" -- realtime/; then
    REALTIME_CHANGED=1
  fi
else
  # No known previous tag to diff against -- can't prove nothing
  # changed under realtime/, so err toward restarting it once.
  REALTIME_CHANGED=1
fi

if [ "$REALTIME_CHANGED" = "1" ]; then
  echo "==> realtime/ changed since the last deploy -- reinstalling + restarting the WebSocket sidecar"
  (cd realtime && npm install --omit=dev)
  systemctl restart ebidhub-realtime
else
  echo "==> realtime/ unchanged since $PREVIOUS_TAG -- leaving ebidhub-realtime running as-is"
fi

# --- Step 7: record + summarize ------------------------------------------
echo "$TAG" > .last-deployed-tag

cat <<EOF

==> Deployed $TAG.

Check by hand before calling this done (README.md Step 17):
  - https://<your-domain>/            -- real marketplace landing page
  - https://<your-domain>/admin/login -- Super Admin login loads
  - Open the same live listing in two browser tabs, place a bid in
    one, confirm the price updates in the OTHER tab within a second
    or two with no refresh (proves the realtime sidecar is really up,
    not just that systemctl said "active").

If anything looks wrong: sudo ./scripts/deploy-i2k2.sh $PREVIOUS_TAG
EOF
