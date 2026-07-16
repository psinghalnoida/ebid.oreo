# eBid Hub — Backend

Node.js + Express API. Real-time bidding uses WebSockets/Socket.io and Redis
(added when the Easy Auction slice is built — not yet wired in this
skeleton).

## Folder structure (planned use — mostly empty placeholders for now)

```
src/
  config/       DB pool, Redis client, environment loading
  models/       Party, Tenant, Listing, SaleEvent, and related entities
                (BR-10: Listing and Sale Event are separate entities)
  routes/       Express route definitions, one file per resource
  controllers/  Request handling — thin, delegates to services/
  services/     Business logic lives here. Each service traces to specific
                BR/PR references, e.g. emdService.js → BR-25 to BR-29,
                ratingService.js → BR-35 to BR-39, listingLifecycleService.js
                → BR-13, BR-14.
  middleware/   Auth (BR-02 mobile/OTP/mPIN), role checks (BR-15, BR-21,
                BR-22), request logging (feeds BR-05 audit trail)
  migrations/   SQL migration files, applied in order
```

## Current status

Only `src/index.js` exists, exposing `/` and `/health`. No business logic yet
— this is intentionally just enough to prove the container runs and can
reach Postgres and Redis, before any real feature is built on top of it.

## Local dev

Handled via the root `docker-compose.yml` — see the main README and
`DEPLOY.md`. Do not run this in isolation from Postgres/Redis without adjusting
env vars.
