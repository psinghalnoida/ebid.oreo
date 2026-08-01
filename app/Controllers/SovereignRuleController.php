<?php

namespace App\Controllers;

use App\Libraries\SovereignRuleService;

// PR-04: the Super Admin's "Rules & Specifications module" — gated
// behind the superAdmin filter, i.e. only reachable after the real
// TOTP-verified Super Admin login (BR-04), same access boundary as
// every other admin surface in this codebase.
class SovereignRuleController extends BaseController
{
    private SovereignRuleService $rules;

    public function __construct()
    {
        $this->rules = new SovereignRuleService();
    }

    public function index()
    {
        return view('admin/rules_list', [
            'title' => 'Rules & Specifications — AdwitiX',
            'rules' => $this->rules->listAll(),
        ]);
    }

    public function editForm(string $ruleId)
    {
        $rule = $this->rules->find($ruleId);
        if (!$rule) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('admin/rules_edit', [
            'title' => 'Edit Rule — AdwitiX',
            'rule' => $rule,
            'revisions' => $this->rules->revisions($ruleId),
        ]);
    }

    public function editSubmit(string $ruleId)
    {
        $numericValue = $this->request->getPost('numeric_value');
        try {
            $this->rules->update(
                $ruleId,
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('statement'),
                (string) $this->request->getPost('logic'),
                $numericValue !== null && $numericValue !== '' ? (float) $numericValue : null,
                (string) $this->request->getPost('reason_for_modification'),
                session()->get('super_admin_party_id')
            );
        } catch (\RuntimeException $e) {
            return redirect()->to("/admin/rules/{$ruleId}")->with('error', $e->getMessage());
        }

        return redirect()->to("/admin/rules/{$ruleId}")->with('error', 'Rule updated and versioned.');
    }

    public function createForm()
    {
        return view('admin/rules_create', ['title' => 'Define a New Rule — AdwitiX']);
    }

    public function createSubmit()
    {
        try {
            $rule = $this->rules->createFreeform(
                (string) $this->request->getPost('title'),
                (string) $this->request->getPost('statement'),
                (string) $this->request->getPost('logic'),
                (string) $this->request->getPost('reason_for_modification'),
                session()->get('super_admin_party_id')
            );
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/rules/new')->with('error', $e->getMessage());
        }

        return redirect()->to("/admin/rules/{$rule['id']}")->with('error', 'Rule created.');
    }
}
