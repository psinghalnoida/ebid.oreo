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
  <?php elseif ($settlement['status'] === 'payout_held'): ?>
    <div style="background:var(--amber-soft); color:#9C5B1F; padding:12px; border-radius:10px; font-size:13px;">
      <?php if ($payoutHold && $payoutHold['status'] === 'pending'): ?>
        <p style="margin:0 0 10px;">⚠️ BR-50: this is a high-value payout (₹<?= number_format((float) $payoutHold['amount'], 2) ?>) pending release to a recently-changed bank account — held for Tenant Admin or SaaS Admin review.</p>
        <?php if (!empty($isReviewAdmin)): ?>
          <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/payout-hold/decide">
            <textarea name="notes" placeholder="Review notes" rows="2"
              style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
            <button type="submit" name="outcome" value="release" class="btn btn-emerald" style="font-size:12px;">Release Payout</button>
            <button type="submit" name="outcome" value="reject" class="btn btn-ghost" style="font-size:12px;">Reject — Hold for Investigation</button>
          </form>
        <?php endif; ?>
      <?php elseif ($payoutHold && $payoutHold['status'] === 'rejected'): ?>
        <p style="margin:0;">⛔ BR-50: this payout (₹<?= number_format((float) $payoutHold['amount'], 2) ?>) was rejected on review and needs manual investigation before it can proceed — it will not release automatically.</p>
        <?php if ($payoutHold['review_notes']): ?><p style="margin:8px 0 0; font-size:12px;"><?= esc($payoutHold['review_notes']) ?></p><?php endif; ?>
      <?php else: ?>
        <p style="margin:0;">⚠️ BR-50: the buyer's payout bank account is still in its mandatory 24-hour cooling-off period — release will happen automatically once it lapses.</p>
      <?php endif; ?>
    </div>
  <?php elseif ($settlement['status'] === 'stalled'): ?>
    <div style="background:var(--amber-soft); color:#9C5B1F; padding:12px; border-radius:10px; font-size:13px;">
      <p style="margin:0 0 10px;">⚠️ This settlement stalled (BR-39) — flagged after sitting incomplete too long.</p>
      <form method="post" action="/settlements/<?= esc($settlement['id']) ?>/force-resolve">
        <p style="font-size:11px; margin:0 0 6px;">Tenant Admin action — applies forced-neutral (3.0★) ratings for whoever never rated, and force-confirms any missing NOC.</p>
        <button type="submit" class="btn btn-ghost" style="font-size:12px;">Force-resolve</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!empty($invoices)): ?>
    <h3 style="font-size:15px; margin-top:24px;">Invoices (BR-56)</h3>
    <?php foreach ($invoices as $inv): ?>
      <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
        <p style="font-size:12px; color:var(--ink-3); margin:0 0 4px;"><?= esc($inv['invoice_number']) ?> — <?= esc(str_replace('_', ' ', $inv['invoice_type'])) ?></p>
        <p style="font-size:13px; margin:0 0 4px;"><?= esc($inv['issued_by_name']) ?> → <?= esc($inv['issued_to_name']) ?></p>
        <p style="font-size:13px; margin:0;">
          ₹<?= number_format((float) $inv['base_amount'], 2) ?> + GST (<?= esc($inv['gst_rate_percent']) ?>%) ₹<?= number_format((float) $inv['gst_amount'], 2) ?>
          = <strong>₹<?= number_format((float) $inv['total_amount'], 2) ?></strong>
        </p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
