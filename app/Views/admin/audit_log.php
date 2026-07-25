<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:900px; padding:40px 24px;">
  <h1 style="font-size:22px;">Audit Log</h1>
  <p style="color:var(--ink-3); font-size:12px;">BR-05 — showing the 100 most recent entries. <a href="/admin/audit-log/verify" style="color:var(--emerald);">Verify chain integrity →</a></p>

  <form method="get" action="/admin/audit-log" style="display:flex; gap:8px; margin:16px 0;">
    <input type="text" name="event_type" value="<?= esc($eventType) ?>" placeholder="Filter by event type" style="flex:1; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <input type="text" name="actor_mobile" value="<?= esc($actorMobile) ?>" placeholder="Filter by actor mobile" style="flex:1; padding:8px; border:1px solid var(--line); border-radius:8px; font-size:12px;">
    <button type="submit" class="btn btn-ghost" style="font-size:12px;">Filter</button>
  </form>

  <table style="width:100%; border-collapse:collapse; font-size:12px;">
    <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
      <th style="padding:6px 0;">#</th><th>Time</th><th>Event</th><th>Actor</th><th>IP</th><th>Payload</th>
    </tr>
    <?php foreach ($entries as $e): ?>
    <tr style="border-top:1px solid var(--line);">
      <td style="padding:6px 0;"><?= esc($e['sequence_number']) ?></td>
      <td><?= esc($e['occurred_at']) ?></td>
      <td><?= esc($e['event_type']) ?></td>
      <td><?= esc($e['mobile_number'] ?? 'system') ?></td>
      <td><?= esc($e['ip_address'] ?? '—') ?></td>
      <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= esc($e['payload']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>
<?= $this->endSection() ?>
