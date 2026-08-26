<?php

namespace App\Controllers;

use App\Libraries\KycService;
use App\Libraries\UserAuthContext;
use App\Models\PartyDocumentModel;
use App\Models\PartyAddressModel;

// BR-17/BR-18/PR-15: patron-facing KYC onboarding — questionnaire,
// document vault, multi-address portfolio, banking, and submission for
// review. All routes run behind the jwtAuth filter.
class KycController extends BaseController
{
    private KycService $kyc;

    public function __construct()
    {
        $this->kyc = new KycService();
    }

    public function form()
    {
        $partyId = UserAuthContext::partyId();
        $party = (new \App\Models\PartyModel())->find($partyId);
        $documents = (new PartyDocumentModel())->forParty($partyId);
        $addresses = (new PartyAddressModel())->forParty($partyId);
        $addressesByType = [];
        foreach ($addresses as $a) {
            $addressesByType[$a['address_type']] = $a;
        }

        return $this->response->setJSON([
            'party' => $party, 'documents' => $documents, 'addressesByType' => $addressesByType,
            'requiredDocuments' => KycService::requiredDocuments($party['entity_type']),
            'allDocumentTypes' => KycService::allDocumentTypes(),
        ]);
    }

    public function saveQuestionnaire()
    {
        $partyId = UserAuthContext::partyId();
        try {
            $this->kyc->saveQuestionnaire($partyId, (string) $this->input('entity_type'), $this->request->getJSON(true) ?? $this->request->getPost());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'questionnaire_failed', $e->getMessage());
        }
        return $this->response->setJSON(['message' => 'Questionnaire saved.']);
    }

    public function uploadDocument()
    {
        $partyId = UserAuthContext::partyId();
        $file = $this->request->getFile('document');
        $documentType = (string) $this->request->getPost('document_type');
        if (!$file) {
            return $this->jsonError(422, 'no_file', 'No file was uploaded.');
        }
        try {
            $document = $this->kyc->uploadDocument($partyId, $documentType, $file);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'upload_failed', $e->getMessage());
        }
        return $this->response->setStatusCode(201)->setJSON(['document' => $document]);
    }

    public function saveAddress()
    {
        $partyId = UserAuthContext::partyId();
        try {
            $address = $this->kyc->registerAddress($partyId, (string) $this->input('address_type'), $this->request->getJSON(true) ?? $this->request->getPost());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'address_failed', $e->getMessage());
        }
        return $this->response->setJSON(['address' => $address]);
    }

    public function saveBanking()
    {
        $partyId = UserAuthContext::partyId();
        try {
            $this->kyc->registerBanking($partyId, $this->request->getJSON(true) ?? $this->request->getPost());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'banking_failed', $e->getMessage());
        }
        return $this->response->setJSON(['message' => 'Banking details saved.']);
    }

    public function submit()
    {
        $partyId = UserAuthContext::partyId();
        try {
            $this->kyc->submitForReview($partyId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'submit_failed', $e->getMessage());
        }
        return $this->response->setJSON(['message' => 'Submitted for review.']);
    }
}
