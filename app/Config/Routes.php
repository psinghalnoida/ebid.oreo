<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/trust-support', 'TrustSupport::index');

// BR-02 auth flow
$routes->get('/register', 'AuthController::registerForm');
$routes->post('/register', 'AuthController::registerSubmit');
$routes->post('/register/verify-otp', 'AuthController::registerVerifyOtpSubmit');
$routes->post('/register/set-mpin', 'AuthController::setMpinSubmit');

$routes->get('/login', 'AuthController::loginForm');
$routes->post('/login', 'AuthController::loginSubmit');
$routes->post('/login/reset-verify-otp', 'AuthController::resetVerifyOtpSubmit');

// BR-11/BR-13 listing lifecycle + BR-12 Easy Auction (dev-testable, real routes)
$routes->get('/listings/create', 'ListingController::createForm');
$routes->post('/listings/pre-audit', 'ListingController::preAudit');
$routes->post('/listings', 'ListingController::createSubmit');
$routes->get('/listings/(:segment)', 'ListingController::show/$1');
$routes->post('/listings/(:segment)/submit-for-approval', 'ListingController::submitForApproval/$1');
$routes->post('/listings/(:segment)/approve', 'ListingController::approve/$1', ['filter' => 'tenantAdmin:listing']);
$routes->post('/listings/(:segment)/reject', 'ListingController::reject/$1', ['filter' => 'tenantAdmin:listing']);
$routes->post('/listings/(:segment)/sale-events', 'SaleEventController::createSubmit/$1');

$routes->post('/sale-events/(:segment)/approve', 'SaleEventController::approve/$1', ['filter' => 'tenantAdmin:saleEvent']);
$routes->post('/sale-events/(:segment)/dev-force-freeze', 'SaleEventController::devForceFreeze/$1', ['filter' => 'tenantAdmin:saleEvent']);
$routes->post('/sale-events/(:segment)/dev-fund-emd', 'BidController::devFundEmd/$1');
$routes->post('/sale-events/(:segment)/bid', 'BidController::placeBid/$1');
// D-113: BR-28 cascade top-up payment — closes the gap CascadeService::
// processTopupPaid() had no real route to reach.
$routes->post('/sale-events/(:segment)/dev-pay-topup', 'BidController::devPayTopup/$1');

// D-117: BR-52/PR-30 Chargeback Handling & Representment.
$routes->post('/sale-events/(:segment)/dev-file-chargeback', 'ChargebackController::devFile/$1');
$routes->get('/admin/chargebacks', 'ChargebackController::index', ['filter' => 'superAdmin']);
$routes->post('/admin/chargebacks/(:segment)/decide', 'ChargebackController::decide/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/chargebacks/(:segment)/review-integrity', 'ChargebackController::reviewIntegrity/$1', ['filter' => 'superAdmin']);

// Buy-Now offers (BR-27/BR-42/BR-29)
$routes->post('/sale-events/(:segment)/dev-fund-emd-offer', 'OfferController::devFundEmd/$1');
$routes->post('/sale-events/(:segment)/offers', 'OfferController::submit/$1');
$routes->post('/sale-events/(:segment)/offers/(:segment)/accept', 'OfferController::accept/$1/$2');
$routes->post('/offers/(:segment)/withdraw', 'OfferController::withdraw/$1');

// Express Auction (BR-12/PR-11)
$routes->post('/sale-events/(:segment)/pledge', 'ExpressController::pledge/$1');
$routes->post('/sale-events/(:segment)/express-bid', 'ExpressController::placeBid/$1');
$routes->post('/sale-events/(:segment)/dev-force-close-bidding', 'ExpressController::devForceCloseBidding/$1', ['filter' => 'tenantAdmin:saleEvent']);

// Listing media (BR-11, BR-45)
$routes->post('/listings/(:segment)/media', 'MediaController::upload/$1');
$routes->post('/listings/(:segment)/media/(:segment)/set-primary', 'MediaController::setPrimary/$1/$2');

