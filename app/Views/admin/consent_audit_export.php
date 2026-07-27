<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Consent Audit Export</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    BR-51 — exports every discrete consent event for a date range, for compliance/dispute documentation.
  </p>

  <form method="get" action="/admin/consent-audit/export/download" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">From</label>
    <input type="date" name="from" required style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">To</label>
    <input type="date" name="to" required style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">

    <button type="submit" class="btn btn-emerald" style="width:100%;">Download CSV Export</button>
  </form>
</main>
<?= $this->endSection() ?>
