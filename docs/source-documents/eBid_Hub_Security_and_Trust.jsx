import React from "react";
import {
  Vault, ShieldCheck, Camera, Scale, FileCheck2, BadgeCheck,
  Lock, EyeOff, Fingerprint, Clock, Ban, Gavel, ChevronRight,
} from "lucide-react";

/* ------------------------------------------------------------------ */
/* Design tokens — shared across the FAQ / Trust & Support pages       */
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
/* Content — six trust pillars, each with 3-4 concrete points          */
/* ------------------------------------------------------------------ */
const PILLARS = [
  {
    icon: Vault,
    title: "Your Money Is Never Pooled",
    intro:
      "Every deposit you make is held separately, tied to the specific bid it secures — never mixed into one shared pot with other buyers' money.",
    points: [
      "Each deposit is a distinct, ring-fenced holding, released or forfeited only based on that specific transaction's outcome.",
      "Your deposit is refunded automatically as soon as it's no longer needed — outbid, not selected, or deal closed — with no fixed delay.",
      "No interest is ever taken from money held on your behalf.",
      "Deposits are held through a licensed, RBI-regulated payment partner, not an unlicensed wallet.",
    ],
  },
  {
    icon: ShieldCheck,
    title: "Your Data Is Protected",
    intro: "Sensitive information is encrypted, minimised, and shared only with whoever genuinely needs it, only when they need it.",
    points: [
      "Aadhaar and other sensitive ID data are stored in masked, tokenised form — never in plain text.",
      "Banking details are encrypted and used solely to process refunds and settlements.",
      "Your identity stays hidden from a counterparty until a real commitment is made on both sides — not shared upfront.",
      "We never sell your data to anyone, for any reason.",
    ],
  },
  {
    icon: Camera,
    title: "What You See Is Real",
    intro: "Listings are built from genuine, verifiable evidence — not stock photos or recycled images.",
    points: [
      "Every photo is captured through the app itself, at the moment of listing — not uploaded from an old gallery.",
      "Location and timestamp data is captured automatically alongside each photo, making it far harder to pass off a fake or unrelated image.",
      "Stock photography and placeholder images are never allowed on a listing.",
      "Even on our fastest, sight-unseen format, sellers must disclose any known defects upfront — not just what the photos happen to show.",
    ],
  },
  {
    icon: Scale,
    title: "A Level Playing Field",
    intro: "The bidding process is designed so no one — including us — gets an unfair edge.",
    points: [
      "During a live auction, nobody can see who else is bidding — not other buyers, not the seller.",
      "Even our own top administrator has zero visibility into an auction while it's actually happening — access opens only after it closes, same as everyone else.",
      "No single bid can jump more than 150% above the current price, which catches costly typing mistakes and blocks deliberate price manipulation.",
      "Bidding activity is continuously monitored for patterns that suggest manipulation or bad-faith behaviour.",
    ],
  },
  {
    icon: FileCheck2,
    title: "Every Action Is Accountable",
    intro: "Decisions that affect you always come with a stated reason and a permanent record — never a silent, unexplained outcome.",
    points: [
      "Every rejection, forfeiture, and dispute ruling is logged with the specific reason behind it.",
      "Records in our system can't be secretly altered — not by outside parties, and not by our own staff.",
      "Before you pledge a deposit, you're shown a clear, specific summary of what you're agreeing to for that exact bid.",
      "A rating can only drop significantly after a human review — it's never an automatic, unchecked penalty — and you always have the right to appeal.",
    ],
  },
  {
    icon: BadgeCheck,
    title: "Independently Checked",
    intro: "We don't just tell you the platform is secure — it's regularly verified by people outside our own team.",
    points: [
      "The platform undergoes independent security audits by outside specialists, not just our own developers.",
      "Our servers' clocks are continuously synced and monitored, so auction timing can't be secretly manipulated.",
      "Unusual money-movement patterns are actively monitored to guard against misuse of the platform.",
      "Our payment infrastructure runs through a licensed, RBI-authorised partner, not an in-house or unregulated system.",
    ],
  },
];

