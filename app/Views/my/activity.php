<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Activity</h1>

  <h3 style="font-size:15px; margin-top:20px;">My Bids</h3>
  <?php foreach ($bids as $b): ?>
    <a href="/listings/<?= esc($b['listing_id']) ?>" style="text-decoration:none; color:inherit;">
      <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between;">
        <span style="font-size:13px;"><?= esc($b['category']) ?> — ₹<?= number_format((float) $b['amount'], 2) ?></span>
        <span style="font-size:11px; color:<?= $b['standing'] === 'h1' ? 'var(--emerald)' : 'var(--ink-3)' ?>; text-transform:uppercase;"><?= esc($b['standing']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (empty($bids)): ?><p style="font-size:12px; color:var(--ink-3);">No bids yet.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">My Offers</h3>
  <?php foreach ($offers as $o): ?>
    <a href="/listings/<?= esc($o['listing_id']) ?>" style="text-decoration:none; color:inherit;">
      <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between;">
        <span style="font-size:13px;"><?= esc($o['category']) ?> — ₹<?= number_format((float) $o['amount'], 2) ?></span>
        <span style="font-size:11px; color:var(--ink-3); text-transform:uppercase;"><?= esc($o['status']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (empty($offers)): ?><p style="font-size:12px; color:var(--ink-3);">No offers yet.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">My Purchases (Settlements)</h3>
  <?php foreach ($settlements as $s): ?>
    <a href="/settlements/<?= esc($s['id']) ?>" style="text-decoration:none; color:inherit;">
      <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between;">
        <span style="font-size:13px;"><?= esc($s['category']) ?> — ₹<?= number_format((float) $s['final_price'], 2) ?></span>
        <span style="font-size:11px; color:var(--ink-3); text-transform:uppercase;"><?= esc($s['status']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
  <?php if (empty($settlements)): ?><p style="font-size:12px; color:var(--ink-3);">No purchases yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
