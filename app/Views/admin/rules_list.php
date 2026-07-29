<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Rules &amp; Specifications</h1>
  <p style="color:var(--ink-3); font-size:12px;">
    PR-04 — the Super Admin's live rules registry. Rows with a <strong>Rule Key</strong> are wired
    into real application code (editing the value here changes live enforcement immediately).
    Rows without one are freeform governance rules — versioned and audited the same way, but with no
    direct code effect. <a href="/admin/rules/new" style="color:var(--emerald);">Define a new rule →</a>
  </p>

  <table style="width:100%; border-collapse:collapse; font-size:12px; margin-top:16px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Rule Key</th><th>Title</th><th>Current Value</th><th>Version</th><th></th>
    </tr>
    <?php foreach ($rules as $r): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0; font-family:'IBM Plex Mono',monospace; font-size:11px;"><?= $r['rule_key'] ? esc($r['rule_key']) : '<span style="color:var(--ink-3);">— freeform —</span>' ?></td>
      <td><?= esc($r['title']) ?></td>
      <td><?= $r['numeric_value'] !== null ? esc(rtrim(rtrim(number_format((float) $r['numeric_value'], 4), '0'), '.')) : '—' ?></td>
      <td>v<?= esc($r['version']) ?></td>
      <td><a href="/admin/rules/<?= esc($r['id']) ?>" style="color:var(--emerald);">View / Edit →</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
<?= $this->endSection() ?>
