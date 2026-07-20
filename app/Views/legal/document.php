<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<style>
  .legal-doc h2{font-size:19px; font-weight:700; margin:34px 0 14px;}
  .legal-doc h2:first-child{margin-top:0;}
  .legal-doc p{font-size:14.5px; line-height:1.7; color:var(--ink-2); margin:0 0 14px;}
  .legal-doc strong{color:var(--ink);}
  .legal-doc table{width:100%; border-collapse:collapse; margin:16px 0; font-size:13.5px;}
  .legal-doc td, .legal-doc th{border:1px solid var(--line); padding:10px 12px; text-align:left; vertical-align:top;}
  .legal-doc th{background:var(--line-soft); font-weight:700; font-size:12px;}
  .legal-pending{background:var(--amber-soft); color:#9C5B1F; padding:2px 8px; border-radius:6px; font-size:12.5px; font-weight:600; white-space:nowrap;}
</style>
<main style="max-width:760px; padding:44px 24px 80px;">
  <p style="font-size:12px; color:var(--ink-3); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">eBid Hub — Legal</p>
  <h1 style="font-size:30px; font-weight:800; margin:6px 0 20px;"><?= esc($docTitle) ?></h1>
  <div class="legal-doc"><?= $bodyHtml ?></div>
</main>
<?= $this->endSection() ?>
