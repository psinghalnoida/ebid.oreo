<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Models\PartyModel;

// Runs globally (Config/Filters.php), unlike JwtAuthFilter: populates
// UserAuthContext from a Bearer token WHEN ONE IS PRESENT, but never
// rejects a request over a missing/invalid one. Needed because a PHP
// session naturally returns null for `session()->get('logged_in_party_id')`
// on an anonymous request — several still-unconverted controllers
// (Home, DiscoveryController, LiveTickerController, TenderController,
// InvoiceController, LotReachController, ChronicleController) read that
// same "viewer id, or null if anonymous" pattern for personalization,
// not as a hard auth gate, and have no per-route jwtAuth filter of
// their own. Without this, UserAuthContext would simply never get set
// for those routes at all, silently breaking their optional
// personalization rather than degrading to "anonymous" the way the old
// session-based code did.
class JwtOptionalAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return; // No token offered — proceed as anonymous, same as before.
        }

        $claims = UserAuthApiService::validateAccessToken($matches[1]);
        if (!$claims) {
            return; // Invalid/expired token — degrade to anonymous rather than 401.
        }

        $party = (new PartyModel())->findActiveById($claims['sub']);
        if ($party) {
            UserAuthContext::set($party, $claims['roles'] ?? ['party']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
