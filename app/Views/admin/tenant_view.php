<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <h1 style="font-size:22px;"><?= esc($tenant['name']) ?></h1>
  <p style="color:var(--ink-3); font-size:12px;"><?= esc($tenant['tenant_class']) ?> · <?= esc($tenant['subdomain']) ?></p>

  <form method="post" action="/admin/tenants/<?= esc($tenant['id']) ?>/edit" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Name</label>
    <input type="text" name="name" value="<?= esc($tenant['name']) ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Buyer Fee Percent</label>
    <input type="number" step="0.01" name="buyer_fee_percent" value="<?= esc($tenant['buyer_fee_percent']) ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Save Changes</button>
  </form>

  <p style="font-size:11px; color:var(--ink-3); margin-top:16px;">Tenant class and subdomain are not editable here — changing them affects existing listings and links, and needs a deliberate decision, not a quick form edit.</p>
</main>
<?= $this->endSection() ?>
