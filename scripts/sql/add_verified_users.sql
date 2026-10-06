-- ---------------------------------------------------------------------------
-- Add KYC-verified demo users (3 Buyers, 3 Sellers, 3 Tenant Admins)
-- Database: u619498811_adwitix
--
-- * Adds data only: nothing is deleted or changed in existing rows.
-- * Super Admin stays single: the existing account +919811047785
--   (party id ad7687c4-a3a8-11f1-a687-0a1b44aff924). Its PIN is already 1234.
-- * Every new user: mobile verified, KYC = verified, PAN / Aadhaar / email /
--   bank verified, all approvals recorded as done by the Super Admin.
-- * Every new user logs in with mobile number + PIN 1234.
-- * Insert or update: if a user already exists it is updated, not duplicated.
--   Safe to run more than once.
--
-- Run it in phpMyAdmin: select the database -> "Import" (or "SQL" tab).
-- ---------------------------------------------------------------------------

SET time_zone = "+00:00";
SET NAMES utf8mb4;
START TRANSACTION;

-- Super Admin (approver): ad7687c4-a3a8-11f1-a687-0a1b44aff924 (+919811047785)
-- PIN for all new users: 1234 (stored as a bcrypt hash)
-- Tenants used: 550e...0001 General Marketplace, 550e...0002 ABC Institute, 550e...0003 XYZ Company

