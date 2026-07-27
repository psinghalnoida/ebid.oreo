# Source Documents

These are the original governing documents this entire platform is built
against — not generated from the code, the other way around. When any
business rule, process workflow, or design decision in `docs/DECISIONS.md`
cites a BR or PR number, it's referencing these files directly.

**`eBid_Hub_Unified_BR_PR.docx`** is the single most important file here.
It's the canonical Business Rules (BR-01 through BR-61) and Process
Rules (PR-01 through PR-36) specification — the actual basis for this
whole project. Any time the code and this document disagree, this
document wins; `docs/DECISIONS.md` explains the reasoning whenever the
build diverges or a rule needed clarification from the project owner.

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
