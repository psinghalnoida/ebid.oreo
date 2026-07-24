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
</head>
<body>

<header class="app-head">
  <div class="head-inner">
    <div class="brand">eBid<span>Hub</span></div>
    <nav class="tabs">
      <a href="/" class="<?= (uri_string() === '' || uri_string() === '/') ? 'active' : '' ?>">Marketplace</a>
      <a href="/browse" class="<?= (strpos(uri_string(), 'browse') !== false) ? 'active' : '' ?>">Browse</a>
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

<?= $this->renderSection('content') ?>

<footer>
  <div class="foot-inner">
    <span>&copy; eBid Hub</span>
    <div class="foot-links">
      <a href="/trust-support">Trust &amp; Support</a>
      <a href="#">Terms</a>
      <a href="#">Privacy</a>
    </div>
  </div>
</footer>

</body>
</html>
