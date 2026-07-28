<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Search History</h1>
  <p style="font-size:12px; color:var(--ink-3);">Your last 20 searches on this platform.</p>

  <?php foreach ($history as $h): ?>
    <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between; align-items:center;">
      <span style="font-size:12px;"><?= esc(http_build_query($h['filtersDecoded'])) ?></span>
      <a href="/listings?<?= http_build_query($h['filtersDecoded']) ?>" class="btn btn-ghost" style="font-size:11px;">Re-run</a>
    </div>
  <?php endforeach; ?>
  <?php if (empty($history)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No search history yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
