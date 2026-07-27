<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Consent Audit (BR-51)</h1>
  <p style="font-size:12px; color:var(--ink-3);">Every discrete consent event captured on the platform — registration terms, per-pledge EMD acknowledgment, and any future consent type.</p>

  <form method="get" action="/admin/consent-audit" style="margin:16px 0; display:flex; gap:8px;">
    <input type="text" name="consent_type" placeholder="Consent type (e.g. emd_pledge)" value="<?= esc($consentType ?? '') ?>"
      style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <input type="text" name="mobile" placeholder="Mobile number" value="<?= esc($mobile ?? '') ?>"
      style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Filter</button>
  </form>

  <p style="font-size:12px;"><a href="/admin/consent-audit/export" style="color:var(--emerald);">Export by date range &rarr;</a></p>

  <table style="width:100%; border-collapse:collapse; margin-top:12px; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">When</th><th>Mobile</th><th>Type</th><th>Version</th><th>Text Shown</th><th>IP</th>
    </tr>
    <?php foreach ($entries as $e): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc(substr($e['created_at'], 0, 19)) ?></td>
      <td><?= esc($e['mobile_number']) ?></td>
      <td><?= esc($e['consent_type']) ?></td>
      <td><?= esc($e['terms_version']) ?></td>
      <td style="max-width:260px;"><?= esc(mb_strimwidth($e['consent_text_shown'], 0, 80, '…')) ?></td>
      <td><?= esc($e['ip_address'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($entries)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No consent events match.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
