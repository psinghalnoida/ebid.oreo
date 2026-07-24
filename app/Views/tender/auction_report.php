<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Auction Report</h1>
  <p style="color:var(--ink-3); font-size:13px;"><?= esc($saleEvent['ern']) ?></p>

  <h3 style="font-size:15px; margin-top:20px;">Eligible Participants (<?= count($report['eligible']) ?>)</h3>
  <?php foreach ($report['eligible'] as $e): ?>
    <p style="font-size:13px; padding:6px 0; border-bottom:1px solid var(--line);"><?= esc($e['party_id']) ?> — <?= esc($e['source']) ?></p>
  <?php endforeach; ?>

  <h3 style="font-size:15px; margin-top:20px;">Bid History (<?= count($report['bidHistory']) ?>)</h3>
  <?php foreach ($report['bidHistory'] as $b): ?>
    <p style="font-size:13px; padding:6px 0; border-bottom:1px solid var(--line);">₹<?= number_format((float) $b['amount'], 2) ?> — <?= esc($b['standing']) ?> — <?= esc($b['placed_at']) ?></p>
  <?php endforeach; ?>

  <h3 style="font-size:15px; margin-top:20px;">EMD Log (<?= count($report['emdLog']) ?>)</h3>
  <?php foreach ($report['emdLog'] as $log): ?>
    <p style="font-size:13px; padding:6px 0; border-bottom:1px solid var(--line);">₹<?= number_format((float) $log['amount'], 2) ?> — <?= esc($log['payment_location_note'] ?? $log['no_emd_reason']) ?></p>
  <?php endforeach; ?>

  <h3 style="font-size:15px; margin-top:20px;">Review History (<?= count($report['reviewRounds']) ?> rounds)</h3>
  <?php foreach ($report['reviewRounds'] as $r): ?>
    <p style="font-size:13px; padding:6px 0; border-bottom:1px solid var(--line);">Round <?= esc($r['round_number']) ?> — <?= esc(strtoupper($r['status'])) ?><?= $r['rejection_reason'] ? ' — ' . esc($r['rejection_reason']) : '' ?></p>
  <?php endforeach; ?>
</main>
<?= $this->endSection() ?>
