<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:640px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">My Offers</h1>

  <form method="get" action="/my-offers" style="margin:16px 0; display:flex; gap:8px;">
    <select name="status" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
      <option value="">All statuses</option>
      <?php foreach (['submitted', 'accepted', 'rejected', 'withdrawn', 'lapsed'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Filter</button>
  </form>

  <?php foreach ($offers as $o): ?>
    <div style="border-bottom:1px solid var(--line); padding:10px 0; display:flex; justify-content:space-between; align-items:center;">
      <a href="/listings/<?= esc($o['listing_id']) ?>" style="text-decoration:none; color:inherit; font-size:13px;">
        <?= esc($o['category']) ?> — ₹<?= number_format((float) $o['amount'], 2) ?>
      </a>
      <span style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:11px; color:var(--ink-3); text-transform:uppercase;"><?= esc($o['status']) ?></span>
        <?php if ($o['status'] === 'submitted'): ?>
          <form method="post" action="/offers/<?= esc($o['id']) ?>/withdraw" style="display:inline;"><?= csrf_field() ?>
            <input type="text" name="reason" placeholder="Reason" required style="font-size:11px; padding:4px; border:1px solid var(--line); border-radius:6px; width:100px;">
            <button type="submit" class="btn btn-ghost" style="font-size:11px; padding:4px 8px;">Withdraw</button>
          </form>
        <?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (empty($offers)): ?><p style="font-size:12px; color:var(--ink-3);">No offers match.</p><?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:8px; margin-top:16px; font-size:12px;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&status=<?= esc($status) ?>" style="<?= $p === $page ? 'font-weight:800; color:var(--emerald);' : 'color:var(--ink-3);' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <p style="font-size:11px; color:var(--ink-3); margin-top:8px;"><?= (int) $total ?> total</p>
</main>
<?= $this->endSection() ?>
