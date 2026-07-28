<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <h1 style="font-size:22px;">Saved Searches</h1>
  <p style="font-size:12px; color:var(--ink-3);">Save a filter combination from the <a href="/listings">Browse</a> page, then re-run it here anytime.</p>

  <?php foreach ($searches as $s): $f = json_decode($s['filters'], true) ?: []; ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <p style="font-size:13px; font-weight:700; margin:0 0 4px;"><?= esc($s['label']) ?></p>
        <p style="font-size:11px; color:var(--ink-3); margin:0;"><?= esc(http_build_query($f)) ?></p>
      </div>
      <div style="display:flex; gap:8px;">
        <a href="/listings?<?= http_build_query($f) ?>" class="btn btn-ghost" style="font-size:12px;">Run</a>
        <form method="post" action="/my-searches/<?= esc($s['id']) ?>/delete">
          <button type="submit" class="btn btn-ghost" style="font-size:12px; color:#B5482F;">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($searches)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No saved searches yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
