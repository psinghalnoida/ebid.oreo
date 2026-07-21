<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:24px;">Whitelist a Tenant</h1>
  <p style="color:var(--ink-3); font-size:13px;">BR-06: creating a tenant here IS the whitelisting act.</p>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <form method="post" action="/admin/tenants">
    <label style="font-size:12px; color:var(--ink-3);">Tenant Name</label>
    <input type="text" name="name" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Subdomain</label>
    <input type="text" name="subdomain" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Tenant Class (BR-07)</label>
    <select name="tenant_class" style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <option value="general">General</option>
      <option value="institutional">Institutional</option>
      <option value="company_shop">Company Shop</option>
    </select>
    <label style="font-size:12px; color:var(--ink-3);">Buyer Fee Percent</label>
    <input type="number" step="0.01" name="buyer_fee_percent" value="5.00"
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Whitelist Tenant</button>
  </form>
</main>
<?= $this->endSection() ?>
