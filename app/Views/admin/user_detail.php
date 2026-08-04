<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;"><?= esc($party['full_name'] ?: $party['mobile_number']) ?></h1>
  <p style="color:var(--ink-3); font-size:12px;"><?= esc($party['mobile_number']) ?> · <?= esc($party['entity_type']) ?> · KYC: <?= esc($party['kyc_status'] ?: 'pending') ?></p>

  <div style="display:flex; gap:16px; margin:16px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= number_format((float) $party['star_rating'], 1) ?>★</p>
      <p style="font-size:11px; color:var(--ink-3);"><?= tsx_term('Buyer') ?> Rating · <?= (int) $party['offence_count_buyer'] ?> offences</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= number_format((float) $party['seller_star_rating'], 1) ?>★</p>
      <p style="font-size:11px; color:var(--ink-3);"><?= tsx_term('Seller') ?> Rating · <?= (int) $party['offence_count_seller'] ?> offences</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:14px;">
      <p style="font-size:20px; font-weight:800; margin:0;"><?= (int) $party['standing_review_complaint_count'] ?></p>
      <p style="font-size:11px; color:var(--ink-3);">Standing Complaints · <?= (int) $party['standing_review_cbs_offense_count'] ?> CBS offenses</p>
    </div>
  </div>

  <?php if ($party['seller_delisted_at']): ?>
    <p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;">Delisted for confirmed fraud on <?= esc(substr($party['seller_delisted_at'], 0, 10)) ?> — <?= esc($party['seller_delisted_reason']) ?></p>
  <?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Roles</h3>
  <?php if (empty($roles)): ?><p style="font-size:12px; color:var(--ink-3);">No active roles.</p><?php endif; ?>
  <ul style="font-size:13px; padding-left:18px;">
    <?php foreach ($roles as $r): ?>
      <li><?= esc($r['role']) ?><?= $r['tenant_id'] ? ' — ' . esc($tenantNames[$r['tenant_id']] ?? $r['tenant_id']) : ' (platform-wide)' ?></li>
    <?php endforeach; ?>
  </ul>

  <div style="border:1px solid var(--line); border-radius:12px; padding:14px; margin-top:12px;">
    <p style="font-size:13px; font-weight:700; margin:0 0 8px;">Promote to <?= tsx_term('Tenant Admin') ?></p>
    <p style="font-size:11px; color:var(--ink-3); margin:0 0 10px;">PR-08. BR-44: this auto-demotes whoever currently holds <?= tsx_term('Tenant Admin') ?> for the selected <?= strtolower(tsx_term('Tenant')) ?>.</p>
    <form method="post" action="/admin/users/<?= esc($party['id']) ?>/promote-tenant-admin" style="display:flex; gap:8px;"><?= csrf_field() ?>
      <select name="tenant_id" required style="flex:1; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
        <option value="">Select a <?= strtolower(tsx_term('Tenant')) ?>…</option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" style="padding:8px 14px; border-radius:8px; border:none; background:var(--emerald-deep); color:#fff; font-size:12px; font-weight:700;">Grant</button>
    </form>
  </div>

  <h3 style="font-size:15px; margin-top:20px;">Recent Purchases (as <?= strtolower(tsx_term('Buyer')) ?>)</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <?php foreach ($purchases as $p): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">₹<?= number_format((float) $p['final_price'], 2) ?></td>
      <td><?= esc($p['status']) ?></td>
      <td><?= esc(substr($p['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($purchases)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Recent Sales (as <?= strtolower(tsx_term('Seller')) ?>)</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <?php foreach ($sales as $s): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;">₹<?= number_format((float) $s['final_price'], 2) ?></td>
      <td><?= esc($s['status']) ?></td>
      <td><?= esc(substr($s['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($sales)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Disputes (filed or against)</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <?php foreach ($disputes as $d): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($d['category']) ?></td>
      <td><?= esc($d['status']) ?></td>
      <td><?= esc($d['filed_by_party_id'] === $party['id'] ? 'Filed by them' : 'Against them') ?></td>
      <td><?= esc(substr($d['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($disputes)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <h3 style="font-size:15px; margin-top:20px;">Rating Events (BR-35)</h3>
  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <?php foreach ($ratingEvents as $e): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($e['event_key'] ?? $e['event_type']) ?></td>
      <td><?= esc($e['rating_role']) ?></td>
      <td><?= number_format((float) $e['previous_value'], 1) ?>★ → <?= number_format((float) $e['new_value'], 1) ?>★</td>
      <td><?= esc(substr($e['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($ratingEvents)): ?><p style="font-size:12px; color:var(--ink-3);">None.</p><?php endif; ?>

  <p style="margin-top:20px;"><a href="/admin/users" style="color:var(--ink-3); font-size:12px;">&larr; Back to Users</a></p>
</main>
<?= $this->endSection() ?>
