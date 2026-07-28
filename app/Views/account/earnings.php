<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:600px; padding:40px 24px;">
  <h1 style="font-size:22px;">Earnings Summary</h1>

  <h3 style="font-size:14px; margin-top:20px;">This Month</h3>
  <div style="display:flex; gap:12px;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $thisMonth['sale_count'] ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Sales</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;">₹<?= number_format((float) $thisMonth['total_sales'], 2) ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Total Sales</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;">₹<?= number_format((float) $thisMonth['total_fees'], 2) ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Fees Paid</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;">₹<?= number_format((float) $thisMonth['total_tds'], 2) ?></p>
      <p style="font-size:11px; color:var(--ink-3);">TDS Deducted (BR-53)</p>
    </div>
  </div>

  <h3 style="font-size:14px; margin-top:24px;">Year to Date</h3>
  <div style="display:flex; gap:12px;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $ytd['sale_count'] ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Sales</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;">₹<?= number_format((float) $ytd['net_earnings'], 2) ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Net Received (after fees + TDS)</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $pendingCount ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Pending Settlements</p>
    </div>
  </div>

  <h3 style="font-size:14px; margin-top:24px;">Payout Account</h3>
  <p style="font-size:13px;">
    <?= $party['payout_bank_account_number'] ? '····' . substr($party['payout_bank_account_number'], -4) . ' (' . esc($party['payout_bank_ifsc']) . ')' : 'None on file' ?>
    — <a href="/payout-bank" style="color:var(--emerald);">manage</a>
  </p>
  <p style="font-size:11px; color:var(--ink-3); margin-top:8px;">
    "Net Received" reflects the platform fee split (BR-33) and TDS deduction (BR-53, Section 194-O, 10%) —
    actual payout to your bank account requires the real payment gateway, which is not yet connected
    (deferred to post-deployment, per the project's own decision).
  </p>
</main>
<?= $this->endSection() ?>
