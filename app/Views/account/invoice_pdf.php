<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; color: #1a1a1a; font-size: 13px; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .muted { color: #666; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; margin-top: 24px; }
  th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
  .total-row td { font-weight: bold; font-size: 15px; border-top: 2px solid #333; }
  .parties { margin-top: 24px; display: table; width: 100%; }
  .party { display: table-cell; width: 50%; vertical-align: top; }
</style>
</head>
<body>
  <h1>Tax Invoice</h1>
  <p class="muted">Invoice #<?= esc($invoice['invoice_number']) ?> &middot; Issued <?= esc(substr($invoice['created_at'], 0, 10)) ?></p>

  <div class="parties">
    <div class="party">
      <p class="muted">Issued By</p>
      <p><?= esc($invoice['issued_by_name']) ?></p>
    </div>
    <div class="party">
      <p class="muted">Issued To</p>
      <p><?= esc($invoice['issued_to_name']) ?></p>
    </div>
  </div>

  <p class="muted" style="margin-top:16px;">
    Sale reference: <?= esc($invoice['ern']) ?> &middot; Format: <?= esc(strtoupper($invoice['sale_format'])) ?> &middot; Category: <?= esc($invoice['category']) ?>
  </p>

  <table>
    <tr><th>Description</th><th>Amount</th></tr>
    <tr><td>Platform Commission (Base Amount)</td><td>&#8377;<?= number_format((float) $invoice['base_amount'], 2) ?></td></tr>
    <tr><td>GST (<?= esc($invoice['gst_rate_percent']) ?>%)</td><td>&#8377;<?= number_format((float) $invoice['gst_amount'], 2) ?></td></tr>
    <tr class="total-row"><td>Total</td><td>&#8377;<?= number_format((float) $invoice['total_amount'], 2) ?></td></tr>
  </table>

  <p class="muted" style="margin-top:32px;">This is a system-generated invoice under BR-56 and does not require a physical signature.</p>
</body>
</html>
