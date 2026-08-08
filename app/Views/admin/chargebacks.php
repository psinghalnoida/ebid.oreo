<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Chargeback Handling (BR-52/PR-30)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Card-funded EMD chargebacks — evidence auto-assembled on filing; the representment outcome and any integrity-flag review are recorded here once known.</p>

  <h3 style="font-size:15px; margin-top:24px;">Flagged: filed against an already-approved forfeiture</h3>
  <p style="font-size:11px; color:var(--ink-3); margin:0 0 8px;">BR-52: a genuine account-integrity concern, reviewed independently of the representment outcome below.</p>
  <?php foreach ($pendingIntegrityReview as $c): ?>
    <div style="border:1px solid #B5482F; border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">₹<?= number_format((float) $c['amount'], 2) ?> — <?= esc($c['mobile_number']) ?> (<?= esc($c['sale_format']) ?>)</p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 6px;">Filed: <?= esc($c['filed_reason']) ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:0 0 10px;">Forfeited at <?= esc(substr((string) ($c['evidence_package'] ? json_decode($c['evidence_package'], true)['forfeitureApprovalChain']['forfeitedAt'] ?? '' : ''), 0, 16)) ?></p>
      <form method="post" action="/admin/chargebacks/<?= esc($c['id']) ?>/review-integrity"><?= csrf_field() ?>
        <textarea name="notes" placeholder="Review notes (required)" required rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <label style="font-size:12px; display:block; margin-bottom:8px;">
          <input type="checkbox" name="apply_rating_consequence" value="1"> Apply the BR-35 rating penalty (-2.0 Trader★) for this confirmed account-integrity event
        </label>
        <button type="submit" class="btn btn-ghost" style="font-size:12px;">Record Review</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pendingIntegrityReview)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Awaiting representment outcome</h3>
  <p style="font-size:11px; color:var(--ink-3); margin:0 0 8px;">Evidence package assembled and ready; record the payment gateway's decision once it arrives.</p>
  <?php foreach ($openRepresentment as $c): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">₹<?= number_format((float) $c['amount'], 2) ?> — <?= esc($c['mobile_number']) ?> (<?= esc($c['sale_format']) ?>)</p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;">Filed: <?= esc($c['filed_reason']) ?></p>
      <form method="post" action="/admin/chargebacks/<?= esc($c['id']) ?>/decide"><?= csrf_field() ?>
        <textarea name="notes" placeholder="Notes (required)" required rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" name="outcome" value="won" class="btn btn-emerald" style="font-size:12px;">Representment Won</button>
        <button type="submit" name="outcome" value="lost" class="btn btn-ghost" style="font-size:12px;">Representment Lost</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($openRepresentment)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Resolved</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">User</th><th>Amount</th><th>Outcome</th><th>Resolved At</th>
    </tr>
    <?php foreach ($resolved as $r): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($r['mobile_number']) ?></td>
      <td>₹<?= number_format((float) $r['amount'], 2) ?></td>
      <td><?= $r['status'] === 'resolved_won' ? 'Won' : 'Lost' ?></td>
      <td><?= esc(substr((string) $r['representment_resolved_at'], 0, 16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($resolved)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
