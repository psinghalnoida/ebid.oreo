<?php

namespace App\Controllers;

use App\Libraries\ChronicleService;
use App\Libraries\AuthorizationService;

// Section 7.10 (ADWITIX_Master.docx): the Trading Session Chronicle.
// Two distinct access paths, deliberately different (Section 7.8):
// the Seller/Tenant Admin download below is session-authenticated like
// everything else in this controller group; the QR verification path
// (verify()/verifyPdf()) is token-only by design, reachable by anyone
// holding the exact unguessable token -- that's what makes a QR code
// scanned by an outside party actually work.
class ChronicleController extends BaseController
{
    private ChronicleService $chronicles;

    public function __construct()
    {
        $this->chronicles = new ChronicleService();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    private function authorizedChronicle(string $chronicleId, string $partyId): ?array
    {
        $chronicle = $this->chronicles->findIfAuthorized($chronicleId, $partyId);
        if ($chronicle) {
            return $chronicle;
        }

        $db = \Config\Database::connect();
        $bare = $db->table('trading_session_chronicle')->where('id', $chronicleId)->get()->getRowArray();
        if ($bare && (new AuthorizationService())->isTenantAdminForSettlement($partyId, $bare['settlement_id'])) {
            return $bare;
        }

        return null;
    }

    public function download(string $chronicleId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $chronicle = $this->authorizedChronicle($chronicleId, $partyId);
        if (!$chronicle) {
            return service('response')->setStatusCode(403)->setBody('You are not authorized to view this Chronicle.');
        }

        return $this->renderPdf($chronicle);
    }

    public function verify(string $token)
    {
        $chronicle = $this->chronicles->getByToken($token);
        if (!$chronicle) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $reportData = json_decode($chronicle['report_data'], true);
        $db = \Config\Database::connect();
        $media = $db->table('listing_media')
            ->join('sale_event', 'sale_event.listing_id = listing_media.listing_id')
            ->where('sale_event.id', $chronicle['sale_event_id'])
            ->select('listing_media.file_path, listing_media.original_filename, listing_media.is_primary')
            ->get()->getResultArray();

        // Digital verification: re-hash the report_data exactly as
        // stored and compare against the content_hash column recorded
        // at generation. A mismatch means the row was altered outside
        // the normal generate() path -- this is the check a QR scan is
        // actually for.
        $recomputedHash = hash('sha256', $chronicle['report_data']);

        return view('chronicle/verify', [
            'title' => 'Chronicle Verification — ' . $chronicle['reference_number'],
            'chronicle' => $chronicle, 'reportData' => $reportData, 'media' => $media,
            'hashMatches' => $recomputedHash === $chronicle['content_hash'],
        ]);
    }

    public function verifyPdf(string $token)
    {
        $chronicle = $this->chronicles->getByToken($token);
        if (!$chronicle) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->renderPdf($chronicle);
    }

    private function renderPdf(array $chronicle)
    {
        $reportData = json_decode($chronicle['report_data'], true);
        $verifyUrl = base_url('chronicle/verify/' . $chronicle['verification_token']);

        // The QR is the sole entry point a scan gets -- it resolves to
        // the verify page, which is where every piece of Lot evidence
        // (photographs, documents) is actually linked, not just named.
        $qrResult = (new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $verifyUrl, size: 220, margin: 8,
        ))->build();

        $html = view('chronicle/pdf', [
            'chronicle' => $chronicle, 'reportData' => $reportData,
            'verifyUrl' => $verifyUrl, 'qrDataUri' => $qrResult->getDataUri(),
            'logoDataUri' => $this->brandLogoDataUri(),
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $chronicle['reference_number'] . '.pdf"')
            ->setBody($dompdf->output());
    }

    // dompdf renders from a detached HTML string, not a live request --
    // a plain <img src="/images/..."> won't resolve, so the logo has to
    // travel with the document as a data URI, same as the QR code.
    private function brandLogoDataUri(): string
    {
        $path = FCPATH . 'images/brand/adwitix-logo-full.jpg';
        return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path));
    }
}
