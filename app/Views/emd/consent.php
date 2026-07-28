<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Confirm Your Deposit</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;"><?= esc($saleEvent['ern']) ?></p>

  <div style="background:var(--line-soft); padding:20px; border-radius:12px; margin:20px 0;">
    <p style="font-size:13px; color:var(--ink-3); margin:0 0 4px;">Earnest Money Deposit (EMD)</p>
    <p style="font-size:28px; font-weight:800; margin:0;">₹<?= number_format($amount, 2) ?></p>
  </div>

  <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:20px;">
    <p style="font-size:13px; line-height:1.6; margin:0;">
      I understand that pledging <strong>₹<?= number_format($amount, 2) ?></strong> as Earnest Money Deposit
      for this bid means: if the auction closes and I fail to complete my obligation, this deposit is
      <strong>forfeited</strong> — allocated to the Tenant, SaaS, and (where applicable) the seller per the
      platform's standard forfeiture rules.
    </p>
  </div>

  <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/emd-consent/<?= esc($action) ?>/confirm">
    <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; margin-bottom:20px;">
      <input type="checkbox" name="confirmed" value="1" required style="margin-top:2px;">
      I have read and understand the deposit and forfeiture terms above.
    </label>
    <input type="text" name="dev_gateway_reference" placeholder="Dev-only: test bank/UTR reference (optional)"
      style="display:block; width:100%; padding:10px; margin-bottom:14px; border:1px dashed var(--line); border-radius:8px; font-size:12px;">
    <p style="font-size:10.5px; color:var(--ink-3); margin:-10px 0 14px;">⚠️ Dev-only: simulates the reference a real payment gateway (BR-52, not yet connected) would return — lets BR-54's AML cross-account check be tested for real.</p>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Confirm & Pledge Deposit</button>
  </form>
</main>
<?= $this->endSection() ?>
