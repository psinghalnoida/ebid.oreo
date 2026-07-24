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
$routes->get('/admin/tenants/create', 'TenantController::createForm', ['filter' => 'superAdmin']);
$routes->post('/admin/tenants', 'TenantController::createSubmit', ['filter' => 'superAdmin']);

// Seller Application (BR-09)
$routes->get('/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applyForm/$1');
$routes->post('/tenants/(:segment)/apply-to-sell', 'SellerApplicationController::applySubmit/$1');
$routes->get('/tenants/(:segment)/pending-sellers', 'SellerApplicationController::pendingList/$1', ['filter' => 'tenantAdmin:tenant']);
$routes->post('/seller-applications/(:segment)/approve', 'SellerApplicationController::approve/$1', ['filter' => 'tenantAdmin:sellerApplication']);
$routes->post('/seller-applications/(:segment)/reject', 'SellerApplicationController::reject/$1', ['filter' => 'tenantAdmin:sellerApplication']);
$routes->get('/tenants/(:segment)/dashboard', 'TenantAdminController::dashboard/$1', ['filter' => 'tenantAdmin:tenant']);

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
$routes->get('/my-listings', 'MyActivityController::myListings');
$routes->get('/my-activity', 'MyActivityController::myActivity');
$routes->get('/profile', 'MyActivityController::profile');
$routes->post('/listings/(:segment)/edit', 'ListingController::editSubmit/$1');
$routes->post('/sale-events/(:segment)/emergency-stop', 'SaleEventController::emergencyStop/$1', ['filter' => 'tenantAdmin:saleEvent']);

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
$routes->get('/terminology', 'InfoController::terminology');
