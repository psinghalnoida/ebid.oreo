<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:60px 24px;">
  <h1 style="font-size:22px;">Audit Log Integrity</h1>
  <?php if ($brokenAt === null): ?>
    <div style="background:var(--emerald-soft); color:var(--emerald-deep); padding:20px; border-radius:12px; margin-top:16px;">
      <p style="font-size:14px; font-weight:700; margin:0;">✓ Chain verified clean</p>
      <p style="font-size:12px; margin:6px 0 0;">Every record's hash was independently recomputed from its actual stored content and matches what's on file. No tampering detected.</p>
    </div>
  <?php else: ?>
    <div style="background:#FBE8E4; color:#B5482F; padding:20px; border-radius:12px; margin-top:16px;">
      <p style="font-size:14px; font-weight:700; margin:0;">⚠ Integrity broken at record #<?= esc($brokenAt) ?></p>
      <p style="font-size:12px; margin:6px 0 0;">This record's stored hash does not match what its actual content would produce. Investigate immediately.</p>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
