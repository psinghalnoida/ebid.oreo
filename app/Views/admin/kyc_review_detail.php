<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:700px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <p style="font-size:11px; color:var(--ink-3);"><a href="/admin/kyc" style="color:var(--emerald);">← KYC Review Queue</a></p>
  <h1 style="font-size:22px;"><?= esc($party['mobile_number']) ?> — <?= esc(ucfirst($party['entity_type'])) ?> KYC Dossier</h1>
  <p style="font-size:12px; color:var(--ink-3);">Status: <strong><?= esc(ucfirst($party['kyc_status'])) ?></strong></p>

  <h3 style="font-size:14px; margin-top:20px;">Questionnaire</h3>
  <?php if ($party['entity_type'] === 'individual'): ?>
    <p style="font-size:12px;">Full Name: <?= esc($party['full_name'] ?? '—') ?><br>
    PAN: <?= esc($party['pan'] ?? '—') ?><br>
    Aadhaar: <?= esc($party['aadhaar_masked'] ?? '—') ?><br>
    DOB: <?= esc($party['date_of_birth'] ?? '—') ?><br>
    Occupation: <?= esc($party['occupation'] ?? '—') ?></p>
  <?php else: ?>
    <p style="font-size:12px;">CIN: <?= esc($party['org_cin'] ?? '—') ?><br>
    GSTIN: <?= esc($party['org_gstin'] ?? '—') ?><br>
    Company PAN: <?= esc($party['org_pan'] ?? '—') ?><br>
    Company Type: <?= esc($party['org_company_type'] ?? '—') ?><br>
    Industry: <?= esc($party['org_industry'] ?? '—') ?></p>
  <?php endif; ?>

  <h3 style="font-size:14px; margin-top:20px;">Compliance Flags</h3>
  <?php foreach (['pan' => 'PAN', 'gstin' => 'GSTIN', 'aadhaar' => 'Aadhaar', 'bank' => 'Bank', 'email' => 'Email'] as $flag => $label): ?>
    <?php $verifiedAt = $party["{$flag}_verified_at"] ?? null; ?>
    <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--line-soft); font-size:12px;">
      <span><?= esc($label) ?>: <?= $verifiedAt ? 'Verified ✓ (' . esc(substr($verifiedAt, 0, 10)) . ')' : 'Not verified' ?></span>
      <?php if (!$verifiedAt): ?>
        <form method="post" action="/admin/kyc/<?= esc($party['id']) ?>/verify-flag" style="margin:0;"><?= csrf_field() ?>
          <input type="hidden" name="flag" value="<?= esc($flag) ?>">
          <button type="submit" class="btn btn-ghost" style="font-size:11px; padding:4px 10px;">Verify manually</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <p style="font-size:10.5px; color:var(--ink-3); margin-top:6px;">No automated PAN/GSTIN registry or UIDAI Aadhaar API exists — these are genuine SaaS Admin manual verification actions, not automated checks.</p>

  <h3 style="font-size:14px; margin-top:20px;">Documents</h3>
  <ul style="font-size:12px; padding-left:18px;">
    <?php foreach ($documents as $d): ?>
      <li><?= esc(ucwords(str_replace('_', ' ', $d['document_type']))) ?> — <a href="/admin/kyc-documents/<?= esc($d['id']) ?>/download" style="color:var(--emerald);" target="_blank"><?= esc($d['original_filename']) ?></a></li>
    <?php endforeach; ?>
    <?php if (empty($documents)): ?><li style="color:var(--ink-3);">No documents uploaded.</li><?php endif; ?>
  </ul>

  <h3 style="font-size:14px; margin-top:20px;">Addresses</h3>
  <ul style="font-size:12px; padding-left:18px;">
    <?php foreach ($addresses as $a): ?>
      <li><?= esc(ucfirst($a['address_type'])) ?>: <?= esc($a['line1']) ?>, <?= esc($a['city']) ?>, <?= esc($a['state']) ?> — <?= esc($a['pin_code']) ?></li>
    <?php endforeach; ?>
    <?php if (empty($addresses)): ?><li style="color:var(--ink-3);">No addresses registered.</li><?php endif; ?>
  </ul>

  <?php if (!empty($party['edd_required_at'])): ?>
  <h3 style="font-size:14px; margin-top:20px;">Enhanced Due Diligence (BR-55)</h3>
  <p style="font-size:12px;">Required at: <?= esc(substr($party['edd_required_at'], 0, 16)) ?><?= $party['edd_cleared_at'] ? ' — Cleared at ' . esc(substr($party['edd_cleared_at'], 0, 16)) : ' — NOT yet cleared' ?></p>
  <?php if (!$party['edd_cleared_at']): ?>
    <form method="post" action="/admin/kyc/<?= esc($party['id']) ?>/clear-edd"><?= csrf_field() ?>
      <button type="submit" class="btn btn-ghost">Clear Enhanced Due Diligence</button>
    </form>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($party['kyc_status'] === 'submitted'): ?>
  <h3 style="font-size:14px; margin-top:24px;">Decision</h3>
  <form method="post" action="/admin/kyc/<?= esc($party['id']) ?>/decide" style="display:flex; gap:8px; margin-bottom:10px;"><?= csrf_field() ?>
    <input type="hidden" name="decision" value="verify">
    <button type="submit" class="btn btn-emerald">Verify KYC</button>
  </form>
  <form method="post" action="/admin/kyc/<?= esc($party['id']) ?>/decide"><?= csrf_field() ?>
    <input type="hidden" name="decision" value="suspend">
    <select name="reason" style="padding:10px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach ($suspensionReasons as $key => $label): ?>
        <option value="<?= esc($key) ?>"><?= esc($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost">Suspend KYC</button>
  </form>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
