<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Purchases</h1>
  <p style="font-size:12px;"><a href="/my-purchases/export" style="color:var(--emerald);">Export CSV &rarr;</a></p>

  <form method="get" action="/my-purchases" style="margin:16px 0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="format" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">All formats</option>
      <?php foreach (['easy', 'express', 'buy_now', 'tender'] as $f): ?>
        <option value="<?= $f ?>" <?= $format === $f ? 'selected' : '' ?>><?= strtoupper($f) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'completed', 'stalled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= esc($from) ?>" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <input type="date" name="to" value="<?= esc($to) ?>" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Filter</button>
  </form>

  <table style="width:100%; border-collapse:collapse; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Date</th><th>Format</th><th>Price</th><th>Seller</th><th>Status</th><th>Rated</th><th>Dispute</th>
    </tr>
    <?php foreach ($purchases as $p): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc(substr($p['created_at'], 0, 10)) ?></td>
      <td><?= esc(strtoupper($p['sale_format'])) ?></td>
      <td><a href="/settlements/<?= esc($p['id']) ?>" style="color:var(--emerald);">₹<?= number_format((float) $p['final_price'], 2) ?></a></td>
      <td><?= esc($p['seller_mobile']) ?></td>
      <td><?= esc(strtoupper($p['status'])) ?></td>
      <td><?= $p['buyer_rated_seller_at'] ? 'Yes' : 'Pending' ?></td>
      <td><?= $p['dispute'] ? '<a href="/disputes/' . esc($p['dispute']['id']) . '" style="color:#B5482F;">View</a>' : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($purchases)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No purchases match.</p><?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:8px; margin-top:16px; font-size:12px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&format=<?= esc($format) ?>&status=<?= esc($status) ?>&from=<?= esc($from) ?>&to=<?= esc($to) ?>"
          style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <p style="font-size:11px; color:var(--ink-3); margin-top:8px;"><?= (int) $total ?> total</p>
</main>
<?= $this->endSection() ?>
