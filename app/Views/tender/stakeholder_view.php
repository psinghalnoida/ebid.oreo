<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <span class="pc-badge" style="background:var(--emerald-soft); color:var(--emerald-deep); padding:5px 12px; border-radius:100px; font-size:11px; font-weight:700;">LIVE — READ ONLY</span>
  <h1 style="font-size:24px; margin:12px 0 4px;"><?= esc($listing['category']) ?></h1>
  <p style="color:var(--ink-3); font-size:13px;"><?= esc($saleEvent['ern']) ?> · TENDER</p>
  <p style="font-size:12px; color:var(--ink-3); margin-top:8px;">Bidder identities are never shown here — amounts only, per BR-16.</p>

  <h3 style="font-size:15px; margin-top:24px;">Bid History (amounts only)</h3>
  <?php foreach ($bidAmounts as $amt): ?>
    <p style="font-size:16px; font-weight:700; padding:8px 0; border-bottom:1px solid var(--line);">₹<?= number_format((float) $amt, 2) ?></p>
  <?php endforeach; ?>
  <?php if (empty($bidAmounts)): ?><p style="font-size:12px; color:var(--ink-3);">No bids yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
