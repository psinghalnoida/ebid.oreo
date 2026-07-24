<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Verify OTP to reset mPIN</h1>
  <p style="color:var(--ink-2); font-size:14px;">
    BR-02: 3 consecutive incorrect mPIN attempts require OTP verification before you can log in or reset your mPIN.
  </p>
  <p style="color:var(--ink-2); font-size:14px;">Sent to <?= esc($mobile) ?><?= !empty($email) ? ' and ' . esc($email) : '' ?>.</p>
  <?php if (!empty($devOtp)): ?>
    <p style="background:var(--amber-soft); color:#9C5B1F; padding:10px; border-radius:8px; font-size:13px;">
      <strong>Dev mode</strong>: mobile OTP is <strong><?= esc($devOtp) ?></strong>
      <?php if (!empty($devEmailOtp)): ?><br>email OTP is <strong><?= esc($devEmailOtp) ?></strong><?php endif; ?>
    </p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/login/reset-verify-otp">
    <label style="font-size:12px; color:var(--ink-3);">Mobile OTP</label>
    <input type="text" name="otp" maxlength="6" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <?php if (!empty($email)): ?>
      <label style="font-size:12px; color:var(--ink-3);">Email OTP (both required together)</label>
      <input type="text" name="email_otp" maxlength="6" required
        style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <?php endif; ?>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Verify &amp; Continue</button>
  </form>
</main>
<?= $this->endSection() ?>
