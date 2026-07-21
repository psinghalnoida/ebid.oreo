<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:24px;">Super Admin Dashboard</h1>

  <div style="display:flex; gap:16px; margin:20px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= count($tenants) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Whitelisted Tenants</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= esc($openDisputes) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Open Disputes</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= esc($stalledSettlements) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Stalled Settlements</p>
    </div>
  </div>

  <a href="/admin/tenants/create" class="btn btn-emerald">+ Whitelist New Tenant</a>

  <h3 style="font-size:16px; margin-top:28px;">Tenants</h3>
  <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Name</th><th>Class</th><th>Subdomain</th><th>Buyer Fee</th>
    </tr>
    <?php foreach ($tenants as $t): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><?= esc($t['name']) ?></td>
      <td><?= esc($t['tenant_class']) ?></td>
      <td><?= esc($t['subdomain']) ?></td>
      <td><?= esc($t['buyer_fee_percent']) ?>%</td>
    </tr>
    <?php endforeach; ?>
  </table>

  <p style="margin-top:24px;"><a href="/admin/logout" style="color:var(--ink-3); font-size:12px;">Log out of Super Admin</a></p>
</main>
<?= $this->endSection() ?>
