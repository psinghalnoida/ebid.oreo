<?php

namespace App\Models;

use CodeIgniter\Model;

class SovereignRuleModel extends Model
{
    protected $table            = 'sovereign_rule';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'rule_key', 'title', 'statement', 'logic', 'numeric_value', 'version', 'updated_at',
    ];
}
