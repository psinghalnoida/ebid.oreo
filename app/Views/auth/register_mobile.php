<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Create your account</h1>
  <p style="color:var(--ink-2); font-size:14px;">BR-02: enter your mobile number to receive a verification OTP.</p>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/register">
    <label style="font-size:12px; color:var(--ink-3);">Mobile Number</label>
    <input type="text" name="mobile_number" placeholder="+919876543210" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Send OTP</button>
  </form>
  <p style="font-size:13px; margin-top:20px;">Already registered? <a href="/login" style="color:var(--emerald);">Log in</a></p>
</main>
<?= $this->endSection() ?>
