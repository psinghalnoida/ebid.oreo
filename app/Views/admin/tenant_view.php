<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>
  <h1 style="font-size:22px;"><?= esc($tenant['name']) ?></h1>
  <p style="color:var(--ink-3); font-size:12px;">
    <?= esc($tenant['tenant_class']) ?> · <?= esc($tenant['subdomain']) ?><?= $tenant['custom_domain'] ? ' · ' . esc($tenant['custom_domain']) : '' ?>
  </p>

  <?php if (!empty($tenant['branding_logo_url'])): ?>
    <img src="<?= esc($tenant['branding_logo_url']) ?>" alt="<?= tsx_term('Tenant') ?> logo" style="max-height:48px; margin:12px 0;">
  <?php endif; ?>

  <form method="post" action="/admin/tenants/<?= esc($tenant['id']) ?>/edit" enctype="multipart/form-data" style="margin-top:20px;"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);">Name</label>
    <input type="text" name="name" value="<?= esc($tenant['name']) ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Subscription Tier (Section 5, BR-08/09) — the Success Fee itself is fixed platform-wide and not set here</label>
    <select name="subscription_tier" style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach (\App\Models\TenantModel::SUBSCRIPTION_TIERS as $tier): ?>
        <option value="<?= esc($tier) ?>" <?= $tenant['subscription_tier'] === $tier ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $tier))) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="font-size:12px; color:var(--ink-3);">Brand Primary Color (BR-06)</label>
    <input type="text" name="branding_primary_color" value="<?= esc($tenant['branding_primary_color'] ?? '') ?>" placeholder="#0F6E4E"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <label style="font-size:12px; color:var(--ink-3);">Brand Logo (JPEG/PNG/WebP/SVG)</label>
    <input type="file" name="branding_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
      style="display:block; width:100%; padding:8px 0; margin:6px 0 20px;">
    <label style="font-size:12px; color:var(--ink-3);">Terms of Use URL (BR-06)</label>
    <input type="text" name="terms_url" value="<?= esc($tenant['terms_url'] ?? '') ?>" placeholder="https://..."
      style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Save Changes</button>
  </form>

  <p style="font-size:11px; color:var(--ink-3); margin-top:16px;"><?= tsx_term('Tenant') ?> class and subdomain are not editable here — changing them affects existing <?= strtolower(tsx_term('Listing', false, true)) ?> and links, and needs a deliberate decision, not a quick form edit.</p>
</main>
<?= $this->endSection() ?>
