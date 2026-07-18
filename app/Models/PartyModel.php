<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyModel extends Model
{
    protected $table            = 'party';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // archived_at handled manually — BR-05 logical archiving
    protected $useTimestamps    = false; // DB defaults + explicit now() on writes, matching prior design

    protected $allowedFields = [
        'id', 'mobile_number', 'mobile_verified_at', 'entity_type', 'mpin_hash', 'failed_mpin_attempts',
        'full_name', 'pan', 'aadhaar_masked', 'date_of_birth', 'occupation',
        'kyc_status', 'kyc_status_reason', 'star_rating', 'seller_star_rating',
        'offence_count_buyer', 'offence_count_seller',
        'crawl_back_active_buyer', 'crawl_back_clean_required_buyer', 'crawl_back_clean_completed_buyer',
        'crawl_back_active_seller', 'crawl_back_clean_required_seller', 'crawl_back_clean_completed_seller',
        'shadow_banned_at_buyer', 'shadow_banned_at_seller', 'deposit_override_amount',
        'forced_neutral_count_buyer', 'forced_neutral_count_seller',
        'updated_at',
    ];

    // BR-02: mobile number is the unique identity key
    public function findByMobile(string $mobileNumber): ?array
    {
        return $this->where('mobile_number', $mobileNumber)
            ->where('archived_at', null)
            ->first();
    }

    public function findActiveById(string $id): ?array
    {
        return $this->where('id', $id)
            ->where('archived_at', null)
            ->first();
    }

    // BR-02: registration creates the party record before mPIN is set
    public function createParty(string $mobileNumber, string $entityType = 'individual'): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'mobile_number' => $mobileNumber, 'entity_type' => $entityType]);
        return $this->findActiveById($id);
    }

    public function setMpinHash(string $partyId, string $mpinHash): void
    {
        $this->update($partyId, ['mpin_hash' => $mpinHash, 'updated_at' => $this->now()]);
    }

    // BR-02: 3 consecutive failed mPIN attempts triggers a failover OTP
    public function incrementFailedMpinAttempts(string $partyId): int
    {
        $this->db->table('party')
            ->where('id', $partyId)
            ->set('failed_mpin_attempts', 'failed_mpin_attempts + 1', false)
            ->set('updated_at', $this->now())
            ->update();
        return (int) $this->find($partyId)['failed_mpin_attempts'];
    }

    public function resetFailedMpinAttempts(string $partyId): void
    {
        $this->update($partyId, ['failed_mpin_attempts' => 0, 'updated_at' => $this->now()]);
    }

    // BR-17: suspension requires a mandatory, logged reason from a closed list
    public function setKycStatus(string $partyId, string $status, ?string $reason = null): void
    {
        $this->update($partyId, ['kyc_status' => $status, 'kyc_status_reason' => $reason, 'updated_at' => $this->now()]);
    }

    // ── Rating state (BR-35, BR-38, BR-39) ──────────────────────────

    public function setRating(string $partyId, string $ratingRole, float $newValue): void
    {
        $column = $ratingRole === 'star_rating' ? 'star_rating' : 'seller_star_rating';
        $this->update($partyId, [$column => $newValue, 'updated_at' => $this->now()]);
    }

    public function incrementOffenceCount(string $partyId, string $role): int
    {
        $column = $role === 'buyer' ? 'offence_count_buyer' : 'offence_count_seller';
        $this->db->table('party')->where('id', $partyId)
            ->set($column, "$column + 1", false)
            ->set('updated_at', $this->now())
            ->update();
        return (int) $this->find($partyId)[$column];
    }

    public function activateCrawlBack(string $partyId, string $role, int $cleanRequired): void
    {
        $prefix = $role === 'buyer' ? 'buyer' : 'seller';
        $this->update($partyId, [
            "crawl_back_active_{$prefix}" => true,
            "crawl_back_clean_required_{$prefix}" => $cleanRequired,
            "crawl_back_clean_completed_{$prefix}" => 0,
            'updated_at' => $this->now(),
        ]);
    }

    public function recordCleanTransaction(string $partyId, string $role): array
    {
        $prefix = $role === 'buyer' ? 'buyer' : 'seller';
        $column = "crawl_back_clean_completed_{$prefix}";
        $this->db->table('party')->where('id', $partyId)
            ->set($column, "$column + 1", false)
            ->set('updated_at', $this->now())
            ->update();
        return $this->find($partyId);
    }

    public function deactivateCrawlBack(string $partyId, string $role): void
    {
        $prefix = $role === 'buyer' ? 'buyer' : 'seller';
        $this->update($partyId, ["crawl_back_active_{$prefix}" => false, 'updated_at' => $this->now()]);
    }

    public function setShadowBanned(string $partyId, string $role, bool $banned): void
    {
        $prefix = $role === 'buyer' ? 'buyer' : 'seller';
        $this->update($partyId, [
            "shadow_banned_at_{$prefix}" => $banned ? $this->now() : null,
            'updated_at' => $this->now(),
        ]);
    }

    public function incrementForcedNeutralCount(string $partyId, string $ratingRole): int
    {
        $column = $ratingRole === 'star_rating' ? 'forced_neutral_count_buyer' : 'forced_neutral_count_seller';
        $this->db->table('party')->where('id', $partyId)
            ->set($column, "$column + 1", false)
            ->set('updated_at', $this->now())
            ->update();
        return (int) $this->find($partyId)[$column];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