-- ---------------------------------------------------------------------------
-- 1) Super Admin: add the global (no-tenant) super_admin role.
--    The app checks for a super_admin role with an empty tenant
--    (AuthorizationService::isSuperAdmin). The existing role row is tied to
--    "General Marketplace", so it is kept as-is and a global one is added.
-- ---------------------------------------------------------------------------
INSERT INTO `party_role` (`id`, `party_id`, `role`, `tenant_id`, `granted_at`, `revoked_at`, `created_at`, `tenant_id_key`, `revoked_at_key`, `active_tenant_admin_marker`) VALUES
('a1000000-0000-4000-8000-000000000900', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', 'super_admin', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL)
ON DUPLICATE KEY UPDATE `revoked_at` = NULL;

-- ---------------------------------------------------------------------------
-- 2) New users (party) - all KYC verified by the Super Admin
-- ---------------------------------------------------------------------------
INSERT INTO `party` (`id`, `mobile_number`, `mobile_verified_at`, `mpin_hash`, `failed_mpin_attempts`, `entity_type`, `full_name`, `pan`, `aadhaar_masked`, `date_of_birth`, `occupation`, `kyc_status`, `kyc_status_reason`, `created_at`, `updated_at`, `recovery_email`, `payout_bank_account_number`, `payout_bank_ifsc`, `payout_bank_updated_at`, `payout_bank_account_holder_name`, `payout_bank_name`, `payout_bank_branch_name`, `pan_verified_at`, `aadhaar_verified_at`, `email_verified_at`, `bank_verified_at`, `kyc_verified_by_party_id`, `kyc_submitted_at`) VALUES
('a1000000-0000-4000-8000-000000000101', '+919000000101', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Rahul Sharma',  'BUYRS0101A', 'XXXX-XXXX-0101', '1985-04-12', 'Salaried',       'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'buyer1@example.com',  '50100000000101', 'HDFC0000101', '2026-10-06 12:00:00.000000', 'Rahul Sharma',  'HDFC Bank',  'Noida Sector 18',  '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000102', '+919000000102', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Priya Verma',   'BUYPV0102B', 'XXXX-XXXX-0102', '1990-08-23', 'Business Owner', 'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'buyer2@example.com',  '50100000000102', 'ICIC0000102', '2026-10-06 12:00:00.000000', 'Priya Verma',   'ICICI Bank', 'Connaught Place', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000103', '+919000000103', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Amit Kumar',    'BUYAK0103C', 'XXXX-XXXX-0103', '1988-01-05', 'Professional',   'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'buyer3@example.com',  '50100000000103', 'SBIN0000103', '2026-10-06 12:00:00.000000', 'Amit Kumar',    'SBI',        'Ghaziabad',        '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000201', '+919000000201', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Neha Gupta',    'SELNG0201D', 'XXXX-XXXX-0201', '1982-11-30', 'Trader',         'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'seller1@example.com', '50100000000201', 'HDFC0000201', '2026-10-06 12:00:00.000000', 'Neha Gupta',    'HDFC Bank',  'Noida Sector 62',  '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000202', '+919000000202', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Vikram Rao',    'SELVR0202E', 'XXXX-XXXX-0202', '1979-06-17', 'Business Owner', 'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'seller2@example.com', '50100000000202', 'ICIC0000202', '2026-10-06 12:00:00.000000', 'Vikram Rao',    'ICICI Bank', 'Gurugram',         '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000203', '+919000000203', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Sunita Jain',   'SELSJ0203F', 'XXXX-XXXX-0203', '1984-02-09', 'Trader',         'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'seller3@example.com', '50100000000203', 'SBIN0000203', '2026-10-06 12:00:00.000000', 'Sunita Jain',   'SBI',        'Faridabad',        '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000301', '+919000000301', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Rohit Mehta',   'TADRM0301G', 'XXXX-XXXX-0301', '1980-09-14', 'Administrator',  'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'tadmin1@example.com', '50100000000301', 'HDFC0000301', '2026-10-06 12:00:00.000000', 'Rohit Mehta',   'HDFC Bank',  'Noida Sector 63',  '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000302', '+919000000302', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Kavita Singh',  'TADKS0302H', 'XXXX-XXXX-0302', '1983-12-01', 'Administrator',  'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'tadmin2@example.com', '50100000000302', 'ICIC0000302', '2026-10-06 12:00:00.000000', 'Kavita Singh',  'ICICI Bank', 'Saket',            '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000000303', '+919000000303', '2026-10-06 12:00:00.000000', '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a', 0, 'individual', 'Sanjay Kapoor', 'TADSK0303J', 'XXXX-XXXX-0303', '1977-07-21', 'Administrator',  'verified', NULL, '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'tadmin3@example.com', '50100000000303', 'SBIN0000303', '2026-10-06 12:00:00.000000', 'Sanjay Kapoor', 'SBI',        'Lucknow',          '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000', 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000')
ON DUPLICATE KEY UPDATE `mobile_verified_at` = VALUES(`mobile_verified_at`), `mpin_hash` = VALUES(`mpin_hash`), `failed_mpin_attempts` = 0, `full_name` = VALUES(`full_name`), `kyc_status` = 'verified', `kyc_status_reason` = NULL, `pan_verified_at` = VALUES(`pan_verified_at`), `aadhaar_verified_at` = VALUES(`aadhaar_verified_at`), `email_verified_at` = VALUES(`email_verified_at`), `bank_verified_at` = VALUES(`bank_verified_at`), `kyc_verified_by_party_id` = VALUES(`kyc_verified_by_party_id`), `kyc_submitted_at` = VALUES(`kyc_submitted_at`), `updated_at` = VALUES(`updated_at`);

-- ---------------------------------------------------------------------------
-- 3) Registered address for every new user (required by KYC, BR-18)
-- ---------------------------------------------------------------------------
INSERT INTO `party_address` (`id`, `party_id`, `address_type`, `line1`, `line2`, `city`, `district`, `state`, `country`, `pin_code`, `created_at`, `updated_at`) VALUES
('a1000000-0000-4000-8000-000000001101', 'a1000000-0000-4000-8000-000000000101', 'registered', 'House 11, Sector 18',     NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201301', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001102', 'a1000000-0000-4000-8000-000000000102', 'registered', 'Flat 22, Connaught Place', NULL, 'New Delhi', 'New Delhi',          'Delhi',         'India', '110001', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001103', 'a1000000-0000-4000-8000-000000000103', 'registered', 'Plot 33, Raj Nagar',       NULL, 'Ghaziabad', 'Ghaziabad',          'Uttar Pradesh', 'India', '201002', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001201', 'a1000000-0000-4000-8000-000000000201', 'registered', 'Shop 5, Sector 62',        NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201309', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001202', 'a1000000-0000-4000-8000-000000000202', 'registered', 'Unit 8, Udyog Vihar',      NULL, 'Gurugram',  'Gurugram',           'Haryana',       'India', '122016', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001203', 'a1000000-0000-4000-8000-000000000203', 'registered', 'Plot 14, Sector 24',       NULL, 'Faridabad', 'Faridabad',          'Haryana',       'India', '121005', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001301', 'a1000000-0000-4000-8000-000000000301', 'registered', 'Office 3, Sector 63',      NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201301', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001302', 'a1000000-0000-4000-8000-000000000302', 'registered', 'Block C, Saket',           NULL, 'New Delhi', 'South Delhi',        'Delhi',         'India', '110017', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000001303', 'a1000000-0000-4000-8000-000000000303', 'registered', '12 Hazratganj',            NULL, 'Lucknow',   'Lucknow',            'Uttar Pradesh', 'India', '226001', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000')
ON DUPLICATE KEY UPDATE `line1` = VALUES(`line1`), `city` = VALUES(`city`), `district` = VALUES(`district`), `state` = VALUES(`state`), `pin_code` = VALUES(`pin_code`), `updated_at` = VALUES(`updated_at`);

