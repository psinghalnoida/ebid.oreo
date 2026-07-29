<?php

namespace App\Models;

use CodeIgniter\Model;

class SovereignRuleRevisionModel extends Model
{
    protected $table            = 'sovereign_rule_revision';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'rule_id', 'version', 'title', 'statement', 'logic',
        'numeric_value', 'reason_for_modification', 'changed_by_party_id',
    ];

    public function forRule(string $ruleId): array
    {
        return $this->where('rule_id', $ruleId)->orderBy('version', 'DESC')->findAll();
    }
}
