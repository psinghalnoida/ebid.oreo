<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Tenant Media Waivers (BR-60)</h1>

  <h3 style="font-size:15px; margin-top:24px;">Pending Requests</h3>
  <?php foreach ($pending as $w): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($w['tenant_name']) ?> — <?= esc($w['category']) ?></p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;"><?= esc($w['business_justification']) ?></p>
      <form method="post" action="/admin/media-waivers/<?= esc($w['id']) ?>/decide">
        <textarea name="rationale" placeholder="Decision rationale (required)" required rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" name="decision" value="approve" class="btn btn-emerald" style="font-size:12px;">Approve (12mo)</button>
        <button type="submit" name="decision" value="decline" class="btn btn-ghost" style="font-size:12px;">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3);">None pending.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Active Waivers</h3>
  <?php foreach ($active as $w): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($w['tenant_name']) ?> — <?= esc($w['category']) ?></p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;">Expires <?= esc(substr($w['expires_at'], 0, 10)) ?></p>
      <form method="post" action="/admin/media-waivers/<?= esc($w['id']) ?>/revoke">
        <input type="text" name="reason" placeholder="Revocation reason (required)" required
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
        <button type="submit" class="btn btn-ghost" style="font-size:12px; color:#B5482F;">Revoke Immediately</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($active)): ?><p style="font-size:12px; color:var(--ink-3);">None active.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
