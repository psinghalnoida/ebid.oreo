<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <h1 style="font-size:22px;">Invoices</h1>
  <p style="font-size:12px; color:var(--ink-3);">GST-compliant invoices (BR-56) for every completed Buy-Now, Express, and Easy transaction you've been party to, as buyer or seller.</p>

  <table style="width:100%; border-collapse:collapse; margin-top:16px; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Invoice #</th><th>Format</th><th>Amount</th><th>GST</th><th>Total</th><th>Date</th><th></th>
    </tr>
    <?php foreach ($invoices as $inv): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><a href="/settlements/<?= esc($inv['settlement_id']) ?>" style="color:var(--emerald);"><?= esc($inv['invoice_number']) ?></a></td>
      <td><?= esc(strtoupper($inv['sale_format'])) ?></td>
      <td>₹<?= number_format((float) $inv['base_amount'], 2) ?></td>
      <td>₹<?= number_format((float) $inv['gst_amount'], 2) ?> (<?= esc($inv['gst_rate_percent']) ?>%)</td>
      <td><strong>₹<?= number_format((float) $inv['total_amount'], 2) ?></strong></td>
      <td><?= esc(substr($inv['created_at'], 0, 10)) ?></td>
      <td><a href="/account/invoices/<?= esc($inv['id']) ?>/pdf" style="color:var(--emerald);">PDF &darr;</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($invoices)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No invoices yet.</p><?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:8px; margin-top:16px; font-size:12px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>" style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <p style="font-size:11px; color:var(--ink-3); margin-top:8px;"><?= (int) $total ?> total</p>
</main>
<?= $this->endSection() ?>
