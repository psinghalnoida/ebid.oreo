<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:24px;"><?= tsx_term('Super Admin') ?> Dashboard</h1>

  <div style="display:flex; gap:16px; margin:20px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= count($tenants) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Whitelisted <?= tsx_term('Tenant', false, true) ?></p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= esc($openDisputes) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Open Disputes</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= esc($stalledSettlements) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Stalled Settlements</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:24px; font-weight:800; margin:0;"><?= esc($openAmlFlags) ?></p>
      <p style="font-size:12px; color:var(--ink-3);">Open AML Flags</p>
    </div>
  </div>

  <a href="/admin/tenants" class="btn btn-emerald"><?= tsx_term('Tenant', false, true) ?></a>
  <a href="/admin/users" class="btn btn-ghost" style="margin-left:8px;">Users</a>
  <a href="/admin/lots" class="btn btn-ghost" style="margin-left:8px;">Lot Directory</a>
  <a href="/admin/trading-sessions" class="btn btn-ghost" style="margin-left:8px;">Trading Session Directory</a>
  <a href="/admin/alerts" class="btn btn-ghost" style="margin-left:8px;">Alerts</a>
  <a href="/admin/delist-seller" class="btn btn-ghost" style="margin-left:8px;">Delist <?= tsx_term('Seller') ?> (Confirmed Fraud)</a>
  <a href="/admin/audit-log" class="btn btn-ghost" style="margin-left:8px;">Audit Log</a>
  <a href="/admin/audit-log/export" class="btn btn-ghost" style="margin-left:8px;">Statutory Export</a>
  <a href="/admin/media-waivers" class="btn btn-ghost" style="margin-left:8px;">Media Waivers</a>
  <a href="/admin/aml" class="btn btn-ghost" style="margin-left:8px;">AML Monitoring</a>
  <a href="/admin/payout-reviews" class="btn btn-ghost" style="margin-left:8px;">Payout Reviews</a>
  <a href="/admin/tenant-invoices" class="btn btn-ghost" style="margin-left:8px;"><?= tsx_term('Tenant') ?> Invoices</a>
  <a href="/admin/rating-reviews" class="btn btn-ghost" style="margin-left:8px;">Rating Reviews</a>
  <a href="/admin/chargebacks" class="btn btn-ghost" style="margin-left:8px;">Chargebacks</a>
  <a href="/admin/consent-audit" class="btn btn-ghost" style="margin-left:8px;">Consent Audit</a>
  <a href="/admin/rules" class="btn btn-ghost" style="margin-left:8px;">Rules &amp; Specifications</a>
  <a href="/admin/kyc" class="btn btn-ghost" style="margin-left:8px;">KYC Review Queue</a>

  <h3 style="font-size:15px; margin-top:28px;">Today</h3>
  <div style="display:flex; gap:16px; margin:10px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $bidsPlacedToday ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Bids Placed</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $salesClosedToday ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Sales Closed</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $disputesFiledToday ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Disputes Filed</p>
    </div>
    <?php foreach ($listingsByFormatToday as $lf): ?>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $lf['cnt'] ?></p>
      <p style="font-size:11px; color:var(--ink-3);">New <?= esc(strtoupper($lf['sale_format'])) ?> <?= tsx_term('Sale Event', false, true) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <h3 style="font-size:16px; margin-top:28px;"><?= tsx_term('Tenant', false, true) ?></h3>
  <table style="width:100%; border-collapse:collapse; margin-top:10px; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Name</th><th>Class</th><th>Subdomain</th><th>Subscription Tier</th>
    </tr>
    <?php foreach ($tenants as $t): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><a href="/admin/tenants/<?= esc($t['id']) ?>" style="color:var(--emerald);"><?= esc($t['name']) ?></a></td>
      <td><?= esc($t['tenant_class']) ?></td>
      <td><?= esc($t['subdomain']) ?></td>
      <td><?= esc(ucwords(str_replace('_', ' ', $t['subscription_tier']))) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <h3 style="font-size:15px; margin-top:28px;">Stalled Settlements — Aging</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Amount</th><th>Since</th><th>Days Stalled</th>
    </tr>
    <?php foreach ($stalledAging as $s): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">₹<?= number_format((float) $s['final_price'], 2) ?></td>
      <td><?= esc(substr($s['created_at'], 0, 10)) ?></td>
      <td><?= (int) $s['days_stalled'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($stalledAging)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">High-Value Disposal Records (BR-49, &gt;₹10L, platform-wide)</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;"><?= tsx_term('Tenant') ?></th><th>Format</th><th>RV</th><th>Final Value</th><th>Variance</th><th>Date</th>
    </tr>
    <?php foreach ($highValueDisposals as $d): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($d['tenant_name']) ?></td>
      <td><?= esc(strtoupper($d['sale_format'])) ?></td>
      <td>₹<?= $d['reserve_value'] !== null ? number_format((float) $d['reserve_value'], 2) : '—' ?></td>
      <td>₹<?= number_format((float) $d['final_sale_value'], 2) ?></td>
      <td>₹<?= number_format((float) $d['variance'], 2) ?></td>
      <td><?= esc(substr($d['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($highValueDisposals)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/admin/logout" style="color:var(--ink-3); font-size:12px;">Log out of <?= tsx_term('Super Admin') ?></a></p>
</main>
<?= $this->endSection() ?>
