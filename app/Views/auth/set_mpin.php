<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">Set your mPIN</h1>
  <p style="color:var(--ink-2); font-size:14px;">BR-02: a 4-digit mPIN for fast, passwordless sign-in going forward.</p>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/register/set-mpin">
    <label style="font-size:12px; color:var(--ink-3);">4-digit mPIN</label>
    <input type="password" name="mpin" maxlength="4" inputmode="numeric" required
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Set mPIN</button>
  </form>
</main>
<?= $this->endSection() ?>
