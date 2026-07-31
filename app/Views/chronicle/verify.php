<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:760px; padding:40px 24px;">
  <h1 style="font-size:22px;">Chronicle Verification</h1>
  <p style="font-size:12px; color:var(--ink-3);">Section 7.10 (ADWITIX_Master.docx) — this page requires no login; it's reachable only with the exact token from the QR code or verification link on the certified PDF.</p>

  <?php if ($hashMatches): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:12px; border-radius:8px; margin-top:16px;">✓ Digital Verification: this record's content hash matches what was certified at generation. Not altered.</p>
  <?php else: ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:12px; border-radius:8px; margin-top:16px;">⚠ Digital Verification FAILED: this record's content does not match its certified hash.</p>
  <?php endif; ?>

  <table style="width:100%; border-collapse:collapse; margin-top:20px; font-size:13px;">
    <tr><td style="padding:6px 0; color:var(--ink-3);">Reference Number</td><td style="padding:6px 0; font-weight:700;"><?= esc($chronicle['reference_number']) ?></td></tr>
    <tr><td style="padding:6px 0; color:var(--ink-3);">Certified</td><td style="padding:6px 0;"><?= esc(substr($chronicle['generated_at'], 0, 16)) ?> UTC</td></tr>
    <tr><td style="padding:6px 0; color:var(--ink-3);">Version</td><td style="padding:6px 0;"><?= esc($chronicle['version']) ?></td></tr>
    <tr><td style="padding:6px 0; color:var(--ink-3);">Trading Session</td><td style="padding:6px 0;"><?= esc($reportData['saleEvent']['ern']) ?></td></tr>
  </table>

  <p style="margin-top:20px;"><a href="/chronicle/verify/<?= esc($chronicle['verification_token']) ?>/pdf" class="btn btn-emerald">Download Certified PDF</a></p>

  <h3 style="font-size:15px; margin-top:28px;">Supporting Evidence</h3>
  <?php if (empty($media)): ?>
    <p style="font-size:12px; color:var(--ink-3);">No photographs or documents attached to this Lot.</p>
  <?php else: ?>
    <?php foreach ($media as $m): ?>
      <p style="font-size:12px; padding:6px 0; border-bottom:1px solid var(--line);"><?= esc($m['original_filename'] ?: $m['file_path']) ?><?= $m['is_primary'] ? ' (primary)' : '' ?></p>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Audit-Trail Excerpt</h3>
  <?php if (empty($reportData['timeline'])): ?>
    <p style="font-size:12px; color:var(--ink-3);">No timeline entries recorded.</p>
  <?php else: ?>
    <?php foreach ($reportData['timeline'] as $event): ?>
      <p style="font-size:12px; padding:6px 0; border-bottom:1px solid var(--line);"><?= esc(substr($event['occurredAt'], 0, 19)) ?> UTC — <?= esc($event['eventType']) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>

  <p style="font-size:11px; color:var(--ink-3); margin-top:20px;">Chain reference: <?= esc(substr($reportData['auditChainRecordHash'] ?? '', 0, 16)) ?>&hellip; — cross-checkable against the platform's own immutable audit trail (BR-05).</p>
</main>
<?= $this->endSection() ?>
