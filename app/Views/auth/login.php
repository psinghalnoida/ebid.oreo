<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Log in</h1>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/login">
    <label style="font-size:12px; color:var(--ink-3);">Mobile Number</label>
    <input type="text" name="mobile_number" placeholder="+919876543210" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">mPIN</label>
    <input type="password" name="mpin" maxlength="4" inputmode="numeric" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Log In</button>
  </form>
  <p style="font-size:13px; margin-top:20px;">New here? <a href="/register" style="color:var(--emerald);">Create an account</a></p>
</main>
<?= $this->endSection() ?>
