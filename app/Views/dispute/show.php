<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:560px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <span class="pc-badge" style="background:var(--emerald-soft); color:var(--emerald-deep); padding:5px 12px; border-radius:100px; font-size:11px; font-weight:700;">
    <?= esc(strtoupper($dispute['status'])) ?>
  </span>
  <h1 style="font-size:22px; margin:12px 0 4px;"><?= esc(str_replace('_', ' ', ucfirst($dispute['category']))) ?></h1>
  <p style="color:var(--ink-2); font-size:14px;"><?= esc($dispute['description']) ?></p>
  <p style="font-size:11px; color:var(--ink-3); text-transform:uppercase;">Ruling authority: <?= esc(str_replace('_', ' ', $dispute['ruling_authority_type'])) ?></p>

  <h3 style="font-size:14px; margin-top:24px;">Evidence</h3>
  <?php foreach ($evidence as $e): ?>
    <div style="border:1px solid var(--line); border-radius:10px; padding:12px; margin-bottom:8px; font-size:13px;">
      <?= esc($e['content']) ?>
      <div style="font-size:10px; color:var(--ink-3); margin-top:4px;"><?= esc($e['created_at']) ?></div>
    </div>
  <?php endforeach; ?>

  <?php if (in_array($dispute['status'], ['filed', 'evidence_window'], true)): ?>
    <form method="post" action="/disputes/<?= esc($dispute['id']) ?>/evidence" style="margin:12px 0;">
      <textarea name="content" required rows="3" placeholder="Submit your evidence/statement" style="display:block; width:100%; padding:10px; border:1px solid var(--line); border-radius:10px;"></textarea>
      <button type="submit" class="btn btn-ghost" style="margin-top:8px; font-size:12px;">Submit Evidence</button>
    </form>

    <div style="background:var(--line-soft); padding:14px; border-radius:10px; margin-top:16px;">
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 8px;">Ruling action — requires the correct authority (Tenant Admin or Super Admin per category, BR-40)</p>
      <form method="post" action="/disputes/<?= esc($dispute['id']) ?>/rule">
        <select name="outcome" style="padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px; margin-bottom:6px; width:100%;">
          <option value="dismissed">Dismiss the claim</option>
          <option value="force_log_noc">Force-log an NOC</option>
          <option value="order_forfeiture">Order EMD forfeiture</option>
          <option value="rating_consequence">Apply a rating consequence</option>
        </select>
        <input type="text" name="at_fault_party_id" placeholder="At-fault party ID (for forfeiture/rating)" style="display:block; width:100%; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px; margin-bottom:6px;">
        <textarea name="rationale" required placeholder="Rationale — required, identifies the evidence relied upon" rows="2" style="display:block; width:100%; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" class="btn btn-emerald" style="margin-top:8px; font-size:12px;">Issue Ruling</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($dispute['status'] === 'ruled'): ?>
    <div style="background:var(--amber-soft); color:#9C5B1F; padding:14px; border-radius:10px; margin-top:16px; font-size:13px;">
      <p style="margin:0 0 6px;"><strong>Ruling:</strong> <?= esc(str_replace('_', ' ', $dispute['ruling_outcome'])) ?></p>
      <p style="margin:0 0 10px;"><strong>Rationale:</strong> <?= esc($dispute['ruling_rationale']) ?></p>
      <?php if ($dispute['ruling_authority_type'] === 'tenant_admin'): ?>
        <form method="post" action="/disputes/<?= esc($dispute['id']) ?>/appeal">
          <button type="submit" class="btn btn-ghost" style="font-size:12px;">Appeal to Super Admin</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($dispute['status'] === 'appealed'): ?>
    <div style="background:var(--line-soft); padding:14px; border-radius:10px; margin-top:16px;">
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 8px;">Super Admin appeal ruling</p>
      <form method="post" action="/disputes/<?= esc($dispute['id']) ?>/rule-appeal">
        <textarea name="rationale" required placeholder="Appeal rationale" rows="2" style="display:block; width:100%; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" class="btn btn-emerald" style="margin-top:8px; font-size:12px;">Rule on Appeal</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($dispute['status'] === 'closed'): ?>
    <div style="background:var(--emerald-soft); color:var(--emerald-deep); padding:14px; border-radius:10px; margin-top:16px; font-size:13px;">
      <p style="margin:0;"><strong>Final (appeal):</strong> <?= esc($dispute['appeal_rationale']) ?></p>
    </div>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
