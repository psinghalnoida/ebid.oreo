# Deployment Guide — i2k2 Dedicated Server

Target: `103.25.128.136`, Ubuntu 22.04 LTS, 6 vCPU / 8GB RAM / 400GB SSD.

**Who runs this:** whoever holds SSH/root access on the project owner's side.
Claude does not execute any of these steps directly (see docs/DECISIONS.md,
D-04) — this document exists so a human can run them deliberately, with full
visibility into what's happening at each step.

---

## 0. One-time server prep

```bash
# Confirm OS and specs match what's expected
lsb_release -a          # should show Ubuntu 22.04
nproc                   # should show 6
free -h                 # should show ~8GB
df -h                   # should show ~400GB available

# Enable Ubuntu Pro (free tier, extends security support to April 2032)
# Get a free token at https://ubuntu.com/pro after creating an account
sudo pro attach <YOUR_FREE_TOKEN>

# System update
sudo apt update && sudo apt upgrade -y
```

## 1. Install Docker + Docker Compose

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker run hello-world
docker compose version
```

## 2. Clone the repository

```bash
git clone https://github.com/psinghalnoida/ebid.oreo.git
cd ebid.oreo
git checkout dev    # or 'testing', per current branch convention
```

(Requires GitHub auth — PAT or SSH key — configured on this machine first.)

## 3. Configure environment

```bash
cp .env.example .env
nano .env   # fill in real values — POSTGRES_PASSWORD, JWT_SECRET at minimum
```

**Never commit `.env`.** It's git-ignored by design.

## 4. Build and run

```bash
docker compose up -d --build
docker compose ps        # confirm all services are 'running'/'healthy'
```

## 5. Verify the walking-skeleton health check

```bash
curl http://localhost:4000/health
# Expect: {"app":"ok","database":"ok","redis":"ok"}
```

If `database` or `redis` show an error instead of `ok`, check
`docker compose logs backend` before proceeding further.

## 6. Nginx reverse proxy + SSL (production only)

```bash
sudo apt install -y nginx certbot python3-certbot-nginx

# Point your domain's DNS A record at 103.25.128.136 first, then:
sudo certbot --nginx -d yourdomain.com
```

Example Nginx config (`/etc/nginx/sites-available/ebidhub`):

```nginx
server {
    server_name yourdomain.com;

    location /api/ {
        proxy_pass http://localhost:4000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location / {
        proxy_pass http://localhost:5173/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/ebidhub /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## Redeploying after code changes

```bash
git pull origin main          # only after dev -> main merge is approved
docker compose up -d --build  # rebuilds and restarts changed services
```

## Rollback

```bash
git log --oneline             # find the last known-good commit
git checkout <commit-hash>
docker compose up -d --build
```
