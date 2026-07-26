<?php

namespace App\Controllers;

use App\Libraries\StandingReviewService;
use App\Models\DisputeModel;

class StandingReviewController extends BaseController
{
    public function show(string $disputeId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $dispute = (new DisputeModel())->find($disputeId);
        if (!$dispute || $dispute['category'] !== 'standing_review') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();
        $seller = $db->table('party')->where('id', $dispute['respondent_party_id'])->get()->getRowArray();
        $tenants = $db->table('seller_application ta')
            ->select('t.id, t.name')
            ->join('tenant t', 't.id = ta.tenant_id')
            ->where('ta.party_id', $dispute['respondent_party_id'])
            ->where('ta.status', 'approved')
            ->get()->getResultArray();

        return view('admin/standing_review_case', [
            'title' => 'Standing Review Case — eBid Hub', 'dispute' => $dispute,
            'seller' => $seller, 'tenants' => $tenants,
        ]);
    }

    public function rule(string $disputeId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $tenantId = $this->request->getPost('tenant_id');
        $outcome = $this->request->getPost('outcome');
        $rationale = $this->request->getPost('rationale');
        $ratingConsequence = $this->request->getPost('rating_consequence') !== '' ? (float) $this->request->getPost('rating_consequence') : null;

        try {
            (new StandingReviewService())->ruleOnCase($disputeId, $partyId, $tenantId, $outcome, $rationale, $ratingConsequence);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to("/admin/standing-review/{$disputeId}")->with('error', 'Standing Review case ruled.');
    }
}
