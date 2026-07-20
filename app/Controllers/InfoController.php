<?php

namespace App\Controllers;

class InfoController extends BaseController
{
    public function faq()
    {
        $bodyHtml = <<<HTML
<h2>Sale Formats</h2>
<p><strong>Does the highest offer always win on Buy-Now?</strong><br>Buyer: No — the seller can factor in your rating and reliability, not just price. If they pick a non-highest offer, they must record why. Seller: You're not locked into the highest number; if you pick someone else, you must state a reason, kept on record.</p>
<p><strong>If Express has no inspection, how do I know what I'm bidding on?</strong><br>Buyer: Every Express listing includes a mandatory checklist declaring any known damage or missing parts. A false disclosure is treated as a genuine dispute. Seller: You must complete that defect-disclosure checklist before your listing goes live.</p>

<h2>Winning &amp; Payment</h2>
<p><strong>I won an auction — what happens next?</strong><br>Buyer: If the final price is higher than your deposit covered, top it up first, then pay the seller directly and arrange collection. Seller: You deal with the buyer directly for payment and handover — the platform only ever holds the deposit.</p>
<p><strong>What happens if the winning bidder doesn't pay?</strong><br>Buyer: You forfeit your deposit and the win passes to the next-highest bidder. Seller: Non-paying winners are automatically replaced, one step at a time; if everyone in line fails to pay, the auction cancels and you relist.</p>
<p><strong>Can I lose my win to someone else?</strong><br>No, for either side — once payment completes on time, the sale is final.</p>

<h2>Ratings &amp; Trust</h2>
<p><strong>How does the star rating system work?</strong><br>Everyone starts at 3 stars. Buyer and seller ratings are entirely separate, even for the same person. Paying promptly / accurate listings raise it; defaults / mismatched descriptions lower it.</p>
<p><strong>Can my rating recover after a bad mark?</strong><br>Yes — a defined run of clean transactions restores your standing over time. Repeat issues mean more transactions are needed to recover.</p>
<p><strong>What happens if my rating gets very low?</strong><br>Very low-rated accounts are shown less (fewer alerts, lower visibility) rather than blocked outright — you can still use the platform.</p>

<h2>Settlement &amp; Handover</h2>
<p><strong>How do I actually pay the seller or receive payment?</strong><br>Payment happens directly between buyer and seller, offline, for 100% of the sale value. The platform only ever holds the deposit, never the full sale amount.</p>
<p><strong>Why do I have to confirm something specific right before I pledge a deposit?</strong><br>That acknowledgment spells out exactly what happens if you don't follow through on this specific bid, so the consequence is always clear at the moment you commit.</p>

<h2>Disputes</h2>
<p><strong>What if the item doesn't match the listing?</strong><br>Routine condition complaints aren't valid once you've had an inspection opportunity — but genuine fraud or material misrepresentation can still be disputed.</p>
<p><strong>Who decides the outcome of a dispute?</strong><br>Most disputes are reviewed by the shop's admin; disputes about buyer conduct go to platform-level review. Every decision comes with a stated reason and one appeal right.</p>

<h2>Listings &amp; Shipping</h2>
<p><strong>What are the photo and video requirements for a listing?</strong><br>At least 5 real photos (up to 50), one marked as the main photo — no stock images. Video is optional, up to 2 minutes.</p>
<p><strong>Does the seller have to arrange shipping?</strong><br>No — a buyer can always self-collect at no extra cost, even if the seller also offers shipping.</p>

<h2>Platform Safeguards</h2>
<p><strong>Is there a limit on how much a bid can jump?</strong><br>Yes — no bid can exceed 150% of the current highest bid, catching typos and blocking price manipulation.</p>
<p><strong>How does the platform stop last-second sniping?</strong><br>A bid in the final 10 minutes extends the auction by 2 more minutes, so a last-second bid can't unfairly end things early.</p>
<p><strong>How does the platform prevent fake or misleading photos?</strong><br>Photos are captured through the app at the moment of listing, with location/timestamp data attached automatically — not uploaded from an old gallery.</p>
HTML;

        return view('legal/document', ['title' => 'FAQ — eBid Hub', 'docTitle' => 'Frequently Asked Questions', 'bodyHtml' => $bodyHtml]);
    }

