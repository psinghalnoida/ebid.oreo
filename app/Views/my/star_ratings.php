<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Star Ratings</h1>
  <p style="color:var(--ink-3); font-size:13px;">Your reputation as a <?= strtolower(tsx_term('Buyer')) ?> and as a <?= strtolower(tsx_term('Seller')) ?> are tracked separately, even if you do both.</p>

  <div style="display:flex; gap:16px; margin-top:20px;">
    <div style="flex:1; border:1px solid var(--line); border-radius:14px; padding:20px;">
      <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; margin:0 0 8px;"><?= tsx_term('Buyer') ?> Rating</p>
      <p style="font-size:32px; font-weight:800; margin:0;">★ <?= number_format((float) $party['star_rating'], 1) ?></p>
      <?php if ($party['shadow_banned_at_buyer']): ?>
        <p style="font-size:12px; color:#B5482F; margin:10px 0 0;">Shadow-banned since <?= esc($party['shadow_banned_at_buyer']) ?></p>
        <?php if ($party['crawl_back_active_buyer']): ?>
          <p style="font-size:12px; color:var(--ink-3); margin:4px 0 0;">Crawl-Back in progress: <?= (int) $party['crawl_back_clean_completed_buyer'] ?> / <?= (int) ($party['crawl_back_clean_required_buyer'] ?? 0) ?> clean transactions completed</p>
        <?php endif; ?>
      <?php else: ?>
        <p style="font-size:12px; color:var(--emerald); margin:10px 0 0;">In good standing</p>
      <?php endif; ?>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:14px; padding:20px;">
      <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase; margin:0 0 8px;"><?= tsx_term('Seller') ?> Rating</p>
      <p style="font-size:32px; font-weight:800; margin:0;">★ <?= number_format((float) $party['seller_star_rating'], 1) ?></p>
      <?php if ($party['shadow_banned_at_seller']): ?>
        <p style="font-size:12px; color:#B5482F; margin:10px 0 0;">Shadow-banned since <?= esc($party['shadow_banned_at_seller']) ?></p>
        <?php if ($party['crawl_back_active_seller']): ?>
          <p style="font-size:12px; color:var(--ink-3); margin:4px 0 0;">Crawl-Back in progress: <?= (int) $party['crawl_back_clean_completed_seller'] ?> / <?= (int) ($party['crawl_back_clean_required_seller'] ?? 0) ?> clean transactions completed</p>
        <?php endif; ?>
      <?php else: ?>
        <p style="font-size:12px; color:var(--emerald); margin:10px 0 0;">In good standing</p>
      <?php endif; ?>
    </div>
  </div>

  <p style="margin-top:20px;"><a href="/my-rating-history" style="color:var(--emerald); font-size:13px;">View full rating history &rarr;</a></p>
  <p style="margin-top:8px;"><a href="/profile" style="color:var(--ink-3); font-size:12px;">&larr; Back to Profile</a></p>
</main>
<?= $this->endSection() ?>
