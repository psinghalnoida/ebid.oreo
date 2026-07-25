<?php

namespace App\Libraries;

class ClvMatchingService
{
    private const TICKER_MATCH_LIMIT = 12;

    private \App\Models\BuyerPreferenceModel $preferenceModel;

    public function __construct()
    {
        $this->preferenceModel = new \App\Models\BuyerPreferenceModel();
    }

    public function savePreferences(string $partyId, array $categories, array $states, ?float $budgetMin, ?float $budgetMax): array
    {
        return $this->preferenceModel->upsert($partyId, [
            'preferred_categories' => empty($categories) ? null : json_encode($categories),
            'comfort_states' => empty($states) ? null : json_encode($states),
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
        ]);
    }

    public function findMatches(string $partyId, ?int $limit = null): array
    {
        $prefs = $this->preferenceModel->findForParty($partyId);
        if (!$prefs) {
            return [];
        }

        $db = \Config\Database::connect();
        $query = $db->table('sale_event se')
            ->select('se.id as sale_event_id, se.ern, se.sale_format, se.current_price, se.reserve_value, se.expected_value,
                      l.id as listing_id, l.category')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party p', 'p.id = l.seller_party_id')
            ->whereIn('se.status', ['active', 'grace_period'])
            ->where('p.shadow_banned_at_seller', null)
            ->where('l.seller_party_id !=', $partyId);

        if (!empty($prefs['preferred_categories'])) {
            $categories = json_decode($prefs['preferred_categories'], true) ?: [];
            if (!empty($categories)) {
                $query->whereIn('l.category', $categories);
            }
        }

        if ($prefs['budget_min'] !== null || $prefs['budget_max'] !== null) {
            $query->groupStart();
            if ($prefs['budget_min'] !== null) {
                $query->groupStart()
                    ->where('se.current_price >=', $prefs['budget_min'])
                    ->orWhere('se.reserve_value >=', $prefs['budget_min'])
                    ->orWhere('se.expected_value >=', $prefs['budget_min'])
                    ->groupEnd();
            }
            if ($prefs['budget_max'] !== null) {
                $query->groupStart()
                    ->where('se.current_price <=', $prefs['budget_max'])
                    ->orWhere('se.reserve_value <=', $prefs['budget_max'])
                    ->orWhere('se.expected_value <=', $prefs['budget_max'])
                    ->groupEnd();
            }
            $query->groupEnd();
        }

        return $query->orderBy('se.created_at', 'DESC')
            ->limit($limit ?? self::TICKER_MATCH_LIMIT)
            ->get()->getResultArray();
    }
}
