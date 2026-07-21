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
