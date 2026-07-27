import React, { useState, useMemo, useRef, useEffect } from "react";
import { Search, ChevronDown, X, Circle } from "lucide-react";

/* ------------------------------------------------------------------ */
/* Design tokens                                                       */
/* ink graphite / paper sage / rust=buyer / teal=seller / amber=match  */
/* ------------------------------------------------------------------ */
const T = {
  ink: "#1C1F26",
  inkSoft: "#5B5F56",
  paper: "#EEF0EA",
  card: "#FFFFFF",
  line: "#D8DACE",
  rust: "#B85C2C",
  rustSoft: "#F3E3D6",
  teal: "#2F5A5F",
  tealSoft: "#DCE7E6",
  amber: "#E3A93C",
};

/* ------------------------------------------------------------------ */
/* Content — plain language, buyer / seller pairs, tagged for search   */
/* ------------------------------------------------------------------ */
const CATEGORIES = [
  {
    id: "start",
    label: "Getting Started",
    items: [
      {
        code: "GS-01",
        q: "How do I create an account?",
        buyer:
          "Enter your mobile number and verify it with an OTP. Once verified, set a 4-digit PIN — you'll use your number and PIN to log in from then on, no password to remember.",
        seller:
          "Same first step as a buyer — every account starts as a buyer account. To list items for sale, you then apply to a specific shop and get approved by that shop's admin.",
        keywords: "signup register otp pin mobile phone mpin login",
      },
      {
        code: "GS-02",
        q: "What documents do I need to verify my account?",
        buyer:
          "Individuals: your name, PAN, Aadhaar, date of birth, and occupation. Businesses: company registration details (CIN, GST, PAN) and a few supporting documents. Verification is reviewed by a person, not an automated system.",
        seller:
          "Same requirements as a buyer, since every seller account is a buyer account first. Your shop may also ask for category-specific documents (for example, a handling licence for regulated goods).",
        keywords: "kyc pan aadhaar documents verification gst company",
      },
      {
        code: "GS-03",
        q: "How do I become a seller?",
        buyer:
          "Apply to sell through a specific shop's storefront. Approval is scoped to that one shop — being approved to sell on one shop doesn't automatically let you sell on another.",
        seller:
          "You apply, the shop's admin reviews your request, and once approved you can start listing items on that storefront. You can apply to multiple shops independently.",
        keywords: "become seller upgrade apply shop tenant approval",
      },
    ],
  },
  {
    id: "money",
    label: "Deposits & Balance",
    items: [
      {
        code: "DB-01",
        q: "What is the security deposit (EMD) and why do I need one?",
        buyer:
          "To bid or make an offer on an item, you pledge 10% of the item's value upfront. It's a genuine commitment — it shows you're serious, and it's what stops people placing bids they never intend to honour.",
        seller:
          "Buyers only — sellers never pay a deposit to list or sell. This is what protects you from someone winning your item and then walking away.",
        keywords: "emd deposit security money pledge 10 percent",
      },
      {
        code: "DB-02",
        q: "How do I add money to my account balance?",
        buyer:
          "Transfer funds by bank transfer and upload the payment receipt. Once verified, the equivalent balance is credited to your account and can be used to bid anywhere on the platform.",
        seller:
          "Not applicable — sellers don't need a balance to list items. This only applies to the buyer side.",
        keywords: "add money deposit balance top up bank transfer pc points",
      },
      {
        code: "DB-03",
        q: "Do I get my deposit back if I don't win?",
        buyer:
          "Yes. As soon as you're outbid or don't win, your deposit is released back to your balance, ready to use on your next bid.",
        seller:
          "Not applicable to sellers, since you never post a deposit in the first place.",
        keywords: "refund deposit release lost auction balance",
      },
      {
        code: "DB-04",
        q: "Can my deposit be forfeited?",
        buyer:
          "Yes — if you win and then fail to pay, your deposit is forfeited in full. This is treated seriously and also affects your rating.",
        seller:
          "If a buyer defaults after winning your item, their forfeited deposit is generally passed to you as compensation for the failed sale, after the shop's and platform's small fee is taken out.",
        keywords: "forfeit default non payment penalty lost deposit",
      },
      {
        code: "DB-05",
        q: "Do I pay extra if my payment method charges a fee?",
        buyer:
          "Yes — if your chosen payment method (like a card) carries a processing charge, it's added on top of your deposit and shown clearly before you pay, so your full deposit still lands intact. Bank transfer or UPI usually carries little to no such charge, so it's often the cheaper, simpler option.",
        seller:
          "Not applicable to you directly, since you never pay a deposit — this only affects how a buyer funds their EMD.",
        keywords: "payment gateway charge fee card upi bank transfer extra cost",
      },
    ],
  },
  {
    id: "formats",
    label: "Sale Formats",
    items: [
      {
        code: "SF-01",
        q: "What's the difference between Buy-Now, Express, Easy, and Tender?",
        buyer:
          "Buy-Now: you make an offer and the seller picks who to sell to. Express: a fast auction that only starts once 3 buyers join, closing within the hour. Easy: a scheduled auction with a proper inspection window beforehand. Tender: a fully private, invite-only sale run directly by the seller.",
        seller:
          "The same four formats, from your side: Buy-Now lets you personally choose the best offer. Express gets you a fast sale once enough interest builds. Easy gives buyers time to inspect before bidding. Tender gives you complete control over who can even participate.",
        keywords: "buy now express easy tender auction formats types",
      },
      {
        code: "SF-02",
        q: "Can I inspect an item before bidding?",
        buyer:
          "Depends on the format. Easy Auctions give you a proper inspection window (the seller sets it, between 24 hours and 7 days) before bidding opens. Express Auctions move too fast for inspection — you're bidding on the listing photos and description alone.",
        seller:
          "For Easy Auctions, you choose how long buyers get to inspect before bidding starts (24 hours to 7 days). Express Auctions skip inspection entirely, in exchange for a much faster sale.",
        keywords: "inspection window view item before bidding easy express",
      },
      {
        code: "SF-03",
        q: "In Buy-Now, does the highest offer always win?",
        buyer:
          "Not necessarily. The seller can choose a lower offer from a buyer they trust more (based on your rating) over a higher offer from someone less reliable. If they do this, they're required to record why.",
        seller:
          "Yes — you're not locked into accepting the highest number. You can factor in a buyer's rating and reliability. If you pick someone other than the top offer, you'll need to state a reason, which is kept on record.",
        keywords: "highest offer buy now seller choice rating discretion",
      },
      {
        code: "SF-04",
        q: "If Express has no inspection, how do I know what I'm bidding on?",
        buyer:
          "Every Express listing includes a mandatory checklist the seller fills out declaring any known damage, missing parts, or non-working components — even though there's no inspection window. If that disclosure turns out to be false, it's treated as a genuine dispute, not just normal sight-unseen risk.",
        seller:
          "You're required to complete a defect-disclosure checklist before your Express listing goes live, covering anything you know to be wrong with the item. Answering it falsely can result in a dispute against you, even though buyers can't physically inspect beforehand.",
        keywords: "express defect disclosure checklist known damage sight unseen",
      },
    ],
  },
  {
    id: "winning",
    label: "Winning & Payment",
    items: [
      {
        code: "WP-01",
        q: "I won an auction — what happens next?",
        buyer:
          "If the final price is higher than your original deposit covered, you may need to top up your deposit first. Then you pay the seller directly (offline, bank transfer or agreed method) and arrange to collect or receive the item.",
        seller:
          "Once a winner is confirmed, you deal with the buyer directly for payment and handover — the platform doesn't hold or move the full sale amount, only the deposit.",
        keywords: "won auction winner next steps payment collect",
      },
      {
        code: "WP-02",
        q: "What happens if the winning bidder doesn't pay?",
        buyer:
          "If you win and don't complete payment within the required window, you forfeit your deposit and the win passes to the next-highest bidder, who then gets their own window to complete the purchase.",
        seller:
          "Non-paying winners are automatically replaced by the next-highest bidder, one step at a time. If every bidder in line fails to pay, the auction is cancelled and you'll need to relist — but you're never left chasing a non-paying buyer.",
        keywords: "non payment default winner fails pay next bidder cascade",
      },
      {
        code: "WP-03",
        q: "Can I lose my win to someone else?",
        buyer:
          "No — once you're confirmed as the winner and you pay on time, the sale is yours. You only lose a win if you fail to pay within the required window.",
        seller:
          "No — once a buyer completes payment on time, you can't reassign the sale to someone else.",
        keywords: "lose win reassign bumped replaced",
      },
    ],
  },
  {
    id: "ratings",
    label: "Ratings & Trust",
    items: [
      {
        code: "RT-01",
        q: "How does the star rating system work?",
        buyer:
          "You have your own buyer rating, separate from any seller rating. Everyone starts at 3 stars once their profile is complete. Paying promptly and completing deals cleanly raises it; defaulting or late payment lowers it.",
        seller:
          "You have your own seller rating, entirely separate from your buyer rating even if you do both. Accurate listings and fast handovers raise it; mismatched descriptions or delays lower it.",
        keywords: "star rating reputation score trust buyer seller",
      },
      {
        code: "RT-02",
        q: "Can my rating recover after a bad mark?",
        buyer:
          "Yes. If your rating drops significantly, you enter a recovery track — you're temporarily limited to smaller-value purchases, and a set number of clean transactions brings you back to a normal rating. Repeat issues mean more transactions are needed to recover.",
        seller:
          "Yes, sellers have the same kind of recovery path — a run of clean, accurate listings restores your standing over time.",
        keywords: "recover rating low star crawl back rehabilitation",
      },
      {
        code: "RT-03",
        q: "What happens if my rating gets very low?",
        buyer:
          "Very low-rated accounts are gradually shown less — fewer alerts, lower visibility — rather than being blocked outright. You can still use the platform, just without the same visibility, until your rating improves.",
        seller:
          "The same applies to your listings — a very low seller rating means your items stop being actively promoted or recommended, though they're still technically listed.",
        keywords: "shadow ban low rating suspended hidden visibility",
      },
    ],
  },
  {
    id: "settlement",
    label: "Settlement & Handover",
    items: [
      {
        code: "ST-01",
        q: "How do I actually pay the seller or receive payment?",
        buyer:
          "You pay the seller directly — offline, bank transfer or whatever method you agree — for 100% of the sale value. The platform only ever holds your deposit, never the full sale amount.",
        seller:
          "You're paid directly by the buyer, in full, outside the platform. Once you've received payment, you confirm it on the platform to move the deal toward final closure.",
        keywords: "payment settlement pay seller receive money offline",
      },
      {
        code: "ST-02",
        q: "What is a confirmation (NOC) and why do both sides need to file one?",
        buyer:
          "You confirm you've received the goods, and the seller confirms they've received your payment. A deal only fully closes once both of you have confirmed your own side — this protects you both.",
        seller:
          "You confirm you've received payment, and the buyer confirms they've received the goods. Both confirmations are required before the deal closes and any remaining deposit is released.",
        keywords: "noc confirmation receipt closing deal both sides",
      },
      {
        code: "ST-03",
        q: "What if the other party won't confirm?",
        buyer:
          "If the seller goes silent, you're sent a reminder, and if they still don't respond, an admin steps in — checking the facts directly rather than just guessing — to get your deal unstuck.",
        seller:
          "If a buyer goes silent on confirming or rating you, the same reminder-then-admin-review process applies from the other side, so you're not left waiting indefinitely either.",
        keywords: "stalling no response unresponsive stuck confirmation",
      },
      {
        code: "ST-04",
        q: "Do I get an invoice for the fees I've paid?",
        buyer:
          "Yes — once a deal closes, a proper invoice covering the transaction fee (plus applicable tax) is generated automatically and made available to you.",
        seller:
          "Yes — an invoice is generated for the shop's commission share, and you can access it against the transaction record.",
        keywords: "invoice gst tax receipt billing",
      },
      {
        code: "ST-05",
        q: "Why do I have to confirm something specific right before I pledge a deposit?",
        buyer:
          "That short acknowledgment spells out exactly what happens if you don't follow through on this specific bid — it's there so you always know the real consequence before committing, not buried in a document you accepted once at signup.",
        seller:
          "This doesn't apply to you directly, since you never pledge a deposit — but the same idea protects you too: every commitment on the platform is confirmed clearly, at the moment it's made.",
        keywords: "consent acknowledgment confirm before pledge terms",
      },
    ],
  },
  {
    id: "disputes",
    label: "Disputes",
    items: [
      {
        code: "DP-01",
        q: "What if the item doesn't match the listing?",
        buyer:
          "For scheduled auctions, you're expected to inspect beforehand, so ordinary condition complaints after winning aren't valid. But if there's genuine fraud or a material difference from what was described, you can file a dispute.",
        seller:
          "Buyers waive routine condition complaints once they've had an inspection opportunity — but a dispute can still be raised against you for genuine misrepresentation, so accurate listings matter.",
        keywords: "item mismatch condition dispute fraud misrepresentation",
      },
      {
        code: "DP-02",
        q: "How do I file a dispute?",
        buyer:
          "Raise it against the specific transaction, choose the category that fits (payment, condition, non-delivery, etc.), and submit any supporting evidence within the filing window.",
        seller:
          "Same process from your side — file against the transaction, select a category, and submit evidence. Both sides get an equal window to respond with their own evidence.",
        keywords: "file dispute complaint evidence claim",
      },
      {
        code: "DP-03",
        q: "Who decides the outcome of a dispute?",
        buyer:
          "Most disputes are reviewed by the shop's admin; disputes specifically about buyer conduct go to platform-level review. Every decision comes with a stated reason, and a shop-level decision can be appealed once.",
        seller:
          "Same structure applies to you — disputes about your conduct as a seller are typically reviewed by the shop's admin, with a documented reason and one appeal right if you disagree with the outcome.",
        keywords: "dispute resolution ruling admin decide appeal",
      },
    ],
  },
  {
    id: "listings",
    label: "Listings & Shipping",
    items: [
      {
        code: "LS-01",
        q: "Can items be grouped and browsed together?",
        buyer:
          "Yes — when several items share a common origin (say, a batch of vehicles from one site), they're tagged and shown together on one screen, even though each is still its own independent sale. You can bid on one, several, or all of them.",
        seller:
          "You can tag multiple related listings as a group so buyers see them together, while each item still sells completely independently — one winner per item, no dependency between them.",
        keywords: "related items group lot batch multiple",
      },
      {
        code: "LS-02",
        q: "What are the photo and video requirements for a listing?",
        buyer:
          "Every listing needs real photos of the actual item — never stock images — so what you see is genuinely what's for sale. Photos and video are automatically optimised for fast loading without losing important detail.",
        seller:
          "You need at least 5 real photos (up to 50), with one marked as the main photo. Video is optional, up to 2 minutes. Everything is compressed automatically after upload — you don't need to resize anything yourself.",
        keywords: "photos video upload listing requirements images",
      },
      {
        code: "LS-03",
        q: "Does the seller have to arrange shipping?",
        buyer:
          "No — shipping is always optional. You can always choose to collect the item yourself at no extra cost, even if the seller also offers shipping.",
        seller:
          "You can offer shipping if you want (at a fixed or distance-based cost), but it's never required — buyers can always self-collect instead.",
        keywords: "shipping delivery collect pickup cost",
      },
    ],
  },
  {
    id: "safeguards",
    label: "Platform Safeguards",
    items: [
      {
        code: "PS-01",
        q: "Is there a limit on how much a bid can jump?",
        buyer:
          "Yes — no bid can be more than 150% of the current highest bid. This catches typing mistakes (like an extra zero) before they cause real problems.",
        seller:
          "The same limit protects your auction from being disrupted by an obviously mistaken or malicious bid.",
        keywords: "bid limit ceiling typo mistake jacking",
      },
      {
        code: "PS-02",
        q: "How does the platform stop last-second sniping?",
        buyer:
          "If a bid lands in the final 10 minutes, the auction automatically extends by 2 more minutes, giving everyone a fair chance to respond rather than losing on a last-second snipe.",
        seller:
          "The same rule protects your sale from closing on an artificially timed last-second bid — it keeps extending as long as genuine bidding continues.",
        keywords: "sniping last second extension dynamic time",
      },
      {
        code: "PS-03",
        q: "How does the platform prevent fake or misleading photos?",
        buyer:
          "Photos are captured through the app itself at the moment of listing, with location and time data attached — not uploaded from an old gallery — making it much harder to pass off fake or unrelated images as the real item.",
        seller:
          "This same in-app capture requirement protects you too — it's clear evidence the photos are genuinely of your item, which helps if a buyer ever questions authenticity.",
        keywords: "fake photos fraud authenticity verification gps",
      },
    ],
  },
];

