<?php

namespace App\Libraries;

use App\Models\PartyModel;

class SuperAdminAuthService
{
    private PartyModel $partyModel;
    private AuthorizationService $authz;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->authz = new AuthorizationService();
    }

    // Only a party already granted the super_admin role (via
    // `php spark grant:super-admin`) can set up TOTP — this doesn't grant
    // the role itself, only enrolls a second factor for someone who
    // already has it.
    public function beginTotpSetup(string $partyId): array
    {
        if (!$this->authz->isSuperAdmin($partyId)) {
            throw new \RuntimeException('Only a party already granted the super_admin role may set up 2FA.');
        }
        $party = $this->partyModel->find($partyId);
        $secret = TotpService::generateSecret();
        $this->partyModel->update($partyId, ['totp_secret' => $secret]);
        $uri = TotpService::getProvisioningUri($secret, $party['mobile_number']);
        return ['secret' => $secret, 'provisioningUri' => $uri];
    }

    public function confirmTotpSetup(string $partyId, string $code): bool
    {
        $party = $this->partyModel->find($partyId);
        if (!$party['totp_secret']) {
            throw new \RuntimeException('No TOTP secret has been generated yet — call beginTotpSetup first.');
        }
        if (!TotpService::verifyCode($party['totp_secret'], $code)) {
            return false;
        }
        $this->partyModel->update($partyId, ['totp_enabled_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    // BR-04: the real separate Super Admin login — mobile + mPIN (same
    // credential mechanism as regular users, per the existing schema) +
    // a genuinely-verified TOTP code, all three required.
    public function login(string $mobileNumber, string $mpin, string $totpCode): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$party['mpin_hash']) {
            throw new \RuntimeException('No registered account with a set mPIN for this mobile number.');
        }
        if (!password_verify($mpin, $party['mpin_hash'])) {
            throw new \RuntimeException('Incorrect mPIN.');
        }
        if (!$this->authz->isSuperAdmin($party['id'])) {
            throw new \RuntimeException('This account does not have Super Admin access.');
        }
        if (!$party['totp_enabled_at'] || !$party['totp_secret']) {
            throw new \RuntimeException('TOTP has not been set up for this account yet.');
        }
        if (!TotpService::verifyCode($party['totp_secret'], $totpCode)) {
            throw new \RuntimeException('Invalid or expired authentication code.');
        }
        return $party;
    }
}
