<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;">Alerts</h1>
  <p style="color:var(--ink-3); font-size:12px;">Everything platform-wide currently queued for a Super Admin decision, in one place.</p>

  <h3 style="font-size:15px; margin-top:24px;">Open AML Flags (<?= count($amlFlags) ?>)</h3>
  <?php foreach ($amlFlags as $f): ?>
    <p style="font-size:13px; border-top:1px solid var(--line); padding:8px 0;">
      <?= esc($f['pattern_type'] ?? $f['flag_type'] ?? '') ?> — <?= esc($f['mobile_number']) ?>
      <a href="/admin/aml" style="color:var(--emerald); margin-left:8px;">Review</a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($amlFlags)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Pending Payout Reviews (<?= count($pendingPayoutReviews) ?>)</h3>
  <?php foreach ($pendingPayoutReviews as $r): ?>
    <p style="font-size:13px; border-top:1px solid var(--line); padding:8px 0;">
      ₹<?= number_format((float) $r['amount'], 2) ?> — <?= esc($r['release_type']) ?>
      <a href="/admin/payout-reviews" style="color:var(--emerald); margin-left:8px;">Review</a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingPayoutReviews)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Pending Rating Reviews (<?= count($pendingRatingReviews) ?>)</h3>
  <?php foreach ($pendingRatingReviews as $r): ?>
    <p style="font-size:13px; border-top:1px solid var(--line); padding:8px 0;">
      <?= number_format((float) $r['previous_value'], 1) ?>★ → <?= number_format((float) $r['new_value'], 1) ?>★ (<?= esc($r['status']) ?>)
      <a href="/admin/rating-reviews" style="color:var(--emerald); margin-left:8px;">Review</a>
    </p>
  <?php endforeach; ?>
  <?php if (empty($pendingRatingReviews)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Open Disputes (<?= count($openDisputes) ?>)</h3>
  <?php foreach ($openDisputes as $d): ?>
    <p style="font-size:13px; border-top:1px solid var(--line); padding:8px 0;">
      <?= esc($d['category']) ?> — <?= esc($d['status']) ?> — filed <?= esc(substr($d['created_at'], 0, 10)) ?>
    </p>
  <?php endforeach; ?>
  <?php if (empty($openDisputes)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Stalled Settlements (<?= count($stalledSettlements) ?>)</h3>
  <?php foreach ($stalledSettlements as $s): ?>
    <p style="font-size:13px; border-top:1px solid var(--line); padding:8px 0;">
      ₹<?= number_format((float) $s['final_price'], 2) ?> — since <?= esc(substr($s['created_at'], 0, 10)) ?>
    </p>
  <?php endforeach; ?>
  <?php if (empty($stalledSettlements)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <p style="margin-top:24px;"><a href="/admin" style="color:var(--ink-3); font-size:12px;">&larr; Back to Dashboard</a></p>
</main>
<?= $this->endSection() ?>
