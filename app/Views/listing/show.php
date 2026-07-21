<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <span class="pc-badge" style="background:var(--emerald-soft); color:var(--emerald-deep); padding:5px 12px; border-radius:100px; font-size:11px; font-weight:700;">
    <?= esc(strtoupper($listing['status'])) ?>
  </span>
  <h1 style="font-size:26px; margin:12px 0 4px;"><?= esc($listing['category']) ?><?= $listing['subcategory'] ? ' / ' . esc($listing['subcategory']) : '' ?></h1>
  <p style="color:var(--ink-3); font-size:13px;">Lot ID: <?= esc($listing['id']) ?></p>

  <table style="width:100%; border-collapse:collapse; margin:20px 0; font-size:14px;">
    <tr><td style="padding:8px 0; color:var(--ink-3); width:180px;">Condition</td><td><?= esc($listing['physical_condition']) ?></td></tr>
    <tr><td style="padding:8px 0; color:var(--ink-3);">Quantity</td><td><?= esc($listing['quantity']) ?> (<?= esc($listing['quantity_basis']) ?>)</td></tr>
    <tr><td style="padding:8px 0; color:var(--ink-3);">Make/Model</td><td><?= esc($listing['make_model'] ?? '—') ?></td></tr>
    <tr><td style="padding:8px 0; color:var(--ink-3);">Location</td><td><?= esc($listing['yard_location_address']) ?> — <?= esc($listing['yard_location_pin']) ?></td></tr>
    <?php if ($listing['rejection_reason']): ?>
    <tr><td style="padding:8px 0; color:var(--ink-3);">Rejection Reason</td><td style="color:#B5482F;"><?= esc($listing['rejection_reason']) ?></td></tr>
    <?php endif; ?>
  </table>

  <?php if ($listing['status'] === 'inventory'): ?>
    <form method="post" action="/listings/<?= esc($listing['id']) ?>/submit-for-approval">
      <button type="submit" class="btn btn-emerald">Submit for Approval</button>
    </form>
  <?php endif; ?>

  <?php if ($listing['status'] === 'pending_approval'): ?>
    <div style="background:var(--line-soft); padding:16px; border-radius:12px; margin-top:16px;">
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;">Tenant Admin actions — requires the tenant_admin role for this listing's tenant (BR-09)</p>
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/approve" style="display:inline;">
        <button type="submit" class="btn btn-emerald">Approve</button>
      </form>
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/reject" style="display:inline;">
        <button type="submit" class="btn btn-ghost">Reject</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($listing['status'] === 'upcoming' && !$saleEvent): ?>
    <div style="display:flex; gap:24px; margin-top:16px;">
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/sale-events" style="flex:1;">
        <input type="hidden" name="sale_format" value="easy">
        <label style="font-size:12px; color:var(--ink-3);">Reserve Value (₹) — Easy Auction</label>
        <input type="number" name="reserve_value" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <button type="submit" class="btn btn-emerald">Attach Easy Auction</button>
      </form>
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/sale-events" style="flex:1;">
        <input type="hidden" name="sale_format" value="buy_now">
        <label style="font-size:12px; color:var(--ink-3);">Expected Value (₹) — Buy-Now</label>
        <input type="number" name="expected_value" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <button type="submit" class="btn btn-ghost">Attach Buy-Now</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($saleEvent): ?>
    <div style="border:1px solid var(--line); border-radius:16px; padding:22px; margin-top:20px;">
      <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px;"><?= esc($saleEvent['ern']) ?> · <?= esc(strtoupper($saleEvent['sale_format'])) ?> · <?= esc(strtoupper($saleEvent['status'])) ?></p>

      <?php if ($saleEvent['sale_format'] === 'buy_now'): ?>
        <?php if ($saleEvent['status'] === 'closed_sold' && $saleEvent['current_price']): ?>
          <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float) $saleEvent['current_price'], 2) ?> <span style="font-size:14px; color:var(--emerald); font-weight:600;">accepted</span></p>
          <p style="font-size:12px; color:var(--ink-3);">Expected Value was ₹<?= number_format((float) $saleEvent['expected_value'], 2) ?></p>
        <?php else: ?>
          <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float) $saleEvent['expected_value'], 2) ?> <span style="font-size:14px; color:var(--ink-3); font-weight:400;">expected</span></p>
          <p style="font-size:12px; color:var(--ink-3);">EMD required: ₹<?= number_format((float) $saleEvent['expected_value'] * 0.10, 2) ?> (10% of EV, BR-27)</p>
        <?php endif; ?>
      <?php else: ?>
        <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float)($saleEvent['current_price'] ?? $saleEvent['reserve_value']), 2) ?></p>
        <p style="font-size:12px; color:var(--ink-3);">Reserve: ₹<?= number_format((float) $saleEvent['reserve_value'], 2) ?> · EMD required: ₹<?= number_format((float) $saleEvent['reserve_value'] * 0.10, 2) ?></p>
      <?php endif; ?>

      <?php if ($saleEvent['status'] === 'pending_approval'): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/approve" style="margin-top:14px;">
          <p style="font-size:12px; color:var(--ink-3);">Tenant Admin action (BR-09)</p>
          <button type="submit" class="btn btn-emerald">Approve Sale Event</button>
        </form>
      <?php endif; ?>

      <?php if ($saleEvent['status'] === 'grace_period'): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/dev-force-freeze" style="margin-top:14px;">
          <p style="font-size:12px; color:var(--ink-3);">⚠️ Dev-only: skips the real 60-minute BR-14 grace window for demo purposes</p>
          <button type="submit" class="btn btn-ghost">Force-freeze to Active (dev)</button>
        </form>
      <?php endif; ?>

      <?php if ($saleEvent['status'] === 'active' && $saleEvent['sale_format'] === 'easy'): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/dev-fund-emd" style="margin-top:16px;">
          <p style="font-size:12px; color:var(--ink-3);">⚠️ Dev-only: simulates cleared EMD payment (no payment gateway connected yet)</p>
          <button type="submit" class="btn btn-ghost">Fund EMD (dev)</button>
        </form>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/bid" style="margin-top:10px; display:flex; gap:8px;">
          <input type="number" name="amount" placeholder="Bid amount" required step="0.01"
            style="flex:1; padding:12px; border:1px solid var(--line); border-radius:10px;">
          <button type="submit" class="btn btn-emerald">Bid</button>
        </form>
      <?php endif; ?>

      <?php if ($saleEvent['status'] === 'active' && $saleEvent['sale_format'] === 'buy_now'): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/dev-fund-emd-offer" style="margin-top:16px;">
          <p style="font-size:12px; color:var(--ink-3);">⚠️ Dev-only: simulates cleared EMD payment (no payment gateway connected yet)</p>
          <button type="submit" class="btn btn-ghost">Fund EMD (dev)</button>
        </form>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/offers" style="margin-top:10px; display:flex; gap:8px;">
          <input type="number" name="amount" placeholder="Offer amount" required step="0.01"
            style="flex:1; padding:12px; border:1px solid var(--line); border-radius:10px;">
          <button type="submit" class="btn btn-emerald">Submit Offer</button>
        </form>

        <?php if (!empty($offers)): ?>
        <div style="margin-top:20px; border-top:1px solid var(--line); padding-top:16px;">
          <p style="font-size:12px; color:var(--ink-3); font-weight:700; text-transform:uppercase; margin-bottom:10px;">Offers Received (BR-16: buyer identity masked pre-acceptance)</p>
          <?php foreach ($offers as $offer): ?>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px dashed var(--line);">
            <div>
              <span style="font-weight:700;">₹<?= number_format((float) $offer['amount'], 2) ?></span>
              <span style="font-size:11px; color:var(--ink-3); margin-left:8px; text-transform:uppercase;"><?= esc($offer['status']) ?></span>
              <?php if ($offer['seller_selection_reason']): ?>
                <div style="font-size:11.5px; color:var(--ink-3); margin-top:2px;">Reason: <?= esc($offer['seller_selection_reason']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($offer['status'] === 'submitted'): ?>
            <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/offers/<?= esc($offer['id']) ?>/accept">
              <input type="text" name="reason" placeholder="Reason (required if not highest)" style="font-size:11px; padding:6px; border:1px solid var(--line); border-radius:6px; margin-right:6px;">
              <button type="submit" class="btn btn-emerald" style="padding:6px 12px; font-size:12px;">Accept</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
