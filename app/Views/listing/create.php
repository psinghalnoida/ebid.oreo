<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<main style="max-width:520px; padding:60px 24px;">
  <h1 style="font-size:24px;">List an Asset</h1>
  <p style="color:var(--ink-2); font-size:14px;">Tell us about the asset — this is the information every <?= strtolower(tsx_term('Listing')) ?> needs before it can go live.</p>
  <?php if (!empty($error)): ?>
    <p style="color:#B5482F; font-size:13px;"><?= esc($error) ?></p>
  <?php endif; ?>
  <form method="post" action="/listings" id="listingCreateForm"><?= csrf_field() ?>
    <label style="font-size:12px; color:var(--ink-3);"><?= tsx_term('Tenant') ?></label>
    <select name="tenant_id" required style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <?php foreach ($tenants as $t): ?>
        <option value="<?= esc($t['id']) ?>"><?= esc($t['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label style="font-size:12px; color:var(--ink-3);">Title (optional)</label>
    <input type="text" name="title" id="listingTitleInput" placeholder="A clear, specific title for this listing"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Physical Condition</label>
    <input type="text" name="physical_condition" required placeholder="e.g. Fire-damaged, functional unverified"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Category</label>
    <select name="category" required style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">
      <option value="">Select a category…</option>
      <?php foreach (\App\Libraries\ListingLifecycleService::PERMITTED_CATEGORIES as $c): ?>
        <option value="<?= esc($c) ?>"><?= esc($c) ?></option>
      <?php endforeach; ?>
    </select>

    <label style="font-size:12px; color:var(--ink-3);">Subcategory (optional)</label>
    <input type="text" name="subcategory"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Quantity</label>
    <input type="number" name="quantity" required value="1" min="1"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Make / Model</label>
    <input type="text" name="make_model"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Yard Location Address</label>
    <input type="text" name="yard_location_address" required
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Yard Location PIN (6-digit)</label>
    <input type="text" name="yard_location_pin" required maxlength="6" pattern="\d{6}"
      style="display:block; width:100%; padding:12px; margin:6px 0 14px; border:1px solid var(--line); border-radius:10px;">

    <label style="font-size:12px; color:var(--ink-3);">Photo Certification</label>
    <select name="media_tier" style="display:block; width:100%; padding:12px; margin:6px 0 20px; border:1px solid var(--line); border-radius:10px;">
      <option value="certified_by_seller">Certified by <?= tsx_term('Seller') ?> — I'll upload my own photos</option>
      <option value="verified">Verified — AdwitiX's inspection team photographs it (inspection fee applies)</option>
    </select>

    <p style="font-size:12px; color:var(--ink-3); margin:16px 0 4px;">Inspection Authority — optional, by mobile number. Anyone bound here is blocked from bidding/offering on this <?= strtolower(tsx_term('Listing')) ?>.</p>
    <input type="text" name="inspector_mobile" placeholder="Yard Inspector mobile (optional)"
      style="display:block; width:100%; padding:10px; margin:4px 0; border:1px solid var(--line); border-radius:8px; font-size:13px;">
    <input type="text" name="surveyor_mobile" placeholder="Surveyor mobile (optional)"
      style="display:block; width:100%; padding:10px; margin:4px 0; border:1px solid var(--line); border-radius:8px; font-size:13px;">
    <input type="text" name="custodian_mobile" placeholder="Physical Custodian mobile (optional)"
      style="display:block; width:100%; padding:10px; margin:4px 0 20px; border:1px solid var(--line); border-radius:8px; font-size:13px;">

    <p style="font-size:12px; color:var(--ink-3); margin:16px 0 4px;">Related Auctions — optional. Use the same label across multiple <?= strtolower(tsx_term('Listing', false, true)) ?> to group them (e.g., a shared-origin lot). Not available on Express.</p>
    <input type="text" name="related_group_label" placeholder="e.g. Flood-Affected Fleet — July 2026"
      style="display:block; width:100%; padding:10px; margin:4px 0 20px; border:1px solid var(--line); border-radius:8px; font-size:13px;">

    <p style="font-size:12px; color:var(--ink-3); margin:16px 0 4px;">Shipping — always optional for the <?= strtolower(tsx_term('Buyer')) ?>, who can self-collect for free regardless.</p>
    <label style="display:block; font-size:13px; padding:4px 0 10px;">
      <input type="checkbox" name="shipping_enabled" value="1" id="shipping-toggle" onchange="document.getElementById('shipping-options').style.display = this.checked ? 'block' : 'none';">
      I can also offer to ship this item
    </label>
    <div id="shipping-options" style="display:none; margin-bottom:16px;">
      <label style="font-size:12px; color:var(--ink-3);">
        <input type="radio" name="shipping_cost_type" value="fixed" checked> Fixed Cost
      </label>
      <label style="font-size:12px; color:var(--ink-3); margin-left:16px;">
        <input type="radio" name="shipping_cost_type" value="variable"> Variable (distance-based)
      </label>
      <input type="number" name="shipping_fixed_cost" placeholder="Fixed cost (₹)"
        style="display:block; width:100%; padding:10px; margin:8px 0; border:1px solid var(--line); border-radius:8px;">
      <input type="number" name="shipping_variable_rate_per_km" placeholder="Rate per km (₹)"
        style="display:block; width:100%; padding:10px; margin:8px 0; border:1px solid var(--line); border-radius:8px;">
    </div>

    <label style="display:block; font-size:13px; padding:4px 0 16px;">
      <input type="checkbox" name="media_is_representative_under_waiver" value="1">
      Use representative imagery under an active <?= tsx_term('Tenant') ?> Media Waiver (only works if your <?= strtolower(tsx_term('Tenant')) ?> has one approved for this category)
    </label>

    <div style="margin: 20px 0; padding: 16px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper-2, #F8F9F5);">
      <button type="button" id="aiPreAuditBtn" class="btn btn-ghost" style="width:100%;">✦ Check with AI</button>
      <p style="font-size:11px; color:var(--ink-3); margin:8px 0 0;">Advisory only — reviews your draft and suggests improvements. Never approves or blocks submission; your <?= strtolower(tsx_term('Tenant Admin')) ?> still reviews every <?= strtolower(tsx_term('Listing')) ?>.</p>
      <div id="aiPreAuditResult" style="display:none; margin-top:14px; font-size:13px;"></div>
    </div>

    <button type="submit" class="btn btn-emerald" style="width:100%;">Create <?= tsx_term('Listing') ?></button>
  </form>
  <p style="font-size:11px; color:var(--ink-3); margin-top:10px;">
    Your progress on this form is auto-saved to your browser and restored if you reload or switch tabs.
    <a href="#" id="clearDraftLink" style="color:var(--emerald);">Clear saved draft</a>
    <span id="draftRestoredNote" style="display:none;"> — a saved draft was restored below.</span>
  </p>
</main>
<script {csp-script-nonce}>
(function () {
  // PR-09: "A background auto-save persists the seller's in-progress
  // form ... (browser localStorage), enabling recovery after a reload
  // or tab switch." Honest limitation: localStorage cannot hold File
  // objects (no serialization support, and well under the size of a
  // real photo/video anyway) — only the TEXT/SELECT/CHECKBOX field
  // values are recoverable this way, matching BR-45's precedent of
  // flagging an honest web-platform limitation rather than silently
  // overclaiming. Uploaded files themselves are not restorable after a
  // reload; the seller re-selects them.
  var DRAFT_KEY = 'ebidhub_listing_draft_v1';
  var form = document.getElementById('listingCreateForm');
  if (!form) return;

  function fieldsForDraft() {
    return form.querySelectorAll('input[type=text], input[type=number], input[type=checkbox], input[type=radio], textarea, select');
  }

  function restoreDraft() {
    var raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    var data;
    try { data = JSON.parse(raw); } catch (e) { return; }
    var restoredAny = false;
    fieldsForDraft().forEach(function (el) {
      if (!el.name || !(el.name in data)) return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = data[el.name] === true || data[el.name] === el.value;
      } else {
        el.value = data[el.name];
      }
      restoredAny = true;
    });
    if (restoredAny) {
      var note = document.getElementById('draftRestoredNote');
      if (note) note.style.display = 'inline';
      var toggle = document.getElementById('shipping-toggle');
      var opts = document.getElementById('shipping-options');
      if (toggle && opts) opts.style.display = toggle.checked ? 'block' : 'none';
    }
  }

  function saveDraft() {
    var data = {};
    fieldsForDraft().forEach(function (el) {
      if (!el.name) return;
      if (el.type === 'checkbox') { data[el.name] = el.checked; }
      else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
      else { data[el.name] = el.value; }
    });
    localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
  }

  restoreDraft();
  form.addEventListener('input', saveDraft);
  form.addEventListener('change', saveDraft);

  // Cleared on genuine submit, not kept around to stale-fill the NEXT
  // listing's form — recovery is for reload/tab-switch before
  // submitting, per PR-09's own wording, not for a server-side
  // validation failure after submission.
  form.addEventListener('submit', function () {
    localStorage.removeItem(DRAFT_KEY);
  });

  var clearLink = document.getElementById('clearDraftLink');
  if (clearLink) {
    clearLink.addEventListener('click', function (e) {
      e.preventDefault();
      localStorage.removeItem(DRAFT_KEY);
      form.reset();
      var note = document.getElementById('draftRestoredNote');
      if (note) note.style.display = 'none';
    });
  }

  // BR-46: purely advisory -- this never touches form validation or
  // blocks submit. "Apply" just fills the Title field; the seller
  // still reviews and submits themselves.
  var aiBtn = document.getElementById('aiPreAuditBtn');
  var aiResult = document.getElementById('aiPreAuditResult');
  if (aiBtn) {
    aiBtn.addEventListener('click', function () {
      aiBtn.disabled = true;
      var originalLabel = aiBtn.textContent;
      aiBtn.textContent = 'Checking…';

      var body = new URLSearchParams();
      body.append('category', form.category.value);
      body.append('subcategory', form.subcategory.value);
      body.append('physical_condition', form.physical_condition.value);
      body.append('quantity', form.quantity.value);
      body.append('make_model', form.make_model.value);
      // CSRF: this is a fetch(), not a real <form> submit, so csrf_field()
      // never touches it -- the token has to be appended manually.
      body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

      fetch('/listings/pre-audit', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          aiResult.style.display = 'block';
          if (!data.available) {
            aiResult.innerHTML = '<p style="color:var(--ink-3);">AI pre-check is not currently available.</p>';
            return;
          }
          var html = '<p><strong>Quality Score:</strong> ' + data.qualityScore + '/100</p>';
          if (data.suggestedTitle) {
            html += '<p><strong>Suggested Title:</strong> ' + data.suggestedTitle
              + ' <a href="#" id="applyTitleLink">Apply</a></p>';
          }
          if (data.missingFields && data.missingFields.length) {
            html += '<p><strong>Consider adding:</strong> ' + data.missingFields.join(', ') + '</p>';
          }
          aiResult.innerHTML = html;

          var applyLink = document.getElementById('applyTitleLink');
          if (applyLink) {
            applyLink.addEventListener('click', function (e) {
              e.preventDefault();
              var titleInput = document.getElementById('listingTitleInput');
              if (titleInput) titleInput.value = data.suggestedTitle;
            });
          }
        })
        .catch(function () {
          aiResult.style.display = 'block';
          aiResult.innerHTML = '<p style="color:var(--ink-3);">AI pre-check is not currently available.</p>';
        })
        .finally(function () {
          aiBtn.disabled = false;
          aiBtn.textContent = originalLabel;
        });
    });
  }
})();
</script>
<?= $this->endSection() ?>
