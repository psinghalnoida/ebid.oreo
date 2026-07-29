<?php

namespace App\Controllers;

use App\Libraries\KycService;
use App\Models\PartyModel;
use App\Models\PartyDocumentModel;
use App\Models\PartyAddressModel;

// BR-17/PR-15: Super Admin (SaaS Admin) side of KYC review — gated
// behind the superAdmin filter (real TOTP-verified login, BR-04).
//
// Deliberate, flagged deviation from PR-15's literal text: PR-15 says
// "Tenant Admin reviews the compliance dossier and transitions master
// KYC Status." KYC is party-level data with no owning tenant, though —
// unlike every other resource TenantAdminFilter guards (listing,
// saleEvent, settlement, sellerApplication, all tenant-owned), a Party's
// own identity isn't scoped to one tenant (BR-06: buyers are federated
// globally). There is no coherent answer to "which Tenant Admin" for a
// buyer who hasn't yet transacted with any tenant. Routed to Super Admin
// instead, consistent with how this codebase already handles other
// genuinely platform-wide compliance functions (BR-54 AML review,
// BR-05 audit log, BR-49's cross-tenant high-value reporting).
class KycReviewController extends BaseController
{
    private KycService $kyc;
    private PartyModel $partyModel;

    public function __construct()
    {
        $this->kyc = new KycService();
        $this->partyModel = new PartyModel();
    }

    public function index()
    {
        $submitted = $this->partyModel->where('kyc_status', 'submitted')->orderBy('kyc_submitted_at', 'ASC')->findAll();
        return view('admin/kyc_review_list', ['title' => 'KYC Review Queue — eBid Hub', 'parties' => $submitted]);
    }

    public function detail(string $partyId)
    {
        $party = $this->partyModel->find($partyId);
        if (!$party) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $documents = (new PartyDocumentModel())->forParty($partyId);
        $addresses = (new PartyAddressModel())->forParty($partyId);

        return view('admin/kyc_review_detail', [
            'title' => 'KYC Dossier — eBid Hub', 'party' => $party,
            'documents' => $documents, 'addresses' => $addresses,
            'suspensionReasons' => KycService::suspensionReasons(),
        ]);
    }

    public function verifyFlag(string $partyId)
    {
        $verifierId = session()->get('super_admin_party_id');
        try {
            $this->kyc->verifyComplianceFlag($partyId, (string) $this->request->getPost('flag'), $verifierId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/admin/kyc/{$partyId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/admin/kyc/{$partyId}")->with('error', 'Compliance flag verified.');
    }

    public function decide(string $partyId)
    {
        $reviewerId = session()->get('super_admin_party_id');
        $approve = $this->request->getPost('decision') === 'verify';
        try {
            $this->kyc->reviewDossier($partyId, $reviewerId, $approve, $this->request->getPost('reason'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/admin/kyc/{$partyId}")->with('error', $e->getMessage());
        }
        return redirect()->to('/admin/kyc')->with('error', $approve ? 'KYC verified.' : 'KYC suspended.');
    }

    public function clearEdd(string $partyId)
    {
        $clearedBy = session()->get('super_admin_party_id');
        $this->kyc->clearEnhancedDueDiligence($partyId, $clearedBy);
        return redirect()->to("/admin/kyc/{$partyId}")->with('error', 'Enhanced due diligence cleared for this party.');
    }

    // Documents are never reachable by a guessed URL — decrypted only
    // on-demand for a real, TOTP-verified Super Admin session.
    public function downloadDocument(string $documentId)
    {
        try {
            $decrypted = $this->kyc->decryptDocument($documentId);
        } catch (\RuntimeException $e) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $this->response
            ->setHeader('Content-Type', $decrypted['mimeType'])
            ->setHeader('Content-Disposition', 'inline; filename="' . $decrypted['filename'] . '"')
            ->setBody($decrypted['contents']);
    }
}
