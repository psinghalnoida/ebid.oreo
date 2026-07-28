<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;">Verification Console — <?= esc($tenant['name']) ?></h1>
  <p style="font-size:12px; color:var(--ink-3);">PR-09: authentic media catalog + thumbnail for every listing awaiting approval.</p>

  <div style="display:flex; flex-direction:column; gap:12px; margin-top:16px;">
    <?php foreach ($pending as $l): ?>
      <a href="/listings/<?= esc($l['id']) ?>" style="display:flex; gap:14px; align-items:center; border:1px solid var(--line); border-radius:12px; padding:12px; text-decoration:none; color:inherit;">
        <?php if ($l['primaryPhotoPath']): ?>
          <img src="/<?= esc($l['primaryPhotoPath']) ?>" style="width:72px; height:72px; object-fit:cover; border-radius:8px; flex-shrink:0;">
        <?php else: ?>
          <div style="width:72px; height:72px; border-radius:8px; background:var(--line-soft); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; color:var(--ink-3); text-align:center;">
            No primary photo yet
          </div>
        <?php endif; ?>
        <div style="flex:1;">
          <p style="font-size:14px; font-weight:700; margin:0 0 4px;"><?= esc($l['category']) ?><?= $l['subcategory'] ? ' / ' . esc($l['subcategory']) : '' ?></p>
          <p style="font-size:12px; color:var(--ink-3); margin:0;"><?= esc($l['physical_condition']) ?> · Lot <?= esc(substr($l['id'], 0, 8)) ?></p>
          <p style="font-size:11px; color:var(--ink-3); margin:4px 0 0;">
            <?= (int) $l['photoCount'] ?> photos · <?= (int) $l['videoCount'] ?> videos · <?= (int) $l['documentCount'] ?> documents
            <?php if ($l['queuedJobCount'] > 0): ?> · <?= (int) $l['queuedJobCount'] ?> still processing<?php endif; ?>
          </p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:16px;">Nothing awaiting review.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/tenants/<?= esc($tenant['id']) ?>/dashboard" style="color:var(--ink-3); font-size:12px;">&larr; Back to Dashboard</a></p>
</main>
<?= $this->endSection() ?>
