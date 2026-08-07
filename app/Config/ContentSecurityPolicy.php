<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // -------------------------------------------------------------------------
    // Broadbrush CSP management
    // -------------------------------------------------------------------------

    /**
     * Default CSP report context
     */
    public bool $reportOnly = false;

    /**
     * Specifies a URL where a browser will send reports
     * when a content security policy is violated.
     */
    public ?string $reportURI = null;

    /**
     * Specifies a reporting endpoint to which violation reports ought to be sent.
     */
    public ?string $reportTo = null;

    /**
     * Instructs user agents to rewrite URL schemes, changing
     * HTTP to HTTPS. This directive is for websites with
     * large numbers of old URLs that need to be rewritten.
     */
    public bool $upgradeInsecureRequests = false;

    // -------------------------------------------------------------------------
    // CSP DIRECTIVES SETTINGS
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Will default to `'self'` if not overridden
     *
     * @var list<string>|string|null
     */
    public $defaultSrc;

    /**
     * Lists allowed scripts' URLs. No external <script src="..."> anywhere
     * in the app (grep-verified) -- 'self' only.
     *
     * @var list<string>|string
     */
    public $scriptSrc = 'self';

    /**
     * Specifies valid sources for JavaScript <script> elements.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcElem = 'self';

    /**
     * Specifies valid sources for JavaScript inline event
     * handlers and JavaScript URLs.
     *
     * 'unsafe-inline' is a real, deliberate tradeoff, not an oversight:
     * listing/create.php and listing/show.php use a handful of inline
     * onclick/onchange/onsubmit handlers (grep-verified, 4 occurrences
     * across 2 real app views). Rewriting those to addEventListener()
     * is a separate, larger frontend pass -- not bundled into this one.
     *
     * @var list<string>|string
     */
    public array|string $scriptSrcAttr = 'unsafe-inline';

    /**
     * Lists allowed stylesheets' URLs. Google Fonts' CSS host, needed for
     * the Archivo/Inter/IBM Plex Mono stylesheet link in layouts/main.php.
     *
     * @var list<string>|string
     */
    public $styleSrc = ['self', 'https://fonts.googleapis.com'];

    /**
     * Specifies valid sources for stylesheets <link> elements.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcElem = ['self', 'https://fonts.googleapis.com'];

    /**
     * Specifies valid sources for stylesheets inline
     * style attributes and `<style>` elements.
     *
     * 'unsafe-inline' is a real, deliberate tradeoff, not an oversight:
     * this entire app is built on inline style="..." attributes -- there
     * is no separate stylesheet to fall back to. Locking this down
     * without breaking every page's visual styling would mean migrating
     * ~75+ view files off inline styles first; that's a real, separate
     * frontend project, not something to fold into a CSP pass.
     *
     * @var list<string>|string
     */
    public array|string $styleSrcAttr = 'unsafe-inline';

    /**
     * Defines the origins from which images can be loaded. 'data:' is
     * needed for the inline SVG watermarks used as data: URIs (e.g. the
     * landing page's empty-state shield icon).
     *
     * @var list<string>|string
     */
    public $imageSrc = ['self', 'data:'];

    /**
     * Restricts the URLs that can appear in a page's `<base>` element.
     *
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    public $baseURI;

    /**
     * Lists the URLs for workers and embedded frame contents
     *
     * @var list<string>|string
     */
    public $childSrc = 'self';

    /**
     * Limits the origins that you can connect to (via XHR,
     * WebSockets, and EventSource). 'ws:'/'wss:' cover the D-42 real-time
     * WebSocket sidecar (listing/show.php + layouts/main.php both open a
     * WebSocket to the app's own host on a different port -- 8081 by
     * default -- which is a different origin by browser rules, so 'self'
     * alone would not cover it; scheme-only keeps this working whether
     * the sidecar is exposed directly or proxied through Nginx per the
     * README's two deployment options).
     *
     * @var list<string>|string
     */
    public $connectSrc = ['self', 'ws:', 'wss:'];

    /**
     * Specifies the origins that can serve web fonts. Google Fonts' actual
     * font-file host (as opposed to fonts.googleapis.com, which serves the
     * CSS that references this host).
     *
     * @var list<string>|string
     */
    public $fontSrc = 'https://fonts.gstatic.com';

    /**
     * Lists valid endpoints for submission from `<form>` tags.
     *
     * @var list<string>|string
     */
    public $formAction = 'self';

    /**
     * Specifies the sources that can embed the current page.
     * This directive applies to `<frame>`, `<iframe>`, `<embed>`,
     * and `<applet>` tags. This directive can't be used in
     * `<meta>` tags and applies only to non-HTML resources.
     *
     * 'none' -- nothing in this app is meant to be iframed by another
     * site; blocks clickjacking (also backed by secureheaders' own
     * X-Frame-Options: SAMEORIGIN, belt-and-braces).
     *
     * @var list<string>|string|null
     */
    public $frameAncestors = 'none';

    /**
     * The frame-src directive restricts the URLs which may
     * be loaded into nested browsing contexts.
     *
     * @var list<string>|string|null
     */
    public $frameSrc;

    /**
     * Restricts the origins allowed to deliver video and audio.
     *
     * @var list<string>|string|null
     */
    public $mediaSrc;

    /**
     * Allows control over Flash and other plugins. Nothing in this app
     * uses <object>/<embed>/<applet> (grep-verified) -- locked to 'none'.
     *
     * @var list<string>|string
     */
    public $objectSrc = 'none';

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc;

    /**
     * @var list<string>|string
     */
    public array|string $workerSrc = [];

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce placeholder for style tags.
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Nonce placeholder for script tags.
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Replace nonce tag automatically?
     */
    public bool $autoNonce = true;
}
