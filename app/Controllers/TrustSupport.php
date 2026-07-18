<?php

namespace App\Controllers;

class TrustSupport extends BaseController
{
    public function index(): string
    {
        $groups = [
            [
                'title' => 'Using the Platform',
                'subtitle' => 'Practical answers for day-to-day buying and selling',
                'cards' => [
                    ['icon' => '?', 'title' => 'FAQ', 'description' => 'Common questions on bidding, EMD, formats, and settlement.'],
                    ['icon' => '✓', 'title' => "Dos & Don'ts", 'description' => 'What\'s expected of you as a buyer or seller on the platform.'],
                    ['icon' => '₹', 'title' => 'Fee & Charges Schedule', 'description' => 'SaaS commission, tenant buyer fees, and how EMD is deducted.'],
                ],
            ],
            [
                'title' => 'Trust & Safety',
                'subtitle' => 'How disputes, refunds, and fraud protection actually work',
                'cards' => [
                    ['icon' => '↺', 'title' => 'Refund & Cancellation Policy', 'description' => 'When EMD is refunded, forfeited, or recalculated at close.'],
                    ['icon' => '⚖', 'title' => 'Dispute Resolution Process', 'description' => 'How payment, condition, and non-lifting disputes get ruled on.'],
                    ['icon' => '🛡', 'title' => 'Security & Trust', 'description' => 'Fishing detection, GPS-verified photos, and fraud safeguards.'],
                ],
            ],
            [
                'title' => 'Legal',
                'subtitle' => 'The formal documents governing your use of eBid Hub',
                'cards' => [
                    ['icon' => '§', 'title' => 'Terms of Service', 'description' => 'The full terms governing every transaction on the platform.'],
                    ['icon' => '🔒', 'title' => 'Privacy Policy', 'description' => 'What we collect, how it\'s used, and how KYC data is protected.'],
                    ['icon' => '!', 'title' => 'Grievance Redressal', 'description' => 'How to formally escalate a complaint, and expected timelines.'],
                ],
            ],
        ];

        // NOTE: this content is placeholder structure only — awaiting the
        // legally-reviewed final copy before it's treated as real policy
        // text (per project owner's instruction to legal-review before
        // handoff). Do not treat any card description above as final.

        return view('trust_support', [
            'title' => 'Trust & Support — eBid Hub',
            'groups' => $groups,
        ]);
    }
}
