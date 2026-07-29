<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyDocumentModel extends Model
{
    protected $table            = 'party_document';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'party_id', 'document_type', 'encrypted_path', 'original_filename', 'mime_type',
    ];

    public function forParty(string $partyId): array
    {
        return $this->where('party_id', $partyId)->orderBy('uploaded_at', 'ASC')->findAll();
    }

    public function typesUploadedBy(string $partyId): array
    {
        return array_column($this->forParty($partyId), 'document_type');
    }
}
