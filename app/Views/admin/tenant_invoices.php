<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;"><?= tsx_term('Tenant') ?> Monthly Invoices (BR-32/33)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Consolidated monthly bill per non-CoCo-Starter <?= tsx_term('Tenant') ?>, covering that period's <?= tsx_term('Seller') ?>-Pays Success Fees. Marking paid is a manual SaaS Admin action — no automated dunning exists yet.</p>

  <h3 style="font-size:15px; margin-top:24px;">Pending</h3>
  <?php foreach ($pending as $inv): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($inv['invoice_number']) ?> — ₹<?= number_format((float) $inv['total_amount'] + (float) $inv['gst_amount'], 2) ?> (incl. GST)</p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;">Period: <?= esc(substr($inv['period_start'], 0, 10)) ?> – <?= esc(substr($inv['period_end'], 0, 10)) ?></p>
      <form method="post" action="/admin/tenant-invoices/<?= esc($inv['id']) ?>/mark-paid"><?= csrf_field() ?>
        <button type="submit" class="btn btn-emerald" style="font-size:12px;">Mark Paid</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3);">None pending.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/admin" style="color:var(--ink-3); font-size:12px;">&larr; Back to Dashboard</a></p>
</main>
<?= $this->endSection() ?>
