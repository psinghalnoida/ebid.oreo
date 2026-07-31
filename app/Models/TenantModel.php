<?php

namespace App\Models;

use CodeIgniter\Model;

class TenantModel extends Model
{
    protected $table            = 'tenant';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    // BR-08/09 (D-87/D-88): subscription_tier replaces buyer_fee_percent
    // — the Success Fee is now a fixed, platform-wide schedule
    // (EmdService::calculateSuccessFee) the Tenant Admin cannot set. The
    // tier instead drives Section 5's subscription model and gates
    // Seller-Pays eligibility (TenantBillingService/SaleEventController).
    public const SUBSCRIPTION_TIERS = ['coco_starter', 'tsx_launch', 'tsx_growth', 'tsx_enterprise'];

    protected $allowedFields = [
        'id', 'name', 'tenant_class', 'subdomain', 'custom_domain',
        'subscription_tier', 'branding_logo_url', 'branding_primary_color',
        'terms_url', 'suspended_at', 'updated_at', 'low_bracket_max', 'medium_bracket_max',
    ];

    public function createTenant(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }
}
