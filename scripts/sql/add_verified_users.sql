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
-- * Safe to run more than once (INSERT IGNORE / WHERE NOT EXISTS).
--
-- Run it in phpMyAdmin: select the database -> "Import" (or "SQL" tab).
-- ---------------------------------------------------------------------------

SET time_zone = "+00:00";
SET NAMES utf8mb4;
START TRANSACTION;

-- Existing Super Admin (approver of everything below)
SET @super_admin := 'ad7687c4-a3a8-11f1-a687-0a1b44aff924';
-- bcrypt hash of PIN "1234"
SET @pin_1234 := '$2y$12$ooAapKK2/gg.4bIYqk82BejP0CMWd66bxwKVIcNtqayQoBqWIaq7a';
SET @now := NOW(6);

-- Tenants (already exist in the database)
SET @t_general := '550e8400-e29b-41d4-a716-446655440001'; -- General Marketplace
SET @t_abc     := '550e8400-e29b-41d4-a716-446655440002'; -- ABC Institute
SET @t_xyz     := '550e8400-e29b-41d4-a716-446655440003'; -- XYZ Company

-- ---------------------------------------------------------------------------
-- 1) Super Admin: add the global (no-tenant) super_admin role.
--    The app checks for a super_admin role with an empty tenant
--    (AuthorizationService::isSuperAdmin). The existing role row is tied to
--    "General Marketplace", so it is kept as-is and a global one is added.
-- ---------------------------------------------------------------------------
INSERT INTO `party_role` (`id`, `party_id`, `role`, `tenant_id`, `granted_at`, `revoked_at`, `created_at`, `tenant_id_key`, `revoked_at_key`, `active_tenant_admin_marker`)
SELECT 'a1000000-0000-4000-8000-000000000900', @super_admin, 'super_admin', NULL, @now, NULL, @now,
       '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `party_role`
    WHERE `party_id` = @super_admin AND `role` = 'super_admin'
      AND `tenant_id` IS NULL AND `revoked_at` IS NULL
);

