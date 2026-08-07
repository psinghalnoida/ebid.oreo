<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:1000px; padding:40px 24px;">
  <h1 style="font-size:22px;">Lot Directory</h1>
  <p style="color:var(--ink-3); font-size:13px;">Every <?= strtolower(tsx_term('Listing')) ?> across every <?= strtolower(tsx_term('Tenant')) ?> — <?= (int) $total ?> total.</p>

  <form method="get" action="/admin/lots" style="display:flex; gap:8px; flex-wrap:wrap; margin:16px 0;">
    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Search category or tenant"
      style="flex:1; min-width:200px; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
    <select name="tenant_id" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
      <option value="">All <?= tsx_term('Tenant', false, true) ?></option>
      <?php foreach ($tenants as $t): ?>
        <option value="<?= esc($t['id']) ?>" <?= $tenantId === $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="format" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
      <option value="">All Formats</option>
      <?php foreach (['easy', 'express', 'buy_now', 'tender'] as $f): ?>
        <option value="<?= $f ?>" <?= $format === $f ? 'selected' : '' ?>><?= esc(tsx_term($f)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
      <option value="">All Statuses</option>
      <?php foreach (['inventory', 'pending_approval', 'upcoming', 'active', 'archived'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $s))) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost">Filter</button>
  </form>

  <table style="width:100%; border-collapse:collapse; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;"><?= tsx_term('Tenant') ?></th><th>Category</th><th>Status</th><th>Format</th><th>Sale Status</th><th>Views</th>
    </tr>
    <?php foreach ($listings as $l): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><?= esc($l['tenant_name']) ?></td>
      <td><a href="/listings/<?= esc($l['id']) ?>" style="color:var(--emerald);"><?= esc($l['category']) ?><?= $l['subcategory'] ? ' — '.esc($l['subcategory']) : '' ?></a></td>
      <td><?= esc(ucfirst(str_replace('_', ' ', $l['status']))) ?></td>
      <td><?= $l['sale_format'] ? esc(tsx_term($l['sale_format'])) : '—' ?></td>
      <td><?= $l['sale_status'] ? esc(str_replace('_', ' ', $l['sale_status'])) : '—' ?></td>
      <td><?= (int) $l['view_count'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($listings)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No matching listings.</p><?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="margin-top:16px; display:flex; gap:6px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($q) ?>&tenant_id=<?= urlencode((string) $tenantId) ?>&format=<?= urlencode((string) $format) ?>&status=<?= urlencode((string) $status) ?>"
          style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?> font-size:12px;"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <p style="margin-top:20px;"><a href="/admin" style="color:var(--ink-3); font-size:12px;">&larr; Back to Dashboard</a></p>
</main>
<?= $this->endSection() ?>
