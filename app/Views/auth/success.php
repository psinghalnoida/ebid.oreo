<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:420px; padding:60px 24px;">
  <h1 style="font-size:24px;">You're in.</h1>
  <p style="color:var(--ink-2); font-size:14px;">Authenticated successfully via CodeIgniter's real session layer.</p>
  <a href="/" class="btn btn-emerald">Go to Marketplace</a>
</main>
<?= $this->endSection() ?>