-- ---------------------------------------------------------------------------
-- 2) New users (party) - all KYC verified by the Super Admin
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `party` (`id`, `mobile_number`, `mobile_verified_at`, `mpin_hash`, `failed_mpin_attempts`, `entity_type`, `full_name`, `pan`, `aadhaar_masked`, `date_of_birth`, `occupation`, `kyc_status`, `kyc_status_reason`, `created_at`, `updated_at`, `recovery_email`, `payout_bank_account_number`, `payout_bank_ifsc`, `payout_bank_updated_at`, `payout_bank_account_holder_name`, `payout_bank_name`, `payout_bank_branch_name`, `pan_verified_at`, `aadhaar_verified_at`, `email_verified_at`, `bank_verified_at`, `kyc_verified_by_party_id`, `kyc_submitted_at`) VALUES
-- Buyers
('a1000000-0000-4000-8000-000000000101', '+919000000101', @now, @pin_1234, 0, 'individual', 'Rahul Sharma',  'BUYRS0101A', 'XXXX-XXXX-0101', '1985-04-12', 'Salaried',       'verified', NULL, @now, @now, 'buyer1@example.com',  '50100000000101', 'HDFC0000101', @now, 'Rahul Sharma',  'HDFC Bank',  'Noida Sector 18',  @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000102', '+919000000102', @now, @pin_1234, 0, 'individual', 'Priya Verma',   'BUYPV0102B', 'XXXX-XXXX-0102', '1990-08-23', 'Business Owner', 'verified', NULL, @now, @now, 'buyer2@example.com',  '50100000000102', 'ICIC0000102', @now, 'Priya Verma',   'ICICI Bank', 'Connaught Place', @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000103', '+919000000103', @now, @pin_1234, 0, 'individual', 'Amit Kumar',    'BUYAK0103C', 'XXXX-XXXX-0103', '1988-01-05', 'Professional',   'verified', NULL, @now, @now, 'buyer3@example.com',  '50100000000103', 'SBIN0000103', @now, 'Amit Kumar',    'SBI',        'Ghaziabad',        @now, @now, @now, @now, @super_admin, @now),
-- Sellers
('a1000000-0000-4000-8000-000000000201', '+919000000201', @now, @pin_1234, 0, 'individual', 'Neha Gupta',    'SELNG0201D', 'XXXX-XXXX-0201', '1982-11-30', 'Trader',         'verified', NULL, @now, @now, 'seller1@example.com', '50100000000201', 'HDFC0000201', @now, 'Neha Gupta',    'HDFC Bank',  'Noida Sector 62',  @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000202', '+919000000202', @now, @pin_1234, 0, 'individual', 'Vikram Rao',    'SELVR0202E', 'XXXX-XXXX-0202', '1979-06-17', 'Business Owner', 'verified', NULL, @now, @now, 'seller2@example.com', '50100000000202', 'ICIC0000202', @now, 'Vikram Rao',    'ICICI Bank', 'Gurugram',         @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000203', '+919000000203', @now, @pin_1234, 0, 'individual', 'Sunita Jain',   'SELSJ0203F', 'XXXX-XXXX-0203', '1984-02-09', 'Trader',         'verified', NULL, @now, @now, 'seller3@example.com', '50100000000203', 'SBIN0000203', @now, 'Sunita Jain',   'SBI',        'Faridabad',        @now, @now, @now, @now, @super_admin, @now),
-- Tenant Admins
('a1000000-0000-4000-8000-000000000301', '+919000000301', @now, @pin_1234, 0, 'individual', 'Rohit Mehta',   'TADRM0301G', 'XXXX-XXXX-0301', '1980-09-14', 'Administrator',  'verified', NULL, @now, @now, 'tadmin1@example.com', '50100000000301', 'HDFC0000301', @now, 'Rohit Mehta',   'HDFC Bank',  'Noida Sector 63',  @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000302', '+919000000302', @now, @pin_1234, 0, 'individual', 'Kavita Singh',  'TADKS0302H', 'XXXX-XXXX-0302', '1983-12-01', 'Administrator',  'verified', NULL, @now, @now, 'tadmin2@example.com', '50100000000302', 'ICIC0000302', @now, 'Kavita Singh',  'ICICI Bank', 'Saket',            @now, @now, @now, @now, @super_admin, @now),
('a1000000-0000-4000-8000-000000000303', '+919000000303', @now, @pin_1234, 0, 'individual', 'Sanjay Kapoor', 'TADSK0303J', 'XXXX-XXXX-0303', '1977-07-21', 'Administrator',  'verified', NULL, @now, @now, 'tadmin3@example.com', '50100000000303', 'SBIN0000303', @now, 'Sanjay Kapoor', 'SBI',        'Lucknow',          @now, @now, @now, @now, @super_admin, @now);

-- ---------------------------------------------------------------------------
-- 3) Registered address for every new user (required by KYC, BR-18)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `party_address` (`id`, `party_id`, `address_type`, `line1`, `line2`, `city`, `district`, `state`, `country`, `pin_code`, `created_at`, `updated_at`) VALUES
('a1000000-0000-4000-8000-000000001101', 'a1000000-0000-4000-8000-000000000101', 'registered', 'House 11, Sector 18',     NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201301', @now, @now),
('a1000000-0000-4000-8000-000000001102', 'a1000000-0000-4000-8000-000000000102', 'registered', 'Flat 22, Connaught Place', NULL, 'New Delhi', 'New Delhi',          'Delhi',         'India', '110001', @now, @now),
('a1000000-0000-4000-8000-000000001103', 'a1000000-0000-4000-8000-000000000103', 'registered', 'Plot 33, Raj Nagar',       NULL, 'Ghaziabad', 'Ghaziabad',          'Uttar Pradesh', 'India', '201002', @now, @now),
('a1000000-0000-4000-8000-000000001201', 'a1000000-0000-4000-8000-000000000201', 'registered', 'Shop 5, Sector 62',        NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201309', @now, @now),
('a1000000-0000-4000-8000-000000001202', 'a1000000-0000-4000-8000-000000000202', 'registered', 'Unit 8, Udyog Vihar',      NULL, 'Gurugram',  'Gurugram',           'Haryana',       'India', '122016', @now, @now),
('a1000000-0000-4000-8000-000000001203', 'a1000000-0000-4000-8000-000000000203', 'registered', 'Plot 14, Sector 24',       NULL, 'Faridabad', 'Faridabad',          'Haryana',       'India', '121005', @now, @now),
('a1000000-0000-4000-8000-000000001301', 'a1000000-0000-4000-8000-000000000301', 'registered', 'Office 3, Sector 63',      NULL, 'Noida',     'Gautam Buddh Nagar', 'Uttar Pradesh', 'India', '201301', @now, @now),
('a1000000-0000-4000-8000-000000001302', 'a1000000-0000-4000-8000-000000000302', 'registered', 'Block C, Saket',           NULL, 'New Delhi', 'South Delhi',        'Delhi',         'India', '110017', @now, @now),
('a1000000-0000-4000-8000-000000001303', 'a1000000-0000-4000-8000-000000000303', 'registered', '12 Hazratganj',            NULL, 'Lucknow',   'Lucknow',            'Uttar Pradesh', 'India', '226001', @now, @now);

-- ---------------------------------------------------------------------------
-- 4) Roles
--    * Every user gets the global 'buyer' role (the app gives it to all
--      registered users).
--    * Sellers get 'seller' on General Marketplace.
--    * Tenant Admins: one per tenant (the app allows only one active
--      Tenant Admin per tenant), so each gets a different tenant.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `party_role` (`id`, `party_id`, `role`, `tenant_id`, `granted_at`, `revoked_at`, `created_at`, `tenant_id_key`, `revoked_at_key`, `active_tenant_admin_marker`) VALUES
-- buyer role (all 9 users)
('a1000000-0000-4000-8000-000000002101', 'a1000000-0000-4000-8000-000000000101', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002102', 'a1000000-0000-4000-8000-000000000102', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002103', 'a1000000-0000-4000-8000-000000000103', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002201', 'a1000000-0000-4000-8000-000000000201', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002202', 'a1000000-0000-4000-8000-000000000202', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002203', 'a1000000-0000-4000-8000-000000000203', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002301', 'a1000000-0000-4000-8000-000000000301', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002302', 'a1000000-0000-4000-8000-000000000302', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000002303', 'a1000000-0000-4000-8000-000000000303', 'buyer', NULL, @now, NULL, @now, '00000000-0000-0000-0000-000000000000', '1970-01-01 00:00:00.000000', NULL),
-- seller role (General Marketplace)
('a1000000-0000-4000-8000-000000003201', 'a1000000-0000-4000-8000-000000000201', 'seller', @t_general, @now, NULL, @now, @t_general, '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003202', 'a1000000-0000-4000-8000-000000000202', 'seller', @t_general, @now, NULL, @now, @t_general, '1970-01-01 00:00:00.000000', NULL),
('a1000000-0000-4000-8000-000000003203', 'a1000000-0000-4000-8000-000000000203', 'seller', @t_general, @now, NULL, @now, @t_general, '1970-01-01 00:00:00.000000', NULL),
-- tenant_admin role (one tenant each)
('a1000000-0000-4000-8000-000000003301', 'a1000000-0000-4000-8000-000000000301', 'tenant_admin', @t_general, @now, NULL, @now, @t_general, '1970-01-01 00:00:00.000000', 'Y'),
('a1000000-0000-4000-8000-000000003302', 'a1000000-0000-4000-8000-000000000302', 'tenant_admin', @t_abc,     @now, NULL, @now, @t_abc,     '1970-01-01 00:00:00.000000', 'Y'),
('a1000000-0000-4000-8000-000000003303', 'a1000000-0000-4000-8000-000000000303', 'tenant_admin', @t_xyz,     @now, NULL, @now, @t_xyz,     '1970-01-01 00:00:00.000000', 'Y');

-- ---------------------------------------------------------------------------
-- 5) Seller applications - approved by the Super Admin
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `seller_application` (`id`, `party_id`, `tenant_id`, `status`, `rejection_reason`, `decided_by_party_id`, `applied_at`, `decided_at`) VALUES
('a1000000-0000-4000-8000-000000004201', 'a1000000-0000-4000-8000-000000000201', @t_general, 'approved', NULL, @super_admin, @now, @now),
('a1000000-0000-4000-8000-000000004202', 'a1000000-0000-4000-8000-000000000202', @t_general, 'approved', NULL, @super_admin, @now, @now),
('a1000000-0000-4000-8000-000000004203', 'a1000000-0000-4000-8000-000000000203', @t_general, 'approved', NULL, @super_admin, @now, @now);

COMMIT;
