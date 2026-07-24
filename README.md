# eBid Hub

Multi-tenant B2B/B2C salvage and surplus auction platform, built on
CodeIgniter 4 (PHP) with server-rendered views.

**Super Admin:** Piyush Singhal
**Deployment contact:** Arpit (SSH/server access, i2k2)

## What this is

All four sale formats (Easy, Buy-Now, Express, Tender) fully built and
tested, a tested EMD escrow engine, a four-score rating system with
Crawl-Back recovery, a real Dispute Resolution Framework, real Tenant
Admin and Super Admin (TOTP 2FA) role-based access control, and a full
Trust & Support content section — all built and verified against real
PostgreSQL data before ever being pushed. 254+ automated assertions
across fifteen test suites.

## ⚠️ Before deploying — read this first

**`main` is currently behind `dev` and does not yet reflect everything
described in this README.** All active development happens on `dev`;
`main` only advances when Piyush explicitly approves a merge as a
deliberate checkpoint (see `docs/DECISIONS.md` for that convention).
Before following the deployment guide below, confirm which branch you're
actually deploying:

```bash
git log --oneline -3 origin/main
git log --oneline -3 origin/dev
```

If `main` is behind and you want the full platform described here, that
merge needs to happen first — check with Piyush.

## Start here

- **`SETUP.md`** — local development setup, what's built and what isn't,
  and the exact convention new models must follow.
- **`docs/DECISIONS.md`** — every architectural and technical decision
  made on this project, with its reasoning, in chronological order (D-01
  through the present). If you're wondering *why* something was built a
  particular way, this is where to look before assuming it's a mistake.
- **`docs/SITE_MAP.md`** — every real, working page in the application,
  organized by who can access it, plus an honest list of what's not yet
  reachable even where the underlying logic exists.
- **The deployment guide below** — for putting this on the actual i2k2
  server.

---

## Deployment Guide — i2k2 Server

Target: `103.25.128.136`, Ubuntu 22.04 LTS, 6 vCPU / 8GB RAM / 400GB SSD.
Every command below assumes you're SSH'd into that server as a user with
sudo access.

### Step 1 — Confirm the server matches what's expected

```bash
lsb_release -a      # should show Ubuntu 22.04
nproc                # should show 6
free -h              # should show ~8GB
df -h                # should show ~400GB available
```

If any of these don't match, stop and confirm with the team before
proceeding — the rest of this guide assumes this exact environment.

### Step 2 — System update and Ubuntu Pro (recommended, free)

```bash
sudo apt update && sudo apt upgrade -y

# Free for up to 5 machines — extends security support to April 2032
# Get a token at https://ubuntu.com/pro after creating a free account
sudo pro attach <YOUR_FREE_TOKEN>
```

### Step 3 — Install PHP 8.2+ and required extensions

`composer.json` requires PHP `^8.2`. **Important:** Ubuntu 22.04's default
package repository only provides PHP 8.1, which does NOT satisfy this
requirement — `composer install` will fail with a platform-requirement
error if you skip the PPA step below.

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-pgsql php8.2-intl unzip git

php -v    # confirm it now reports 8.2.x, not 8.1.x
```

If `php -v` still shows 8.1, run `sudo update-alternatives --config php`
and select the 8.2 entry.

### Step 4 — Install PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

### Step 5 — Create the database and user

Replace `<REAL_PASSWORD>` with an actual strong password — you'll need it
again in Step 8.

```bash
sudo -u postgres psql -c "CREATE USER ebidhub_app WITH PASSWORD '<REAL_PASSWORD>';"
sudo -u postgres psql -c "CREATE DATABASE ebidhub OWNER ebidhub_app;"
sudo -u postgres psql -d ebidhub -c "GRANT ALL ON SCHEMA public TO ebidhub_app;"
```

### Step 6 — Install Composer

```bash
sudo apt install -y composer
composer --version    # confirm it installed
```

(This server has normal internet access, so — unlike Claude's sandboxed
dev environment, see D-11 in `docs/DECISIONS.md` — Composer will reach
Packagist normally with no special handling needed.)

### Step 7 — Clone the repository

```bash
cd /var/www
sudo git clone https://github.com/psinghalnoida/ebid.oreo.git
cd ebid.oreo
sudo git checkout main
git log --oneline -5    # confirm this matches what you actually expect to deploy
```

**Re-read the warning at the top of this README before this step** —
confirm `main` actually has the commit you intend to deploy, especially
if this is the first real deployment after a lot of `dev`-only work.

### Step 8 — Install dependencies and configure the environment

```bash
sudo composer install
sudo cp env .env
sudo nano .env
```

Set at minimum (see `SETUP.md` for the full reference):

```
CI_ENVIRONMENT = production

