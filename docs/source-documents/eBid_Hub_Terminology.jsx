import React, { useState, useMemo, useRef } from "react";
import { Search, X, BookOpen } from "lucide-react";

/* ------------------------------------------------------------------ */
/* Design tokens — shared with FAQ / Trust & Support / Security pages  */
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
/* Glossary content — plain language, no internal rule references      */
/* ------------------------------------------------------------------ */
const TERMS = [
  { term: "Baton-Pass (Cascading Default)", def: "What happens when a winning bidder doesn't pay: the win passes to the next-highest bidder at their own price, within their own time window. This can happen up to three times before an auction is cancelled outright.", related: ["Forfeiture", "H1 / H2 / H3"] },
  { term: "Bid Ceiling (150% Rule)", def: "A safety limit stopping any single bid from jumping more than 150% above the current price — catches typing mistakes and blocks deliberate price manipulation.", related: [] },
  { term: "Buy-Now", def: "A sale format where buyers make offers and the seller personally chooses who to sell to, considering price and the buyer's rating — not necessarily the highest offer.", related: ["Express Auction", "Easy Auction"] },
  { term: "CAT-LOC-VAL", def: "Short for Category-Location-Value — the set of preferences (what you're interested in, where, and at what price range) that personalises which listings and alerts you see.", related: ["Live Ticker"] },
  { term: "Certified by Seller (CBS)", def: "A listing where the seller has taken their own photos, by any method. Real, unedited photos of the actual item are required — stock or generated images are never allowed. Contrast with Verified Listing.", related: ["Verified Listing", "Standing Review"] },
  { term: "Crawl-Back", def: "A recovery path for a buyer whose rating has dropped significantly — they're temporarily limited to smaller purchases until a set number of clean transactions restores their standing.", related: ["Star Rating", "Shadow Banning"] },
  { term: "Dispute Resolution", def: "The formal process for resolving a disagreement about a specific transaction — filed by a buyer or seller, reviewed with evidence from both sides, and decided with a stated reason.", related: ["Grievance Redressal", "Standing Review"] },
  { term: "Dynamic Time (Anti-Sniping)", def: "A rule that extends an auction by a short period whenever a bid lands in the closing minutes, so a last-second bid can't unfairly end the auction before others can respond.", related: [] },
  { term: "Easy Auction", def: "A scheduled auction with a real inspection window (set by the seller, 24 hours to 7 days) before bidding opens. The seller can choose whether results close instantly or need their approval.", related: ["Buy-Now", "Express Auction"] },
  { term: "EMD (Security Deposit)", def: "The refundable deposit — 10% of the item's value — a buyer must pledge before placing a bid or offer. It's returned automatically unless you win and then fail to pay.", related: ["Forfeiture", "Money Points (PC)"] },
  { term: "EV (Expected Value)", def: "The price a seller states as their target/asking figure in a Buy-Now listing. Buyers may offer above or below it — it's a reference point, not a hard limit.", related: ["Reserve Value (RV)", "Buy-Now"] },
  { term: "Express Auction", def: "The fastest sale format — no inspection window at all. The auction only starts once exactly 3 buyers have pledged a deposit, then runs for about an hour.", related: ["Easy Auction", "Buy-Now"] },
  { term: "Forfeiture", def: "The loss of a buyer's deposit after they win an auction and then fail to complete payment. In most cases the money goes to the seller as compensation; in a full cascading default, it doesn't.", related: ["EMD (Security Deposit)", "Baton-Pass (Cascading Default)"] },
  { term: "Grievance Redressal", def: "The process for raising a broader concern about the platform itself — separate from a dispute about one specific transaction.", related: ["Dispute Resolution"] },
  { term: "H1 / H2 / H3", def: "Shorthand for the highest, second-highest, and third-highest bidder in an auction at the moment it closes. If H1 defaults, the win passes to H2, then H3.", related: ["Baton-Pass (Cascading Default)"] },
  { term: "KYC", def: "Know Your Customer — the identity verification every user completes before transacting, using documents like PAN and Aadhaar (for individuals) or company registration details (for businesses).", related: [] },
  { term: "Live Ticker", def: "The real-time scrolling feed showing your account balance, the status of your active bids, and new listings matching your interests.", related: ["CAT-LOC-VAL"] },
  { term: "Money Points (PC)", def: "The platform's internal representation of deposited funds — always equal in value to what you deposited (1:1), held as a distinct amount tied to each specific bid, not one shared spendable balance.", related: ["EMD (Security Deposit)"] },
  { term: "NOC (No Objection Certificate)", def: "A digital confirmation both sides must submit before a deal is considered closed — the seller confirms they were paid, the buyer confirms they received the goods.", related: ["Settlement"] },
  { term: "Payment Gateway Charge", def: "A processing fee some payment methods (like cards) carry, added on top of your deposit so the full deposit amount still reaches escrow untouched.", related: ["EMD (Security Deposit)"] },
  { term: "Related Auctions", def: "Multiple listings that share a common origin (e.g., items from the same site) shown together on one screen for convenience — while each one remains a fully separate, independent sale.", related: [] },
  { term: "Reserve Value (RV)", def: "The confidential floor price a seller sets for an Easy or Express Auction. The item won't sell for less than this, and it's what a buyer's deposit is calculated against.", related: ["EV (Expected Value)", "Easy Auction"] },
  { term: "SaaS Admin (Super Admin)", def: "The platform's top-level administrator, who oversees the whole system but never personally buys, sells, or has any visibility into a live auction while it's happening.", related: ["Tenant Admin"] },
  { term: "Seller Rating (sellerStarRating)", def: "Your reputation score specifically as a seller — separate from your buyer rating, even if you do both.", related: ["Star Rating"] },
  { term: "Settlement", def: "The final stage of a completed sale: the buyer pays the seller directly, both confirm their side (NOC), both rate each other, and any remaining deposit is refunded.", related: ["NOC (No Objection Certificate)"] },
  { term: "Shadow Banning", def: "A gradual reduction in visibility (fewer alerts, less promotion) for an account with a persistently poor rating — not a full block, just reduced platform support.", related: ["Crawl-Back", "Star Rating"] },
  { term: "Standing Review", def: "A periodic, big-picture check on a seller's overall conduct — triggered either once a year, or sooner if enough complaints build up — that looks at the pattern, not just one incident.", related: ["Certified by Seller (CBS)", "Dispute Resolution"] },
  { term: "Star Rating", def: "Your reputation score, starting at 3 out of 5, that rises with clean, on-time transactions and falls with defaults or disputes. Buyers and sellers each have their own, separate score.", related: ["Seller Rating (sellerStarRating)", "Crawl-Back"] },
  { term: "Tenant / Shop", def: "An individual storefront operating on eBid Hub under its own branding — could be a bank, an institution, or the platform's own general marketplace.", related: ["Tenant Admin"] },
  { term: "Tenant Admin", def: "The person a shop has authorised to approve listings, set local terms, and manage sellers on that specific storefront.", related: ["Tenant / Shop", "SaaS Admin (Super Admin)"] },
  { term: "Tender Auction", def: "A fully private, invite-only sale format where the seller personally controls who can participate and on what terms — available only through the platform's own operated shop.", related: ["Buy-Now"] },
  { term: "Verified Listing", def: "A listing where eBid Hub's own inspection team has visited the item and taken the photos themselves. Contrast with Certified by Seller.", related: ["Certified by Seller (CBS)"] },
];

