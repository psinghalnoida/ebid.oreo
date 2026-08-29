<?php

namespace App\Models;

use CodeIgniter\Model;

// Custodian (Super Admin) email+password credential — deliberately
// separate from PartyModel; see CreateSuperAdminCredential migration
// for why. One row per Custodian party.
class SuperAdminCredentialModel extends Model
{
    protected $table            = 'super_admin_credential';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'email', 'password_hash', 'updated_at'];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    public function findByPartyId(string $partyId): ?array
    {
        return $this->where('party_id', $partyId)->first();
    }

    // Creates the credential row on first setup, or resets the
    // email/password on an existing one — mirrors AuthService::setMpin's
    // "safe to re-run" behavior (used by bootstrap:custodian and the
    // forgot-password flow alike).
    public function setCredential(string $partyId, string $email, string $passwordHash): array
    {
        $email = strtolower(trim($email));
        $existing = $this->findByPartyId($partyId);
        if ($existing) {
            $this->update($existing['id'], [
                'email' => $email,
                'password_hash' => $passwordHash,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return $this->find($existing['id']);
        }

        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id,
            'party_id' => $partyId,
            'email' => $email,
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($id);
    }

    public function setPasswordHash(string $partyId, string $passwordHash): void
    {
        $existing = $this->findByPartyId($partyId);
        if (!$existing) {
            throw new \RuntimeException('No email/password credential exists for this Custodian yet.');
        }
        $this->update($existing['id'], ['password_hash' => $passwordHash, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