/* ------------------------------------------------------------------ */
/* Pillar card                                                         */
/* ------------------------------------------------------------------ */
function Pillar({ icon: Icon, title, intro, points, index }) {
  return (
    <div
      style={{
        background: T.card,
        border: `1px solid ${T.line}`,
        borderRadius: 14,
        padding: "24px 24px 22px",
        display: "flex",
        flexDirection: "column",
        gap: 14,
      }}
    >
      <div style={{ display: "flex", alignItems: "flex-start", gap: 14 }}>
        <div
          style={{
            width: 44,
            height: 44,
            borderRadius: 10,
            background: T.rustSoft,
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            flexShrink: 0,
          }}
        >
          <Icon size={22} color={T.rust} />
        </div>
        <div>
          <div
            style={{
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 11,
              letterSpacing: "0.06em",
              color: T.inkSoft,
              marginBottom: 4,
            }}
          >
            {String(index + 1).padStart(2, "0")}
          </div>
          <h3
            style={{
              fontFamily: "'Archivo', sans-serif",
              fontWeight: 700,
              fontSize: 18,
              color: T.ink,
              margin: 0,
            }}
          >
            {title}
          </h3>
        </div>
      </div>

      <p style={{ fontSize: 14, lineHeight: 1.6, color: T.inkSoft, margin: 0 }}>{intro}</p>

      <ul style={{ margin: 0, padding: 0, listStyle: "none", display: "flex", flexDirection: "column", gap: 9 }}>
        {points.map((pt, i) => (
          <li key={i} style={{ display: "flex", gap: 8, alignItems: "flex-start" }}>
            <ChevronRight size={15} color={T.teal} style={{ flexShrink: 0, marginTop: 2 }} />
            <span style={{ fontSize: 13.5, lineHeight: 1.55, color: T.ink }}>{pt}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Main page                                                            */
/* ------------------------------------------------------------------ */
export default function SecurityAndTrust() {
  return (
    <div style={{ minHeight: "100vh", background: T.paper, fontFamily: "'Inter', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;700&display=swap');
        * { box-sizing: border-box; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
      `}</style>

      {/* Masthead */}
      <header style={{ borderBottom: `1px solid ${T.line}`, background: T.card }}>
        <div style={{ maxWidth: 980, margin: "0 auto", padding: "40px 20px 32px" }}>
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
            EBID HUB — TRUST &amp; SUPPORT
          </div>
          <h1
            style={{
              fontFamily: "'Archivo', sans-serif",
              fontWeight: 800,
              fontSize: "clamp(30px, 4vw, 40px)",
              color: T.ink,
              margin: "0 0 8px",
              letterSpacing: "-0.01em",
            }}
          >
            Security & Trust
          </h1>
          <p style={{ fontSize: 15.5, color: T.inkSoft, margin: 0, maxWidth: 640, lineHeight: 1.6 }}>
            A plain explanation of how your money, your data, and your transactions are protected on
            eBid Hub — and how we hold ourselves accountable.
          </p>
        </div>
      </header>

      {/* Icon strip — quick-scan summary */}
      <div style={{ borderBottom: `1px solid ${T.line}`, background: T.tealSoft + "40" }}>
        <div
          style={{
            maxWidth: 980,
            margin: "0 auto",
            padding: "18px 20px",
            display: "flex",
            gap: 22,
            flexWrap: "wrap",
            justifyContent: "center",
          }}
        >
          {[
            [Lock, "Segregated deposits"],
            [EyeOff, "Anonymous bidding"],
            [Fingerprint, "Encrypted identity data"],
            [Clock, "Tamper-checked timing"],
            [Ban, "150% bid ceiling"],
            [Gavel, "Independent audits"],
          ].map(([Icon, label], i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 7 }}>
              <Icon size={16} color={T.teal} />
              <span style={{ fontSize: 12.5, color: T.ink, fontWeight: 500 }}>{label}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Pillars */}
      <main style={{ maxWidth: 980, margin: "0 auto", padding: "36px 20px 80px" }}>
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
            gap: 18,
          }}
        >
          {PILLARS.map((p, i) => (
            <Pillar key={p.title} {...p} index={i} />
          ))}
        </div>
      </main>

      <footer style={{ borderTop: `1px solid ${T.line}`, padding: "24px 20px", textAlign: "center" }}>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: "0 0 4px" }}>
          For the full legal detail behind these protections, see our Terms of Service and Privacy Policy.
        </p>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: 0 }}>
          Have a specific concern? Reach out through Contact Us.
        </p>
      </footer>
    </div>
  );
}
