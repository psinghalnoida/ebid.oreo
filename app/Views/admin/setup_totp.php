<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:24px;">Set Up Super Admin 2FA</h1>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <p style="color:var(--ink-2); font-size:14px;">BR-04: Super Admin access requires a real second authentication factor (TOTP), separate from regular mPIN login.</p>

  <div style="background:var(--line-soft); padding:16px; border-radius:10px; margin:16px 0;">
    <p style="font-size:12px; color:var(--ink-3); margin:0 0 8px;">Add this manually in Google Authenticator, Authy, or any TOTP app (no QR code — enter the key directly):</p>
    <code style="display:block; word-break:break-all; font-size:13px; background:#fff; padding:10px; border-radius:6px;"><?= esc($setup['secret']) ?></code>
  </div>

  <form method="post" action="/admin/setup-totp">
    <label style="font-size:12px; color:var(--ink-3);">Enter the 6-digit code from your app to confirm</label>
    <input type="text" name="code" maxlength="6" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Confirm & Enable 2FA</button>
  </form>
</main>
<?= $this->endSection() ?>
