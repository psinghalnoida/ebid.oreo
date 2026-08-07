<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:22px;">Confirm New mPIN</h1>
  <?php if (!empty($devOtp)): ?>
    <p style="background:var(--amber-soft); color:#9C5B1F; padding:10px; border-radius:8px; font-size:13px;">
      <strong>Dev mode</strong> (SMS provider not yet connected): your OTP is <strong><?= esc($devOtp) ?></strong>
    </p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/account/change-mpin/confirm"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">6-digit OTP</label>
    <input type="text" name="otp" maxlength="6" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">New 4-digit mPIN</label>
    <input type="password" name="new_mpin" maxlength="4" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Confirm</button>
  </form>
</main>
<?= $this->endSection() ?>
