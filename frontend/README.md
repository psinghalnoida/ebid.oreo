# eBid Hub — Frontend

React (Vite + TypeScript). Currently a minimal connectivity check only — see
root README for status.

## Where the mockups fit in

The standalone HTML mockups built earlier (landing page + auction page,
"Modern Marketplace Minimal" direction) are **not yet part of this app**.
They were static, single-file HTML/CSS demos for design review. Once Phase 0
backend logic exists, these will be rebuilt as real React
components/pages under `src/pages/` and `src/components/`, consuming the
backend API instead of hardcoded sample data — same visual design, real data
underneath.

## Folder structure (planned use)

```
src/
  pages/        One file per screen (Landing, Marketplace, Item Detail,
                Live Ticker, Buy-Now Offer Flow, Tenant Admin, ...)
  components/   Shared, reusable UI pieces (buttons, cards, badges) built
                once against the design system, reused across pages
```
