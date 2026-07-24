# eBid Hub — Site Map & Completeness Check

Generated directly from the live `app/Config/Routes.php` on `dev` (commit `5b94707`), not from memory. Every route below genuinely exists and has been tested. The **Gaps Found** section at the end lists things a complete marketplace needs that are NOT currently reachable — some are deliberately deferred, some are things I built and tested but simply never wired a page to.

---

## 1. Public / No Login Required

| Page | Route | Notes |
|---|---|---|
| Landing page (marketplace) | `GET /` | Real live listings grid, category counts (D-33) |
| Trust & Support hub | `GET /trust-support` | |
| FAQ | `GET /faq` | |
| Dos & Don'ts | `GET /dos-and-donts` | |
| Security & Trust | `GET /security-trust` | |
| Fee & Charges Schedule | `GET /fees` | |
| Terminology glossary | `GET /terminology` | |
| Terms of Usage | `GET /terms` | Blanks marked "Pending" per your decision (D-15) |
| Privacy Policy | `GET /privacy` | Same |
| Grievance Redressal | `GET /grievance-redressal` | Same |
| Refund & Cancellation | `GET /refund-cancellation` | Same |
| Dispute Resolution (legal doc) | `GET /dispute-resolution` | Same |
| Cookie Policy | `GET /cookie-policy` | Same |
| Register | `GET/POST /register` + OTP/mPIN steps | |
| Log in | `GET/POST /login` + reset flow | |
| **Tender stakeholder view** | `GET /tender-view/{token}` | Genuinely no login — verified (D-38) |

## 2. Buyer / Seller (logged in)

### Listings
| Page | Route |
|---|---|
| Create a listing | `GET/POST /listings/create`, `POST /listings` |
| View a listing | `GET /listings/{id}` |
| Upload photos | `POST /listings/{id}/media` |
| Set primary photo | `POST /listings/{id}/media/{mediaId}/set-primary` |
| Submit for approval | `POST /listings/{id}/submit-for-approval` |
| Apply to sell on a tenant | `GET/POST /tenants/{id}/apply-to-sell` |

### Sale Events — Easy Auction
| Page | Route |
|---|---|
| Attach Easy Auction | `POST /listings/{id}/sale-events` |
| Place a bid | `POST /sale-events/{id}/bid` |
| Fund EMD (dev stub) | `POST /sale-events/{id}/dev-fund-emd` |

### Sale Events — Buy-Now
| Page | Route |
|---|---|
| Submit an offer | `POST /sale-events/{id}/offers` |
| Accept an offer (seller) | `POST /sale-events/{id}/offers/{offerId}/accept` |
| Withdraw an offer | `POST /offers/{id}/withdraw` |
| Fund EMD (dev stub) | `POST /sale-events/{id}/dev-fund-emd-offer` |

### Sale Events — Express Auction
| Page | Route |
|---|---|
| Pledge reserve (fund EMD) | `POST /sale-events/{id}/pledge` |
| Place a bid | `POST /sale-events/{id}/express-bid` |

### Sale Events — Tender Auction
| Page | Route |
|---|---|
| Register interest | `POST /sale-events/{id}/tender/interest` |
| Manage eligibility (seller) | `GET /sale-events/{id}/tender/eligibility` |
| Grant eligibility (seller) | `POST /sale-events/{id}/tender/eligibility/grant` |
| Publish Terms of Sale/documents (seller) | `POST /sale-events/{id}/tender/documents` |
| Log manual EMD (seller/admin) | `POST /sale-events/{id}/tender/emd` |
| Place a bid | `POST /sale-events/{id}/tender/bid` |
| Generate stakeholder link (seller) | `POST /sale-events/{id}/tender/stakeholder-link` |
| Close bidding (seller) | `POST /sale-events/{id}/tender/close-bidding` |
| Review action — extend/reject/confirm (Tenant Admin) | `POST /tender-reviews/{id}/action` |
| Auction report (seller) | `GET /sale-events/{id}/tender/report` |