// Settlement (BR-33, BR-39)
$routes->get('/settlements/(:segment)', 'SettlementController::show/$1');
$routes->post('/settlements/(:segment)/confirm-seller-noc', 'SettlementController::confirmSellerNoc/$1');
$routes->post('/settlements/(:segment)/confirm-buyer-noc', 'SettlementController::confirmBuyerNoc/$1');
$routes->post('/settlements/(:segment)/rate-as-buyer', 'SettlementController::rateAsBuyer/$1');
$routes->post('/settlements/(:segment)/rate-as-seller', 'SettlementController::rateAsSeller/$1');
$routes->post('/settlements/dev-flag-stalled', 'SettlementController::devFlagStalled');
$routes->post('/settlements/(:segment)/force-resolve', 'SettlementController::forceResolve/$1', ['filter' => 'tenantAdmin:settlement']);

// Dispute Resolution Framework (BR-40)
$routes->get('/sale-events/(:segment)/dispute', 'DisputeController::fileForm/$1');
$routes->post('/sale-events/(:segment)/dispute', 'DisputeController::fileSubmit/$1');
$routes->get('/disputes/(:segment)', 'DisputeController::show/$1');
$routes->post('/disputes/(:segment)/evidence', 'DisputeController::submitEvidence/$1');
$routes->post('/disputes/(:segment)/rule', 'DisputeController::rule/$1');
$routes->post('/disputes/(:segment)/appeal', 'DisputeController::appeal/$1');
$routes->post('/disputes/(:segment)/rule-appeal', 'DisputeController::ruleOnAppeal/$1', ['filter' => 'superAdmin']);

// Super Admin real auth (BR-04)
$routes->get('/admin/setup-totp', 'SuperAdminAuthController::setupTotpForm');
$routes->post('/admin/setup-totp', 'SuperAdminAuthController::setupTotpSubmit');
$routes->get('/admin/login', 'SuperAdminAuthController::loginForm');
$routes->post('/admin/login', 'SuperAdminAuthController::loginSubmit');
$routes->get('/admin/logout', 'SuperAdminAuthController::logout');

$routes->get('/admin', 'AdminController::dashboard', ['filter' => 'superAdmin']);
$routes->get('/admin/alerts', 'AdminController::alerts', ['filter' => 'superAdmin']);
$routes->post('/admin/alerts/server-time-drift/(:segment)/acknowledge', 'AdminController::acknowledgeServerTimeDrift/$1', ['filter' => 'superAdmin']);
$routes->get('/admin/tenants', 'TenantController::list', ['filter' => 'superAdmin']);
$routes->get('/admin/tenants/create', 'TenantController::createForm', ['filter' => 'superAdmin']);
$routes->post('/admin/tenants', 'TenantController::createSubmit', ['filter' => 'superAdmin']);
$routes->get('/admin/users', 'UserController::index', ['filter' => 'superAdmin']);
$routes->get('/admin/users/(:segment)', 'UserController::detail/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/users/(:segment)/promote-tenant-admin', 'UserController::promoteTenantAdmin/$1', ['filter' => 'superAdmin']);

