<?php

namespace App\Libraries;

// BR-46: AI Listing Quality Pre-Audit. "Before submitting a listing for
// Tenant Admin review, a seller may trigger a real-time, server-side
// Gemini AI pre-check ... returning a quality score, status flag, and
// one-click 'Apply Title' action. This check is purely advisory -- it
// never gates or auto-approves a listing."
//
// Genuinely blocked on an external credential (a real Gemini API key)
// this environment doesn't have -- same category as BR-52's payment
// gateway. Built end to end anyway, inert until GEMINI_API_KEY is set:
// evaluate() throws immediately, before any network call, if the key
// is absent, so both callers (the portal button and the Tenant API
// endpoint) can degrade to a clean "not currently available" response
// rather than a fake result. The request/response contract, tier
// gating, and both call sites are real and exercised by test:aiaudit;
// the one thing that can't be tested in this environment is an actual
// Gemini response, since there's no real key to call with.
class GeminiPreAuditService
{
    private const DEFAULT_MODEL = 'gemini-2.0-flash';

    public function isConfigured(): bool
    {
        return trim((string) env('GEMINI_API_KEY', '')) !== '';
    }

    // $draft: category, subcategory, physicalCondition, quantity,
    // quantityBasis, makeModel -- the same fields the create/edit form
    // and the Tenant API's push payload already collect. Returns
    // qualityScore (0-100), suggestedTitle, missingFields (a list of
    // business-level parameters BR-46 names as examples -- condition
    // grading, GST HSN/SAC codes, operating hours -- the model is
    // asked to flag, not a fixed platform field list), and statusFlag.
    public function evaluate(array $draft): array
    {
        $apiKey = trim((string) env('GEMINI_API_KEY', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('AI pre-audit is not currently available: no Gemini API key is configured.');
        }

        $model = env('GEMINI_MODEL', self::DEFAULT_MODEL);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = \Config\Services::curlrequest()->post($url, [
            'timeout' => 15,
            'json' => [
                'contents' => [[
                    'parts' => [['text' => $this->buildPrompt($draft)]],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'qualityScore' => ['type' => 'INTEGER'],
                            'suggestedTitle' => ['type' => 'STRING'],
                            'missingFields' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                            'statusFlag' => ['type' => 'STRING', 'enum' => ['good', 'needs_attention', 'incomplete']],
                        ],
                        'required' => ['qualityScore', 'suggestedTitle', 'missingFields', 'statusFlag'],
                    ],
                ],
            ],
        ]);

        $body = json_decode($response->getBody(), true);
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            throw new \RuntimeException('AI pre-audit is not currently available: the model returned an unexpected response.');
        }

        $result = json_decode($text, true);
        if (!is_array($result)) {
            throw new \RuntimeException('AI pre-audit is not currently available: the model returned an unparseable response.');
        }

        return [
            'qualityScore' => (int) ($result['qualityScore'] ?? 0),
            'suggestedTitle' => (string) ($result['suggestedTitle'] ?? ''),
            'missingFields' => array_values(array_map('strval', $result['missingFields'] ?? [])),
            'statusFlag' => (string) ($result['statusFlag'] ?? 'needs_attention'),
        ];
    }

    private function buildPrompt(array $draft): string
    {
        $lines = [
            'You are reviewing a draft B2B salvage/repossessed-asset listing before its seller submits it for marketplace review.',
            'Evaluate completeness and clarity. Suggest an optimized, professional title. Flag missing business-level parameters such as condition grading detail, GST HSN/SAC codes, and operating hours, if genuinely absent from the draft below.',
            'This check is advisory only -- it must never claim to approve, reject, or gate the listing.',
            '',
            'Draft listing:',
            'Category: ' . ($draft['category'] ?? ''),
            'Subcategory: ' . ($draft['subcategory'] ?? ''),
            'Physical condition: ' . ($draft['physicalCondition'] ?? ''),
            'Quantity: ' . ($draft['quantity'] ?? '') . ' ' . ($draft['quantityBasis'] ?? ''),
            'Make/Model: ' . ($draft['makeModel'] ?? ''),
        ];

        return implode("\n", $lines);
    }
}
