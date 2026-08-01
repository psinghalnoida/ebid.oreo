<?php

namespace App\Controllers;

use App\Libraries\ClvMatchingService;
use App\Models\BuyerPreferenceModel;

class PreferencesController extends BaseController
{
    public function form()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $existing = (new BuyerPreferenceModel())->findForParty($partyId);
        $db = \Config\Database::connect();
        $allCategories = $db->table('listing')->distinct()->select('category')->orderBy('category', 'ASC')->get()->getResultArray();

        return view('preferences/form', [
            'title' => 'My Preferences — AdwitiX',
            'existing' => $existing,
            'allCategories' => array_column($allCategories, 'category'),
            'selectedCategories' => $existing && $existing['preferred_categories'] ? json_decode($existing['preferred_categories'], true) : [],
        ]);
    }

    public function submit()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $categories = $this->request->getPost('categories') ?: [];
        $statesText = trim((string) $this->request->getPost('states_text'));
        $states = $statesText !== '' ? array_map('trim', explode(',', $statesText)) : [];
        $budgetMin = $this->request->getPost('budget_min') !== '' ? (float) $this->request->getPost('budget_min') : null;
        $budgetMax = $this->request->getPost('budget_max') !== '' ? (float) $this->request->getPost('budget_max') : null;

        (new ClvMatchingService())->savePreferences($partyId, $categories, $states, $budgetMin, $budgetMax);

        return redirect()->to('/preferences')->with('error', 'Preferences saved.');
    }
}
