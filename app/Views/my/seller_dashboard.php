<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Seller Dashboard</h1>
  <p style="color:var(--ink-3); font-size:13px;">A real summary across your account — each section links to its full page.</p>

  <div style="display:flex; gap:16px; margin:20px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['activeListingsCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Active <?= tsx_term('Listing', false, true) ?></p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['salesThisMonthCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Sales This Month</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;">₹<?= number_format($summary['salesThisMonthTotal'], 0) ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Sales Value This Month</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0; color:<?= $summary['pendingSettlementsCount'] > 0 ? '#B5482F' : 'inherit' ?>;"><?= (int) $summary['pendingSettlementsCount'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Pending Settlements</p>
    </div>
  </div>

  <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:20px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 4px;">Payout Bank</p>
    <?php if ($summary['payoutBankSet']): ?>
      <p style="font-size:12px; color:var(--emerald); margin:0;">On file<?= $summary['payoutBankPending'] ? ' — a change is pending activation' : '' ?></p>
    <?php else: ?>
      <p style="font-size:12px; color:#B5482F; margin:0;">Not set — <a href="/payout-bank" style="color:#B5482F;">add one now</a></p>
    <?php endif; ?>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:baseline;">
    <h3 style="font-size:15px;">Active <?= tsx_term('Listing', false, true) ?></h3><a href="/my-listings" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['activeListings'] as $l): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <a href="/listings/<?= esc($l['id']) ?>" style="color:inherit;"><?= esc($l['category']) ?><?= $l['subcategory'] ? ' — '.esc($l['subcategory']) : '' ?></a> — <?= (int) $l['view_count'] ?> views
    </p>
  <?php endforeach; ?>
  <?php if (empty($summary['activeListings'])): ?><p style="font-size:12px; color:var(--ink-3);">No active listings — <a href="/listings/create">create one</a>.</p><?php endif; ?>

  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:24px;">
    <h3 style="font-size:15px;">Pending Settlements</h3><a href="/my-sales" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['pendingSettlements'] as $s): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <a href="/settlements/<?= esc($s['id']) ?>" style="color:#B5482F;"><?= esc($s['category']) ?> — ₹<?= number_format((float) $s['final_price'], 0) ?></a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($summary['pendingSettlements'])): ?><p style="font-size:12px; color:var(--ink-3);">Nothing pending.</p><?php endif; ?>

  <div style="display:flex; justify-content:space-between; align-items:baseline; margin-top:24px;">
    <h3 style="font-size:15px;">Recent Invoices</h3><a href="/account/invoices" style="font-size:12px; color:var(--emerald);">View all &rarr;</a>
  </div>
  <?php foreach ($summary['recentInvoices'] as $inv): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);"><?= esc($inv['invoice_number'] ?? $inv['id']) ?> — ₹<?= number_format((float) ($inv['total_amount'] ?? 0), 0) ?></p>
  <?php endforeach; ?>
  <?php if (empty($summary['recentInvoices'])): ?><p style="font-size:12px; color:var(--ink-3);">No invoices yet.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/profile" style="color:var(--ink-3); font-size:12px;">&larr; Back to Profile</a></p>
</main>
<?= $this->endSection() ?>
