<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:760px; padding:40px 24px;">
  <h1 style="font-size:22px;">Rating History</h1>
  <p style="color:var(--ink-3); font-size:13px;">Every rating change on your account, in both roles — real, permanent, audit-trail data.</p>

  <table style="width:100%; border-collapse:collapse; font-size:13px; margin-top:16px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:11px; text-transform:uppercase;">
      <th style="padding:8px 0;">Date</th><th>Role</th><th>Type</th><th>Change</th><th>Reason</th><th>Status</th>
    </tr>
    <?php foreach ($events as $e): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:8px 0; white-space:nowrap;"><?= esc(substr($e['created_at'], 0, 10)) ?></td>
      <td><?= $e['rating_role'] === 'star_rating' ? tsx_term('Buyer') : tsx_term('Seller') ?></td>
      <td>
        <?php if ($e['event_type'] === 'upgrade'): ?>
          <span style="color:var(--emerald); font-weight:700;">Upgrade</span>
        <?php elseif ($e['event_type'] === 'downgrade'): ?>
          <span style="color:#B5482F; font-weight:700;">Downgrade</span>
        <?php else: ?>
          <span style="color:var(--ink-3); font-weight:700;">Forced Neutral</span>
        <?php endif; ?>
      </td>
      <td>★<?= number_format((float) $e['previous_value'], 1) ?> &rarr; ★<?= number_format((float) $e['new_value'], 1) ?></td>
      <td style="max-width:260px; font-size:12px; color:var(--ink-2);"><?= esc($e['reason']) ?></td>
      <td style="font-size:11px; text-transform:uppercase; color:var(--ink-3);">
        <?= esc(str_replace('_', ' ', $e['status'])) ?>
        <?php if ($e['appealed_at']): ?><br><span style="color:var(--ink-3);">Appealed<?= $e['appeal_outcome'] ? ': '.esc($e['appeal_outcome']) : '' ?></span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (empty($events)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:12px;">No rating events yet — every transaction starts at a neutral 3.0★.</p><?php endif; ?>

  <p style="margin-top:20px;"><a href="/my-star-ratings" style="color:var(--ink-3); font-size:12px;">&larr; Back to Star Ratings</a></p>

  <div id="rating-live-banner" style="display:none; position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:var(--ink-1, #1a1a1a); color:#fff; padding:10px 18px; border-radius:100px; font-size:12px; z-index:100;">
    Your rating just changed — refreshing…
  </div>
</main>
<script>
  // D-111: same reasoning as my/star_ratings.php — this event only
  // ever reaches the affected party's own channel, so any receipt
  // while viewing this history table means a new row just landed.
  window.addEventListener('ebidhub:rating_updated', function () {
    var banner = document.getElementById('rating-live-banner');
    if (banner) banner.style.display = 'block';
    setTimeout(function () { window.location.reload(); }, 1200);
  });
</script>
<?= $this->endSection() ?>
