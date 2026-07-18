<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
  .hub-hero{padding:56px 0 40px; text-align:center;}
  .eyebrow{font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:var(--emerald); margin:0 0 12px;}
  .hub-hero h1{font-size:38px; font-weight:800; letter-spacing:-1px; margin:0 0 14px;}
  .hub-hero p{font-size:15.5px; color:var(--ink-2); max-width:520px; margin:0 auto 28px; line-height:1.6;}
  .group{margin-top:44px;}
  .group-head h2{font-size:20px; font-weight:700; margin:0 0 4px;}
  .group-head p{font-size:13px; color:var(--ink-3); margin:0 0 18px;}
  .card-grid{display:grid; grid-template-columns:repeat(3, 1fr); gap:14px;}
  .card{background:var(--card); border:1px solid var(--line); border-radius:var(--radius); padding:22px;}
  .card-icon{width:38px; height:38px; border-radius:11px; background:var(--emerald-soft); color:var(--emerald-deep);
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:15px; margin-bottom:14px;}
  .card h3{margin:0 0 6px; font-size:15px; font-weight:700;}
  .card p{margin:0; font-size:12.5px; color:var(--ink-2); line-height:1.55;}
  @media (max-width:900px){ .card-grid{grid-template-columns:repeat(2,1fr);} }
  @media (max-width:600px){ .card-grid{grid-template-columns:1fr;} }
</style>

<main>
  <div class="hub-hero">
    <div class="eyebrow">Trust & Support</div>
    <h1>How can we help?</h1>
    <p>Answers, policies, and safeguards — everything about how eBid Hub protects buyers, sellers, and every transaction in between.</p>
  </div>

  <?php foreach ($groups as $group): ?>
  <div class="group">
    <div class="group-head">
      <h2><?= esc($group['title']) ?></h2>
      <p><?= esc($group['subtitle']) ?></p>
    </div>
    <div class="card-grid">
      <?php foreach ($group['cards'] as $card): ?>
      <div class="card">
        <div class="card-icon"><?= esc($card['icon']) ?></div>
        <h3><?= esc($card['title']) ?></h3>
        <p><?= esc($card['description']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</main>
<?= $this->endSection() ?>
