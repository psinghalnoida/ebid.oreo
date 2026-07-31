<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:720px; padding:40px 24px;">
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <p style="color:var(--emerald-deep); font-size:13px; background:var(--emerald-soft); padding:10px; border-radius:8px;"><?= esc($flashError) ?></p>
  <?php endif; ?>

  <h1 style="font-size:22px;">API Access — <?= esc($tenant['name']) ?></h1>
  <p style="font-size:12px; color:var(--ink-3);">BR-62-66: integrate your own systems with the platform as an alternative to this portal. The API grants no privilege the portal doesn't already grant, and bypasses none of the same approval/lifecycle governance.</p>

  <?php if (!$hasApiAccess): ?>
    <p style="font-size:13px; background:#FBE8E4; color:#B5482F; padding:12px; border-radius:8px; margin-top:16px;">BR-66: a CoCo Starter TSX has no API access. Upgrade to TSX Launch or above to enable it.</p>
  <?php else: ?>

    <p style="font-size:12px; color:var(--ink-3); margin-top:12px;">
      Current tier permissions:
      Read (status/reporting) — yes.
      Push Lots — <?= $canPushListings ? 'yes' : 'no (requires TSX Growth or above)' ?>.
      Push Sale Systems — <?= $canPushSaleEvents ? 'yes' : 'no (requires TSX Enterprise)' ?>.
    </p>

    <?php $newCredential = session()->getFlashdata('newCredential'); ?>
    <?php if ($newCredential): ?>
      <div style="border:1px solid var(--line); border-radius:12px; padding:16px; margin-top:16px; background:var(--line-soft);">
        <p style="font-size:12px; font-weight:700; margin:0 0 8px;">New credential issued — the secret is shown once only, save it now.</p>
        <p style="font-size:12px; font-family:monospace; margin:0 0 4px;">client_id: <?= esc($newCredential['clientId']) ?></p>
        <p style="font-size:12px; font-family:monospace; margin:0;">client_secret: <?= esc($newCredential['clientSecret']) ?></p>
      </div>
    <?php endif; ?>

    <h3 style="font-size:15px; margin-top:24px;">Credentials</h3>
    <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/api-access/credentials">
      <button type="submit" class="btn btn-emerald" style="font-size:12px;">Issue New Credential</button>
    </form>
    <table style="width:100%; border-collapse:collapse; margin-top:12px; font-size:12px;">
      <tr style="text-align:left; color:var(--ink-3); font-size:10px; text-transform:uppercase;">
        <th style="padding:6px 0;">Client ID</th><th>Status</th><th>Last Used</th><th></th>
      </tr>
      <?php foreach ($credentials as $c): ?>
      <tr style="border-top:1px solid var(--line);">
        <td style="padding:6px 0; font-family:monospace;"><?= esc($c['client_id']) ?></td>
        <td><?= esc(ucfirst($c['status'])) ?></td>
        <td><?= $c['last_used_at'] ? esc(substr($c['last_used_at'], 0, 16)) : 'Never' ?></td>
        <td>
          <?php if ($c['status'] === 'active'): ?>
          <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/api-access/credentials/<?= esc($c['id']) ?>/revoke">
            <button type="submit" class="btn btn-ghost" style="font-size:11px; padding:4px 10px;">Revoke</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (empty($credentials)): ?><p style="font-size:12px; color:var(--ink-3); margin-top:8px;">None issued yet.</p><?php endif; ?>

    <h3 style="font-size:15px; margin-top:28px;">Webhook Delivery (PR-37)</h3>
    <p style="font-size:12px; color:var(--ink-3);">listing.approved, sale_event.created, listing.archived, settlement.completed, and dispute.filed events are POSTed here, signed with X-TSX-Signature (HMAC-SHA256).</p>
    <form method="post" action="/tenants/<?= esc($tenant['id']) ?>/api-access/webhook-url">
      <input type="text" name="webhook_url" value="<?= esc($tenant['webhook_url'] ?? '') ?>" placeholder="https://your-system.example.com/webhooks/tsx"
        style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <button type="submit" class="btn btn-ghost">Save Webhook URL</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:24px;"><a href="/tenants/<?= esc($tenant['id']) ?>/dashboard" style="color:var(--ink-3); font-size:12px;">&larr; Back to Tenant Dashboard</a></p>
</main>
<?= $this->endSection() ?>
