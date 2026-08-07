<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">AML Monitoring (BR-54)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Visible to SaaS Admin only, per PR-31 — not shown to any <?= tsx_term('Tenant Admin') ?> or the flagged User.</p>

  <h3 style="font-size:15px; margin-top:24px;">Open Flags</h3>
  <?php foreach ($open as $f): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc(strtoupper(str_replace('_', ' ', $f['flag_type']))) ?> — <?= esc($f['mobile_number']) ?></p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;"><?= esc($f['detail']) ?></p>
      <form method="post" action="/admin/aml/<?= esc($f['id']) ?>/review"><?= csrf_field() ?>
        <textarea name="notes" placeholder="Review notes (required)" required rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <input type="text" name="str_reference" placeholder="STR reference (required only if filing)"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
        <button type="submit" name="decision" value="no_action" class="btn btn-ghost" style="font-size:12px;">Explainable — No Action</button>
        <button type="submit" name="decision" value="str_filed" class="btn btn-emerald" style="font-size:12px;">STR Filed</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($open)): ?><p style="font-size:12px; color:var(--ink-3);">No open flags.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Reviewed History</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Type</th><th>User</th><th>Outcome</th><th>Reviewer</th><th>Reviewed At</th>
    </tr>
    <?php foreach ($reviewed as $r): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc(str_replace('_', ' ', $r['flag_type'])) ?></td>
      <td><?= esc($r['mobile_number']) ?></td>
      <td><?= $r['status'] === 'reviewed_str_filed' ? 'STR Filed (' . esc($r['str_reference']) . ')' : 'No Action' ?></td>
      <td><?= esc($r['reviewer_mobile'] ?? '—') ?></td>
      <td><?= esc(substr($r['reviewed_at'], 0, 16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($reviewed)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