database.default.hostname = localhost
database.default.database = ebidhub
database.default.username = ebidhub_app
database.default.password = <REAL_PASSWORD from Step 5>
database.default.DBDriver = Postgre
database.default.port = 5432
database.default.charset = utf8

app.baseURL = 'https://<YOUR_ACTUAL_DOMAIN>/'
```

**`app.baseURL` cannot be left blank** — CodeIgniter rejects an empty
value outright, and every redirect (login, listing creation, bidding)
depends on this being correct.

### Step 9 — Run the migrations

```bash
sudo php spark migrate
```

This creates every table the application needs — as of this build, 22
migrations covering parties, tenants, listings, all four sale formats,
EMD escrow, ratings, settlement, disputes, and Tender's full workflow.
Confirm with:

```bash
sudo -u postgres psql -d ebidhub -c "\dt"
```

If the migration count doesn't match what you expect, check
`app/Database/Migrations/` directly and compare against
`docs/DECISIONS.md` for what should be there.

### Step 10 — Verify the build against real data on THIS server

Before configuring the web server, confirm the application logic itself
works correctly here. These are real, permanent test commands, not
throwaway scripts — run every one of them:

```bash
sudo php spark test:cascade                # EMD engine, H1/H2/H3 cascade
sudo php spark test:rating                 # Rating engine, Crawl-Back
sudo php spark test:lifecycle              # Listing lifecycle
sudo php spark test:auth                   # Mobile/OTP/mPIN auth
sudo php spark test:buynow                 # Buy-Now offers
sudo php spark test:express                # Express Auction
sudo php spark test:settlement             # Dual-NOC settlement gate
sudo php spark test:dispute                # Dispute Resolution Framework
sudo php spark test:scheduler              # Scheduled-job automation
sudo php spark test:tier3                  # Super Admin TOTP, conflict-of-interest
sudo php spark test:easyschedule           # Easy Auction schedule + Dynamic Time
sudo php spark test:tenderfoundation       # Tender interest/eligibility/documents
sudo php spark test:tenderbidding          # Tender increment + dual-window timing
sudo php spark test:tenderreview           # Tender post-auction review workflow
sudo php spark test:easyexpresscorrections # Easy/Express increment corrections
```

If any of these fail here but passed during development, something about
*this specific server's* PHP version, PostgreSQL version, or configuration
differs — stop and investigate before going further, rather than
deploying something unverified.

### Step 11 — Fix file permissions

```bash
sudo chown -R www-data:www-data /var/www/ebid.oreo
sudo chmod -R 755 /var/www/ebid.oreo/writable
sudo chmod -R 755 /var/www/ebid.oreo/public/uploads
```

### Step 12 — Configure Nginx

```bash
sudo apt install -y nginx
sudo nano /etc/nginx/sites-available/ebidhub
```

Paste (replace `<YOUR_ACTUAL_DOMAIN>`):

```nginx
server {
    listen 80;
    server_name <YOUR_ACTUAL_DOMAIN>;
    root /var/www/ebid.oreo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

**Critical:** the document root is `public/`, not the project root — this
is a CodeIgniter requirement that keeps `app/`, `.env`, and everything
else outside what's publicly reachable.

```bash
sudo ln -s /etc/nginx/sites-available/ebidhub /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl status php8.2-fpm    # confirm it's running (installed in Step 3)
```

### Step 13 — Install and run the real-time WebSocket sidecar (D-42)

Live bidding updates (the price updating for everyone watching an
auction without needing to refresh) run through a genuinely separate
Node.js process — CodeIgniter/PHP has no native way to push updates to a
browser. This is not optional if you want bidders to see updates live;
without it, the site still works completely correctly, just without
real-time push (everyone needs to refresh to see the latest price).

```bash
# Install Node.js if not already present
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

cd /var/www/ebid.oreo/realtime
sudo npm install
```

Set a real secret (not the dev default) and add it to `.env` alongside
the database config from Step 8:

```
EBIDHUB_WS_INTERNAL_URL = http://127.0.0.1:8081/broadcast
EBIDHUB_BROADCAST_SECRET = <a real random string, not the dev default>
```

Run it as a systemd service so it survives reboots and restarts on crash:

```bash
sudo nano /etc/systemd/system/ebidhub-realtime.service
```

```ini
[Unit]
Description=eBid Hub Real-time WebSocket Sidecar
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/ebid.oreo/realtime
Environment=EBIDHUB_WS_PORT=8081
Environment=EBIDHUB_BROADCAST_SECRET=<the same real secret from .env above>
ExecStart=/usr/bin/node server.js
Restart=always
RestartSec=5
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable ebidhub-realtime
sudo systemctl start ebidhub-realtime
sudo systemctl status ebidhub-realtime    # confirm it's running
```

**Browsers need to reach port 8081 directly** (the JavaScript on the
listing page connects to `wss://<domain>:8081/ws`) — either open that
port in the firewall, or better, add a WebSocket-aware location block to
the Nginx config from Step 12 to proxy it through the same domain/port
443 instead of exposing 8081 separately:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8081;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

If you use this Nginx approach instead of exposing port 8081 directly,
update the WebSocket URL the browser connects to accordingly — this
requires a small change to `app/Views/listing/show.php`'s script block
(currently hardcoded to connect on port 8081). Flag this with Piyush if
you go this route, since it's a real code change, not just a config one.

### Step 14 — Point your domain's DNS at this server

Add an A record for `<YOUR_ACTUAL_DOMAIN>` pointing at `103.25.128.136`,
through whatever DNS provider manages that domain. This needs to happen
before Step 14 can succeed.

### Step 15 — SSL via Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d <YOUR_ACTUAL_DOMAIN>
```

### Step 16 — Provision the first Super Admin and Tenant Admin

A real Super Admin panel with genuine TOTP 2FA now exists (see
`docs/DECISIONS.md` D-29) — this is no longer a CLI-only bootstrap the
way it was in earlier builds.

**Super Admin:**
1. Register a normal account through the live site (`/register`).
2. Grant the role via CLI (no self-service UI for this specific step, by
   design — see D-29):
   ```bash
   sudo php spark grant:super-admin <mobile_number>
   ```
3. Log in normally once, then visit `/admin/setup-totp` to enroll a real
   authenticator app (Google Authenticator, Authy, etc.).
4. From then on, Super Admin access is only through the separate
   `/admin/login` — mobile + mPIN + a valid TOTP code, all three required.
5. Once logged in as Super Admin, create/whitelist your first tenant
   through `/admin/tenants/create` — no more manual database inserts
   needed for this.

**Tenant Admin** (still CLI-only — no self-service UI exists for this yet):
```bash
sudo php spark grant:tenant-admin <mobile_number> <tenant_id>
```

### Step 17 — Final verification

Visit `https://<YOUR_ACTUAL_DOMAIN>/` — should show the real marketplace
landing page (live listings grid, category counts — not a placeholder).
Visit `/trust-support` — should show the FAQ/legal hub. Try `/register`
end-to-end with a real phone number. Log in as Super Admin via
`/admin/login` and confirm the dashboard loads.

**Also confirm the real-time sidecar specifically**: open two browser
tabs (or two different devices) on the same live auction's listing page,
place a bid from one, and confirm the price updates on the *other* tab
within a second or two, with no refresh. If it doesn't update, check
`sudo systemctl status ebidhub-realtime` and the browser's developer
console for a WebSocket connection error — the site itself still works
correctly either way, this only affects the live-update experience.

---

### Before real users touch this — read first

Search the codebase for `DEV-ONLY` (`grep -rn "DEV-ONLY" app/`). Every
match is a stand-in for something not yet built — mainly payment gateway
simulation, SMS (OTP still shows on-screen), and a couple of time-based
triggers that need the scheduled-job cron entry below to actually run
automatically. Each is explained in `docs/DECISIONS.md`. This deployment
guide gets the *application* running correctly; it does not by itself
make every feature production-safe for real money and real users. Review
those markers with Piyush before opening this up beyond internal testing.

### Scheduled jobs — required for timers to work automatically

Without this cron entry, grace windows, Express's countdown, Buy-Now's
offer lapse, and settlement stall-flagging only advance via manual
dev-force actions:

```bash
crontab -e
```
Add:
```
* * * * * cd /var/www/ebid.oreo && php spark run:scheduler >> /var/log/ebidhub-scheduler.log 2>&1
```

## Verifying the build (quick reference)

See Step 10 above for the full list of fifteen test commands — all real,
permanent verification tooling, not throwaway scripts. Rerun any of them
after making a change to confirm nothing broke.
