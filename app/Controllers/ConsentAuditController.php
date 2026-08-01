<?php

namespace App\Controllers;

// BR-51: consent capture (D-56) has been recording real, discrete
// consent events since it was built — this is the missing viewing/
// export layer, same relationship BR-58 has to BR-05's audit trail
// (D-58: "a reporting/export capability layered on existing data, not
// a separate data-capture requirement"). Mirrors AuditLogController's
// established filter/export pattern rather than inventing a new one.
class ConsentAuditController extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $consentType = $this->request->getGet('consent_type');
        $mobile = $this->request->getGet('mobile');

        $query = $db->table('consent_event ce')
            ->select('ce.id, ce.consent_type, ce.terms_version, ce.related_reference_id, ce.consent_text_shown, ce.ip_address, ce.created_at, p.mobile_number')
            ->join('party p', 'p.id = ce.party_id')
            ->orderBy('ce.created_at', 'DESC')
            ->limit(200);

        if ($consentType) {
            $query->where('ce.consent_type', $consentType);
        }
        if ($mobile) {
            $query->where('p.mobile_number', $mobile);
        }

        return view('admin/consent_audit', [
            'title' => 'Consent Audit — AdwitiX',
            'entries' => $query->get()->getResultArray(),
            'consentType' => $consentType, 'mobile' => $mobile,
        ]);
    }

    public function exportForm()
    {
        return view('admin/consent_audit_export', ['title' => 'Consent Audit Export — AdwitiX']);
    }

    public function export()
    {
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        if (!$from || !$to) {
            return redirect()->to('/admin/consent-audit/export')->with('error', 'Both a start and end date are required to export.');
        }

        $db = \Config\Database::connect();
        $entries = $db->table('consent_event ce')
            ->select('ce.id, ce.consent_type, ce.terms_version, ce.related_reference_id, ce.consent_text_shown, ce.ip_address, ce.created_at, p.mobile_number')
            ->join('party p', 'p.id = ce.party_id')
            ->where('ce.created_at >=', $from . ' 00:00:00')
            ->where('ce.created_at <=', $to . ' 23:59:59')
            ->orderBy('ce.created_at', 'ASC')
            ->get()->getResultArray();

        (new \App\Libraries\AuditLogService())->log('consent_audit.export_generated', session()->get('super_admin_party_id'), [
            'from' => $from, 'to' => $to, 'recordCount' => count($entries),
        ]);

        $filename = "ebidhub-consent-audit-{$from}-to-{$to}.csv";
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");

        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['ID', 'Consent Type', 'Terms Version', 'Related Reference', 'Consent Text Shown', 'IP Address', 'Mobile', 'Recorded At']);
        foreach ($entries as $e) {
            fputcsv($out, [
                $e['id'], $e['consent_type'], $e['terms_version'], $e['related_reference_id'] ?? '',
                $e['consent_text_shown'], $e['ip_address'] ?? '', $e['mobile_number'], $e['created_at'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->setBody($csv);
    }
}
