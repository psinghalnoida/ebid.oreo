<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\TenantContext;
use App\Models\TenantModel;

// BR-06/PR-06: "On request, the edge layer inspects the incoming Host
// header to match the tenant... injects the tenant's branding and
// inventory, displaying a white-label portal." Global, runs on every
// request, never blocks — no match (the platform's own domain, localhost
// during dev, or an unmapped host) falls through to the ordinary
// federated, platform-wide experience unchanged. This is deliberately
// the ONLY behavior change: it never restricts what a buyer can reach,
// per BR-06's "buyers are federated globally... across every tenant
// domain" — a resolved tenant only affects branding + which inventory
// the landing/browse pages show by default, not authorization anywhere.
class TenantResolutionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $host = strtolower((string) $request->getServer('HTTP_HOST'));
        if ($host === '') {
            return;
        }
        $host = explode(':', $host)[0]; // strip a dev port like :8080

        $tenantModel = new TenantModel();

        // Custom domain: exact match against the tenant's own domain.
        $tenant = $tenantModel->where('custom_domain', $host)->first();

        // Subdomain: {label}.{platform's own root domain}, derived from
        // app.baseURL rather than hardcoded, so this works in dev
        // (localhost) and prod without a config split.
        if (!$tenant) {
            $platformHost = strtolower((string) parse_url(config('App')->baseURL, PHP_URL_HOST));
            if ($platformHost !== '' && str_ends_with($host, '.' . $platformHost)) {
                $label = substr($host, 0, -(strlen($platformHost) + 1));
                if ($label !== '' && !str_contains($label, '.')) {
                    $tenant = $tenantModel->where('subdomain', $label)->first();
                }
            }
        }

        if ($tenant && $tenant['suspended_at'] === null) {
            TenantContext::set($tenant);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed.
    }
}
