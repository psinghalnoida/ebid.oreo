<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:24px;">Apply to Sell on <?= esc($tenant['name'] ?? 'this tenant') ?></h1>
  <p style="color:var(--ink-3); font-size:13px;">BR-09: selling rights are tenant-specific — approval here doesn't carry over to any other tenant.</p>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <?php if ($existing): ?>
    <p style="font-size:14px; padding:14px; background:var(--line-soft); border-radius:10px;">
      Application status: <strong><?= esc(strtoupper($existing['status'])) ?></strong>
      <?php if ($existing['status'] === 'rejected' && $existing['rejection_reason']): ?>
        <br><span style="font-size:12px; color:var(--ink-3);">Reason: <?= esc($existing['rejection_reason']) ?></span>
      <?php endif; ?>
    </p>
  <?php else: ?>
    <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/apply-to-sell">
      <button type="submit" class="btn btn-emerald" style="width:100%;">Apply to Sell Here</button>
    </form>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
