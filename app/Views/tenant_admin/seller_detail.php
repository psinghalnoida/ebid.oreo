<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;"><?= esc($seller['mobile_number']) ?></h1>
  <p style="font-size:13px; color:var(--ink-3);">
    <?= tsx_term('Seller') ?> rating <?= number_format((float) $seller['seller_star_rating'], 1) ?>★ · <?= tsx_term('Buyer') ?> rating <?= number_format((float) $seller['star_rating'], 1) ?>★ ·
    KYC: <?= esc($seller['kyc_status']) ?>
  </p>

  <?php if ($seller['seller_delisted_at']): ?>
    <p style="font-size:13px; color:#B5482F; margin-top:10px;">Delisted for confirmed fraud on <?= esc(substr($seller['seller_delisted_at'], 0, 10)) ?>: <?= esc($seller['seller_delisted_reason']) ?></p>
  <?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Standing Review</h3>
  <?php if ($openCase): ?>
    <p style="font-size:13px;">An open case exists — <a href="/admin/standing-review/<?= esc($openCase['id']) ?>" style="color:var(--emerald);">view / rule on it</a></p>
  <?php else: ?>
    <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/sellers/<?= esc($seller['id']) ?>/initiate-review">
      <textarea name="reason" placeholder="Reason for opening a Standing Review case (required)" required rows="2"
        style="display:block; width:100%; padding:8px; margin:8px 0; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
      <button type="submit" class="btn btn-ghost" style="font-size:12px;">Initiate Standing Review Case</button>
    </form>
    <p style="font-size:11px; color:var(--ink-3); margin-top:6px;">Note: cases also open automatically once complaints exceed 10, or on the <?= strtolower(tsx_term('Seller')) ?>'s annual anniversary — this is for a <?= tsx_term('Tenant Admin') ?> to act ahead of that threshold on their own judgment.</p>
  <?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Violation History (real named rating consequences, BR-35)</h3>
  <?php foreach ($violations as $v): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <?= esc(substr($v['created_at'] ?? '', 0, 10)) ?> — <?= esc($v['reason']) ?>
      (<?= number_format((float) $v['previous_value'], 1) ?>★ → <?= number_format((float) $v['new_value'], 1) ?>★, <?= esc($v['status']) ?>)
    </p>
  <?php endforeach; ?>
  <?php if (empty($violations)): ?><p style="font-size:12px; color:var(--ink-3);">None recorded.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Sales on This <?= tsx_term('Tenant') ?></h3>
  <?php foreach ($sales as $sale): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);">
      <a href="/settlements/<?= esc($sale['id']) ?>"><?= esc(strtoupper($sale['sale_format'])) ?> — <?= esc($sale['category']) ?></a>
      — ₹<?= number_format((float) $sale['final_price'], 2) ?> — <?= esc($sale['status']) ?>
    </p>
  <?php endforeach; ?>
  <?php if (empty($sales)): ?><p style="font-size:12px; color:var(--ink-3);">None yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
