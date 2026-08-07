<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;">Billing — <?= esc($tenant['name']) ?></h1>
  <p style="font-size:12px; color:var(--ink-3);"><?= tsx_term('Seller') ?>-Pays Success Fees on this TSX's Trading Sessions accrue here, and are consolidated into one GST-compliant invoice per calendar month.</p>

  <h3 style="font-size:15px; margin-top:24px;">Unbilled this period — ₹<?= number_format(array_sum(array_column($unbilled, 'amount')), 2) ?></h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Amount</th><th>Recorded</th>
    </tr>
    <?php foreach ($unbilled as $entry): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">₹<?= number_format((float) $entry['amount'], 2) ?></td>
      <td><?= esc(substr($entry['created_at'], 0, 16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($unbilled)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Monthly Invoices</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Invoice</th><th>Period</th><th>Total (incl. GST)</th><th>Status</th>
    </tr>
    <?php foreach ($invoices as $inv): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($inv['invoice_number']) ?></td>
      <td><?= esc(substr($inv['period_start'], 0, 10)) ?> – <?= esc(substr($inv['period_end'], 0, 10)) ?></td>
      <td>₹<?= number_format((float) $inv['total_amount'] + (float) $inv['gst_amount'], 2) ?></td>
      <td><?= esc(ucfirst($inv['status'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($invoices)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/tenants/<?= esc($tenant['id']) ?>/dashboard" style="color:var(--ink-3); font-size:12px;">&larr; Back to <?= tsx_term('Tenant') ?> Dashboard</a></p>
</main>
<?= $this->endSection() ?>
