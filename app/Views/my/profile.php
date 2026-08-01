<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<style>
  .profile-wrap{max-width:560px; padding:40px 24px 60px;}
  .profile-summary{display:flex; align-items:center; gap:16px; margin-bottom:var(--sp-6);}
  .profile-avatar{width:56px; height:56px; border-radius:50%; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; font-family:'Archivo',sans-serif; font-weight:800; font-size:20px; flex:none;}
  .profile-summary h1{font-size:20px; margin:0 0 4px;}
  .profile-summary .sub{font-size:13px; color:var(--ink-3);}
  .stat-row{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:var(--sp-6);}
  .stat-pill{flex:1; min-width:120px; background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:14px 16px;}
  .stat-pill .k{font-size:11px; color:var(--ink-3); font-weight:600; margin-bottom:4px;}
  .stat-pill .v{font-size:16px; font-weight:800;}
  .settings-group{margin-bottom:var(--sp-5);}
  .settings-group h3{font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:var(--ink-3); margin:0 0 var(--sp-2); font-family:'Inter',sans-serif; font-weight:700;}
  .settings-list{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); overflow:hidden;}
  .settings-list a{display:flex; align-items:center; justify-content:space-between; padding:14px 16px; font-size:13.5px; font-weight:600; border-bottom:1px solid var(--line-soft);}
  .settings-list a:last-child{border-bottom:none;}
  .settings-list a:hover{background:var(--line-soft);}
  .settings-list a .chev{color:var(--ink-3); font-weight:400;}
  .settings-list a.danger{color:#B5482F;}
  @media(max-width:640px){ .stat-pill{min-width:100px;} }
</style>
<main class="profile-wrap">
  <div class="profile-summary">
    <div class="profile-avatar"><?= esc(strtoupper(substr($party['mobile_number'] ?? '?', -2))) ?></div>
    <div>
      <h1>My Profile</h1>
      <div class="sub"><?= esc($party['mobile_number']) ?> · Member since <?= esc(substr($party['created_at'] ?? '', 0, 10)) ?></div>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat-pill"><div class="k"><?= tsx_term('Buyer') ?> Rating</div><div class="v">★ <?= esc($party['star_rating']) ?></div></div>
    <div class="stat-pill"><div class="k"><?= tsx_term('Seller') ?> Rating</div><div class="v">★ <?= esc($party['seller_star_rating']) ?></div></div>
    <div class="stat-pill"><div class="k">KYC Status</div><div class="v" style="font-size:13px; text-transform:capitalize;"><?= esc($party['kyc_status'] ?? 'Not started') ?></div></div>
    <div class="stat-pill"><div class="k">Last Login</div><div class="v" style="font-size:12.5px;"><?= $party['last_login_at'] ? esc(substr($party['last_login_at'], 0, 16)) : '—' ?></div></div>
  </div>

  <div class="settings-group">
    <h3>Account</h3>
    <div class="settings-list">
      <a href="/account/edit">Edit Account <span class="chev">→</span></a>
      <a href="/account/change-mpin">Change mPIN <span class="chev">→</span></a>
      <a href="/payout-bank">Payout Bank Details <span class="chev">→</span></a>
    </div>
  </div>

  <div class="settings-group">
    <h3>Activity</h3>
    <div class="settings-list">
      <a href="/my-bids">My Bids <span class="chev">→</span></a>
      <a href="/my-offers">My Offers <span class="chev">→</span></a>
      <a href="/my-purchases">My Purchases <span class="chev">→</span></a>
      <a href="/my-sales">My Sales <span class="chev">→</span></a>
    </div>
  </div>

  <div class="settings-group">
    <h3>Discovery</h3>
    <div class="settings-list">
      <a href="/my-favorites">My Favorites <span class="chev">→</span></a>
      <a href="/my-searches">Saved Searches <span class="chev">→</span></a>
      <a href="/search-history">Search History <span class="chev">→</span></a>
      <a href="/recommendations">Recommendations <span class="chev">→</span></a>
    </div>
  </div>

  <div class="settings-group">
    <div class="settings-list">
      <a href="/logout">Log Out <span class="chev">→</span></a>
      <a href="/account/delete" class="danger">Delete Account <span class="chev">→</span></a>
    </div>
  </div>
</main>
<?= $this->endSection() ?>
