<?php

namespace App\Libraries;

use App\Models\SovereignRuleModel;
use App\Models\SovereignRuleRevisionModel;

// PR-04/BR-01/BR-04: "Secure procedure through which the Super Admin
// administers or amends rules inside the live system" — a Super Admin
// reviews current rules or defines a new one (Title, Statement, Logic),
// submits a mandatory 'Reason for Modification' audit comment, and the
// system versions the change and updates the live application's
// behavior. Every business rule was previously hardcoded in PHP
// (private consts scattered across services); this is the live,
// admin-editable registry those consts now read through.
//
// Honestly scoped: only the rules in SEED below are actually wired
// into a real code path (getNumeric() is called at the exact spot the
// old hardcoded const used to be). A Super Admin can also record a
// freeform rule (rule_key = null) purely as a governed, versioned,
// audited policy statement — satisfying BR-01's "rationale record" for
// decisions that don't correspond to a single numeric knob in code —
// but it has no runtime effect. There is no generic rule-expression
// evaluator in this codebase; building one would be a rules-engine
// rewrite, not this pass.
class SovereignRuleService
{
    // rule_key => [title, statement, logic, numeric_value]. numeric_value
    // here is the exact figure the hardcoded const it replaces used to
    // have — the seed row this rule starts life at, not a fabricated one.
    private const SEED = [
        'BR-43.bid_ceiling_multiplier' => [
            'title' => 'Anti-Jacking Bid Ceiling',
            'statement' => 'No single bid may exceed this multiple of the current highest bid, catching fat-finger typos and blocking deliberate price manipulation.',
            'logic' => 'reject bid if amount > current_high_bid * multiplier',
            'numeric_value' => 1.5,
        ],
        'BR-27.emd_percent' => [
            'title' => 'EMD Baseline Percentage',
            'statement' => 'Flat percentage of reserve/expected value a bidder must hold as Earnest Money Deposit before bidding, applied uniformly across Easy Auction, Express Auction, and Buy-Now.',
            'logic' => 'required_emd = reserve_or_expected_value * percent',
            'numeric_value' => 0.10,
        ],
        'BR-49.high_value_threshold' => [
            'title' => 'High-Value Disposal Threshold',
            'statement' => 'A single Rupee threshold, applied uniformly across every tenant and sale format, above which a settlement is auto-flagged for High-Value Disposal Reporting and any payout account changed after settlement additionally requires reviewer sign-off before release.',
            'logic' => 'flag/gate if final_settlement_value > threshold',
            'numeric_value' => 1000000.0,
        ],
        'BR-38.shadow_ban_threshold' => [
            'title' => 'Shadow Ban Star-Rating Threshold',
            'statement' => 'Star rating at or below which a seller is automatically Shadow Banned — excluded from Browse/discovery, but still directly reachable by URL.',
            'logic' => 'shadow_ban if star_rating <= threshold',
            'numeric_value' => 1.5,
        ],
        'BR-38.crawl_back_threshold' => [
            'title' => 'Crawl-Back Star-Rating Threshold',
            'statement' => 'Star rating at or below which a buyer enters the Crawl-Back rehabilitation ladder, restricting them to bidding within their permitted transaction-value bracket.',
            'logic' => 'crawl_back if star_rating <= threshold',
            'numeric_value' => 2.0,
        ],
        'BR-55.enhanced_due_diligence_threshold' => [
            'title' => 'Enhanced Due Diligence Threshold',
            'statement' => 'Transaction-value Rupee threshold above which a User, beyond ordinary KYC verification, is additionally subject to enhanced due diligence before that specific transaction is permitted to proceed. BR-55\'s own text leaves this figure open, to be set by SaaS Admin in consultation with compliance/legal advice referencing RBI/PMLA KYC master directions — not fixed by the governing document itself.',
            'logic' => 'require_edd_clearance if transaction_value > threshold',
            'numeric_value' => 500000.0,
        ],
        'TechStack-3.10.server_time_drift_tolerance_seconds' => [
            'title' => 'Server Time Drift Tolerance',
            'statement' => 'Maximum permitted difference, in seconds, between this server\'s clock and an authoritative NTP time source before a drift alert fires. Tech Stack §3.10 requires "a defined tolerance" without stating a figure — a reasonable default, flagged the same way as other unspecified thresholds in this codebase (e.g. SettlementService::STALL_THRESHOLD_DAYS), not a fixed value from the governing document.',
            'logic' => 'alert if abs(local_time - ntp_time) > tolerance_seconds',
            'numeric_value' => 5.0,
        ],
    ];

    // Per-process cache — the same rule can be read many times within
    // one request/CLI run (e.g. every bid re-checks the ceiling); this
    // avoids a DB round trip on each one without needing a full caching
    // layer this codebase doesn't otherwise have.
    private static array $cache = [];

    // Exposes the original seed definitions read-only — used by
    // test:sovereignrule to restore the platform's default rule values
    // after exercising live edits, since (unlike every other Test*
    // command) it mutates genuinely shared, persistent configuration.
    public static function seedDefinitions(): array
    {
        return self::SEED;
    }

    private SovereignRuleModel $ruleModel;
    private SovereignRuleRevisionModel $revisionModel;

    public function __construct()
    {
        $this->ruleModel = new SovereignRuleModel();
        $this->revisionModel = new SovereignRuleRevisionModel();
    }

