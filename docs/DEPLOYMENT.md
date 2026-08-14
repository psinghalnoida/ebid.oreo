# Deploying to Cloud Run — one-time GCP setup

`.github/workflows/deploy.yml` deploys automatically on every push to
`main` that the regression suite (`.github/workflows/tests.yml`)
passed for. It does **not** provision any infrastructure itself —
that's a one-time setup, done once by whoever owns the GCP project,
using the commands below. Run these once (an ops engineer, not
necessarily a developer) before the first deploy; `deploy.yml` will
then run unattended on every subsequent merge to `main`.

This matches the platform's own documented tech stack (Docker on Cloud
Run, GitHub Actions, Cloud Build) — see the master Business Rules
document's Section 4, and mirrors this repo's own real requirements
(`SETUP.md`), not a generic template.

## What actually gets deployed

Three Cloud Run resources, all from the same two container images
(`Dockerfile` / `realtime/Dockerfile`):

| Resource | Type | What it runs |
|---|---|---|
| `ebidhub-app` | Cloud Run **service** | The PHP app (Apache/mod_php), public-facing |
| `ebidhub-realtime` | Cloud Run **service** | The WebSocket sidecar (D-42), public-facing |
| `ebidhub-migrate` | Cloud Run **job** | `php spark migrate --all`, runs once per deploy |
| `ebidhub-scheduler` | Cloud Run **job** | `php spark run:scheduler`, triggered every minute by Cloud Scheduler — replaces `SETUP.md`'s cron entry, since Cloud Run has no persistent process to cron against |

## 0. Prerequisites

- A GCP project with billing enabled.
- `gcloud` CLI authenticated as a project owner/editor (for this
  one-time setup only — the GitHub Actions workflow uses its own,
  narrowly-scoped service account, never a human's credentials).
- This repo's GitHub remote (`psinghalnoida/ebid.oreo`) — Workload
  Identity Federation is bound to it by name below.

Set these once in your shell for the rest of this doc:

```bash
export PROJECT_ID="your-gcp-project-id"
export REGION="asia-south1"                # Mumbai — adjust if you serve elsewhere
export GITHUB_REPO="psinghalnoida/ebid.oreo"

gcloud config set project "$PROJECT_ID"
```

## 1. Enable the required APIs

```bash
gcloud services enable \
  run.googleapis.com \
  artifactregistry.googleapis.com \
  sqladmin.googleapis.com \
  secretmanager.googleapis.com \
  cloudscheduler.googleapis.com \
  cloudresourcemanager.googleapis.com \
  iamcredentials.googleapis.com \
  storage.googleapis.com
```

## 2. Artifact Registry — where built images live

```bash
gcloud artifacts repositories create ebidhub \
  --repository-format=docker \
  --location="$REGION" \
  --description="eBid Hub app + realtime images"
```

`GCP_ARTIFACT_REPO` (a GitHub repo **variable**, set in step 8) = `ebidhub`.

## 3. Cloud SQL — MySQL 8

Matches `SETUP.md`'s real schema requirements exactly (same
`ebidhub`/`ebidhub_app` names used throughout local dev and CI).
Originally PostgreSQL 16 in this pipeline's first version; migrated to
MySQL (see `docs/DECISIONS.md`'s MySQL migration entry for the full,
empirically-verified conversion — 67 migration files converted, every
Postgres-specific construct (`gen_random_uuid()`, `CREATE TYPE ... AS
ENUM`, partial unique indexes, `pg_advisory_lock`, etc.) replaced with
its real MySQL equivalent, all 39 `test:*` regression suites re-run
green against a real local MySQL 8 server).

