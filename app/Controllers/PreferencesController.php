<?php

namespace App\Controllers;

use App\Libraries\ClvMatchingService;
use App\Libraries\UserAuthContext;
use App\Models\BuyerPreferenceModel;

class PreferencesController extends BaseController
{
    public function show()
    {
        $partyId = UserAuthContext::partyId();
        $existing = (new BuyerPreferenceModel())->findForParty($partyId);
        $db = \Config\Database::connect();
        $allCategories = $db->table('listing')->distinct()->select('category')->orderBy('category', 'ASC')->get()->getResultArray();

        return $this->response->setJSON([
            'existing' => $existing,
            'allCategories' => array_column($allCategories, 'category'),
            'selectedCategories' => $existing && $existing['preferred_categories'] ? json_decode($existing['preferred_categories'], true) : [],
        ]);
    }

    public function submit()
    {
        $partyId = UserAuthContext::partyId();

        $categories = $this->input('categories') ?: [];
        $statesText = trim((string) $this->input('states_text'));
        $states = $statesText !== '' ? array_map('trim', explode(',', $statesText)) : [];
        $budgetMin = $this->input('budget_min') !== null && $this->input('budget_min') !== '' ? (float) $this->input('budget_min') : null;
        $budgetMax = $this->input('budget_max') !== null && $this->input('budget_max') !== '' ? (float) $this->input('budget_max') : null;

        (new ClvMatchingService())->savePreferences($partyId, $categories, $states, $budgetMin, $budgetMax);

        return $this->response->setJSON(['message' => 'Preferences saved.']);
    }
}
