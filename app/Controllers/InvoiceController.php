<?php

namespace App\Controllers;

use App\Libraries\InvoiceService;
use App\Libraries\Paginator;

// BR-56: seller/buyer-facing invoice history, closing the gap where
// invoices were only ever reachable one at a time from a specific
// settlement's page (SettlementController::show) with no aggregate
// view across all of a party's transactions.
class InvoiceController extends BaseController
{
    private InvoiceService $invoices;

    public function __construct()
    {
        $this->invoices = new InvoiceService();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function index()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $pg = Paginator::fromRequest($this->request);
        $total = $this->invoices->countForParty($partyId);
        $invoices = $this->invoices->findForParty($partyId, $pg['perPage'], $pg['offset']);

        return view('account/invoices', [
            'title' => 'Invoices — AdwitiX', 'invoices' => $invoices,
            'page' => $pg['page'], 'perPage' => $pg['perPage'],
            'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    public function pdf(string $invoiceId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $invoice = $this->invoices->findIfAuthorized($invoiceId, $partyId);
        if (!$invoice) {
            return service('response')->setStatusCode(403)->setBody('You are not a party to the settlement this invoice was generated for.');
        }

        $html = view('account/invoice_pdf', ['invoice' => $invoice]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $invoice['invoice_number'] . '.pdf"')
            ->setBody($dompdf->output());
    }
}
