import React from "react";
import {
  HelpCircle, CheckSquare, Receipt, FileText, ShieldCheck, RotateCcw,
  Scale, MessageCircleQuestion, Phone, ArrowRight, Circle, CheckCircle2,
} from "lucide-react";

/* ------------------------------------------------------------------ */
/* Design tokens — shared with the FAQ page for a consistent site feel */
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
/* Content — grouped sections, each a set of cards                     */
/* ------------------------------------------------------------------ */
const GROUPS = [
  {
    id: "platform",
    label: "Using the Platform",
    blurb: "Practical answers for day-to-day buying and selling.",
    items: [
      {
        icon: HelpCircle,
        title: "FAQ",
        desc: "Searchable answers to common questions, shown separately for buyers and sellers.",
        status: "available",
      },
      {
        icon: CheckSquare,
        title: "Dos & Don'ts",
        desc: "A quick, practical guide to what to do — and avoid — at every stage of a transaction.",
        status: "available",
      },
      {
        icon: Receipt,
        title: "Fee & Charges Schedule",
        desc: "Exactly what buyers, sellers, and shops pay — deposits, commission, GST, and forfeiture rules.",
        status: "available",
      },
    ],
  },
  {
    id: "legal",
    label: "Legal Documents",
    blurb: "The formal terms governing your use of eBid Hub.",
    items: [
      {
        icon: FileText,
        title: "Terms of Service",
        desc: "The binding agreement covering listings, bidding, settlement, and account conduct.",
        status: "available",
      },
      {
        icon: ShieldCheck,
        title: "Privacy Policy",
        desc: "What data we collect, how it's used, and how it's protected.",
        status: "available",
      },
      {
        icon: RotateCcw,
        title: "Refund & Cancellation Policy",
        desc: "When a deposit is refunded, when it's forfeited, and how long each takes.",
        status: "soon",
      },
    ],
  },
  {
    id: "trust",
    label: "Trust, Safety & Support",
    blurb: "How we protect your money and data, and how to get help.",
    items: [
      {
        icon: ShieldCheck,
        title: "Security & Trust",
        desc: "How deposits are held, how listings are verified, and how the platform is audited.",
        status: "soon",
      },
      {
        icon: Scale,
        title: "Dispute Resolution Process",
        desc: "A plain-language walkthrough of how a disagreement gets reviewed and resolved.",
        status: "soon",
      },
      {
        icon: MessageCircleQuestion,
        title: "Grievance Redressal",
        desc: "How to escalate a complaint, and what response time to expect.",
        status: "soon",
      },
      {
        icon: Phone,
        title: "Contact Us",
        desc: "Reach the right team for account, transaction, or general queries.",
        status: "soon",
      },
    ],
  },
];

/* ------------------------------------------------------------------ */
/* Card                                                                 */
/* ------------------------------------------------------------------ */
function Card({ icon: Icon, title, desc, status }) {
  const available = status === "available";
  return (
    <div
      role={available ? "button" : undefined}
      tabIndex={available ? 0 : -1}
      className="ts-card"
      style={{
        background: T.card,
        border: `1px solid ${T.line}`,
        borderRadius: 12,
        padding: "20px 20px 18px",
        display: "flex",
        flexDirection: "column",
        gap: 12,
        cursor: available ? "pointer" : "default",
        opacity: available ? 1 : 0.75,
        transition: "box-shadow 180ms ease, border-color 180ms ease, transform 180ms ease",
      }}
    >
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <div
          style={{
            width: 38,
            height: 38,
            borderRadius: 9,
            background: available ? T.rustSoft : "#EFEFEA",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <Icon size={19} color={available ? T.rust : T.inkSoft} />
        </div>
        {available ? (
          <span
            style={{
              display: "flex",
              alignItems: "center",
              gap: 4,
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 10.5,
              letterSpacing: "0.06em",
              color: T.teal,
              fontWeight: 700,
            }}
          >
            <CheckCircle2 size={12} /> AVAILABLE
          </span>
        ) : (
          <span
            style={{
              display: "flex",
              alignItems: "center",
              gap: 4,
              fontFamily: "'IBM Plex Mono', monospace",
              fontSize: 10.5,
              letterSpacing: "0.06em",
              color: T.inkSoft,
              fontWeight: 700,
            }}
          >
            <Circle size={10} /> COMING SOON
          </span>
        )}
      </div>

      <div>
        <h3
          style={{
            fontFamily: "'Archivo', sans-serif",
            fontWeight: 700,
            fontSize: 16.5,
            color: T.ink,
            margin: "0 0 6px",
          }}
        >
          {title}
        </h3>
        <p style={{ fontSize: 13.5, lineHeight: 1.55, color: T.inkSoft, margin: 0 }}>{desc}</p>
      </div>

      {available && (
        <div
          style={{
            marginTop: 2,
            display: "flex",
            alignItems: "center",
            gap: 6,
            fontSize: 13,
            fontWeight: 600,
            color: T.rust,
          }}
        >
          Open <ArrowRight size={14} />
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Main page                                                            */
/* ------------------------------------------------------------------ */
export default function TrustAndSupport() {
  return (
    <div style={{ minHeight: "100vh", background: T.paper, fontFamily: "'Inter', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;700&display=swap');
        * { box-sizing: border-box; }
        .ts-card:hover { box-shadow: 0 8px 22px rgba(28,31,38,0.10); }
        .ts-card[role="button"]:hover { border-color: ${T.rust}; transform: translateY(-2px); }
        .ts-card:focus-visible { outline: 2px solid ${T.rust}; outline-offset: 2px; }
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
            EBID HUB
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
            Trust & Support
          </h1>
          <p style={{ fontSize: 15.5, color: T.inkSoft, margin: 0, maxWidth: 600, lineHeight: 1.6 }}>
            Everything you need to buy and sell with confidence — how the platform works, what protects
            you, and where to get help — in one place.
          </p>
        </div>
      </header>

      {/* Groups */}
      <main style={{ maxWidth: 980, margin: "0 auto", padding: "36px 20px 80px" }}>
        {GROUPS.map((group, gi) => (
          <section key={group.id} style={{ marginBottom: gi < GROUPS.length - 1 ? 44 : 0 }}>
            <div style={{ marginBottom: 18 }}>
              <h2
                style={{
                  fontFamily: "'Archivo', sans-serif",
                  fontWeight: 700,
                  fontSize: 20,
                  color: T.ink,
                  margin: "0 0 4px",
                }}
              >
                {group.label}
              </h2>
              <p style={{ fontSize: 13.5, color: T.inkSoft, margin: 0 }}>{group.blurb}</p>
            </div>
            <div
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))",
                gap: 14,
              }}
            >
              {group.items.map((item) => (
                <Card key={item.title} {...item} />
              ))}
            </div>
          </section>
        ))}
      </main>

      <footer style={{ borderTop: `1px solid ${T.line}`, padding: "24px 20px", textAlign: "center" }}>
        <p style={{ fontSize: 12.5, color: T.inkSoft, margin: 0 }}>
          Can't find what you need? Reach out to your shop's support, or check back soon — this page is
          actively growing.
        </p>
      </footer>
    </div>
  );
}
