<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:480px; padding:40px 24px;">
  <h1 style="font-size:22px;">My Profile</h1>
  <div style="border:1px solid var(--line); border-radius:12px; padding:20px; margin-top:16px;">
    <p style="font-size:13px; color:var(--ink-3);">Mobile Number</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;"><?= esc($party['mobile_number']) ?></p>

    <p style="font-size:13px; color:var(--ink-3);">Buyer Rating</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;">★ <?= esc($party['star_rating']) ?> / 5.0</p>

    <p style="font-size:13px; color:var(--ink-3);">Seller Rating</p>
    <p style="font-size:15px; font-weight:600; margin:0 0 16px;">★ <?= esc($party['seller_star_rating']) ?> / 5.0</p>

    <p style="font-size:13px; color:var(--ink-3);">KYC Status</p>
    <p style="font-size:15px; font-weight:600; margin:0;"><?= esc($party['kyc_status'] ?? 'Not started') ?></p>
  </div>

  <div style="border:1px solid var(--line); border-radius:12px; padding:20px; margin-top:16px;">
    <p style="font-size:13px; color:var(--ink-3); margin:0 0 4px;">Payout Bank Account (BR-50)</p>
    <?php if ($bankAccount): ?>
      <?php $number = (string) $bankAccount['account_number']; $masked = strlen($number) > 4 ? str_repeat('•', strlen($number) - 4) . substr($number, -4) : $number; ?>
      <p style="font-size:15px; font-weight:600; margin:0 0 2px;"><?= esc($bankAccount['account_holder_name']) ?></p>
      <p style="font-size:13px; margin:0 0 4px;"><?= esc($masked) ?> · <?= esc($bankAccount['ifsc_code']) ?></p>
      <?php if ($bankAccount['status'] === 'cooling_off'): ?>
        <p style="font-size:11.5px; color:#9C5B1F;">⚠️ Cooling off — not yet active for payouts until <?= esc(substr($bankAccount['activates_at'], 0, 19)) ?> (BR-50).</p>
      <?php else: ?>
        <p style="font-size:11.5px; color:var(--emerald);">Active for payouts.</p>
      <?php endif; ?>
    <?php else: ?>
      <p style="font-size:13px; color:var(--ink-3); margin:0 0 8px;">No payout bank account registered yet.</p>
    <?php endif; ?>
    <a href="/payout-account/change" class="btn btn-ghost" style="margin-top:10px; display:inline-block; font-size:12px;">
      <?= $bankAccount ? 'Change' : 'Add' ?> Payout Bank Account
    </a>
  </div>

  <a href="/logout" class="btn btn-ghost" style="margin-top:20px; display:inline-block;">Log Out</a>
</main>
<?= $this->endSection() ?>
