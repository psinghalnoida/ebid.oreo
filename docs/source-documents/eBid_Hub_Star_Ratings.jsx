import React, { useState } from "react";
import {
  Star, TrendingUp, TrendingDown, ShieldQuestion, Unlock, Lock,
  ArrowUpCircle, ArrowDownCircle, MinusCircle, ChevronDown, ScrollText, Gavel,
} from "lucide-react";

/* ------------------------------------------------------------------ */
/* Design tokens — shared across the Trust & Support page family       */
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
  green: "#2E7D4F",
  greenSoft: "#E3F0E7",
  red: "#B03A2E",
  redSoft: "#F5E4E1",
};

/* ------------------------------------------------------------------ */
/* Content — event tables (restored, real point values)                */
/* ------------------------------------------------------------------ */
const BUYER_SMALL = [
  ["High Participation", "5 or more bids placed in a 30-day period.", "+0.1★"],
  ["Prompt Query Response", "You respond promptly to a seller's questions during a live deal.", "+0.1★"],
  ["Prompt NOC Confirmation", "You confirm goods-received quickly, not just eventually.", "+0.1★"],
  ["Prompt Rating Submission", "You rate the seller promptly once a deal is done.", "+0.1★"],
  ["Successful Collection", "Goods transfer is confirmed cleanly.", "+0.2★"],
  ["Clean Inspection", "No dispute raised after an Easy Auction win.", "+0.3★"],
];
const BUYER_SMALL_FALL = [
  ["Weak Withdrawal Pattern", "Repeatedly withdrawing offers with thin, unconvincing reasons.", "−0.2★"],
];
const BUYER_MEDIUM = [
  ["Early Settlement", "You pay the balance within 48 hours of winning.", "+0.5★"],
  ["Sustained Clean Streak", "10 consecutive dispute-free transactions in a row.", "+0.6★"],
];
const BUYER_MEDIUM_FALL = [
  ["Late Payment", "Balance paid more than 7 days after winning.", "−0.5★"],
  ["Stalling Pattern", "Reaching the 5th instance of a forced-neutral rating for non-response.", "−0.5★"],
  ["Baseless Dispute Pattern", "A repeated pattern of filing disputes that don't hold up.", "−0.6★"],
];
const BUYER_LARGE_FALL = [
  ["Frivolous Dispute", "A baseless condition complaint raised after winning.", "−1.5★"],
  ["1st / 2nd / 3rd Default", "Failing to pay, collect, or behaving disruptively after winning — escalates each time.", "−1.0★ / −1.5★ / −2.0★ or floor"],
  ["Harassment at Handover", "Disruptive or abusive conduct during collection.", "−1.5★"],
  ["Confirmed Fishing Pattern", "Repeatedly unlocking a seller's details, then abandoning the deal.", "−1.5★"],
  ["Illegitimate Chargeback", "Filing a chargeback against an already-approved, legitimate forfeiture.", "−2.0★"],
  ["Confirmed KYC Fraud", "Deliberately false identity information.", "Reset to 1★"],
];

const SELLER_SMALL = [
  ["Prompt NOC Confirmation", "Fund-clearance confirmed quickly, not just eventually.", "+0.1★"],
  ["Prompt Rating Submission", "You rate the buyer promptly once a deal is done.", "+0.1★"],
  ["Shipping As Promised", "Delivered exactly at the cost/timeline stated.", "+0.1★"],
  ["Detailed Documentation", "Listing includes video, photos, and location/map data.", "+0.2★"],
  ["Rapid Handover", "Delivery/NOC confirmed within 24 hours of payment.", "+0.3★"],
];
const SELLER_MEDIUM = [
  ["Accurate Description", "Buyers consistently rate your listing accuracy highly.", "+0.4★"],
  ["Sustained Clean Streak", "10 consecutive transactions with zero disputes or rejections.", "+0.6★"],
];
const SELLER_MEDIUM_FALL = [
  ["Delayed Collection", "You're unreachable or unavailable at handover.", "−0.5★"],
  ["Stalling Pattern", "Reaching the 5th instance of a forced-neutral rating for non-response.", "−0.5★"],
  ["Baseless Rejection Pattern", "A repeated pattern of rejecting Easy Auction results without valid reason.", "−0.6★"],
];
const SELLER_LARGE_FALL = [
  ["Unprofessional Conduct", "A verified complaint about your conduct during a deal.", "−1.0★"],
  ["Data Mismatch", "A significant discrepancy between the listing and the real item.", "−1.5★"],
  ["Dishonest Defect Disclosure", "A false Express Auction defect checklist, confirmed via dispute.", "−1.5★"],
  ["Confirmed Off-Platform Solicitation", "Seeking to complete a deal outside the platform to avoid fees.", "−1.5★"],
  ["Confirmed CBS Violation", "Stock or fake photos used, beyond the warning stage.", "−2.0★"],
  ["Confirmed Fraud / KYC Fraud", "Bid manipulation, deliberate misrepresentation, or false identity information.", "Reset to 1★"],
];

