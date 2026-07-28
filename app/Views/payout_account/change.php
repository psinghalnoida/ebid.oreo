<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($error) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Change Payout Bank Account</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    BR-50: this requires OTP re-verification, and a mandatory 24-hour cooling-off period applies before the new account is used for any payout.
  </p>

  <form method="post" action="/payout-account/change" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Account Holder Name</label>
    <input type="text" name="account_holder_name" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Account Number</label>
    <input type="text" name="account_number" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">IFSC Code</label>
    <input type="text" name="ifsc_code" required maxlength="11"
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px; text-transform:uppercase;">

    <button type="submit" class="btn btn-emerald" style="width:100%;">Send OTP to Confirm</button>
  </form>
</main>
<?= $this->endSection() ?>
