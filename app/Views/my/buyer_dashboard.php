<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Buyer Dashboard</h1>
  <p style="color:var(--ink-3); font-size:13px;">A real summary across your account — each section links to its full page.</p>

  <div style="display:flex; gap:16px; margin:20px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['activeBidsCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Active Bids</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['openOffersCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Open Offers</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0; color:<?= $summary['purchasesToRateCount'] > 0 ? '#B5482F' : 'inherit' ?>;"><?= (int) $summary['purchasesToRateCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Purchases to Rate</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['favoriteCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Favorites</p>
    </div>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:24px;">
    <h3 style="font-size:15px;">Active Bids</h3><a href="/my-bids" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['activeBids'] as $b): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <a href="/sale-events/<?= esc($b['sale_event_id']) ?>" style="color:inherit;"><?= esc($b['category']) ?></a> —
      ₹<?= number_format((float) $b['amount'], 0) ?> — <span style="text-transform:uppercase; font-size:11px; color:var(--ink-3);"><?= esc($b['standing']) ?></span>
    </p>
  <?php endforeach; ?>
  <?php if (empty($summary['activeBids'])): ?><p style="font-size:12px; color:var(--ink-3);">No active bids.</p><?php endif; ?>

  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:24px;">
    <h3 style="font-size:15px;">Open Offers</h3><a href="/my-offers" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['openOffers'] as $o): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);"><?= esc($o['category']) ?> — ₹<?= number_format((float) $o['amount'], 0) ?></p>
  <?php endforeach; ?>
  <?php if (empty($summary['openOffers'])): ?><p style="font-size:12px; color:var(--ink-3);">No open offers.</p><?php endif; ?>

  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:24px;">
    <h3 style="font-size:15px;">Purchases to Rate</h3><a href="/my-purchases" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['purchasesToRate'] as $s): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <a href="/settlements/<?= esc($s['id']) ?>" style="color:#B5482F;"><?= esc($s['category']) ?> — ₹<?= number_format((float) $s['final_price'], 0) ?> — rate now &rarr;</a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($summary['purchasesToRate'])): ?><p style="font-size:12px; color:var(--ink-3);">Nothing pending a rating.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/profile" style="color:var(--ink-3); font-size:12px;">&larr; Back to Profile</a></p>
</main>
<?= $this->endSection() ?>
