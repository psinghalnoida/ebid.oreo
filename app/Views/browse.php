<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<style>
  .browse-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:20px;}
  .filter-bar{display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;}
  .filter-chip{font-size:12px; padding:8px 14px; border-radius:100px; border:1px solid var(--line); color:var(--ink-2); text-decoration:none;}
  .filter-chip.active{background:var(--emerald); color:#fff; border-color:var(--emerald);}
  @media(max-width:900px){ .browse-grid{grid-template-columns:repeat(2,1fr);} }
</style>
<main style="max-width:1240px; margin:0 auto; padding:40px 24px;">
  <h1 style="font-size:26px;">Browse All Listings</h1>

  <div class="filter-bar">
    <a href="/browse" class="filter-chip <?= !$selectedCategory && !$selectedFormat ? 'active' : '' ?>">All</a>
    <?php foreach (['easy', 'buy_now', 'express', 'tender'] as $fmt): ?>
      <a href="/browse?format=<?= esc($fmt) ?>" class="filter-chip <?= $selectedFormat === $fmt ? 'active' : '' ?>"><?= esc(strtoupper($fmt)) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-bar">
    <?php foreach ($allCategories as $cat): ?>
      <a href="/browse?category=<?= esc(urlencode($cat)) ?>" class="filter-chip <?= $selectedCategory === $cat ? 'active' : '' ?>"><?= esc($cat) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-grid">
    <?php if (empty($listings)): ?>
      <p style="grid-column:1/-1; color:var(--ink-3); font-size:14px; padding:40px; text-align:center;">No listings match this filter.</p>
    <?php endif; ?>
    <?php foreach ($listings as $item): ?>
      <a href="/listings/<?= esc($item['listing_id']) ?>" style="text-decoration:none; color:inherit;">
        <div style="border:1px solid var(--line); border-radius:16px; overflow:hidden;">
          <div style="height:160px; background:#F1F1EE; <?= $item['photo_path'] ? 'background-image:url(/'.esc($item['photo_path']).');background-size:cover;background-position:center;' : '' ?>"></div>
          <div style="padding:14px;">
            <p style="font-size:10px; color:var(--ink-3); text-transform:uppercase; margin:0 0 4px;"><?= esc(strtoupper($item['sale_format'])) ?></p>
            <p style="font-size:14px; font-weight:700; margin:0 0 6px;"><?= esc($item['category']) ?></p>
            <p style="font-size:15px; font-weight:800; color:var(--emerald); margin:0;">₹<?= number_format((float) ($item['current_price'] ?? $item['reserve_value'] ?? $item['expected_value']), 0) ?></p>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</main>
<?= $this->endSection() ?>