const ALL_ITEMS = CATEGORIES.flatMap((c) =>
  c.items.map((it) => ({ ...it, categoryId: c.id, categoryLabel: c.label }))
);

/* ------------------------------------------------------------------ */
/* Highlight helper                                                    */
/* ------------------------------------------------------------------ */
function highlight(text, term) {
  if (!term) return text;
  const idx = text.toLowerCase().indexOf(term.toLowerCase());
  if (idx === -1) return text;
  return (
    <>
      {text.slice(0, idx)}
      <mark style={{ background: T.amber, color: T.ink, padding: "0 2px", borderRadius: 2 }}>
        {text.slice(idx, idx + term.length)}
      </mark>
      {text.slice(idx + term.length)}
    </>
  );
}

/* ------------------------------------------------------------------ */
/* Entry card — the "manifest tag" signature element                   */
/* ------------------------------------------------------------------ */
function EntryCard({ item, term, isOpen, onToggle }) {
  return (
    <div
      style={{
        background: T.card,
        border: `1px solid ${T.line}`,
        borderRadius: 10,
        overflow: "hidden",
        transition: "box-shadow 180ms ease, border-color 180ms ease",
        boxShadow: isOpen ? "0 6px 20px rgba(28,31,38,0.08)" : "none",
      }}
    >
      <button
        onClick={onToggle}
        aria-expanded={isOpen}
        style={{
          width: "100%",
          display: "flex",
          alignItems: "center",
          gap: 14,
          padding: "16px 18px",
          background: "transparent",
          border: "none",
          cursor: "pointer",
          textAlign: "left",
        }}
        className="faq-trigger"
      >
        <span
          style={{
            fontFamily: "'IBM Plex Mono', monospace",
            fontSize: 12,
            letterSpacing: "0.05em",
            color: T.inkSoft,
            border: `1px solid ${T.line}`,
            borderRadius: 6,
            padding: "3px 7px",
            flexShrink: 0,
          }}
        >
          {item.code}
        </span>
        <span
          style={{
            fontFamily: "'Archivo', sans-serif",
            fontWeight: 600,
            fontSize: 16,
            color: T.ink,
            flex: 1,
          }}
        >
          {highlight(item.q, term)}
        </span>
        <ChevronDown
          size={18}
          color={T.inkSoft}
          style={{
            flexShrink: 0,
            transform: isOpen ? "rotate(180deg)" : "rotate(0deg)",
            transition: "transform 180ms ease",
          }}
        />
      </button>

      <div
        style={{
          display: "grid",
          gridTemplateRows: isOpen ? "1fr" : "0fr",
          transition: "grid-template-rows 220ms ease",
        }}
      >
        <div style={{ overflow: "hidden" }}>
          <div
            className="faq-split"
            style={{
              borderTop: `1px solid ${T.line}`,
              display: "grid",
              gridTemplateColumns: "1fr 1fr",
            }}
          >
            <div style={{ padding: "16px 18px", position: "relative" }}>
              <div
                style={{
                  fontFamily: "'IBM Plex Mono', monospace",
                  fontSize: 11,
                  letterSpacing: "0.08em",
                  color: T.rust,
                  fontWeight: 700,
                  marginBottom: 8,
                }}
              >
                FOR BUYERS
              </div>
              <p style={{ fontSize: 14.5, lineHeight: 1.6, color: T.ink, margin: 0 }}>
                {highlight(item.buyer, term)}
              </p>
            </div>
            <div
              style={{
                padding: "16px 18px",
                borderLeft: `1px dashed ${T.line}`,
                background: T.tealSoft + "55",
              }}
            >
              <div
                style={{
                  fontFamily: "'IBM Plex Mono', monospace",
                  fontSize: 11,
                  letterSpacing: "0.08em",
                  color: T.teal,
                  fontWeight: 700,
                  marginBottom: 8,
                }}
              >
                FOR SELLERS
              </div>
              <p style={{ fontSize: 14.5, lineHeight: 1.6, color: T.ink, margin: 0 }}>
                {highlight(item.seller, term)}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Main page                                                            */
/* ------------------------------------------------------------------ */
export default function EBidHubFAQ() {
  const [query, setQuery] = useState("");
  const [activeCategory, setActiveCategory] = useState("all");
  const [openCodes, setOpenCodes] = useState(() => new Set());
  const inputRef = useRef(null);

  const results = useMemo(() => {
    const term = query.trim().toLowerCase();
    return ALL_ITEMS.filter((it) => {
      if (activeCategory !== "all" && it.categoryId !== activeCategory) return false;
      if (!term) return true;
      const haystack = `${it.q} ${it.buyer} ${it.seller} ${item_keywords(it)}`.toLowerCase();
      return haystack.includes(term);
    });
  }, [query, activeCategory]);

  function item_keywords(it) {
    return it.keywords || "";
  }

  // auto-expand matches while searching
  useEffect(() => {
    if (query.trim()) {
      setOpenCodes(new Set(results.map((r) => r.code)));
    }
  }, [query]); // eslint-disable-line react-hooks/exhaustive-deps

  const grouped = useMemo(() => {
    const map = new Map();
    results.forEach((it) => {
      if (!map.has(it.categoryId)) map.set(it.categoryId, { label: it.categoryLabel, items: [] });
      map.get(it.categoryId).items.push(it);
    });
    return Array.from(map.entries());
  }, [results]);

  function toggle(code) {
    setOpenCodes((prev) => {
      const next = new Set(prev);
      next.has(code) ? next.delete(code) : next.add(code);
      return next;
    });
  }

  return (
    <div style={{ minHeight: "100vh", background: T.paper, fontFamily: "'Inter', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;700&display=swap');
        * { box-sizing: border-box; }
        .faq-trigger:focus-visible { outline: 2px solid ${T.rust}; outline-offset: -2px; }
        .chip:focus-visible { outline: 2px solid ${T.ink}; outline-offset: 2px; }
        input:focus-visible { outline: 2px solid ${T.rust}; }
        @media (max-width: 720px) {
          .faq-split { grid-template-columns: 1fr !important; }
          .faq-split > div:last-child { border-left: none !important; border-top: 1px dashed ${T.line}; }
        }
        @media (prefers-reduced-motion: reduce) {
          * { transition: none !important; }
        }
      `}</style>

      {/* Masthead */}
      <header style={{ borderBottom: `1px solid ${T.line}`, background: T.card }}>
        <div style={{ maxWidth: 880, margin: "0 auto", padding: "36px 20px 28px" }}>
          <div
            style={{
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 11,
              letterSpacing: "0.12em",
              color: T.rust,
              fontWeight: 700,
              marginBottom: 10,
            }}
          >
            EBID HUB — HELP CENTER
          </div>
          <h1
            style={{
              fontFamily: "'Archivo', sans-serif",
              fontWeight: 800,
              fontSize: "clamp(28px, 4vw, 38px)",
              color: T.ink,
              margin: "0 0 8px",
              letterSpacing: "-0.01em",
            }}
          >
            Answers for buyers and sellers
          </h1>
          <p style={{ fontSize: 15.5, color: T.inkSoft, margin: 0, maxWidth: 560, lineHeight: 1.6 }}>
            Every topic is explained twice — once from the buyer's side, once from the seller's — so you
            only read the half that applies to you.
          </p>
        </div>
      </header>

      {/* Search + filters, sticky */}
      <div
        style={{
          position: "sticky",
          top: 0,
          zIndex: 10,
          background: T.paper + "F5",
          backdropFilter: "blur(6px)",
          borderBottom: `1px solid ${T.line}`,
        }}
      >
        <div style={{ maxWidth: 880, margin: "0 auto", padding: "14px 20px" }}>
          <div style={{ position: "relative", marginBottom: 12 }}>
            <Search
              size={17}
              color={T.inkSoft}
              style={{ position: "absolute", left: 14, top: "50%", transform: "translateY(-50%)" }}
            />
            <input
              ref={inputRef}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search topics — deposits, ratings, disputes, shipping..."
              style={{
                width: "100%",
                padding: "12px 40px",
                borderRadius: 9,
                border: `1px solid ${T.line}`,
                background: T.card,
                fontSize: 14.5,
                color: T.ink,
                fontFamily: "'Inter', sans-serif",
              }}
            />
            {query && (
              <button
                onClick={() => setQuery("")}
                aria-label="Clear search"
                style={{
                  position: "absolute",
                  right: 10,
                  top: "50%",
                  transform: "translateY(-50%)",
                  background: "none",
                  border: "none",
                  cursor: "pointer",
                  padding: 4,
                  display: "flex",
                }}
              >
                <X size={16} color={T.inkSoft} />
              </button>
            )}
          </div>

          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <Chip
              active={activeCategory === "all"}
              onClick={() => setActiveCategory("all")}
              label="All topics"
            />
            {CATEGORIES.map((c) => (
              <Chip
                key={c.id}
                active={activeCategory === c.id}
                onClick={() => setActiveCategory(c.id)}
                label={c.label}
              />
            ))}
          </div>
        </div>
      </div>

      {/* Results */}
      <main style={{ maxWidth: 880, margin: "0 auto", padding: "24px 20px 80px" }}>
        <div
          style={{
            fontFamily: "'IBM Plex Mono', monospace",
            fontSize: 12,
            color: T.inkSoft,
            marginBottom: 16,
          }}
        >
          {results.length} {results.length === 1 ? "entry" : "entries"} indexed
        </div>

        {grouped.length === 0 && (
          <div
            style={{
              textAlign: "center",
              padding: "60px 20px",
              color: T.inkSoft,
              fontSize: 15,
            }}
          >
            No topics match "{query}". Try a different word, like "deposit" or "rating."
          </div>
        )}

        {grouped.map(([catId, group]) => (
          <section key={catId} style={{ marginBottom: 32 }}>
            <h2
              style={{
                fontFamily: "'Archivo', sans-serif",
                fontWeight: 700,
                fontSize: 13,
                letterSpacing: "0.08em",
                textTransform: "uppercase",
                color: T.ink,
                margin: "0 0 12px",
                display: "flex",
                alignItems: "center",
                gap: 8,
              }}
            >
              <Circle size={7} fill={T.rust} color={T.rust} />
              {group.label}
            </h2>
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {group.items.map((item) => (
                <EntryCard
                  key={item.code}
                  item={item}
                  term={query.trim()}
                  isOpen={openCodes.has(item.code)}
                  onToggle={() => toggle(item.code)}
                />
              ))}
            </div>
          </section>
        ))}
      </main>

      <footer style={{ borderTop: `1px solid ${T.line}`, padding: "24px 20px", textAlign: "center" }}>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: 0 }}>
          Can't find what you're looking for? Contact your shop's support for anything specific to your
          account or an active transaction.
        </p>
      </footer>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Filter chip                                                         */
/* ------------------------------------------------------------------ */
function Chip({ active, onClick, label }) {
  return (
    <button
      onClick={onClick}
      className="chip"
      style={{
        padding: "7px 14px",
        borderRadius: 999,
        border: `1px solid ${active ? T.ink : T.line}`,
        background: active ? T.ink : T.card,
        color: active ? T.paper : T.inkSoft,
        fontSize: 13,
        fontWeight: 500,
        fontFamily: "'Inter', sans-serif",
        cursor: "pointer",
        whiteSpace: "nowrap",
        transition: "all 150ms ease",
      }}
    >
      {label}
    </button>
  );
}
