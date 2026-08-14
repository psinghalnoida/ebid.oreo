<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Forgot mPIN</h1>
  <p style="color:var(--ink-3); font-size:13px;">
    Enter the mobile number on your <?= tsx_term('Super Admin') ?> account. If it's genuinely
    a Custodian account, a reset code is sent to that mobile number and to
    the recovery email on file (both required together).
  </p>
  <?php if (!empty($info)): ?>
    <p style="color:var(--ink-2); font-size:13px; background:var(--line-soft); padding:10px; border-radius:8px;"><?= esc($info) ?></p>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/forgot-mpin"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">Mobile Number</label>
    <input type="text" name="mobile_number" placeholder="+919876543210" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Send Reset Code</button>
  </form>
  <p style="margin-top:16px;"><a href="/admin/login" style="font-size:13px; color:var(--ink-3);">Back to login</a></p>
</main>
<?= $this->endSection() ?>
