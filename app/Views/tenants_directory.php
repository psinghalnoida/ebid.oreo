<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Browse Tenants</h1>
  <p style="color:var(--ink-3); font-size:13px;">Apply to sell on any of these storefronts — approval is per-tenant, per BR-09.</p>
  <?php foreach ($tenants as $t): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <p style="font-size:14px; font-weight:700; margin:0;"><?= esc($t['name']) ?></p>
        <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0; text-transform:uppercase;"><?= esc($t['tenant_class']) ?></p>
      </div>
      <a href="/tenants/<?= esc($t['id']) ?>/apply-to-sell" class="btn btn-ghost" style="font-size:12px;">Apply to Sell</a>
    </div>
  <?php endforeach; ?>
  <?php if (empty($tenants)): ?><p style="color:var(--ink-3); font-size:13px;">No tenants whitelisted yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
