# Technical Decision Log

Same discipline as BR-01 (Discuss First & Rationale Archive) in the governing
BR/PR document, applied to build/infra decisions rather than business rules.
Every decision below was discussed and confirmed with the Super Admin
(Piyush Singhal) before being acted on. Nothing here is assumed or defaulted
silently.

---

### D-01: Vertical-slice build order over full-breadth scaffold

**Decision:** Build one complete, working sale format end-to-end before
starting the next, rather than a shallow skeleton across all formats at once.

**Order:** Phase 0 (shared foundation: data model, EMD engine, rating engine,
listing lifecycle, tenant model, auth) → Easy Auction (first full slice) →
remaining formats (Buy-Now, Express, Tender) → admin surfaces (Tenant Admin,
Super Admin) → Phase 2 items (Reverse Auction / procurement) per the
roadmap's own phasing.

**Rationale:** Easy Auction touches the most shared plumbing (listing
lifecycle, EMD gate, Dynamic Time bidding, H1/H2/H3 cascade, dual-NOC
settlement, rating update), so completing it first proves out Phase 0 and
makes every subsequent format faster to add. A shallow build across
everything at once produces something buggy everywhere rather than solid
anywhere.

---

### D-02: Deployment target — i2k2 dedicated server, not GCP

**Decision:** The original BR/PR document's Section 3 tech stack specifies
Google Cloud Run, Cloud SQL, and Secret Manager. The actual deployment target
is a pre-existing i2k2 dedicated server (IP 103.25.128.136), not GCP.

**Adaptation:**
| Original (GCP)            | Actual (i2k2, self-managed)              |
|----------------------------|-------------------------------------------|
| Cloud Run                  | Docker Compose running directly on the server |
| Cloud SQL                  | PostgreSQL container on the same server    |
| Secret Manager             | `.env` file on the server, git-ignored     |
| Cloud Build → Cloud Run    | GitHub → manual pull + `docker compose up` on server |
| Cloudflare CDN              | Unchanged — works in front of any origin server |

**Rationale:** The dedicated server was already provisioned before this
project's tooling discussion; re-platforming to GCP was not evaluated as
necessary given the server's specs (6 vCPU / 8GB RAM / 400GB SSD) comfortably
meet the platform's Phase 1 needs.

---

### D-03: OS — Ubuntu 22.04 LTS (server as-provisioned), not reimaged

**Decision:** Server was found running CentOS 7 (end-of-life since June
2024) but had already been reimaged to Ubuntu 22.04 LTS by the time of
confirmation. Accepted as-is — no further reimage to 24.04.

**Rationale:** CentOS 7 was unacceptable (no security patches — unsuitable
for a platform holding buyer EMD funds in escrow). Ubuntu 22.04 LTS carries
standard security support until April 2027, with full compatibility across
the entire planned stack (Docker, Node.js, PostgreSQL, Redis, Nginx,
Certbot). Forcing a further reimage to 24.04 was judged unnecessary friction
given 22.04 is not end-of-life.

**Follow-up action:** Enable Ubuntu Pro (free tier, up to 5 machines) on the
server to extend security coverage (ESM) to April 2032, at zero cost. Plan a
`do-release-upgrade` to 24.04 before April 2027 as routine maintenance, not
an urgent blocker.

---

### D-04: Deploy boundary — no AI-direct writes to the production server

**Decision:** Claude (in any form — this chat, or Claude Code) never writes,
edits, or executes anything directly on the production server, even when
technical SSH access exists.

**Flow:** Claude writes code locally / in a Claude Code project → commits
pushed to GitHub → the human with SSH access on the project owner's side
pulls from GitHub and runs the deploy step manually.

**Rationale:** Ensures every production change is a deliberate, visible,
human-executed action with a git-commit audit trail — consistent with the
platform's own audit-trail principle (BR-05) applied to the build process
itself, not just the running application.

---

### D-05: GitHub as the deployment handoff layer

**Decision:** GitHub repo `psinghalnoida/ebid.oreo` (private) is the single
handoff point between AI-written code and server deployment.

**Branching:** `main` = production (only updated via deliberate merge from
`dev`, approved by the project owner). `dev` (a.k.a. `testing`) = active
development branch.

**Rationale:** Directly enforces D-04 — merging `dev` → `main` becomes the
explicit human decision point that gates what reaches production.

---

### D-06: UI design direction — Modern Marketplace Minimal

**Decision:** After reviewing three concept directions (Auction House
Editorial, Industrial Premium, Modern Marketplace Minimal), the project
owner selected **Modern Marketplace Minimal** — off-white background,
near-black text, emerald accent, Sora typeface, soft rounded cards and pill
buttons — as the visual direction for the landing page and auction page.

**Rationale:** Chosen over the platform's earlier "salvage manifest"
aesthetic (graphite/paper/amber/teal/rust, Inter + IBM Plex Mono) in favor of
a more polished, ecommerce-familiar look.

