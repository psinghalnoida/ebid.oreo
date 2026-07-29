<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:800px; padding:40px 24px;">
  <h1 style="font-size:22px;">KYC Review Queue</h1>
  <p style="font-size:12px; color:var(--ink-3);">PR-15 — dossiers submitted and awaiting a decision.</p>

  <table style="width:100%; border-collapse:collapse; font-size:12px; margin-top:16px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Mobile</th><th>Entity Type</th><th>Name</th><th>Submitted</th><th></th>
    </tr>
    <?php foreach ($parties as $p): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><?= esc($p['mobile_number']) ?></td>
      <td><?= esc(ucfirst($p['entity_type'])) ?></td>
      <td><?= esc($p['entity_type'] === 'individual' ? ($p['full_name'] ?? '—') : ($p['org_cin'] ?? '—')) ?></td>
      <td><?= esc(substr($p['kyc_submitted_at'] ?? '', 0, 16)) ?></td>
      <td><a href="/admin/kyc/<?= esc($p['id']) ?>" style="color:var(--emerald);">Review →</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($parties)): ?>
      <tr><td colspan="5" style="padding:20px 0; color:var(--ink-3); text-align:center;">No dossiers awaiting review.</td></tr>
    <?php endif; ?>
  </table>
</main>
<?= $this->endSection() ?>
