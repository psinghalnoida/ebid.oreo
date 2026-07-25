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
            'title' => 'Audit Log — eBid Hub', 'entries' => $entries,
            'eventType' => $eventType, 'actorMobile' => $actorMobile,
        ]);
    }

    public function verifyIntegrity()
    {
        $audit = new AuditLogService();
        $brokenAt = $audit->verifyChainIntegrity();

        return view('admin/audit_verify', [
            'title' => 'Audit Log Integrity — eBid Hub', 'brokenAt' => $brokenAt,
        ]);
    }
}
