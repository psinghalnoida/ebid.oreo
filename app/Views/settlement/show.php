<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:560px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <span class="pc-badge" style="background:var(--emerald-soft); color:var(--emerald-deep); padding:5px 12px; border-radius:100px; font-size:11px; font-weight:700;">
    <?= esc(strtoupper($settlement['status'])) ?>
  </span>
  <h1 style="font-size:24px; margin:12px 0 4px;">Settlement</h1>
  <p style="color:var(--ink-3); font-size:13px;">Final price: ₹<?= number_format((float) $settlement['final_price'], 2) ?> · <?= esc($saleEvent['ern']) ?></p>

  <p style="font-size:13px; color:var(--ink-2); margin:16px 0;">
    BR-33: a sale only formally closes once all four steps below are complete — both parties confirming the physical transaction, and both parties rating each other.
  </p>

  <div style="border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:12px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 4px;">1. Seller confirms receipt of payment</p>
    <?php if ($settlement['seller_noc_confirmed_at']): ?>
      <p style="font-size:12px; color:var(--emerald);">✓ Confirmed</p>
    <?php elseif ($callerId === $settlement['seller_party_id']): ?>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/confirm-seller-noc">
        <button type="submit" class="btn btn-emerald" style="font-size:12px; padding:8px 14px;">I received payment</button>
      </form>
    <?php else: ?>
      <p style="font-size:12px; color:var(--ink-3);">Waiting on seller</p>
    <?php endif; ?>
  </div>

  <div style="border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:12px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 4px;">2. Buyer confirms receipt of goods</p>
    <?php if ($settlement['buyer_noc_confirmed_at']): ?>
      <p style="font-size:12px; color:var(--emerald);">✓ Confirmed</p>
    <?php elseif ($callerId === $settlement['buyer_party_id']): ?>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/confirm-buyer-noc">
        <button type="submit" class="btn btn-emerald" style="font-size:12px; padding:8px 14px;">I received the item</button>
      </form>
    <?php else: ?>
      <p style="font-size:12px; color:var(--ink-3);">Waiting on buyer</p>
    <?php endif; ?>
  </div>

  <div style="border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:12px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 4px;">3. Buyer rates the seller</p>
    <?php if ($settlement['buyer_rated_seller_at']): ?>
      <p style="font-size:12px; color:var(--emerald);">✓ Rated</p>
    <?php elseif ($callerId === $settlement['buyer_party_id']): ?>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/rate-as-buyer" style="display:flex; gap:6px; align-items:center;">
        <select name="outcome" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
          <option value="good">Good transaction</option>
          <option value="problem">There was a problem</option>
        </select>
        <input type="text" name="reason" placeholder="Reason (if problem)" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px; flex:1;">
        <button type="submit" class="btn btn-emerald" style="font-size:12px; padding:8px 14px;">Submit</button>
      </form>
    <?php else: ?>
      <p style="font-size:12px; color:var(--ink-3);">Waiting on buyer</p>
    <?php endif; ?>
  </div>

  <div style="border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:20px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 4px;">4. Seller rates the buyer</p>
    <?php if ($settlement['seller_rated_buyer_at']): ?>
      <p style="font-size:12px; color:var(--emerald);">✓ Rated</p>
    <?php elseif ($callerId === $settlement['seller_party_id']): ?>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/rate-as-seller" style="display:flex; gap:6px; align-items:center;">
        <select name="outcome" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
          <option value="good">Good transaction</option>
          <option value="problem">There was a problem</option>
        </select>
        <input type="text" name="reason" placeholder="Reason (if problem)" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px; flex:1;">
        <button type="submit" class="btn btn-emerald" style="font-size:12px; padding:8px 14px;">Submit</button>
      </form>
    <?php else: ?>
      <p style="font-size:12px; color:var(--ink-3);">Waiting on seller</p>
    <?php endif; ?>
  </div>

  <?php if ($settlement['status'] === 'completed'): ?>
    <p style="background:var(--emerald-soft); color:var(--emerald-deep); padding:12px; border-radius:10px; font-size:13px;">
      ✓ Settlement complete. EMD processed and released.
    </p>
  <?php elseif ($settlement['status'] === 'stalled'): ?>
    <div style="background:var(--amber-soft); color:#9C5B1F; padding:12px; border-radius:10px; font-size:13px;">
      <p style="margin:0 0 10px;">⚠️ This settlement stalled (BR-39) — flagged after sitting incomplete too long.</p>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/force-resolve">
        <p style="font-size:11px; margin:0 0 6px;">Tenant Admin action — applies forced-neutral (3.0★) ratings for whoever never rated, and force-confirms any missing NOC.</p>
        <button type="submit" class="btn btn-ghost" style="font-size:12px;">Force-resolve</button>
      </form>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
