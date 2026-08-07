<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:24px;">Whitelist a <?= tsx_term('Tenant') ?></h1>
  <p style="color:var(--ink-3); font-size:13px;">BR-06: creating a <?= strtolower(tsx_term('Tenant')) ?> here IS the whitelisting act.</p>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/tenants"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);"><?= tsx_term('Tenant') ?> Name</label>
    <input type="text" name="name" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Subdomain</label>
    <input type="text" name="subdomain" required placeholder="e.g. pnb"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Custom Domain (BR-06, optional)</label>
    <input type="text" name="custom_domain" placeholder="e.g. www.salvagemanagers.com"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);"><?= tsx_term('Tenant') ?> Class (BR-07)</label>
    <select name="tenant_class" style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <option value="general">General</option>
      <option value="institutional">Institutional</option>
      <option value="company_shop">Company Shop</option>
    </select>
    <label style="font-size:12px; color:var(--ink-3);">Subscription Tier (Section 5, BR-08/09) — the Success Fee itself is fixed platform-wide and not set here</label>
    <select name="subscription_tier" style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
      <option value="coco_starter">CoCo Starter (<?= tsx_term('Buyer') ?>-Pays only)</option>
      <option value="tsx_launch">TSX Launch</option>
      <option value="tsx_growth">TSX Growth</option>
      <option value="tsx_enterprise">TSX Enterprise</option>
    </select>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Whitelist <?= tsx_term('Tenant') ?></button>
  </form>
</main>
<?= $this->endSection() ?>
