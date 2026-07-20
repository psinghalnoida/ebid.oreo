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
                    ['icon' => '?', 'title' => 'FAQ', 'description' => 'Common questions on bidding, EMD, formats, and settlement.', 'url' => '/faq'],
                    ['icon' => '✓', 'title' => "Dos & Don'ts", 'description' => 'What\'s expected of you as a buyer or seller on the platform.', 'url' => '/dos-and-donts'],
                    ['icon' => '₹', 'title' => 'Fee & Charges Schedule', 'description' => 'SaaS commission, tenant buyer fees, and how EMD is deducted.', 'url' => '/fees'],
                    ['icon' => '📖', 'title' => 'Terminology', 'description' => 'Plain-language definitions for every platform-specific term.', 'url' => '/terminology'],
                ],
            ],
            [
                'title' => 'Trust & Safety',
                'subtitle' => 'How disputes, refunds, and fraud protection actually work',
                'cards' => [
                    ['icon' => '↺', 'title' => 'Refund & Cancellation Policy', 'description' => 'When EMD is refunded, forfeited, or recalculated at close.', 'url' => '/refund-cancellation'],
                    ['icon' => '⚖', 'title' => 'Dispute Resolution Process', 'description' => 'How payment, condition, and non-lifting disputes get ruled on.', 'url' => '/dispute-resolution'],
                    ['icon' => '🛡', 'title' => 'Security & Trust', 'description' => 'Fishing detection, GPS-verified photos, and fraud safeguards.', 'url' => '/security-trust'],
                ],
            ],
            [
                'title' => 'Legal',
                'subtitle' => 'The formal documents governing your use of eBid Hub',
                'cards' => [
                    ['icon' => '§', 'title' => 'Terms of Service', 'description' => 'The full terms governing every transaction on the platform.', 'url' => '/terms'],
                    ['icon' => '🔒', 'title' => 'Privacy Policy', 'description' => 'What we collect, how it\'s used, and how KYC data is protected.', 'url' => '/privacy'],
                    ['icon' => '!', 'title' => 'Grievance Redressal', 'description' => 'How to formally escalate a complaint, and expected timelines.', 'url' => '/grievance-redressal'],
                ],
            ],
        ];

        // NOTE: legal document content reflects the project owner's
        // confirmed decision to publish the reviewed structural content
        // now, with not-yet-filled fields (entity name, effective date,
        // Grievance Officer contact, jurisdiction) shown as a clearly
        // labeled "pending" note rather than raw placeholder brackets or
        // an invented value. See docs/DECISIONS.md D-15.

        return view('trust_support', [
            'title' => 'Trust & Support — eBid Hub',
            'groups' => $groups,
        ]);
    }
}
