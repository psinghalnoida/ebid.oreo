<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Bids</h1>

  <form method="get" action="/my-bids" style="margin:16px 0; display:flex; gap:8px; flex-wrap:wrap;">
    <select name="format" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">All formats</option>
      <?php foreach (['easy', 'express', 'buy_now', 'tender'] as $f): ?>
        <option value="<?= $f ?>" <?= $format === $f ? 'selected' : '' ?>><?= strtoupper($f) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">All standings</option>
      <?php foreach (['h1', 'h2', 'h3', 'outbid', 'defaulted', 'withdrawn'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <?php foreach (['recent' => 'Recent first', 'oldest' => 'Oldest first', 'highest' => 'Highest amount', 'lowest' => 'Lowest amount'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Filter</button>
  </form>

  <?php foreach ($bids as $b): ?>
    <a href="/listings/<?= esc($b['listing_id']) ?>" style="text-decoration:none; color:inherit;">
      <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between;">
        <span style="font-size:13px;"><?= esc($b['category']) ?> — <?= esc(strtoupper($b['sale_format'])) ?> — ₹<?= number_format((float) $b['amount'], 2) ?></span>
        <span style="font-size:11px; color:<?= $b['standing'] === 'h1' ? 'var(--emerald)' : 'var(--ink-3)' ?>; text-transform:uppercase;"><?= esc($b['standing']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (empty($bids)): ?><p style="font-size:12px; color:var(--ink-3);">No bids match.</p><?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:8px; margin-top:16px; font-size:12px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&format=<?= esc($format) ?>&status=<?= esc($status) ?>&sort=<?= esc($sort) ?>"
          style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <p style="font-size:11px; color:var(--ink-3); margin-top:8px;"><?= (int) $total ?> total</p>
</main>
<?= $this->endSection() ?>
