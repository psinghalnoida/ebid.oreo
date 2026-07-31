<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Delist <?= tsx_term('Seller') ?> — Confirmed Fraud</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    BR-38 — this is a platform-wide, permanent action, distinct from a <?= tsx_term('Tenant Admin') ?>'s <?= strtolower(tsx_term('Tenant')) ?>-scoped suspension.
    It immediately suspends every active <?= strtolower(tsx_term('Listing')) ?> this <?= strtolower(tsx_term('Seller')) ?> has across every <?= strtolower(tsx_term('Tenant')) ?> and blocks all future <?= strtolower(tsx_term('Listing')) ?>
    creation. Reserved strictly for a confirmed fraud finding — never use for ordinary poor ratings or disputes.
  </p>

  <form method="post" action="/admin/delist-seller" style="margin-top:24px;">
    <label style="font-size:12px; color:var(--ink-3);"><?= tsx_term('Seller') ?>'s Mobile Number</label>
    <input type="text" name="mobile_number" required placeholder="+919876543210"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Confirmed Fraud Reason (required, logged)</label>
    <textarea name="confirmed_fraud_reason" required rows="4"
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <button type="submit" class="btn" style="width:100%; background:#B5482F; color:#fff; border:none; padding:12px; border-radius:10px; font-weight:600;">
      Delist This <?= tsx_term('Seller') ?> Permanently
    </button>
  </form>
</main>
<?= $this->endSection() ?>
