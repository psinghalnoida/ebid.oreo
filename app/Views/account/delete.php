<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Delete Account</h1>

  <?php if (!empty($party['deletion_requested_at'])): ?>
    <p style="font-size:13px; color:var(--amber, #9C5B1F);">
      Deletion requested on <?= esc(substr($party['deletion_requested_at'], 0, 10)) ?> — your account will be archived
      30 days from that date unless cancelled first.
    </p>
    <form method="post" action="/account/delete/cancel">
      <button type="submit" class="btn btn-emerald">Cancel Deletion Request</button>
    </form>
  <?php else: ?>
    <p style="font-size:13px; color:var(--ink-3);">
      Requesting deletion gives you a 30-day grace period to change your mind. After 30 days, your account is
      archived and you can no longer log in.
    </p>
    <form method="post" action="/account/delete/request" style="margin-top:16px;">
      <textarea name="reason" placeholder="Reason (optional)" rows="3"
        style="display:block; width:100%; padding:8px; margin-bottom:12px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
      <button type="submit" class="btn btn-ghost" style="color:#B5482F;">Request Account Deletion</button>
    </form>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
