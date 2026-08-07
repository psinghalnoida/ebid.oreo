<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <h1 style="font-size:24px;"><?= tsx_term('Tenant Admin') ?> — <?= esc($tenant['name']) ?></h1>
  <a href="/tenants/<?= esc($tenant['id']) ?>/verification" class="btn btn-emerald" style="font-size:12px;">Verification Console</a>
  <a href="/tenants/<?= esc($tenant['id']) ?>/media-waiver" class="btn btn-ghost" style="font-size:12px;">Request Media Waiver</a>
  <a href="/admin/payout-reviews" class="btn btn-ghost" style="font-size:12px;">Payout Reviews</a>
  <a href="/admin/rating-reviews" class="btn btn-ghost" style="font-size:12px;">Rating Reviews</a>
  <a href="/tenants/<?= esc($tenant['id']) ?>/sellers" class="btn btn-ghost" style="font-size:12px;"><?= tsx_term('Seller') ?> Management</a>
  <a href="/tenants/<?= esc($tenant['id']) ?>/billing" class="btn btn-ghost" style="font-size:12px;">Billing</a>
  <a href="/tenants/<?= esc($tenant['id']) ?>/api-access" class="btn btn-ghost" style="font-size:12px;">API Access</a>

  <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; margin:20px 0;">
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingListings) ?></p>
      <p style="font-size:10px; color:var(--ink-3);"><?= tsx_term('Listing', false, true) ?> to Review</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingSaleEvents) ?></p>
      <p style="font-size:10px; color:var(--ink-3);"><?= tsx_term('Sale Event', false, true) ?> to Approve</p>
    </div>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= count($pendingSellers) ?></p>
      <p style="font-size:10px; color:var(--ink-3);"><?= tsx_term('Seller') ?> Applications</p>
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

  <h3 style="font-size:15px; margin-top:24px;"><?= tsx_term('Listing', false, true) ?> Awaiting Approval</h3>
  <?php foreach ($pendingListings as $l): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <a href="/listings/<?= esc($l['id']) ?>"><?= esc($l['category']) ?> — <?= esc($l['physical_condition']) ?></a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingListings)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;"><?= tsx_term('Sale Event', false, true) ?> Awaiting Approval</h3>
  <?php foreach ($pendingSaleEvents as $se): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <?= esc($se['ern']) ?> — <?= esc(strtoupper($se['sale_format'])) ?>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingSaleEvents)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;"><?= tsx_term('Seller') ?> Applications</h3>
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

  <h3 style="font-size:15px; margin-top:20px;">High-Value Disposal Records (&gt;₹10L)</h3>
  <?php foreach ($highValueDisposals as $d): ?>
    <p style="font-size:13px; padding:10px; border-bottom:1px solid var(--line);">
      <a href="/settlements/<?= esc($d['settlement_id']) ?>">₹<?= number_format((float) $d['final_sale_value'], 2) ?></a>
      <span style="color:var(--ink-3); font-size:11px;">
        RV ₹<?= $d['reserve_value'] !== null ? number_format((float) $d['reserve_value'], 2) : '—' ?>
        · Variance ₹<?= number_format((float) $d['variance'], 2) ?>
        · <?= esc(strtoupper($d['sale_format'])) ?>
      </span>
    </p>
  <?php endforeach; ?>
  <?php if (empty($highValueDisposals)): ?><p style="font-size:12px; color:var(--ink-3);">None</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
