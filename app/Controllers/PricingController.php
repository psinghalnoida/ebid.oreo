<?php

namespace App\Controllers;

// Serves the standalone TradeSphereX pricing page (tenant subscription
// tiers + Success Fee calculator) at the site's clean /pricing URL.
// The page is a complete, self-contained document (own fonts, styles,
// and calculator script) provided ready-made -- served verbatim rather
// than re-themed into the app's shared layouts/main, since doing so
// would alter a page that was handed over as a finished artifact.
// Canonical file: public/pricing.html (also directly reachable there).
class PricingController extends BaseController
{
    public function index()
    {
        $path = FCPATH . 'pricing.html';
        if (!is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->response->setContentType('text/html')->setBody(file_get_contents($path));
    }
}
