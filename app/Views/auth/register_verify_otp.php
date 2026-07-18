<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Enter the OTP</h1>
  <p style="color:var(--ink-2); font-size:14px;">Sent to <?= esc($mobile) ?>.</p>
  <?php if (!empty($devOtp)): ?>
    <p style="background:var(--amber-soft); color:#9C5B1F; padding:10px; border-radius:8px; font-size:13px;">
      <strong>Dev mode</strong> (SMS provider not yet connected — BR-02 tech-stack open item): your OTP is <strong><?= esc($devOtp) ?></strong>
    </p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/register/verify-otp">
    <label style="font-size:12px; color:var(--ink-3);">6-digit OTP</label>
    <input type="text" name="otp" maxlength="6" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Verify</button>
  </form>
</main>
<?= $this->endSection() ?>
