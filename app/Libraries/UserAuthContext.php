<?php

namespace App\Libraries;

// Carries the authenticated party (set by JwtAuthFilter::before()) to the
// controller. Same request-scoped static state pattern as
// ApiRequestContext (Tenant API): safe because each HTTP request is its
// own PHP process (php-fpm/spark serve), so nothing leaks between requests.
class UserAuthContext
{
    private static ?array $party = null;
    private static array $roles = ['party'];

    // $roles is the calling token's own 'roles' claim (default ['party']
    // for a plain access token) — lets a controller with dual
    // authorization (e.g. PayoutReviewController/RatingReviewController's
    // "Tenant Admin OR Super Admin") tell whether THIS token was actually
    // issued through the separate, TOTP/email-OTP-verified Super Admin
    // login (SuperAdminAuthApiController), not just whether the account
    // happens to hold the super_admin DB role — the same boundary
    // SuperAdminFilter's session marker enforced.
    public static function set(array $party, array $roles = ['party']): void
    {
        self::$party = $party;
        self::$roles = $roles;
    }

    public static function party(): ?array
    {
        return self::$party;
    }

    public static function partyId(): ?string
    {
        return self::$party['id'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        return in_array($role, self::$roles, true);
    }
}
