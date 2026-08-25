<?php

namespace App\Libraries;

// Carries the authenticated party (set by JwtAuthFilter::before()) to the
// controller. Same request-scoped static state pattern as
// ApiRequestContext (Tenant API): safe because each HTTP request is its
// own PHP process (php-fpm/spark serve), so nothing leaks between requests.
class UserAuthContext
{
    private static ?array $party = null;

    public static function set(array $party): void
    {
        self::$party = $party;
    }

    public static function party(): ?array
    {
        return self::$party;
    }

    public static function partyId(): ?string
    {
        return self::$party['id'] ?? null;
    }
}
