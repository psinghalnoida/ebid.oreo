<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:22px;">Payout Bank Details</h1>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>

  <p style="font-size:13px; color:var(--ink-3);">
    Active: <?= $party['payout_bank_account_number'] ? '····' . substr($party['payout_bank_account_number'], -4) . ' (' . esc($party['payout_bank_ifsc']) . ')' : 'None on file' ?>
  </p>
  <?php if (!empty($party['payout_bank_pending_activates_at'])): ?>
    <p style="font-size:12px; color:var(--amber, #9C5B1F);">
      A change is pending — activates <?= esc($party['payout_bank_pending_activates_at']) ?> (24-hour cooling-off period).
    </p>
  <?php endif; ?>

  <form method="post" action="/payout-bank/request" style="margin-top:16px;">
    <label style="font-size:12px; color:var(--ink-3);">New Account Number</label>
    <input type="text" name="account_number" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">IFSC</label>
    <input type="text" name="ifsc" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Send OTP to Confirm Change</button>
  </form>
</main>
<?= $this->endSection() ?>