**Note:** Per D-01-adjacent reasoning, backend/business logic development is
sequenced ahead of UI polish. Screens may be built functionally plain first
and restyled to this direction afterward without touching business logic,
since frontend and backend are fully decoupled (BR-10's Listing-vs-Sale-Event
separation extends naturally to this UI/logic separation).

---

### D-07: Repository reset

**Decision:** The `ebid.oreo` repository was fully reset (history wiped, not
just files deleted) after pre-existing "OREO" architecture docs and database
schema content of unconfirmed origin were found in it.

**Rationale:** That content's provenance and the decisions behind it could
not be confirmed with the project owner, and building on undocumented,
unreviewed decisions would violate the BR-01 discipline this entire project
is built around. A clean slate was judged safer than attempting to reconcile
unexplained prior work.

**Note:** A separate, unrelated public repository (`Ebid-Hub`, an Android /
Google AI Studio scaffold referencing a Gemini API key) was also discovered
during this process and confirmed **not** to be part of this project. It is
not touched by any decision in this log.

---

### D-08: Rating engine — OPEN ITEM: Shadow Banning threshold unconfirmed

**Status:** ⚠️ NOT CONFIRMED — placeholder value in use, needs Super Admin decision.

**Context:** BR-38 states Shadow Banning applies at "a further threshold"
below the Crawl-Back trigger (2.0★), without stating the number. Unlike
Crawl-Back's clean-transaction ladder (3/5/8, settled in prior project
work) and the ₹50k deposit-override floor (also settled), no specific
Shadow Banning threshold has been confirmed with the Super Admin.

**Current placeholder in code:** 1.5★ (`SHADOW_BAN_THRESHOLD` in
`ratingService.js`) — chosen only to keep the engine testable, not as a
business decision.

**Action required:** Confirm the actual Shadow Banning threshold with the
Super Admin before this is treated as final. Until confirmed, do not rely
on 1.5★ in any downstream decision (UI copy, tenant communication, etc.).

---

### D-09: Trust & Support hub restyled to Modern Marketplace Minimal

**Decision:** The Trust & Support hub (FAQ, Dos & Don'ts, Fee Schedule,
Refund Policy, Dispute Resolution, ToS, Privacy, Grievance Redressal,
Security & Trust, Contact Us — built in an earlier session with a different
design system) is restyled into Modern Marketplace Minimal (off-white,
near-black, emerald, Sora), matching the landing/auction pages.

**Rationale:** The two designs conflicted; project owner confirmed this
direction should apply site-wide rather than maintaining two visual
languages.

---

### D-10: Stack pivot — Node.js/Express/React → CodeIgniter 4 (PHP), server-rendered views

**Decision:** At Arpit's (the project owner's SSH/deployment contact)
request, the backend and frontend stack changes from Node.js/Express with
a separate React SPA to **CodeIgniter 4 (PHP)**, using **CodeIgniter's own
server-rendered views** rather than a separate frontend application.

**Rationale (as relayed):** Arpit's operational comfort is with a PHP/LAMP-
style stack on i2k2 hosting, which runs natively via Apache/Nginx +
PHP-FPM without a separate Node process to manage.

**Cost, confirmed with project owner before proceeding:** the EMD engine
and rating engine built and tested in Node.js (39 passing assertions
across both, against real PostgreSQL data) do not run under PHP and must
be fully rewritten. The 9 SQL migration files are reusable (standard
PostgreSQL DDL), pending porting into CodeIgniter's own migration format.
The Node/React skeleton itself (Docker Compose targeting a Node container,
the Vite/React frontend) is superseded and not carried forward.

**What is NOT changing:** the underlying BR/PR business rules, the
database schema design (party/tenant/listing/sale_event/bid/emd_hold/
rating_event), the i2k2/Ubuntu 22.04 deployment target, the GitHub
handoff flow (D-05), and the no-AI-direct-server-writes boundary (D-04) —
this is a stack change, not a re-opening of those decisions.

---

### D-11: Sandbox verification method for CodeIgniter (network-restricted environment)

**Context (informational, not a project decision):** Claude's sandboxed
dev environment blocks network access to Packagist (`repo.packagist.org`,
`getcomposer.org`), so `composer install`/`composer create-project` cannot
resolve CodeIgniter's dependencies inside that sandbox. This has no effect
on the actual project — Arpit's real server has normal internet access and
`composer install` will work there exactly as it would on any standard
CodeIgniter deployment.

**What was done to verify the delivered code anyway:** rather than deliver
untested code, the framework source and its two runtime dependencies
(laminas/laminas-escaper, psr/log) were pulled directly from their GitHub
source repositories (allowed in the sandbox's network policy) and manually
wired together with a hand-written autoloader, purely to prove the
delivered controllers/views/routes actually execute correctly under a real
CodeIgniter boot — confirmed via HTTP 200 responses and correct dynamic
content rendering on both the landing page and Trust & Support hub. This
verification scaffolding (`vendor/manual-autoload.php` and similar) is
**not** included in the delivered project — the delivered `composer.json`
is the standard, unmodified `codeigniter4/appstarter` manifest, intended to
be installed normally via `composer install`.


