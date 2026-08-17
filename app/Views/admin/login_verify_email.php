<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Verify Login Code</h1>
  <p style="color:var(--ink-2); font-size:14px;">
    Mobile number and mPIN confirmed. A code was sent to <?= esc($email ?? 'your recovery email') ?>.
  </p>
  <?php if (!empty($email) && empty($emailSent)): ?>
    <p style="color:#9C5B1F; font-size:13px; background:var(--amber-soft); padding:10px; border-radius:8px;">
      Real email delivery is not configured in this environment (no SMTP
      credentials set) — use the dev-mode code shown below instead.
    </p>
  <?php endif; ?>
  <?php if (!empty($devOtp)): ?>
    <p style="background:var(--amber-soft); color:#9C5B1F; padding:10px; border-radius:8px; font-size:13px;">
      <strong>Dev mode</strong>: code is <strong><?= esc($devOtp) ?></strong>
    </p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/login/verify-email"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">Email Code</label>
    <input type="text" name="otp" maxlength="6" required autofocus
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Verify &amp; Log In</button>
  </form>
  <p style="margin-top:16px;"><a href="/admin/login" style="font-size:13px; color:var(--ink-3);">Back to login</a></p>
</main>
<?= $this->endSection() ?>