// Legacy names kept for compatibility with existing render calls below
const BUYER_RISE = [...BUYER_SMALL, ...BUYER_MEDIUM];
const BUYER_FALL = [...BUYER_SMALL_FALL, ...BUYER_MEDIUM_FALL, ...BUYER_LARGE_FALL];
const SELLER_RISE = [...SELLER_SMALL, ...SELLER_MEDIUM];
const SELLER_FALL = [...SELLER_MEDIUM_FALL, ...SELLER_LARGE_FALL];

const SAMPLE_LEDGER = [
  { date: "12 Jun", event: "Early Settlement", detail: "Paid balance in 31 hours", change: "+0.5★", total: "3.5★", tone: "up" },
  { date: "28 Jun", event: "Successful Collection", detail: "Handover confirmed, no issues", change: "+0.2★", total: "3.7★", tone: "up" },
  { date: "14 Jul", event: "Late Payment", detail: "Balance paid on day 9", change: "−1.0★", total: "2.7★", tone: "down" },
  { date: "02 Aug", event: "Clean Inspection", detail: "Easy Auction win, no dispute", change: "+0.3★", total: "3.0★", tone: "up" },
];

/* ------------------------------------------------------------------ */
/* Small building blocks                                               */
/* ------------------------------------------------------------------ */
function SectionLabel({ children }) {
  return (
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
      {children}
    </div>
  );
}

