<?php

namespace App\Libraries;

// Carries the authenticated tenant/credential identity from
// ApiAuthFilter::before() to the controller. Plain request-scoped
// static state is safe here: each HTTP request is its own PHP process
// (php-fpm/spark serve), so nothing leaks between requests.
class ApiRequestContext
{
    private static ?string $tenantId = null;
    private static ?string $clientId = null;
    private static ?string $credentialId = null;

    public static function set(string $tenantId, string $clientId, string $credentialId): void
    {
        self::$tenantId = $tenantId;
        self::$clientId = $clientId;
        self::$credentialId = $credentialId;
    }

    public static function tenantId(): ?string
    {
        return self::$tenantId;
    }

    public static function clientId(): ?string
    {
        return self::$clientId;
    }

    public static function credentialId(): ?string
    {
        return self::$credentialId;
    }
}