```bash
gcloud sql instances create ebidhub-prod \
  --database-version=MYSQL_8_0 \
  --tier=db-custom-2-8192 \
  --region="$REGION" \
  --storage-auto-increase \
  --database-flags=character_set_server=utf8mb4,collation_server=utf8mb4_unicode_ci

gcloud sql databases create ebidhub --instance=ebidhub-prod \
  --charset=utf8mb4 --collation=utf8mb4_unicode_ci

# Pick a real password — this becomes the ebidhub-db-password secret
# in step 5, never committed anywhere.
gcloud sql users create ebidhub_app \
  --instance=ebidhub-prod \
  --password="$(openssl rand -base64 24)"
```

**Known, accepted MySQL-vs-Postgres gap**: the original Postgres
migrations locked `audit_log`/`consent_event`/`invoice` down with
`REVOKE UPDATE, DELETE, TRUNCATE ... GRANT INSERT, SELECT` so the
app's own DB connection could never modify or destroy a row after
writing it — real privilege-level tamper *prevention*, on top of
`audit_log`'s SHA-256 hash chain (tamper *evidence*). That REVOKE/GRANT
scheme is confirmed **not achievable on Cloud SQL for MySQL either**:
MySQL's `partial_revokes` mechanism only carves a *database*-level
restriction out of a *global* (`*.*`) grant, never a *table*-level
restriction out of the *database*-level grant `ebidhub_app` needs for
ordinary migrations to run. Reproducing it would mean never granting
`ebidhub_app` database-wide and instead enumerating UPDATE/DELETE per
table by hand — rejected as fragile (any future table whose migration
forgets the grant silently loses app write access). `ebidhub_app`
therefore retains full DML on these three tables in production too;
the hash chain (`AuditLogService::verifyChainIntegrity()`) remains the
real, working tamper-evidence guarantee, unaffected by this gap.

Get the instance connection name (used everywhere below as
`GCP_CLOUDSQL_CONNECTION_NAME`):

```bash
gcloud sql instances describe ebidhub-prod --format='value(connectionName)'
# -> your-project:asia-south1:ebidhub-prod
```

## 4. GCS bucket — listing photo/document uploads

`MediaService` writes uploaded listing media to what it treats as a
local path (`public/uploads/listings/`) — real, unmodified application
behavior, not something this deploy pipeline changes. Cloud Run's
filesystem is stateless and ephemeral across instances and deploys, so
that path is mounted onto this bucket via Cloud Run gen2's native
Cloud Storage FUSE volume support (`deploy.yml`'s `--add-volume`/
`--add-volume-mount` flags) — the app never finds out its "local"
files are actually in GCS.

```bash
gcloud storage buckets create "gs://${PROJECT_ID}-ebidhub-uploads" \
  --location="$REGION" \
  --uniform-bucket-level-access
```

`GCP_UPLOADS_BUCKET` (variable) = `${PROJECT_ID}-ebidhub-uploads`.

## 5. Secret Manager — the four real secrets the app reads

Cross-referenced directly against every `getenv()`/`env()` call in
`app/Libraries/` — nothing here is guessed:

```bash
# Real value from step 3
printf '%s' 'THE_REAL_DB_PASSWORD_FROM_STEP_3' | \
  gcloud secrets create ebidhub-db-password --data-file=-

# Shared secret between the app and the realtime sidecar
# (RealtimeBroadcastService <-> realtime/server.js) — generate once,
# both services get the same value via --set-secrets in deploy.yml.
openssl rand -hex 32 | gcloud secrets create ebidhub-broadcast-secret --data-file=-

# Signs the Tenant API's OAuth2 client-credentials tokens
# (ApiCredentialService) — BR-62-66/PR-37.
openssl rand -hex 32 | gcloud secrets create ebidhub-api-token-secret --data-file=-

# BR-46's AI Listing Pre-Audit — an accepted, already-known external
# dependency (see docs/DECISIONS.md/BR_PR_AUDIT.md). Fine to seed with
# a placeholder now; GeminiPreAuditService checks for a non-empty
# value and simply stays inert (advisory-only, never blocking) until a
# real key is set here.
printf '%s' 'REPLACE_WITH_REAL_GEMINI_KEY_WHEN_AVAILABLE' | \
  gcloud secrets create ebidhub-gemini-api-key --data-file=-
```

