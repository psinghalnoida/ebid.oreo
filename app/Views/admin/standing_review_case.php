<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:560px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Standing Review Case (BR-61)</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    <?= tsx_term('Seller') ?>: <?= esc($seller['mobile_number'] ?? 'Unknown') ?> — <?= esc($dispute['description']) ?>
  </p>
  <p style="font-size:12px; color:var(--ink-3);">Status: <?= esc($dispute['status']) ?></p>

  <?php if ($dispute['status'] === 'filed' || $dispute['status'] === 'evidence_window'): ?>
  <form method="post" action="/admin/standing-review/<?= esc($dispute['id']) ?>/rule" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Ruling as <?= tsx_term('Tenant Admin') ?> for</label>
    <select name="tenant_id" required style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach ($tenants as $t): ?>
        <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label style="font-size:12px; color:var(--ink-3);">Outcome</label>
    <select name="outcome" required style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">
      <option value="no_action">No Action</option>
      <option value="escalation_consequence">Escalation-Ladder Consequence</option>
      <option value="suspension">Suspension of <?= tsx_term('Seller') ?> Privileges</option>
    </select>

    <label style="font-size:12px; color:var(--ink-3);">Rating Consequence (★, only if escalation)</label>
    <input type="number" step="0.1" name="rating_consequence" placeholder="e.g. 0.5"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Rationale (required)</label>
    <textarea name="rationale" rows="4" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Issue Ruling</button>
  </form>
  <?php else: ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:20px;">
      <p style="font-size:13px; margin:0 0 6px;"><strong>Outcome:</strong> <?= esc($dispute['ruling_outcome']) ?></p>
      <p style="font-size:13px; margin:0;"><strong>Rationale:</strong> <?= esc($dispute['ruling_rationale']) ?></p>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
