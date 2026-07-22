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
  <p style="color:var(--ink-3); font-size:13px;">Lot ID: <?= esc($listing['id']) ?> · Media: <?= esc(strtoupper($listing['media_tier'])) ?></p>

  <?php if (!empty($media)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:8px; margin:16px 0;">
      <?php foreach ($media as $m): ?>
        <div style="position:relative;">
          <img src="/<?= esc($m['file_path']) ?>" style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; border:2px solid <?= $m['is_primary'] ? 'var(--emerald)' : 'var(--line)' ?>;">
          <?php if ($m['is_primary']): ?><span style="position:absolute; top:4px; left:4px; background:var(--emerald); color:#fff; font-size:9px; padding:2px 6px; border-radius:100px;">PRIMARY</span><?php endif; ?>
          <?php if (!empty($isOwner) && !$m['is_primary']): ?>
            <form method="post" action="/listings/<?= esc($listing['id']) ?>/media/<?= esc($m['id']) ?>/set-primary">
              <button type="submit" style="font-size:9px; margin-top:2px; width:100%; background:none; border:1px solid var(--line); border-radius:6px; cursor:pointer;">Set primary</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p style="font-size:12px; color:<?= (int) $listing['media_count'] < $minPhotos ? '#B5482F' : 'var(--ink-3)' ?>;">
    <?= (int) $listing['media_count'] ?> / <?= $minPhotos ?> minimum photos (BR-11) — max 50
  </p>

  <?php if (!empty($isOwner) && in_array($listing['status'], ['inventory', 'pending_approval'], true)): ?>
    <form method="post" action="/listings/<?= esc($listing['id']) ?>/media" enctype="multipart/form-data" style="margin:10px 0 20px;">
      <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" required
        style="display:block; width:100%; padding:10px; border:1px dashed var(--line); border-radius:10px; margin-bottom:8px;">
      <input type="hidden" name="gps_lat" id="gpsLat_<?= esc($listing['id']) ?>">
      <input type="hidden" name="gps_lng" id="gpsLng_<?= esc($listing['id']) ?>">
      <button type="submit" class="btn btn-ghost">Upload Photos</button>
      <p style="font-size:10.5px; color:var(--ink-3); margin-top:6px;">
        BR-45: location is captured automatically if your browser allows it — this is a best-effort web substitute for a native app's automatic capture, not a guarantee.
      </p>
    </form>
    <script>
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
          var latEl = document.getElementById('gpsLat_<?= esc($listing['id']) ?>');
          var lngEl = document.getElementById('gpsLng_<?= esc($listing['id']) ?>');
          if (latEl) latEl.value = pos.coords.latitude;
          if (lngEl) lngEl.value = pos.coords.longitude;
        });
      }
    </script>
  <?php endif; ?>

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
    <div style="display:flex; gap:16px; margin-top:16px;">
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/sale-events" style="flex:1;">
        <input type="hidden" name="sale_format" value="easy">
        <label style="font-size:12px; color:var(--ink-3);">Reserve Value (₹) — Easy Auction</label>
        <input type="number" name="reserve_value" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <label style="font-size:12px; color:var(--ink-3);">Start (BR-12: you set the schedule)</label>
        <input type="datetime-local" name="scheduled_start_at" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <label style="font-size:12px; color:var(--ink-3);">End</label>
        <input type="datetime-local" name="scheduled_end_at" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <button type="submit" class="btn btn-emerald">Attach Easy</button>
      </form>
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/sale-events" style="flex:1;">
        <input type="hidden" name="sale_format" value="buy_now">
        <label style="font-size:12px; color:var(--ink-3);">Expected Value (₹) — Buy-Now</label>
        <input type="number" name="expected_value" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <button type="submit" class="btn btn-ghost">Attach Buy-Now</button>
      </form>
      <form method="post" action="/listings/<?= esc($listing['id']) ?>/sale-events" style="flex:1;">
        <input type="hidden" name="sale_format" value="express">
        <label style="font-size:12px; color:var(--ink-3);">Reserve Value (₹) — Express Auction</label>
        <input type="number" name="reserve_value" required
          style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
        <button type="submit" class="btn btn-ghost">Attach Express</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($saleEvent): ?>
    <div style="border:1px solid var(--line); border-radius:16px; padding:22px; margin-top:20px;">
      <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px;"><?= esc($saleEvent['ern']) ?> · <?= esc(strtoupper($saleEvent['sale_format'])) ?> · <?= esc(strtoupper($saleEvent['status'])) ?></p>

      <?php if (!empty($settlementRecord)): ?>
        <a href="/settlements/<?= esc($settlementRecord['id']) ?>" class="btn btn-emerald" style="display:inline-block; margin-bottom:12px; font-size:12px; padding:8px 14px;">
          Go to Settlement (<?= esc(strtoupper($settlementRecord['status'])) ?>)
        </a>
      <?php endif; ?>

      <?php if ($saleEvent['sale_format'] === 'buy_now'): ?>
        <?php if ($saleEvent['status'] === 'closed_sold' && $saleEvent['current_price']): ?>
          <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float) $saleEvent['current_price'], 2) ?> <span style="font-size:14px; color:var(--emerald); font-weight:600;">accepted</span></p>
          <p style="font-size:12px; color:var(--ink-3);">Expected Value was ₹<?= number_format((float) $saleEvent['expected_value'], 2) ?></p>
        <?php else: ?>
          <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float) $saleEvent['expected_value'], 2) ?> <span style="font-size:14px; color:var(--ink-3); font-weight:400;">expected</span></p>
          <p style="font-size:12px; color:var(--ink-3);">EMD required: ₹<?= number_format((float) $saleEvent['expected_value'] * 0.10, 2) ?> (10% of EV, BR-27)</p>
        <?php endif; ?>
      <?php elseif ($saleEvent['sale_format'] === 'express'): ?>
        <p style="font-size:32px; font-weight:800; margin:4px 0;">₹<?= number_format((float)($saleEvent['current_price'] ?? $saleEvent['reserve_value']), 2) ?></p>
        <p style="font-size:12px; color:var(--ink-3);">Reserve: ₹<?= number_format((float) $saleEvent['reserve_value'], 2) ?> · EMD required: ₹<?= number_format((float) $saleEvent['reserve_value'] * 0.10, 2) ?></p>
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

      <?php if ($saleEvent['status'] === 'active' && $saleEvent['sale_format'] === 'express'): ?>
        <div style="margin-top:16px; background:var(--line-soft); padding:12px 16px; border-radius:10px;">
          <p style="font-size:13px; font-weight:600;">Pledges: <?= esc($expressState['pledgeCount']) ?> / 3 required to open bidding (PR-11)</p>
          <?php if (!$expressState['biddingOpen'] && $expressState['pledgeCount'] < 3): ?>
            <p style="font-size:12px; color:var(--ink-3); margin:4px 0 0;">Bidding opens automatically the instant the 3rd distinct buyer pledges — no seller/admin action needed.</p>
          <?php endif; ?>
        </div>

        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/pledge" style="margin-top:12px;">
          <p style="font-size:12px; color:var(--ink-3);">⚠️ Dev-only: simulates cleared EMD payment. The pledge-count/trigger logic itself is real.</p>
          <button type="submit" class="btn btn-ghost">Pledge Reserve (fund EMD)</button>
        </form>

        <?php if ($expressState['biddingOpen']): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/express-bid" style="margin-top:10px; display:flex; gap:8px;">
          <input type="number" name="amount" placeholder="Bid amount" required step="0.01"
            style="flex:1; padding:12px; border:1px solid var(--line); border-radius:10px;">
          <button type="submit" class="btn btn-emerald">Bid</button>
        </form>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/dev-force-close-bidding" style="margin-top:10px;">
          <p style="font-size:12px; color:var(--ink-3);">⚠️ Dev-only: forces the real 1-hour bidding window to expire immediately (Tenant Admin action)</p>
          <button type="submit" class="btn btn-ghost">Force-close Bidding (dev)</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