    public function dosAndDonts()
    {
        $bodyHtml = <<<HTML
<p>Everything here reflects the platform's actual rules, written in plain language. When in doubt, the safer choice is almost always the one on the left.</p>

<h2>1. Getting Started</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Register with your own genuine mobile number and complete KYC honestly and fully.</td><td>Create multiple accounts, or register using someone else's details.</td></tr>
<tr><td>Keep your 4-digit mPIN private, and treat it like a banking PIN.</td><td>Share your mPIN or OTP with anyone — not even someone claiming to be platform support.</td></tr>
<tr><td>Apply to a specific shop if you want to sell, and wait for approval.</td><td>Assume selling approval on one shop lets you sell on another.</td></tr>
<tr><td>Read the terms shown before you accept them, especially around a specific bid or deposit.</td><td>Click through consent screens without reading — they describe real, binding commitments.</td></tr>
</table>

<h2>2. Bidding &amp; Deposits (EMD)</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Only bid or offer on items you genuinely intend to buy if you win.</td><td>Bid casually or "just to see" — every bid pledges real money.</td></tr>
<tr><td>Check your available balance before bidding.</td><td>Assume you can back out painlessly after winning — a default forfeits your deposit.</td></tr>
<tr><td>Read the specific forfeiture terms shown before you pledge a deposit.</td><td>Ignore the acknowledgment shown before each pledge.</td></tr>
<tr><td>Use bank transfer or UPI where possible.</td><td>Rely on card payment if bank-transfer is available and convenient.</td></tr>
</table>

<h2>3. Buying</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Inspect the item during the window provided (Easy Auctions, Buy-Now).</td><td>Expect an inspection window on Express Auctions — sight-unseen by design.</td></tr>
<tr><td>Review a seller's rating and listing details carefully before committing.</td><td>Assume the highest bid always wins in Buy-Now.</td></tr>
<tr><td>Raise a genuine concern about fraud or major misrepresentation promptly.</td><td>Dispute an item's general condition after winning if you had a fair chance to inspect first.</td></tr>
<tr><td>Complete payment within the required window if you win.</td><td>Delay payment or go silent after winning.</td></tr>
</table>

<h2>6. Star Ratings &amp; Reputation</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Take a rating drop seriously and use the recovery path available to you.</td><td>Assume a low rating is permanent.</td></tr>
<tr><td>Remember your buyer and seller ratings are separate — build both deliberately.</td><td>Assume being a great seller automatically makes you look trustworthy as a buyer.</td></tr>
</table>

<h2>7. Disputes &amp; Problems</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>File a dispute promptly, within the stated window, with real supporting evidence.</td><td>Wait past the filing window and expect a dispute to still be accepted.</td></tr>
<tr><td>Choose the dispute category that genuinely matches your situation.</td><td>File repeated, weak, or baseless disputes — this pattern is tracked.</td></tr>
<tr><td>Accept a ruling's stated reasoning, or use the appeal path if you genuinely disagree.</td><td>Assume every ruling can be appealed indefinitely — most allow exactly one appeal.</td></tr>
</table>

<h2>8. Account &amp; Payment Security</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Verify your identity again if asked, especially before a bank detail change.</td><td>Be surprised by a short waiting period after changing your payout account.</td></tr>
<tr><td>Report any suspicious account activity immediately.</td><td>Reuse your platform mPIN as a password anywhere else.</td></tr>
</table>

<h2>9. Shipping &amp; Handover</h2>
<table><tr><th>✓ DO</th><th>✗ DON'T</th></tr>
<tr><td>Check whether a seller offers shipping, and its cost, before assuming it's included.</td><td>Assume shipping is mandatory — self-collection is always free.</td></tr>
<tr><td>Agree on collection/delivery logistics clearly and promptly after winning.</td><td>Show up at a yard or site without confirming details with the seller first.</td></tr>
</table>

<h2>10. One-Page Summary</h2>
<p><strong>1. A bid or deposit is a real commitment.</strong> Never place one you're not prepared to honour.</p>
<p><strong>2. Inspect when you can.</strong> Express has none; Easy and Buy-Now give you a real window.</p>
<p><strong>3. Both sides must confirm and rate each other to close a deal.</strong> This is what releases the remaining deposit.</p>
<p><strong>4. Your rating is a real asset.</strong> Recovery is possible, but takes consistent, clean behaviour.</p>
<p><strong>5. If something goes wrong, use the dispute process promptly and honestly.</strong></p>
HTML;

        return view('legal/document', ['title' => "Dos & Don'ts — eBid Hub", 'docTitle' => "Dos & Don'ts", 'bodyHtml' => $bodyHtml]);
    }

