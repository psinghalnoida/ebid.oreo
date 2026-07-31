<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;"><?= tsx_term('Tenant', false, true) ?></h1>
  <form method="get" action="/admin/tenants" style="margin:16px 0;">
    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Search name or subdomain"
      style="width:300px; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-ghost">Search</button>
    <a href="/admin/tenants/create" class="btn btn-emerald" style="margin-left:8px;">+ Whitelist New <?= tsx_term('Tenant') ?></a>
  </form>

  <table style="width:100%; border-collapse:collapse; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Name</th><th>Class</th><th>Subdomain</th><th>Subscription Tier</th>
    </tr>
    <?php foreach ($tenants as $t): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><a href="/admin/tenants/<?= esc($t['id']) ?>" style="color:var(--emerald);"><?= esc($t['name']) ?></a></td>
      <td><?= esc($t['tenant_class']) ?></td>
      <td><?= esc($t['subdomain']) ?></td>
      <td><?= esc(ucwords(str_replace('_', ' ', $t['subscription_tier']))) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($tenants)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No matching <?= strtolower(tsx_term('Tenant', false, true)) ?>.</p><?php endif; ?>

  <p style="margin-top:20px;"><a href="/admin" style="color:var(--ink-3); font-size:12px;">&larr; Back to Dashboard</a></p>
</main>
<?= $this->endSection() ?>