**Still not wired by this pipeline, unchanged from before** — a real
payment gateway and a real SMS provider. Both are accepted, tracked
external dependencies (`SETUP.md`'s "Not yet built" section), not
something a deploy pipeline can close on its own; connecting them is
real application work for whenever those accounts/credentials exist.

## 6. Runtime service account — what the deployed services run as

Separate from the GitHub Actions deploy identity in step 7 — this is
who `ebidhub-app`/`ebidhub-realtime`/the two jobs actually run as once
live, needing read access to the secrets and the Cloud SQL instance,
nothing more.

```bash
gcloud iam service-accounts create ebidhub-runtime \
  --display-name="eBid Hub Cloud Run runtime identity"

RUNTIME_SA="ebidhub-runtime@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member="serviceAccount:${RUNTIME_SA}" --role="roles/cloudsql.client"

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member="serviceAccount:${RUNTIME_SA}" --role="roles/secretmanager.secretAccessor"

gcloud storage buckets add-iam-policy-binding "gs://${PROJECT_ID}-ebidhub-uploads" \
  --member="serviceAccount:${RUNTIME_SA}" --role="roles/storage.objectAdmin"
```

Add `--service-account="$RUNTIME_SA"` to each `gcloud run deploy`/
`gcloud run jobs deploy` command in `deploy.yml` if you want this
explicit (Cloud Run uses the project's default compute service account
otherwise, which also works but is broader-scoped than necessary).

## 7. Deploy service account + Workload Identity Federation (for GitHub Actions)

Keyless — no long-lived JSON key ever leaves Google, GitHub
authenticates via short-lived OIDC tokens instead.

```bash
gcloud iam service-accounts create ebidhub-deployer \
  --display-name="eBid Hub GitHub Actions deployer"

DEPLOY_SA="ebidhub-deployer@${PROJECT_ID}.iam.gserviceaccount.com"

for ROLE in roles/run.admin roles/artifactregistry.writer \
            roles/iam.serviceAccountUser roles/cloudscheduler.admin \
            roles/cloudsql.client; do
  gcloud projects add-iam-policy-binding "$PROJECT_ID" \
    --member="serviceAccount:${DEPLOY_SA}" --role="$ROLE"
done

gcloud iam workload-identity-pools create github-actions \
  --location="global" --display-name="GitHub Actions"

gcloud iam workload-identity-pools providers create-oidc github \
  --location="global" --workload-identity-pool="github-actions" \
  --display-name="GitHub OIDC" \
  --attribute-mapping="google.subject=assertion.sub,attribute.repository=assertion.repository" \
  --issuer-uri="https://token.actions.githubusercontent.com"

# Restrict to THIS repo specifically, not any repo in your GitHub org.
gcloud iam service-accounts add-iam-policy-binding "$DEPLOY_SA" \
  --role="roles/iam.workloadIdentityUser" \
  --member="principalSet://iam.googleapis.com/projects/$(gcloud projects describe "$PROJECT_ID" --format='value(projectNumber)')/locations/global/workloadIdentityPools/github-actions/attribute.repository/${GITHUB_REPO}"
```

Get the provider resource name for the GitHub secret in step 8:

```bash
gcloud iam workload-identity-pools providers describe github \
  --location=global --workload-identity-pool=github-actions \
  --format='value(name)'
# -> projects/PROJECT_NUMBER/locations/global/workloadIdentityPools/github-actions/providers/github
```

## 8. GitHub repo secrets & variables

In the repo's Settings → Secrets and variables → Actions:

**Secrets** (sensitive):
| Name | Value |
|---|---|
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | The `projects/.../providers/github` string from step 7 |
| `GCP_SERVICE_ACCOUNT_EMAIL` | `ebidhub-deployer@PROJECT_ID.iam.gserviceaccount.com` |

**Variables** (not secret, just deploy-time config):
| Name | Value |
|---|---|
| `GCP_PROJECT_ID` | Your project ID |
| `GCP_REGION` | e.g. `asia-south1` |
| `GCP_ARTIFACT_REPO` | `ebidhub` (from step 2) |
| `GCP_UPLOADS_BUCKET` | `PROJECT_ID-ebidhub-uploads` (from step 4) |
| `GCP_CLOUDSQL_CONNECTION_NAME` | `PROJECT_ID:REGION:ebidhub-prod` (from step 3) |
| `APP_BASE_URL` | `https://yourdomain.com/` — **must** match `SETUP.md`'s own warning: empty or wrong host breaks every redirect (login, bidding, listing creation) |

## 9. First deploy

Push to `main` (or merge a PR into it) once the above is done — the
regression suite runs, and on its success `deploy.yml` builds both
images, deploys both services, runs migrations, and deploys the two
Cloud Run Jobs plus the Cloud Scheduler trigger, all unattended.

Watch the Actions tab for the first run especially closely — this is
the first time these exact `gcloud run deploy` commands execute for
real; if a flag or IAM binding needs adjusting, this is where it'll
show up.

## 10. Custom domain (optional, do once you have one)

```bash
gcloud run domain-mappings create --service=ebidhub-app \
  --domain=yourdomain.com --region="$REGION"
```

Update `APP_BASE_URL` (step 8) to match once DNS propagates — this is
exactly the field `SETUP.md` already warns is not optional to get
right.

## Known limitations of this pipeline, stated plainly

- **The real payment gateway, SMS provider, and (until step 5's
  placeholder is replaced) the Gemini key remain external dependencies
  this pipeline does not and cannot close** — they need real
  third-party accounts, not more infrastructure code.
- **OTP is still shown on-screen in every environment, including
  `CI_ENVIRONMENT=production`** — `AuthController`'s `devOtp` isn't
  gated on environment anywhere in the codebase today. `SETUP.md` has
  flagged this as a "must remove before production" item for a while;
  this deploy pipeline makes going live mechanically possible, which
  makes that pre-existing gap newly urgent rather than theoretical.
  Worth fixing (gate `devOtp` behind `ENVIRONMENT !== 'production'`)
  before real user signups start, independent of this pipeline itself.
- **This pipeline was written and syntax-validated but not built
  end-to-end in a real GCP project** — this development sandbox has no
  GCP credentials and (separately) its outbound network policy blocks
  `deb.debian.org`, so `docker build` on `Dockerfile` (the apt-get
  step specifically) could not be run to completion here. What *was*
  verified for real: both base images pull cleanly via `mirror.gcr.io`,
  `realtime/Dockerfile` builds and its container actually runs (started
  the container, confirmed it listens and its auth check genuinely
  rejects an unauthenticated broadcast request), `docker-compose.yml`
  parses cleanly, and a genuine bug in `Dockerfile` (composer's
  platform check failing against a *different* PHP than the one the
  app actually ships in) was found and fixed by that partial build
  attempt. The first real end-to-end build happens in GitHub Actions
  on the first push past this doc — watch that run.
- **Re-attempted after the MySQL migration (D-124+), same result**:
  `Dockerfile`'s apt-get step now installs `default-libmysqlclient-dev`
  and `docker-php-ext-install pdo_mysql mysqli` instead of the Postgres
  equivalents — a real local build was re-attempted to verify this, and
  it hit the exact same `deb.debian.org` sandbox network block as
  before (base images still pull cleanly via `mirror.gcr.io`; only the
  apt-get layer is blocked). `pdo_mysql`/`mysqli` are core, always-
  bundled PHP extensions and `default-libmysqlclient-dev` is the
  standard Debian package for their build headers — a far more common,
  well-trodden combination than `pdo_pgsql`/`pgsql` was — but this
  specific layer still hasn't completed a real build in this sandbox.
  Watch the first GitHub Actions run after this change lands, same as
  before.
