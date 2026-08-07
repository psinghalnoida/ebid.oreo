<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<style {csp-style-nonce}>
  .browse-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:20px;}
  .filter-bar{display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;}
  .filter-chip{font-size:12px; padding:8px 14px; border-radius:100px; border:1px solid var(--line); color:var(--ink-2); text-decoration:none;}
  .filter-chip.active{background:var(--emerald); color:#fff; border-color:var(--emerald);}
  .clv-badge{position:absolute; top:8px; left:8px; background:var(--emerald); color:#fff; font-size:10px; padding:3px 8px; border-radius:100px; font-weight:700;}
  @media(max-width:900px){ .browse-grid{grid-template-columns:repeat(2,1fr);} }
</style>
<main style="max-width:1240px; margin:0 auto; padding:40px 24px;">
  <h1 style="font-size:26px;">Browse All <?= tsx_term('Listing', false, true) ?></h1>

  <form method="get" action="/listings" style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
    <input type="text" name="q" value="<?= esc($q ?? '') ?>" placeholder="Search category..."
      style="padding:9px 12px; border:1px solid var(--line); border-radius:8px; font-size:12px; min-width:160px;">
    <input type="text" name="location" value="<?= esc($location ?? '') ?>" placeholder="Location"
      style="padding:9px 12px; border:1px solid var(--line); border-radius:8px; font-size:12px; width:120px;">
    <input type="number" name="price_min" value="<?= esc($priceMin ?? '') ?>" placeholder="Min ₹"
      style="padding:9px 12px; border:1px solid var(--line); border-radius:8px; font-size:12px; width:100px;">
    <input type="number" name="price_max" value="<?= esc($priceMax ?? '') ?>" placeholder="Max ₹"
      style="padding:9px 12px; border:1px solid var(--line); border-radius:8px; font-size:12px; width:100px;">
    <select name="min_rating" style="padding:9px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">Any <?= strtolower(tsx_term('Seller')) ?> rating</option>
      <?php foreach ([3, 4, 5] as $r): ?>
        <option value="<?= $r ?>" <?= (string) ($minRating ?? '') === (string) $r ? 'selected' : '' ?>><?= $r ?>★+</option>
      <?php endforeach; ?>
    </select>
    <select name="condition" style="padding:9px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">Any condition</option>
      <?php foreach (['New', 'Used', 'Refurbished', 'For Parts'] as $c): ?>
        <option value="<?= esc($c) ?>" <?= ($condition ?? '') === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="posted" style="padding:9px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">Any time</option>
      <option value="24h" <?= ($posted ?? '') === '24h' ? 'selected' : '' ?>>Last 24 hours</option>
      <option value="7d" <?= ($posted ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30d" <?= ($posted ?? '') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
    </select>
    <select name="sort" style="padding:9px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recent first</option>
      <option value="ending_soon" <?= $sort === 'ending_soon' ? 'selected' : '' ?>>Ending soon</option>
      <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
      <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
      <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>><?= tsx_term('Seller') ?> rating</option>
    </select>
    <input type="hidden" name="category" value="<?= esc($selectedCategory ?? '') ?>">
    <input type="hidden" name="format" value="<?= esc($selectedFormat ?? '') ?>">
    <button type="submit" class="btn btn-emerald" style="font-size:12px;">Search</button>
  </form>

  <?php if (session()->get('logged_in_party_id')): ?>
  <form method="post" action="/my-searches" style="margin-top:8px; display:flex; gap:8px;"><?= csrf_field() ?>
    <?php foreach (['category' => $selectedCategory, 'format' => $selectedFormat, 'price_min' => $priceMin, 'price_max' => $priceMax, 'location' => $location, 'min_rating' => $minRating, 'condition' => $condition, 'posted' => $posted, 'sort' => $sort, 'q' => $q] as $k => $v): ?>
      <input type="hidden" name="<?= esc($k) ?>" value="<?= esc($v ?? '') ?>">
    <?php endforeach; ?>
    <input type="text" name="label" placeholder="Name this search" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Save this search</button>
  </form>
  <?php endif; ?>

  <div class="filter-bar">
    <a href="/listings" class="filter-chip <?= !$selectedCategory && !$selectedFormat ? 'active' : '' ?>">All</a>
    <?php foreach (['easy', 'buy_now', 'express', 'tender'] as $fmt): ?>
      <a href="/listings?format=<?= esc($fmt) ?>" class="filter-chip <?= $selectedFormat === $fmt ? 'active' : '' ?>"><?= esc(strtoupper($fmt)) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-bar">
    <?php foreach ($allCategories as $cat): ?>
      <a href="/listings?category=<?= esc(urlencode($cat)) ?>" class="filter-chip <?= $selectedCategory === $cat ? 'active' : '' ?>"><?= esc($cat) ?></a>
    <?php endforeach; ?>
  </div>

  <p style="font-size:12px; color:var(--ink-3); margin-top:12px;">Showing <?= count($listings) ?> of <?= (int) $total ?> active <?= strtolower(tsx_term('Listing', false, true)) ?></p>

  <div class="browse-grid">
    <?php if (empty($listings)): ?>
      <p style="grid-column:1/-1; color:var(--ink-3); font-size:14px; padding:40px; text-align:center;">No <?= strtolower(tsx_term('Listing', false, true)) ?> match this filter.</p>
    <?php endif; ?>
    <?php foreach ($listings as $item): ?>
      <a href="/listings/<?= esc($item['listing_id']) ?>" style="text-decoration:none; color:inherit;">
        <div style="border:1px solid var(--line); border-radius:16px; overflow:hidden; position:relative;">
          <?php if ($item['clvMatch']): ?><span class="clv-badge">Matches your preferences</span><?php endif; ?>
          <div style="height:160px; background:#F1F1EE; <?= $item['photo_path'] ? 'background-image:url(/'.esc($item['photo_path']).');background-size:cover;background-position:center;' : '' ?>"></div>
          <div style="padding:14px;">
            <p style="font-size:10px; color:var(--ink-3); text-transform:uppercase; margin:0 0 4px;"><?= esc(strtoupper($item['sale_format'])) ?> · <?= esc($item['physical_condition']) ?></p>
            <p style="font-size:14px; font-weight:700; margin:0 0 6px;"><?= esc($item['category']) ?></p>
            <p style="font-size:15px; font-weight:800; color:var(--emerald); margin:0 0 4px;">₹<?= number_format((float) $item['display_price'], 0) ?></p>
            <p style="font-size:11px; color:var(--ink-3); margin:0;"><?= tsx_term('Seller') ?> <?= number_format((float) $item['seller_star_rating'], 1) ?>★</p>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <?php
      $qs = http_build_query(array_filter([
        'category' => $selectedCategory, 'format' => $selectedFormat, 'price_min' => $priceMin, 'price_max' => $priceMax,
        'location' => $location, 'min_rating' => $minRating, 'condition' => $condition, 'posted' => $posted, 'sort' => $sort, 'q' => $q,
      ]));
    ?>
    <div style="display:flex; gap:8px; margin-top:24px; font-size:12px; flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?<?= $qs ?>&page=<?= $p ?>" style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
