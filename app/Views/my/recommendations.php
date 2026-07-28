<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:1000px; padding:40px 24px;">
  <h1 style="font-size:22px;">Recommendations</h1>

  <h3 style="font-size:15px; margin-top:20px;">Trending Now (bid activity in the last 24 hours)</h3>
  <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-top:10px;">
    <?php foreach ($trending as $t): ?>
      <a href="/listings/<?= esc($t['listing_id']) ?>" style="text-decoration:none; color:inherit; border:1px solid var(--line); border-radius:12px; padding:12px;">
        <p style="font-size:10px; color:var(--ink-3); text-transform:uppercase; margin:0 0 4px;"><?= esc(strtoupper($t['sale_format'])) ?> · <?= (int) $t['bid_count'] ?> bids</p>
        <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($t['category']) ?></p>
        <p style="font-size:13px; font-weight:800; color:var(--emerald); margin:0;">₹<?= number_format((float) $t['display_price'], 0) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($trending)): ?><p style="font-size:12px; color:var(--ink-3);">Nothing trending right now.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Based on Your Bids</h3>
  <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-top:10px;">
    <?php foreach ($basedOnBids as $b): ?>
      <a href="/listings/<?= esc($b['listing_id']) ?>" style="text-decoration:none; color:inherit; border:1px solid var(--line); border-radius:12px; padding:12px;">
        <p style="font-size:10px; color:var(--ink-3); text-transform:uppercase; margin:0 0 4px;"><?= esc(strtoupper($b['sale_format'])) ?></p>
        <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($b['category']) ?></p>
        <p style="font-size:13px; font-weight:800; color:var(--emerald); margin:0;">₹<?= number_format((float) $b['display_price'], 0) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($basedOnBids)): ?><p style="font-size:12px; color:var(--ink-3);">Bid on something to see recommendations here.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
