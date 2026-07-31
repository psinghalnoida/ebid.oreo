<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:820px; padding:40px 24px;">
  <h1 style="font-size:22px;"><?= tsx_term('Seller') ?> Management — <?= esc($tenant['name']) ?></h1>
  <p style="font-size:12px; color:var(--ink-3);">Backed by the real Standing Review system (BR-61) — sorted by complaint count.</p>

  <table style="width:100%; border-collapse:collapse; margin-top:16px; font-size:13px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Mobile</th><th>Rating</th><th>Sales</th><th>Complaints</th><th>CBS Offenses</th><th>Standing</th>
    </tr>
    <?php foreach ($sellers as $s): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0;"><a href="/tenants/<?= esc($tenant['id']) ?>/sellers/<?= esc($s['party_id']) ?>" style="color:var(--emerald);"><?= esc($s['mobile_number']) ?></a></td>
      <td><?= number_format((float) $s['seller_star_rating'], 1) ?>★</td>
      <td><?= (int) $s['salesOnTenant'] ?></td>
      <td><?= (int) $s['standing_review_complaint_count'] ?></td>
      <td><?= (int) $s['standing_review_cbs_offense_count'] ?></td>
      <td>
        <?php if ($s['seller_delisted_at']): ?>
          <span style="color:#B5482F;">Delisted (fraud)</span>
        <?php elseif ($s['hasOpenCase']): ?>
          <span style="color:var(--amber, #9C5B1F);">Under Review</span>
        <?php else: ?>
          Good standing
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($sellers)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No approved <?= strtolower(tsx_term('Seller', false, true)) ?> on this <?= strtolower(tsx_term('Tenant')) ?> yet.</p><?php endif; ?>
</main>
<?= $this->endSection() ?>
