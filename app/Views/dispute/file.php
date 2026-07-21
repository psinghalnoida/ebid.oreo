<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:520px; padding:60px 24px;">
  <h1 style="font-size:24px;">File a Dispute</h1>
  <p style="color:var(--ink-2); font-size:14px;">BR-40: choose the category that best matches your situation. Tender Auctions are excluded from this process entirely.</p>
  <form method="post" action="/sale-events/<?= esc($saleEventId) ?>/dispute">
    <label style="font-size:12px; color:var(--ink-3);">Category</label>
    <select name="category" required style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <option value="payment">Payment Dispute</option>
      <option value="condition_delivery">Condition/Delivery Dispute</option>
      <option value="non_lifting_collection">Non-Lifting/Collection Dispute</option>
      <option value="auction_rejection">Auction Rejection Dispute</option>
      <option value="buyer_non_response">Buyer Non-Response Dispute</option>
    </select>
    <label style="font-size:12px; color:var(--ink-3);">Description</label>
    <textarea name="description" required rows="5" style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;"></textarea>
    <button type="submit" class="btn btn-emerald" style="width:100%;">File Dispute</button>
  </form>
</main>
<?= $this->endSection() ?>
