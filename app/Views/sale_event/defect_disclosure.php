<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <h1 style="font-size:22px;">Defect Disclosure — Express Auction</h1>
  <p style="color:var(--ink-3); font-size:13px; margin-top:8px;">
    Express Auctions have no inspection window, so this checklist is your only way to give buyers
    accurate information before they bid sight-unseen. Answer honestly — a false disclosure here can be
    filed as a Condition/Delivery Dispute even though Express is otherwise buyer-beware.
  </p>

  <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/defect-disclosure" style="margin-top:20px;"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">Known Damage</label>
    <textarea name="known_damage" rows="3" placeholder="e.g. Minor dent on rear panel, none if not applicable"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <label style="font-size:12px; color:var(--ink-3);">Missing Components</label>
    <textarea name="missing_components" rows="3" placeholder="e.g. Missing original charger, none if not applicable"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <label style="font-size:12px; color:var(--ink-3);">Non-Functional Aspects</label>
    <textarea name="nonfunctional_aspects" rows="3" placeholder="e.g. AC not working, none if not applicable"
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;"></textarea>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Complete Disclosure</button>
  </form>
</main>
<?= $this->endSection() ?>
