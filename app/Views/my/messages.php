<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Messages</h1>
  <p style="color:var(--ink-3); font-size:13px;">Sent by <?= strtolower(tsx_term('Seller')) ?>s whose listings matched your saved preferences.</p>

  <?php foreach ($messages as $m): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:14px; margin-top:12px; <?= $m['read_at'] ? '' : 'background:var(--emerald-soft, #E4EFE6);' ?>">
      <div style="display:flex; justify-content:space-between; align-items:baseline;">
        <p style="font-size:11px; color:var(--ink-3); margin:0; text-transform:uppercase;">Re: <a href="/listings/<?= esc($m['listing_id']) ?>" style="color:inherit;"><?= esc($m['category']) ?><?= $m['subcategory'] ? ' — '.esc($m['subcategory']) : '' ?></a></p>
        <p style="font-size:11px; color:var(--ink-3); margin:0;"><?= esc($m['delivered_at']) ?></p>
      </div>
      <p style="font-size:13.5px; margin:8px 0 0;"><?= esc($m['message_body']) ?></p>
      <?php if (!$m['read_at']): ?>
        <form method="post" action="/my-messages/<?= esc($m['recipient_id']) ?>/read" style="margin-top:8px;"><?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost" style="font-size:11px;">Mark as read</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (empty($messages)): ?><p style="font-size:13px; color:var(--ink-3); margin-top:12px;">No messages yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
