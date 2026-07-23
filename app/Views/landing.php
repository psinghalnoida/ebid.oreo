<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
  .tag-strip{display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap;}
  .tag-chip{font-size:12px; font-weight:600; color:var(--emerald-deep); background:var(--emerald-soft); padding:6px 13px; border-radius:var(--radius-pill);}
  .hero{display:grid; grid-template-columns:1.05fr 0.95fr; gap:56px; padding:56px 0 60px; align-items:center;}
  .hero h1{font-size:52px; line-height:1.06; font-weight:800; margin:0 0 20px; letter-spacing:-1.5px;}
  .hero h1 em{font-style:normal; color:var(--emerald);}
  .hero p.lead{font-size:16.5px; line-height:1.65; color:var(--ink-2); max-width:460px; margin:0 0 30px;}
  .hero-ctas{display:flex; gap:12px; margin-bottom:38px;}
  .hero-stats{display:flex; gap:36px;}
  .hstat b{display:block; font-size:24px; font-weight:800; letter-spacing:-0.5px;}
  .hstat span{font-size:12px; color:var(--ink-3); font-weight:600;}

  .product-card{background:var(--card); border:1px solid var(--line); border-radius:20px; overflow:hidden; box-shadow:0 24px 48px -20px rgba(20,20,20,0.14);}
  .pc-photo{height:200px; background:linear-gradient(135deg,#F1F1EE 0%, #E7E7E1 100%); background-size:cover; background-position:center; position:relative; display:flex; align-items:flex-start; justify-content:space-between; padding:16px;}
  .pc-badge{font-size:11px; font-weight:700; padding:6px 12px; border-radius:var(--radius-pill); background:var(--amber-soft); color:#9C5B1F;}
  .pc-body{padding:20px;}
  .pc-body .lot{font-size:11px; color:var(--ink-3); font-weight:600; margin-bottom:4px;}
  .pc-body h3{margin:0 0 12px; font-size:16px; font-weight:700; letter-spacing:-0.2px;}
  .pc-row{display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;}
  .pc-row .k{font-size:11.5px; color:var(--ink-3); font-weight:600;}
  .pc-row .v{font-size:13.5px; font-weight:700;}
  .pc-row .v.big{font-size:22px; color:var(--emerald); font-weight:800;}
  .pc-cta{width:100%; padding:12px; background:var(--emerald); color:#fff; border:none; font-weight:700; font-family:'Sora',sans-serif; border-radius:var(--radius-pill); cursor:pointer; font-size:13.5px; text-align:center; display:block;}

  .listings-grid{display:grid; grid-template-columns:repeat(3, 1fr); gap:18px; margin-top:30px;}

  section.block{padding:50px 0; border-top:1px solid var(--line);}
  .eyebrow{font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--emerald); margin:0 0 10px;}
  h2.block-title{font-size:28px; font-weight:800; margin:0 0 10px; letter-spacing:-0.7px;}
  p.block-lead{font-size:14.5px; color:var(--ink-2); max-width:560px; line-height:1.65; margin:0 0 30px;}

  .format-grid{display:grid; grid-template-columns:repeat(4,1fr); gap:16px;}
  .fmt-card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:22px;}
  .fmt-icon{width:34px; height:34px; border-radius:10px; background:var(--emerald-soft); color:var(--emerald-deep); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; margin-bottom:14px;}
  .fmt-card h4{margin:0 0 6px; font-size:15px; font-weight:700;}
  .fmt-card p{margin:0; font-size:12.5px; color:var(--ink-2); line-height:1.55;}
  .fmt-card .fmt-tag{font-size:10.5px; font-weight:700; color:var(--ink-3); margin-top:12px; display:block;}

  .cat-grid{display:grid; grid-template-columns:repeat(6,1fr); gap:12px;}
  .cat-tile{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:18px 12px; text-align:center; font-size:12px; font-weight:600; color:var(--ink-2);}
  .cat-tile .n{display:block; font-size:20px; font-weight:800; color:var(--ink); margin-bottom:5px; letter-spacing:-0.4px;}

  .trust-wrap{display:grid; grid-template-columns:0.9fr 1.1fr; gap:44px; align-items:center;}
  .star-demo{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:24px;}
  .star-row{display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--line-soft);}
  .star-row:last-child{border-bottom:none;}
  .star-row .label{font-size:13px; font-weight:600; color:var(--ink-2);}
  .star-row .stars{font-weight:800; color:var(--amber); font-size:14px;}
  .trust-points{list-style:none; margin:0; padding:0;}
  .trust-points li{display:flex; gap:14px; margin-bottom:18px; align-items:flex-start;}
  .trust-points .mark{width:28px; height:28px; flex:none; background:var(--emerald); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px;}
  .trust-points b{display:block; font-size:14px; margin-bottom:3px;}
  .trust-points span{font-size:13px; color:var(--ink-2); line-height:1.5;}

  .cta-band{background:var(--emerald); color:#fff; border-radius:22px; padding:40px; display:flex; justify-content:space-between; align-items:center; margin:50px 0;}
  .cta-band h3{margin:0 0 6px; font-size:22px; font-weight:800; letter-spacing:-0.4px;}
  .cta-band p{margin:0; color:#CFE8DD; font-size:13.5px;}
  .cta-band .btn{background:#fff; color:var(--emerald-deep);}

  .empty-state{grid-column:1/-1; text-align:center; padding:50px 24px; background:var(--line-soft); border-radius:var(--radius); color:var(--ink-3); font-size:14px;}

  @media(max-width:900px){ .hero, .trust-wrap{grid-template-columns:1fr;} .format-grid, .cat-grid, .listings-grid{grid-template-columns:repeat(2,1fr);} }
</style>

<div class="hero">
  <div>
    <div class="tag-strip">
      <span class="tag-chip">Repossessed</span>
      <span class="tag-chip">Salvaged Claims</span>
      <span class="tag-chip">Industrial Surplus</span>
      <span class="tag-chip">Confiscated</span>
    </div>
    <h1>Salvage, sold <em>simply</em>.</h1>
    <p class="lead">eBid Hub is India's multi-tenant marketplace for salvage, surplus, and repossessed assets — three sale formats live today, one global identity, and a rating system that keeps every deal honest.</p>
    <div class="hero-ctas">
      <a href="#live-listings" class="btn btn-emerald">Browse Live Auctions</a>
      <a href="/listings/create" class="btn btn-ghost">List an Asset →</a>
    </div>
    <div class="hero-stats">
      <div class="hstat"><b><?= esc($totalActiveCount) ?></b><span>Live Right Now</span></div>
      <div class="hstat"><b>3</b><span>Sale Formats</span></div>
      <div class="hstat"><b>10%</b><span>Flat EMD Entry</span></div>
      <div class="hstat"><b>100%</b><span>Direct Settlement</span></div>
    </div>
  </div>

  <?php $hero = $activeListings[0] ?? null; ?>
  <?php if ($hero): ?>
    <a href="/listings/<?= esc($hero['listing_id']) ?>" style="text-decoration:none; color:inherit;">
      <div class="product-card">
        <div class="pc-photo" <?= $hero['photo_path'] ? 'style="background-image:url(/'.esc($hero['photo_path']).')"' : '' ?>>
          <span class="pc-badge"><?= esc(strtoupper($hero['sale_format'])) ?></span>
        </div>
        <div class="pc-body">
          <div class="lot">LOT #<?= esc($hero['ern']) ?> · PIN <?= esc($hero['yard_location_pin']) ?></div>
          <h3><?= esc($hero['category']) ?><?= $hero['subcategory'] ? ' — '.esc($hero['subcategory']) : '' ?></h3>
          <div class="pc-row"><span class="k">Current Price</span><span class="v big">₹<?= number_format((float)($hero['current_price'] ?? $hero['reserve_value'] ?? $hero['expected_value']), 0) ?></span></div>
          <div class="pc-row"><span class="k">Condition</span><span class="v"><?= esc($hero['physical_condition']) ?></span></div>
          <span class="pc-cta">View Listing</span>
        </div>
      </div>
    </a>
  <?php else: ?>
    <div class="product-card">
      <div class="pc-photo"></div>
      <div class="pc-body">
        <div class="lot">NO LIVE LISTINGS YET</div>
        <h3>Be the first on the yard.</h3>
        <p style="font-size:13px; color:var(--ink-2); margin:0 0 16px;">Nothing's live right now — once a seller lists and it's approved, it'll show up right here.</p>
        <a href="/listings/create" class="pc-cta">List an Asset</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<section class="block" id="live-listings">
  <div class="eyebrow">Live Right Now</div>
  <h2 class="block-title">What's actually on the yard.</h2>
  <p class="block-lead">Every listing here is a real, active sale event — not a demo.</p>
  <div class="listings-grid">
    <?php if (empty($activeListings)): ?>
      <div class="empty-state">No live auctions right now — check back soon, or be the first to list.</div>
    <?php else: ?>
      <?php foreach ($activeListings as $item): ?>
        <a href="/listings/<?= esc($item['listing_id']) ?>" style="text-decoration:none; color:inherit;">
          <div class="product-card">
            <div class="pc-photo" <?= $item['photo_path'] ? 'style="background-image:url(/'.esc($item['photo_path']).')"' : '' ?>>
              <span class="pc-badge"><?= esc(strtoupper($item['sale_format'])) ?></span>
            </div>
            <div class="pc-body">
              <div class="lot">LOT #<?= esc($item['ern']) ?></div>
              <h3><?= esc($item['category']) ?></h3>
              <div class="pc-row"><span class="k">Price</span><span class="v big" style="font-size:18px;">₹<?= number_format((float)($item['current_price'] ?? $item['reserve_value'] ?? $item['expected_value']), 0) ?></span></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="block">
  <div class="eyebrow">How Selling Works</div>
  <h2 class="block-title">Three ways to sell today. One winner, always.</h2>
  <p class="block-lead">Every listing is matched to the disposal mechanism that fits it — Tender is coming soon, Company Shop exclusive.</p>
  <div class="format-grid">
    <div class="fmt-card">
      <div class="fmt-icon">BN</div>
      <h4>Buy-Now</h4>
      <p>Judgment-based offers. Sellers weigh price against buyer rating, not just the highest number.</p>
      <span class="fmt-tag">3-day offer validity</span>
    </div>
    <div class="fmt-card">
      <div class="fmt-icon">EA</div>
      <h4>Easy Auction</h4>
      <p>Scheduled open bidding with Dynamic Time extensions — a late bid pushes the deadline back.</p>
      <span class="fmt-tag">Seller sets the schedule</span>
    </div>
    <div class="fmt-card">
      <div class="fmt-icon">EX</div>
      <h4>Express Auction</h4>
      <p>No inspection, no waiting — launches the instant 3 buyers pledge EMD. Fully automatic result.</p>
      <span class="fmt-tag">1-hour run time</span>
    </div>
    <div class="fmt-card" style="opacity:0.55;">
      <div class="fmt-icon">TD</div>
      <h4>Tender</h4>
      <p>Fully curated, invitation-only concierge sales — Company Shop exclusive.</p>
      <span class="fmt-tag">Coming soon</span>
    </div>
  </div>
</section>

<section class="block">
  <div class="eyebrow">Categories</div>
  <h2 class="block-title">Browse by what's on the yard.</h2>
  <div class="cat-grid">
    <?php if (empty($categoryCounts)): ?>
      <div class="empty-state" style="grid-column:1/-1;">No categories with live listings yet.</div>
    <?php else: ?>
      <?php foreach ($categoryCounts as $cat): ?>
        <div class="cat-tile"><span class="n"><?= esc($cat['listing_count']) ?></span><?= esc($cat['category']) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="block">
  <div class="trust-wrap">
    <div>
      <div class="eyebrow">Trust System</div>
      <h2 class="block-title">Four scores, one honest track record.</h2>
      <ul class="trust-points">
        <li><span class="mark">1</span><div><b>Every profile starts at 3★</b><span>No advantage for age of account — rating reflects behaviour, never frequency.</span></div></li>
        <li><span class="mark">2</span><div><b>Downgrades are human-reviewed</b><span>No rating drops silently. Tenant Admin — and Super Admin below 2★ — must approve it.</span></div></li>
        <li><span class="mark">3</span><div><b>Recovery is real</b><span>Crawl-Back lets a buyer rebuild trust through clean transactions.</span></div></li>
      </ul>
    </div>
    <div class="star-demo">
      <div class="star-row"><span class="label">Buyer Rating</span><span class="stars">★★★☆☆ 3.0</span></div>
      <div class="star-row"><span class="label">Seller Rating</span><span class="stars">★★★☆☆ 3.0</span></div>
      <div class="star-row"><span class="label">Every account starts here</span><span class="stars">Neutral baseline</span></div>
    </div>
  </div>
</section>

<div class="cta-band">
  <div>
    <h3>Ready to clear the yard?</h3>
    <p>List your first asset in minutes — no listing fee, ever.</p>
  </div>
  <a href="/listings/create" class="btn">Get Started</a>
</div>

<?= $this->endSection() ?>
