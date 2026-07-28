<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:24px;">2FA Enabled — Save Your Backup Codes</h1>
  <p style="color:var(--ink-2); font-size:14px;">PR-17: each code below can be used once, in place of your authenticator app, if you lose access to your device. These codes are shown only this one time — they are not recoverable afterward (a new confirm regenerates a fresh set and invalidates these).</p>

  <div style="background:var(--line-soft); padding:16px; border-radius:10px; margin:16px 0;">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-family:monospace; font-size:14px;">
      <?php foreach ($codes as $code): ?>
        <code style="background:#fff; padding:8px; border-radius:6px; text-align:center;"><?= esc($code) ?></code>
      <?php endforeach ?>
    </div>
  </div>

  <p style="color:var(--ink-3); font-size:12px;">Download or print this page now — it will not be shown again.</p>

  <form method="get" action="/admin/login">
    <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin:10px 0;">
      <input type="checkbox" required> I have saved these codes somewhere safe
    </label>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Continue to Login</button>
  </form>
</main>
<?= $this->endSection() ?>
