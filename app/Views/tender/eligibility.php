<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:560px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Manage Tender Eligibility</h1>
  <p style="color:var(--ink-3); font-size:13px;"><?= esc($saleEvent['ern']) ?></p>

  <h3 style="font-size:15px; margin-top:24px;">Buyers Who Registered Interest</h3>
  <?php foreach ($interested as $i): ?>
    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--line);">
      <span style="font-size:13px;">Party: <?= esc($i['party_id']) ?></span>
      <?php if (!in_array($i['party_id'], $eligiblePartyIds, true)): ?>
        <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/tender/eligibility/grant">
          <input type="hidden" name="party_id" value="<?= esc($i['party_id']) ?>">
          <button type="submit" class="btn btn-emerald" style="font-size:11px; padding:6px 12px;">Approve</button>
        </form>
      <?php else: ?>
        <span style="font-size:11px; color:var(--emerald);">✓ Eligible</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (empty($interested)): ?><p style="font-size:12px; color:var(--ink-3);">No interest registered yet.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:24px;">Add a Buyer Directly (by mobile number)</h3>
  <form method="post" action="/sale-events/<?= esc($saleEvent['id']) ?>/tender/eligibility/grant" style="display:flex; gap:8px;">
    <input type="text" name="mobile_number" placeholder="+919876543210" style="flex:1; padding:10px; border:1px solid var(--line); border-radius:8px;">
    <button type="submit" class="btn btn-ghost">Add</button>
  </form>

  <h3 style="font-size:15px; margin-top:24px;">Eligible Buyers (<?= count($eligible) ?>)</h3>
  <?php foreach ($eligible as $e): ?>
    <p style="font-size:13px; padding:8px 0; border-bottom:1px solid var(--line);"><?= esc($e['party_id']) ?> — <span style="color:var(--ink-3); font-size:11px;"><?= esc($e['source']) ?></span></p>
  <?php endforeach; ?>
</main>
<?= $this->endSection() ?>
