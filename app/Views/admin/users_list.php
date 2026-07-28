<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Users</h1>
  <form method="get" action="/admin/users" style="margin:16px 0;">
    <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Search mobile, name, email, GSTIN"
      style="width:340px; padding:10px 12px; border:1px solid var(--line); border-radius:10px;">
    <button type="submit" class="btn btn-ghost">Search</button>
  </form>

  <table style="width:100%; border-collapse:collapse; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Mobile</th><th>Name</th><th>Type</th><th>KYC</th><th>Buyer★</th><th>Seller★</th><th>Status</th>
    </tr>
    <?php foreach ($users as $u): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><a href="/admin/users/<?= esc($u['id']) ?>" style="color:var(--emerald);"><?= esc($u['mobile_number']) ?></a></td>
      <td><?= esc($u['full_name'] ?: '—') ?></td>
      <td><?= esc($u['entity_type']) ?></td>
      <td><?= esc($u['kyc_status'] ?: 'pending') ?></td>
      <td><?= number_format((float) $u['star_rating'], 1) ?>★</td>
      <td><?= number_format((float) $u['seller_star_rating'], 1) ?>★</td>
      <td>
        <?php if ($u['seller_delisted_at']): ?><span style="color:#B5482F;">Delisted</span>
        <?php elseif ($u['shadow_banned_at_seller'] || $u['shadow_banned_at_buyer']): ?><span style="color:var(--amber, #9C5B1F);">Shadow-banned</span>
        <?php else: ?>Active<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($users)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No matching users.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
