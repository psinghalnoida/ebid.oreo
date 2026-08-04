<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:22px;">Change mPIN</h1>
  <p style="font-size:13px; color:var(--ink-3);">We'll send an OTP to your registered mobile to confirm this change.</p>
  <form method="post" action="/account/change-mpin/request-otp" style="margin-top:16px;"><?= csrf_field() ?>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Send OTP</button>
  </form>
</main>
<?= $this->endSection() ?>
