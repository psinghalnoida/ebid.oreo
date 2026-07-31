<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">KYC Verification</h1>
  <p style="font-size:12px; color:var(--ink-3);">BR-17/BR-18 — required before your first EMD deposit or first <?= tsx_term('Listing') ?> (BR-55).</p>

  <?php
    $statusColors = ['pending' => '#9A9A93', 'submitted' => '#D98C4A', 'verified' => '#0F5C4C', 'suspended' => '#B5482F'];
    $statusColor = $statusColors[$party['kyc_status']] ?? '#9A9A93';
  ?>
  <div style="display:inline-block; padding:6px 14px; border-radius:100px; background:<?= $statusColor ?>1A; color:<?= $statusColor ?>; font-size:12px; font-weight:700; text-transform:uppercase; margin:10px 0 20px;">
    KYC Status: <?= esc(ucfirst($party['kyc_status'])) ?>
  </div>
  <?php if ($party['kyc_status'] === 'suspended' && !empty($party['kyc_status_reason'])): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;">Suspended: <?= esc($party['kyc_status_reason']) ?></p>
  <?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">1. Entity Type &amp; Questionnaire</h3>
  <form method="post" action="/kyc/questionnaire">
    <label style="font-size:12px; color:var(--ink-3);">Entity Type</label>
    <select name="entity_type" style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <option value="individual" <?= $party['entity_type'] === 'individual' ? 'selected' : '' ?>>Individual</option>
      <option value="organization" <?= $party['entity_type'] === 'organization' ? 'selected' : '' ?>>Organization</option>
    </select>

    <p style="font-size:11px; color:var(--ink-3); margin-top:16px;">Individual fields</p>
    <input type="text" name="full_name" placeholder="Full Name" value="<?= esc($party['full_name'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="pan" placeholder="PAN (e.g. ABCDE1234F)" value="<?= esc($party['pan'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="aadhaar" placeholder="Aadhaar (12 digits — masked once saved)" maxlength="12"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <?php if (!empty($party['aadhaar_masked'])): ?>
      <p style="font-size:11px; color:var(--ink-3);">On file: <?= esc($party['aadhaar_masked']) ?></p>
    <?php endif; ?>
    <input type="date" name="date_of_birth" value="<?= esc($party['date_of_birth'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="occupation" placeholder="Occupation" value="<?= esc($party['occupation'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">

    <p style="font-size:11px; color:var(--ink-3); margin-top:16px;">Organization fields</p>
    <input type="text" name="org_cin" placeholder="CIN" value="<?= esc($party['org_cin'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_gstin" placeholder="GSTIN" value="<?= esc($party['org_gstin'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_pan" placeholder="Company PAN" value="<?= esc($party['org_pan'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_msme_registration" placeholder="MSME Registration (optional)" value="<?= esc($party['org_msme_registration'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_udyam_number" placeholder="UDYAM Number (optional)" value="<?= esc($party['org_udyam_number'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_company_type" placeholder="Company Type" value="<?= esc($party['org_company_type'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="org_industry" placeholder="Industry" value="<?= esc($party['org_industry'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="number" name="org_annual_turnover" placeholder="Annual Turnover (optional)" value="<?= esc($party['org_annual_turnover'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="number" name="org_employee_count" placeholder="Employee Count (optional)" value="<?= esc($party['org_employee_count'] ?? '') ?>"
      style="display:block; width:100%; padding:10px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <button type="submit" class="btn btn-emerald">Save Questionnaire</button>
  </form>

  <h3 style="font-size:15px; margin-top:28px;">2. Documents</h3>
  <p style="font-size:11px; color:var(--ink-3);">Required for <?= esc($party['entity_type']) ?>: <?= esc(implode(', ', $requiredDocuments)) ?></p>
  <ul style="font-size:12px; padding-left:18px;">
    <?php foreach ($documents as $d): ?>
      <li><?= esc($d['document_type']) ?> — <?= esc($d['original_filename']) ?> (uploaded <?= esc(substr($d['uploaded_at'], 0, 10)) ?>)</li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="/kyc/documents" enctype="multipart/form-data">
    <select name="document_type" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach ($allDocumentTypes as $t): ?>
        <option value="<?= esc($t) ?>"><?= esc(ucwords(str_replace('_', ' ', $t))) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="document" accept="application/pdf,image/jpeg,image/png" style="margin:0 8px;">
    <button type="submit" class="btn btn-ghost">Upload</button>
  </form>

  <h3 style="font-size:15px; margin-top:28px;">3. Address Portfolio</h3>
  <?php foreach (['registered' => 'Registered', 'billing' => 'Billing', 'correspondence' => 'Correspondence', 'site_yard' => 'Site/Yard'] as $type => $label): ?>
    <?php $existing = $addressesByType[$type] ?? null; ?>
    <details style="margin:8px 0; border:1px solid var(--line); border-radius:10px; padding:10px;">
      <summary style="font-size:12px; font-weight:700; cursor:pointer;"><?= esc($label) ?> Address <?= $existing ? '✓' : '' ?></summary>
      <form method="post" action="/kyc/addresses" style="margin-top:10px;">
        <input type="hidden" name="address_type" value="<?= esc($type) ?>">
        <input type="text" name="line1" placeholder="Address Line 1" value="<?= esc($existing['line1'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <input type="text" name="line2" placeholder="Address Line 2 (optional)" value="<?= esc($existing['line2'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <input type="text" name="city" placeholder="City" value="<?= esc($existing['city'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <input type="text" name="district" placeholder="District" value="<?= esc($existing['district'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <input type="text" name="state" placeholder="State" value="<?= esc($existing['state'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <input type="text" name="pin_code" placeholder="6-digit PIN" maxlength="6" value="<?= esc($existing['pin_code'] ?? '') ?>" style="display:block; width:100%; padding:8px; margin:4px 0; border:1px solid var(--line); border-radius:8px;">
        <button type="submit" class="btn btn-ghost" style="margin-top:6px;">Save <?= esc($label) ?> Address</button>
      </form>
    </details>
  <?php endforeach; ?>

  <h3 style="font-size:15px; margin-top:28px;">4. Banking Details</h3>
  <form method="post" action="/kyc/banking">
    <input type="text" name="account_holder_name" placeholder="Account Holder Name" value="<?= esc($party['payout_bank_account_holder_name'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="bank_name" placeholder="Bank Name" value="<?= esc($party['payout_bank_name'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="branch_name" placeholder="Branch Name" value="<?= esc($party['payout_bank_branch_name'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="account_number" placeholder="Account Number" value="<?= esc($party['payout_bank_account_number'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="ifsc" placeholder="IFSC" value="<?= esc($party['payout_bank_ifsc'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0; border:1px solid var(--line); border-radius:10px;">
    <input type="text" name="upi_id" placeholder="UPI ID (optional)" value="<?= esc($party['payout_bank_upi_id'] ?? '') ?>" style="display:block; width:100%; padding:10px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-emerald">Save Banking Details</button>
  </form>

  <?php if (in_array($party['kyc_status'], ['pending'], true)): ?>
  <form method="post" action="/kyc/submit" style="margin-top:28px;">
    <button type="submit" class="btn btn-emerald" style="width:100%;">Submit for Review</button>
  </form>
  <?php elseif ($party['kyc_status'] === 'submitted'): ?>
    <p style="font-size:12px; color:var(--ink-3); margin-top:20px;">Your dossier has been submitted and is awaiting review.</p>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
