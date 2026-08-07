<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Lot Reach &amp; Interest</h1>
  <p style="color:var(--ink-3); font-size:13px; max-width:640px;">For each live <?= strtolower(tsx_term('Listing')) ?>, see which <?= tsx_term('Buyer', false, true) ?> match on category, location and value preferences — plus who's actually viewed or favorited it — so you know who to reach out to.</p>

  <?php $flashSuccess = session()->getFlashdata('success'); $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashSuccess): ?><p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashSuccess) ?></p><?php endif; ?>
  <?php if ($flashError): ?><p style="color:#B5482F; font-size:13px; background:#FBE8E4; padding:10px; border-radius:8px;"><?= esc($flashError) ?></p><?php endif; ?>

  <div style="display:flex; gap:16px; margin:20px 0;">
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['totals']['lots'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Live <?= tsx_term('Listing', false, true) ?></p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0; color:var(--emerald);"><?= (int) $summary['totals']['matched'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Full Matches (Category + Location + Value)</p>
    </div>
    <div style="flex:1; border:1px solid var(--line); border-radius:12px; padding:16px;">
      <p style="font-size:22px; font-weight:800; margin:0;"><?= (int) $summary['totals']['viewed'] ?></p>
      <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;">Total Views</p>
    </div>
  </div>

  <?php foreach ($summary['listings'] as $entry): $l = $entry['listing']; ?>
    <div style="border:1px solid var(--line); border-radius:14px; padding:16px; margin-bottom:14px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <p style="font-size:14px; font-weight:700; margin:0;"><a href="/listings/<?= esc($l['id']) ?>" style="color:inherit;"><?= esc($l['category']) ?><?= $l['subcategory'] ? ' — '.esc($l['subcategory']) : '' ?></a></p>
          <p style="font-size:11px; color:var(--ink-3); margin:2px 0 0;"><?= (int) $l['view_count'] ?> views · <?= $entry['fullMatchCount'] ?> full match<?= $entry['fullMatchCount'] === 1 ? '' : 'es' ?></p>
        </div>
        <?php if (!empty($entry['matchedBuyers'])): ?>
          <details style="text-align:right;">
            <summary class="btn btn-ghost" style="font-size:11px; cursor:pointer; display:inline-block;">Message matched buyers</summary>
            <form method="post" action="/listings/<?= esc($l['id']) ?>/reach/message" style="margin-top:10px; text-align:left;"><?= csrf_field() ?>
              <p style="font-size:11px; color:var(--ink-3); margin:0 0 6px;">Sending to <?= count($entry['matchedBuyers']) ?> matched <?= strtolower(tsx_term('Buyer', false, true)) ?>. Delivered to their Messages inbox.</p>
              <textarea name="message_body" required rows="2" placeholder="e.g. This listing matches your preferences and closes soon."
                style="display:block; width:280px; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px; margin-bottom:6px;"></textarea>
              <button type="submit" class="btn btn-emerald" style="font-size:11px;">Send</button>
            </form>
          </details>
        <?php endif; ?>
      </div>

      <?php if (!empty($entry['matchedBuyers'])): ?>
        <table style="width:100%; margin-top:12px; font-size:12px; border-collapse:collapse;">
          <thead>
            <tr style="color:var(--ink-3); text-align:left;">
              <th style="padding:4px 0;">Buyer</th><th>Category</th><th>Location</th><th>Value</th><th>Viewed</th><th>Favorited</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entry['matchedBuyers'] as $m): ?>
              <tr style="border-top:1px solid var(--line-soft, var(--line));">
                <td style="padding:6px 0; font-family:monospace; font-size:11px;"><?= esc(substr($m['partyId'], 0, 8)) ?>…</td>
                <td><?= $m['categoryMatch'] ? '✓' : '—' ?></td>
                <td><?= $m['locationMatch'] ? '✓' : '—' ?></td>
                <td><?= $m['valueMatch'] ? '✓' : '—' ?></td>
                <td><?= $m['viewed'] ? '✓' : '—' ?></td>
                <td><?= $m['favorited'] ? '✓' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="font-size:12px; color:var(--ink-3); margin-top:10px;">No buyers matched on any dimension yet.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (empty($summary['listings'])): ?>
    <p style="font-size:13px; color:var(--ink-3);">No live listings yet — <a href="/listings/create">create one</a> to start tracking reach.</p>
  <?php endif; ?>
</main>
<?= $this->endSection() ?>
