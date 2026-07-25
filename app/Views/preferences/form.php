<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">My Preferences</h1>
  <p style="color:var(--ink-3); font-size:13px;">BR-23 — fine-tune your feed and Live Ticker matches. You'll still see every listing by default; this just curates what's highlighted for you.</p>

  <form method="post" action="/preferences" style="margin-top:20px;">
    <label style="font-size:12px; color:var(--ink-3);">Preferred Categories (leave all unchecked for everything)</label>
    <div style="margin:8px 0 16px;">
      <?php foreach ($allCategories as $cat): ?>
        <label style="display:block; font-size:13px; padding:4px 0;">
          <input type="checkbox" name="categories[]" value="<?= esc($cat) ?>" <?= in_array($cat, $selectedCategories, true) ? 'checked' : '' ?>>
          <?= esc($cat) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label style="font-size:12px; color:var(--ink-3);">Comfort Inspection States (comma-separated, or leave blank for All India)</label>
    <input type="text" name="states_text" placeholder="e.g. Tamil Nadu, Karnataka"
      value="<?= $existing && $existing['comfort_states'] ? esc(implode(', ', json_decode($existing['comfort_states'], true))) : '' ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Budget Range (₹)</label>
    <div style="display:flex; gap:8px; margin:6px 0 20px;">
      <input type="number" name="budget_min" placeholder="Min" value="<?= $existing ? esc($existing['budget_min']) : '' ?>"
        style="flex:1; padding:12px; border:1px solid var(--line); border-radius:10px;">
      <input type="number" name="budget_max" placeholder="Max" value="<?= $existing ? esc($existing['budget_max']) : '' ?>"
        style="flex:1; padding:12px; border:1px solid var(--line); border-radius:10px;">
    </div>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Save Preferences</button>
  </form>
</main>
<?= $this->endSection() ?>
