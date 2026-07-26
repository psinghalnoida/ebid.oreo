<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Statutory Audit Export</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    BR-58 — exports the hash-chained audit trail for a date range, in a form usable by the finance function
    for statutory books of account. Currently covers the hot tier only (records within the last year, held
    in Postgres) — cold-tier archival is a separate, still-pending item (blocked on real Google Cloud
    credentials, unchanged since D-45).
  </p>

  <form method="get" action="/admin/audit-log/export/download" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">From</label>
    <input type="date" name="from" required style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">To</label>
    <input type="date" name="to" required style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">

    <button type="submit" class="btn btn-emerald" style="width:100%;">Download CSV Export</button>
  </form>
</main>
<?= $this->endSection() ?>
