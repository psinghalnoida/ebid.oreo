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
