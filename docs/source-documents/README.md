# Source Documents

These are the original governing documents this entire platform is built
against — not generated from the code, the other way around. When any
business rule, process workflow, or design decision in `docs/DECISIONS.md`
cites a BR or PR number, it's referencing these files directly.

**`eBid_Hub_Unified_BR_PR.docx`** is the single most important file here.
It's the canonical Business Rules and Process Rules specification — the
actual basis for this whole project. Any time the code and this
document disagree, this document wins; `docs/DECISIONS.md` explains the
reasoning whenever the build diverges or a rule needed clarification
from the project owner.

**Updated 2026-07-29** (was BR-01–BR-61 / PR-01–PR-36; now BR-01–BR-66 /
PR-01–PR-37) — the project owner's replacement supersedes the prior
version per its own "Status" line, same as the prior version did before
it. It's a real binary `.docx` (the earlier version in this repo's
history was plain text saved with a `.docx` extension — `git log -p`
on this file still has that original content if needed). What's new:
- **BR-62–BR-66 / PR-37: Tenant API Access** — a whole new module letting
  a Tenant integrate its own systems via API as an alternative to the
  portal UI, governed by the exact same approval/lifecycle rules as a
  portal submission (no API-side approval bypass), OAuth2 client-credentials
  scoped per-tenant via the existing Auth0 relationship, visibility capped
  at whatever the Visibility Matrix (BR-16) already grants that role.
- **Section 3 (Tech Stack) is substantially more decided**: the payment
  gateway is no longer TBD — **SabPaisa** is named as the selected PG
  (RBI-authorised, VAN/UPI collection, Payouts API). KYC verification is
  explicitly specified as **manual, no automated vendor** — Tenant Admin/
  Super Admin review only, matching this repo's own D-76 KYC decision.
  New requirements not yet built: server-time integrity (NTP sync + drift
  alerting), audit-log DB-permission hardening (no UPDATE/DELETE grant at
  the database layer, not just the application layer), and an independent
  third-party security audit before go-live. SMS OTP, bank verification/
  penny-drop, and email provider all remain TBD.
- Every already-built numeric rule spot-checked against the new text
  (BR-27 EMD 10%, BR-43's 150% ceiling, BR-49's ₹10L threshold, BR-38's
  shadow-ban thresholds) is unchanged. BR-27 gained one new clause: a
  Payment Gateway collection charge must be charged to the buyer on top
  of the stated EMD, never deducted from it — relevant once BR-52's real
  gateway integration happens, not yet actionable.
- Section 4 (Phased Roadmap) is now explicit that this document is Phase
  1 only, with Phase 2 (a full Reverse-Auction/Procurement format, plus
  a Market Intelligence pricing-guide feature) intentionally out of
  scope for now, not overlooked.

## The rest of the documents

- **`eBid_Hub_Vision_Document.docx`** — the platform's founding vision and positioning
- **`eBid_Hub_Tech_Stack_Specification.docx`** — the original technical direction (see `SETUP.md` for what was actually built)
- **`eBid_Hub_Fee_Charges_Schedule.docx`** — the published fee structure, including the worked examples D-57's invoicing was verified against
- **`eBid_Hub_Terms_of_Usage_DRAFT.docx`**, **`eBid_Hub_Privacy_Policy_DRAFT.docx`**, **`eBid_Hub_Cookie_Policy_DRAFT.docx`** — legal drafts, still marked DRAFT pending real entity/jurisdiction details
- **`eBid_Hub_Dispute_Resolution_Process_DRAFT.docx`**, **`eBid_Hub_Grievance_Redressal_Policy_DRAFT.docx`**, **`eBid_Hub_Refund_and_Cancellation_Policy_DRAFT.docx`** — supporting policy drafts
- **`eBid_Hub_Dos_and_Donts.docx`** — platform conduct guidance
- **`eBid_Hub_FAQ.jsx`**, **`eBid_Hub_Terminology.jsx`**, **`eBid_Hub_Star_Ratings.jsx`**, **`eBid_Hub_Security_and_Trust.jsx`**, **`eBid_Hub_Trust_and_Support.jsx`** — buyer/seller-facing content components, written as JSX for direct use in the frontend

## Why these are in the repo now

Previously these lived only in the Claude project's knowledge base —
real, but not durable outside that specific environment. Copying them
directly into version control means the entire basis for this project
travels with the codebase itself: a fresh clone, a new collaborator, a
different AI session, or a different account all have everything needed
without depending on any external knowledge store.
