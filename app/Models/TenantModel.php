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

    protected $allowedFields = [
        'id', 'name', 'tenant_class', 'subdomain', 'custom_domain',
        'buyer_fee_percent', 'branding_logo_url', 'branding_primary_color',
        'terms_url', 'suspended_at', 'updated_at',
    ];

    public function createTenant(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }
}
