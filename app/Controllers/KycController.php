<?php

namespace App\Controllers;

use App\Libraries\KycService;
use App\Models\PartyDocumentModel;
use App\Models\PartyAddressModel;

// BR-17/BR-18/PR-15: patron-facing KYC onboarding — questionnaire,
// document vault, multi-address portfolio, banking, and submission for
// review.
class KycController extends BaseController
{
    private KycService $kyc;

    public function __construct()
    {
        $this->kyc = new KycService();
    }

    public function form()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        $party = (new \App\Models\PartyModel())->find($partyId);
        $documents = (new PartyDocumentModel())->forParty($partyId);
        $addresses = (new PartyAddressModel())->forParty($partyId);
        $addressesByType = [];
        foreach ($addresses as $a) {
            $addressesByType[$a['address_type']] = $a;
        }

        return view('kyc/form', [
            'title' => 'KYC Verification — AdwitiX', 'party' => $party,
            'documents' => $documents, 'addressesByType' => $addressesByType,
            'requiredDocuments' => KycService::requiredDocuments($party['entity_type']),
            'allDocumentTypes' => KycService::allDocumentTypes(),
        ]);
    }

    public function saveQuestionnaire()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        try {
            $this->kyc->saveQuestionnaire($partyId, (string) $this->request->getPost('entity_type'), $this->request->getPost());
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }
        return redirect()->to('/kyc')->with('error', 'Questionnaire saved.');
    }

    public function uploadDocument()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        $file = $this->request->getFile('document');
        $documentType = (string) $this->request->getPost('document_type');
        if (!$file) {
            return redirect()->to('/kyc')->with('error', 'No file was uploaded.');
        }
        try {
            $this->kyc->uploadDocument($partyId, $documentType, $file);
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }
        return redirect()->to('/kyc')->with('error', 'Document uploaded.');
    }

    public function saveAddress()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        try {
            $this->kyc->registerAddress($partyId, (string) $this->request->getPost('address_type'), $this->request->getPost());
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }
        return redirect()->to('/kyc')->with('error', 'Address saved.');
    }

    public function saveBanking()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        try {
            $this->kyc->registerBanking($partyId, $this->request->getPost());
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }
        return redirect()->to('/kyc')->with('error', 'Banking details saved.');
    }

    public function submit()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }
        try {
            $this->kyc->submitForReview($partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }
        return redirect()->to('/kyc')->with('error', 'Submitted for review.');
    }
}
