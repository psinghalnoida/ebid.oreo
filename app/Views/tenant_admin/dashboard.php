<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <h1 style="font-size:24px;">Tenant Admin — <?= esc($tenant['name']) ?></h1>

  <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; margin:20px 0;">
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingListings) ?></p>
      <p style="font-size:10px; color:var(--ink-3);">Listings to Review</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingSaleEvents) ?></p>
      <p style="font-size:10px; color:var(--ink-3);">Sale Events to Approve</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingSellers) ?></p>
      <p style="font-size:10px; color:var(--ink-3);">Seller Applications</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($openDisputes) ?></p>
      <p style="font-size:10px; color:var(--ink-3);">Open Disputes</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($stalledSettlements) ?></p>
      <p style="font-size:10px; color:var(--ink-3);">Stalled Settlements</p>
    </div>
  </div>

  <h3 style="font-size:15px; margin-top:24px;">Listings Awaiting Approval</h3>
  <?php foreach ($pendingListings as $l): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <a href="/listings/<?= esc($l['id']) ?>"><?= esc($l['category']) ?> — <?= esc($l['physical_condition']) ?></a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingListings)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Sale Events Awaiting Approval</h3>
  <?php foreach ($pendingSaleEvents as $se): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <?= esc($se['ern']) ?> — <?= esc(strtoupper($se['sale_format'])) ?>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingSaleEvents)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Seller Applications</h3>
  <?php if (!empty($pendingSellers)): ?>
    <a href="/tenants/<?= esc($tenant['id']) ?>/pending-sellers" class="btn btn-ghost" style="font-size:12px;">Review <?= count($pendingSellers) ?> pending</a>
  <?php else: ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Open Disputes</h3>
  <?php foreach ($openDisputes as $d): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <a href="/disputes/<?= esc($d['id']) ?>"><?= esc(str_replace('_', ' ', $d['category'])) ?></a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($openDisputes)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Stalled Settlements</h3>
  <?php foreach ($stalledSettlements as $s): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <a href="/settlements/<?= esc($s['id']) ?>">₹<?= number_format((float) $s['final_price'], 2) ?></a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($stalledSettlements)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
