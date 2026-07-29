<?php

namespace App\Libraries;

// BR-06/PR-06: "On request, the edge layer inspects the incoming Host
// header to match the tenant... injects the tenant's branding and
// inventory, displaying a white-label portal." Set once per request by
// TenantResolutionFilter, read by controllers (inventory scoping) and
// the shared layout (branding injection) — a plain static holder rather
// than CI4's Services container, matching this codebase's existing
// preference for direct instantiation over DI ceremony.
class TenantContext
{
    private static ?array $current = null;

    public static function set(?array $tenant): void
    {
        self::$current = $tenant;
    }

    public static function current(): ?array
    {
        return self::$current;
    }
}
