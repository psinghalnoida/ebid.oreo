<?php

use CodeIgniter\Router\RouteCollection;


$routes->options(
    'api/(:any)',
    static function () {
        return service('response')->setStatusCode(204);
    }
);

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/trust-support', 'TrustSupport::index');

// BR-02 auth flow — see the REST/JWT routes near the bottom of this
// file (UserAuthApiController) for the register/login/mpin-reset API
// that replaced this section's former HTML routes (D-137, Phase 6).

// BR-11/BR-13 listing lifecycle + BR-12 Easy Auction — Phase 2 of the
// CI4->REST/JWT migration (D-132). Party-facing actions run behind
// jwtAuth; Tenant-Admin actions behind jwtTenantAdmin (the JWT
// counterpart of the old session-based tenantAdmin filter).
// GET /api/v1/app/tenants (TenantController::directory, below) is the
// tenant picker for the React "list an asset" form. Dev convenience:
// for now, lists any tenant to attach to — tenant selection/scoping by
// seller role (BR-09) is not yet built.
$routes->post('/api/v1/app/listings/pre-audit', 'ListingController::preAudit', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings', 'ListingController::createSubmit', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/listings/(:segment)', 'ListingController::show/$1');
$routes->post('/api/v1/app/listings/(:segment)/edit', 'ListingController::editSubmit/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/favorite', 'ListingController::favorite/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/unfavorite', 'ListingController::unfavorite/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/flag-cbs-violation', 'ListingController::flagCbsViolation/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/submit-for-approval', 'ListingController::submitForApproval/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/approve', 'ListingController::approve/$1', ['filter' => 'jwtTenantAdmin:listing']);
$routes->post('/api/v1/app/listings/(:segment)/reject', 'ListingController::reject/$1', ['filter' => 'jwtTenantAdmin:listing']);
$routes->post('/api/v1/app/listings/(:segment)/sale-events', 'SaleEventController::createSubmit/$1', ['filter' => 'jwtAuth']);

$routes->post('/api/v1/app/sale-events/(:segment)/approve', 'SaleEventController::approve/$1', ['filter' => 'jwtTenantAdmin:saleEvent']);
$routes->post('/api/v1/app/sale-events/(:segment)/defect-disclosure', 'SaleEventController::defectDisclosureSubmit/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/dev-force-freeze', 'SaleEventController::devForceFreeze/$1', ['filter' => 'jwtTenantAdmin:saleEvent']);
$routes->post('/api/v1/app/sale-events/(:segment)/emergency-stop', 'SaleEventController::emergencyStop/$1', ['filter' => 'jwtTenantAdmin:saleEvent']);
$routes->post('/api/v1/app/sale-events/(:segment)/dev-fund-emd', 'BidController::devFundEmd/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/bid', 'BidController::placeBid/$1', ['filter' => 'jwtAuth']);
// D-113: BR-28 cascade top-up payment — closes the gap CascadeService::
// processTopupPaid() had no real route to reach.
$routes->post('/api/v1/app/sale-events/(:segment)/dev-pay-topup', 'BidController::devPayTopup/$1', ['filter' => 'jwtAuth']);

// D-117: BR-52/PR-30 Chargeback Handling & Representment.
$routes->post('/api/v1/app/sale-events/(:segment)/dev-file-chargeback', 'ChargebackController::devFile/$1', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/admin/chargebacks', 'ChargebackController::index', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/chargebacks/(:segment)/decide', 'ChargebackController::decide/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/chargebacks/(:segment)/review-integrity', 'ChargebackController::reviewIntegrity/$1', ['filter' => 'jwtSuperAdmin']);

