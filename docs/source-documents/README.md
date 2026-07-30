# Source Documents

These are the original governing documents this entire platform is built
against — not generated from the code, the other way around. When any
business rule, process workflow, or design decision in `docs/DECISIONS.md`
cites a BR or PR number, it's referencing these files directly.

**`ADWITIX_Master.docx`** is the single most important file here — and,
as of 2026-07-30, the canonical replacement for the retired
`eBid_Hub_Unified_BR_PR.docx` (recoverable via `git log -p` on this
file's old path if ever needed). It's the single, absolute source of
truth for the platform's Business Rules and Process Workflows — the
actual basis for this whole project. Any time the code and this
document disagree, this document wins; `docs/DECISIONS.md` explains the
reasoning whenever the build diverges or a rule needed clarification
from the project owner.

**Replaced 2026-07-30** (was BR-01–BR-66/PR-01–PR-37 in
`eBid_Hub_Unified_BR_PR.docx`; now BR-01–BR-68/PR-01–PR-37 in
`ADWITIX_Master.docx`) — the project owner's own "Status" line
describes this as "Fully reconciled — supersedes all prior governance
drafts, the separately-issued Consolidated Specification, and all
standalone Business Model documents." A real binary `.docx`. What's new:

- **A complete Section 6 (Business Model) — new, not present in any prior version.** Product tiers (CoCo Starter/Concierge, TSX Launch/Growth/Enterprise), a subscription discount ladder, storage/media allowances per tier, optional professional services and Enterprise add-ons, and the platform's overall revenue-line priorities.
- **The commission model is fundamentally rewritten** (BR-08, BR-09, BR-31 through BR-34, BR-56, BR-12, and PR-06/PR-32 — six contradictions the document itself names as resolved by this pass). The old flat-0.5%-SaaS-plus-tenant-adjustable-0.5%–5%-band model is replaced by a **single, platform-wide, non-tenant-adjustable declining Success Fee schedule** (2.00% down to 0.50% by final sale value, minimum ₹500+GST — Section 6.4) plus a new **Fee Payer Election** per Trading Session/Sale Event (Buyer-Pays, the default, or Seller-Pays, restricted to paid tiers). **Built** — see `docs/DECISIONS.md` D-87 (the rewrite itself, flagged for the project owner before any code changes) and D-88 (the discussion that resolved BR-32/33's own internal contradiction — Seller-Pays cannot literally deduct from the seller's proceeds given BR-33's offline-payment model — via a monthly Tenant-billing mechanism, plus the full build and a further amendment to this document's own BR-31/32/6.4 text reflecting that mechanism).
- **BR-67: Branded Terminology Layer** (new) — formalizes "TradeSphereX"/TSX as the commercial-facing brand name mapped onto the existing technical roles (Tenant→TSX, Tenant Admin→TSX Master, Seller→Market Maker, Buyer→Trader, Super Admin→Custodian, Listing→Lot, Sale Event→Trading Session) — explicitly a presentation-layer mapping, not a data-model rename. Matches the branding already used in `public/pricing.html` (D-86).
- **BR-68: Visual Identity** (new) — a canonical color/typography system for ADWITIX-branded surfaces, matching the palette already used on the pricing page.
- Tech Stack (Section 4) is otherwise unchanged from the prior version: SabPaisa as PG, KYC manual-only, server-time integrity, audit-log DB-permission hardening, and independent security audit all confirmed the same as before.
- Section 5 (Phased Roadmap) confirms Phase 1 scope unchanged, now explicit that Section 6's commercial model is Phase 1, in-scope, "now the sole authority on pricing, superseding the flat-fee figures originally stated in BR-08/BR-31."

**Restructured 2026-07-30 (same day, follow-up pass)** — a new **Section 1 (Terminology)** was inserted at the front of the document, ahead of the Business Rules, and every other section shifted down by one (old Sections 1–5 are now 2–6; every internal cross-reference, e.g. "Section 5.4," was updated to match, e.g. "Section 6.4"). Section 1 has two parts: **Part A** is BR-67's technical-to-branded mapping table, moved here from inside BR-67 (BR-67 itself stays in Section 2 as a citable rule, now pointing here instead of duplicating the table). **Part B** is a 36-term plain-language glossary — the 30 terms from `eBid_Hub_Terminology.jsx` transcribed into the document, plus 4 new entries (Success Fee, Fee Payer Election, Subscription Tier, Tenant API Access) covering concepts introduced by the D-87/88/89 rebuilds after that glossary was originally written. Full detail in `docs/DECISIONS.md` D-90.

## The rest of the documents

- **`eBid_Hub_Vision_Document.docx`** — the platform's founding vision and positioning
- **`eBid_Hub_Tech_Stack_Specification.docx`** — the original technical direction (see `SETUP.md` for what was actually built)
- **`eBid_Hub_Fee_Charges_Schedule.docx`** — the published fee structure, including the worked examples D-57's invoicing was verified against
- **`eBid_Hub_Terms_of_Usage_DRAFT.docx`**, **`eBid_Hub_Privacy_Policy_DRAFT.docx`**, **`eBid_Hub_Cookie_Policy_DRAFT.docx`** — legal drafts, still marked DRAFT pending real entity/jurisdiction details
- **`eBid_Hub_Dispute_Resolution_Process_DRAFT.docx`**, **`eBid_Hub_Grievance_Redressal_Policy_DRAFT.docx`**, **`eBid_Hub_Refund_and_Cancellation_Policy_DRAFT.docx`** — supporting policy drafts
- **`eBid_Hub_Dos_and_Donts.docx`** — platform conduct guidance
- **`eBid_Hub_FAQ.jsx`**, **`eBid_Hub_Star_Ratings.jsx`**, **`eBid_Hub_Security_and_Trust.jsx`**, **`eBid_Hub_Trust_and_Support.jsx`** — buyer/seller-facing content components, written as JSX for direct use in the frontend
- **`eBid_Hub_Terminology.jsx`** — the same kind of buyer/seller-facing JSX glossary component as the four above, still the source for the live front-end glossary page. Its 30 term definitions are also now transcribed into `ADWITIX_Master.docx` Section 1, Part B (plus 4 new entries the JSX doesn't have yet — Success Fee, Fee Payer Election, Subscription Tier, Tenant API Access), so the governing document itself carries the same vocabulary a reader needs before hitting the Business Rules. This file is unchanged; the docx transcription is a one-way copy, not a link.
- **`eBid_Hub_Pricing_TradeSphereX.html`** — the tenant subscription-pricing page, provided as a complete, ready-made standalone document (own fonts/styles/Success Fee calculator), matching `ADWITIX_Master.docx` Section 6 (Business Model) and BR-68's visual identity system exactly. Uses "TradeSphereX" branding per BR-67's Branded Terminology Layer — Custodian = Super Admin, TSX Master = Tenant Admin, Market Maker = Seller, Trader = Buyer. Served verbatim (not re-themed into `layouts/main`) at `/pricing`; the canonical served copy lives at `public/pricing.html`.

## Why these are in the repo now

Previously these lived only in the Claude project's knowledge base —
real, but not durable outside that specific environment. Copying them
directly into version control means the entire basis for this project
travels with the codebase itself: a fresh clone, a new collaborator, a
different AI session, or a different account all have everything needed
without depending on any external knowledge store.