### Settlement (all formats funnel here)
| Page | Route |
|---|---|
| View settlement | `GET /settlements/{id}` |
| Confirm seller NOC | `POST /settlements/{id}/confirm-seller-noc` |
| Confirm buyer NOC | `POST /settlements/{id}/confirm-buyer-noc` |
| Rate as buyer | `POST /settlements/{id}/rate-as-buyer` |
| Rate as seller | `POST /settlements/{id}/rate-as-seller` |

### Disputes
| Page | Route |
|---|---|
| File a dispute | `GET/POST /sale-events/{id}/dispute` |
| View a dispute | `GET /disputes/{id}` |
| Submit evidence | `POST /disputes/{id}/evidence` |
| Rule on a dispute (Tenant Admin) | `POST /disputes/{id}/rule` |
| Appeal | `POST /disputes/{id}/appeal` |

## 3. Tenant Admin

| Page | Route |
|---|---|
| Tenant dashboard | `GET /tenants/{id}/dashboard` |
| Approve/reject a listing | `POST /listings/{id}/approve`, `/reject` |
| Approve a sale event | `POST /sale-events/{id}/approve` |
| Pending seller applications | `GET /tenants/{id}/pending-sellers` |
| Approve/reject a seller | `POST /seller-applications/{id}/approve`, `/reject` |
| Force-resolve a stalled settlement | `POST /settlements/{id}/force-resolve` |
| Dev-only force-freeze/force-close (testing aids) | Various `/dev-*` routes |

## 4. Super Admin

| Page | Route |
|---|---|
| Set up TOTP 2FA | `GET/POST /admin/setup-totp` |
| Log in (separate path) | `GET/POST /admin/login` |
| Log out | `GET /admin/logout` |
| Dashboard | `GET /admin` |
| Create/whitelist a tenant | `GET/POST /admin/tenants/create`, `POST /admin/tenants` |
| Rule on a dispute appeal | `POST /disputes/{id}/rule-appeal` |

---

## Gaps Found — Not Currently Reachable

### Real, tested logic with literally no page to reach it
- **Editing an approved listing** (`ListingLifecycleService::requestMaterialEdit`) — fully built and tested (archive-and-recreate, refunds active bids), but **zero HTTP route exists**. A seller currently cannot edit a listing through the site at all.
- **Emergency Stop** (`ListingLifecycleService::emergencyStop`) — same situation. Tested, works, but no button or route anywhere.

### Basic account/navigation gaps
- **No generic `/logout` for regular users** — only Super Admin has a logout route. A buyer/seller has no way to log out.
- **No "My Listings" page** — a seller has no central place to see everything they've listed; they'd need to remember individual URLs.
- **No "My Bids/Offers/Purchases" page** — same problem for buyers.
- **No user profile/account page** — no way to view your own rating, edit your details, or change your mPIN.
- **No "browse all listings" / marketplace search page** — the landing page shows up to 12 recent listings; there's no dedicated page with filters (category, format, price) to see everything live.
- **No page to browse/discover tenants** — `/tenants/{id}/apply-to-sell` requires already knowing the tenant's ID; there's no directory of shops to choose from.

### Admin-side gaps
- **Super Admin cannot view or edit an existing tenant** — only creation exists, no tenant list/detail/edit page.
- **No TOTP recovery path** — if a Super Admin loses their authenticator device, there's currently no recovery mechanism built.

### Explicitly deferred, not oversights (confirmed earlier in this project)
- KYC data collection flow (Tier 4)
- Full media upload spec — video/documents, transcoding (deferred per your decision)
- Real payment gateway / SMS — deliberately stubbed, connects post-deployment
- Legal document blank fields — waiting on real values from you

---

## Honest summary

The **transactional core** — listing, all four sale formats, settlement, disputes, admin approval chains — is genuinely complete and well-tested. What's missing is mostly **everyday navigation and account management** that a real user would expect on day one: logging out, seeing your own listings, browsing the marketplace properly, and editing a listing after you've created it. None of this is hard to build; it just hasn't been prioritized yet since the focus has been on the harder transactional logic.
