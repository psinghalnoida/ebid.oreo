<?php

namespace App\Models;

use CodeIgniter\Model;

class SuperAdminBackupCodeModel extends Model
{
    protected $table            = 'super_admin_backup_code';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'code_hash', 'used_at'];

    // Regenerating (enrollment or re-enrollment) invalidates every
    // prior code — old codes were trust-bound to the old device context.
    public function regenerateFor(string $partyId, int $count = 10): array
    {
        $this->where('party_id', $partyId)->delete();

        $plainCodes = [];
        foreach (range(1, $count) as $i) {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars, e.g. "A1B2C3D4"
            $plainCodes[] = $code;
            $this->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $partyId, 'code_hash' => password_hash($code, PASSWORD_BCRYPT)]);
        }
        return $plainCodes;
    }

    // Returns true and marks the code consumed if it matches an unused
    // one for this party — false otherwise. Never reveals which stored
    // hash matched.
    public function consumeIfValid(string $partyId, string $submittedCode): bool
    {
        $unused = $this->where('party_id', $partyId)->where('used_at', null)->findAll();
        foreach ($unused as $row) {
            if (password_verify($submittedCode, $row['code_hash'])) {
                $this->update($row['id'], ['used_at' => date('Y-m-d H:i:s')]);
                return true;
            }
        }
        return false;
    }
}
