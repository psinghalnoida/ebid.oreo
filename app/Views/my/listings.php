<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Listings</h1>
  <?php if (empty($listings)): ?>
    <p style="color:var(--ink-3); font-size:14px;">You haven't listed anything yet. <a href="/listings/create" style="color:var(--emerald);">Create your first listing</a>.</p>
  <?php endif; ?>
  <?php foreach ($listings as $l): ?>
    <a href="/listings/<?= esc($l['id']) ?>" style="text-decoration:none; color:inherit;">
      <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between;">
          <strong style="font-size:14px;"><?= esc($l['category']) ?><?= $l['subcategory'] ? ' — '.esc($l['subcategory']) : '' ?></strong>
          <span style="font-size:11px; color:var(--ink-3); text-transform:uppercase;"><?= esc($l['status']) ?></span>
        </div>
        <p style="font-size:12px; color:var(--ink-3); margin:6px 0 0;">
          <?= esc($l['physical_condition']) ?>
          <?php if ($l['sale_format']): ?> · <?= esc(strtoupper($l['sale_format'])) ?><?php endif; ?>
          <?php if ($l['current_price'] ?? $l['reserve_value'] ?? $l['expected_value']): ?>
            · ₹<?= number_format((float) ($l['current_price'] ?? $l['reserve_value'] ?? $l['expected_value']), 2) ?>
          <?php endif; ?>
        </p>
      </div>
    </a>
  <?php endforeach; ?>
</main>
<?= $this->endSection() ?>
