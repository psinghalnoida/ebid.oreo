<?php
  // BR-06/PR-06: "injects the tenant's branding ... displaying a
  // white-label portal" — read once here since every page extends this
  // layout, rather than threading it through every controller's own
  // view data.
  $__tenant = \App\Libraries\TenantContext::current();

  // D-102 follow-up: a real Tenant Admin, once promoted, had no
  // discoverable way back to their own dashboard from anywhere in the
  // app -- every sub-page (Verification Console, Media Waiver, Sellers,
  // Billing, API Access) links back to it, but nothing linked to it in
  // the first place. Computed once per page load, gated on an actual
  // logged-in session, same tradeoff already made for $__tenant above.
  $__adminTenantId = null;
  if ($loggedInPartyId = session()->get('logged_in_party_id')) {
    $__administeredTenantIds = (new \App\Models\PartyRoleModel())->findAdministeredTenantIds($loggedInPartyId);
    $__adminTenantId = $__administeredTenantIds[0] ?? null;
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($title ?? 'AdwitiX') ?></title>
<link rel="icon" type="image/jpeg" href="/images/brand/adwitix-shield.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style {csp-style-nonce}>
  /* BR-68: Visual Identity — Ink/Ink Soft/Paper/Card/Line/Rust/Teal/Amber
     tokens, Archivo/Inter/IBM Plex Mono typography. Variable names kept
     stable (--emerald etc.) since 80+ views already reference them —
     only the values change, matching the same approach BR-67 took for
     variable/field names vs. rendered text.

     D-101: extended with a spacing scale, elevation (shadow) scale, and
     two brand accent colors lifted from the AdwitiX shield/logo (navy,
     gold) for hero/illustrative treatments — Rust/Amber stay the
     functional/interactive color, navy/gold are decorative accents
     only, not reused for buttons or status states. Also introduces a
     small set of shared component classes (.field, .card, .section,
     .grid-2/3/4, .badge, .table-wrap) so pages can stop hand-rolling
     the same inline styles, plus real breakpoints (900px, 640px) —
     previously there was exactly one @media rule in this entire file. */
  :root{
    --bg:#EEF0EA; --card:#FFFFFF; --ink:#1C1F26; --ink-2:#5B5F56; --ink-3:#9DA099;
    --emerald:#B85C2C; --emerald-soft:#F3E3D6; --emerald-deep:#934A23;
    --amber:#E3A93C; --amber-soft:#FBF2E2;
    --navy:#1B2A5B; --navy-soft:#E7E9F2;
    --gold:#C9A356; --gold-soft:#F6EFDE;
    --line:#D8DACE; --line-soft:#E5E7DF;
    --radius:16px; --radius-lg:22px; --radius-pill:100px;
    --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:24px; --sp-6:32px; --sp-7:48px; --sp-8:64px;
    --shadow-sm:0 2px 6px -2px rgba(28,31,38,0.10);
    --shadow-md:0 12px 28px -12px rgba(28,31,38,0.18);
    --shadow-lg:0 24px 48px -20px rgba(28,31,38,0.20);
  }
  *{box-sizing:border-box;}
  body{margin:0; background:var(--bg); color:var(--ink); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased;}
  h1,h2,h3{font-family:'Archivo',sans-serif;}
  a{color:inherit; text-decoration:none;}

  header.app-head{position:sticky; top:0; z-index:60; background:rgba(238,240,234,0.92); backdrop-filter:blur(10px); border-bottom:1px solid var(--line);}
  .head-inner{max-width:1240px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:14px 24px; gap:16px;}
  .brand{font-family:'Archivo',sans-serif; font-weight:800; font-size:19px; letter-spacing:-0.4px; display:flex; align-items:center; gap:8px;}
  .brand img.brand-icon{width:26px; height:26px; border-radius:6px; vertical-align:middle;}
  .brand span{color:var(--emerald);}
  nav.tabs{display:flex; gap:4px; background:var(--line-soft); padding:4px; border-radius:var(--radius-pill);}
  nav.tabs a{font-size:13px; font-weight:600; padding:8px 18px; border-radius:var(--radius-pill); color:var(--ink-2);}
  nav.tabs a.active{color:#fff; background:var(--ink);}
  .head-actions{display:flex; align-items:center; gap:8px;}
  .btn{font-weight:700; font-size:13.5px; padding:10px 20px; border-radius:var(--radius-pill); cursor:pointer; border:1px solid transparent; display:inline-block;}
  .btn-emerald{background:var(--emerald); color:#fff;}
  .btn-ghost{background:transparent; color:var(--ink); border-color:var(--line);}

  /* Mobile nav: hidden by default, JS-toggled via .nav-open on <body>. */
  .nav-toggle{display:none; width:40px; height:40px; border-radius:10px; border:1px solid var(--line); background:var(--card); cursor:pointer; flex:none; align-items:center; justify-content:center;}
  .nav-toggle span, .nav-toggle span::before, .nav-toggle span::after{content:''; display:block; width:18px; height:2px; background:var(--ink); border-radius:2px; position:relative; transition:transform 0.15s ease, opacity 0.15s ease;}
  .nav-toggle span::before{position:absolute; top:-6px;}
  .nav-toggle span::after{position:absolute; top:6px;}
  body.nav-open .nav-toggle span{background:transparent;}
  body.nav-open .nav-toggle span::before{transform:translateY(6px) rotate(45deg);}
  body.nav-open .nav-toggle span::after{transform:translateY(-6px) rotate(-45deg);}

  main{max-width:1240px; margin:0 auto; padding:0 24px;}

  /* Shared components */
  .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:var(--sp-5); box-shadow:var(--shadow-sm);}
  .section{padding:var(--sp-7) 0; border-top:1px solid var(--line);}
  .grid-2{display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-5);}
  .grid-3{display:grid; grid-template-columns:repeat(3,1fr); gap:var(--sp-4);}
  .grid-4{display:grid; grid-template-columns:repeat(4,1fr); gap:var(--sp-4);}
  .field{margin:0 0 var(--sp-4);}
  .field label{display:block; font-size:12px; color:var(--ink-3); font-weight:600; margin-bottom:var(--sp-2);}
  .field input, .field select, .field textarea{display:block; width:100%; padding:12px; border:1px solid var(--line); border-radius:10px; font-family:inherit; font-size:14px; background:var(--card); color:var(--ink);}
  .field .hint{font-size:11.5px; color:var(--ink-3); margin-top:6px; line-height:1.5;}
  .badge{display:inline-block; font-size:11px; font-weight:700; padding:5px 12px; border-radius:var(--radius-pill); background:var(--line-soft); color:var(--ink-2);}
  .badge-emerald{background:var(--emerald-soft); color:var(--emerald-deep);}
  .badge-amber{background:var(--amber-soft); color:#9C5B1F;}
  .badge-navy{background:var(--navy-soft); color:var(--navy);}
  .table-wrap{overflow-x:auto; -webkit-overflow-scrolling:touch;}
  .table-wrap table{min-width:520px;}

  footer{border-top:1px solid var(--line); padding:32px 0 44px; font-size:12.5px; color:var(--ink-3); margin-top:60px;}
  .foot-inner{max-width:1240px; margin:0 auto; padding:0 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;}
  .foot-links{display:flex; gap:24px; flex-wrap:wrap;}

  @media (max-width:900px){
    .nav-toggle{display:inline-flex;}
    nav.tabs, .head-actions{display:none;}
    .head-inner{flex-wrap:wrap;}
    /* Both panels become full-width, normal-flow flex children of
       .head-inner (which wraps) — this stacks them vertically below
       the brand/toggle row in DOM order, rather than both trying to
       absolutely-position at the same spot and overlapping. */
    body.nav-open nav.tabs, body.nav-open .head-actions{
      display:flex; flex-direction:column; align-items:stretch; gap:6px;
      width:100%; order:10; background:transparent; border-radius:0; padding:0;
      border-top:1px solid var(--line); margin-top:12px; padding-top:12px;
    }
    body.nav-open .head-actions{border-top:none; margin-top:0; padding-top:6px;}
    body.nav-open nav.tabs a, body.nav-open .head-actions a{border-radius:10px; padding:12px 14px; text-align:left; width:100%;}
    body.nav-open nav.tabs a.active{background:var(--line-soft); color:var(--ink);}
    main{padding:0 16px;}
    .grid-2, .grid-3, .grid-4{grid-template-columns:1fr;}
  }
  @media (max-width:640px){
    main{padding:0 14px;}
    .card{padding:var(--sp-4);}
  }
</style>
<?php if ($__tenant && !empty($__tenant['branding_primary_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $__tenant['branding_primary_color'])): ?>
<style {csp-style-nonce}>
  /* BR-06: white-label brand color override for this tenant's domain —
     derived from their one stored color via color-mix() rather than
     hand-rolled hex math, since no color library exists in this codebase.
     The value is validated as a strict 6-digit hex code (both here and on
     save in TenantController::editSubmit) rather than run through
     esc(..., 'css'): CI4's CSS-context escaping hex-escapes the '#',
     which CSS then tokenizes as an identifier rather than a hash-color
     token, silently invalidating color-mix() and losing the branding. */
  :root{
    --emerald: <?= $__tenant['branding_primary_color'] ?>;
    --emerald-deep: color-mix(in srgb, <?= $__tenant['branding_primary_color'] ?> 80%, black);
    --emerald-soft: color-mix(in srgb, <?= $__tenant['branding_primary_color'] ?> 15%, white);
  }
</style>
<?php endif; ?>
</head>
<body>

<header class="app-head">
  <div class="head-inner">
    <div class="brand">
      <?php if ($__tenant): ?>
        <?php if (!empty($__tenant['branding_logo_url'])): ?>
          <img src="<?= esc($__tenant['branding_logo_url']) ?>" alt="<?= esc($__tenant['name']) ?>" style="height:26px; vertical-align:middle;">
        <?php else: ?>
          <?= esc($__tenant['name']) ?>
        <?php endif; ?>
      <?php else: ?>
        <img class="brand-icon" src="/images/brand/adwitix-shield.jpg" alt="">
        Adwiti<span>X</span>
      <?php endif; ?>
    </div>
    <nav class="tabs">
      <a href="/" class="<?= (uri_string() === '' || uri_string() === '/') ? 'active' : '' ?>">Marketplace</a>
      <a href="/browse" class="<?= (strpos(uri_string(), 'browse') !== false) ? 'active' : '' ?>">Browse</a>
      <a href="/tenants" class="<?= (uri_string() === 'tenants') ? 'active' : '' ?>">Sell</a>
      <a href="/trust-support" class="<?= (strpos(uri_string(), 'trust-support') !== false) ? 'active' : '' ?>">Trust & Support</a>
    </nav>
    <div class="head-actions">
      <?php if (session()->get('logged_in_party_id')): ?>
        <a href="/my-listings" class="btn btn-ghost">My Listings</a>
        <a href="/my-activity" class="btn btn-ghost">My Activity</a>
        <a href="/kyc" class="btn btn-ghost">KYC</a>
        <?php if ($__adminTenantId): ?>
          <a href="/tenants/<?= esc($__adminTenantId) ?>/dashboard" class="btn btn-ghost"><?= tsx_term('Tenant Admin') ?> Console</a>
        <?php endif; ?>
        <a href="/profile" class="btn btn-ghost">Profile</a>
        <a href="/logout" class="btn btn-ghost">Log Out</a>
      <?php else: ?>
        <a href="/login" class="btn btn-ghost">Log In</a>
      <?php endif; ?>
      <a href="/listings/create" class="btn btn-emerald">List an Asset</a>
    </div>
    <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle menu"><span></span></button>
  </div>
</header>
<script {csp-script-nonce}>
  (function () {
    var toggle = document.getElementById('navToggle');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('nav-open')) return;
      if (e.target === toggle || toggle.contains(e.target)) return;
      if (e.target.closest('nav.tabs, .head-actions')) return;
      document.body.classList.remove('nav-open');
    });
  })();
</script>

<?php if (session()->get('logged_in_party_id')): ?>
<aside id="live-ticker" style="position:fixed; right:0; top:62px; bottom:0; width:260px; background:#fff; border-left:1px solid var(--line); overflow-y:auto; padding:16px; z-index:50;">
  <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px; margin:0 0 12px;">Live Ticker</p>

  <div id="ticker-own-bids"></div>
  <p id="ticker-own-bids-empty" style="font-size:12px; color:var(--ink-3); display:none;">No active bids right now.</p>

  <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px; margin:20px 0 12px;">Sales of Interest</p>
  <div id="ticker-interest-matches"></div>
  <p style="font-size:11px; margin-top:16px;"><a href="/preferences" style="color:var(--emerald);">Tune your preferences →</a></p>
</aside>
<style {csp-style-nonce}>
  main { margin-right: 260px; }
  @media (max-width: 900px) { #live-ticker { display: none; } main { margin-right: 0; } }
  .ticker-item { border-bottom: 1px solid var(--line); padding: 8px 0; font-size: 12px; }
  .ticker-item .amount { font-weight: 700; }
  .ticker-item .h1 { color: var(--emerald); }
</style>
<script {csp-script-nonce}>
  (function () {
    const partyId = <?= json_encode(session()->get('logged_in_party_id')) ?>;

    function renderOwnBids(bids) {
      const container = document.getElementById('ticker-own-bids');
      const empty = document.getElementById('ticker-own-bids-empty');
      if (!bids.length) { container.innerHTML = ''; empty.style.display = 'block'; return; }
      empty.style.display = 'none';
      container.innerHTML = bids.map(function (b) {
        const isH1 = b.standing === 'h1';
        return '<div class="ticker-item"><a href="/listings/' + b.listing_id + '" style="color:inherit; text-decoration:none;">' +
          '<div>' + b.category + ' <span style="color:var(--ink-3); font-size:10px;">' + b.sale_format.toUpperCase() + '</span></div>' +
          '<div class="amount">₹' + Number(b.amount).toLocaleString('en-IN') + ' <span class="' + (isH1 ? 'h1' : '') + '">' + b.standing.toUpperCase() + '</span></div>' +
          '</a></div>';
      }).join('');
    }

    function renderInterestMatches(matches) {
      const container = document.getElementById('ticker-interest-matches');
      if (!matches.length) { container.innerHTML = '<p style="font-size:12px; color:var(--ink-3);">No matches yet — set your preferences.</p>'; return; }
      container.innerHTML = matches.map(function (m) {
        const price = m.current_price || m.reserve_value || m.expected_value;
        return '<div class="ticker-item"><a href="/listings/' + m.listing_id + '" style="color:inherit; text-decoration:none;">' +
          '<div>' + m.category + '</div><div class="amount">₹' + Number(price).toLocaleString('en-IN') + '</div></a></div>';
      }).join('');
    }

    fetch('/ticker-feed').then(function (r) { return r.json(); }).then(function (data) {
      renderOwnBids(data.ownBids);
      renderInterestMatches(data.interestMatches);
    });

    // BR-48: live updates via the buyer-scoped WebSocket room — a
    // single persistent connection watching every auction this buyer
    // cares about, not one connection per auction.
    if (!partyId) return;
    const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    let socket;
    try {
      socket = new WebSocket(wsProtocol + '//' + window.location.hostname + ':8081/ws?buyerId=' + partyId);
    } catch (e) { return; }

    socket.onmessage = function (event) {
      const msg = JSON.parse(event.data);
      if (msg.event === 'ticker_bid_update') {
        // Simplest correct refresh: re-fetch the feed rather than
        // trying to patch individual DOM rows — the ticker is small
        // enough that this stays cheap, and avoids subtle state bugs
        // from partial client-side merging.
        fetch('/ticker-feed').then(function (r) { return r.json(); }).then(function (data) {
          renderOwnBids(data.ownBids);
          renderInterestMatches(data.interestMatches);
        });
      }

      // D-108: this same per-party channel also carries a seller's
      // own incoming Buy-Now offers (RealtimeBroadcastService::
      // broadcastToBuyer() targets any party, not literally only
      // buyers — reused as-is rather than opening a second
      // connection). This layout has no listing-page-specific DOM to
      // update, so it relays the event for whichever page cares
      // (listing/show.php listens for this when the viewer owns that
      // specific listing).
      if (msg.event === 'offer_received') {
        window.dispatchEvent(new CustomEvent('ebidhub:offer_received', { detail: msg.data }));
      }
    };
    socket.onerror = function () { /* sidecar unreachable — ticker just doesn't update live, page still works */ };
  })();
</script>
<?php endif; ?>

<?= $this->renderSection('content') ?>

<footer>
  <div class="foot-inner">
    <span>&copy; AdwitiX</span>
    <div class="foot-links">
      <a href="/trust-support">Trust &amp; Support</a>
      <a href="/pricing">Pricing</a>
      <a href="<?= ($__tenant && !empty($__tenant['terms_url'])) ? esc($__tenant['terms_url']) : '/terms' ?>">Terms</a>
      <a href="/privacy">Privacy</a>
    </div>
  </div>
</footer>

</body>
</html>
