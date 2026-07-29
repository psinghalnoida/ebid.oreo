<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:600px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <p style="font-size:11px; color:var(--ink-3);"><a href="/admin/rules" style="color:var(--emerald);">← Rules &amp; Specifications</a></p>
  <h1 style="font-size:22px;"><?= esc($rule['title']) ?></h1>
  <p style="font-size:11px; color:var(--ink-3); font-family:'IBM Plex Mono',monospace;">
    <?= $rule['rule_key'] ? esc($rule['rule_key']) . ' · ' : '' ?>v<?= esc($rule['version']) ?>
    <?= $rule['rule_key'] ? '· wired into live enforcement' : '· freeform governance rule (no code effect)' ?>
  </p>

  <form method="post" action="/admin/rules/<?= esc($rule['id']) ?>/edit" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Title</label>
    <input type="text" name="title" value="<?= esc($rule['title']) ?>" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box;">

    <label style="font-size:12px; color:var(--ink-3);">Statement</label>
    <textarea name="statement" rows="3" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;"><?= esc($rule['statement']) ?></textarea>

    <label style="font-size:12px; color:var(--ink-3);">Logic</label>
    <textarea name="logic" rows="2" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;"><?= esc($rule['logic']) ?></textarea>

    <?php if ($rule['rule_key']): ?>
      <label style="font-size:12px; color:var(--ink-3);">Live Numeric Value</label>
      <input type="number" step="0.0001" name="numeric_value" value="<?= esc($rule['numeric_value']) ?>" required
        style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box;">
      <p style="font-size:11px; color:var(--ink-3); margin:-8px 0 14px;">Changing this changes live application behavior on save — not just this record.</p>
    <?php else: ?>
      <input type="hidden" name="numeric_value" value="">
    <?php endif; ?>

    <label style="font-size:12px; color:var(--ink-3);">Reason for Modification <span style="color:var(--emerald-deep);">(required)</span></label>
    <textarea name="reason_for_modification" rows="2" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;" placeholder="Why is this changing?"></textarea>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Save &amp; Version</button>
  </form>

  <?php if (!empty($revisions)): ?>
  <h3 style="font-size:14px; margin-top:32px;">Revision History</h3>
  <table style="width:100%; border-collapse:collapse; font-size:11.5px; margin-top:8px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Ver</th><th>When</th><th>Value</th><th>Reason</th>
    </tr>
    <?php foreach ($revisions as $rev): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">v<?= esc($rev['version']) ?></td>
      <td><?= esc($rev['created_at']) ?></td>
      <td><?= $rev['numeric_value'] !== null ? esc($rev['numeric_value']) : '—' ?></td>
      <td><?= esc($rev['reason_for_modification']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
