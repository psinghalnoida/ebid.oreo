<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Pending Rating Downgrades (BR-36)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Downgrades between 5.0 and 2.1★ need <?= tsx_term('Tenant Admin') ?> approval; at 2.0★ or below, both <?= tsx_term('Tenant Admin') ?> and <?= tsx_term('Super Admin') ?>.</p>

  <?php foreach ($pending as $e): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">
        <?= esc($e['mobile_number']) ?> — <?= $e['rating_role'] === 'star_rating' ? tsx_term('Buyer') : tsx_term('Seller') ?> rating
        <?= number_format((float) $e['previous_value'], 1) ?>★ → <?= number_format((float) $e['new_value'], 1) ?>★
      </p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;"><?= esc($e['reason']) ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:0 0 10px;">
        <?= $e['tenant_admin_approved_at'] ? '✓ ' . tsx_term('Tenant Admin') . ' approved' : 'Awaiting ' . tsx_term('Tenant Admin') ?>
        <?= (float) $e['new_value'] <= 2.0 ? (' · ' . ($e['super_admin_approved_at'] ? '✓ ' . tsx_term('Super Admin') . ' approved' : 'Awaiting ' . tsx_term('Super Admin'))) : '' ?>
      </p>
      <form method="post" action="/admin/rating-reviews/<?= esc($e['id']) ?>/approve">
        <button type="submit" class="btn btn-emerald" style="font-size:12px;">Approve</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3);">None pending.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
