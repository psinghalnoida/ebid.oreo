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
    <p style="font-size:15px; font-weight:600; margin:0;"><?= esc($party['kyc_status'] ?? 'Not started') ?></p>
  </div>
  <a href="/logout" class="btn btn-ghost" style="margin-top:20px; display:inline-block;">Log Out</a>
</main>
<?= $this->endSection() ?>
