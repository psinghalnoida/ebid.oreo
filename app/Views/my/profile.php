<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Profile</h1>
  <div style="border:1px solid var(--line); border-radius:12px; padding:20px; margin-top:16px;">
    <p style="font-size:13px; color:var(--ink-3);">Mobile Number</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;"><?= esc($party['mobile_number']) ?></p>

    <p style="font-size:13px; color:var(--ink-3);">Buyer Rating</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;">★ <?= esc($party['star_rating']) ?> / 5.0</p>

    <p style="font-size:13px; color:var(--ink-3);">Seller Rating</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;">★ <?= esc($party['seller_star_rating']) ?> / 5.0</p>

    <p style="font-size:13px; color:var(--ink-3);">KYC Status</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;"><?= esc($party['kyc_status'] ?? 'Not started') ?></p>

    <p style="font-size:13px; color:var(--ink-3);">Member Since</p>
    <p style="font-size:13px; margin:0 0 16px;"><?= esc(substr($party['created_at'] ?? '', 0, 10)) ?></p>

    <p style="font-size:13px; color:var(--ink-3);">Last Login</p>
    <p style="font-size:13px; margin:0;"><?= $party['last_login_at'] ? esc(substr($party['last_login_at'], 0, 16)) : '—' ?></p>
  </div>

  <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
    <a href="/account/edit" class="btn btn-ghost" style="font-size:12px;">Edit Account</a>
    <a href="/account/change-mpin" class="btn btn-ghost" style="font-size:12px;">Change mPIN</a>
    <a href="/payout-bank" class="btn btn-ghost" style="font-size:12px;">Payout Bank Details</a>
    <a href="/my-bids" class="btn btn-ghost" style="font-size:12px;">My Bids</a>
    <a href="/my-offers" class="btn btn-ghost" style="font-size:12px;">My Offers</a>
    <a href="/my-purchases" class="btn btn-ghost" style="font-size:12px;">My Purchases</a>
    <a href="/my-sales" class="btn btn-ghost" style="font-size:12px;">My Sales</a>
    <a href="/account/delete" class="btn btn-ghost" style="font-size:12px; color:#B5482F;">Delete Account</a>
  </div>

  <a href="/logout" class="btn btn-ghost" style="margin-top:20px; display:inline-block;">Log Out</a>
</main>
<?= $this->endSection() ?>
