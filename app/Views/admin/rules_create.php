<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:600px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <p style="font-size:11px; color:var(--ink-3);"><a href="/admin/rules" style="color:var(--emerald);">← Rules &amp; Specifications</a></p>
  <h1 style="font-size:22px;">Define a New Rule</h1>
  <p style="font-size:12px; color:var(--ink-3);">A freeform governance rule — versioned and audited like any other, but with no direct code effect (no rule_key exists to wire it to).</p>

  <form method="post" action="/admin/rules/new" style="margin-top:20px;"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">Title</label>
    <input type="text" name="title" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box;">

    <label style="font-size:12px; color:var(--ink-3);">Statement</label>
    <textarea name="statement" rows="3" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;"></textarea>

    <label style="font-size:12px; color:var(--ink-3);">Logic</label>
    <textarea name="logic" rows="2" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;"></textarea>

    <label style="font-size:12px; color:var(--ink-3);">Reason for Modification <span style="color:var(--emerald-deep);">(required)</span></label>
    <textarea name="reason_for_modification" rows="2" required
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px; box-sizing:border-box; font-family:inherit;" placeholder="Why is this rule being introduced?"></textarea>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Create Rule</button>
  </form>
</main>
<?= $this->endSection() ?>