const ALPHABET = [...new Set(TERMS.map(t => t.term[0].toUpperCase()))].sort();

/* ------------------------------------------------------------------ */
/* Highlight helper                                                    */
/* ------------------------------------------------------------------ */
function highlight(text, q) {
  if (!q) return text;
  const idx = text.toLowerCase().indexOf(q.toLowerCase());
  if (idx === -1) return text;
  return (
    <>
      {text.slice(0, idx)}
      <mark style={{ background: T.amber, color: T.ink, padding: "0 2px", borderRadius: 2 }}>
        {text.slice(idx, idx + q.length)}
      </mark>
      {text.slice(idx + q.length)}
    </>
  );
}

/* ------------------------------------------------------------------ */
/* Main page                                                            */
/* ------------------------------------------------------------------ */
export default function Terminology() {
  const [query, setQuery] = useState("");
  const inputRef = useRef(null);
  const letterRefs = useRef({});

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return TERMS;
    return TERMS.filter(
      (t) => t.term.toLowerCase().includes(q) || t.def.toLowerCase().includes(q)
    );
  }, [query]);

  const grouped = useMemo(() => {
    const map = new Map();
    filtered.forEach((t) => {
      const letter = t.term[0].toUpperCase();
      if (!map.has(letter)) map.set(letter, []);
      map.get(letter).push(t);
    });
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [filtered]);

  const availableLetters = new Set(grouped.map(([l]) => l));

  function jumpTo(letter) {
    setQuery("");
    requestAnimationFrame(() => {
      letterRefs.current[letter]?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  return (
    <div style={{ minHeight: "100vh", background: T.paper, fontFamily: "'Inter', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;700&display=swap');
        * { box-sizing: border-box; }
        .term-card { transition: box-shadow 160ms ease, border-color 160ms ease; }
        .term-card:hover { box-shadow: 0 6px 18px rgba(28,31,38,0.08); border-color: ${T.rust}; }
        .letter-btn { transition: all 150ms ease; }
        .letter-btn:hover { background: ${T.rust} !important; color: #fff !important; }
        input:focus-visible, .letter-btn:focus-visible { outline: 2px solid ${T.rust}; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; scroll-behavior: auto !important; } }
      `}</style>

      {/* Masthead */}
      <header style={{ borderBottom: `1px solid ${T.line}`, background: T.card }}>
        <div style={{ maxWidth: 880, margin: "0 auto", padding: "40px 20px 28px" }}>
          <div
            style={{
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 11,
              letterSpacing: "0.12em",
              color: T.rust,
              fontWeight: 700,
              marginBottom: 10,
              display: "flex",
              alignItems: "center",
              gap: 6,
            }}
          >
            <BookOpen size={13} /> EBID HUB — TRUST &amp; SUPPORT
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
            Terminology
          </h1>
          <p style={{ fontSize: 15.5, color: T.inkSoft, margin: 0, maxWidth: 560, lineHeight: 1.6 }}>
            Plain-language definitions for every platform-specific term you'll come across on eBid Hub.
          </p>
        </div>
      </header>

      {/* Search + A-Z nav, sticky */}
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
              placeholder="Search a term — EMD, rating, verified, forfeiture..."
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

          <div style={{ display: "flex", gap: 5, flexWrap: "wrap" }}>
            {ALPHABET.map((letter) => (
              <button
                key={letter}
                className="letter-btn"
                onClick={() => jumpTo(letter)}
                style={{
                  width: 28,
                  height: 28,
                  borderRadius: 6,
                  border: `1px solid ${T.line}`,
                  background: T.card,
                  color: T.ink,
                  fontFamily: "'IBM Plex Mono', monospace",
                  fontSize: 12.5,
                  fontWeight: 700,
                  cursor: "pointer",
                }}
              >
                {letter}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Glossary */}
      <main style={{ maxWidth: 880, margin: "0 auto", padding: "24px 20px 80px" }}>
        <div
          style={{
            fontFamily: "'IBM Plex Mono', monospace",
            fontSize: 12,
            color: T.inkSoft,
            marginBottom: 16,
          }}
        >
          {filtered.length} {filtered.length === 1 ? "term" : "terms"}
        </div>

        {grouped.length === 0 && (
          <div style={{ textAlign: "center", padding: "60px 20px", color: T.inkSoft, fontSize: 15 }}>
            No terms match "{query}".
          </div>
        )}

        {grouped.map(([letter, terms]) => (
          <section
            key={letter}
            ref={(el) => (letterRefs.current[letter] = el)}
            style={{ marginBottom: 30, scrollMarginTop: 140 }}
          >
            <div
              style={{
                fontFamily: "'Archivo', sans-serif",
                fontWeight: 800,
                fontSize: 22,
                color: T.rust,
                marginBottom: 12,
                paddingBottom: 6,
                borderBottom: `2px solid ${T.rustSoft}`,
              }}
            >
              {letter}
            </div>
            <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
              {terms.map((t) => (
                <div
                  key={t.term}
                  className="term-card"
                  style={{
                    background: T.card,
                    border: `1px solid ${T.line}`,
                    borderRadius: 10,
                    padding: "16px 18px",
                  }}
                >
                  <h3
                    style={{
                      fontFamily: "'Archivo', sans-serif",
                      fontWeight: 700,
                      fontSize: 15.5,
                      color: T.ink,
                      margin: "0 0 6px",
                    }}
                  >
                    {highlight(t.term, query)}
                  </h3>
                  <p style={{ fontSize: 13.5, lineHeight: 1.55, color: T.ink, margin: t.related.length ? "0 0 8px" : 0 }}>
                    {highlight(t.def, query)}
                  </p>
                  {t.related.length > 0 && (
                    <div style={{ display: "flex", gap: 6, flexWrap: "wrap", alignItems: "center" }}>
                      <span
                        style={{
                          fontFamily: "'IBM Plex Mono', monospace",
                          fontSize: 10.5,
                          letterSpacing: "0.05em",
                          color: T.teal,
                          fontWeight: 700,
                        }}
                      >
                        SEE ALSO:
                      </span>
                      {t.related.map((r) => (
                        <span
                          key={r}
                          style={{
                            fontSize: 12,
                            color: T.teal,
                            background: T.tealSoft,
                            padding: "2px 8px",
                            borderRadius: 999,
                          }}
                        >
                          {r}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </section>
        ))}
      </main>

      <footer style={{ borderTop: `1px solid ${T.line}`, padding: "24px 20px", textAlign: "center" }}>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: 0 }}>
          Still not sure about a term? Check our FAQ, or reach out through Contact Us.
        </p>
      </footer>
    </div>
  );
}
