<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderDocumentModel extends Model
{
    protected $table            = 'tender_document';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'sale_event_id', 'uploaded_by_party_id', 'document_type', 'title', 'file_path', 'description_text'];

    public function createDocument(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->orderBy('created_at', 'ASC')->findAll();
    }

    public function hasTermsOfSale(string $saleEventId): bool
    {
        return $this->where('sale_event_id', $saleEventId)->where('document_type', 'terms_of_sale')->countAllResults() > 0;
    }
}
