<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:560px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <h1 style="font-size:22px;">Pending Seller Applications — <?= esc($tenant['name'] ?? '') ?></h1>
  <?php if (empty($applications)): ?>
    <p style="color:var(--ink-3); font-size:14px;">No pending applications.</p>
  <?php endif; ?>
  <?php foreach ($applications as $app): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:10px;">
      <p style="font-size:13px; color:var(--ink-3);">Party ID: <?= esc($app['party_id']) ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Applied: <?= esc($app['applied_at']) ?></p>
      <form method="post" action="/seller-applications/<?= esc($app['id']) ?>/approve" style="display:inline;">
        <button type="submit" class="btn btn-emerald" style="font-size:12px; padding:8px 14px;">Approve</button>
      </form>
      <form method="post" action="/seller-applications/<?= esc($app['id']) ?>/reject" style="display:inline;">
        <input type="text" name="reason" placeholder="Reason" style="font-size:11px; padding:6px; border:1px solid var(--line); border-radius:6px;">
        <button type="submit" class="btn btn-ghost" style="font-size:12px; padding:8px 14px;">Reject</button>
      </form>
    </div>
  <?php endforeach; ?>
</main>
<?= $this->endSection() ?>