    public function securityTrust()
    {
        $bodyHtml = <<<HTML
<h2>Your Money Is Never Pooled</h2>
<p>Every deposit you make is held separately, tied to the specific bid it secures — never mixed into one shared pot with other buyers' money.</p>
<p>Each deposit is a distinct, ring-fenced holding, released or forfeited only based on that specific transaction's outcome. Your deposit is refunded automatically as soon as it's no longer needed — outbid, not selected, or deal closed — with no fixed delay. No interest is ever taken from money held on your behalf. Deposits are held through a licensed, RBI-regulated payment partner, not an unlicensed wallet.</p>

<h2>Your Data Is Protected</h2>
<p>Sensitive information is encrypted, minimised, and shared only with whoever genuinely needs it, only when they need it.</p>
<p>Aadhaar and other sensitive ID data are stored in masked, tokenised form — never in plain text. Banking details are encrypted and used solely to process refunds and settlements. Your identity stays hidden from a counterparty until a real commitment is made on both sides. We never sell your data to anyone, for any reason.</p>

<h2>What You See Is Real</h2>
<p>Listings are built from genuine, verifiable evidence — not stock photos or recycled images.</p>
<p>Every photo is captured through the app itself, at the moment of listing. Location and timestamp data is captured automatically alongside each photo. Stock photography and placeholder images are never allowed. Even on the fastest, sight-unseen format, sellers must disclose any known defects upfront.</p>

<h2>A Level Playing Field</h2>
<p>The bidding process is designed so no one — including us — gets an unfair edge. A 150% bid ceiling catches mistakes and blocks manipulation. Dynamic Time extensions stop last-second sniping. Every rating change and dispute ruling comes with a stated, logged reason.</p>
HTML;

        return view('legal/document', ['title' => 'Security & Trust — eBid Hub', 'docTitle' => 'Security & Trust', 'bodyHtml' => $bodyHtml]);
    }

