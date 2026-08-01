<?php

namespace App\Controllers;

use App\Libraries\AuditLogService;

class AuditLogController extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $eventType = $this->request->getGet('event_type');
        $actorMobile = $this->request->getGet('actor_mobile');

        $query = $db->table('audit_log al')
            ->select('al.id, al.occurred_at, al.event_type, al.actor_party_id, al.ip_address, al.payload, al.record_hash, al.sequence_number, p.mobile_number')
            ->join('party p', 'p.id = al.actor_party_id', 'left')
            ->orderBy('al.sequence_number', 'DESC')
            ->limit(100);

        if ($eventType) {
            $query->where('al.event_type', $eventType);
        }
        if ($actorMobile) {
            $query->where('p.mobile_number', $actorMobile);
        }

        $entries = $query->get()->getResultArray();

        return view('admin/audit_log', [
            'title' => 'Audit Log — AdwitiX', 'entries' => $entries,
            'eventType' => $eventType, 'actorMobile' => $actorMobile,
        ]);
    }

    public function verifyIntegrity()
    {
        $audit = new AuditLogService();
        $brokenAt = $audit->verifyChainIntegrity();

        return view('admin/audit_verify', [
            'title' => 'Audit Log Integrity — AdwitiX', 'brokenAt' => $brokenAt,
        ]);
    }

    // BR-58: "a reporting/export capability layered on the existing
    // audit trail, not a separate data-capture requirement" — the hash
    // chain itself (D-45) already exists; this is purely the extraction
    // layer for the finance function. Scoped honestly to the hot tier
    // only — cold-tier archival remains blocked on real Google Cloud
    // credentials (flagged since D-45, unchanged here).
    public function exportForm()
    {
        return view('admin/audit_export', ['title' => 'Statutory Export — AdwitiX']);
    }

    public function export()
    {
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        if (!$from || !$to) {
            return redirect()->to('/admin/audit-log/export')->with('error', 'Both a start and end date are required.');
        }

        $db = \Config\Database::connect();
        $entries = $db->table('audit_log al')
            ->select('al.sequence_number, al.occurred_at, al.event_type, al.actor_party_id, p.mobile_number,
                      al.ip_address, al.payload, al.previous_hash, al.record_hash')
            ->join('party p', 'p.id = al.actor_party_id', 'left')
            ->where('al.occurred_at >=', $from . ' 00:00:00')
            ->where('al.occurred_at <=', $to . ' 23:59:59')
            ->orderBy('al.sequence_number', 'ASC')
            ->get()->getResultArray();

        (new AuditLogService())->log('audit_log.statutory_export_generated', session()->get('logged_in_party_id'), [
            'from' => $from, 'to' => $to, 'recordCount' => count($entries),
        ]);

        $filename = "ebidhub-audit-export-{$from}-to-{$to}.csv";
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");

        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['Sequence Number', 'Occurred At (UTC)', 'Event Type', 'Actor Mobile', 'Actor Party ID', 'IP Address', 'Payload', 'Previous Hash', 'Record Hash']);
        foreach ($entries as $e) {
            fputcsv($out, [
                $e['sequence_number'], $e['occurred_at'], $e['event_type'],
                $e['mobile_number'] ?? '(system)', $e['actor_party_id'] ?? '',
                $e['ip_address'] ?? '', $e['payload'], $e['previous_hash'], $e['record_hash'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->setBody($csv);
    }
}
