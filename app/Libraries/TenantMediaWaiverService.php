<?php

namespace App\Libraries;

use App\Models\TenantMediaWaiverModel;

class TenantMediaWaiverService
{
    private TenantMediaWaiverModel $waiverModel;

    public function __construct()
    {
        $this->waiverModel = new TenantMediaWaiverModel();
    }

    public function requestWaiver(string $tenantId, string $tenantAdminId, string $category, string $justification): array
    {
        $existing = $this->waiverModel->where('tenant_id', $tenantId)
            ->where('category', $category)->whereIn('status', ['pending', 'approved'])->first();
        if ($existing) {
            throw new \RuntimeException('BR-60: this tenant already has a pending or active waiver for this category.');
        }

        $id = Uuid::v4();
        $this->waiverModel->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'category' => $category,
            'business_justification' => $justification, 'status' => 'pending',
            'requested_by_party_id' => $tenantAdminId,
        ]);

        (new AuditLogService())->log('tenant_media_waiver.requested', $tenantAdminId, [
            'waiverId' => $id, 'tenantId' => $tenantId, 'category' => $category,
        ]);

        return $this->waiverModel->find($id);
    }

    public function decide(string $waiverId, string $superAdminId, bool $approve, string $rationale): array
    {
        $waiver = $this->requireWaiver($waiverId);
        if ($waiver['status'] !== 'pending') {
            throw new \RuntimeException('This waiver has already been decided.');
        }

        $update = [
            'status' => $approve ? 'approved' : 'declined',
            'decided_by_party_id' => $superAdminId, 'decision_rationale' => $rationale,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($approve) {
            $update['granted_at'] = date('Y-m-d H:i:s');
            $update['expires_at'] = (new \DateTimeImmutable())->modify('+12 months')->format('Y-m-d H:i:s');
        }
        $this->waiverModel->update($waiverId, $update);

        (new AuditLogService())->log($approve ? 'tenant_media_waiver.approved' : 'tenant_media_waiver.declined', $superAdminId, [
            'waiverId' => $waiverId, 'tenantId' => $waiver['tenant_id'], 'category' => $waiver['category'], 'rationale' => $rationale,
        ]);

        return $this->waiverModel->find($waiverId);
    }

    public function revoke(string $waiverId, string $superAdminId, string $reason): array
    {
        $waiver = $this->requireWaiver($waiverId);
        if ($waiver['status'] !== 'approved') {
            throw new \RuntimeException('Only an active, approved waiver can be revoked.');
        }

        $this->waiverModel->update($waiverId, [
            'status' => 'revoked', 'revoked_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogService())->log('tenant_media_waiver.revoked', $superAdminId, [
            'waiverId' => $waiverId, 'tenantId' => $waiver['tenant_id'], 'category' => $waiver['category'], 'reason' => $reason,
        ]);

        return $this->waiverModel->find($waiverId);
    }

    public function lapseExpired(): array
    {
        $expired = $this->waiverModel->where('status', 'approved')
            ->where('expires_at <=', date('Y-m-d H:i:s'))->findAll();

        foreach ($expired as $w) {
            $this->waiverModel->update($w['id'], ['status' => 'lapsed', 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogService())->log('tenant_media_waiver.lapsed', null, [
                'waiverId' => $w['id'], 'tenantId' => $w['tenant_id'], 'category' => $w['category'],
            ]);
        }

        return array_column($expired, 'id');
    }

    public function isCbsProhibitionWaived(string $tenantId, string $category): bool
    {
        return $this->waiverModel->findActiveForTenantCategory($tenantId, $category) !== null;
    }

    private function requireWaiver(string $waiverId): array
    {
        $waiver = $this->waiverModel->find($waiverId);
        if (!$waiver) {
            throw new \RuntimeException('Waiver not found.');
        }
        return $waiver;
    }
}
