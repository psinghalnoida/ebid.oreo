<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<main>
  <div style="padding:76px 0 60px;">
    <h1 style="font-size:52px; font-weight:800; letter-spacing:-1.5px; margin:0 0 20px; max-width:640px;">
      Salvage, sold <span style="color:#0F5C4C;">simply</span>.
    </h1>
    <p style="font-size:16.5px; line-height:1.65; color:#5C5C5C; max-width:460px; margin:0 0 30px;">
      eBid Hub is India's multi-tenant marketplace for salvage, surplus, and repossessed assets.
      This page is rendered by a real CodeIgniter 4 controller and view — <?= esc($renderedBy) ?>.
    </p>
    <a href="/trust-support" class="btn btn-emerald">Visit Trust & Support</a>
  </div>
</main>
<?= $this->endSection() ?>