// Buy-Now offers (BR-27/BR-42/BR-29)
$routes->post('/api/v1/app/sale-events/(:segment)/dev-fund-emd-offer', 'OfferController::devFundEmd/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/offers', 'OfferController::submit/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/offers/(:segment)/accept', 'OfferController::accept/$1/$2', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/offers/(:segment)/withdraw', 'OfferController::withdraw/$1', ['filter' => 'jwtAuth']);

// Express Auction (BR-12/PR-11)
$routes->post('/api/v1/app/sale-events/(:segment)/pledge', 'ExpressController::pledge/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/express-bid', 'ExpressController::placeBid/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/dev-force-close-bidding', 'ExpressController::devForceCloseBidding/$1', ['filter' => 'jwtTenantAdmin:saleEvent']);

// Listing media (BR-11, BR-45)
$routes->post('/api/v1/app/listings/(:segment)/media', 'MediaController::upload/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/listings/(:segment)/media/(:segment)/set-primary', 'MediaController::setPrimary/$1/$2', ['filter' => 'jwtAuth']);

// Settlement (BR-33, BR-39) — Phase 3 of the CI4->REST/JWT migration (D-133)
$routes->get('/api/v1/app/settlements/(:segment)', 'SettlementController::show/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/settlements/(:segment)/confirm-seller-noc', 'SettlementController::confirmSellerNoc/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/settlements/(:segment)/confirm-buyer-noc', 'SettlementController::confirmBuyerNoc/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/settlements/(:segment)/rate-as-buyer', 'SettlementController::rateAsBuyer/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/settlements/(:segment)/rate-as-seller', 'SettlementController::rateAsSeller/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/settlements/dev-flag-stalled', 'SettlementController::devFlagStalled', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/settlements/(:segment)/force-resolve', 'SettlementController::forceResolve/$1', ['filter' => 'jwtTenantAdmin:settlement']);

// Dispute Resolution Framework (BR-40)
$routes->post('/api/v1/app/sale-events/(:segment)/dispute', 'DisputeController::fileSubmit/$1', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/disputes/(:segment)', 'DisputeController::show/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/disputes/(:segment)/evidence', 'DisputeController::submitEvidence/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/disputes/(:segment)/rule', 'DisputeController::rule/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/disputes/(:segment)/appeal', 'DisputeController::appeal/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/disputes/(:segment)/rule-appeal', 'DisputeController::ruleOnAppeal/$1', ['filter' => 'jwtSuperAdmin']);

// Phase 4 of the CI4->REST/JWT migration (D-134): KYC (below, near its
// old routes), Payout Bank, EMD Consent, Preferences, My Activity/
// Account, Seller Delisting.
$routes->post('/api/v1/app/payout-bank/request', 'PayoutBankController::requestSubmit', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/payout-bank/confirm', 'PayoutBankController::confirmSubmit', ['filter' => 'jwtAuth']);

$routes->get('/api/v1/app/sale-events/(:segment)/emd-consent/(:segment)', 'EmdConsentController::terms/$1/$2', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/sale-events/(:segment)/emd-consent/(:segment)/confirm', 'EmdConsentController::confirm/$1/$2', ['filter' => 'jwtAuth']);

$routes->get('/api/v1/app/preferences', 'PreferencesController::show', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/preferences', 'PreferencesController::submit', ['filter' => 'jwtAuth']);

$routes->post('/api/v1/app/admin/delist-seller', 'SellerDelistingController::submit', ['filter' => 'jwtSuperAdmin']);

$routes->get('/api/v1/app/my-listings', 'MyActivityController::myListings', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-activity', 'MyActivityController::myActivity', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/profile', 'MyActivityController::profile', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-bids', 'MyActivityController::myBids', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-offers', 'MyActivityController::myOffers', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-purchases', 'MyActivityController::myPurchases', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-purchases/export', 'MyActivityController::myPurchasesExport', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-sales', 'MyActivityController::mySales', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-sales/export', 'MyActivityController::mySalesExport', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-messages', 'MyActivityController::messages', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/my-messages/(:segment)/read', 'MyActivityController::markMessageRead/$1', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-star-ratings', 'MyActivityController::starRatings', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-rating-history', 'MyActivityController::ratingHistory', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-buyer-dashboard', 'MyActivityController::buyerDashboard', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/my-seller-dashboard', 'MyActivityController::sellerDashboard', ['filter' => 'jwtAuth']);

$routes->get('/api/v1/app/account', 'MyActivityController::profile', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/account/edit', 'AccountController::editSubmit', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/account/change-mpin/request-otp', 'AccountController::changeMpinRequestOtp', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/account/change-mpin/confirm', 'AccountController::changeMpinConfirm', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/account/delete/request', 'AccountController::deleteRequestSubmit', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/account/delete/cancel', 'AccountController::deleteCancelSubmit', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/account/earnings', 'AccountController::earnings', ['filter' => 'jwtAuth']);

// Super Admin real auth (BR-04) — see SuperAdminAuthApiController's
// REST/JWT routes near the bottom of this file for the setup-totp/
// login/forgot-mpin API that replaced this section (D-137, Phase 6).

// Phase 5 of the CI4->REST/JWT migration (D-135): Admin dashboard,
// Tenant/User management — jwtSuperAdmin-gated.
$routes->get('/api/v1/app/admin', 'AdminController::dashboard', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/alerts', 'AdminController::alerts', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/alerts/server-time-drift/(:segment)/acknowledge', 'AdminController::acknowledgeServerTimeDrift/$1', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/tenants', 'TenantController::list', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/tenants', 'TenantController::createSubmit', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/users', 'UserController::index', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/users/(:segment)', 'UserController::detail/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/users/(:segment)/promote-tenant-admin', 'UserController::promoteTenantAdmin/$1', ['filter' => 'jwtSuperAdmin']);

// Seller Application (BR-09)
$routes->get('/api/v1/app/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applyStatus/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applySubmit/$1', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/tenants/(:segment)/pending-sellers', 'SellerApplicationController::pendingList/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->post('/api/v1/app/seller-applications/(:segment)/approve', 'SellerApplicationController::approve/$1', ['filter' => 'jwtTenantAdmin:sellerApplication']);
$routes->post('/api/v1/app/seller-applications/(:segment)/reject', 'SellerApplicationController::reject/$1', ['filter' => 'jwtTenantAdmin:sellerApplication']);
$routes->get('/api/v1/app/tenants/(:segment)/dashboard', 'TenantAdminController::dashboard/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->get('/api/v1/app/tenants/(:segment)/verification', 'TenantAdminController::verification/$1', ['filter' => 'jwtTenantAdmin:tenant']);

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

// Navigation gaps closed — browse. (No server-side /logout route: JWT
// logout is client-side — the React app just discards the token.)
$routes->get('/browse', 'Home::browse');
$routes->get('/listings', 'Home::browse');
$routes->get('/api/v1/app/admin/audit-log', 'AuditLogController::index', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/audit-log/verify', 'AuditLogController::verifyIntegrity', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/audit-log/export', 'AuditLogController::export', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/tenants/(:segment)/media-waiver', 'TenantMediaWaiverController::requestSubmit/$1', ['filter' => 'jwtAuth']);
$routes->get('/api/v1/app/admin/media-waivers', 'TenantMediaWaiverController::pendingList', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/media-waivers/(:segment)/decide', 'TenantMediaWaiverController::decide/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/media-waivers/(:segment)/revoke', 'TenantMediaWaiverController::revoke/$1', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/standing-review/(:segment)', 'StandingReviewController::show/$1', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/admin/standing-review/(:segment)/rule', 'StandingReviewController::rule/$1', ['filter' => 'jwtAuth']);
// SellerDelistingController, PreferencesController, EmdConsentController:
// migrated to /api/v1 with jwtSuperAdmin/jwtAuth below (Phase 4, D-134).
$routes->get('/ticker-feed', 'LiveTickerController::feed');
$routes->get('/api/v1/app/admin/tenants/(:segment)', 'TenantController::view/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/tenants/(:segment)/edit', 'TenantController::editSubmit/$1', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/tenants', 'TenantController::directory');

// AML Monitoring (BR-54/PR-31) — SaaS Admin only
$routes->get('/api/v1/app/admin/aml', 'AmlController::index', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/aml/(:segment)/review', 'AmlController::review/$1', ['filter' => 'jwtSuperAdmin']);

// Payout Account Change Control (BR-50/PR-28) — migrated to /api/v1
// below (Phase 4, D-134).
$routes->get('/api/v1/app/admin/payout-reviews', 'PayoutReviewController::index', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/admin/payout-reviews/(:segment)/decide', 'PayoutReviewController::decide/$1', ['filter' => 'jwtAuth']);

// Pending rating downgrade reviews (BR-35/BR-36)
$routes->get('/api/v1/app/admin/rating-reviews', 'RatingReviewController::index', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/admin/rating-reviews/(:segment)/approve', 'RatingReviewController::approve/$1', ['filter' => 'jwtAuth']);

// Tenant monthly billing for Seller-Pays Success Fees (BR-32/33, D-88)
$routes->get('/api/v1/app/tenants/(:segment)/billing', 'TenantBillingController::forTenant/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->get('/api/v1/app/admin/tenant-invoices', 'TenantBillingController::index', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/tenant-invoices/(:segment)/mark-paid', 'TenantBillingController::markPaid/$1', ['filter' => 'jwtSuperAdmin']);

// Seller Management for Tenant Admin (BR-61, built on the real Standing Review system)
$routes->get('/api/v1/app/tenants/(:segment)/sellers', 'SellerManagementController::list/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->get('/api/v1/app/tenants/(:segment)/sellers/(:segment)', 'SellerManagementController::detail/$1/$2', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->post('/api/v1/app/tenants/(:segment)/sellers/(:segment)/initiate-review', 'SellerManagementController::initiateReview/$1/$2', ['filter' => 'jwtAuth']);

// Consent Audit viewer (BR-51)
$routes->get('/api/v1/app/admin/consent-audit', 'ConsentAuditController::index', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/consent-audit/export', 'ConsentAuditController::export', ['filter' => 'jwtSuperAdmin']);

// Phase 3A: account management — migrated to /api/v1 below (Phase 4, D-134).

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

// Phase 3A: real, dedicated, paginated/filterable transaction pages —
// migrated to /api/v1 below (Phase 4, D-134).

// Phase 3C+: favorites (migrated above), saved searches, search
// history, recommendations
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
// MyActivityController's messages/star-ratings/rating-history/dashboards:
// migrated to /api/v1 below (Phase 4, D-134).
$routes->get('/api/v1/app/admin/lots', 'AdminController::lotDirectory', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/trading-sessions', 'AdminController::tradingSessionDirectory', ['filter' => 'jwtSuperAdmin']);

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
$routes->get('/api/v1/app/admin/rules', 'SovereignRuleController::index', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/rules', 'SovereignRuleController::createSubmit', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/rules/(:segment)', 'SovereignRuleController::show/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/rules/(:segment)/edit', 'SovereignRuleController::editSubmit/$1', ['filter' => 'jwtSuperAdmin']);

// KYC Verification (BR-17/BR-18/BR-55/PR-15) — patron-facing onboarding.
// Phase 4 of the CI4->REST/JWT migration (D-134).
$routes->get('/api/v1/app/kyc', 'KycController::form', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/kyc/questionnaire', 'KycController::saveQuestionnaire', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/kyc/documents', 'KycController::uploadDocument', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/kyc/addresses', 'KycController::saveAddress', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/kyc/banking', 'KycController::saveBanking', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/kyc/submit', 'KycController::submit', ['filter' => 'jwtAuth']);

// KYC review — Super Admin (SaaS Admin) side, see KycReviewController's
// class doc block for why this is Super Admin rather than Tenant Admin.
$routes->get('/api/v1/app/admin/kyc', 'KycReviewController::index', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/kyc/(:segment)', 'KycReviewController::detail/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/kyc/(:segment)/verify-flag', 'KycReviewController::verifyFlag/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/kyc/(:segment)/decide', 'KycReviewController::decide/$1', ['filter' => 'jwtSuperAdmin']);
$routes->post('/api/v1/app/admin/kyc/(:segment)/clear-edd', 'KycReviewController::clearEdd/$1', ['filter' => 'jwtSuperAdmin']);
$routes->get('/api/v1/app/admin/kyc-documents/(:segment)/download', 'KycReviewController::downloadDocument/$1', ['filter' => 'jwtSuperAdmin']);

// Tenant API Access (BR-62-66/PR-37) — Tenant Admin portal-side credential
// and webhook management.
$routes->get('/api/v1/app/tenants/(:segment)/api-access', 'TenantApiSettingsController::index/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->post('/api/v1/app/tenants/(:segment)/api-access/credentials', 'TenantApiSettingsController::issueCredential/$1', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->post('/api/v1/app/tenants/(:segment)/api-access/credentials/(:segment)/revoke', 'TenantApiSettingsController::revokeCredential/$1/$2', ['filter' => 'jwtTenantAdmin:tenant']);
$routes->post('/api/v1/app/tenants/(:segment)/api-access/webhook-url', 'TenantApiSettingsController::updateWebhookUrl/$1', ['filter' => 'jwtTenantAdmin:tenant']);

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

// User REST/JWT Login API — mobile-OTP login for mobile/SPA clients.
// Distinct from both the browser session flow (AuthController) and the
// Tenant API above: this issues a user-scoped JWT (jwtAuth filter), not a
// PHP session cookie or a Tenant API credential token.
// 1) request OTP  2) verify OTP -> otp_ticket  3) submit profile -> JWT
$routes->post('/api/v1/app/auth/otp/request', 'UserAuthApiController::requestOtp');
$routes->post('/api/v1/app/auth/otp/verify', 'UserAuthApiController::verifyOtp');
$routes->post('/api/v1/app/auth/submit', 'UserAuthApiController::submit');
$routes->get('/api/v1/app/auth/me', 'UserAuthApiController::me', ['filter' => 'jwtAuth']);

// BR-02 mPIN registration/login (D-137, Phase 6 — replaces the former
// AuthController). registerVerifyOtp and loginVerifyResetOtp both
// return a pending_ticket for the shared final step, mpin/complete.
$routes->post('/api/v1/app/auth/register/otp/request', 'UserAuthApiController::registerRequestOtp');
$routes->post('/api/v1/app/auth/register/otp/verify', 'UserAuthApiController::registerVerifyOtp');
$routes->post('/api/v1/app/auth/login', 'UserAuthApiController::loginWithMpin');
$routes->post('/api/v1/app/auth/login/verify-reset-otp', 'UserAuthApiController::loginVerifyResetOtp');
$routes->post('/api/v1/app/auth/mpin/complete', 'UserAuthApiController::completeMpinSetup');

// User forgot-password (mPIN reset), unauthenticated. Standalone
// entry point — reuses the same pending_ticket verify/complete steps
// as the lockout-triggered reset above (loginVerifyResetOtp/
// completeMpinSetup), see UserAuthApiService::requestForgotPassword().
$routes->post('/api/v1/app/auth/forgot-password', 'UserAuthApiController::forgotPassword');
$routes->post('/api/v1/app/auth/forgot-password/verify', 'UserAuthApiController::loginVerifyResetOtp');

// Super Admin REST/JWT login (BR-04) — JWT counterpart of the former
// SuperAdminAuthController. Custodian login is email + password
// (super_admin_credential table) + TOTP/email-OTP second factor.
$routes->post('/api/v1/app/admin/auth/login', 'SuperAdminAuthApiController::login');
$routes->post('/api/v1/app/admin/auth/login/verify-email', 'SuperAdminAuthApiController::verifyEmail');
$routes->post('/api/v1/app/admin/auth/setup-totp', 'SuperAdminAuthApiController::setupTotp', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/admin/auth/setup-totp/confirm', 'SuperAdminAuthApiController::confirmSetupTotp', ['filter' => 'jwtAuth']);
$routes->post('/api/v1/app/admin/auth/forgot-mpin', 'SuperAdminAuthApiController::forgotMpinRequest');
$routes->post('/api/v1/app/admin/auth/forgot-mpin/verify', 'SuperAdminAuthApiController::forgotMpinVerify');
// "forgot-password" aliases (Custodian-facing naming) for the same two
// endpoints above, plus the completion step that actually sets the new
// password on super_admin_credential.
$routes->post('/api/v1/app/admin/auth/forgot-password', 'SuperAdminAuthApiController::forgotMpinRequest');
$routes->post('/api/v1/app/admin/auth/forgot-password/verify', 'SuperAdminAuthApiController::forgotMpinVerify');
$routes->post('/api/v1/app/admin/auth/forgot-password/complete', 'SuperAdminAuthApiController::setNewPassword');
