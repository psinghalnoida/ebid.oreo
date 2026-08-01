<?php // BR-31/32 (D-87/D-88): Fee Payer Election -- set once per Trading
// Session, before it opens; locked once bidding is live. Seller-Pays is
// only offered on a paid TSX tier (TenantBillingService bills it monthly
// -- see D-88); a CoCo Starter TSX only ever sees Buyer-Pays. ?>
<label style="font-size:12px; color:var(--ink-3);">Fee Payer Election — locked once bidding is live</label>
<select name="fee_payer" style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
  <option value="buyer_pays"><?= tsx_term('Buyer') ?>-Pays (default) — <?= strtolower(tsx_term('Buyer')) ?>'s EMD covers the Success Fee</option>
  <option value="seller_pays" <?= empty($sellerPaysAllowed) ? 'disabled' : '' ?>><?= tsx_term('Seller') ?>-Pays — you absorb the Success Fee, <?= strtolower(tsx_term('Buyer')) ?> sees no premium<?= empty($sellerPaysAllowed) ? ' (requires a paid ' . tsx_term('Tenant') . ' tier)' : '' ?></option>
</select>
