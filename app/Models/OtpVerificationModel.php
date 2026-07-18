<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpVerificationModel extends Model
{
    protected $table            = 'otp_verification';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'mobile_number', 'otp_hash', 'purpose', 'attempts', 'expires_at', 'verified_at'];

    public function createOtp(string $id, string $mobileNumber, string $otpHash, string $purpose, string $expiresAt): array
    {
        $this->insert([
            'id' => $id, 'mobile_number' => $mobileNumber, 'otp_hash' => $otpHash,
            'purpose' => $purpose, 'expires_at' => $expiresAt,
        ]);
        return $this->find($id);
    }

    // Most recent, unverified, unexpired OTP for this mobile+purpose
    public function findActive(string $mobileNumber, string $purpose): ?array
    {
        return $this->where('mobile_number', $mobileNumber)
            ->where('purpose', $purpose)
            ->where('verified_at', null)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function incrementAttempts(string $id): int
    {
        $this->db->table('otp_verification')->where('id', $id)
            ->set('attempts', 'attempts + 1', false)->update();
        return (int) $this->find($id)['attempts'];
    }

    public function markVerified(string $id): array
    {
        $this->update($id, ['verified_at' => date('Y-m-d H:i:s')]);
        return $this->find($id);
    }
}