-- ---------------------------------------------------------------------------
-- 4) Roles
--    * Every user gets the global 'buyer' role (the app gives it to all
--      registered users).
--    * Sellers get 'seller' on General Marketplace.
--    * Tenant Admins: one per tenant (the app allows only one active
--      Tenant Admin per tenant), so each gets a different tenant.
-- ---------------------------------------------------------------------------
INSERT INTO `party_role` (`id`, `party_id`, `role`, `tenant_id`, `granted_at`, `revoked_at`, `created_at`, `tenant_id_key`, `revoked_at_key`, `active_tenant_admin_marker`) VALUES
('a1000000-0000-4000-8000-000000002101', 'a1000000-0000-4000-8000-000000000101', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002102', 'a1000000-0000-4000-8000-000000000102', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002103', 'a1000000-0000-4000-8000-000000000103', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002201', 'a1000000-0000-4000-8000-000000000201', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002202', 'a1000000-0000-4000-8000-000000000202', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002203', 'a1000000-0000-4000-8000-000000000203', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002301', 'a1000000-0000-4000-8000-000000000301', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002302', 'a1000000-0000-4000-8000-000000000302', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002303', 'a1000000-0000-4000-8000-000000000303', 'buyer', NULL, '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003201', 'a1000000-0000-4000-8000-000000000201', 'seller', '550e8400-e29b-41d4-a716-446655440001', '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440001', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003202', 'a1000000-0000-4000-8000-000000000202', 'seller', '550e8400-e29b-41d4-a716-446655440001', '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440001', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003203', 'a1000000-0000-4000-8000-000000000203', 'seller', '550e8400-e29b-41d4-a716-446655440001', '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440001', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003301', 'a1000000-0000-4000-8000-000000000301', 'tenant_admin', '550e8400-e29b-41d4-a716-446655440001', '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440001', '1970-01-01 00:00:00.000000', 'Y'),
('a1000000-0000-4000-8000-000000003302', 'a1000000-0000-4000-8000-000000000302', 'tenant_admin', '550e8400-e29b-41d4-a716-446655440002',     '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440002',     '1970-01-01 00:00:00.000000', 'Y'),
('a1000000-0000-4000-8000-000000003303', 'a1000000-0000-4000-8000-000000000303', 'tenant_admin', '550e8400-e29b-41d4-a716-446655440003',     '2026-10-06 12:00:00.000000', NULL, '2026-10-06 12:00:00.000000', '550e8400-e29b-41d4-a716-446655440003',     '1970-01-01 00:00:00.000000', 'Y')
ON DUPLICATE KEY UPDATE `revoked_at` = NULL;

-- ---------------------------------------------------------------------------
-- 5) Seller applications - approved by the Super Admin
-- ---------------------------------------------------------------------------
INSERT INTO `seller_application` (`id`, `party_id`, `tenant_id`, `status`, `rejection_reason`, `decided_by_party_id`, `applied_at`, `decided_at`) VALUES
('a1000000-0000-4000-8000-000000004201', 'a1000000-0000-4000-8000-000000000201', '550e8400-e29b-41d4-a716-446655440001', 'approved', NULL, 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000004202', 'a1000000-0000-4000-8000-000000000202', '550e8400-e29b-41d4-a716-446655440001', 'approved', NULL, 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000'),
('a1000000-0000-4000-8000-000000004203', 'a1000000-0000-4000-8000-000000000203', '550e8400-e29b-41d4-a716-446655440001', 'approved', NULL, 'ad7687c4-a3a8-11f1-a687-0a1b44aff924', '2026-10-06 12:00:00.000000', '2026-10-06 12:00:00.000000')
ON DUPLICATE KEY UPDATE `status` = 'approved', `rejection_reason` = NULL, `decided_by_party_id` = VALUES(`decided_by_party_id`), `decided_at` = VALUES(`decided_at`);

COMMIT;
