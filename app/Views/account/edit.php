<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <h1 style="font-size:22px;">Edit Account</h1>
  <p style="font-size:12px; color:var(--ink-3);">
    Mobile number, PAN, Aadhaar, and other KYC-verification details are not editable here — those require real verification, not a self-service edit.
  </p>

  <form method="post" action="/account/edit" style="margin-top:16px;">
    <label style="font-size:12px; color:var(--ink-3);">Full Name</label>
    <input type="text" name="full_name" value="<?= esc($party['full_name'] ?? '') ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Recovery Email</label>
    <input type="email" name="recovery_email" value="<?= esc($party['recovery_email'] ?? '') ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Occupation</label>
    <input type="text" name="occupation" value="<?= esc($party['occupation'] ?? '') ?>"
      style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

    <?php if ($party['entity_type'] === 'organization'): ?>
      <label style="font-size:12px; color:var(--ink-3);">Company Type</label>
      <input type="text" name="org_company_type" value="<?= esc($party['org_company_type'] ?? '') ?>"
        style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

      <label style="font-size:12px; color:var(--ink-3);">Industry</label>
      <input type="text" name="org_industry" value="<?= esc($party['org_industry'] ?? '') ?>"
        style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

      <label style="font-size:12px; color:var(--ink-3);">Annual Turnover (₹, optional)</label>
      <input type="number" step="0.01" name="org_annual_turnover" value="<?= esc($party['org_annual_turnover'] ?? '') ?>"
        style="display:block; width:100%; padding:12px; margin:6px 0 16px; border:1px solid var(--line); border-radius:10px;">

      <label style="font-size:12px; color:var(--ink-3);">Employee Count (optional)</label>
      <input type="number" name="org_employee_count" value="<?= esc($party['org_employee_count'] ?? '') ?>"
        style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
    <?php endif; ?>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Save Changes</button>
  </form>
</main>
<?= $this->endSection() ?>
