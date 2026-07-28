<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Payout Holds (BR-50)</h1>
  <p style="font-size:12px; color:var(--ink-3);">High-value payouts pending release to a recently-changed bank account. Also reviewable by the relevant Tenant Admin directly on each settlement's own page.</p>

  <h3 style="font-size:15px; margin-top:24px;">Pending</h3>
  <?php foreach ($pending as $h): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">
        <?= esc($h['tenant_name']) ?> — ₹<?= number_format((float) $h['amount'], 2) ?> — <?= esc($h['full_name'] ?? $h['mobile_number']) ?> (<?= esc($h['mobile_number']) ?>)
      </p>
      <p style="font-size:11.5px; color:var(--ink-3); margin:0 0 8px;">Flagged <?= esc(substr($h['created_at'], 0, 19)) ?> · <a href="/settlements/<?= esc($h['settlement_id']) ?>" style="color:var(--emerald);">View settlement</a></p>
      <form method="post" action="/admin/payout-holds/<?= esc($h['id']) ?>/decide">
        <textarea name="notes" placeholder="Review notes" rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" name="outcome" value="release" class="btn btn-emerald" style="font-size:12px;">Release Payout</button>
        <button type="submit" name="outcome" value="reject" class="btn btn-ghost" style="font-size:12px;">Reject — Hold for Investigation</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3);">None pending.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Recently Reviewed</h3>
  <?php foreach ($reviewed as $h): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; margin-top:8px;">
      <p style="font-size:12.5px; font-weight:700; margin:0 0 4px;">
        <?= esc($h['tenant_name']) ?> — ₹<?= number_format((float) $h['amount'], 2) ?> — <?= esc($h['full_name'] ?? $h['mobile_number']) ?> — <?= esc(strtoupper($h['status'])) ?>
      </p>
      <?php if ($h['review_notes']): ?><p style="font-size:11.5px; color:var(--ink-3); margin:0;"><?= esc($h['review_notes']) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (empty($reviewed)): ?><p style="font-size:12px; color:var(--ink-3);">None reviewed yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
