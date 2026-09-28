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

        return $this->apiResponse([
            'existing' => $existing,
            'allCategories' => array_column($allCategories, 'category'),
            'selectedCategories' => $existing && $existing['preferred_categories'] ? json_decode($existing['preferred_categories'], true) : [],
        ]);
    }

    public function submit()
    {
        $partyId = UserAuthContext::partyId();

        $categories = $this->input('categories') ?: [];
        if (!is_array($categories)) {
            return $this->jsonError(422, 'invalid_categories', 'categories must be a list, e.g. ["Vehicles"].');
        }
        $categories = array_values(array_filter(array_map(fn ($c) => trim((string) $c), $categories), fn ($c) => $c !== ''));

        $statesText = trim((string) $this->input('states_text'));
        $states = $statesText !== '' ? array_values(array_filter(array_map('trim', explode(',', $statesText)), fn ($s) => $s !== '')) : [];

        $budgetMinRaw = $this->input('budget_min');
        $budgetMaxRaw = $this->input('budget_max');
        foreach (['budget_min' => $budgetMinRaw, 'budget_max' => $budgetMaxRaw] as $field => $raw) {
            if ($raw !== null && $raw !== '' && (!is_numeric($raw) || (float) $raw < 0)) {
                return $this->jsonError(422, 'invalid_budget', "{$field} must be a number of 0 or more.");
            }
        }
        $budgetMin = $budgetMinRaw !== null && $budgetMinRaw !== '' ? (float) $budgetMinRaw : null;
        $budgetMax = $budgetMaxRaw !== null && $budgetMaxRaw !== '' ? (float) $budgetMaxRaw : null;
        if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
            return $this->jsonError(422, 'invalid_budget', 'budget_min cannot be greater than budget_max.');
        }

        if (empty($categories) && empty($states) && $budgetMin === null && $budgetMax === null) {
            return $this->jsonError(422, 'no_preferences', 'Provide at least one of categories, states_text, budget_min or budget_max.');
        }

        (new ClvMatchingService())->savePreferences($partyId, $categories, $states, $budgetMin, $budgetMax);

        return $this->apiResponse(['message' => 'Preferences saved.']);
    }
}