    public function feeSchedule()
    {
        $bodyHtml = <<<HTML
<h2>1. Overview — Who Pays What</h2>
<p>eBid Hub operates a zero-seller-fee model on Buy-Now, Express, and Easy: all platform commission on these formats is funded by the buyer, deducted from their refundable security deposit at settlement. Tender Auction is the one exception.</p>
<table>
<tr><th>Party</th><th>Pays / Earns</th></tr>
<tr><td>Buyer</td><td>Security Deposit (EMD, 10% of item value, refundable) + Transaction Fee defaulting to 5% (tenant-adjustable). Tender: as set by the seller.</td></tr>
<tr><td>Seller</td><td>Nothing, on Buy-Now/Express/Easy. Tender: per the seller's own arrangement.</td></tr>
<tr><td>Tenant (Shop)</td><td>Majority of the applicable transaction fee (default rate minus SaaS's 0.5% share).</td></tr>
<tr><td>SaaS (Platform)</td><td>Flat 0.5% of final sale value.</td></tr>
</table>

<h2>2. Security Deposit (EMD)</h2>
<table><tr><th>Sale Format</th><th>Deposit Required</th></tr>
<tr><td>Buy-Now</td><td>10% of the seller's Expected Value (EV)</td></tr>
<tr><td>Express Auction</td><td>10% of the Reserve Value (RV)</td></tr>
<tr><td>Easy Auction</td><td>10% of the Reserve Value (RV)</td></tr>
<tr><td>Tender Auction</td><td>Set entirely by the seller</td></tr>
</table>
<p>If the final price exceeds the value the deposit was calculated on, the buyer must top up to 10% of the actual final price before the win is confirmed. If outbid or not selected, the deposit releases automatically with no fixed delay.</p>

<h2>3. Seller Fees</h2>
<p>Sellers pay nothing to list, run a sale, or complete a transaction on Buy-Now, Express, and Easy. Tender is the exception — the seller sets the fee arrangement for that specific auction. The one exception outside Tender: a <strong>Verified listing inspection fee</strong>, charged when eBid Hub's own inspection team visits and photographs the item. Certified by Seller (CBS) listings remain free.</p>

<h2>4. Buyer Transaction Fee &amp; Platform Split</h2>
<p>On Buy-Now and Easy, the buyer's fee defaults to 5%, adjustable by each Tenant between a floor of 0.5% (matching SaaS's own share) and a ceiling of 5%. Express is not tenant-adjustable. Tender is fully custom per auction.</p>
<p><em>Worked example:</em> item sells for ₹1,50,000 at the 5% default rate — total fee ₹7,500. Of that, ₹750 (0.5%) goes to SaaS, ₹6,750 to the Tenant.</p>

<h2>5. GST on Platform Fees</h2>
<p>GST applies on top of every fee charged — never absorbed into the stated percentage. <em>Worked example:</em> on the ₹7,500 fee above, GST at 18% adds ₹1,350 — meaning ₹8,850 is actually deducted from the buyer's deposit.</p>

<h2>7. What Happens If a Buyer Doesn't Pay</h2>
<p><strong>Standard default:</strong> the forfeited deposit is applied first to Tenant/SaaS commission, with the remainder paid to the seller as compensation.</p>
<p><strong>Full cascade failure (Express &amp; Easy only):</strong> if every eligible bidder defaults in sequence (up to 3 attempts), the auction is cancelled and the seller receives none of the forfeited deposits — this exists specifically so a seller has no incentive to set unrealistic terms purely to farm forfeiture revenue.</p>
<table><tr><th>Format</th><th>1st Window</th><th>2nd Window</th><th>3rd Window</th></tr>
<tr><td>Express</td><td>2 hrs</td><td>hr 2–4</td><td>hr 4–6</td></tr>
<tr><td>Easy</td><td>24 hrs</td><td>hr 24–48</td><td>hr 48–72</td></tr>
</table>

<h2>9. Tenant Discretion &amp; Limits</h2>
<p>Each Tenant sets its own buyer fee rate within platform-set limits. SaaS's 0.5% share is always carved out of that same fee, never charged separately. Sellers pay 0% on every Tenant's storefront — platform-wide, not something an individual Tenant can change.</p>
HTML;

        return view('legal/document', ['title' => 'Fee & Charges Schedule — eBid Hub', 'docTitle' => 'Fee & Charges Schedule', 'bodyHtml' => $bodyHtml]);
    }

    public function terminology()
    {
        $bodyHtml = <<<HTML
<p><strong>Baton-Pass (Cascading Default):</strong> What happens when a winning bidder doesn't pay: the win passes to the next-highest bidder at their own price, within their own time window. This can happen up to three times before an auction is cancelled outright.</p>
<p><strong>Bid Ceiling (150% Rule):</strong> A safety limit stopping any single bid from jumping more than 150% above the current price — catches typing mistakes and blocks deliberate price manipulation.</p>
<p><strong>Buy-Now:</strong> A sale format where buyers make offers and the seller personally chooses who to sell to, considering price and the buyer's rating — not necessarily the highest offer.</p>
<p><strong>Certified by Seller (CBS):</strong> A listing where the seller has taken their own photos, by any method. Real, unedited photos of the actual item are required.</p>
<p><strong>Crawl-Back:</strong> A recovery path for a buyer whose rating has dropped significantly — temporarily limited to smaller purchases until a set number of clean transactions restores their standing.</p>
<p><strong>Dispute Resolution:</strong> The formal process for resolving a disagreement about a specific transaction, reviewed with evidence from both sides, decided with a stated reason.</p>
<p><strong>Dynamic Time (Anti-Sniping):</strong> A rule that extends an auction by a short period whenever a bid lands in the closing minutes, so a last-second bid can't unfairly end the auction.</p>
<p><strong>EMD (Earnest Money Deposit):</strong> A refundable security deposit, 10% of the applicable value, pledged to participate in a bid or offer.</p>
<p><strong>Grievance Redressal:</strong> The formal process for raising a concern about the platform itself, distinct from a dispute about a specific transaction.</p>
HTML;

        return view('legal/document', ['title' => 'Terminology — eBid Hub', 'docTitle' => 'Terminology Glossary', 'bodyHtml' => $bodyHtml]);
    }
}