function EventTable({ rows, tone }) {
  const toneColor = tone === "up" ? T.green : T.red;
  const toneSoft = tone === "up" ? T.greenSoft : T.redSoft;
  const Icon = tone === "up" ? ArrowUpCircle : ArrowDownCircle;
  return (
    <div style={{ border: `1px solid ${T.line}`, borderRadius: 10, overflow: "hidden" }}>
      {rows.map((r, i) => (
        <div
          key={i}
          style={{
            display: "grid",
            gridTemplateColumns: "1fr 3fr auto",
            gap: 12,
            alignItems: "center",
            padding: "12px 14px",
            background: i % 2 === 1 ? T.paper : T.card,
            borderBottom: i < rows.length - 1 ? `1px solid ${T.line}` : "none",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <Icon size={15} color={toneColor} style={{ flexShrink: 0 }} />
            <span style={{ fontSize: 13.5, fontWeight: 700, color: T.ink }}>{r[0]}</span>
          </div>
          <span style={{ fontSize: 12.5, color: T.inkSoft, lineHeight: 1.4 }}>{r[1]}</span>
          <span
            style={{
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 12.5,
              fontWeight: 700,
              color: toneColor,
              background: toneSoft,
              padding: "3px 9px",
              borderRadius: 999,
              whiteSpace: "nowrap",
              textAlign: "center",
            }}
          >
            {r[2]}
          </span>
        </div>
      ))}
    </div>
  );
}

function Accordion({ title, icon: Icon, children, defaultOpen }) {
  const [open, setOpen] = useState(!!defaultOpen);
  return (
    <div style={{ border: `1px solid ${T.line}`, borderRadius: 10, background: T.card, overflow: "hidden" }}>
      <button
        onClick={() => setOpen((o) => !o)}
        style={{
          width: "100%",
          display: "flex",
          alignItems: "center",
          gap: 12,
          padding: "14px 16px",
          background: "transparent",
          border: "none",
          cursor: "pointer",
          textAlign: "left",
        }}
      >
        <Icon size={18} color={T.rust} style={{ flexShrink: 0 }} />
        <span style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 15, color: T.ink, flex: 1 }}>
          {title}
        </span>
        <ChevronDown
          size={16}
          color={T.inkSoft}
          style={{ transform: open ? "rotate(180deg)" : "rotate(0)", transition: "transform 160ms ease" }}
        />
      </button>
      <div
        style={{
          display: "grid",
          gridTemplateRows: open ? "1fr" : "0fr",
          transition: "grid-template-rows 200ms ease",
        }}
      >
        <div style={{ overflow: "hidden" }}>
          <div style={{ padding: "0 16px 16px", borderTop: `1px solid ${T.line}` }}>{children}</div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Main page                                                            */
/* ------------------------------------------------------------------ */
export default function StarRatings() {
  return (
    <div style={{ minHeight: "100vh", background: T.paper, fontFamily: "'Inter', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;700&display=swap');
        * { box-sizing: border-box; }
        @media (max-width: 720px) { .two-col { grid-template-columns: 1fr !important; } }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
      `}</style>

      {/* Masthead */}
      <header style={{ borderBottom: `1px solid ${T.line}`, background: T.card }}>
        <div style={{ maxWidth: 940, margin: "0 auto", padding: "40px 20px 28px" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 6, marginBottom: 10 }}>
            <Star size={13} color={T.rust} fill={T.rust} />
            <span style={{ fontFamily: "'IBM Plex Mono', monospace", fontSize: 11, letterSpacing: "0.12em", color: T.rust, fontWeight: 700 }}>
              EBID HUB — TRUST &amp; SUPPORT
            </span>
          </div>
          <h1 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 800, fontSize: "clamp(28px, 4vw, 38px)", color: T.ink, margin: "0 0 8px", letterSpacing: "-0.01em" }}>
            Star Ratings
          </h1>
          <p style={{ fontSize: 15.5, color: T.inkSoft, margin: 0, maxWidth: 620, lineHeight: 1.6 }}>
            How your reputation is built, what raises and lowers it, and what to do if it falls.
          </p>
        </div>
      </header>

      <main style={{ maxWidth: 940, margin: "0 auto", padding: "36px 20px 80px" }}>
        {/* Why ratings exist */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>WHY THIS EXISTS</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 10px" }}>
            A trust signal between strangers
          </h2>
          <p style={{ fontSize: 14, lineHeight: 1.65, color: T.ink, maxWidth: 700 }}>
            Buyers and sellers on eBid Hub often transact with someone they've never met, sometimes without ever
            seeing the item in person. Your star rating is how the platform — and everyone you deal with — knows
            you've genuinely honoured your commitments before. It rewards good behaviour with real, practical
            benefits, and it's the mechanism that lets us apply lighter rules to trustworthy accounts and tighter
            ones to risky accounts, rather than treating everyone the same.
          </p>
        </section>

        {/* Two independent scores */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>TWO SEPARATE SCORES</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 10px" }}>
            Your buyer rating and seller rating never mix
          </h2>
          <p style={{ fontSize: 14, lineHeight: 1.65, color: T.ink, marginBottom: 16, maxWidth: 700 }}>
            If you both buy and sell, you have two entirely independent scores. A slow payment as a buyer never
            touches your seller rating, and an inaccurate listing as a seller never touches your buyer rating.
            Both start at <strong>3.0 out of 5.0</strong> once your profile is complete, and neither moves just
            because you've been inactive — only real behaviour affects the number.
          </p>
          <div className="two-col" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
            <div style={{ background: T.card, border: `1px solid ${T.line}`, borderRadius: 10, padding: 16 }}>
              <div style={{ fontFamily: "'IBM Plex Mono', monospace", fontSize: 11, color: T.rust, fontWeight: 700, marginBottom: 4 }}>
                AS A BUYER
              </div>
              <p style={{ fontSize: 13, color: T.inkSoft, margin: 0 }}>Tracks how reliably you pay, collect, and honour a win.</p>
            </div>
            <div style={{ background: T.card, border: `1px solid ${T.line}`, borderRadius: 10, padding: 16 }}>
              <div style={{ fontFamily: "'IBM Plex Mono', monospace", fontSize: 11, color: T.teal, fontWeight: 700, marginBottom: 4 }}>
                AS A SELLER
              </div>
              <p style={{ fontSize: 13, color: T.inkSoft, margin: 0 }}>Tracks how accurately you describe items and how promptly you hand them over.</p>
            </div>
          </div>
        </section>

        {/* Buyer table */}
        <section style={{ marginBottom: 32 }}>
          <SectionLabel>BUYER RATING</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 6px" }}>
            What raises and lowers it
          </h2>
          <p style={{ fontSize: 13, color: T.inkSoft, marginBottom: 16, maxWidth: 700 }}>
            Movement is graduated: small, routine behaviour moves the rating a little; a genuine lapse or a
            consistent habit moves it moderately; deliberate or repeated failure moves it a lot — sometimes across
            more than one star at once.
          </p>
          <div style={{ display: "flex", flexDirection: "column", gap: 18 }}>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Small — 0.1★ to 0.3★</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                <EventTable rows={BUYER_SMALL} tone="up" />
                <EventTable rows={BUYER_SMALL_FALL} tone="down" />
              </div>
            </div>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Medium — 0.4★ to 0.7★</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                <EventTable rows={BUYER_MEDIUM} tone="up" />
                <EventTable rows={BUYER_MEDIUM_FALL} tone="down" />
              </div>
            </div>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Large — 0.8★ and above</div>
              <EventTable rows={BUYER_LARGE_FALL} tone="down" />
            </div>
          </div>
        </section>

        {/* Seller table */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>SELLER RATING</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 14px" }}>
            What raises and lowers it
          </h2>
          <div style={{ display: "flex", flexDirection: "column", gap: 18 }}>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Small — 0.1★ to 0.3★</div>
              <EventTable rows={SELLER_SMALL} tone="up" />
            </div>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Medium — 0.4★ to 0.7★</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                <EventTable rows={SELLER_MEDIUM} tone="up" />
                <EventTable rows={SELLER_MEDIUM_FALL} tone="down" />
              </div>
            </div>
            <div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: T.inkSoft, marginBottom: 6, textTransform: "uppercase", letterSpacing: "0.04em" }}>Large — 0.8★ and above</div>
              <EventTable rows={SELLER_LARGE_FALL} tone="down" />
            </div>
          </div>
        </section>

        {/* Benefits */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>WHY IT'S WORTH PROTECTING</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 14px" }}>
            What a strong rating actually gets you
          </h2>
          <div className="two-col" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
            {[
              [Unlock, "Full access, no restrictions", "Stay above the recovery thresholds and you're never capped to small-value transactions or hidden from search and alerts."],
              [TrendingUp, "An edge in Buy-Now", "Sellers can — and do — choose a trusted buyer's offer over a higher one from someone less reliable."],
              [ShieldQuestion, "Fewer questions asked", "A strong history is itself evidence in your favour if a dispute or review ever comes up."],
              [Star, "Visible to everyone you deal with", "Your seller rating is shown to bidders even in fully anonymous auctions — it's your reputation working for you before a deal even starts."],
            ].map(([Icon, title, body], i) => (
              <div key={i} style={{ background: T.card, border: `1px solid ${T.line}`, borderRadius: 10, padding: 16, display: "flex", gap: 12 }}>
                <Icon size={20} color={T.rust} style={{ flexShrink: 0, marginTop: 2 }} />
                <div>
                  <div style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 14, color: T.ink, marginBottom: 4 }}>{title}</div>
                  <div style={{ fontSize: 12.5, color: T.inkSoft, lineHeight: 1.5 }}>{body}</div>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* If it falls */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>IF YOUR RATING FALLS</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 14px" }}>
            A path back, not a dead end
          </h2>
          <p style={{ fontSize: 14, lineHeight: 1.65, color: T.ink, marginBottom: 16, maxWidth: 700 }}>
            A drop in rating isn't permanent, and it isn't automatic either — anything beyond a routine change goes
            through a real review before it takes effect, and you always have the right to appeal.
          </p>
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            <Accordion title="Below 2★ — Restricted, not blocked" icon={Lock} defaultOpen>
              <p style={{ fontSize: 13.5, color: T.ink, lineHeight: 1.6, marginTop: 12 }}>
                You're temporarily limited to smaller-value transactions on that shop, rather than being blocked
                outright. A defined number of clean transactions in a row — more each time this happens — restores
                you to a normal rating.
              </p>
            </Accordion>
            <Accordion title="Persistently low — Reduced visibility" icon={MinusCircle}>
              <p style={{ fontSize: 13.5, color: T.ink, lineHeight: 1.6, marginTop: 12 }}>
                You can still use the platform fully, but you stop being actively promoted — fewer alerts, less
                visibility in search and recommendations — until your standing improves.
              </p>
            </Accordion>
            <Accordion title="At the platform floor — A firm but liftable limit" icon={Gavel}>
              <p style={{ fontSize: 13.5, color: T.ink, lineHeight: 1.6, marginTop: 12 }}>
                At the lowest tier, transactions are capped at a flat platform-wide limit — but this can be raised
                by maintaining a higher standing deposit, giving you a concrete way to regain access even at your
                current rating.
              </p>
            </Accordion>
          </div>
        </section>

        {/* Sample ledger */}
        <section style={{ marginBottom: 36 }}>
          <SectionLabel>YOUR RATING LEDGER</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 8px" }}>
            See exactly why your rating changed
          </h2>
          <p style={{ fontSize: 14, lineHeight: 1.65, color: T.ink, marginBottom: 16, maxWidth: 700 }}>
            Every event that moves your rating is logged against your account, so you never have to guess why a
            number changed. Here's an illustrative example of what your own ledger looks like:
          </p>
          <div style={{ border: `1px solid ${T.line}`, borderRadius: 10, overflow: "hidden", background: T.card }}>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "80px 1fr 1.4fr 70px 70px",
                gap: 8,
                padding: "10px 14px",
                background: T.ink,
                color: T.paper,
                fontFamily: "'IBM Plex Mono', monospace",
                fontSize: 10.5,
                letterSpacing: "0.04em",
              }}
            >
              <span>DATE</span>
              <span>EVENT</span>
              <span>DETAIL</span>
              <span>CHANGE</span>
              <span>TOTAL</span>
            </div>
            {SAMPLE_LEDGER.map((row, i) => (
              <div
                key={i}
                style={{
                  display: "grid",
                  gridTemplateColumns: "80px 1fr 1.4fr 70px 70px",
                  gap: 8,
                  padding: "11px 14px",
                  alignItems: "center",
                  background: i % 2 === 1 ? T.paper : T.card,
                  borderTop: `1px solid ${T.line}`,
                }}
              >
                <span style={{ fontSize: 12, color: T.inkSoft, fontFamily: "'IBM Plex Mono', monospace" }}>{row.date}</span>
                <span style={{ fontSize: 12.5, fontWeight: 600, color: T.ink }}>{row.event}</span>
                <span style={{ fontSize: 12, color: T.inkSoft }}>{row.detail}</span>
                <span
                  style={{
                    fontFamily: "'IBM Plex Mono', monospace",
                    fontSize: 12,
                    fontWeight: 700,
                    color: row.tone === "up" ? T.green : T.red,
                  }}
                >
                  {row.change}
                </span>
                <span style={{ fontFamily: "'IBM Plex Mono', monospace", fontSize: 12.5, fontWeight: 700, color: T.ink }}>{row.total}</span>
              </div>
            ))}
          </div>
          <p style={{ fontSize: 11.5, color: T.inkSoft, marginTop: 8, fontStyle: "italic" }}>
            Illustrative example — your own ledger reflects your actual transaction history.
          </p>
        </section>

        {/* Appeals */}
        <section>
          <SectionLabel>IF YOU DISAGREE</SectionLabel>
          <h2 style={{ fontFamily: "'Archivo', sans-serif", fontWeight: 700, fontSize: 20, color: T.ink, margin: "0 0 14px" }}>
            How to appeal a downgrade
          </h2>
          <div style={{ background: T.card, border: `1px solid ${T.line}`, borderRadius: 10, padding: 20, display: "flex", gap: 14 }}>
            <ScrollText size={22} color={T.rust} style={{ flexShrink: 0 }} />
            <div>
              <p style={{ fontSize: 13.5, color: T.ink, lineHeight: 1.65, margin: "0 0 10px" }}>
                A meaningful downgrade is never applied automatically — it's reviewed first, and always comes with a
                stated reason. If you believe a rating change was wrong:
              </p>
              <ol style={{ paddingLeft: 18, margin: 0, display: "flex", flexDirection: "column", gap: 8 }}>
                <li style={{ fontSize: 13, color: T.ink }}>Check the stated reason on your Ratings Ledger — every change references the specific event behind it.</li>
                <li style={{ fontSize: 13, color: T.ink }}>Raise an appeal directly against that event, with any evidence that supports your case.</li>
                <li style={{ fontSize: 13, color: T.ink }}>Your appeal is reviewed once, by an authority independent of the original decision, and you'll receive a reasoned outcome either way.</li>
              </ol>
            </div>
          </div>
        </section>
      </main>

      <footer style={{ borderTop: `1px solid ${T.line}`, padding: "24px 20px", textAlign: "center" }}>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: 0 }}>
          For how disputes and appeals actually work, see our Dispute Resolution Process.
        </p>
      </footer>
    </div>
  );
}
