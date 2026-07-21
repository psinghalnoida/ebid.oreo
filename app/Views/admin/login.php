<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Super Admin Login</h1>
  <p style="color:var(--ink-3); font-size:13px;">BR-04: separate from regular user login — requires mPIN plus a verified TOTP code.</p>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/login">
    <label style="font-size:12px; color:var(--ink-3);">Mobile Number</label>
    <input type="text" name="mobile_number" placeholder="+919876543210" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">mPIN</label>
    <input type="password" name="mpin" maxlength="4" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Authenticator Code</label>
    <input type="text" name="totp_code" maxlength="6" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Log In</button>
  </form>
</main>
<?= $this->endSection() ?>
