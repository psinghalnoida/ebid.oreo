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

- **`SETUP.md`** — local development setup, what's built and what isn't,
  and the exact convention new models must follow.
- **`docs/DECISIONS.md`** — every architectural and technical decision
  made on this project, with its reasoning, in chronological order (D-01
  through the present). If you're wondering *why* something was built a
  particular way, this is where to look before assuming it's a mistake.
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
```

`main` currently holds everything through commit `6765172` — the full
CodeIgniter 4 rebuild, all three working sale formats, and the repo
cleanup pass. Confirm with `git log --oneline -5` that this matches what
you expect.

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

This creates all 11 tables. Confirm with:

```bash
sudo -u postgres psql -d ebidhub -c "\dt"
```

You should see: `party`, `tenant`, `party_role`, `listing`, `sale_event`,
`bid`, `emd_hold`, `rating_event`, `otp_verification`, `offer`, plus
CodeIgniter's own `migrations` tracking table.

### Step 10 — Verify the build against real data on THIS server

Before configuring the web server, confirm the application logic itself
works correctly here — these are the same test suites verified during
development (121 assertions across all six, zero failures expected):

```bash
sudo php spark test:cascade
sudo php spark test:rating
sudo php spark test:lifecycle
sudo php spark test:auth
sudo php spark test:buynow
sudo php spark test:express
```

If any of these fail here but passed during development, something about
*this specific server's* PHP version, PostgreSQL version, or configuration
differs — stop and investigate before going further, rather than
deploying something unverified.

### Step 11 — Fix file permissions

```bash
sudo chown -R www-data:www-data /var/www/ebid.oreo
sudo chmod -R 755 /var/www/ebid.oreo/writable
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

### Step 13 — Point your domain's DNS at this server

Add an A record for `<YOUR_ACTUAL_DOMAIN>` pointing at `103.25.128.136`,
through whatever DNS provider manages that domain. This needs to happen
before Step 14 can succeed.

### Step 14 — SSL via Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d <YOUR_ACTUAL_DOMAIN>
```

### Step 15 — Provision the first Tenant Admin

There's no Super Admin panel yet — someone needs to register a normal
account through the live site first (`/register`), then:

```bash
sudo php spark grant:tenant-admin <their_mobile_number> <tenant_id>
```

(You'll need a `tenant_id` — if no tenant exists yet, one needs to be
created directly in the database for now, since there's no tenant-creation
UI yet either. Ask Piyush for the intended first tenant's details before
this step.)

### Step 16 — Final verification

Visit `https://<YOUR_ACTUAL_DOMAIN>/` in a browser — should show the
landing page. Visit `/trust-support` — should show the FAQ/legal hub.
Try `/register` end-to-end with a real phone number.

---

### Before real users touch this — read first

Search the codebase for `DEV-ONLY` (`grep -rn "DEV-ONLY" app/`). Every
match is a stand-in for something not yet built — mainly payment gateway
simulation, and a couple of missing ownership checks — each explained in
`docs/DECISIONS.md`. This deployment guide gets the *application* running
correctly; it does not by itself make every feature production-safe for
real money and real users. Review those markers with Piyush before
opening this up beyond internal testing.

## Verifying the build (quick reference)

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
