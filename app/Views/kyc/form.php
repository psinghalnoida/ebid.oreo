<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<style {csp-style-nonce}>
  .kyc-wrap{max-width:640px; padding:40px 24px 60px;}
  .kyc-section{margin-top:var(--sp-6);}
  .kyc-section h3{font-size:15px; margin:0 0 var(--sp-4); display:flex; align-items:center; gap:10px;}
  .kyc-section h3 .step{width:24px; height:24px; border-radius:50%; background:var(--navy); color:#fff; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; flex:none;}
  .entity-fields{display:grid; grid-template-columns:1fr 1fr; gap:0 var(--sp-4);}
  @media(max-width:640px){ .entity-fields{grid-template-columns:1fr;} }
  .addr-details{margin:8px 0; border:1px solid var(--line); border-radius:10px; padding:14px; background:var(--card);}
  .addr-details summary{font-size:12.5px; font-weight:700; cursor:pointer;}
</style>
<main class="kyc-wrap">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px; margin-bottom:4px;">KYC Verification</h1>
  <p style="font-size:12px; color:var(--ink-3);">Required before your first EMD deposit or first <?= tsx_term('Listing') ?>.</p>

  <?php
    $statusColors = ['pending' => '#9DA099', 'submitted' => '#E3A93C', 'verified' => '#B85C2C', 'suspended' => '#B5482F'];
    $statusColor = $statusColors[$party['kyc_status']] ?? '#9DA099';
  ?>
  <div class="badge" style="background:<?= $statusColor ?>1A; color:<?= $statusColor ?>; margin:10px 0 4px;">
    KYC Status: <?= esc(ucfirst($party['kyc_status'])) ?>
  </div>
  <?php if ($party['kyc_status'] === 'suspended' && !empty($party['kyc_status_reason'])): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px; margin-top:12px;">Suspended: <?= esc($party['kyc_status_reason']) ?></p>
  <?php endif; ?>

  <div class="kyc-section card">
    <h3><span class="step">1</span> Entity Type &amp; Questionnaire</h3>
    <form method="post" action="/kyc/questionnaire" id="kycQuestionnaireForm"><?= csrf_field() ?>
      <div class="field">
        <label>Entity Type</label>
        <select name="entity_type" id="entityTypeSelect">
          <option value="individual" <?= $party['entity_type'] === 'individual' ? 'selected' : '' ?>>Individual</option>
          <option value="organization" <?= $party['entity_type'] === 'organization' ? 'selected' : '' ?>>Organization</option>
        </select>
      </div>

      <div id="individualFields">
        <p style="font-size:11px; color:var(--ink-3); font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 10px;">Individual details</p>
        <div class="entity-fields">
          <div class="field"><label>Full Name</label><input type="text" name="full_name" value="<?= esc($party['full_name'] ?? '') ?>"></div>
          <div class="field"><label>PAN</label><input type="text" name="pan" placeholder="ABCDE1234F" value="<?= esc($party['pan'] ?? '') ?>"></div>
          <div class="field">
            <label>Aadhaar</label>
            <input type="text" name="aadhaar" placeholder="12 digits" maxlength="12">
            <?php if (!empty($party['aadhaar_masked'])): ?><p class="hint">On file: <?= esc($party['aadhaar_masked']) ?></p><?php endif; ?>
          </div>
          <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" value="<?= esc($party['date_of_birth'] ?? '') ?>"></div>
          <div class="field"><label>Occupation</label><input type="text" name="occupation" value="<?= esc($party['occupation'] ?? '') ?>"></div>
        </div>
      </div>

      <div id="organizationFields">
        <p style="font-size:11px; color:var(--ink-3); font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 10px;">Organization details</p>
        <div class="entity-fields">
          <div class="field"><label>CIN</label><input type="text" name="org_cin" value="<?= esc($party['org_cin'] ?? '') ?>"></div>
          <div class="field"><label>GSTIN</label><input type="text" name="org_gstin" value="<?= esc($party['org_gstin'] ?? '') ?>"></div>
          <div class="field"><label>Company PAN</label><input type="text" name="org_pan" value="<?= esc($party['org_pan'] ?? '') ?>"></div>
          <div class="field"><label>MSME Registration (optional)</label><input type="text" name="org_msme_registration" value="<?= esc($party['org_msme_registration'] ?? '') ?>"></div>
          <div class="field"><label>UDYAM Number (optional)</label><input type="text" name="org_udyam_number" value="<?= esc($party['org_udyam_number'] ?? '') ?>"></div>
          <div class="field"><label>Company Type</label><input type="text" name="org_company_type" value="<?= esc($party['org_company_type'] ?? '') ?>"></div>
          <div class="field"><label>Industry</label><input type="text" name="org_industry" value="<?= esc($party['org_industry'] ?? '') ?>"></div>
          <div class="field"><label>Annual Turnover (optional)</label><input type="number" name="org_annual_turnover" value="<?= esc($party['org_annual_turnover'] ?? '') ?>"></div>
          <div class="field"><label>Employee Count (optional)</label><input type="number" name="org_employee_count" value="<?= esc($party['org_employee_count'] ?? '') ?>"></div>
        </div>
      </div>

      <button type="submit" class="btn btn-emerald">Save Questionnaire</button>
    </form>
  </div>

  <div class="kyc-section card">
    <h3><span class="step">2</span> Documents</h3>
    <p class="hint" style="margin-bottom:10px;">Required for <?= esc($party['entity_type']) ?>: <?= esc(implode(', ', $requiredDocuments)) ?></p>
    <?php if (!empty($documents)): ?>
    <ul style="font-size:12.5px; padding-left:18px; margin:0 0 14px;">
      <?php foreach ($documents as $d): ?>
        <li><?= esc($d['document_type']) ?> — <?= esc($d['original_filename']) ?> (uploaded <?= esc(substr($d['uploaded_at'], 0, 10)) ?>)</li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="post" action="/kyc/documents" enctype="multipart/form-data" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;"><?= csrf_field() ?>
      <select name="document_type" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
        <?php foreach ($allDocumentTypes as $t): ?>
          <option value="<?= esc($t) ?>"><?= esc(ucwords(str_replace('_', ' ', $t))) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="file" name="document" accept="application/pdf,image/jpeg,image/png">
      <button type="submit" class="btn btn-ghost">Upload</button>
    </form>
  </div>

  <div class="kyc-section card">
    <h3><span class="step">3</span> Address Portfolio</h3>
    <?php foreach (['registered' => 'Registered', 'billing' => 'Billing', 'correspondence' => 'Correspondence', 'site_yard' => 'Site/Yard'] as $type => $label): ?>
      <?php $existing = $addressesByType[$type] ?? null; ?>
      <details class="addr-details">
        <summary><?= esc($label) ?> Address <?= $existing ? '✓' : '' ?></summary>
        <form method="post" action="/kyc/addresses" style="margin-top:10px;"><?= csrf_field() ?>
          <input type="hidden" name="address_type" value="<?= esc($type) ?>">
          <div class="field"><input type="text" name="line1" placeholder="Address Line 1" value="<?= esc($existing['line1'] ?? '') ?>"></div>
          <div class="field"><input type="text" name="line2" placeholder="Address Line 2 (optional)" value="<?= esc($existing['line2'] ?? '') ?>"></div>
          <div class="field"><input type="text" name="city" placeholder="City" value="<?= esc($existing['city'] ?? '') ?>"></div>
          <div class="field"><input type="text" name="district" placeholder="District" value="<?= esc($existing['district'] ?? '') ?>"></div>
          <div class="field"><input type="text" name="state" placeholder="State" value="<?= esc($existing['state'] ?? '') ?>"></div>
          <div class="field"><input type="text" name="pin_code" placeholder="6-digit PIN" maxlength="6" value="<?= esc($existing['pin_code'] ?? '') ?>"></div>
          <button type="submit" class="btn btn-ghost">Save <?= esc($label) ?> Address</button>
        </form>
      </details>
    <?php endforeach; ?>
  </div>

  <div class="kyc-section card">
    <h3><span class="step">4</span> Banking Details</h3>
    <form method="post" action="/kyc/banking"><?= csrf_field() ?>
      <div class="entity-fields">
        <div class="field"><label>Account Holder Name</label><input type="text" name="account_holder_name" value="<?= esc($party['payout_bank_account_holder_name'] ?? '') ?>"></div>
        <div class="field"><label>Bank Name</label><input type="text" name="bank_name" value="<?= esc($party['payout_bank_name'] ?? '') ?>"></div>
        <div class="field"><label>Branch Name</label><input type="text" name="branch_name" value="<?= esc($party['payout_bank_branch_name'] ?? '') ?>"></div>
        <div class="field"><label>Account Number</label><input type="text" name="account_number" value="<?= esc($party['payout_bank_account_number'] ?? '') ?>"></div>
        <div class="field"><label>IFSC</label><input type="text" name="ifsc" value="<?= esc($party['payout_bank_ifsc'] ?? '') ?>"></div>
        <div class="field"><label>UPI ID (optional)</label><input type="text" name="upi_id" value="<?= esc($party['payout_bank_upi_id'] ?? '') ?>"></div>
      </div>
      <button type="submit" class="btn btn-emerald">Save Banking Details</button>
    </form>
  </div>

  <?php if (in_array($party['kyc_status'], ['pending'], true)): ?>
  <form method="post" action="/kyc/submit" style="margin-top:28px;"><?= csrf_field() ?>
    <button type="submit" class="btn btn-emerald" style="width:100%;">Submit for Review</button>
  </form>
  <?php elseif ($party['kyc_status'] === 'submitted'): ?>
    <p style="font-size:12px; color:var(--ink-3); margin-top:20px;">Your dossier has been submitted and is awaiting review.</p>
  <?php endif; ?>
</main>
<script {csp-script-nonce}>
  (function () {
    var select = document.getElementById('entityTypeSelect');
    var individual = document.getElementById('individualFields');
    var organization = document.getElementById('organizationFields');
    if (!select) return;
    function sync() {
      var isOrg = select.value === 'organization';
      individual.style.display = isOrg ? 'none' : 'block';
      organization.style.display = isOrg ? 'block' : 'none';
    }
    select.addEventListener('change', sync);
    sync();
  })();
</script>
<?= $this->endSection() ?>
