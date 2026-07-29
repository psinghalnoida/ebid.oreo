<?php
  // BR-06/PR-06: "injects the tenant's branding ... displaying a
  // white-label portal" — read once here since every page extends this
  // layout, rather than threading it through every controller's own
  // view data.
  $__tenant = \App\Libraries\TenantContext::current();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($title ?? 'eBid Hub') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#FCFCFA; --card:#FFFFFF; --ink:#141414; --ink-2:#5C5C5C; --ink-3:#9A9A93;
    --emerald:#0F5C4C; --emerald-soft:#E3F0EB; --emerald-deep:#0B4438;
    --amber:#D98C4A; --amber-soft:#FBEADA;
    --line:#EDEDE9; --line-soft:#F4F4F1;
    --radius:16px; --radius-pill:100px;
  }
  *{box-sizing:border-box;}
  body{margin:0; background:var(--bg); color:var(--ink); font-family:'Sora',sans-serif; -webkit-font-smoothing:antialiased;}
  a{color:inherit; text-decoration:none;}

  header.app-head{position:sticky; top:0; z-index:60; background:rgba(252,252,250,0.9); backdrop-filter:blur(10px); border-bottom:1px solid var(--line);}
  .head-inner{max-width:1240px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:16px 24px;}
  .brand{font-weight:800; font-size:19px; letter-spacing:-0.4px;}
  .brand span{color:var(--emerald);}
  nav.tabs{display:flex; gap:4px; background:var(--line-soft); padding:4px; border-radius:var(--radius-pill);}
  nav.tabs a{font-size:13px; font-weight:600; padding:8px 18px; border-radius:var(--radius-pill); color:var(--ink-2);}
  nav.tabs a.active{color:#fff; background:var(--ink);}
  .btn{font-weight:700; font-size:13.5px; padding:10px 20px; border-radius:var(--radius-pill); cursor:pointer; border:1px solid transparent;}
  .btn-emerald{background:var(--emerald); color:#fff;}
  .btn-ghost{background:transparent; color:var(--ink); border-color:var(--line);}

  main{max-width:1240px; margin:0 auto; padding:0 24px;}

  footer{border-top:1px solid var(--line); padding:32px 0 44px; font-size:12.5px; color:var(--ink-3); margin-top:60px;}
  .foot-inner{max-width:1240px; margin:0 auto; padding:0 24px; display:flex; justify-content:space-between; align-items:center;}
  .foot-links{display:flex; gap:24px;}
</style>
<?php if ($__tenant && !empty($__tenant['branding_primary_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $__tenant['branding_primary_color'])): ?>
<style>
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
        eBid<span>Hub</span>
      <?php endif; ?>
    </div>
    <nav class="tabs">
      <a href="/" class="<?= (uri_string() === '' || uri_string() === '/') ? 'active' : '' ?>">Marketplace</a>
      <a href="/browse" class="<?= (strpos(uri_string(), 'browse') !== false) ? 'active' : '' ?>">Browse</a>
      <a href="/tenants" class="<?= (uri_string() === 'tenants') ? 'active' : '' ?>">Sell</a>
      <a href="/trust-support" class="<?= (strpos(uri_string(), 'trust-support') !== false) ? 'active' : '' ?>">Trust & Support</a>
    </nav>
    <div>
      <?php if (session()->get('logged_in_party_id')): ?>
        <a href="/my-listings" class="btn btn-ghost">My Listings</a>
        <a href="/my-activity" class="btn btn-ghost">My Activity</a>
        <a href="/profile" class="btn btn-ghost">Profile</a>
        <a href="/logout" class="btn btn-ghost">Log Out</a>
      <?php else: ?>
        <a href="/login" class="btn btn-ghost">Log In</a>
      <?php endif; ?>
      <a href="/listings/create" class="btn btn-emerald">List an Asset</a>
    </div>
  </div>
</header>

<?php if (session()->get('logged_in_party_id')): ?>
<aside id="live-ticker" style="position:fixed; right:0; top:62px; bottom:0; width:260px; background:#fff; border-left:1px solid var(--line); overflow-y:auto; padding:16px; z-index:50;">
  <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px; margin:0 0 12px;">Live Ticker</p>

  <div id="ticker-own-bids"></div>
  <p id="ticker-own-bids-empty" style="font-size:12px; color:var(--ink-3); display:none;">No active bids right now.</p>

  <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px; margin:20px 0 12px;">Sales of Interest</p>
  <div id="ticker-interest-matches"></div>
  <p style="font-size:11px; margin-top:16px;"><a href="/preferences" style="color:var(--emerald);">Tune your preferences →</a></p>
</aside>
<style>
  main { margin-right: 260px; }
  @media (max-width: 900px) { #live-ticker { display: none; } main { margin-right: 0; } }
  .ticker-item { border-bottom: 1px solid var(--line); padding: 8px 0; font-size: 12px; }
  .ticker-item .amount { font-weight: 700; }
  .ticker-item .h1 { color: var(--emerald); }
</style>
<script>
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
    };
    socket.onerror = function () { /* sidecar unreachable — ticker just doesn't update live, page still works */ };
  })();
</script>
<?php endif; ?>

<?= $this->renderSection('content') ?>

<footer>
  <div class="foot-inner">
    <span>&copy; eBid Hub</span>
    <div class="foot-links">
      <a href="/trust-support">Trust &amp; Support</a>
      <a href="<?= ($__tenant && !empty($__tenant['terms_url'])) ? esc($__tenant['terms_url']) : '/terms' ?>">Terms</a>
      <a href="/privacy">Privacy</a>
    </div>
  </div>
</footer>

</body>
</html>
