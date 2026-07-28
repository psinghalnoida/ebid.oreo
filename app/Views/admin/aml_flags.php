<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">AML Monitoring (BR-54)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Visible only here — never to the flagged User or any Tenant Admin, per BR-54.</p>

  <h3 style="font-size:15px; margin-top:24px;">Open Flags</h3>
  <?php foreach ($open as $f): ?>
    <?php $detail = json_decode($f['detail'], true) ?? []; ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">
        <?= esc(strtoupper(str_replace('_', ' ', $f['pattern_type']))) ?>
        — <?= esc($f['full_name'] ?? $f['mobile_number']) ?> (<?= esc($f['mobile_number']) ?>)
      </p>
      <p style="font-size:11.5px; color:var(--ink-3); margin:0 0 4px;">Flagged <?= esc(substr($f['created_at'], 0, 19)) ?></p>
      <?php if ($f['pattern_type'] === 'rapid_deposit_release_no_activity'): ?>
        <p style="font-size:12px; margin:0 0 8px;">
          Sale event <?= esc($detail['saleEventId'] ?? '—') ?> (<?= esc(strtoupper($detail['saleFormat'] ?? '')) ?>) —
          ₹<?= number_format((float) ($detail['amount'] ?? 0), 2) ?> held <?= esc($detail['heldAt'] ?? '') ?>,
          released <?= esc($detail['releasedAt'] ?? '') ?>, zero bids/offers placed.
        </p>
      <?php elseif ($f['pattern_type'] === 'shared_external_reference'): ?>
        <p style="font-size:12px; margin:0 0 8px;">
          External reference <code><?= esc($f['external_reference']) ?></code> also used by
          <?= count($detail['otherPartyIds'] ?? []) ?> other account(s).
        </p>
      <?php endif; ?>

      <form method="post" action="/admin/aml-flags/<?= esc($f['id']) ?>/decide">
        <textarea name="notes" placeholder="Review notes (why explainable, or why escalated)" rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <label style="font-size:12px; color:var(--ink-3); display:block; margin-bottom:6px;">
          <input type="checkbox" name="str_filed" value="1"> Suspicious Transaction Report filed under PMLA
        </label>
        <input type="text" name="str_reference" placeholder="STR filing reference (if filed)"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
        <button type="submit" name="outcome" value="dismissed" class="btn btn-ghost" style="font-size:12px;">Dismiss — Explainable</button>
        <button type="submit" name="outcome" value="escalated" class="btn btn-emerald" style="font-size:12px;">Escalate</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($open)): ?><p style="font-size:12px; color:var(--ink-3);">None open.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Recently Reviewed</h3>
  <?php foreach ($reviewed as $f): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; margin-top:8px;">
      <p style="font-size:12.5px; font-weight:700; margin:0 0 4px;">
        <?= esc(strtoupper(str_replace('_', ' ', $f['pattern_type']))) ?>
        — <?= esc($f['full_name'] ?? $f['mobile_number']) ?> — <?= esc(strtoupper($f['status'])) ?>
        <?php if (in_array($f['str_filed'], [true, 't', 1, '1'], true)): ?><span style="color:#B5482F;"> · STR filed</span><?php endif; ?>
      </p>
      <?php if ($f['review_notes']): ?><p style="font-size:11.5px; color:var(--ink-3); margin:0;"><?= esc($f['review_notes']) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (empty($reviewed)): ?><p style="font-size:12px; color:var(--ink-3);">None reviewed yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