// Seller Application (BR-09)
$routes->get('/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applyForm/$1');
$routes->post('/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applySubmit/$1');
$routes->get('/tenants/(:segment)/pending-sellers', 'SellerApplicationController::pendingList/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/seller-applications/(:segment)/approve', 'SellerApplicationController::approve/$1', ['filter' => 'tenantAdmin:sellerApplication']);
$routes->post('/seller-applications/(:segment)/reject', 'SellerApplicationController::reject/$1', ['filter' => 'tenantAdmin:sellerApplication']);
$routes->get('/tenants/(:segment)/dashboard', 'TenantAdminController::dashboard/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->get('/tenants/(:segment)/verification', 'TenantAdminController::verification/$1', ['filter' => 'tenantAdmin:tenant']);

// Tender Auction — real HTTP routes
$routes->post('/sale-events/(:segment)/tender/interest', 'TenderController::registerInterest/$1');
$routes->get('/sale-events/(:segment)/tender/eligibility', 'TenderController::manageEligibility/$1');
$routes->post('/sale-events/(:segment)/tender/eligibility/grant', 'TenderController::grantEligibility/$1');
$routes->post('/sale-events/(:segment)/tender/documents', 'TenderController::publishDocument/$1');
$routes->post('/sale-events/(:segment)/tender/emd', 'TenderController::logEmd/$1');
$routes->post('/sale-events/(:segment)/tender/bid', 'TenderController::placeBid/$1');
$routes->post('/sale-events/(:segment)/tender/stakeholder-link', 'TenderController::generateStakeholderLink/$1');
$routes->get('/tender-view/(:segment)', 'TenderController::stakeholderView/$1');
$routes->post('/sale-events/(:segment)/tender/close-bidding', 'TenderController::closeBidding/$1');
$routes->post('/tender-reviews/(:segment)/action', 'TenderController::reviewAction/$1');
$routes->get('/sale-events/(:segment)/tender/report', 'TenderController::auctionReport/$1');

// Navigation gaps closed — logout, My Listings/Activity/Profile, browse
$routes->get('/logout', 'AuthController::logout');
$routes->get('/browse', 'Home::browse');
$routes->get('/listings', 'Home::browse');
$routes->get('/my-listings', 'MyActivityController::myListings');
$routes->get('/my-activity', 'MyActivityController::myActivity');
$routes->get('/profile', 'MyActivityController::profile');
$routes->post('/listings/(:segment)/edit', 'ListingController::editSubmit/$1');
$routes->post('/sale-events/(:segment)/emergency-stop', 'SaleEventController::emergencyStop/$1', ['filter' => 'tenantAdmin:saleEvent']);
$routes->get('/admin/audit-log', 'AuditLogController::index', ['filter' => 'superAdmin']);
$routes->get('/admin/audit-log/verify', 'AuditLogController::verifyIntegrity', ['filter' => 'superAdmin']);
$routes->get('/admin/audit-log/export', 'AuditLogController::exportForm', ['filter' => 'superAdmin']);
$routes->get('/admin/audit-log/export/download', 'AuditLogController::export', ['filter' => 'superAdmin']);
$routes->get('/tenants/(:segment)/media-waiver', 'TenantMediaWaiverController::requestForm/$1');
$routes->post('/tenants/(:segment)/media-waiver', 'TenantMediaWaiverController::requestSubmit/$1');
$routes->get('/admin/media-waivers', 'TenantMediaWaiverController::pendingList', ['filter' => 'superAdmin']);
$routes->post('/admin/media-waivers/(:segment)/decide', 'TenantMediaWaiverController::decide/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/media-waivers/(:segment)/revoke', 'TenantMediaWaiverController::revoke/$1', ['filter' => 'superAdmin']);
$routes->post('/listings/(:segment)/flag-cbs-violation', 'ListingController::flagCbsViolation/$1');
$routes->get('/admin/standing-review/(:segment)', 'StandingReviewController::show/$1');
$routes->post('/admin/standing-review/(:segment)/rule', 'StandingReviewController::rule/$1');
$routes->get('/admin/delist-seller', 'SellerDelistingController::form', ['filter' => 'superAdmin']);
$routes->post('/admin/delist-seller', 'SellerDelistingController::submit', ['filter' => 'superAdmin']);
$routes->get('/preferences', 'PreferencesController::form');
$routes->post('/preferences', 'PreferencesController::submit');
$routes->get('/ticker-feed', 'LiveTickerController::feed');
$routes->get('/sale-events/(:segment)/emd-consent/(:segment)', 'EmdConsentController::form/$1/$2');
$routes->post('/sale-events/(:segment)/emd-consent/(:segment)/confirm', 'EmdConsentController::confirm/$1/$2');
$routes->get('/sale-events/(:segment)/defect-disclosure', 'SaleEventController::defectDisclosureForm/$1');
$routes->post('/sale-events/(:segment)/defect-disclosure', 'SaleEventController::defectDisclosureSubmit/$1');
$routes->get('/admin/tenants/(:segment)', 'TenantController::view/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/tenants/(:segment)/edit', 'TenantController::editSubmit/$1', ['filter' => 'superAdmin']);
$routes->get('/tenants', 'TenantController::directory');

// AML Monitoring (BR-54/PR-31) — SaaS Admin only
$routes->get('/admin/aml', 'AmlController::index', ['filter' => 'superAdmin']);
$routes->post('/admin/aml/(:segment)/review', 'AmlController::review/$1', ['filter' => 'superAdmin']);

// Payout Account Change Control (BR-50/PR-28)
$routes->get('/payout-bank', 'PayoutBankController::requestForm');
$routes->post('/payout-bank/request', 'PayoutBankController::requestSubmit');
$routes->post('/payout-bank/confirm', 'PayoutBankController::confirmSubmit');
$routes->get('/admin/payout-reviews', 'PayoutReviewController::index');
$routes->post('/admin/payout-reviews/(:segment)/decide', 'PayoutReviewController::decide/$1');

// Pending rating downgrade reviews (BR-35/BR-36)
$routes->get('/admin/rating-reviews', 'RatingReviewController::index');
$routes->post('/admin/rating-reviews/(:segment)/approve', 'RatingReviewController::approve/$1');

// Tenant monthly billing for Seller-Pays Success Fees (BR-32/33, D-88)
$routes->get('/tenants/(:segment)/billing', 'TenantBillingController::forTenant/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->get('/admin/tenant-invoices', 'TenantBillingController::index', ['filter' => 'superAdmin']);
$routes->post('/admin/tenant-invoices/(:segment)/mark-paid', 'TenantBillingController::markPaid/$1', ['filter' => 'superAdmin']);

// Seller Management for Tenant Admin (BR-61, built on the real Standing Review system)
$routes->get('/tenants/(:segment)/sellers', 'SellerManagementController::list/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->get('/tenants/(:segment)/sellers/(:segment)', 'SellerManagementController::detail/$1/$2', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/tenants/(:segment)/sellers/(:segment)/initiate-review', 'SellerManagementController::initiateReview/$1/$2', ['filter' => 'tenantAdmin:tenant']);

// Consent Audit viewer (BR-51)
$routes->get('/admin/consent-audit', 'ConsentAuditController::index', ['filter' => 'superAdmin']);
$routes->get('/admin/consent-audit/export', 'ConsentAuditController::exportForm', ['filter' => 'superAdmin']);
$routes->get('/admin/consent-audit/export/download', 'ConsentAuditController::export', ['filter' => 'superAdmin']);

// Phase 3A: account management
$routes->get('/account', 'MyActivityController::profile');
$routes->get('/account/edit', 'AccountController::editForm');
$routes->post('/account/edit', 'AccountController::editSubmit');
$routes->get('/account/change-mpin', 'AccountController::changeMpinForm');
$routes->post('/account/change-mpin/request-otp', 'AccountController::changeMpinRequestOtp');
$routes->post('/account/change-mpin/confirm', 'AccountController::changeMpinConfirm');
$routes->get('/account/delete', 'AccountController::deleteForm');
$routes->post('/account/delete/request', 'AccountController::deleteRequestSubmit');
$routes->post('/account/delete/cancel', 'AccountController::deleteCancelSubmit');
$routes->get('/account/earnings', 'AccountController::earnings');

// Phase 3D remainder: BR-56 invoice history + PDF (D-72)
$routes->get('/account/invoices', 'InvoiceController::index');
$routes->get('/account/invoices/(:segment)/pdf', 'InvoiceController::pdf/$1');

// Section 7.10 (ADWITIX_Master.docx): Trading Session Chronicle.
// verify()/verifyPdf() are deliberately token-only, no session filter --
// that's the whole point of a QR code reachable by anyone with the exact
// unguessable token, per Section 7.8's stated exception.
$routes->get('/chronicles/(:segment)', 'ChronicleController::view/$1');
$routes->get('/chronicles/(:segment)/download', 'ChronicleController::download/$1');
$routes->get('/chronicle/verify/(:segment)', 'ChronicleController::verify/$1');
$routes->get('/chronicle/verify/(:segment)/pdf', 'ChronicleController::verifyPdf/$1');

// Phase 3A: real, dedicated, paginated/filterable transaction pages
$routes->get('/my-bids', 'MyActivityController::myBids');
$routes->get('/my-offers', 'MyActivityController::myOffers');
$routes->get('/my-purchases', 'MyActivityController::myPurchases');
$routes->get('/my-purchases/export', 'MyActivityController::myPurchasesExport');
$routes->get('/my-sales', 'MyActivityController::mySales');
$routes->get('/my-sales/export', 'MyActivityController::mySalesExport');

// Phase 3C+: favorites, saved searches, search history, recommendations
$routes->post('/listings/(:segment)/favorite', 'ListingController::favorite/$1');
$routes->post('/listings/(:segment)/unfavorite', 'ListingController::unfavorite/$1');
$routes->get('/my-favorites', 'DiscoveryController::myFavorites');
$routes->get('/my-searches', 'DiscoveryController::mySearches');
$routes->post('/my-searches', 'DiscoveryController::saveSearchSubmit');
$routes->post('/my-searches/(:segment)/delete', 'DiscoveryController::deleteSearch/$1');
$routes->get('/search-history', 'DiscoveryController::searchHistory');
$routes->get('/recommendations', 'DiscoveryController::recommendations');

// D-105: Lot Reach & Interest -- per-listing reach analytics + real
// in-app bulk messaging to matched buyers.
$routes->get('/my-listings/reach', 'LotReachController::index');
$routes->post('/listings/(:segment)/reach/message', 'LotReachController::sendMessage/$1');
$routes->get('/my-messages', 'MyActivityController::messages');
$routes->post('/my-messages/(:segment)/read', 'MyActivityController::markMessageRead/$1');

// D-106: the 6 screens flagged in the design handoff as having neither
// a design nor a consolidated backend (docs/design/CLAUDE_DESIGN_HANDOFF.md §2).
$routes->get('/my-star-ratings', 'MyActivityController::starRatings');
$routes->get('/my-rating-history', 'MyActivityController::ratingHistory');
$routes->get('/my-buyer-dashboard', 'MyActivityController::buyerDashboard');
$routes->get('/my-seller-dashboard', 'MyActivityController::sellerDashboard');
$routes->get('/admin/lots', 'AdminController::lotDirectory', ['filter' => 'superAdmin']);
$routes->get('/admin/trading-sessions', 'AdminController::tradingSessionDirectory', ['filter' => 'superAdmin']);

// Legal documents (BR-01/D-15: reviewed structural content, pending fields flagged)
$routes->get('/terms', 'LegalController::termsOfUsage');
$routes->get('/privacy', 'LegalController::privacyPolicy');
$routes->get('/grievance-redressal', 'LegalController::grievanceRedressal');
$routes->get('/refund-cancellation', 'LegalController::refundCancellation');
$routes->get('/dispute-resolution', 'LegalController::disputeResolution');
$routes->get('/cookie-policy', 'LegalController::cookiePolicy');

// Info / support pages
$routes->get('/faq', 'InfoController::faq');
$routes->get('/dos-and-donts', 'InfoController::dosAndDonts');
$routes->get('/security-trust', 'InfoController::securityTrust');
$routes->get('/fees', 'InfoController::feeSchedule');
$routes->get('/pricing', 'PricingController::index');
$routes->get('/terminology', 'InfoController::terminology');

// Sovereign Rule Revision (PR-04/BR-01/BR-04) — Rules & Specifications
// module. /new must be registered before the generic (:segment) edit
// route below it, same ordering pattern as /admin/tenants/create.
$routes->get('/admin/rules', 'SovereignRuleController::index', ['filter' => 'superAdmin']);
$routes->get('/admin/rules/new', 'SovereignRuleController::createForm', ['filter' => 'superAdmin']);
$routes->post('/admin/rules/new', 'SovereignRuleController::createSubmit', ['filter' => 'superAdmin']);
$routes->get('/admin/rules/(:segment)', 'SovereignRuleController::editForm/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/rules/(:segment)/edit', 'SovereignRuleController::editSubmit/$1', ['filter' => 'superAdmin']);

// KYC Verification (BR-17/BR-18/BR-55/PR-15) — patron-facing onboarding
$routes->get('/kyc', 'KycController::form');
$routes->post('/kyc/questionnaire', 'KycController::saveQuestionnaire');
$routes->post('/kyc/documents', 'KycController::uploadDocument');
$routes->post('/kyc/addresses', 'KycController::saveAddress');
$routes->post('/kyc/banking', 'KycController::saveBanking');
$routes->post('/kyc/submit', 'KycController::submit');

// KYC review — Super Admin (SaaS Admin) side, see KycReviewController's
// class doc block for why this is Super Admin rather than Tenant Admin.
$routes->get('/admin/kyc', 'KycReviewController::index', ['filter' => 'superAdmin']);
$routes->get('/admin/kyc/(:segment)', 'KycReviewController::detail/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/kyc/(:segment)/verify-flag', 'KycReviewController::verifyFlag/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/kyc/(:segment)/decide', 'KycReviewController::decide/$1', ['filter' => 'superAdmin']);
$routes->post('/admin/kyc/(:segment)/clear-edd', 'KycReviewController::clearEdd/$1', ['filter' => 'superAdmin']);
$routes->get('/admin/kyc-documents/(:segment)/download', 'KycReviewController::downloadDocument/$1', ['filter' => 'superAdmin']);

// Tenant API Access (BR-62-66/PR-37) — Tenant Admin portal-side credential
// and webhook management.
$routes->get('/tenants/(:segment)/api-access', 'TenantApiSettingsController::index/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/tenants/(:segment)/api-access/credentials', 'TenantApiSettingsController::issueCredential/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/tenants/(:segment)/api-access/credentials/(:segment)/revoke', 'TenantApiSettingsController::revokeCredential/$1/$2', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/tenants/(:segment)/api-access/webhook-url', 'TenantApiSettingsController::updateWebhookUrl/$1', ['filter' => 'tenantAdmin:tenant']);

// Tenant API Access (BR-62-66/PR-37) — the actual API surface, OAuth2
// client-credentials-authenticated (apiAuth filter), not session-based.
// D-107: BR-65 amended (reversed) — the API is now versioned with a
// visible /v1/ segment. A breaking change ships as a new /v2/ etc.
// alongside /v1/, never as a silent mutation of the existing shape;
// additive changes still ship within the current version, no bump.
$routes->post('/api/v1/oauth/token', 'TenantApiController::issueToken');
$routes->post('/api/v1/listings/pre-audit', 'TenantApiController::preAuditListing', ['filter' => 'apiAuth']);
$routes->post('/api/v1/listings', 'TenantApiController::pushListing', ['filter' => 'apiAuth']);
$routes->get('/api/v1/listings/(:segment)', 'TenantApiController::getListing/$1', ['filter' => 'apiAuth']);
$routes->post('/api/v1/listings/(:segment)/sale-events', 'TenantApiController::pushSaleEvent/$1', ['filter' => 'apiAuth']);
$routes->get('/api/v1/sale-events/(:segment)', 'TenantApiController::getSaleEvent/$1', ['filter' => 'apiAuth']);
