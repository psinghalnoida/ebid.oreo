<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:520px; padding:60px 24px;">
  <h1 style="font-size:24px;">List an Asset</h1>
  <p style="color:var(--ink-2); font-size:14px;">BR-11: universal required listing metadata.</p>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/listings">
    <label style="font-size:12px; color:var(--ink-3);">Tenant</label>
    <select name="tenant_id" required style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach ($tenants as $t): ?>
        <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label style="font-size:12px; color:var(--ink-3);">Physical Condition</label>
    <input type="text" name="physical_condition" required placeholder="e.g. Fire-damaged, functional unverified"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Category</label>
    <input type="text" name="category" required placeholder="e.g. Industrial Surplus"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Subcategory (optional)</label>
    <input type="text" name="subcategory"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Quantity</label>
    <input type="number" name="quantity" required value="1" min="1"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Make / Model</label>
    <input type="text" name="make_model"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Yard Location Address</label>
    <input type="text" name="yard_location_address" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Yard Location PIN (6-digit)</label>
    <input type="text" name="yard_location_pin" required maxlength="6" pattern="\d{6}"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Media Tier (BR-59)</label>
    <select name="media_tier" style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
      <option value="certified_by_seller">Certified by Seller — I'll upload my own photos</option>
      <option value="verified">Verified — eBid Hub's inspection team photographs it (inspection fee applies)</option>
    </select>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Create Listing</button>
  </form>
</main>
<?= $this->endSection() ?>
