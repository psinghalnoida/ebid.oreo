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
$routes->post('/listings/(:segment)/dev-approve', 'ListingController::devApprove/$1');
$routes->post('/listings/(:segment)/dev-reject', 'ListingController::devReject/$1');
$routes->post('/listings/(:segment)/sale-events', 'SaleEventController::createSubmit/$1');

$routes->post('/sale-events/(:segment)/dev-approve', 'SaleEventController::devApprove/$1');
$routes->post('/sale-events/(:segment)/dev-force-freeze', 'SaleEventController::devForceFreeze/$1');
$routes->post('/sale-events/(:segment)/dev-fund-emd', 'BidController::devFundEmd/$1');
$routes->post('/sale-events/(:segment)/bid', 'BidController::placeBid/$1');
