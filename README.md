# eBid Hub

Multi-tenant B2B/B2C salvage & surplus auction platform.

Super Admin: Piyush Singhal (psinghalnoida@gmail.com)

## Governing Document

This project's business rules and process workflows (BR-01 to BR-50, PR-01 to PR-28)
are defined in the Unified BR/PR document held outside this repo. Every piece of
business logic implemented here must trace back to a specific BR/PR reference —
see code comments (e.g. `// BR-27: EMD Baseline Calculation`) throughout.

## Project Structure

```
/backend    Node.js + Express API — business logic, EMD engine, rating engine,
            listing lifecycle, auth. See backend/README.md.
/frontend   React (Vite + TypeScript) UI — mockups being progressively wired
            to the real API. See frontend/README.md.
/docs       Build-process documentation (not business rules — those live in
            the BR/PR document). DECISIONS.md logs technical/infra decisions
            made during development, same "discuss first, log why" discipline
            as BR-01 applies to the BR/PR document itself.
```

## Branching

- `main` — production. Only updated via deliberate, reviewed merge. Deploys to
  the live i2k2 server.
- `dev` (a.k.a. `testing`) — active development. All work happens here first.

## Getting Started (Local Development)

See `DEPLOY.md` for full instructions. Quick version:

```bash
cp .env.example .env      # fill in real values
docker compose up -d --build
```

Backend API: http://localhost:4000
Frontend dev server: http://localhost:5173

## Deployment

See `DEPLOY.md` — written specifically for the current target server
(i2k2 dedicated, Ubuntu 22.04 LTS).

## Status

**Phase 0 — Foundation.** No sale format is functional yet. This skeleton
establishes structure only: folder layout, Docker Compose wiring, environment
config, and deployment docs. Next: data model + EMD engine + rating engine,
then the Easy Auction vertical slice end-to-end.
