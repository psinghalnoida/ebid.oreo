<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Favorites</h1>
  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:16px;">
    <?php foreach ($favorites as $f): ?>
      <div style="border:1px solid var(--line); border-radius:16px; overflow:hidden;">
        <a href="/listings/<?= esc($f['listing_id']) ?>" style="text-decoration:none; color:inherit;">
          <div style="height:140px; background:#F1F1EE; <?= $f['photo_path'] ? 'background-image:url(/'.esc($f['photo_path']).');background-size:cover;background-position:center;' : '' ?>"></div>
          <div style="padding:12px;">
            <p style="font-size:10px; color:var(--ink-3); text-transform:uppercase; margin:0 0 4px;"><?= esc(strtoupper($f['sale_format'] ?? '')) ?></p>
            <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($f['category']) ?></p>
            <p style="font-size:14px; font-weight:800; color:var(--emerald); margin:0;">₹<?= number_format((float) ($f['display_price'] ?? 0), 0) ?></p>
          </div>
        </a>
        <form method="post" action="/listings/<?= esc($f['listing_id']) ?>/unfavorite" style="padding:0 12px 12px;"><?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost" style="font-size:11px; width:100%;">Remove</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($favorites)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No favorites yet — browse <a href="/listings">listings</a> and add some.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