    // Called from application code at the exact spot a hardcoded const
    // used to sit. Falls back to $default (the original hardcoded value)
    // if the row hasn't been seeded yet, so behavior never changes until
    // a Super Admin actually opens the Rules module — no migration-order
    // dependency, no risk of a mid-deploy gap.
    public static function getNumeric(string $key, float $default): float
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $row = (new SovereignRuleModel())->where('rule_key', $key)->first();
        $value = $row && $row['numeric_value'] !== null ? (float) $row['numeric_value'] : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    // Ensures every SEED rule exists as a row (idempotent — inserts only
    // what's missing) and returns every rule, seeded ones first in a
    // stable order, then any freeform rules by title.
    public function listAll(): array
    {
        $existingKeys = array_column($this->ruleModel->whereIn('rule_key', array_keys(self::SEED))->findAll(), 'rule_key');
        foreach (self::SEED as $key => $def) {
            if (in_array($key, $existingKeys, true)) {
                continue;
            }
            $this->ruleModel->insert([
                'id' => Uuid::v4(), 'rule_key' => $key,
                'title' => $def['title'], 'statement' => $def['statement'], 'logic' => $def['logic'],
                'numeric_value' => $def['numeric_value'], 'version' => 1, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $seeded = $this->ruleModel->whereIn('rule_key', array_keys(self::SEED))->findAll();
        usort($seeded, fn ($a, $b) => array_search($a['rule_key'], array_keys(self::SEED)) <=> array_search($b['rule_key'], array_keys(self::SEED)));

        $freeform = $this->ruleModel->where('rule_key', null)->orderBy('title', 'ASC')->findAll();

        return array_merge($seeded, $freeform);
    }

    public function find(string $id): ?array
    {
        return $this->ruleModel->find($id);
    }

    public function revisions(string $ruleId): array
    {
        return $this->revisionModel->forRule($ruleId);
    }

    // PR-04 "defines a new rule (Title, Statement, Logic)" — a freeform,
    // non-wired governance rule. Still versioned and audited like any
    // other rule from the moment it's created.
    public function createFreeform(string $title, string $statement, string $logic, string $reason, ?string $changedByPartyId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Reason for Modification is required.');
        }

        $id = Uuid::v4();
        $now = date('Y-m-d H:i:s');
        $this->ruleModel->insert([
            'id' => $id, 'rule_key' => null, 'title' => $title, 'statement' => $statement,
            'logic' => $logic, 'numeric_value' => null, 'version' => 1, 'updated_at' => $now,
        ]);
        $this->recordRevision($id, 1, $title, $statement, $logic, null, $reason, $changedByPartyId);
        $this->logAudit($id, null, 1, $reason, $changedByPartyId);

        return $this->ruleModel->find($id);
    }

    // PR-04 steps 3-5: Admin edits Title/Statement/Logic(/value), submits
    // a mandatory Reason for Modification, system versions + commits it.
    // For a seeded rule_key, this is the one and only place its live
    // numeric value can change — the very next getNumeric() call
    // (subject to the process cache above) reflects it.
    public function update(string $ruleId, string $title, string $statement, string $logic, ?float $numericValue, string $reason, ?string $changedByPartyId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Reason for Modification is required.');
        }

        $rule = $this->ruleModel->find($ruleId);
        if (!$rule) {
            throw new \RuntimeException('Rule not found.');
        }

        $newVersion = (int) $rule['version'] + 1;
        $this->ruleModel->update($ruleId, [
            'title' => $title, 'statement' => $statement, 'logic' => $logic,
            'numeric_value' => $numericValue, 'version' => $newVersion, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordRevision($ruleId, $newVersion, $title, $statement, $logic, $numericValue, $reason, $changedByPartyId);
        $this->logAudit($ruleId, $rule['rule_key'], $newVersion, $reason, $changedByPartyId);

        // Refresh the process cache immediately — without this, a
        // long-running process (a persistent worker, or several updates
        // within one CLI run) would keep serving the stale pre-edit
        // value to getNumeric() until the process happened to restart.
        if ($rule['rule_key'] !== null) {
            self::$cache[$rule['rule_key']] = $numericValue;
        }

        return $this->ruleModel->find($ruleId);
    }

    private function recordRevision(string $ruleId, int $version, string $title, string $statement, string $logic, ?float $numericValue, string $reason, ?string $changedByPartyId): void
    {
        $this->revisionModel->insert([
            'id' => Uuid::v4(), 'rule_id' => $ruleId, 'version' => $version,
            'title' => $title, 'statement' => $statement, 'logic' => $logic,
            'numeric_value' => $numericValue, 'reason_for_modification' => $reason,
            'changed_by_party_id' => $changedByPartyId,
        ]);
    }

    // BR-05: every configuration change is tracked in the append-only,
    // tamper-evident audit trail, same as every other admin action in
    // this codebase — not just PR-04's own revision table.
    private function logAudit(string $ruleId, ?string $ruleKey, int $version, string $reason, ?string $changedByPartyId): void
    {
        (new AuditLogService())->log('sovereign_rule.revised', $changedByPartyId, [
            'rule_id' => $ruleId, 'rule_key' => $ruleKey, 'version' => $version,
            'reason_for_modification' => $reason,
        ]);
    }
}
