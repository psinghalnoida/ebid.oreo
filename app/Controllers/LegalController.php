<?php

namespace App\Controllers;

class LegalController extends BaseController
{
    // Styled placeholder for fields not yet filled in, per the project
    // owner's explicit decision (docs/DECISIONS.md D-15): publish the
    // reviewed structural content now, leave unfilled fields as a clearly
    // labeled "pending" note rather than raw bracket text or an invented value.
    private function pending(string $label = 'Pending — to be published'): string
    {
        return '<span class="legal-pending">' . esc($label) . '</span>';
    }

    public function termsOfUsage()
    {
        $p = $this->pending();
        $bodyHtml = <<<HTML
<p><strong>Operator:</strong> {$p} &nbsp; <strong>Effective Date:</strong> {$p} &nbsp; <strong>Contact:</strong> {$p}</p>

<h2>1. Introduction &amp; Acceptance</h2>
<p><strong>1.1.</strong> eBid Hub ("the Platform", "we", "us") is a multi-tenant online marketplace that enables the sale of salvaged, surplus, repossessed, used, and specialised assets between registered Sellers and registered Buyers, operating through individually branded storefronts ("Tenants" or "Shops").</p>
<p><strong>1.2.</strong> By registering an account, browsing listings, placing a bid or offer, or otherwise using the Platform, you agree to be bound by these Terms of Usage ("Terms"), our Privacy Policy, and any additional terms published by the specific Tenant/Shop through which you transact.</p>
<p><strong>1.3.</strong> If you do not agree to these Terms, you must not register for or use the Platform.</p>
<p><strong>1.4.</strong> These Terms apply to all users of the Platform, including Buyers, Sellers, and Tenant Administrators, collectively referred to as "Users" or "you."</p>

<h2>2. Definitions</h2>
<p><strong>"Platform":</strong> The eBid Hub website, mobile-responsive application, and all associated services.</p>
<p><strong>"Super Admin":</strong> The sovereign administrator of the Platform, who does not participate as a Buyer, Seller, or Tenant Administrator in any transaction.</p>
<p><strong>"Tenant / Shop":</strong> A whitelisted storefront operating on the Platform under its own branding, administered by a Tenant Administrator.</p>
<p><strong>"Tenant Administrator":</strong> A person authorised by a Tenant's company, under formal agreement with eBid Hub, to administer that Tenant's storefront.</p>
<p><strong>"Buyer":</strong> A registered User who bids on, offers for, or purchases a listed item.</p>
<p><strong>"Seller":</strong> A registered User approved by a specific Tenant to list items for sale on that Tenant's storefront.</p>
<p><strong>"Listing":</strong> A cataloged item and its associated description, media, and condition information.</p>
<p><strong>"Sale System":</strong> The transactional format under which a Listing is offered for sale — Buy-Now, Express Auction, Easy Auction, or Tender Auction.</p>
<p><strong>"EMD (Earnest Money Deposit)":</strong> A refundable security deposit pledged by a Buyer to participate in a bid or offer, calculated as 10% of the applicable value.</p>

<h2>4. Account Security</h2>
<p><strong>4.1.</strong> You are solely responsible for all activity that occurs under your account, whether or not authorised by you, except where such activity results from the Platform's own security failure.</p>
<p><strong>4.2.</strong> You must notify the Platform immediately if you suspect unauthorised access to your account.</p>
<p><strong>4.3.</strong> Three consecutive failed mPIN attempts will trigger a mandatory OTP verification step before further access is permitted.</p>

<h2>5. Nature of the Platform</h2>
<p><strong>5.1.</strong> eBid Hub is a marketplace facilitator only. We are not a party to, and assume no responsibility for, the underlying sale contract formed between a Buyer and a Seller. Title to any item transfers directly between Buyer and Seller, not through the Platform.</p>
<p><strong>5.2.</strong> The Platform does not take custody of, inspect, or guarantee the condition, legality, or authenticity of any listed item, except to the extent expressly stated in these Terms.</p>
<p><strong>5.3.</strong> 100% of the sale value for any completed transaction is settled directly and offline between Buyer and Seller. The Platform at no point holds, transmits, or has custody of the full sale value — only the EMD (where applicable) is held via the Platform's payment mechanisms.</p>
<p><strong>5.4.</strong> The Super Admin does not participate as a Buyer, Seller, or Tenant Administrator in any transaction on the Platform, and has no visibility into live bidding activity while it is in progress.</p>

<h2>6. Tenants &amp; Shops</h2>
<p><strong>6.1.</strong> Each Tenant operates its own branded storefront under an agreement with eBid Hub, and is administered by a Tenant Administrator authorised by the Tenant's company.</p>
<p><strong>6.2.</strong> A Tenant Administrator has authority to approve or reject Listings and Sale Events on their storefront, set local commission and fee terms within Platform-wide limits, and approve or suspend Sellers on their storefront.</p>
<p><strong>6.3.</strong> Approval to sell on one Tenant's storefront does not extend to any other Tenant's storefront. A Seller must separately apply to and be approved by each Tenant on whose storefront they wish to list items.</p>
<p><strong>6.4.</strong> A Tenant may restrict or suspend a Seller's or Buyer's access to its own storefront at its discretion. Such restriction is limited to that Tenant's storefront and does not, by itself, affect the User's Star Rating or ability to transact on other Tenants' storefronts.</p>

<h2>7. Listings</h2>
<p><strong>7.1.</strong> Only salvaged, claims-related, surplus, repossessed, used, or specialised assets may be listed on the Platform. New retail-consumer goods are prohibited.</p>
<p><strong>7.2.</strong> Every Listing must include a minimum of 5 and a maximum of 50 seller-uploaded photographs of the actual item, with one designated as the primary display photograph. Stock photography, placeholder images, or auto-generated fallbacks are strictly prohibited.</p>
<p><strong>7.3.</strong> Sellers warrant that all information provided in a Listing — including condition, quantity, and specifications — is accurate to the best of their knowledge. Material misrepresentation may result in Listing removal, Seller rating penalties, and/or account suspension.</p>
<p><strong>7.4.</strong> Once a Listing is live and attached to an active Sale Event, its parameters cannot be altered directly. Any requested change follows the Platform's edit-request process, which may result in the original Listing being archived and a new Listing created.</p>

<h2>8. Sale Formats</h2>
<p><strong>8.1.</strong> The Platform supports four Sale Systems: Buy-Now (negotiated offers, Seller selects the winning offer at their discretion), Express Auction (automated rapid bidding, triggered once three Buyers pledge EMD), Easy Auction (scheduled bidding with a Seller-defined inspection window), and Tender Auction (private, invitation-only, fully Seller-curated, available only on the Platform's own operated storefront).</p>
<p><strong>8.2.</strong> Each Sale Format's specific mechanics — including bidding increments, timing windows, and inspection availability — are as published on the Platform at the time of listing and may vary by format.</p>
<p><strong>8.3.</strong> In Buy-Now transactions, the Seller retains full discretion to accept any offer, including one that is not the highest received, taking into account factors such as the Buyer's Star Rating.</p>
<p><strong>8.4.</strong> Bidding on Easy and Express Auction formats constitutes an acknowledgment that you have had a reasonable opportunity to review the Listing and, where applicable, inspect the item, and a waiver of the right to dispute the item's general condition post-sale, save for cases of fraud or material misrepresentation.</p>

<h2>9. Bidding, Offers &amp; Earnest Money Deposit (EMD)</h2>
<p><strong>9.1.</strong> A Buyer must pledge an EMD of 10% of the applicable Reserve Value or Expected Value before a bid or offer will be accepted. This requirement is verified at the time of every bid, not solely at auction close. Where the Buyer's chosen payment method carries a payment gateway collection charge, that charge is payable by the Buyer in addition to the EMD amount, disclosed prior to payment, so that the full EMD is what is held in escrow.</p>
<p><strong>9.2.</strong> Where a winning bid or accepted offer exceeds the value against which the initial EMD was calculated, the Buyer must remit the balance EMD within the timeframe specified for the applicable Sale Format, failing which the Buyer will be treated as in default.</p>
<p><strong>9.3.</strong> In the event of Buyer default following a win, the Platform's cascading default process (Section 12) applies.</p>
<p><strong>9.4.</strong> EMD funds are held via a segregated escrow mechanism, in compliance with applicable Reserve Bank of India regulations. No pooled or wallet-style balance is maintained.</p>
<p><strong>9.5.</strong> Unused EMD is released back to the Buyer's account upon the earlier of: non-selection as the winning bidder, or completion of settlement per Section 11.</p>

<h2>10. Payment &amp; Settlement</h2>
<p><strong>10.1.</strong> Upon a successful sale, the Buyer shall pay 100% of the agreed sale value directly to the Seller, through an offline payment method mutually agreed between them. The Platform is not a party to, and bears no liability for, this payment.</p>
<p><strong>10.2.</strong> A transaction is deemed formally closed only once all of the following are complete: (a) the Seller has registered a digital NOC confirming receipt of full payment; (b) the Buyer has registered a digital NOC confirming receipt of the goods; (c) the Buyer has submitted a mandatory Star Rating of the Seller; and (d) the Seller has submitted a mandatory Star Rating of the Buyer.</p>
<p><strong>10.3.</strong> Where a party fails to complete their obligation under Section 10.2 within the Platform's prescribed timeframe, the Platform's stall-resolution process applies, which may include a time-bound reminder followed by administrative intervention.</p>
<p><strong>10.4.</strong> Following formal closure of a transaction, applicable commission and Platform fees are deducted from the Buyer's held EMD, and the remaining balance, if any, is released to the Buyer.</p>

<h2>11. Fees &amp; Commission</h2>
<p><strong>11.1.</strong> Sellers are not charged any fee to list or sell items on the Platform.</p>
<p><strong>11.2.</strong> A transaction fee is charged to the Buyer, at a rate determined by the relevant Tenant (subject to Platform-wide limits), and deducted from the Buyer's held EMD at settlement.</p>
<p><strong>11.3.</strong> eBid Hub charges each Tenant a platform usage fee of 0.5% of final sale turnover on transactions completed through that Tenant's storefront.</p>
<p><strong>11.4.</strong> All applicable fees will be disclosed to the Buyer prior to the placement of a bid or offer.</p>

<h2>12. Cancellations, Defaults &amp; Forfeiture</h2>
<p><strong>12.1.</strong> If a winning Buyer fails to complete payment of the required EMD balance within the applicable timeframe, they are deemed in default, and their pledged EMD is forfeited in full.</p>

<h2>13. Star Ratings</h2>
<p><strong>13.5.</strong> Star Ratings, and associated dispute history, are tied to a User's single global account and persist across all Tenants on the Platform.</p>

<h2>14. Disputes</h2>
<p><strong>14.1.</strong> Disputes concerning payment, item condition, delivery/collection, or a Tenant's rejection of an auction result may be raised by either party against a specific transaction within the timeframe published on the Platform.</p>
<p><strong>14.2.</strong> Both parties to a dispute will be given an opportunity to submit supporting evidence within a defined window.</p>
<p><strong>14.3.</strong> Disputes are reviewed and ruled upon by the relevant Tenant Administrator or, where the dispute concerns Buyer conduct, by the Super Admin. Every ruling will be accompanied by a stated rationale.</p>
<p><strong>14.4.</strong> A ruling made by a Tenant Administrator may be appealed once to the Super Admin, whose decision on appeal is final. Rulings made directly by the Super Admin are final and not subject to further appeal.</p>
<p><strong>14.5.</strong> The Platform reserves the right to take action, including rating adjustment or account restriction, against a User found to have engaged in a pattern of repeated, baseless dispute filing.</p>

<h2>15. Prohibited Conduct</h2>
<p>You agree that you will not: list any item outside the Platform's permitted categories, or misrepresent an item's condition, quantity, or authenticity; attempt to circumvent the Platform to complete a transaction directly with a counterparty in order to avoid applicable fees; place a bid or offer without a genuine intention to complete the transaction; attempt to access, bid on, or influence a Sale Event where you hold a conflicting role; use the Platform for any unlawful purpose; or attempt to interfere with, disable, or circumvent any security, verification, or audit mechanism of the Platform.</p>
<p>Violation of this Section may result in Listing removal, rating penalties, transaction reversal, suspension, or permanent removal from the Platform, at the Platform's discretion and following the Platform's internal governance process.</p>

<h2>16. Shipping &amp; Delivery</h2>
<p><strong>16.1.</strong> Sellers may optionally offer shipping for a Listing, at either a fixed or distance-based cost, disclosed at the time of listing. Buyers always retain the right to self-collect an item at no shipping cost.</p>
<p><strong>16.2.</strong> Arrangements for shipping or collection are made directly between Buyer and Seller. The Platform is not responsible for the performance of any shipping or logistics arrangement.</p>

<h2>17. Content &amp; Intellectual Property</h2>
<p><strong>17.1.</strong> You retain ownership of any photographs, descriptions, and other content you upload to a Listing, but grant the Platform a non-exclusive, worldwide licence to host, display, reproduce, and distribute such content for the purpose of operating the Platform.</p>
<p><strong>17.2.</strong> You warrant that you own or have the necessary rights to any content you upload, and that such content does not infringe the intellectual property or other rights of any third party.</p>
<p><strong>17.3.</strong> The eBid Hub name, logo, and Platform design are the property of eBid Hub and may not be used without prior written consent.</p>

<h2>18. Data &amp; Privacy</h2>
<p><strong>18.1.</strong> Your personal data is collected, used, and stored in accordance with the Platform's Privacy Policy, which forms part of these Terms by reference.</p>
<p><strong>18.2.</strong> Sensitive identity data (including Aadhaar) is stored in tokenised/masked form. Transaction and audit records are retained in accordance with the Platform's data retention policy for a minimum period as required under applicable Indian law.</p>

<h2>19. Limitation of Liability</h2>
<p><strong>19.1.</strong> The Platform is provided on an "as is" and "as available" basis. To the maximum extent permitted by applicable law, eBid Hub disclaims all warranties, whether express or implied, regarding the Platform's operation or the quality, safety, or legality of any listed item.</p>
<p><strong>19.2.</strong> eBid Hub's aggregate liability to any User arising from or relating to use of the Platform shall not exceed the total fees paid by that User to the Platform in the twelve (12) months preceding the event giving rise to the claim, save where such limitation is not permitted under applicable law.</p>
<p><strong>19.3.</strong> eBid Hub shall not be liable for any indirect, incidental, or consequential loss, including loss of profit or business opportunity, arising from use of the Platform.</p>

<h2>20. Indemnification</h2>
<p><strong>20.1.</strong> You agree to indemnify and hold harmless eBid Hub, its officers, and the relevant Tenant, from any claim, loss, or liability arising from your breach of these Terms, your Listing content, or your conduct in any transaction.</p>

<h2>21. Suspension &amp; Termination</h2>
<p><strong>21.1.</strong> The Platform may suspend or terminate your account for breach of these Terms, following the Platform's internal governance and, where applicable, dispute resolution process.</p>
<p><strong>21.2.</strong> You may close your account at any time, subject to the completion of any pending transactions and settlement obligations.</p>
<p><strong>21.3.</strong> Provisions of these Terms that by their nature should survive termination (including Sections 17 through 20) shall survive.</p>

<h2>22. Governing Law &amp; Jurisdiction</h2>
<p><strong>22.1.</strong> These Terms are governed by the laws of India.</p>
<p><strong>22.2.</strong> Subject to the Platform's internal dispute resolution process, any dispute arising from these Terms shall be subject to the exclusive jurisdiction of the courts at {$p}.</p>

<h2>23. Amendments</h2>
<p><strong>23.1.</strong> eBid Hub may amend these Terms from time to time. Material changes will be notified to Users through the Platform. Continued use of the Platform following such notice constitutes acceptance of the amended Terms.</p>

<h2>24. Grievance Officer &amp; Contact</h2>
<p>In accordance with the Information Technology Act 2000 and rules made thereunder, the details of the Grievance Officer are: {$p}</p>
<p>For all other queries, contact: {$p}</p>
HTML;

        return view('legal/document', ['title' => 'Terms of Usage — eBid Hub', 'docTitle' => 'Terms of Usage', 'bodyHtml' => $bodyHtml]);
    }

    public function privacyPolicy()
    {
        $p = $this->pending();
        $bodyHtml = <<<HTML
<p><strong>Data Fiduciary:</strong> {$p} &nbsp; <strong>Effective Date:</strong> {$p} &nbsp; <strong>Contact:</strong> {$p}</p>

<h2>1. Introduction &amp; Scope</h2>
<p><strong>1.1.</strong> This Privacy Policy describes how eBid Hub ("the Platform", "we", "us") collects, uses, discloses, and protects the personal data of registered Buyers, Sellers, and Tenant Administrators ("you") in connection with your use of the Platform.</p>
<p><strong>1.2.</strong> This Policy applies across all Tenant storefronts operating on the Platform. Individual Tenants may not collect or process your personal data outside the mechanisms described in this Policy without your separate consent.</p>
<p><strong>1.3.</strong> By registering for or using the Platform, you consent to the collection and processing of your personal data as described in this Policy.</p>

<h2>2. Data We Collect</h2>
<p><strong>2.1 Identity &amp; Contact Data</strong> — Mobile phone number (verified via OTP), your unique platform identifier; full name, salutation, date of birth; email address (where provided).</p>
<p><strong>2.2 KYC &amp; Verification Data</strong> — Individual Users: PAN, Aadhaar (masked/tokenised), occupation. Business Users: CIN, GSTIN, company PAN, MSME/UDYAM registration, company type, industry, and optionally annual turnover and employee count. Supporting documents as applicable to your account type.</p>
<p><strong>2.3 Address &amp; Banking Data</strong> — Up to four address records (Registered, Billing, Correspondence, Site/Yard), including optional GPS coordinates. Encrypted banking details for processing refunds and settlements.</p>
<p><strong>2.4 Transactional Data</strong> — Listings, bids, offers, EMD pledges and refunds, settlement records, NOC confirmations, and Star Rating history.</p>
<p><strong>2.5 Listing Media &amp; Location Data</strong> — Photographs, videos, and documents uploaded for a Listing, including structured GPS and timestamp data captured at the moment of upload for authenticity verification.</p>

<h2>10. Data Storage &amp; Localisation</h2>
<p><strong>10.1.</strong> The Platform's operations, compliance, and data processing are currently scoped to India, consistent with the Platform's operational jurisdiction.</p>
<p><strong>10.2.</strong> Where any service provider processes data outside India (e.g., a global cloud infrastructure provider), such transfer will be made subject to appropriate contractual safeguards.</p>

<h2>11. Grievance Officer &amp; Contact</h2>
<p>In accordance with the Information Technology Act 2000 and rules made thereunder, the details of the Grievance Officer are: {$p}</p>
<p>For any privacy-related queries or to exercise your data rights, contact: {$p}</p>

<h2>12. Changes to This Policy</h2>
<p><strong>12.1.</strong> We may update this Privacy Policy from time to time. Material changes will be notified to Users through the Platform, and the "Effective Date" at the top of this Policy will be updated accordingly.</p>
HTML;

        return view('legal/document', ['title' => 'Privacy Policy — eBid Hub', 'docTitle' => 'Privacy Policy', 'bodyHtml' => $bodyHtml]);
    }

    public function grievanceRedressal()
    {
        $p = $this->pending();
        $bodyHtml = <<<HTML
<p><strong>Effective Date:</strong> {$p} &nbsp; <strong>Contact:</strong> {$p}</p>

<h2>1. Purpose &amp; Scope</h2>
<p><strong>1.1.</strong> This Policy sets out how to raise a grievance about eBid Hub as a platform — distinct from a dispute about a specific transaction, which is handled under our separate Dispute Resolution Process.</p>
<p><strong>1.2.</strong> A grievance may relate to matters such as: how your data has been handled, conduct by a Tenant Admin or platform staff, a concern about how a rule was applied to you, or dissatisfaction with the outcome of a prior support interaction.</p>

<h2>2. Grievance Officer</h2>
<p>In accordance with the Information Technology Act 2000 and the rules made thereunder, the details of our Grievance Officer are: {$p}</p>

<h2>3. How to File a Grievance</h2>
<p><strong>3.1.</strong> A grievance may be submitted in writing, through the contact channel published on the Trust &amp; Support section of the app, or directly to the Grievance Officer.</p>
<p><strong>3.2.</strong> Please include: your registered mobile number, a description of the issue, and any relevant transaction or listing reference.</p>

<h2>4. Acknowledgement &amp; Resolution Timelines</h2>
<p><strong>4.1.</strong> Grievances are acknowledged within 24 hours of receipt, and are resolved, or a substantive response provided, within 15 days.</p>

<h2>5. Escalation Path</h2>
<p><strong>5.1.</strong> Most day-to-day concerns are resolved directly by your Tenant's support team or Tenant Admin.</p>
<p><strong>5.2.</strong> If unresolved, or if the concern relates to SaaS-level conduct, it is escalated to SaaS Admin.</p>
<p><strong>5.3.</strong> If still unresolved, or if you are dissatisfied with the response, you may escalate directly to the Grievance Officer named in Section 2.</p>

<h2>6. Your Right to External Recourse</h2>
<p><strong>6.1.</strong> Nothing in this Policy limits your right to approach a Consumer Disputes Redressal Commission, another applicable regulatory authority, or a court of competent jurisdiction, at any time.</p>
HTML;

        return view('legal/document', ['title' => 'Grievance Redressal — eBid Hub', 'docTitle' => 'Grievance Redressal Policy', 'bodyHtml' => $bodyHtml]);
    }

    public function refundCancellation()
    {
        $bodyHtml = <<<HTML
<p>Full detail and worked examples appear in the Fee &amp; Charges Schedule.</p>

<h2>4. Withdrawing an Offer or Bid</h2>
<p><strong>4.1.</strong> Buy-Now: an offer may be withdrawn before the Seller accepts it, provided the Buyer states a reason from the list provided in-app. No reason is required if the offer simply lapses unactioned after 3 days.</p>
<p><strong>4.2.</strong> Easy and Express Auctions: once a bid is placed and the required EMD is pledged, it stands for the remainder of that auction. There is no separate mid-auction bid withdrawal mechanism distinct from being outbid.</p>
<p><strong>4.3.</strong> Tender Auction: withdrawal and its consequences are entirely as set by the Seller for that specific auction.</p>

<h2>5. Listing &amp; Sale Event Cancellation</h2>
<p><strong>5.1.</strong> A Seller may request a change to an approved listing at any time; if an active Sale Event is attached, that event is cancelled and all active bids/deposits on it are refunded in full, with bidders notified. The listing itself is then archived and a new version re-enters the approval process.</p>
<p><strong>5.2.</strong> Once a Sale Event is live, it cannot be cancelled by the Seller directly (Express Auctions cannot be cancelled by the Seller at all). The Tenant Admin or Super Admin may terminate any live event via an Emergency Stop, with a mandatory, logged reason, in which case all active bids/deposits are refunded in full.</p>

<h2>6. Verified Listing Inspection Fee</h2>
<p><strong>6.1.</strong> The inspection fee for a Verified listing is payable before inspection is scheduled.</p>

<h2>7. How Refunds Are Paid</h2>
<p><strong>7.1.</strong> Refunds are paid to the account originally used to fund the deposit. A different payout account may only be used if it has been formally verified and any required cooling-off period has elapsed, per the Terms of Usage.</p>

<h2>8. Contact</h2>
<p>Questions about a specific refund or forfeiture should be raised through Dispute Resolution or directly with your Tenant's support channel.</p>
HTML;

        return view('legal/document', ['title' => 'Refund & Cancellation — eBid Hub', 'docTitle' => 'Refund & Cancellation Policy', 'bodyHtml' => $bodyHtml]);
    }

    public function disputeResolution()
    {
        $p = $this->pending();
        $bodyHtml = <<<HTML
<p><strong>Effective Date:</strong> {$p} &nbsp; <strong>Contact:</strong> {$p}</p>

<h2>1. What This Covers</h2>
<p>This document explains, in plain language, how a disagreement about a specific transaction gets reviewed and resolved on eBid Hub. It applies to Buy-Now, Express, and Easy Auctions. Tender Auctions are excluded, since they run entirely on terms set directly by the seller.</p>

<h2>2. What Can Be Disputed</h2>
<p><strong>Payment Dispute:</strong> one side says money was paid or received when the other side disagrees.</p>
<p><strong>Condition/Delivery Dispute:</strong> the item received doesn't genuinely match what was described or shown.</p>
<p><strong>Non-Lifting/Collection Dispute:</strong> a buyer won't collect an item, or a seller is blocking collection.</p>
<p><strong>Auction Rejection Dispute:</strong> a seller rejected a winning result and you believe the reason given doesn't hold up.</p>
<p><strong>Buyer Non-Response Dispute:</strong> a buyer has gone silent on confirming or rating, holding up a seller's settlement.</p>
<p>A sixth, internal process — Standing Review — looks at a seller's overall pattern of conduct over time. This isn't something you file; it happens automatically based on accumulated history.</p>

<h2>3. How to File</h2>
<p><strong>3.1.</strong> Open the transaction in question and select "Raise a Dispute." Choose the category that best matches your situation and provide a clear description.</p>
<p><strong>3.2.</strong> Disputes must be filed within 7 days of the event that triggered them (for example, within 7 days of the expected delivery date, or of a rejected result).</p>

<h2>4. Evidence</h2>
<p><strong>4.1.</strong> Once filed, both sides have a window to upload supporting evidence — photographs, messages, delivery records, payment proof, or anything else relevant.</p>
<p><strong>4.2.</strong> A ruling is based only on the evidence submitted within this window, so it's worth submitting everything relevant promptly rather than waiting.</p>

<h2>5. Who Decides</h2>
<p><strong>5.1.</strong> Most disputes are reviewed and ruled on by your Tenant's own admin team, since they're closest to that shop's transactions.</p>
<p><strong>5.2.</strong> Disputes specifically about buyer non-response are reviewed directly by our platform-level team.</p>
<p><strong>5.3.</strong> Every ruling comes with a stated reason — you'll always know why a decision was made, not just what the decision was.</p>
HTML;

        return view('legal/document', ['title' => 'Dispute Resolution — eBid Hub', 'docTitle' => 'Dispute Resolution Process', 'bodyHtml' => $bodyHtml]);
    }

    public function cookiePolicy()
    {
        $p = $this->pending();
        $bodyHtml = <<<HTML
<p><strong>Effective Date:</strong> {$p} &nbsp; <strong>Contact:</strong> {$p}</p>

<h2>1. What Are Cookies</h2>
<p>Cookies are small text files placed on your device when you use a website or app, used to remember information about your visit.</p>

<h2>2. Cookies We Use</h2>
<table>
<tr><th>Category</th><th>Purpose</th><th>Can You Opt Out?</th></tr>
<tr><td>Strictly Necessary / Session</td><td>Keeps you logged in and your session secure while using the platform.</td><td>No — required for the platform to function.</td></tr>
<tr><td>Functional</td><td>Remembers preferences such as your discovery filters, so you don't have to reset them each visit.</td><td>Yes, though some convenience features may not work without them.</td></tr>
<tr><td>Analytics</td><td>Helps us understand how the platform is used, to improve it over time.</td><td>Yes, where offered.</td></tr>
</table>

<h2>3. What We Do Not Use Cookies For</h2>
<p>We do not use cookies to store your banking details, EMD/deposit balance, or KYC documents — this data is held server-side, within our segregated escrow and encrypted storage systems, never in a browser cookie. We do not use third-party advertising or ad-retargeting cookies.</p>

<h2>4. Third-Party Cookies</h2>
<p><strong>4.1.</strong> Our Payment Gateway partner may set its own cookies during the payment/checkout process, governed by their own cookie and privacy practices, not this Policy.</p>

<h2>5. Managing Your Preferences</h2>
<p><strong>5.1.</strong> Most browsers let you block or delete cookies through their settings. Blocking strictly necessary cookies will prevent you from using core platform features, including logging in.</p>

<h2>6. Changes to This Policy</h2>
<p><strong>6.1.</strong> We may update this Policy as our use of cookies changes. Material changes will be reflected in the "Effective Date" above.</p>
HTML;

        return view('legal/document', ['title' => 'Cookie Policy — eBid Hub', 'docTitle' => 'Cookie Policy', 'bodyHtml' => $bodyHtml]);
    }
}
