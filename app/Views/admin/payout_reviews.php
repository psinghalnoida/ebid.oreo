<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">Payout Release Reviews (BR-50)</h1>
  <p style="font-size:12px; color:var(--ink-3);">High-value (&gt;₹10L) payouts to a recently-changed bank account — <?= tsx_term('Tenant Admin') ?> (for that <?= strtolower(tsx_term('Sale Event')) ?>) or SaaS Admin may decide.</p>

  <h3 style="font-size:15px; margin-top:24px;">Pending</h3>
  <?php foreach ($pending as $r): ?>
    <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:10px;">
      <p style="font-size:13px; font-weight:700; margin:0 0 4px;">₹<?= number_format((float) $r['amount'], 2) ?> — <?= esc($r['mobile_number']) ?></p>
      <p style="font-size:12px; color:var(--ink-3); margin:0 0 10px;"><?= esc(str_replace('_', ' ', $r['release_type'])) ?></p>
      <form method="post" action="/admin/payout-reviews/<?= esc($r['id']) ?>/decide"><?= csrf_field() ?>
        <textarea name="rationale" placeholder="Decision rationale (required)" required rows="2"
          style="display:block; width:100%; padding:8px; margin-bottom:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;"></textarea>
        <button type="submit" name="decision" value="approve" class="btn btn-emerald" style="font-size:12px;">Approve Release</button>
        <button type="submit" name="decision" value="decline" class="btn btn-ghost" style="font-size:12px;">Decline</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($pending)): ?><p style="font-size:12px; color:var(--ink-3);">None pending.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:28px;">Decided</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">Amount</th><th>User</th><th>Decision</th><th>Reviewer</th><th>When</th>
    </tr>
    <?php foreach ($reviewed as $r): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">₹<?= number_format((float) $r['amount'], 2) ?></td>
      <td><?= esc($r['mobile_number']) ?></td>
      <td><?= esc(ucfirst($r['status'])) ?></td>
      <td><?= esc($r['reviewer_mobile'] ?? '—') ?></td>
      <td><?= esc(substr($r['reviewed_at'], 0, 16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($reviewed)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
