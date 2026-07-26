<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Request Media Waiver — <?= esc($tenant['name']) ?></h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    BR-60 — for one specific category where item-specific photography isn't meaningful (e.g., bulk
    commodities, rental space, surplus goods sold by specification). Valid 12 months if approved, subject
    to active review, always disclosed to buyers.
  </p>

  <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/media-waiver" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Category</label>
    <input type="text" name="category" required placeholder="e.g. Industrial Plant"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Business Justification</label>
    <textarea name="business_justification" rows="4" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Submit for SaaS Admin Review</button>
  </form>
</main>
<?= $this->endSection() ?>
