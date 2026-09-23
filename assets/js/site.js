/* ===== data.js ===== */
/* ============================================================
   JOLLOF LIVING — data.js  (database-backed)
   ------------------------------------------------------------
   Everything here used to be a hard-coded literal. It now comes
   from MySQL: includes/view.php prints a window.JL payload for the
   current page, and this module simply adapts it to the shapes the
   rendering code already expects.
   ============================================================ */
"use strict";

const JL = window.JL || {};
const BOOT = JL.data || {};

/* ---------------- pricing config ---------------- */
const FX = BOOT.fx || { NGN: { s: "\u20A6", r: 1, d: 0 } };
const RATES = BOOT.rates || {
  cleaning: 15000, service: 0.08, vat: 0.075,
  weeklyDisc: 0.12, monthlyDisc: 0.25, deposit: 0.20, insurance: 0.03,
};
const PROMOS = BOOT.promos || {};
const ADDONS = BOOT.addons || {};
const PAY_METHODS = BOOT.payMethods || [];
const AUTH_METHODS = ["Email", "Phone", "Google", "Apple", "Facebook"];

/* ---------------- catalogue ---------------- */
const PROPERTIES = BOOT.properties || [];
const COLLECTIONS = BOOT.collections || [];
const COLLECTION_MAP = BOOT.collectionMap || {};
const NEIGHBORHOODS = BOOT.neighborhoods || [];
const EXPERIENCES = BOOT.experiences || [];
const BLOG = BOOT.blog || [];

/* ---------------- editorial ---------------- */
const TESTIMONIALS = BOOT.testimonials || [];
const FAQS = BOOT.faqs || [];
const HELP_CATEGORIES = BOOT.helpCategories || [];
const ROADMAP = BOOT.roadmap || [];

/* ---------------- membership ---------------- */
const TIERS = BOOT.tiers || [];
const POINTS_LEDGER = BOOT.pointsLedger || [];

/* ---------------- user-scoped ---------------- */
const NOTIFS = BOOT.notifications || [];
const CONVERSATIONS = BOOT.conversations || [];

/* ---------------- back office ---------------- */
const ADMIN_STATE = BOOT.admin || {
  users: [], moderation: [], fraud: [], campaigns: [], audit: [], cms: [],
};
const ADMIN_STATS = BOOT.adminStats || {};

/* ---------------- owner (host) workspace ---------------- */
const HOST = BOOT.host || {
  stats: {}, listings: [], bookings: [], earnings: [], sources: [],
  calendar: { days: [] }, rules: [], team: [], templates: [], channels: [],
  payouts: [], payoutSettings: {}, insights: [],
};

/* ---------------- signed-in user ---------------- */
const USER = JL.user || null;
const IS_ADMIN = !!JL.isAdmin;
const IS_OWNER = !!(USER && USER.isHost);
const REFERRAL_CODE = (USER && USER.referral) || "JOLLOF";

/* ============================================================
   Image registry — real files served from assets/img, replacing
   the old base64 blob. img() returns a URL for a registry key.
   ============================================================ */
const IMG_BASE = (JL.imgBase || "assets/img/").replace(/\/?$/, "/");
const IMG_MAP = JL.images || {};

function img(key) {
  if (!key) return IMG_BASE + "p1.jpg";
  if (/^(https?:|data:|\/)/.test(key)) return key;           // absolute / uploaded
  if (IMG_MAP[key]) return IMG_BASE + IMG_MAP[key];
  if (/\.(jpe?g|png|webp|avif|gif)$/i.test(key)) return IMG_BASE + key;
  return IMG_BASE + key + ".jpg";
}

/* Gallery keys for a property: the images uploaded against the listing,
   falling back to the hero image. Only keys that resolve to a real file
   are returned, so a thumbnail strip never renders a broken image. */
function galleryKeys(p) {
  if (!p) return [];
  const known = (k) => !!k && (IMG_MAP[k] || /^(https?:|data:|\/)/.test(k) || /\.(jpe?g|png|webp|avif|gif)$/i.test(k));
  const out = [];
  for (const k of [p.img, ...(p.gallery || [])]) {
    if (known(k) && !out.includes(k)) out.push(k);
  }
  return out.length ? out : [p.img].filter(known);
}

/* Back-compat: legacy templates wrote `data:image/jpeg;base64,${ASSETS[k]}`.
   ASSETS is now a proxy returning a plain URL, and the build rewrites those
   template fragments to img(...) — this guard keeps stragglers working. */
const ASSETS = new Proxy({}, {
  get: (_t, k) => (typeof k === "string" ? img(k) : undefined),
  has: () => true,
});

/* ============================================================
   API client — every state change is persisted server-side.
   ============================================================ */
const API_BASE = JL.apiBase || "api/";

async function api(endpoint, payload, method) {
  const opts = {
    method: method || (payload ? "POST" : "GET"),
    headers: { "X-CSRF-Token": JL.csrf || "", "X-Requested-With": "fetch" },
    credentials: "same-origin",
  };
  if (payload) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(Object.assign({ csrf: JL.csrf || "" }, payload));
  }
  let res, body;
  try {
    res = await fetch(API_BASE + endpoint, opts);
    body = await res.json();
  } catch (err) {
    return { ok: false, message: "Network problem — please try again." };
  }
  return body && typeof body === "object" ? body : { ok: false, message: "Unexpected response." };
}

/* Fire-and-forget helper that surfaces the server message as a toast. */
function apiToast(endpoint, payload, okIcon) {
  return api(endpoint, payload).then((r) => {
    if (r.message) toast(r.message, r.ok ? (okIcon || "check") : "x");
    return r;
  });
}


/* ===== ui.js ===== */
/* ============================================================
   JOLLOF LIVING — ui.js  (icons, helpers, components)
   ============================================================ */

const I = {
  pin:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>',
  star:'<svg viewBox="0 0 24 24"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>',
  bed:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M3 18h18M3 18v2m18-2v2M6 9V7a2 2 0 0 1 2-2h3v4"/></svg>',
  bath:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12h16v2a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5v-2z"/><path d="M6 12V5a2 2 0 0 1 4 0M7 19l-1 2m11-2l1 2"/></svg>',
  users:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3.2 2.8-5 5.5-5s4.9 1.8 5.5 5M16 4.6a3.2 3.2 0 0 1 0 6.2M17.5 14.4c2 .7 3.3 2.3 3.7 4.6"/></svg>',
  check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6L9 17l-5-5"/></svg>',
  ok:"✓", x:"✕",
  checkCircle:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.2l2.4 2.4 4.6-5"/></svg>',
  wifi:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.5 8.5C8 3.5 16 3.5 21.5 8.5M5.5 12c4-3.4 9-3.4 13 0M9 15.3c2-1.7 4-1.7 6 0"/><circle cx="12" cy="18.6" r="1.2" fill="currentColor"/></svg>',
  pool:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 17c1.5-1.2 3-1.2 4.5 0s3 1.2 4.5 0 3-1.2 4.5 0 3 1.2 4.5 0M2 21c1.5-1.2 3-1.2 4.5 0s3 1.2 4.5 0 3-1.2 4.5 0 3 1.2 4.5 0M8 15V5.5A1.5 1.5 0 0 1 11 5m0 3.5A1.5 1.5 0 0 1 14 10m-6 5V3.8"/></svg>',
  car:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 16v-4l1.8-4.2A2 2 0 0 1 7.7 6.5h8.6a2 2 0 0 1 1.9 1.3L20 12v4m-16 0v2m16-2v2M4 16h16M6.5 13.5h.01M17.5 13.5h.01"/></svg>',
  shield:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2.5l7.5 3v6c0 4.8-3.2 8.2-7.5 10-4.3-1.8-7.5-5.2-7.5-10v-6z"/><path d="M8.8 12l2.2 2.2 4.2-4.4"/></svg>',
  wallet:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M16 15h2.5"/></svg>',
  spark:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4zM19 16l.9 2.6L22.5 19l-2.6.9L19 22.5l-.9-2.6L15.5 19l2.6-.9z"/></svg>',
  calendar:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 2.5V7m8-4.5V7"/></svg>',
  clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
  heart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20.5S3.5 15 3.5 9.1A4.6 4.6 0 0 1 12 6.6a4.6 4.6 0 0 1 8.5 2.5c0 5.9-8.5 11.4-8.5 11.4z"/></svg>',
  heartFill:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 20.5S3.5 15 3.5 9.1A4.6 4.6 0 0 1 12 6.6a4.6 4.6 0 0 1 8.5 2.5c0 5.9-8.5 11.4-8.5 11.4z"/></svg>',
  sun:'<svg class="ic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.5 4.5l1.8 1.8M17.7 17.7l1.8 1.8M4.5 19.5l1.8-1.8M17.7 6.3l1.8-1.8"/></svg>',
  moon:'<svg class="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11z"/></svg>',
  menu:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
  xsvg:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>',
  plus:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>',
  minus:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/></svg>',
  send:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>',
  chat:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5c-1.5 0-2.9-.4-4.1-1L3 21l1.6-5A8.5 8.5 0 1 1 21 12z"/></svg>',
  search:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20.5 20.5L16 16"/></svg>',
  arrow:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h16M13 5l7 7-7 7"/></svg>',
  globe:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 2.6 4.2 5.6 4.2 9S14.8 18.4 12 21c-2.8-2.6-4.2-5.6-4.2-9S9.2 5.6 12 3z"/></svg>',
  gift:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="8" width="17" height="4"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8M12 8v13M12 8s-4.5.2-5.5-2C5.8 4.2 8 2.6 9.5 3.8 11 5 12 8 12 8zm0 0s4.5.2 5.5-2c.7-1.8-1.5-3.4-3-2.2C13 5 12 8 12 8z"/></svg>',
  broom:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13.5 3l7 7-1.8 1.8a3 3 0 0 1-4.2 0L8 5.2M7 8L3 20l9-3.5M4.5 15.5L9 19"/></svg>',
  key:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="15" r="4.5"/><path d="M11.5 11.5L20 3m-3 3l3 3m-6 0l2.5 2.5"/></svg>',
  grid:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>',
  scale:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3v18M5 21h14M5 6l7-3 7 3M5 6l-2.5 7a3 3 0 0 0 5 0L5 6zm14 0l-2.5 7a3 3 0 0 0 5 0L19 6z"/></svg>',
  lock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>',
  phone:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>',
  book:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 19V5a2 2 0 0 1 2-2h13v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H19v-4"/></svg>',
  leaf:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 20c0-9 4-15 15-16-.5 10-5 16-13 16M5 20c1.5-6 5-10 10-12"/></svg>',
  bolt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13 2L4.5 13.5H11L9.5 22 19 10h-6.5z"/></svg>',
  tv:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="6.5" width="18" height="12" rx="2"/><path d="M8 3l4 3.5L16 3"/></svg>',
  dumbbell:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 8v8m10-8v8M3.5 9.5v5M20.5 9.5v5M7 12h10"/></svg>',
  wine:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3h8l-.7 6a3.3 3.3 0 0 1-6.6 0zM12 15v6M8.5 21h7M8.5 3l-2 4.5a2.5 2.5 0 0 0 4.8 1M15.5 3l2 4.5a2.5 2.5 0 0 1-4.8 1"/></svg>',
  ac:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="7" width="19" height="10" rx="2"/><path d="M7 10v4m5-4v4m5-4v4"/></svg>',
  building:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8.5 7h2m3 0h2m-7 4h2m3 0h2m-7 4h2m3 0h2M10 21v-3h4v3"/></svg>',
  eye:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>',
  doc:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h8l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
  bot:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 4.5V8M8.5 13h.01M15.5 13h.01M9 16.5h6"/><circle cx="12" cy="4" r="1.6"/></svg>',
  exchange:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 8h13l-3.5-3.5M20 16H7l3.5 3.5"/></svg>',
  gold:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7.5l4.2 3.3L12 4.5l4.8 6.3L21 7.5l-1.6 10.5H4.6z"/><path d="M5 21h14"/></svg>',
  camera:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="6.5" width="19" height="13" rx="2.5"/><path d="M8.5 6.5L10 4h4l1.5 2.5M12 10a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>',
  zoom:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20.5 20.5L16 16M11 8v6M8 11h6"/></svg>',
  chatBell:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/></svg>',
  video:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="6.5" width="13" height="11" rx="2.5"/><path d="M15.5 10.5l6-3v9l-6-3z"/></svg>',
  call:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>',
  trash:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M9 7V5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 5v2m4 0l-.8 12a2 2 0 0 1-2 1.9H7.8a2 2 0 0 1-2-1.9L5 7M10 11v6m4-6v6"/></svg>',
  edit:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 20h4L20 8l-4-4L4 16zM13.5 6.5l4 4"/></svg>',
  download:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 19h16"/></svg>',
  share:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="6" cy="12" r="2.6"/><circle cx="17.5" cy="5.5" r="2.6"/><circle cx="17.5" cy="18.5" r="2.6"/><path d="M8.3 10.8l6.9-4M8.3 13.2l6.9 4"/></svg>',
  play:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg>',
  notification:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/></svg>',
};

/* ---------------- helpers ---------------- */
const $  = (s, el=document) => el.querySelector(s);
const $$ = (s, el=document) => [...el.querySelectorAll(s)];
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const store = {
  get(k,d){ try{ const v = localStorage.getItem("jl_"+k); return v===null?d:JSON.parse(v);}catch(e){return d;} },
  set(k,v){ try{ localStorage.setItem("jl_"+k, JSON.stringify(v)); }catch(e){} },
};
let currency = store.get("currency","NGN");
const fmt = (ngn) => { const c = FX[currency]; const v = ngn*c.r; return c.s + (v>=1000 ? Math.round(v).toLocaleString("en-NG") : v.toFixed(c.d)); };
const K = (ngn) => fmt(ngn).replace(/,000$/,"k");
/* "pending" -> "Pending"; safe on empty/undefined input */
const cap = (s) => { s = String(s == null ? "" : s); return s ? s[0].toUpperCase() + s.slice(1) : s; };

const todayStr = (off=0) => { const d=new Date(); d.setDate(d.getDate()+off); return d.toISOString().slice(0,10); };

/* ------------- routing -------------
   Authoring uses "/path"; the PHP site serves real .php files (with
   pretty-URL rewrites in .htaccess, so /stays and /stay/onyx also work). */
const SITE_BASE = (JL.base || "").replace(/\/?$/, "/");
const PAGE_MAP = {
  "/": "", "/index": "",
  "/stays": "stays.php", "/map": "map.php",
  "/collections": "collections.php",
  "/experiences": "experiences.php",
  "/reviews": "reviews.php",
  "/blog": "blog.php",
  "/help": "help.php",
  "/membership": "membership.php",
  "/giftcards": "giftcards.php",
  "/referral": "referral.php",
  "/business": "business.php",
  "/about": "about.php",
  "/app": "app.php",
  "/future": "future.php",
  "/concierge": "concierge.php",
  "/agent": "agent.php",
  "/messages": "messages.php",
  "/notifications": "notifications.php",
  "/trips": "trips.php",
  "/wishlist": "wishlist.php",
  "/compare": "compare.php",
  "/account": "account.php",
  "/auth": "auth.php",
  "/logout": "logout.php",
  "/host": "host.php",
  "/host/onboarding": "host-onboarding.php",
  "/host/dashboard": "host-dashboard.php",
  "/payments": "payments.php",
  "/neighborhoods": "neighborhoods.php",
  "/admin": "admin.php",
  "/admin-login": "admin-login.php",
  "/404": "404.php",
  "/confirm": "confirm.php",
};
const URL = (path) => {
  let p = path || "/", qs = "";
  const qi = p.indexOf("?"); if (qi > -1) { qs = p.slice(qi); p = p.slice(0, qi); }
  const seg = p.split("/").filter(Boolean);
  const s1 = seg[0] || "", s2 = seg[1] || "";
  const at = (f) => SITE_BASE + f;
  if (s1 === "stay" && s2) return at(`stay.php?p=${encodeURIComponent(s2)}`) + qs.replace("?", "&");
  if (s1 === "booking" && s2) return at(`booking.php?p=${encodeURIComponent(s2)}`) + qs.replace("?", "&");
  if (s1 === "neighborhood" && s2) return at(`neighborhood.php?n=${encodeURIComponent(s2)}`) + qs.replace("?", "&");
  if (s1 === "blog" && s2) return at(`blog-post.php?s=${encodeURIComponent(s2)}`) + qs.replace("?", "&");
  if (s1 === "confirm" && s2) return at(`confirm.php?ref=${encodeURIComponent(s2)}`);
  if (PAGE_MAP[p] !== undefined) return at(PAGE_MAP[p]) + qs;
  const one = PAGE_MAP["/" + s1];
  return at(one !== undefined ? one : "404.php") + qs;
};
/* navigate to an internal route */
function nav(path) { location.href = URL(path); }
/* current page id — printed by the PHP layout as <body data-page="…"> */
const PAGE_ID = (document.body && document.body.getAttribute("data-page")) || "index";
const qp = (k) => new URLSearchParams(location.search).get(k);
const qps = () => Object.fromEntries(new URLSearchParams(location.search).entries());

/* turns data-goto="/stay/onyx" into real page navigation.
   Anchors already carry a resolved href from URL(), so they need no handling
   here -- and must not be intercepted, or modifier-clicks / open-in-new-tab
   would break. */
document.addEventListener("click", (e) => {
  const g = e.target.closest("[data-goto]");
  if (!g) return;
  // let a nested link, button or control inside the card do its own thing
  const inner = e.target.closest("a[href], button, input, select, textarea, [data-heart], [data-cmp]");
  if (inner && inner !== g && g.contains(inner)) return;
  location.href = URL(g.dataset.goto);
});

/* ============================================================
   Global state.
   Anything durable (wishlists, compare, trips, points, notifications)
   is owned by MySQL and arrives in window.JL.state; the client keeps a
   mirror for instant UI feedback and POSTs every change to /api/*.php.
   Purely ephemeral things (filters, the booking wizard) stay local.
   ============================================================ */
const BOOT_STATE = JL.state || {};

const S = {
  /* server-owned */
  wishlists:      BOOT_STATE.wishlists      || { default: [] },
  activeWishlist: BOOT_STATE.activeWishlist || "default",
  compare:        BOOT_STATE.compare        || [],
  bookings:       BOOT_STATE.bookings       || [],
  waitlist:       BOOT_STATE.waitlist       || [],
  recent:         BOOT_STATE.recent         || [],
  points:         BOOT_STATE.points         || 0,
  tier:           BOOT_STATE.tier           || "bronze",
  hostListings:   BOOT_STATE.hostListings   || [],
  notifications:  NOTIFS,
  unreadMsgs:     BOOT_STATE.unreadMsgs     || 0,
  signedIn:       !!USER,

  /* view-local */
  requests: [],
  promoApplied: null,
  giftApplied: null,
  filters: { loc: "all", guests: 1, price: 500, type: "all", instants: false, flex: false },
  searchDates: null,
};

/* View preferences are the only thing still kept in the browser. */
const dump = () => {
  store.set("theme", document.documentElement.getAttribute("data-theme"));
  store.set("currency", currency);
};

/* Ask the server to re-send state after a mutation, then repaint. */
async function syncState(repaint) {
  const r = await api("state.php");
  if (r && r.ok && r.data) {
    Object.assign(S, r.data);
    S.signedIn = !!USER;
  }
  renderBadges();
  if (repaint !== false && typeof render === "function") render();
  return r;
}

/* Guests must sign in before anything is written to their account. */
function requireAuth(what) {
  if (S.signedIn) return true;
  toast("Sign in to " + (what || "continue") + " — it takes 90 seconds.", "lock");
  setTimeout(() => nav("/auth?next=" + encodeURIComponent(location.pathname + location.search)), 900);
  return false;
}

/* recent views are recorded server-side by stay.php; keep the mirror tidy */
function recentAdd(id) {
  const i = S.recent.indexOf(id);
  if (i > -1) S.recent.splice(i, 1);
  S.recent.unshift(id);
  if (S.recent.length > 10) S.recent.length = 10;
}

/* ---------------- toast ---------------- */
function toast(msg, icon="check") {
  const t=document.createElement("div"); t.className="toast";
  t.innerHTML=(I[icon]?I[icon]:I.check)+`<span>${msg}</span>`;
  $("#toasts").appendChild(t);
  setTimeout(()=>t.classList.add("out"),3600); setTimeout(()=>t.remove(),4100);
}

/* ---------------- modal / sheet ---------------- */
function openModal(html, cls="") {
  const body=$("#modalBody"), panel=$("#modalPanel");
  body.innerHTML=html; panel.className="modal-panel "+cls;
  $("#modalRoot").classList.add("open"); document.body.style.overflow="hidden";
  const s=body.querySelector(".mscroll"); if(s){ const t=body.querySelector(".mtop"); if(t) t.scrollIntoView({block:"start"}); }
}
function closeModal(){ $("#modalRoot").classList.remove("open"); document.body.style.overflow=""; }
function openSheet(html){ $("#sheetPanel").innerHTML=html; $("#sheetRoot").classList.add("open"); document.body.style.overflow="hidden"; }
function closeSheet(){ $("#sheetRoot").classList.remove("open"); document.body.style.overflow=""; }
document.getElementById("modalX").addEventListener("click", closeModal);

/* ---------------- charts (pure SVG) ---------------- */
function lineChart(data, opts={}) {
  const W=560,H=210,P=34, vals=data.map(d=>d.v), min=Math.min(...vals)*0.92, max=Math.max(...vals)*1.06;
  const x=i=>P+i*(W-2*P)/(data.length-1), y=v=>H-P-(v-min)/(max-min)*(H-2*P);
  let path="", area=`M${x(0)},${y(vals[0])}`;
  vals.forEach((v,i)=>{ path+=`${i?"L":"M"}${x(i)},${y(v)}`; area+=` L${x(i)},${y(v)}`; });
  area+=` L${x(vals.length-1)},${H-P} L${x(0)},${H-P} Z`;
  const last=vals[vals.length-1];
  const grid=[0,0.5,1].map(f=>H-P-f*(H-2*P)).map(gy=>`<line x1="${P}" x2="${W-P}" y1="${gy}" y2="${gy}" stroke="var(--line-soft)" stroke-width="1"/>`).join("");
  return `<svg viewBox="0 0 ${W} ${H}" role="img">
    ${grid}
    <path d="${area}" fill="var(--gold-soft)"/>
    <path d="${path}" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round"/>
    ${vals.map((v,i)=>`<circle cx="${x(i)}" cy="${y(v)}" r="${i===vals.length-1?5:2.6}" fill="${i===vals.length-1?"var(--accent)":"var(--card)"}" stroke="var(--accent)" stroke-width="1.6"/>`).join("")}
    <text x="${x(vals.length-1)}" y="${y(last)-12}" text-anchor="end" font-size="13" font-family="Cormorant Garamond,serif" font-weight="600" fill="var(--accent)">${opts.fmt?opts.fmt(last):last}</text>
    ${(opts.labels||[]).map((l,i)=>`<text x="${x(i)}" y="${H-10}" text-anchor="middle" font-size="10.5" fill="var(--ink-faint)" font-family="Jost,sans-serif">${l}</text>`).join("")}
  </svg>`;
}
function barChart(data, opts={}) {
  const W=560,H=210,P=44, max=Math.max(...data.map(d=>d.v))*1.12;
  const bw=(W-2*P)/data.length*0.58;
  return `<svg viewBox="0 0 ${W} ${H}">
    <line x1="${P}" x2="${W-P}" y1="${H-P}" y2="${H-P}" stroke="var(--line)" stroke-width="1"/>
    ${data.map((d,i)=>{ const h=(d.v/max)*(H-2*P); const x=P+i*(W-2*P)/data.length+( (W-2*P)/data.length-bw)/2; return `<rect x="${x}" y="${H-P-h}" width="${bw}" height="${h}" rx="6" fill="${d.c||"var(--accent)"}" opacity="${d.c?"0.9":"0.85"}"/><text x="${x+bw/2}" y="${H-P+16}" text-anchor="middle" font-size="10.5" fill="var(--ink-faint)" font-family="Jost,sans-serif">${d.l}</text>`;}).join("")}
  </svg>`;
}
function donutChart(parts, center) {
  const total=parts.reduce((a,p)=>a+p.v,0); let acc=0;
  const ring=parts.map(p=>{ const a0=acc/total*360; acc+=p.v; const a1=acc/total*360;
    const large=a1-a0>180?1:0; const r1=54,r0=34, c=(x,y)=>[x,y];
    const pt=(r,a)=>{ const rad=(a-90)*Math.PI/180; return [100+r*Math.cos(rad),100+r*Math.sin(rad)]; };
    const [x0,y0]=pt(r1,a0),[x1,y1]=pt(r1,a1),[x2,y2]=pt(r0,a1),[x3,y3]=pt(r0,a0);
    return `<path d="M${x0},${y0} A${r1},${r1} 0 ${large} 1 ${x1},${y1} L${x2},${y2} A${r0},${r0} 0 ${large} 0 ${x3},${y3} Z" fill="${p.c}"/>`;}).join("");
  return `<svg viewBox="0 0 200 200" style="max-width:200px;margin:0 auto">${ring}<text x="100" y="96" text-anchor="middle" font-size="24" font-family="Cormorant Garamond,serif" font-weight="600" fill="var(--ink)">${center[0]}</text><text x="100" y="116" text-anchor="middle" font-size="10.5" fill="var(--ink-faint)" font-family="Jost,sans-serif">${center[1]}</text></svg>`;
}
function sparkline(data, w=120, h=34) {
  const min=Math.min(...data), max=Math.max(...data);
  const pts=data.map((v,i)=>`${(i*(w-8)/(data.length-1)+4)},${h-5-(v-min)/(max-min||1)*(h-10)}`).join(" ");
  return `<svg viewBox="0 0 ${w} ${h}" style="width:${w}px;height:${h}px"><polyline points="${pts}" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"/></svg>`;
}

/* ---------------- prices & booking math ---------------- */
function nightsBetween(a,b){ const n=Math.round((new Date(b)-new Date(a))/864e5); return n>0?n:0; }
function priceMath(p, n, opts={}) {
  const monthly=n>=30, weekly=n>=7;
  const discRate=monthly?RATES.monthlyDisc:weekly?RATES.weeklyDisc:0;
  const nightly=p.price*(1-discRate);
  const subtotal=nightly*n;
  /* callers pass [{k:"transfer"},…]; a bare key is tolerated too. Percentage
     add-ons (insurance, carbon) charge a share of the subtotal, flat ones a
     fixed fee. An unknown key must never take the page down. */
  let addons=0;
  (opts.addons||[]).forEach(a=>{
    const key = (a && typeof a==="object") ? a.k : a;
    const def = ADDONS[key];
    if(!def) return;
    addons += def.price>=1 ? def.price : subtotal*def.price;
  });
  /* Mirror Pricing::quote() on the server exactly: the service fee is charged
     on subtotal + add-ons, and VAT on subtotal + add-ons + cleaning + service.
     This is only an estimate for display -- the server recalculates and its
     figure is the one charged -- but the two must agree or the review step
     shows a different number from the invoice. */
  const svc=Math.round((subtotal+addons)*RATES.service);
  const vat=Math.round((subtotal+addons+RATES.cleaning+svc)*RATES.vat);
  const deposit=Math.round(subtotal*RATES.deposit);
  let total=subtotal+addons+RATES.cleaning+svc+vat;
  if(opts.promo){ const pr=PROMOS[opts.promo]; if(pr) total-= pr.off? Math.round(total*pr.off) : (pr.flat||0); }
  if(opts.gift) total-=Math.min(opts.gift, total>0?Math.max(0,total):0);
  return { monthly, weekly, discRate, nightly, subtotal, addons, svc, vat, deposit, total: Math.max(0,Math.round(total)), split: monthly||weekly };
}
function persist(){ renderBadges(); }

/* ---------------- badges ---------------- */
function renderBadges() {
  const wl=$("#wlCount");
  if(wl) wl.textContent=Object.values(S.wishlists||{}).reduce((a,l)=>a+(l?l.length:0),0);

  /* read state lives in the database — S.unreadNotifs is authoritative */
  const nc=$("#notifCount");
  if(nc){
    const u=(typeof S.unreadNotifs==="number")
      ? S.unreadNotifs
      : (S.notifications||[]).filter(n=>n.unread).length;
    nc.textContent=u; nc.style.display=u?"grid":"none";
  }

  const mc=$("#msgCount");
  if(mc){ const m=S.unreadMsgs||0; mc.textContent=m; mc.style.display=m?"grid":"none"; }
}

/* ---------------- stay card ---------------- */
function stayCard(p) {
  const fav=(S.wishlists[S.activeWishlist]||[]).includes(p.id);
  const cmp=S.compare.includes(p.id);
  return `
  <article class="stay-card reveal in">
    <div class="stay-media" data-goto="/stay/${p.id}">
      <img src="${img(p.img)}" alt="${esc(p.name)}" loading="lazy">
      <span class="ribbon ${p.badgeGold?"gold":""}">${p.badgeGold?I.gold:I.shield}${esc(p.badge)}</span>
      <button class="heart-btn ${fav?"active":""}" data-heart="${p.id}" aria-label="Save to wishlist">${fav?I.heartFill:I.heart}</button>
    </div>
    <div class="stay-body">
      <div class="stay-top">
        <div>
          <h3 data-goto="/stay/${p.id}">${esc(p.name)}</h3>
          <div class="stay-loc">${I.pin}${esc(p.area)}, ${esc(p.city)}</div>
        </div>
        <div class="stay-rating">${I.star}${p.rating.toFixed(2)}<span class="rev">(${p.reviews})</span></div>
      </div>
      <div class="stay-specs">
        <span class="spec">${I.bed}${p.beds} bd</span><span class="spec">${I.bath}${p.baths} ba</span><span class="spec">${I.users}${p.guests} guests</span>
      </div>
      <div class="stay-foot">
        <div class="price">${p.oldPrice?`<span class="old-price">${fmt(p.oldPrice)}</span>`:""}<span class="amt" data-price="${p.price}">${fmt(p.price)}</span><span class="per">/night</span></div>
        <div class="btnrow" style="gap:6px">
          ${S.compare.length<3||cmp?`<button class="btn btn-ghost btn-sm" data-cmp="${p.id}">${I.scale.replace("fill=\"none\"","fill=\"none\"")}Compare</button>`:""}
          <button class="btn btn-green btn-sm" data-goto="/stay/${p.id}">View</button>
        </div>
      </div>
    </div>
  </article>`;
}

/* ---------------- calendar widget ---------------- */
function calWidget(sel, soldRanges) {
  const now=new Date();
  const calTitle=(m,y)=>new Date(m,y,1).toLocaleDateString("en-GB",{month:"long",year:"numeric"});
  const cells=(m,y)=>{
    const first=new Date(m,y,1).getDay(), days=new Date(m,y+1,0).getDate();
    let h=`${["S","M","T","W","T","F","S"].map(d=>`<div class="dow">${d}</div>`).join("")}`;
    for(let i=0;i<first;i++) h+="<span></span>";
    for(let d=1;d<=days;d++){
      const iso=`${y}-${String(m+1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;
      const past=iso<todayStr();
      const sold=(soldRanges||[]).some(r=>iso>=r[0]&&iso<=r[1]);
      let cls="cd"; if(past) cls+=" past"; if(sold) cls+=" sold"; if(iso===todayStr()) cls+=" today";
      if(sel && iso===sel.in) cls+=" edge"; else if(sel && iso===sel.out) cls+=" edge";
      else if(sel && iso>sel.in && iso<sel.out) cls+=" inrange";
      h+=`<div class="${cls}" data-d="${iso}">${d}</div>`;
    }
    return h;
  };
  const state={m:now.getMonth(),y:now.getFullYear()};
  return `<div class="cal" id="calBox">
    <div class="cals-head">
      <button class="icon-btn" data-cal="-1" aria-label="Previous month">‹</button>
      <b id="calTitle">${calTitle(state.m,state.y)}</b>
      <button class="icon-btn" data-cal="1" aria-label="Next month">›</button>
    </div>
    <div class="cal-grid" id="calGrid">${cells(state.m,state.y)}</div>
  </div>`;
}
function bindCal(sel, soldRanges, onPick) {
  const box=$("#calBox"); if(!box) return;
  const title=$("#calTitle");
  let m=box.dataset.m?+box.dataset.m:new Date().getMonth(), y=box.dataset.y?+box.dataset.y:new Date().getFullYear();
  const render=()=>{
    box.dataset.m=m; box.dataset.y=y;
    title.textContent=new Date(m,y,1).toLocaleDateString("en-GB",{month:"long",year:"numeric"});
    box.querySelectorAll("#calGrid .cd").forEach(()=>{});
    const grid=box.querySelector("#calGrid");
    const first=new Date(m,y,1).getDay(), days=new Date(m,y+1,0).getDate();
    let h=grid.querySelector(".dow")?grid.outerHTML.match(/<div class="dow">[^]*?<\/div>/g)[0]:"";
    let cell="";
    for(let i=0;i<first;i++) cell+="<span></span>";
    for(let d=1;d<=days;d++){
      const iso=`${y}-${String(m+1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;
      const past=iso<todayStr();
      const sold=(soldRanges||[]).some(r=>iso>=r[0]&&iso<=r[1]);
      let cls="cd"; if(past) cls+=" past"; if(sold) cls+=" sold"; if(iso===todayStr()) cls+=" today";
      if(sel && iso===sel.in) cls+=" edge"; else if(sel && iso===sel.out) cls+=" edge";
      else if(sel && iso>sel.in && iso<sel.out) cls+=" inrange";
      cell+=`<div class="${cls}" data-d="${iso}">${d}</div>`;
    }
    grid.innerHTML=cell;
    grid.querySelectorAll(".cd").forEach(c=>c.addEventListener("click",()=>{
      const d=c.dataset.d;
      if(!sel.in||(sel.in&&sel.out)||d<=sel.in){ sel.in=d; sel.out=null; }
      else if(d>sel.in){ sel.out=d; }
      render(); onPick&&onPick(sel);
    }));
  };
  box.querySelectorAll("[data-cal]").forEach(b=>b.addEventListener("click",()=>{ m+=+b.dataset.cal; if(m<0){m=11;y--;} if(m>11){m=0;y++;} render(); }));
  render();
}

/* ---------------- review stars ---------------- */
function stars(n, size=14) {
  let h="";
  for(let i=1;i<=5;i++) h+=`<svg viewBox="0 0 24 24" class="${i<=n?"":"off"}"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>`;
  return `<span class="stars-row">${h}</span>`;
}

/* ---------------- page scaffolding ---------------- */
function pageHead(crumbs, title, sub, actions="") {
  return `<div class="page-head"><div class="wrap">
    <div class="crumbs">${crumbs.map(c=>c[1]?`<a href="${c[1]}">${c[0]}</a>`:c[0]).join(" &nbsp;/&nbsp; ")}</div>
    <h1>${title}</h1>
    ${sub?`<p>${sub}</p>`:""}
    ${actions?`<div class="head-actions">${actions}</div>`:""}
  </div></div>`;
}

/* delegated actions (navigation for data-goto/`#/` links lives in ui.js helpers above) */
document.addEventListener("click", (e)=>{
  const hb=e.target.closest("[data-heart]");
  if(hb){ e.stopPropagation(); toggleWish(hb.dataset.heart, hb); }
  const cb=e.target.closest("[data-cmp]");
  if(cb){ e.stopPropagation(); toggleCompare(cb.dataset.cmp); return; }
  const cls=e.target.closest("[data-closeall]");
  if(cls){ closeModal(); closeSheet(); }
  const cl2=e.target.closest("[data-close]");
  if(cl2){ closeDrawer(); }
});


/* ---------------- clipboard ---------------- */
async function copyText(text, message){
  try{
    if(navigator.clipboard && window.isSecureContext){ await navigator.clipboard.writeText(text); }
    else{
      const ta=document.createElement("textarea");
      ta.value=text; ta.style.position="fixed"; ta.style.opacity="0";
      document.body.appendChild(ta); ta.select(); document.execCommand("copy"); ta.remove();
    }
    toast(message||"Copied to clipboard","share");
    return true;
  }catch(e){
    toast("Copy that manually: "+text,"share");
    return false;
  }
}


/* ===== pages-discovery.js ===== */
/* ============================================================
   JOLLOF LIVING — pages-discovery.js
   home · stays · stay / map / collections / neighborhoods / experiences
   ============================================================ */

/* ---------------- HOME ---------------- */
function pHome() {
  const featured = PROPERTIES.filter(p=>p.featured);
  return `
  <div class="hero">
    <div class="hero-bg" style="background-image:url('${img('hero')}')"></div>
    <div class="hero-tint"></div>
    <div class="hero-inner">
      <span class="eyebrow center">Luxury Living · African Soul</span>
      <h1>Exclusive residences,<br><em class="serif-i gold-text">crafted for the extraordinary</em></h1>
      <p class="sub">Lagos &amp; Abuja's finest penthouses, villas, suites and heritage homes — <b>short stays, long stays</b> and everything in between, with a concierge that never sleeps.</p>
      <div class="search-card" role="search">
        <div class="field loc">
          <label>${I.pin}Location</label>
          <select id="hLoc">
            <option value="all">Anywhere in Nigeria</option>
            ${[...new Set(PROPERTIES.map(p=>p.area))].map(a=>`<option>${a}</option>`).join("")}
          </select>
        </div>
        <div class="field">
          <label>${I.calendar}Check in</label>
          <input type="date" id="hIn" min="${todayStr()}">
        </div>
        <div class="field">
          <label>${I.calendar}Check out</label>
          <input type="date" id="hOut" min="${todayStr()}">
        </div>
        <div class="field">
          <label>${I.users}Guests</label>
          <select id="hGuests">${[1,2,3,4,5,6,7,8].map(n=>`<option>${n}</option>`).join("")}</select>
        </div>
        <div class="search-go"><button class="btn btn-gold" id="hGo">Search</button></div>
      </div>
      <div class="hero-chips">
        <button class="chip solid" data-go="/stays?tab=waterfront">🌊 Waterfront escapes</button>
        <button class="chip" data-go="/stays?tab=lastmin">⚡ Last-minute — save 15%</button>
        <button class="chip" data-go="/stays?flex=1">📅 I'm flexible</button>
        <button class="chip" data-go="/stays?loc=Eko Atlantic">✨ Eko Atlantic</button>
        <button class="chip" data-go="/experiences">🍲 Private chefs &amp; cruises</button>
      </div>
      <div class="hero-trust">
        <span>${I.shield} Escrow-protected payments</span>
        <span>${I.gold} Jollof Verified homes</span>
        <span>${I.bolt} 24/7 concierge &amp; AI support</span>
      </div>
    </div>
    <div class="scroll-hint">Scroll</div>
  </div>

  <section class="stats"><div class="wrap"><div class="grid">
    <div class="stat"><div class="num gold-text">120+</div><div class="lbl">Features</div></div>
    <div class="stat"><div class="num gold-text">25+</div><div class="lbl">AI features</div></div>
    <div class="stat"><div class="num gold-text">12</div><div class="lbl">Curated categories</div></div>
    <div class="stat"><div class="num gold-text">4.93★</div><div class="lbl">Avg. rating</div></div>
  </div></div></section>

  <section class="sec-pad" id="collections">
    <div class="wrap">
      <div class="sec-head reveal"><span class="eyebrow">Curated Collections</span>
        <h2>Stays with a <em class="serif-i">point of view</em></h2>
        <p>Waterfront escapes, sky-high penthouses, executive suites and heritage homes — each collection hand-finished by our editors.</p>
        <div style="margin-top:16px"><a class="link-arrow" href="${URL('/collections')}">Browse all ${COLLECTIONS.length}+ collections ${I.arrow}</a></div>
      </div>
      <div class="collections-grid stagger">${colCards(COLLECTIONS.slice(0,6))}</div>
    </div>
  </section>

  <section class="sec-pad alt-bg" id="featured">
    <div class="wrap">
      <div class="sec-head reveal" style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div><span class="eyebrow">Seasonal &amp; featured</span><h2>Editor's <em class="serif-i">picks</em></h2></div>
        <a class="link-arrow" href="${URL('/stays')}">View the whole collection ${I.arrow}</a>
      </div>
      <div class="stays-grid stagger">${featured.map(stayCard).join("")}</div>
    </div>
  </section>

  <section class="sec-pad" id="home-why">
    <div class="wrap">
      <div class="sec-head center reveal"><span class="eyebrow center">The Jollof Standard</span>
        <h2>Considered in every <em class="serif-i">detail</em></h2>
        <p>The operational depth of a global platform, the warmth of Nigerian hospitality.</p></div>
      <div class="why-grid stagger">
        ${[["instant","Instant Booking","Verified listings reserve in seconds — no host approval, no waiting.",""],
           ["shield","Escrow Payments","Funds held securely, released to hosts only after you confirm check-in.",""],
           ["bot","Jollof AI Concierge","Books, arranges and translates in English, Pidgin, Yoruba, Hausa, Igbo & French.","AI"],
           ["wallet","Split & Flexible Pay","−25% monthly, instalments on long stays, NGN · USD · GBP · EUR.",""],
           ["gold","Jollof Verified","In-person inspections, full KYC and AI fraud screening on every listing.",""],
           ["spark","Price-Drop Alerts","AI predicts the cheapest dates and alerts you the moment prices dip.","AI"],
           ["chatBell","24/7 Human Support","Chat, phone, WhatsApp — median first response under 3 minutes.",""],
           ["gift","Long-Stay Living","30+ night stays with digital lease agreements and housekeeping.",""]].map(w=>`
        <div class="why-card">${w[3]?`<span class="ai-pill">${w[3]}</span>`:""}<div class="why-ico">${I[w[0]]}</div><h3>${w[1]}</h3><p>${w[2]}</p></div>`).join("")}
      </div>
    </div>
  </section>

  <section class="sec-pad alt-bg" id="home-exp">
    <div class="wrap">
      <div class="sec-head reveal"><span class="eyebrow">Beyond the stay</span>
        <h2>Experiences, <em class="serif-i">arranged for you</em></h2>
        <p>Boat cruises, private chefs, spa rituals and city tours — bundled with your residence or booked alone.</p></div>
      <div class="exp-grid stagger">${EXPERIENCES.slice(0,4).map(expCard).join("")}</div>
      <div style="margin-top:22px;text-align:center"><a class="link-arrow" href="${URL('/experiences')}">All experiences ${I.arrow}</a></div>
    </div>
  </section>

  <section class="sec-pad" id="home-map">
    <div class="wrap">
      <div class="sec-head reveal"><span class="eyebrow">Explore the map</span>
        <h2>Where in <em class="serif-i">Lagos</em> will you land?</h2>
        <p>Live price pins across the city's most coveted addresses.</p></div>
      <div class="map-shell reveal">${mapSVG()}<div class="map-side">
        <h3>Neighbourhood guide</h3>
        <p class="serif-i" style="color:var(--ink-faint);font-size:14.5px;margin-bottom:8px">Average nightly rate by area</p>
        ${AREAS_LIST()}
        <div style="margin-top:16px"><a class="btn btn-ghost btn-sm" href="${URL('/neighborhoods')}">Full neighbourhood guides</a></div>
      </div></div>
    </div>
  </section>

  <section class="sec-pad alt-bg" id="home-host">
    <div class="wrap host-shell">
      <div class="reveal">
        <span class="eyebrow">Become a host</span>
        <h2 style="font-size:clamp(1.9rem,4vw,2.8rem);margin:13px 0 10px">Your home, <em class="serif-i">curated by us</em></h2>
        <p class="muted">We handle photography, pricing intelligence, guest screening and payouts — you keep 88% and the compliments.</p>
        <ul class="host-checks">
          <li>${I.checkCircle} Professional photography &amp; AI listing optimisation</li>
          <li>${I.checkCircle} Dynamic pricing, seasonal premiums &amp; calendar control</li>
          <li>${I.checkCircle} Escrow payouts &amp; automatic transfers, every week</li>
          <li>${I.checkCircle} Superhost programme, co-host tools &amp; channel sync</li>
        </ul>
        <div class="btnrow"><a class="btn btn-gold" href="${URL('/host/onboarding')}">Start hosting</a><a class="btn btn-ghost" href="${URL('/host/dashboard')}">Open host dashboard</a></div>
      </div>
      <div class="calc-card reveal">
        <h3>Earnings <em class="serif-i">estimator</em></h3>
        <div class="small" style="margin-bottom:18px">Based on listed residences in Lagos &amp; Abuja</div>
        <div class="slider-row"><div class="lab"><span>Nightly rate</span><b id="hRateVal">₦150,000</b></div>
          <input type="range" id="hRate" min="50" max="500" value="150" step="5"></div>
        <div class="slider-row"><div class="lab"><span>Occupancy</span><b id="hOccVal">65%</b></div>
          <input type="range" id="hOcc" min="30" max="95" value="65" step="1"></div>
        <div class="calc-out">
          <div class="row"><span>Average nightly</span><span id="hAvg">₦150,000</span></div>
          <div class="row"><span>Gross monthly</span><span id="hGross">₦2,925,000 / mo</span></div>
          <div class="row"><span>Host payout (88%)</span><span id="hNet">₦2,574,000 / mo</span></div>
          <div class="row total"><span>Est. annual income</span><b id="hYear">₦30,888,000</b></div>
        </div>
      </div>
    </div>
  </section>

  <section class="sec-pad" id="home-club">
    <div class="wrap">
      <div class="sec-head center reveal"><span class="eyebrow center">Jollof Club</span>
        <h2>Membership that <em class="serif-i">travels well</em></h2>
        <p>Earn Jollof Points on every stay — redeem for upgrades, transfers and experiences.</p></div>
      <div class="tiers stagger">${tierCards().slice(1,4).join("")}</div>
      <div style="text-align:center;margin-top:26px"><a class="link-arrow" href="${URL('/membership')}">Full membership details ${I.arrow}</a></div>
    </div>
  </section>

  <section class="sec-pad alt-bg" id="home-reviews">
    <div class="wrap rev-shell reveal">
      <div class="sec-head center"><span class="eyebrow center">Guest stories</span>
        <h2>Loved, <em class="serif-i">again and again</em></h2></div>
      <div class="rev-grid stagger">${TESTIMONIALS.slice(0,6).map(t=>`<figure class="rev-card"><div class="stars">${I.star.repeat(5)}</div><q>“${esc(t[2])}”</q><figcaption class="rev-who"><span class="avatar">${t[0][0]}</span><div><div class="nm">${esc(t[0])}</div><div class="st">${esc(t[1])}</div></div></figcaption></figure>`).join("")}</div>
    </div>
  </section>

  <section class="sec-pad" id="home-blog">
    <div class="wrap">
      <div class="sec-head reveal" style="display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap">
        <div><span class="eyebrow">The Journal</span><h2>Guides &amp; <em class="serif-i">culture</em></h2></div>
        <a class="link-arrow" href="${URL('/blog')}">All stories ${I.arrow}</a>
      </div>
      <div class="grid-3 stagger">${BLOG.slice(0,3).map(postCard).join("")}</div>
    </div>
  </section>

  <section class="sec-pad alt-bg" id="home-app">
    <div class="wrap app-grid">
      <div class="reveal">
        <span class="eyebrow">On the go</span>
        <h2 style="font-size:clamp(1.8rem,3.6vw,2.7rem);margin:12px 0 10px">The Jollof Living <em class="serif-i">app</em></h2>
        <p class="muted" style="max-width:52ch">Keyless check-in, live chat with hosts, trip dashboards, wallet passes, voice booking with Siri and Google Assistant — everything, in your pocket.</p>
        <div class="btnrow" style="margin-top:20px"><a class="btn btn-ghost" href="${URL('/app')}">Explore app features</a><a class="btn btn-gold" href="${URL('/account')}">Open your account</a></div>
      </div>
      <div class="reveal" style="justify-self:end">
        ${phoneMock()}
      </div>
    </div>
  </section>`;
}
function bindHome() {
  const hGo=$("#hGo");
  hGo.addEventListener("click", ()=>{
    const loc=$("#hLoc").value, g=$("#hGuests").value, din=$("#hIn").value, dout=$("#hOut").value;
    nav("/stays?loc="+encodeURIComponent(loc)+"&g="+g+(din?"&in="+din+"&out="+dout:""));
  });
  $$(".hero .chip").forEach(c=>c.addEventListener("click",()=>nav(c.dataset.go)));
  const rcalc=()=>{ const rate=+$("#hRate").value, occ=+$("#hOcc").value;
    const m=rate*30*occ/100, g=m*12;
    $("#hRateVal").textContent="\u20A6"+(rate*1000).toLocaleString();
    $("#hOccVal").textContent=occ+"%";
    ["#hRate","#hOcc"].forEach(s=>{const el=$(s); el.style.setProperty("--fill",((el.value-el.min)/(el.max-el.min)*100)+"%");});
    $("#hGross").textContent="\u20A6"+Math.round(m).toLocaleString()+" / mo";
    $("#hNet").textContent="\u20A6"+Math.round(m*0.88).toLocaleString()+" / mo";
    $("#hYear").textContent="\u20A6"+Math.round(g*0.88).toLocaleString()+" / yr";
    $("#hAvg").textContent="\u20A6"+Math.round(rate*1000).toLocaleString()+" / night";
  };
  ["#hRate","#hOcc"].forEach(s=>$(s).addEventListener("input",rcalc));
  rcalc();
  /* The home page also renders collection tiles, so they need the same
     binding the dedicated collections page uses -- without this they look
     clickable but do nothing. */
  bindCollections();
  /* NOTE: no auto-scroll, no auto-advancing carousels — the page never moves by itself. */
}

/* ---------------- STAYS ---------------- */
function pStays(q) {
  const f={ loc:"all", guests:1, price:500, type:"all", beds:1, instant:false, flex:false, tab:"all", sort:"rec" };
  if(q.loc) f.loc=q.loc; if(q.g) f.guests=q.g; if(q.type) f.type=q.type; if(q.instant) f.instant=true;
  if(q.flex) f.flex=true; if(q.tab) f.tab=q.tab;
  S.filters=f;
  return `
  <div class="page-top"></div>
  <div class="page-head"><div class="wrap">
    <div class="crumbs"><a href="${URL('/')}">Home</a> / Stays</div>
    <h1>The <em class="serif-i">Collection</em></h1>
    <p>Every residence is inspected, verified and dressed to a standard — before it ever reaches you. Filter by location, price, dates, type and amenities.</p>
  </div></div>
  <div class="page-body"><div class="wrap">

    <div class="panel" style="margin-bottom:26px" id="filterPanel">
      <div class="frm-grid">
        <div class="frm-row"><label>Location</label><select class="sel" id="fLoc">
          <option value="all">Anywhere in Nigeria</option>
          ${[...new Set(PROPERTIES.map(p=>[p.area,p.city]))].map(([a,c])=>`<option>${a} · ${c}</option>`).join("")}</select></div>
        <div class="frm-row"><label>Property type</label><select class="sel" id="fType">
          <option value="all">All types</option><option>Penthouse</option><option>Villa</option><option>Suite</option><option>Loft</option><option>Furnished Townhouse</option></select></div>
        <div class="frm-row"><label>Check-in</label><input type="date" class="inp" id="fIn" min="${todayStr()}"></div>
        <div class="frm-row"><label>Check-out</label><input type="date" class="inp" id="fOut" min="${todayStr()}"></div>
        <div class="frm-row"><label>Guests</label><select class="sel" id="fGuests">${[1,2,3,4,5,6,7,8].map(n=>`<option>${n}</option>`).join("")}</select></div>
        <div class="frm-row"><label>Bedrooms min.</label><select class="sel" id="fBeds"><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
      </div>
      <div class="frm-row" style="margin-top:6px"><label>Max price / night — <b id="fPriceVal" style="color:var(--accent)">₦500,000</b></label>
        <input type="range" id="fPrice" min="90" max="500" value="${f.price}" step="5"></div>
      <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-top:10px">
        <label class="chk"><input type="checkbox" id="fInstant"> Instant Book only</label>
        <label class="chk"><input type="checkbox" id="fFlex"> I'm flexible — cheapest dates</label>
        <div class="spacer" style="flex:1"></div>
        <button class="btn btn-gold btn-sm" id="fGo">Search</button>
        <button class="btn btn-ghost btn-sm" id="fClear">Reset</button>
      </div>
    </div>

    <div class="tabs" style="margin-bottom:18px">
      <button class="tab ${f.tab==="all"?"active":""}" data-tab="all">All</button>
      <button class="tab ${f.tab==="waterfront"?"active":""}" data-tab="waterfront">Waterfront</button>
      <button class="tab ${f.tab==="penthouse"?"active":""}" data-tab="penthouse">Penthouse &amp; Sky</button>
      <button class="tab ${f.tab==="abuja"?"active":""}" data-tab="abuja">Abuja Executive</button>
      <button class="tab ${f.tab==="verified"?"active":""}" data-tab="verified">Jollof Verified</button>
      <button class="tab ${f.tab==="lastmin"?"active":""}" data-tab="lastmin">Last-Minute Deals</button>
      <button class="tab ${f.tab==="new"?"active":""}" data-tab="new">New This Season</button>
    </div>

    <div class="filter-bar">
      <div class="results-note" id="staysNote" style="margin:0"></div>
      <div class="spacer"></div>
      <div class="sort">Sort
        <select class="select-pill" id="fSort"><option value="rec">Recommended</option><option value="price-asc">Price ↑</option><option value="price-desc">Price ↓</option><option value="rating">Top rated</option><option value="new">Newest</option></select>
      </div>
      <div class="tabs" style="display:inline-flex"><button class="tab ${f.view!=="map"?"active":""}" id="vList">${I.grid} List</button><button class="tab ${f.view==="map"?"active":""}" id="vMap">${I.pin} Map</button></div>
    </div>

    <div id="staysWrap"><div class="stays-grid" id="staysGrid">${filteredStays().map(stayCard).join("")}</div></div>
    <div id="mapWrap" style="display:none" class="mtl"><div class="map-shell" style="margin-top:22px">${mapSVG()}
      <div class="map-side"><h3>Results by area</h3>${AREAS_LIST()}</div></div></div>
  </div></div>`;
}
function filteredStays() {
  const f=S.filters;
  let list=[...PROPERTIES];
  if(f.loc&&f.loc!=="all") list=list.filter(p=>f.loc.includes(p.area)||f.loc.includes(p.city));
  if(f.guests>1) list=list.filter(p=>p.guests>=f.guests);
  if(f.beds>1) list=list.filter(p=>p.beds>=f.beds);
  if(f.price) { const mx=f.price*1000; list=list.filter(p=>p.price<=mx); }
  if(f.type!=="all") list=list.filter(p=>p.type.includes(f.type)||p.type.toLowerCase().includes(f.type.toLowerCase()));
  if(f.instant) list=list.filter(p=>p.instant);
  if(f.tab==="waterfront") list=list.filter(p=>p.amens.some(a=>/pool|ocean|lagoon|frontage/i.test(a)));
  else if(f.tab==="penthouse") list=list.filter(p=>/penthouse|terrace|loft|sky/i.test(p.type));
  else if(f.tab==="abuja") list=list.filter(p=>p.city==="Abuja");
  else if(f.tab==="verified") list=list.filter(p=>p.badge.includes("Verified"));
  else if(f.tab==="new") list=list.filter(p=>p.new);
  else if(f.tab==="lastmin") list=list.filter(p=>p.badge==="Last-Minute Deal"||p.oldPrice);
  if(f.sort==="price-asc") list.sort((a,b)=>a.price-b.price);
  if(f.sort==="price-desc") list.sort((a,b)=>b.price-a.price);
  if(f.sort==="rating") list.sort((a,b)=>b.rating-a.rating);
  if(f.sort==="new") list.sort((a,b)=>(b.new?1:0)-(a.new?1:0));
  return list;
}
function bindStays() {
  const q=qps()||{};
  if(q.flex) $("#fFlex").checked=true;
  if(q.instant) $("#fInstant").checked=true;
  const sync=()=>{
    const f=S.filters;
    $("#fLoc").value=f.loc; $("#fType").value=f.type; $("#fGuests").value=f.guests; $("#fBeds").value=f.beds;
    $("#fPrice").value=f.price; $("#fPriceVal").textContent=fmt(f.price*1000);
  };
  sync();
  const re=()=>{ $("#staysGrid").innerHTML=filteredStays().map(stayCard).join(""); note(); };
  const note=()=>{ const l=filteredStays(); $("#staysNote").innerHTML=`${l.length} exclusive ${l.length===1?"residence":"residences"} · updated with your filters`; };
  note();
  $("#fGo").addEventListener("click",()=>{
    const f=S.filters;
    f.loc=$("#fLoc").value; f.type=$("#fType").value; f.guests=+$("#fGuests").value; f.beds=+$("#fBeds").value;
    f.price=+$("#fPrice").value; f.instant=$("#fInstant").checked; f.flex=$("#fFlex").checked;
    const di=$("#fIn").value, dO=$("#fOut").value; if(di&&dO) S.searchDates={in:di,out:dO};
    re();
    if(f.flex) toast("Cheapest dates suggested — Tuesday arrivals save ~15% on average","spark");
    else if(di&&dO) toast(`Checking availability for ${di} → ${dO}`,"calendar");
  });
  $("#fClear").addEventListener("click",()=>{ S.filters={loc:"all",guests:1,price:500,type:"all",beds:1,instant:false,flex:false,tab:"all",sort:"rec"}; sync(); re(); });
  $("#fPrice").addEventListener("input",e=>$("#fPriceVal").textContent=fmt(e.target.value*1000));
  $$("#stays #fSort").forEach(s=>{});
  const sort=$("#fSort"); if(sort) sort.addEventListener("change",()=>{ S.filters.sort=sort.value; re(); });
  $$(".tabs .tab").forEach(t=>t.addEventListener("click",()=>{
    S.filters.tab=t.dataset.tab;
    $$(".tabs .tab").forEach(x=>x.classList.toggle("active",x===t));
    re();
    if(t.dataset.tab!=="all") $("#stays").scrollIntoView({behavior:"smooth"});
  }));
  const vList=$("#vList"), vMap=$("#vMap");
  vList.addEventListener("click",()=>{ $("#staysWrap").style.display=""; $("#mapWrap").style.display="none"; vList.classList.add("active"); vMap.classList.remove("active"); });
  vMap.addEventListener("click",()=>{ $("#staysWrap").style.display="none"; $("#mapWrap").style.display="block"; vMap.classList.add("active"); vList.classList.remove("active"); });
}

/* ---------------- STAY DETAIL ---------------- */
function pStay(id) {
  const p=PROPERTIES.find(x=>x.id===id); if(!p) return p404();
  recentAdd(p.id);
  const n=3, m=priceMath(p,n);
  const sold=tourRanges(p);
  return `
  <div class="page-head"><div class="wrap">
    <div class="crumbs"><a href="${URL('/')}">Home</a> / <a href="${URL('/stays')}">Stays</a> / ${esc(p.area)}</div>
    <div class="breadcrumb-bar" style="margin-bottom:0">
      <div><h1 style="margin-bottom:6px">${esc(p.name)}</h1>
      <div class="sd-meta" style="margin:0">
        <span class="rt"><b>${I.star} ${p.rating.toFixed(2)}</b> · ${p.reviews} reviews</span>
        <span>${I.pin}${esc(p.area)}, ${esc(p.city)}</span>
        <span class="badge ${p.badgeGold?"":""}">${p.badgeGold?I.gold:I.shield} ${esc(p.badge)}</span>
        ${p.instant?'<span class="badge ok">'+I.bolt+' Instant Book</span>':'<span class="badge warn">'+I.clock+' Request to Book</span>'}
      </div></div>
      <div class="btnrow" style="margin-left:auto">
        <button class="btn btn-ghost btn-sm" data-heart="${p.id}">${I.heart} Save</button>
        <button class="btn btn-ghost btn-sm" data-cmp="${p.id}">${I.scale} Compare</button>
        <button class="btn btn-ghost btn-sm" onclick="shareStay('${p.id}')">${I.share} Share</button>
      </div>
    </div>
  </div></div>
  <div class="page-body"><div class="wrap">
    <div class="sd-gallery">
      <div class="sd-main" id="sdMain" style="background-image:url('${img(p.img)}')"></div>
      <div class="sd-tags">
        ${p.tour?'<span class="tag" style="background:rgba(10,12,9,.6);color:#f0ead8;backdrop-filter:blur(8px)">'+I.eye+' Virtual tour available</span>':""}
        ${p.soldOut?'<span class="tag" style="background:rgba(180,68,58,.8);color:#fff">'+I.clock+' Waitlist open for holidays</span>':""}
      </div>
      <div class="sd-thumbs" id="sdThumbs">
        ${galleryKeys(p).map((k,i)=>`<button class="${i===0?"on":""}" data-sd="${i}"><img src="${img(k)}" alt=""></button>`).join("")}
        <button data-sd="360" onclick="openVirtualTour('${p.id}')" style="opacity:.9"><span style="display:grid;place-items:center;height:100%;color:#fff;background:rgba(0,0,0,.35);font-size:11px;letter-spacing:.08em">360°</span></button>
      </div>
    </div>

    <div class="sd-layout">
      <div>
        <div class="sd-sec" style="margin-top:0"><h3>${I.book} About this residence</h3>
          <p class="sd-desc">${esc(p.desc)}</p>
          <div class="panel" style="margin-top:16px;display:flex;gap:26px;flex-wrap:wrap">
            <div><div class="small">Type</div><b>${esc(p.type)}</b></div>
            <div><div class="small">Sleeps</div><b>${p.guests} guests</b></div>
            <div><div class="small">Bedrooms</div><b>${p.beds}</b></div>
            <div><div class="small">Bathrooms</div><b>${p.baths}</b></div>
            <div><div class="small">Floor plan</div><b>${esc(p.floor)}</b></div>
            <div><div class="small">Policy</div><b>${p.policy[0].toUpperCase()+p.policy.slice(1)}</b></div>
            ${p.tour?'<div><div class="small">Tour</div><b>360° virtual walkthrough</b></div>':""}
          </div>
        </div>

        <div class="sd-sec"><h3>${I.gift} Amenities &amp; smart features</h3>
          <div class="amen-grid">${p.amens.map(a=>`<span class="amen">${I.checkCircle}${esc(a)}</span>`).join("")}</div>
        </div>

        <div class="sd-sec"><h3>${I.camera} Floor plan</h3>
          <div class="panel" style="padding:10px">${floorPlan(p)}</div>
        </div>

        <div class="sd-sec"><h3>${I.star} Guest reviews</h3>
          <div class="ai-callout">${I.spark}<span><b>AI summary:</b> ${esc(p.aiSummary)}</span></div>
          <div class="panel" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
            <div style="text-align:center;min-width:110px"><div style="font-family:var(--fs-serif);font-size:52px;font-weight:600;line-height:1">${p.rating.toFixed(2)}</div>${stars(5,12)}<div class="small">${p.reviews} reviews</div></div>
            <div class="rev-scores" style="flex:1;min-width:260px;margin:0">
              ${Object.entries(p.scores).map(([k,v])=>`<div class="rev-score"><div class="s">${v.toFixed(1)}</div><div class="l">${({c:"Cleanliness",a:"Accuracy",com:"Communication",loc:"Location",ci:"Check-in",v:"Value"})[k]}</div></div>`).join("")}
            </div>
          </div>
          <div style="margin-top:16px">${p.reviewsList.map(r=>`<div class="rev-mini"><div class="h"><span class="nm"><span class="avatar">${r[0][0]}</span>${esc(r[0])}</span><span class="dt">${esc(r[1])}</span></div><p>“${esc(r[2])}”</p></div>`).join("")}
          <a class="link-arrow" style="margin-top:14px" href="${URL('/reviews')}">Read the AI analysis of all ${p.reviews} reviews ${I.arrow}</a></div>
        </div>

        <div class="sd-sec"><h3>${I.pin} Neighbourhood highlights</h3>
          <div class="panel">${p.nearby.map(n=>`<div class="nearby-row"><span>${I.pin}${esc(n[0])}</span><span class="d">${esc(n[1])}</span></div>`).join("")}
          <a class="link-arrow" style="margin-top:12px" href="${URL('/neighborhoods')}">Full neighbourhood guide ${I.arrow}</a></div>
        </div>

        <div class="sd-sec"><h3>${I.book} House rules</h3>
          <ul class="rules-list">
            <li>${I.check} Check-in from 3pm · keyless smart-code entry</li>
            <li>${I.check} ${p.guests>4?"Intimate events welcome by arrangement":"Quiet hours after 10pm · no parties"}</li>
            <li>${I.check} No smoking indoors · pets on request</li>
            <li>${I.check} ${p.instant?"Instant booking — reserve now":"Host confirmation within 24 hours"} · ${p.policy} cancellation</li>
            <li>${I.check} Safety: smoke detectors, fire extinguisher, first-aid kit</li>
          </ul>
        </div>

        <div class="sd-sec"><h3>${I.users} Your host</h3>
          <div class="host-card">
            <div class="avatar">T</div>
            <div class="inf"><div class="nm">Tunde Bakare ${I.gold} Superhost</div>
            <div class="st">Verified · KYC complete · replies in ~5 min · 4 listings</div></div>
            <button class="btn btn-ghost btn-sm" data-goto="/messages?to=team-onyx">${I.chat} Message</button>
          </div>
        </div>
      </div>

      <div>
        <div class="book-card" id="bookCard">
          <div class="pr"><span class="amt" data-price="${p.price}">${fmt(p.price)}</span><span class="per">/ night</span>${p.oldPrice?`<span class="old-price">${fmt(p.oldPrice)}</span>`:""}</div>
          ${p.soldOut?`<div class="store-note">${I.clock}<span><b>Waitlist open</b> — ${esc(p.soldOut.replace("/"," – "))} is fully booked. Join the waitlist and we'll alert you instantly.</span></div>`:""}
          <div style="margin-bottom:12px">${calWidget({},sold)}</div>
          <div class="book-dates" style="margin:10px 0 4px">
            <div class="fld"><label>Check-in</label><input type="date" id="dIn" min="${todayStr()}" value="${todayStr(7)}"></div>
            <div class="fld"><label>Check-out</label><input type="date" id="dOut" min="${todayStr(7)}" value="${todayStr(10)}"></div>
          </div>
          <div class="guest-row" style="display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:12px;padding:10px 13px;margin-bottom:10px">
            <div class="small">Guests</div>
            <div class="stepper">
              <button onclick="gDec()" ${""}>${I.minus}</button><b id="gNum">2</b><button onclick="gInc()">${I.plus}</button>
            </div>
          </div>
          <label class="chk" style="margin-bottom:10px"><input type="checkbox" id="dFlex"> I'm flexible — show cheapest dates</label>
          <select class="sel" style="margin-bottom:10px" id="dPolicy">
            <option value="flexible">Flexible — full refund up to 48h</option>
            <option value="moderate" ${p.policy==="moderate"?"selected":""}>Moderate — full refund up to 5 days</option>
            <option value="strict" ${p.policy==="strict"?"selected":""}>Strict — 50% refund up to 14 days</option>
          </select>
          <div class="breakdown" id="dBreak">${breakdownHTML(p,{in:todayStr(7),out:todayStr(10),n:(()=>3)(),guests:2})}</div>
          <div class="book-actions">
            ${p.instant
              ?`<button class="btn btn-gold btn-block" data-goto="/booking/${p.id}">${I.bolt} Reserve — Instant Book</button>`
              :`<button class="btn btn-gold btn-block" data-goto="/booking/${p.id}?req=1">${I.send} Request to Book</button>`}
          </div>
          <div class="escrow-note">${I.shield}<span>Payment held in escrow and released to the host only after you confirm check-in. You won't be charged yet.</span></div>
          <div class="alt-btns">
            <button onclick="joinWait('${p.id}')">${I.clock} Waitlist</button>
            <button onclick="gcalStay('${p.id}')">${I.calendar} Calendar</button>
            <button onclick="shareStay('${p.id}')">${I.share} Share</button>
          </div>
        </div>
        <div class="ai-callout" style="margin-top:16px">${I.spark}<span><b>AI price watch:</b> this residence is ${p.oldPrice?`<b>${Math.round((1-p.price/p.oldPrice)*100)}% below</b> its recent high`:"priced at market"} — Tuesday arrivals typically save ~15%.</span></div>
        <div class="panel" style="margin-top:16px">
          <b style="font-family:var(--fs-serif);font-size:17px">Why stay here</b>
          <div style="margin-top:10px">${[I.gold+" Jollof Verified quality",I.shield+" Escrow-protected payment",I.bolt+" 24/7 concierge + AI",I.wallet+" Split pay on long stays"].map(x=>`<div class="krow"><span class="k">${x}</span></div>`).join("")}</div>
        </div>
      </div>
    </div>
  </div></div>`;
}
function breakdownHTML(p,o) {
  const m=priceMath(p,o.n||3);
  return `
    <div class="brow"><span>${fmt(m.nightly)} × ${o.n} ${m.monthly?"nights (monthly −25%)":m.weekly?"nights (weekly −12%)":"nights"}</span><span>${fmt(m.subtotal)}</span></div>
    ${o.flex?`<div class="brow"><span>Cheapest nearby dates (flexible)</span><span class="free">${fmt(Math.round(p.price*0.85))} avg</span></div>`:""}
    <div class="brow"><span>Cleaning fee</span><span>${fmt(RATES.cleaning)}</span></div>
    <div class="brow"><span>Service fee (8%)</span><span>${fmt(m.svc)}</span></div>
    <div class="brow"><span>VAT (7.5%)</span><span>${fmt(m.vat)}</span></div>
    <div class="brow"><span>Security deposit (held)</span><span>${fmt(m.deposit)}</span></div>
    <div class="brow total"><span>Total</span><b>${fmt(m.total)}</b></div>`;
}
function bindStay(id) {
  const p=PROPERTIES.find(x=>x.id===id);
  const sold=tourRanges(p);
  const guestState={n:2};
  window.gDec=()=>{ if(guestState.n>1){guestState.n--; $("#gNum").textContent=guestState.n;} };
  window.gInc=()=>{ if(guestState.n<p.guests){guestState.n++; $("#gNum").textContent=guestState.n;} };
  const sel={in:$("#dIn").value,out:$("#dOut").value};
  const refresh=()=>{
    const n=nightsBetween(sel.in,sel.out)||3;
    sel.in=sel.in||todayStr(7); sel.out=sel.out||todayStr(10);
    $("#dBreak").innerHTML=breakdownHTML(p,{n,guests:guestState.n,flex:$("#dFlex").checked});
  };
  bindCal(sel,sold,refresh);
  ["#dIn","#dOut"].forEach(s=>{ $(s).addEventListener("change",e=>{ if(s==="#dIn")sel.in=e.target.value; else sel.out=e.target.value; refresh(); }); });
  $("#dFlex").addEventListener("change",refresh);
  /* thumbs */
  $$("#sdThumbs [data-sd]").forEach(b=>b.addEventListener("click",()=>{
    if(b.dataset.sd==="360") return;
    const path=galleryKeys(p)[+b.dataset.sd];
    $("#sdMain").style.backgroundImage=`url('${img(path)}')`;
    $$("#sdThumbs button").forEach((x,i)=>x.classList.toggle("on",i===+b.dataset.sd));
  }));
}
function tourRanges(p){ return p.soldOut?[p.soldOut.split("/")]:[]; }
function openVirtualTour(id){
  const p=PROPERTIES.find(x=>x.id===id);
  openModal(`<h2 style="margin-bottom:4px">360° virtual tour</h2>
    <p class="small" style="margin-bottom:16px">${esc(p.name)} · drag to look around, scroll to zoom. Drone footage of the exterior available after booking.</p>
    <div style="position:relative;border-radius:16px;overflow:hidden;aspect-ratio:16/9;background:var(--bg-3);cursor:grab" id="tourStage">
      <div id="tourPano" style="position:absolute;inset:-40%;background-image:url('${img(p.img)}');background-size:cover;transition:transform .1s linear"></div>
      <div style="position:absolute;left:14px;bottom:14px;background:rgba(10,12,9,.6);color:#f0ead8;padding:8px 14px;border-radius:999px;font-size:12px" id="tourHint">Drag to explore · wheel to zoom</div>
    </div>
    <div class="btnrow" style="margin-top:16px"><button class="btn btn-green" onclick="closeModal();openVirtualTour('${p.id}')">Replay</button><button class="btn btn-ghost" onclick="closeModal()">Close</button></div>`);
  const stage=$("#tourStage"), pano=$("#tourPano");
  let tx=0,ty=0,z=1.4,drag=false,px=0,py=0;
  stage.addEventListener("mousedown",e=>{drag=true;px=e.clientX;py=e.clientY;});
  addEventListener("mouseup",()=>drag=false);
  addEventListener("mousemove",e=>{ if(!drag)return; tx+=(e.clientX-px)*0.4; ty+=(e.clientY-py)*0.4; px=e.clientX; py=e.clientY; pano.style.transform=`translate(${tx}px,${ty}px) scale(${z})`; });
  stage.addEventListener("wheel",e=>{e.preventDefault(); z=Math.min(3,Math.max(1,z+(e.deltaY<0?0.1:-0.1))); pano.style.transform=`translate(${tx}px,${ty}px) scale(${z})`;},{passive:false});
}
function floorPlan(p){
  const rooms=[["Living",10,70,120,74],["Kitchen",10,154,54,40],["Master bed",150,70,110,70],["Bed 2",150,150,84,58],["Bed 3",244,150,0,0],["Baths",244,70,64,50]].filter(r=>r[4]);
  return `<svg viewBox="0 0 330 240" style="width:100%;max-width:520px;display:block;margin:0 auto">
    <rect x="8" y="8" width="314" height="224" rx="10" fill="none" stroke="var(--accent)" stroke-width="1.6"/>
    ${rooms.map(r=>`<rect x="${r[1]}" y="${r[2]}" width="${r[3]}" height="${r[4]}" rx="6" fill="var(--gold-soft)" stroke="var(--accent)" stroke-opacity=".5"/><text x="${r[1]+r[3]/2}" y="${r[2]+r[4]/2+4}" text-anchor="middle" font-size="10.5" font-family="Jost,sans-serif" fill="var(--ink-soft)">${r[0]}</text>`).join("")}
    <text x="165" y="28" text-anchor="middle" font-size="11" font-family="Jost,sans-serif" letter-spacing="2" fill="var(--ink-faint)">${esc(p.floor)} · ~${p.beds*95+p.guests*8} m²</text>
  </svg>`;
}
function shareStay(id){ const p=PROPERTIES.find(x=>x.id===id);
  const txt=`${p.name} — ${fmt(p.price)}/night on Jollof Living. ${p.desc.slice(0,90)}…`;
  if(navigator.share){ navigator.share({title:p.name,text:txt}).catch(()=>{}); }
  else { navigator.clipboard&&navigator.clipboard.writeText(txt+" jollofliving.com"); toast("Stay details copied — ready to share","share"); }
}
function gcalStay(id){ const p=PROPERTIES.find(x=>x.id===id);
  const url=`https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent("Stay — "+p.name)}&dates=${todayStr(7).replace(/-/g,"")}/${todayStr(10).replace(/-/g,"")}&details=${encodeURIComponent(p.name+" via Jollof Living")}&location=${encodeURIComponent(p.area+", "+p.city)}`;
  window.open(url,"_blank"); toast("Google Calendar opened — save the event there to add it to your calendar","calendar");
}
async function joinWait(id){
  const p=PROPERTIES.find(x=>x.id===id)||{};
  if(!requireAuth("join the waitlist")) return;
  const r=await api("waitlist.php",{property:id});
  if(!r.ok){ toast(r.message||"Could not join the waitlist","x"); return; }
  await syncState(false);
  toast(r.message||`You're on the waitlist for ${p.name} — we'll alert you the moment dates open`,"clock");
}

/* ---------------- MAP ---------------- */
const PIN_POS={ "ocean-spearl":[372,205],"villa-azur":[300,262],"sky-garden":[430,172],"onyx":[500,300],"atelier-loft":[236,258],"heritage-house":[462,208],"lagoon-villa":[338,235],"maitama":[540,60],"lagoon-duplex":[510,318],"eko-icon":[250,290],"abuja-sky":[560,40],"island-retreat":[316,246] };
function mapSVG(){
  return `<div class="map-wrap">
    <svg viewBox="0 0 620 340" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Map of Lagos with property price pins">
      <g>
        <path d="M0 0h620v340H340C240 280 210 220 240 150 260 90 220 40 120 0H0z" fill="var(--map-land)"/>
        <path d="M0 0h120C240 60 260 100 240 170c-20 60 30 100 100 170H0z" fill="var(--map-water)"/>
        <path d="M300 262c30-14 44-38 40-64 12 6 28 10 44 10" fill="none" stroke="var(--map-water2)" stroke-width="1.6" stroke-dasharray="4 4"/>
      </g>
      <g font-size="13.5" font-family="Jost,sans-serif" fill="var(--map-text)" opacity=".9">
        <text x="70" y="180" transform="rotate(-64 70 180)">LAGOS LAGOON</text>
        <text x="392" y="120">IKOYI</text><text x="352" y="322">LEKKI PHASE 1</text>
        <text x="150" y="318">EKO ATLANTIC</text><text x="318" y="168">VICTORIA ISLAND</text>
      </g>
      <g>
        <rect x="460" y="12" width="140" height="62" rx="12" fill="var(--map-card)" stroke="var(--map-card-stroke)" stroke-width="1"/>
        <text x="530" y="34" text-anchor="middle" font-size="10.5" letter-spacing="2.5" fill="var(--map-text)" font-family="Jost,sans-serif">ABUJA</text>
      </g>
      ${Object.entries(PIN_POS).map(([id,[x,y]])=>{ const p=PROPERTIES.find(q=>q.id===id); if(!p) return "";
        return `<g class="map-pin" transform="translate(${x},${y})" data-goto="/stay/${id}" data-tip="${esc(p.name)}">
        <circle r="9" fill="#c9a227" stroke="var(--map-pin-stroke)" stroke-width="2.5"/>
        <circle r="16" fill="none" stroke="#c9a227" stroke-opacity=".35" stroke-width="1.5">
          <animate attributeName="r" values="12;20;12" dur="3s" repeatCount="indefinite"/>
          <animate attributeName="stroke-opacity" values=".4;0;.4" dur="3s" repeatCount="indefinite"/>
        </circle>
        <rect x="0" y="18" width="0" height="0" fill="none"/>
        <g transform="translate(0,16)"><rect x="-33" y="0" width="66" height="21" rx="10" fill="var(--map-card)" stroke="#c9a227" stroke-width="1"/><text x="0" y="14.5" text-anchor="middle" font-size="11" font-family="Jost,sans-serif" font-weight="600" fill="#8a6a1f">${K(p.price)}</text></g>
      </g>`;}).join("")}
    </svg>
    <div class="map-tip" id="mapTip"></div>
  </div>`;
}
function AREAS_LIST(){
  const byArea=(area)=>{ const l=PROPERTIES.filter(p=>p.area.includes(area)||p.city.includes(area)); return {n:l.length, avg: l.length?Math.round(l.reduce((a,p)=>a+p.price,0)/l.length):0, ids:l.slice(0,1)[0]?l[0].id:null}; };
  return [
    ["Victoria Island","ocean-spearl"],["Banana Island","villa-azur"],["Ikoyi","sky-garden"],
    ["Lekki Phase 1","onyx"],["Eko Atlantic","atelier-loft"],["Maitama · Abuja","maitama"],
  ].map(([a,id])=>{ const d=byArea(a); return `<div class="area-row" data-goto="/stay/${id}"><span class="dot"></span><span class="nm">${a}</span><span class="ct">${d.n} stays</span><span class="pr">${K(d.avg)}</span></div>`; }).join("");
}
function bindMap(){
  const svg=$(".map-wrap svg"); if(!svg) return;
  svg.addEventListener("mousemove",e=>{ const t=e.target.closest(".map-pin"); const tip=$("#mapTip");
    if(t&&tip){ const r=svg.getBoundingClientRect();
      tip.innerHTML=`<b>${t.dataset.tip}</b>Tap to view`;
      tip.style.left=(e.clientX-r.left)+"px"; tip.style.top=(e.clientY-r.top)+"px"; tip.style.opacity=1;
    } else if(tip) tip.style.opacity=0;
  });
}

/* ---------------- COLLECTIONS ---------------- */
function pCollections(){
  return `${pageHead([["Home",URL("/")],["Collections"]],"Curated <em class='serif-i'>collections</em>","Twelve editorially curated ways to fall in love with a city — from waterfront escapes to cultural heritage stays.")}
  <div class="page-body"><div class="wrap">
    <div class="collections-grid stagger" style="grid-template-columns:repeat(4,1fr)">${COLLECTIONS.map(c=>`<div class="col-card" data-gocol="${c.id}"><div class="img" style="background-image:url('${img(c.img)}')"></div><div class="veil"></div><div class="n">${COLLECTION_MAP[c.id].length} stays</div><div class="meta"><h3>${esc(c.name)}</h3><p>${esc(c.sub)}</p></div></div>`).join("")}</div>
  </div></div>`;
}
function bindCollections(){
  $$("[data-gocol]").forEach(c=>c.addEventListener("click",()=>{
    nav("/stays?tab=col-"+c.dataset.gocol);
  }));
}

/* ---------------- NEIGHBORHOODS ---------------- */
function pNeighborhoods(){
  return `${pageHead([["Home",URL("/")],["Neighbourhood guides"]],"Neighbourhood <em class='serif-i'>guides</em>","Restaurants, nightlife, safety tips and transport — the insider's view of every address we serve.", `<button class="btn btn-gold" data-goto="/stays">Browse stays in these areas</button>`)}
  <div class="page-body"><div class="wrap">
    <div class="grid-3 stagger">${NEIGHBORHOODS.map(n=>`
      <div class="neigh-card" data-goto="/neighborhood/${n.id}">
        <div class="bg" style="background-image:url('${img(n.img)}')"></div><div class="veil"></div>
        <div class="txt"><div class="small" style="color:#e0d3a4">From ${K(n.avg)}/night · ${n.stays} stays</div><h3>${esc(n.name)}</h3><p style="font-size:13px;color:#ddd5bd">${esc(n.tag)}</p></div>
      </div>`).join("")}
    </div>
  </div></div>`;
}
function pNeighborhood(id){
  const n=NEIGHBORHOODS.find(x=>x.id===id)||NEIGHBORHOODS[0];
  const si=(l)=>`<ul class="list-non" style="list-style:none;display:grid;gap:8px;font-size:14px;color:var(--ink-soft)">${l.map(x=>`<li style="display:flex;gap:9px"><span style="color:var(--accent)">◆</span>${esc(x)}</li>`).join("")}</ul>`;
  return `${pageHead([["Home",URL("/")],["Neighbourhood guides",URL("/neighborhoods")],[n.name]],n.name,`${esc(n.tag)} · from ${K(n.avg)}/night across ${n.stays} stays`)}
  <div class="page-body"><div class="wrap">
    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:26px" class="grid-2m">
      <div class="stack">
        <div class="panel"><h3>About ${esc(n.name)}</h3><p class="muted" style="font-size:15px">${esc(n.desc)}</p></div>
        <div class="panel"><h3>${I.wine} Where to eat</h3>${si(n.dining)}</div>
        <div class="panel"><h3>${I.moon} Nightlife</h3>${si(n.night)}</div>
        <div class="panel"><h3>${I.camera} Culture &amp; sights</h3>${si(n.culture)}</div>
      </div>
      <div class="stack">
        <div class="panel"><h3>${I.car} Getting around</h3>${si(n.transport)}</div>
        <div class="panel"><h3>${I.shield} Safety notes</h3>${si(n.safety)}</div>
        <div class="panel"><h3>${I.pin} Homes in ${esc(n.name)}</h3>
          ${PROPERTIES.filter(p=>p.area.includes(n.name.slice(0,6))||p.city===n.name||n.name.includes(p.area.slice(0,6)) ).map(p=>`<div class="krow"><span class="k">${esc(p.name)}</span><a class="v" style="color:var(--accent)" data-goto="/stay/${p.id}">${fmt(p.price)} →</a></div>`).join("")||'<div class="small">See the map for live pins.</div>'}
        </div>
        <div class="panel" style="background:linear-gradient(150deg,var(--card),var(--gold-soft))"><h3>Ask the concierge</h3>
          <p class="muted" style="font-size:14px">Not sure where to stay? Jollof knows every street.</p>
          <div class="btnrow" style="margin-top:12px"><button class="btn btn-green btn-sm" data-goto="/concierge?q=">Ask Jollof</button></div></div>
      </div>
    </div>
    <style>@media(max-width:900px){.grid-2m{grid-template-columns:1fr!important}}</style>
  </div></div>`;
}

/* ---------------- EXPERIENCES ---------------- */
function expCard(e){
  return `<article class="exp-card" onclick="bookExperience('${esc(e.id)}')" style="cursor:pointer"><div class="bg" style="background-image:url('${img(e.img)}')"></div><div class="veil"></div>
    <div class="txt"><div class="k">${esc(e.cat)} · ${esc(e.dur)}</div><h3>${esc(e.name)}</h3><p>${esc(e.desc)}</p>
    <span class="go">${e.price?("From "+fmt(e.price)):"Included with select stays"} ${I.arrow}</span></div></article>`;
}
function pExperiences(){
  return `${pageHead([["Home",URL("/")],["Experiences"]],"Experiences, <em class='serif-i'>arranged for you</em>","Boat cruises, private chefs, spa rituals and city tours — bundled with your residence, or booked alone.","<button class='btn btn-gold' data-goto='/stays'>Bundle with a stay</button>")}
  <div class="page-body"><div class="wrap">
    <div class="tabs" id="expTabs" style="margin-bottom:22px">
      <button class="tab active" data-cat="all">All</button>
      ${[...new Set(EXPERIENCES.map(e=>e.cat))].map(c=>`<button class="tab" data-cat="${c}">${c}</button>`).join("")}
    </div>
    <div class="exp-grid stagger" id="expGrid" style="grid-template-columns:repeat(4,1fr)">${EXPERIENCES.map(expCard).join("")}</div>
    <div class="panel" style="margin-top:26px;background:linear-gradient(150deg,var(--card),var(--gold-soft))">
      <div class="grid-2" style="align-items:center;gap:30px">
        <div><h3 style="font-size:23px">Every experience is Jollof-verified</h3>
        <p class="muted" style="font-size:14.5px">Vetted operators, transparent pricing, and the concierge on call before, during and after. Cancel freely up to 24h before.</p></div>
        <div class="btnrow"><a class="btn btn-green" href="${URL("/concierge")}">Plan my itinerary</a>
        <button class="btn btn-ghost" data-goto="/concierge">Ask the AI concierge</button></div>
      </div>
    </div>
  </div></div>`;
}
function bookExperience(id){
  const x=EXPERIENCES.find(e=>e.id===id)||{};
  openModal(`<h2 style="margin-bottom:4px">${esc(x.name||"Experience")}</h2>
    <p class="small" style="margin-bottom:14px">${esc(x.dur||"")}${x.price?" · "+fmt(x.price):""} — tell us when, and the concierge will confirm.</p>
    <div class="frm-grid">
      <div class="frm-row"><label>Your name</label><input class="inp" id="xbName" value="${esc(USER?USER.name:"")}"></div>
      <div class="frm-row"><label>Email</label><input class="inp" id="xbEmail" type="email" value="${esc(USER?USER.email:"")}"></div>
    </div>
    <div class="frm-grid">
      <div class="frm-row"><label>Preferred date</label><input class="inp" id="xbDate" type="date" min="${todayStr(1)}" value="${todayStr(7)}"></div>
      <div class="frm-row"><label>Guests</label><input class="inp" id="xbGuests" type="number" min="1" max="20" value="2"></div>
    </div>
    <div class="frm-row"><label>Anything we should know?</label><textarea class="txa" id="xbNotes" rows="3" placeholder="Dietary needs, occasion, pick-up address…"></textarea></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="submitExperience('${esc(id)}')">Request this experience</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function submitExperience(id){
  const r=await api("experience-book.php",{experience:id,
    name:$("#xbName").value,email:$("#xbEmail").value,date:$("#xbDate").value,
    guests:+$("#xbGuests").value,notes:$("#xbNotes").value});
  if(!r.ok){ toast(r.message||"Could not send that request","x"); return; }
  closeModal(); toast(r.message||"Requested — the concierge will confirm shortly ✨","spark");
}
function bindExperiences(){
  $$("#expTabs .tab").forEach(t=>t.addEventListener("click",()=>{
    $$("#expTabs .tab").forEach(x=>x.classList.toggle("active",x===t));
    const cat=t.dataset.cat;
    $("#expGrid").innerHTML=EXPERIENCES.filter(e=>cat==="all"||e.cat===cat).map(expCard).join("");
  }));
}

/* ---------------- shared bits ---------------- */
function colCards(list){ return list.map(c=>`<div class="col-card ${c.wide?"wide":""} ${c.tall?"tall":""}" data-gocol="${c.id}"><div class="img" style="background-image:url('${img(c.img)}')"></div><div class="veil"></div><div class="n">${COLLECTION_MAP[c.id].length} stays</div><div class="meta"><h3>${esc(c.name)}</h3><p>${esc(c.sub)}</p></div></div>`).join(""); }
function tierCards(){ return TIERS.map(t=>`
  <div class="tier ${t.featured?"featured":""}">
    ${t.featured?'<span class="best">Most loved</span>':""}
    <div class="medal">${t.letter}</div><h3>${t.name}</h3><div class="req">${t.req}</div>
    <ul>${t.perks.map(p=>`<li>${I.check}${p}</li>`).join("")}</ul>
    <div class="pts"><b>${t.pts}</b> points · earn ${t.mult} per stay</div>
  </div>`); }
function postCard(b){ return `<article class="post-card" data-goto="/blog/${b.slug}"><div class="im"><div class="bg" style="background-image:url('${img(b.img)}')"></div></div>
  <div class="bd"><span class="cat">${esc(b.cat)}</span><h3>${esc(b.title)}</h3><p>${esc(b.excerpt)}</p><div class="meta">${b.date} · ${b.read} read</div></div></article>`; }
function phoneMock(){ return `<div class="phone"><div class="notch"></div><div class="scr">
  <div style="display:flex;align-items:center;gap:8px;margin-top:4px"><img src="${img("wordmark-light")}" style="height:20px"><span class="small" style="margin-left:auto">${"●"}</span></div>
  <div class="mini-card"><img src="${img('p1')}" alt=""><div class="t"><b style="font-family:var(--fs-serif);font-size:13px">The Onyx Penthouse</b><div class="small">₦185,000/night · ★ 4.97</div></div></div>
  <div class="mini-card"><img src="${img('p3')}" alt=""><div class="t"><b style="font-family:var(--fs-serif);font-size:13px">Villa Azur</b><div class="small">₦420,000/night · ★ 4.99</div></div></div>
  <div style="background:var(--gold-grad);border-radius:12px;padding:10px;color:#231a05;font-size:11.5px;font-weight:500">${I.bot} Jollof: “Booked your boat cruise for Saturday ⛵”</div>
  <div style="margin-top:auto;background:var(--card);border:1px solid var(--line-soft);border-radius:12px;padding:8px 10px;font-size:10px;color:var(--ink-faint)">${(S.bookings||[]).some(x=>x.code)?`Check-in code · ${esc(S.bookings.find(x=>x.code).code)}`:"Check-in codes appear here once a stay is confirmed"}</div>
</div></div>`; }


/* ===== pages-booking.js ===== */
/* ============================================================
   JOLLOF LIVING — pages-booking.js
   booking flow · confirmation · trips · wishlist · compare
   ============================================================ */

const BOOK_STATE = {
  id:null, in:todayStr(7), out:todayStr(10), guests:2, policy:"moderate",
  addons:[], promo:null, giftCode:null, giftAmt:0, method:"card", split:false, req:false,
};

/* Capability ledger (R1): promises about outside services render only when
   their flag is switched on. Absent flag ⇒ off — an un-migrated install
   claims nothing. */
const jlFlag=(k)=>!!(window.JL&&JL.flags&&JL.flags[k]===true);
function pBooking(id, q) {
  const p=PROPERTIES.find(x=>x.id===id); if(!p) return p404();
  BOOK_STATE.id=id; BOOK_STATE.req=!!(q&&q.req); BOOK_STATE.addons=[]; BOOK_STATE.promo=null; BOOK_STATE.giftCode=null; BOOK_STATE.giftAmt=0; BOOK_STATE.method="card"; BOOK_STATE.split=false;
  return `
  <div class="page-top"></div>
  <div class="page-head"><div class="wrap">
    <div class="crumbs"><a href="${URL('/')}">Home</a> / <a href="${URL(`/stay/${id}`)}">${esc(p.name)}</a> / ${BOOK_STATE.req?"Request to Book":"Checkout"}</div>
    <h1>${BOOK_STATE.req?"Request to <em class='serif-i'>book</em>":"Complete your <em class='serif-i'>reservation</em>"}</h1>
    <p>${esc(p.name)} · ${esc(p.area)}, ${esc(p.city)} · ${I.star} ${p.rating.toFixed(2)} (${p.reviews})</p>
  </div></div>
  <div class="page-body"><div class="wrap" style="max-width:1060px">
    <div class="wizard-steps">${["Dates & guests","Add-ons & policy","Payment","Review & confirm"].map((s,i)=>`<div class="ws ${i===0?"done":i<=0?"done":""}" id="ws${i}"><span class="lbl">${i+1}. ${s}</span></div>`).join("")}</div>
    <div id="bkStage"></div>
  </div></div>`;
}
function bkStep(n){ $("#bkStage").innerHTML = [bkDates, bkAddons, bkPayment, bkReview][n](); window.scrollTo({top:0,behavior:"smooth"}); }
function bkCalc(){
  const p=PROPERTIES.find(x=>x.id===BOOK_STATE.id);
  const n=nightsBetween(BOOK_STATE.in,BOOK_STATE.out)||3;
  const addons=BOOK_STATE.addons.map(k=>({k}));
  return priceMath(p,n,{addons,promo:BOOK_STATE.promo,gift:BOOK_STATE.giftAmt||0});
}
function bkDates(){
  const p=PROPERTIES.find(x=>x.id===BOOK_STATE.id);
  const n=nightsBetween(BOOK_STATE.in,BOOK_STATE.out)||3;
  return `
  <div class="wizard-step">
    <h2>When are you staying?</h2>
    <div class="sub">Flexible? We'll show you the cheapest nearby dates after selection.</div>
    <div class="grid-2" style="grid-template-columns:1.1fr .9fr">
      <div style="display:flex;justify-content:center">${calWidget({in:BOOK_STATE.in,out:BOOK_STATE.out},tourRanges(p.id))}</div>
      <div class="stack">
        <div class="panel">
          <div class="frm-row"><label>Check-in</label><input type="date" class="inp" id="bkIn" value="${BOOK_STATE.in}" min="${todayStr()}"></div>
          <div class="frm-row"><label>Check-out</label><input type="date" class="inp" id="bkOut" value="${BOOK_STATE.out}" min="${todayStr()}"></div>
          <div class="frm-row"><label>Guests (max ${p.guests})</label>
            <div class="stepper"><button id="bkGd">${I.minus}</button><b id="bkG">${BOOK_STATE.guests}</b><button id="bkGu">${I.plus}</button></div></div>
          <label class="chk" style="margin-top:6px"><input type="checkbox" id="bkFlex"> I'm flexible — show cheapest dates</label>
        </div>
        <div class="panel"><h3 style="font-size:18px">Duration benefits</h3>
          <div class="krow"><span class="k">Nightly rate</span><span class="v" data-price="${p.price}">${fmt(p.price)}</span></div>
          <div class="krow"><span class="k">Weekly (7+)</span><span class="v">−12%</span></div>
          <div class="krow"><span class="k">Monthly (30+)</span><span class="v">−25% + split pay + lease</span></div>
          <div class="krow"><span class="k">${n} nights × ${fmt(p.price)}</span><span class="v"><b style="font-family:var(--fs-serif);font-size:19px" data-price="${bkCalc().subtotal}">${fmt(bkCalc().subtotal)}</b></span></div>
        </div>
      </div>
    </div>
    <div class="wizard-foot">
      <a class="btn btn-ghost" href="${URL(`/stay/${BOOK_STATE.id}`)}">← Back to listing</a>
      <button class="btn btn-gold" id="bkNext1">Continue → Add-ons &amp; policy</button>
    </div>
  </div>`;
}
function bkAddons(){
  return `
  <div class="wizard-step">
    <h2>Make it yours</h2>
    <div class="sub">Optional add-ons, cancellation policy, and the agreement that fits your stay.</div>
    <div class="grid-2" style="grid-template-columns:1.05fr .95fr">
      <div class="grid-2" style="grid-template-columns:1fr 1fr;gap:10px" id="bkAddons">
        ${Object.entries(ADDONS).map(([k,a])=>`
          <label class="panel" style="cursor:pointer;padding:16px;display:flex;flex-direction:column;gap:7px;${BOOK_STATE.addons.includes(k)?"border-color:var(--accent);background:linear-gradient(150deg,var(--card),var(--gold-soft))":""}">
            <div style="display:flex;align-items:center;gap:10px"><span class="why-ico" style="width:36px;height:36px;border-radius:10px;margin:0">${I[a.ico]||I.spark}</span>
            <input type="checkbox" data-addon="${k}" ${BOOK_STATE.addons.includes(k)?"checked":""} style="accent-color:var(--accent);width:16px;height:16px;margin-left:auto"></div>
            <b style="font-family:var(--fs-serif);font-size:17px;font-weight:600">${a.name}</b>
            <div class="small">${a.note}</div>
            <div style="color:var(--accent);font-weight:500;font-size:13.5px">${a.price>=1?"+ "+fmt(a.price):"+"+Math.round(a.price*100)+"% of subtotal"}</div>
          </label>`).join("")}
      </div>
      <div class="stack">
        <div class="panel">
          <h3 style="font-size:18px">Cancellation policy</h3>
          <select class="sel" id="bkPol" style="margin-top:8px">
            <option value="flexible" ${BOOK_STATE.policy==="flexible"?"selected":""}>Flexible — full refund to 48h before</option>
            <option value="moderate" ${BOOK_STATE.policy==="moderate"?"selected":""}>Moderate — full refund to 5 days · 50% after</option>
            <option value="strict" ${BOOK_STATE.policy==="strict"?"selected":""}>Strict — 50% refund to 14 days</option>
          </select>
          <div class="small" style="margin-top:10px">${I.shield} Refunds are processed to your original payment method within 3–5 days. Policies are always shown before you pay.</div>
        </div>
        <div class="panel" id="bkSplitNote">
          <h3 style="font-size:18px">Extended stay agreement</h3>
          <p class="muted" style="font-size:13.5px">For 30+ nights we prepare a written extended-stay agreement by email before you sign — the document vault is not part of this install.</p>
        </div>
        ${nightsBetween(BOOK_STATE.in,BOOK_STATE.out)>=30?`<div class="panel">
          <h3 style="font-size:18px">Split payments</h3>
          <label class="chk" style="margin-top:6px"><input type="checkbox" id="bkSplit" ${BOOK_STATE.split?"checked":""}> Pay 50% now, 50% at check-in (30+ nights)</label>
          <div class="small" style="margin-top:6px">Eases the financial burden of long-term luxury living.</div>
        </div>`:""}
      </div>
    </div>
    <div class="wizard-foot">
      <button class="btn btn-ghost" onclick="bkStep(0)">← Dates &amp; guests</button>
      <button class="btn btn-gold" id="bkNext2">Continue → Payment</button>
    </div>
  </div>`;
}
function bkPayment(){
  const m=bkCalc();
  return `
  <div class="wizard-step">
    <h2>Payment</h2>
    <div class="sub">Multi-currency · escrow-protected · PCI-DSS secure. You won't be charged until you confirm.</div>
    <div class="grid-2" style="grid-template-columns:1fr 1fr">
      <div>
        <div class="grid-2" style="grid-template-columns:1fr 1fr;gap:9px">
          ${PAY_METHODS.map(pm=>`<button class="panel pay-method" data-pm="${pm.id}" style="text-align:left;cursor:pointer;padding:14px;display:flex;gap:10px;align-items:center;${BOOK_STATE.method===pm.id?"border-color:var(--accent);background:linear-gradient(150deg,var(--card),var(--gold-soft))":""}">
            <span class="why-ico" style="width:34px;height:34px;border-radius:9px;margin:0">${I[pm.ico]}</span>
            <span><b style="font-size:13.5px;font-weight:500;display:block">${pm.name}</b><span class="small" style="font-size:11px">${pm.note}</span></span>
          </button>`).join("")}
        </div>
        <div class="ai-callout" style="margin-top:14px">${I.bolt}<span><b>Crypto (BTC / USDT)</b> moves to beta next quarter — join the waitlist from your account.</span></div>
        <div class="panel" style="margin-top:14px">
          <h3 style="font-size:17px">Promo code &amp; gift cards</h3>
          <div style="display:flex;gap:8px;margin-top:10px">
            <input class="inp" id="bkPromo" placeholder="e.g. JOLLOF10" value="${BOOK_STATE.promo||""}">
            <button class="btn btn-green btn-sm" id="bkPromoGo">Apply</button>
          </div>
          <div class="small" style="margin-top:8px">Try <b>JOLLOF10</b> (10% off) or <b>WELCOME5</b> (5% off first stay). Gift cards apply automatically.</div>
          <button class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="openGiftCard()">+ Add gift card</button>
        </div>
      </div>
      <div class="stack">
        <div class="panel">
          <h3 style="font-size:18px">Price breakdown</h3>
          <div class="breakdown">
            <div class="brow"><span>${fmt(m.nightly)} × ${nightsBetween(BOOK_STATE.in,BOOK_STATE.out)||3} nights ${m.monthly?"(−25% monthly)":m.weekly?"(−12% weekly)":""}</span><span>${fmt(m.subtotal)}</span></div>
            ${BOOK_STATE.addons.map(k=>`<div class="brow"><span>${ADDONS[k].name}</span><span>${ADDONS[k].price>=1?fmt(ADDONS[k].price):fmt(Math.abs(Math.round(m.subtotal*ADDONS[k].price)))}</span></div>`).join("")}
            ${BOOK_STATE.promo?`<div class="brow"><span style="color:var(--ok)">${PROMOS[BOOK_STATE.promo].label}</span><span class="free">applied</span></div>`:""}
            ${BOOK_STATE.giftAmt?`<div class="brow"><span style="color:var(--ok)">Gift card ${esc(BOOK_STATE.giftCode)}</span><span class="free">−${fmt(BOOK_STATE.giftAmt)}</span></div>`:""}
            <div class="brow"><span>Cleaning fee</span><span>${fmt(RATES.cleaning)}</span></div>
            <div class="brow"><span>Service fee (8%)</span><span>${fmt(m.svc)}</span></div>
            <div class="brow"><span>VAT (7.5%)</span><span>${fmt(m.vat)}</span></div>
            <div class="brow total"><span>Total ${BOOK_STATE.split?"(50% now)":""}</span><b>${fmt(BOOK_STATE.split?Math.round(m.total/2):m.total)}</b></div>
          </div>
        </div>
        <div class="escrow-note">${I.shield}<span><b>Escrow:</b> ${fmt(m.total)} is held by Jollof Living and released to the host only after you confirm check-in.</span></div>
      </div>
    </div>
    <div class="wizard-foot">
      <button class="btn btn-ghost" onclick="bkStep(1)">← Add-ons &amp; policy</button>
      <button class="btn btn-gold" id="bkNext3">Continue → Review</button>
    </div>
  </div>`;
}
function bkReview(){
  const p=PROPERTIES.find(x=>x.id===BOOK_STATE.id);
  const m=bkCalc();
  const ref="JL-2026-"+String(Math.floor(1000+Math.random()*9000));
  return `
  <div class="wizard-step">
    <h2>Review &amp; confirm</h2>
    <div class="sub">One last look before ${BOOK_STATE.req?"we send your request":"your reservation is locked in"}.</div>
    <div class="grid-2" style="grid-template-columns:1.1fr .9fr">
      <div class="stack">
        <div class="panel" style="display:flex;gap:14px;align-items:center">
          <img src="${img(p.img)}" style="width:120px;height:88px;object-fit:cover;border-radius:12px" alt="">
          <div><b style="font-family:var(--fs-serif);font-size:20px">${esc(p.name)}</b>
          <div class="small">${esc(p.area)}, ${esc(p.city)} · ${p.beds} bd · ${p.baths} ba · ${BOOK_STATE.guests} guests</div>
          <div class="small">${BOOK_STATE.in} → ${BOOK_STATE.out} · ${nightsBetween(BOOK_STATE.in,BOOK_STATE.out)||3} nights</div></div>
        </div>
        <div class="panel">
          <h3 style="font-size:17px">What's included</h3>
          <div class="krow"><span class="k">Cancellation</span><span class="v">${BOOK_STATE.policy[0].toUpperCase()+BOOK_STATE.policy.slice(1)} · full terms shown before paying</span></div>
          <div class="krow"><span class="k">Payment</span><span class="v">${PAY_METHODS.find(x=>x.id===BOOK_STATE.method).name}</span></div>
          <div class="krow"><span class="k">Add-ons</span><span class="v">${BOOK_STATE.addons.length?BOOK_STATE.addons.map(k=>ADDONS[k].name).join(", "):"None"}</span></div>
          <div class="krow"><span class="k">Split payment</span><span class="v">${BOOK_STATE.split?"50% now · 50% at check-in":"—"}</span></div>
          ${BOOK_STATE.req?'<div class="krow"><span class="k">Host confirmation</span><span class="v">Within 24 hours</span></div>':""}
          <div class="krow"><span class="k">Check-in</span><span class="v">From 3:00 PM · entry code issued with your confirmation</span></div>
        </div>
        <label class="chk" style="margin-top:4px"><input type="checkbox" id="bkTerms"> I agree to the house rules, cancellation policy, and extended-stay terms (if 30+ nights)</label>
        <label class="chk" style="margin-top:8px"><input type="checkbox" checked> Send me booking updates via email, SMS &amp; WhatsApp</label>
        <label class="chk" style="margin-top:8px"><input type="checkbox" checked> Add to my calendar &amp; wallet pass</label>
      </div>
      <div class="stack">
        <div class="panel">
          <h3 style="font-size:18px">Total</h3>
          <div class="breakdown">
            <div class="brow"><span>Stay &amp; fees</span><span>${fmt(m.subtotal+m.svc+m.vat+RATES.cleaning)}</span></div>
            <div class="brow"><span>Add-ons</span><span>${fmt(m.addons)}</span></div>
            ${BOOK_STATE.promo?`<div class="brow"><span style="color:var(--ok)">${PROMOS[BOOK_STATE.promo].label}</span><span class="free">−${fmt(PROMOS[BOOK_STATE.promo].off?Math.round(m.total*PROMOS[BOOK_STATE.promo].off):PROMOS[BOOK_STATE.promo].flat)}</span></div>`:""}
            <div class="brow"><span>Security deposit (held)</span><span>${fmt(m.deposit)}</span></div>
            <div class="brow total"><span>${BOOK_STATE.split?"Due now (50%)":"Total due"}</span><b>${fmt(BOOK_STATE.split?Math.round(m.total/2):m.total)}</b></div>
          </div>
          <div class="store-note">${I.shield}<span>Held in escrow · released after check-in confirmation. Invoice <b>${ref}</b> will be emailed instantly.</span></div>
        </div>
        <div class="ai-callout">${I.spark}<span><b>AI says:</b> booking Tue–Thu for these dates saves ~${fmt(Math.round(m.total*0.12))} — want the flexible option instead?</span></div>
        <div class="store-note">${I.check}<span>PDF invoice, receipt and digital pass are generated automatically.</span></div>
      </div>
    </div>
    <div class="wizard-foot">
      <button class="btn btn-ghost" onclick="bkStep(2)">← Payment</button>
      <button class="btn btn-gold btn-lg" id="bkConfirm">${BOOK_STATE.req?"Send request":"Confirm & pay "+fmt(BOOK_STATE.split?Math.round(m.total/2):m.total)}</button>
    </div>
  </div>`;
}
function bindBooking(id,q){
  const p=PROPERTIES.find(x=>x.id===id);
  bkStep(0);
  const bkBound=function(e){
    /* common wizard handlers */
    const next1=e.target.closest("#bkNext1"); if(next1){ BOOK_STATE.policy=$("#bkPol")?.value||BOOK_STATE.policy; bkStep(1); return; }
    const next2=e.target.closest("#bkNext2"); if(next2){ const pol=$("#bkPol"); if(pol) BOOK_STATE.policy=pol.value;
      $$("[data-addon]").forEach(c=>{ const k=c.dataset.addon; const has=c.checked; if(has&&!BOOK_STATE.addons.includes(k)) BOOK_STATE.addons.push(k); if(!has) BOOK_STATE.addons=BOOK_STATE.addons.filter(x=>x!==k); });
      const sp=$("#bkSplit"); if(sp) BOOK_STATE.split=sp.checked && nightsBetween(BOOK_STATE.in,BOOK_STATE.out)>=30;
      bkStep(2); return; }
    const next3=e.target.closest("#bkNext3"); if(next3){ bkStep(3); return; }
    const conf=e.target.closest("#bkConfirm"); if(conf){
      const terms=$("#bkTerms"); if(terms&&!terms.checked){ toast("Please accept the terms to continue","shield"); return; }
      confirmBooking(PROPERTIES.find(x=>x.id===BOOK_STATE.id)||p); return; }
    const promogo=e.target.closest("#bkPromoGo"); if(promogo){
      const code=($("#bkPromo").value||"").trim().toUpperCase();
      if(PROMOS[code]){ BOOK_STATE.promo=code; toast(`Promo applied — ${PROMOS[code].label}`,"gift"); bkStep(2); }
      else toast("That code isn't valid (try JOLLOF10)","x"); return; }
    const pmbtn=e.target.closest("[data-pm]"); if(pmbtn){ BOOK_STATE.method=pmbtn.dataset.pm;
      $$(".pay-method").forEach(x=>x.style.borderColor=""); pmbtn.style.borderColor="var(--accent)";
      toast(`Payment method: ${PAY_METHODS.find(x=>x.id===BOOK_STATE.method).name}`,"wallet"); return; }
    const gd=e.target.closest("#bkGd"); if(gd){ BOOK_STATE.guests=Math.max(1,BOOK_STATE.guests-1); $("#bkG").textContent=BOOK_STATE.guests; return; }
    const gu=e.target.closest("#bkGu"); if(gu){ BOOK_STATE.guests=Math.min((PROPERTIES.find(x=>x.id===BOOK_STATE.id)||PROPERTIES[0]).guests,BOOK_STATE.guests+1); $("#bkG").textContent=BOOK_STATE.guests; return; }
  };
  if(!window.__bkBound){ window.__bkBound=1; document.addEventListener("click",bkBound); }
  /* calendar + date inputs on step 0 are bound lazily by cal widget handler when step renders */
  const calTimer=setInterval(()=>{ if($("#calBox")&&$("#bkIn")){ clearInterval(calTimer);
    const sel={in:BOOK_STATE.in,out:BOOK_STATE.out};
    bindCal(sel,tourRanges(p),(s)=>{ BOOK_STATE.in=s.in; BOOK_STATE.out=s.out; const i=$("#bkIn"),o=$("#bkOut"); if(i)i.value=s.in; if(o)o.value=s.out; });
    $("#bkIn").addEventListener("change",e=>{BOOK_STATE.in=e.target.value; refreshCal()});
    $("#bkOut").addEventListener("change",e=>{BOOK_STATE.out=e.target.value; refreshCal()});
  }},80);
}
function refreshCal(){}
async function confirmBooking(p){
  const btn=$("#bkConfirm");
  if(btn){ btn.disabled=true; btn.dataset.label=btn.textContent; btn.textContent="Securing your reservation…"; }
  const payload={
    property:p.id,
    checkin:BOOK_STATE.in, checkout:BOOK_STATE.out,
    guests:BOOK_STATE.guests, policy:BOOK_STATE.policy,
    method:BOOK_STATE.method, addons:BOOK_STATE.addons,
    promo:BOOK_STATE.promo||"", giftCode:BOOK_STATE.giftCode||"",
    split:!!BOOK_STATE.split, request:!!BOOK_STATE.req,
    name:$("#bkName")?.value||"", email:$("#bkEmail")?.value||"", phone:$("#bkPhone")?.value||"",
  };
  const r=await api("booking-create.php",payload);
  if(!r.ok){
    if(btn){ btn.disabled=false; btn.textContent=btn.dataset.label||"Confirm & pay"; }
    toast(r.message||"We could not complete that reservation.","x");
    if(r.requiresAuth) setTimeout(()=>nav("/auth?next="+encodeURIComponent(location.pathname+location.search)),1000);
    return;
  }
  nav("/confirm/"+encodeURIComponent(r.data.ref));
}
function pConfirm(ref){
  /* confirm.php looks the reference up in MySQL and hands it over in JL.booking */
  const b=(JL.booking)||S.bookings.find(x=>x.ref===decodeURIComponent(ref));
  if(!b) return p404();
  const p=PROPERTIES.find(x=>x.id===b.prop)||{};
  const earned=b.pointsEarned||0;  return `
  <div class="page-top"></div>
  <div style="padding:calc(var(--header-h) + 30px) 0 70px"><div class="wrap" style="max-width:780px">
    <div style="text-align:center;margin-bottom:28px">
      <div style="width:84px;height:84px;border-radius:50%;background:var(--green-soft);color:var(--ok);display:grid;place-items:center;margin:0 auto 18px">${I.checkCircle.replace("<svg","<svg style='width:40px;height:40px'")}</div>
      <span class="eyebrow center">${b.req?"Request sent":"Reservation confirmed"}</span>
      <h1 style="font-size:clamp(1.9rem,4vw,2.8rem);margin:12px 0 8px">${b.req?"The host will confirm within 24 hours":"See you in "+esc(b.city)+", soon."}</h1>
      <p class="muted">Reference <b style="color:var(--accent)">${b.ref}</b> · A confirmation has been sent by email, SMS &amp; WhatsApp.</p>
    </div>
    <div class="panel" style="padding:26px">
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;padding-bottom:18px;border-bottom:1px solid var(--line-soft)">
        <img src="${img(b.img)}" style="width:130px;height:92px;object-fit:cover;border-radius:12px" alt="">
        <div style="flex:1;min-width:200px"><div class="small">${b.city} · ${esc(b.area)}</div>
          <b style="font-family:var(--fs-serif);font-size:22px">${esc(b.name)}</b>
          <div class="small">${b.in} → ${b.out} · ${b.nights} nights · ${b.guests} guests</div></div>
        <div class="pill-status ok">${b.status==="confirmed"?"Confirmed":"Awaiting host"}</div>
      </div>
      <div class="breakdown" style="border:none;padding-top:14px">
        <div class="brow"><span>Total (${b.split?"split 50/50":PAY_METHODS.find(x=>x.id===b.method).name})</span><span><b style="font-family:var(--fs-serif);font-size:22px">${fmt(b.total)}</b></span></div>
        <div class="brow"><span>Paid in escrow</span><span>${fmt(b.split?Math.round(b.total/2):b.total)}</span></div>
        <div class="brow"><span>Security deposit held</span><span>${fmt(Math.round(b.total*RATES.deposit))}</span></div>
        <div class="brow" style="color:var(--ok)"><span>Jollof Points earned</span><span>+${earned.toLocaleString()} pts</span></div>
      </div>
    </div>
    <div class="grid-2" style="margin-top:18px">
      <div class="panel" style="text-align:center;padding:20px">
        <div class="small">Digital check-in</div>
        <div style="font-family:var(--fs-serif);font-size:34px;font-weight:600;letter-spacing:.14em;margin:6px 0">${esc((b.code||((S.bookings.find(x=>x.ref===b.ref)||{}).code))||"on confirmation")}</div>
        <div class="small">Valid from ${b.in}, 3:00 PM · ${jlFlag("smartlock.sync")?"synced to the door lock":"your host confirms how the code is delivered"}</div>
      </div>
      <div class="panel" style="text-align:center;padding:20px">
        <div class="small">Digital lease agreement</div>
        <div style="font-size:15px;margin:10px 0;color:var(--ink-soft)">${b.nights>=30?"Generated for your 30+ night stay":"Short-stay terms apply"}</div>
        <button class="btn btn-ghost btn-sm" onclick="toast('${b.nights>=30?'Your extended-stay agreement is prepared by our team — watch your inbox':'House rules and cancellation policy from your confirmation email apply to this stay'}','doc')">${I.doc} Stay agreement</button>
      </div>
    </div>
    <div class="btnrow" style="justify-content:center;margin-top:24px">
      <a class="btn btn-green" data-goto="/trips">${I.calendar} View my trips</a>
      <a class="btn btn-ghost" onclick="gcalStay('${b.prop}')">${I.calendar} Add to calendar</a>
      <button class="btn btn-ghost" onclick="openInvoice('${b.ref}')">${I.doc} Invoice PDF</button>
    </div>
    <p class="small" style="text-align:center;margin-top:18px">${I.shield} Funds are held in escrow by Jollof Living and released to the host upon check-out.</p>
  </div></div>`;
}
function openInvoice(ref){
  const b=S.bookings.find(x=>x.ref===ref)||S.bookings[0];
  if(!b){ toast("No reservation to invoice","x"); return; }
  const p=PROPERTIES.find(x=>x.id===b.prop)||{};
  const me=USER||{};
  /* the printable invoice is rendered by the server from the stored
     line items, so the figures always match what was actually charged */
  const url=`${JL.apiBase}invoice.php?ref=${encodeURIComponent(b.ref)}`;
  openModal(`
    <div class="mhead" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
      <div><span class="eyebrow">Invoice ${esc(b.ref)}</span><h2 style="margin-top:8px">Jollof Living</h2></div>
      <div style="text-align:right"><div class="small">Luxury Living, African Soul</div><div class="small">${esc((JL.data&&JL.data.siteName)||"jollofliving.com")}</div></div>
    </div>
    <div class="separator" style="height:1px;background:var(--line);margin:14px 0 20px"></div>
    <div class="grid-2" style="gap:20px;margin-bottom:20px">
      <div><div class="small">Billed to</div><div class="muted" style="font-size:14.5px">${esc(me.name||"Guest")}<br>${esc(me.email||"")}<br>${esc(b.city||"Lagos")}, Nigeria</div></div>
      <div><div class="small">Stay</div><div class="muted" style="font-size:14.5px">${esc(b.name)}<br>${esc(b.area)}, ${esc(b.city)}<br>${b.in} → ${b.out}</div></div>
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
      <tr><td>${fmt(p.price||0)} × ${b.nights} night${b.nights===1?"":"s"}</td><td>${b.nights}</td><td>${fmt(p.price||0)}</td><td style="text-align:right">${fmt((p.price||0)*b.nights)}</td></tr>
      ${(b.addons||[]).filter(a=>ADDONS[a]).map(a=>`<tr><td>${esc(ADDONS[a].name)}</td><td>1</td><td>${ADDONS[a].price>=1?fmt(ADDONS[a].price):"3%"}</td><td style="text-align:right">${ADDONS[a].price>=1?fmt(ADDONS[a].price):"—"}</td></tr>`).join("")}
      <tr><td>Cleaning fee</td><td>1</td><td>${fmt(RATES.cleaning)}</td><td style="text-align:right">${fmt(RATES.cleaning)}</td></tr>
      <tr><td>Service fee &amp; VAT</td><td>1</td><td>—</td><td style="text-align:right">included</td></tr>
      <tr><td colspan="3"><b>Total ${b.split?"(split: 50% now)":"paid"} · ${esc((PAY_METHODS.find(x=>x.id===b.method)||{}).name||b.method||"")}</b></td>
          <td style="text-align:right"><b style="font-family:var(--fs-serif);font-size:19px">${fmt(b.total)}</b></td></tr>
    </tbody></table></div>
    <div class="small" style="margin-top:14px">VAT computed at ${Math.round(RATES.vat*100)}% · WHT applies to host payouts · Payment held in escrow until check-in. Thank you for staying with Jollof Living.</div>
    <div class="btnrow" style="margin-top:18px">
      <a class="btn btn-gold" href="${url}" target="_blank" rel="noopener">${I.download} Open printable invoice</a>
      <button class="btn btn-ghost" onclick="emailInvoice('${esc(b.ref)}')">${I.send} Email it to me</button></div>
  `);
}
async function emailInvoice(ref){
  const r=await api("invoice.php",{ref,action:"email"});
  toast(r.ok?(r.message||"Invoice sent to your inbox"):(r.message||"Could not send that invoice"), r.ok?"send":"x");
}

function openGiftCard(){
  openModal(`<h2 style="margin-bottom:4px">Apply a gift card</h2>
    <p class="small" style="margin-bottom:16px">Gift cards apply instantly to any reservation.</p>
    <div class="frm-row"><label>Gift card code</label><input class="inp" placeholder="JL-GIFT-XXXX" id="gcInp"></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="applyGift()">Apply card</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function applyGift(){
  const v=$("#gcInp").value.trim().toUpperCase();
  if(v.length<8){ toast("Enter a valid gift card code","x"); return; }
  // The real balance, checked server-side — the checkout applies the card's
  // own figure, never a number this script made up.
  const r=await api("giftcard.php",{action:"check",code:v});
  if(!r.ok){ toast(r.message||"That gift card code is not valid","x"); return; }
  const total=(bkCalc().total)||0;
  BOOK_STATE.giftCode=r.data.code;
  BOOK_STATE.giftAmt=Math.min(r.data.balance,total>0?total:r.data.balance);
  closeModal();
  toast(`${fmt(BOOK_STATE.giftAmt)} gift card credit applied to this reservation`,"gift");
  if($("#bkStage")) bkStep(2);
}

/* ---------------- TRIPS ---------------- */
function pTrips(q){
  const tab=(q&&q.tab)||"upcoming";
  const now=todayStr();
  const upcoming=S.bookings.filter(b=>["pending","confirmed"].includes(b.status)&&b.out>=now);
  const active=S.bookings.filter(b=>b.status==="active");
  const past=S.bookings.filter(b=>b.status==="completed"||(b.status!=="cancelled"&&b.out<now));
  const pending=S.bookings.filter(b=>b.status==="pending");
  const cancelled=S.bookings.filter(b=>b.status==="cancelled");
  const all={upcoming,active,past,pending,cancelled};
  const list=all[tab]||[];
  return `${pageHead([["Home",URL("/")],["My trips"]],"<em class='serif-i'>My</em> trips","Every reservation, request and stay — in one organised place.",
    `<button class="btn btn-gold" data-goto="/stays">Book a new stay</button>`)}
  <div class="page-body"><div class="wrap">
    <div class="tabs" style="margin-bottom:24px" id="tripTabs">
      ${[["upcoming","Upcoming"],["pending","Awaiting host"],["active","Active"],["past","Past"],["cancelled","Cancelled"]].map(([k,l])=>`<button class="tab ${tab===k?"active":""}" data-tt="${k}">${l}</button>`).join("")}
    </div>
    ${list.length? `<div class="stack">${list.map(b=>tripCard(b)).join("")}</div>`
    : `<div class="empty-state">${I.calendar}<b>Nothing ${tab} yet</b>Your reservations will appear here — from instant bookings to host-approved requests.<br><br><a class="btn btn-gold" href="${URL('/stays')}">Browse residences</a></div>`}
    ${S.waitlist.length?`<div class="panel" style="margin-top:26px"><h3 style="font-size:18px">${I.clock} Waitlists</h3>
      ${S.waitlist.map(w=>{const id=typeof w==="string"?w:w.id; const p=PROPERTIES.find(x=>x.id===id)||{}; const win=(typeof w==="object"&&w.window)||p.soldOut||"any dates"; return `<div class="krow"><span class="k">${esc(p.name||id)} · ${esc(win)}</span><span class="v"><span class="pill-status gold">Watching for openings</span></span></div>`;}).join("")}
    </div>`:""}
  </div></div>`;
}
function tripCard(b){
  const p=PROPERTIES.find(x=>x.id===b.prop);
  const st={confirmed:["ok","Confirmed"],pending:["warn","Awaiting host"],active:["info","In progress"],completed:["ok","Completed"],cancelled:["bad","Cancelled"]}[b.status]||["info",b.status];
  return `<div class="panel" style="padding:0;overflow:hidden">
    <div style="display:flex;flex-wrap:wrap;gap:18px;padding:18px;align-items:center">
      <img src="${img(b.img)}" style="width:150px;height:104px;object-fit:cover;border-radius:12px" alt="">
      <div style="flex:1;min-width:220px">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span class="pill-status ${st[0]}">${st[1]}</span><span class="small">${b.ref}</span></div>
        <b style="font-family:var(--fs-serif);font-size:21px;display:block;margin:6px 0 2px">${esc(b.name)}</b>
        <div class="small">${b.in} → ${b.out} · ${b.nights} nights · ${b.guests} guests · ${PAY_METHODS.find(x=>x.id===b.method)?.name||"card"}</div>
        <div class="small" style="color:var(--accent);margin-top:4px">${fmt(b.total)} total ${b.split?"· split 50/50":""} · +${b.pointsEarned||0} pts</div>
      </div>
      <div class="btnrow" style="flex-wrap:wrap">
        ${b.status==="confirmed"?`<button class="btn btn-green btn-sm" onclick="tripCheckin('${b.ref}')">${I.key} Check-in</button>`:""}
        ${b.status==="active"?`<button class="btn btn-green btn-sm" onclick="tripCheckout('${b.ref}')">${I.check} Confirm check-out</button>`:""}
        ${b.status==="completed"?`<button class="btn btn-gold btn-sm" onclick="openReview('${b.ref}')">${I.star} Write review</button>`:""}
        ${b.status==="confirmed"?`<button class="btn btn-ghost btn-sm" onclick="openModify('${b.ref}')">${I.edit} Modify</button>`:""}
        ${b.status==="confirmed"||b.status==="pending"?`<button class="btn btn-ghost btn-sm" onclick="openCancel('${b.ref}')">${I.trash} Cancel</button>`:""}
        <button class="btn btn-ghost btn-sm" onclick="openInvoice('${b.ref}')">${I.doc} Invoice</button>
        <button class="btn btn-ghost btn-sm" data-goto="/messages?to=team-onyx">${I.chat} Message</button>
      </div>
    </div>
    ${b.status==="confirmed"&&nightsBetween(todayStr(),b.in)<=3?`<div style="background:var(--green-soft);padding:11px 18px;font-size:13px;display:flex;gap:10px;align-items:center">${I.key}<span><b>${b.code?`Entry code ready: ${esc(b.code)}`:"Your entry code issues on confirmation"}</b> · valid ${b.in} from 3:00 PM · ${jlFlag("smartlock.sync")?"synced to the door lock":"host confirms how the code is handed over"}</span></div>`:""}
  </div>`;
}
function bindTrips(){
  $$("#tripTabs .tab").forEach(t=>t.addEventListener("click",()=>nav("/trips?tab="+t.dataset.tt)));
}
async function tripAction(ref,action,successIcon){
  const r=await api("booking-action.php",{ref,action});
  if(!r.ok){ toast(r.message||"That action is not available.","x"); return null; }
  await syncState(false);
  return r;
}
async function tripCheckin(ref){
  const b=S.bookings.find(x=>x.ref===ref);
  const r=await tripAction(ref,"checkin"); if(!r) return;
  render(); renderBadges();
  openModal(`<div style="text-align:center;padding:12px 0"><div class="why-ico" style="margin:0 auto 16px;width:60px;height:60px;border-radius:18px">${I.key}</div>
    <h2>You're checked in 🎉</h2><p class="muted" style="margin:8px 0 2px">Welcome to ${esc(b?b.name:"your residence")}. The host has been notified and your funds remain securely protected in escrow until check-out.</p>
    <div class="small" style="margin-bottom:16px">If anything isn't right, report it from your stay dashboard within 24h.</div>
    <div class="btnrow" style="justify-content:center"><button class="btn btn-green" onclick="closeModal()">Enjoy your stay</button><button class="btn btn-ghost" data-goto="/messages">Message host</button></div></div>`);
}
async function tripCheckout(ref){
  const r=await tripAction(ref,"checkout"); if(!r) return;
  render(); renderBadges();
  toast("Check-out confirmed — thank you! Please leave a review ✨","star");
}
function openModify(ref){ const b=S.bookings.find(x=>x.ref===ref); if(!b) return;
  openModal(`<h2 style="margin-bottom:4px">Modify reservation</h2><p class="small" style="margin-bottom:16px">Change dates, guest count, or add add-ons. ${b.ref}</p>
    <div class="frm-grid"><div class="frm-row"><label>New check-in</label><input class="inp" type="date" id="mIn" value="${b.in}"></div>
    <div class="frm-row"><label>New check-out</label><input class="inp" type="date" id="mOut" value="${b.out}"></div></div>
    <div class="frm-row"><label>Guests</label><input class="inp" type="number" id="mG" value="${b.guests}" min="1" max="8"></div>
    <div class="panel" style="background:var(--gold-soft)"><div class="small">Any rate difference is applied instantly; the host is notified of the change. Extended stays re-qualify for weekly/monthly discounts automatically.</div></div>
    <div class="btnrow" style="margin-top:16px"><button class="btn btn-gold" onclick="submitModify('${b.ref}')">Request modification</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function submitModify(ref){
  const r=await api("booking-action.php",{ref,action:"modify",checkin:$("#mIn").value,checkout:$("#mOut").value,guests:+$("#mG").value});
  closeModal();
  if(!r.ok){ toast(r.message||"Could not send that request","x"); return; }
  await syncState();
  toast(r.message||`Modification requested for ${ref} — host will confirm shortly`,"calendar");
}
function openCancel(ref){ const b=S.bookings.find(x=>x.ref===ref); if(!b) return;
  const refunds={flexible:1,moderate:0.5,strict:0.5};
  const rf=refunds[b.policy]||0.5;
  openModal(`<h2 style="margin-bottom:4px">Cancel this reservation?</h2><p class="muted" style="font-size:14.5px;margin-bottom:14px">${b.ref} · ${esc(b.name)}</p>
    <div class="panel" style="margin-bottom:14px">
      <div class="krow"><span class="k">Cancellation policy</span><span class="v">${b.policy[0].toUpperCase()+b.policy.slice(1)}</span></div>
      <div class="krow"><span class="k">Total paid</span><span class="v">${fmt(b.total)}</span></div>
      <div class="krow"><span class="k">Estimated refund</span><span class="v" style="color:var(--ok)">${fmt(Math.round(b.total*rf))}</span></div>
      <div class="krow"><span class="k">Refund method</span><span class="v">Original payment · 3–5 days</span></div>
    </div>
    <div class="btnrow"><button class="btn btn-ghost" onclick="closeModal()">Keep booking</button><button class="btn btn-ghost" style="border-color:var(--bad);color:var(--bad)" onclick="doCancel('${b.ref}')">Cancel reservation</button></div>`);
}
async function doCancel(ref){
  const r=await tripAction(ref,"cancel");
  closeModal();
  if(!r) return;
  render(); toast(r.message||"Cancellation confirmed — refund on its way","clock");
}
function openReview(ref){ const b=S.bookings.find(x=>x.ref===ref); if(!b) return;
  window.__rv={};
  openModal(`<h2 style="margin-bottom:4px">Rate your stay</h2><p class="small" style="margin-bottom:14px">${esc(b.name)} · ${b.in} → ${b.out}</p>
    ${["cleanliness","accuracy","communication","location","checkin","value"].map(k=>`
      <div class="krow"><span class="k" style="text-transform:capitalize">${k}</span><span class="v" id="rv-${k}"><button onclick="rvSet('${k}',1)">★</button><button onclick="rvSet('${k}',2)">★</button><button onclick="rvSet('${k}',3)">★</button><button onclick="rvSet('${k}',4)">★</button><button onclick="rvSet('${k}',5)">★</button></span></div>`).join("")}
    <div class="frm-row" style="margin-top:12px"><label>Your review</label><textarea class="txa" id="rvTxt" placeholder="What should future guests know?"></textarea></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="submitReview('${b.ref}')">Publish review</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
  window.rvSet=(k,v)=>{ window.__rv[k]=v; const el=$("#rv-"+k); el.innerHTML=""; for(let i=1;i<=5;i++){ const bt=document.createElement("button"); bt.textContent="★"; bt.style.color=i<=v?"var(--accent)":"var(--line)"; bt.onclick=()=>window.rvSet(k,i); el.appendChild(bt);} };
}
async function submitReview(ref){
  const body=($("#rvTxt")?.value||"").trim();
  if(body.length<10){ toast("Tell future guests a little more (10+ characters)","x"); return; }
  const r=await api("review-create.php",{ref,body,scores:window.__rv||{}});
  closeModal();
  if(!r.ok){ toast(r.message||"Could not publish that review","x"); return; }
  await syncState();
  toast(r.message||"Thank you! Your review has been submitted ✨","star");
}

function pWishlist(){
  const lists=Object.keys(S.wishlists);
  const active=S.activeWishlist||"default";
  const items=S.wishlists[active]||[];
  return `${pageHead([["Home",URL("/")],["Wishlist"]],"<em class='serif-i'>My</em> wishlist","Save residences to named lists, get push alerts on price drops, and share plans with your travel crew.",
    `<button class="btn btn-gold" id="wlNew">${I.plus} New list</button>`)}
  <div class="page-body"><div class="wrap">
    <div style="display:grid;grid-template-columns:250px 1fr;gap:24px" class="wl-shell">
      <div class="panel" style="height:fit-content">
        <div class="small" style="margin-bottom:8px">LISTS</div>
        ${lists.map(l=>`<div class="krow" style="cursor:pointer;${l===active?"color:var(--accent);border-color:var(--accent)":""}" data-wl="${l}"><span class="k">${esc(l==="default"?"Default":l)}</span><span class="v">${S.wishlists[l].length}</span></div>`).join("")}
        <div class="krow" style="cursor:pointer" data-wl="+add"><span class="k" style="color:var(--accent)">+ Add list</span></div>
      </div>
      <div>
        <div class="panel" style="margin-bottom:16px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
          <div style="flex:1;min-width:200px"><b style="font-family:var(--fs-serif);font-size:20px">${esc(active==="default"?"Default list":active)}</b>
          <div class="small">${items.length} ${items.length===1?"residence":"residences"} · price-drop alerts on</div></div>
          <button class="btn btn-ghost btn-sm" onclick="copyText(location.origin+URL('/wishlist')+'?list='+encodeURIComponent(S.activeWishlist),'Wishlist link copied — ready to share')">${I.share} Share list</button>
        </div>
        ${items.length?`<div class="stays-grid">${items.map(id=>{const p=PROPERTIES.find(x=>x.id===id); return p?stayCard(p):"";}).join("")}</div>`
        :`<div class="empty-state">${I.heartFill}<b>Nothing saved here yet</b>Tap the ♥ on any residence to add it to ${active==="default"?"your wishlist":"this list"}.<br><br><a class="btn btn-gold" href="${URL('/stays')}">Browse residences</a></div>`}
      </div>
    </div>
    <style>@media(max-width:860px){.wl-shell{grid-template-columns:1fr!important}}</style>
  </div></div>`;
}
function bindWishlist(){
  $$("[data-wl]").forEach(r=>r.addEventListener("click",()=>{
    if(r.dataset.wl==="+add"){
      wlCreate();
      return;
    }
    S.activeWishlist=r.dataset.wl;
    api("wishlist.php",{action:"active",list:S.activeWishlist}).then(()=>render());
  }));
  const wlNew=$("#wlNew");
  if(wlNew) wlNew.addEventListener("click",wlCreate);
}
async function wlCreate(){
  if(!requireAuth("create a list")) return;
  const name=prompt("Name your list (e.g. “December Trip”, “Work Travel”):");
  if(!name||!name.trim()) return;
  const r=await api("wishlist.php",{action:"create",name:name.trim()});
  if(!r.ok){ toast(r.message||"Could not create that list","x"); return; }
  await syncState(false);
  const createdSlug=(r.data&&r.data.slug)||r.slug||name.trim();
  S.activeWishlist=createdSlug;
  render(); toast("List created ✨","gift");
}
async function toggleWish(id,btn){
  if(!requireAuth("save residences")) return;
  const list=S.wishlists[S.activeWishlist]||(S.wishlists[S.activeWishlist]=[]);
  const had=list.indexOf(id)>-1;
  /* optimistic paint, then persist */
  if(had) list.splice(list.indexOf(id),1); else list.push(id);
  if(btn){ btn.classList.toggle("active",!had); btn.innerHTML=had?I.heart:I.heartFill; }
  renderBadges();
  const r=await api("wishlist.php",{action:"toggle",property:id,list:S.activeWishlist});
  if(!r.ok){ /* roll back */
    if(had) list.push(id); else list.splice(list.indexOf(id),1);
    if(btn){ btn.classList.toggle("active",had); btn.innerHTML=had?I.heartFill:I.heart; }
    renderBadges(); toast(r.message||"Could not update your wishlist","x"); return;
  }
  const isSaved = (r.data&&typeof r.data.saved!=="undefined") ? r.data.saved : r.saved;
  toast(isSaved?"Saved to your wishlist — alerts on":"Removed from wishlist","heart");
  const grid=$("#staysGrid"); if(grid&&typeof filteredStays==="function") grid.innerHTML=filteredStays().map(stayCard).join("");
}

/* ---------------- COMPARE ---------------- */
function pCompare(){
  const items=S.compare.map(id=>PROPERTIES.find(x=>x.id===id)).filter(Boolean);
  const rows=[["Price / night",p=>`<span class="cpr" style="font-family:var(--fs-serif);font-size:22px;font-weight:600;color:var(--accent)">${fmt(p.price)}</span>`],
    ["Rating",p=>`${I.star} <b>${p.rating.toFixed(2)}</b> · ${p.reviews} reviews`],
    ["Size",p=>`${p.beds} bd · ${p.baths} ba · ${p.guests} guests`],
    ["Location",p=>`${esc(p.area)}, ${esc(p.city)}`],
    ["Booking",p=>p.instant?`<span class="pill-status ok">Instant Book</span>`:`<span class="pill-status warn">Request to book</span>`],
    ["Cancellation",p=>`<span class="pill-status info">${p.policy[0].toUpperCase()+p.policy.slice(1)}</span>`],
    ["Verified",p=>p.badge.includes("Verified")?`<span class="pill-status ok">${I.gold} Verified</span>`:`<span class="pill-status gold">${esc(p.badge)}</span>`],
    ["Long-stay",p=>`<span class="pill-status ok">−25% monthly</span>`],
    ["Highlights",p=>`<span class="small">${esc(p.amens.slice(0,3).join(" · "))}</span>`]];
  return `${pageHead([["Home",URL("/")],["Compare"]],"Compare <em class='serif-i'>residences</em>","Side by side across price, rating, size and policies — up to three homes at once.",
    `<a class="btn btn-gold" href="${URL('/stays')}">Add more residences</a>`)}
  <div class="page-body"><div class="wrap">
    ${items.length? `
    <div class="tbl-wrap"><table class="tbl" style="min-width:700px">
      <thead><tr><th style="width:150px"></th>${items.map(p=>`<th><div style="border-radius:14px;overflow:hidden;aspect-ratio:4/3;margin-bottom:10px"><img src="${img(p.img)}" style="width:100%;height:100%;object-fit:cover" alt=""></div>
        <div style="font-family:var(--fs-serif);font-size:19px;cursor:pointer" data-goto="/stay/${p.id}">${esc(p.name)}</div>
        <button class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="toggleCompare('${p.id}')">Remove</button></th>`).join("")}</tr></thead>
      <tbody>${rows.map(([l,f])=>`<tr><th>${l}</th>${items.map(p=>`<td>${f(p)}</td>`).join("")}</tr>`).join("")}</tbody>
    </table></div>
    <div class="ai-callout" style="margin-top:20px">${I.spark}<span><b>AI verdict:</b> ${items.length>1?`${items[0].id!==items[1].id?`<b>${items[0].name}</b> scores highest on rating, while <b>${items[1].name}</b> offers ${Math.round((1-items[1].price/items[0].price)*100)}% more value per night.`:"these are the same residence"}`:""}</span></div>`
    : `<div class="empty-state">${I.scale}<b>Nothing to compare yet</b>Pick up to three residences using the “Compare” button on any card.<br><br><a class="btn btn-gold" href="${URL('/stays')}">Browse residences</a></div>`}
  </div></div>`;
}
async function toggleCompare(id){
  if(!requireAuth("compare residences")) return;
  const r=await api("compare.php",{action:"toggle",property:id});
  if(!r.ok){ toast(r.message||"Could not update compare","scale"); return; }
  S.compare=(r.data&&r.data.compare)||r.compare||S.compare;
  toast(r.message||"Compare updated","scale");
  render(); renderBadges();
}


/* ===== pages-comm.js ===== */
/* ============================================================
   JOLLOF LIVING — pages-comm.js
   messages · notifications · concierge · account · auth
   ============================================================ */

/* ---------------- MESSAGES ---------------- */
let msgState=null;
function pMessages(q){
  if(!msgState||(q&&q.to&&msgState.lastTo!==q.to)) { /* reset on new visit */ }
  msgState={lastTo:(q&&q.to)||"concierge"};
  return `${pageHead([["Home",URL("/")],["Messages"]],"<em class='serif-i'>Inbox</em>","Real-time chat with hosts, support and Jollof — with read receipts, media sharing and AI translation.")}
  <div class="page-body"><div class="wrap">
    <div class="chat-page">
      <aside class="conv-list" id="convList">
        <h3>Conversations</h3>
        ${CONVERSATIONS.map(c=>`<div class="conv ${c.id===msgState.lastTo?"active":""}" data-conv="${c.id}">
          <span class="avatar ${c.id==="concierge"?"green":"ivory"}">${c.id==="concierge"?"J":c.name[0]}</span>
          <div class="inf"><div class="nm">${esc(c.name)}</div><div class="last">${esc(c.msgs[c.msgs.length-1].text.replace(/<[^>]+>/g,"").slice(0,26))}…</div></div>
          <span class="t">${c.msgs[c.msgs.length-1].t}</span>
        </div>`).join("")}
        <div style="display:grid;gap:9px;margin-top:16px">
          <a class="btn btn-ghost btn-sm" href="${URL('/messages')}?to=concierge">${I.plus} Message the concierge</a>
        </div>
      </aside>
      <div class="chat-main" id="chatMain"></div>
    </div>
  </div></div>`;
}
function chatThread(id){
  const c=CONVERSATIONS.find(x=>x.id===id);
  msgState.thread=id;
  const isJenny=c.id==="support";
  return `
  <div class="chat-head">
    <span class="avatar ${c.id==="concierge"?"green":"ivory"}">${c.id==="concierge"?"J":c.name[0]}</span>
    <div><div class="nm">${esc(c.name)}</div><div class="st">${esc(c.sub)}</div></div>
    <div class="btnrow" style="margin-left:auto">
      <a class="icon-btn" href="tel:${esc((JL.data&&JL.data.contactPhone)||'+2348000000000')}" title="Call the concierge">${I.call}</a>
    </div>
  </div>
  <div class="chat-body" id="chatBody">
    ${c.moreDays?`<div class="day">Yesterday</div>`:""}
    ${c.msgs.map(m=>`<div class="msg ${m.from==="me"?"me":"bot"}" >${m.text}
      <span class="mtime">${m.t}${m.from==="me"?` <span class="rr">${m.read?"read":"delivered"} ${m.read?I.check:I.check}</span>`:""}</span></div>`).join("")}
  </div>
  <div class="quick-replies" id="qrBox">
    ${["Check-in code, please","Can I extend by a night?","Send me the invoice","Airport transfer, please"].map(x=>`<button data-qr="${esc(x)}">${esc(x)}</button>`).join("")}
  </div>
  <div class="chat-foot">

    <input class="inp" id="chatInp" placeholder="Write a message…" style="border-radius:999px">
    <button class="btn btn-gold" style="border-radius:50%;width:46px;height:46px;padding:0" id="chatSend" aria-label="Send">${I.send.replace("<svg","<svg style='width:18px;height:18px'")}</button>
  </div>`;
}
function bindMessages(q){
  const thread=(q&&q.to)||"concierge";
  const load=(id)=>{ $("#chatMain").innerHTML=chatThread(id);
    const body=$("#chatBody"); body.scrollTop=body.scrollHeight;
    $("#chatSend").addEventListener("click",()=>sendChat(id));
    $("#chatInp").addEventListener("keydown",e=>{ if(e.key==="Enter") sendChat(id); });
    $$("#qrBox button").forEach(b=>b.addEventListener("click",()=>{ $("#chatInp").value=b.dataset.qr; sendChat(id); }));
  };
  $$(".conv").forEach(c=>c.addEventListener("click",()=>{ $$(".conv").forEach(x=>x.classList.remove("active")); c.classList.add("active"); load(c.dataset.conv); }));
  load(thread);
}
async function sendChat(id){
  const inp=$("#chatInp"); const txt=inp.value.trim(); if(!txt) return; inp.value="";
  const conv=CONVERSATIONS.find(x=>x.id===id);
  const body=$("#chatBody");
  body.insertAdjacentHTML("beforeend",`<div class="msg me">${esc(txt)}<span class="mtime">sending…</span></div>`);
  body.scrollTop=body.scrollHeight;
  /* the API keys threads by their numeric row id */
  const r=await api("message-send.php",{conversation:conv?conv.cid:id,text:txt});
  const mine=body.querySelector(".msg.me:last-child .mtime");
  if(!r.ok){ if(mine) mine.textContent="not delivered"; toast(r.message||"Message not delivered","x"); return; }
  if(mine) mine.textContent=(r.data&&r.data.time?r.data.time:"now")+" · delivered ✓";
  if(r.data&&r.data.reply){
    body.insertAdjacentHTML("beforeend",`<div class="msg bot">${r.data.reply}<span class="mtime">${esc(r.data.replyTime||"now")}</span></div>`);
    body.scrollTop=body.scrollHeight;
  }
  const badge=$("#msgCount"); if(badge){ badge.style.display="none"; }
}

/* ---------------- NOTIFICATIONS ---------------- */
function pNotif(){
  const unread=S.notifications.filter(n=>n.unread).length;
  return `${pageHead([["Home",URL("/")],["Notifications"]],"<em class='serif-i'>Notifications</em>",`${unread} unread · booking alerts, price drops, promotions and system updates.`,
    `<button class="btn btn-ghost btn-sm" onclick="markAllRead()">Mark all read</button>
     <button class="btn btn-ghost btn-sm" onclick="openNotifSettings()">Preferences</button>`)}
  <div class="page-body"><div class="wrap" style="max-width:820px">
    <div class="panel" style="padding:10px 20px">
      ${S.notifications.length?S.notifications.map(n=>{ const isU=n.unread;
        return `<div class="notif-item ${isU?"unread":""}"><span class="ico">${I[n.ico]||I.check}</span>
        <div class="bd"><b>${esc(n.title)}</b><p>${esc(n.body)}</p><div class="t">${esc(n.time)}</div></div>
        ${isU?`<button class="icon-btn" style="width:30px;height:30px" onclick="markRead(${n.id})" title="Mark read">${I.check}</button>`:""}
      </div>`; }).join(""):`<div class="empty-state">${I.notification}<b>No notifications yet</b>Booking alerts, price drops and promotions will land here.</div>`}
    </div>
    <div class="panel" style="margin-top:18px">
      <h3 style="font-size:18px">Delivery channels</h3>
      <div class="grid-3" style="margin-top:10px">
        ${[["Push notifications","On"],["Email digest","Daily"],["SMS alerts","Critical only"],["WhatsApp updates","On"],["Price drops","Instant"],["Marketing","Weekly"]].map(([k,v])=>`<div class="krow"><span class="k">${k}</span><span class="v" style="color:var(--ok)">${v}</span></div>`).join("")}
      </div>
      <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost btn-sm" onclick="toast('Preferences saved','check')">Save preferences</button></div>
    </div>
  </div></div>`;
}
async function markRead(id){
  const r=await api("notifications.php",{action:"read",id});
  if(!r.ok){ toast(r.message||"Could not update that notification","x"); return; }
  const n=S.notifications.find(x=>x.id===id); if(n) n.unread=false;
  render(); renderBadges();
}
async function markAllRead(){
  const r=await api("notifications.php",{action:"read-all"});
  if(!r.ok){ toast(r.message||"Could not update notifications","x"); return; }
  S.notifications.forEach(n=>{ n.unread=false; });
  render(); renderBadges(); toast("All caught up ✨","check");
}
function savePref(el){
  api("notifications.php",{action:"pref",key:el.parentElement.textContent.trim(),on:el.checked})
    .then(r=>toast(r.ok?"Preference updated":"Could not save that preference", r.ok?"check":"x"));
}
function openNotifSettings(){
  openModal(`<h2 style="margin-bottom:14px">Notification preferences</h2>
    ${[["Booking confirmations & reminders",true],["Price drops on wishlisted homes",true],["Promotions & seasonal campaigns",true],["WhatsApp booking updates",true],["SMS for critical alerts",true],["Product news & Jollof Club",false]].map(([k,v],i)=>`<label class="chk" style="margin:10px 0"><input type="checkbox" ${v?"checked":""} onchange="savePref(this)"> ${k}</label>`).join("")}
    <div class="btnrow" style="margin-top:14px"><button class="btn btn-gold" onclick="closeModal();toast('Preferences saved ✨','check')">Save</button></div>`);
}

/* ---------------- CONCIERGE ---------------- */
function concReply(txt){
  const t=txt.toLowerCase();
  const byArea=PROPERTIES.find(p=>t.includes(p.area.toLowerCase()));
  if(/itinerar|plan|weekend|schedule|itinerary/.test(t)){
    const p=byArea||PROPERTIES[0];
    return `Here's your <b>dream weekend</b> itinerary ✨<br><b>Day 1:</b> check-in at ${p.name} (3pm) → sunset cruise on the lagoon (6pm)<br><b>Day 2:</b> private chef brunch → Nike Art Gallery → rooftop dinner at ${p.area}<br><b>Day 3:</b> spa ritual in-residence → late checkout (2pm).<br>Bundle it — the experience package saves ~15%.`;
  }
  if(/hello|hi|hey|good (morning|evening)/.test(t)) return "Hello! 👋 I'm <b>Jollof</b>, your AI concierge — ask me about stays, prices, transfers or experiences, in English, Pidgin or Yoruba.";
  if(/book|want|need|find|stay|apartment|place|room|nice/.test(t)){
    const pick=byArea||PROPERTIES.find(p=>p.price<=150000)||PROPERTIES[0];
    return `I can help! ✨ <b>${pick.name}</b> in ${pick.area} (${fmt(pick.price)}/night, ★${pick.rating}) is a lovely fit — ${pick.instant?"it's <b>Instant Book</b>, no approval needed.":"the host confirms within 24h."} Want me to add a transfer or chef?`;
  }
  if(/how much|price|cost|cheap|budget|naira|₦/.test(t)){
    const opts=[...PROPERTIES].sort((a,b)=>a.price-b.price).slice(0,3);
    return `Prices start at ${fmt(opts[0].price)}/night. Best value now: ${opts.map(p=>`<b>${p.name}</b> (${fmt(p.price)})`).join(" · ")}. Weekly −12%, monthly −25% + split payments.`;
  }
  if(/lagos|lekki|ikoyi|victoria|banana|eko|abuja|maitama/.test(t)){
    const city=/abuja|maitama/.test(t)?"Abuja":"Lagos";
    const list=PROPERTIES.filter(p=>p.city===city).slice(0,3);
    return `${city} has ${PROPERTIES.filter(p=>p.city===city).length} residences — ${list.map(p=>`<b>${p.name}</b>`).join(", ")}. Tap any card for rates, photos & availability.`;
  }
  if(/pool|wifi|gym|cinema|amenit/.test(t)){
    const p=byArea||PROPERTIES[0];
    return `<b>${p.name}</b> offers: ${p.amens.slice(0,4).join(", ").toLowerCase()} and more. Use the filters in Explore to narrow by amenity.`;
  }
  if(/payment|pay|escrow|split|installment|transfer|currency|crypto/.test(t))
    return `Pay by ${PAY_METHODS.map(m=>m.name.toLowerCase()).join(", ")} — billed in ${JL.currency||"NGN"}. Funds ride in <b>escrow</b> and release to the host at check-out; 30+ night stays split <b>50/50</b>. 🔒`;
  if(/chef|cook|food|jollof|restaurant/.test(t)) return "Our private chefs are Jollof-approved — Nigerian fine dining, jollof masterclasses and tasting menus in your kitchen. From ₦55,000. Want your dates? 🍲";
  if(/boat|cruise|lagoon|experience|tour/.test(t)) return "Signature experiences: <b>Lagos sunset cruises</b> (₦85k), private chefs, spa rituals and heritage tours. Bundle any with your stay — save ~15%. 🛥️";
  if(/safe|security|verified|trust/.test(t)) return "Every Jollof listing is KYC-verified, AI-screened and (for the gold badge) inspected in person. Payments ride in escrow until you confirm check-in. 🛡️";
  if(/long|month|weekly|discount/.test(t)) return "7+ nights: −12%. 30+ nights: −25%, split payments, and a digital lease agreement. Long-stay, made effortless. 📆";
  if(/cancel|refund/.test(t)) return "Cancellation tiers: <b>Flexible</b> (full refund to 48h), <b>Moderate</b> (full to 5 days), <b>Strict</b> (50% to 14 days). Every listing shows its policy before payment.";
  if(/host|list|earn|income|rent out/.test(t)) return "Hosts keep <b>88%</b>, with dynamic pricing, weekly escrow payouts, professional photography and sync to Airbnb, Booking.com & VRBO. Use the earnings calculator on the Host page. 🏠";
  if(/refer|invite|friend/.test(t)) return "Our referral programme: <b>give ₦10,000, get ₦10,000</b>. Your code is <b>ADEBAYO10</b> — share it from the Membership page. 🎁";
  if(/thank|thanks/.test(t)) return "You're welcome! 🇳🇬 Anything else — transfers, chefs, or a last-minute deal within 48 hours?";
  return "I can help with <b>bookings</b>, <b>prices</b>, <b>amenities</b>, <b>payments</b>, <b>transfers</b>, <b>chefs</b> and <b>experiences</b>. Try “book me something nice in Lekki for next weekend”.";
}
function pConcierge(q){
  const prefill=q&&q.q?decodeURIComponent(q.q):"";
  return `${pageHead([["Home",URL("/")],["AI Concierge"]],"Meet <em class='serif-i gold-text'>Jollof</em>","Your 24/7 AI concierge. Books, arranges, plans and translates — in English, Pidgin, Yoruba, Hausa, Igbo & French.",`<span class="badge ok">${I.bolt} 2,400+ conversations this month</span>`)}
  <div class="page-body"><div class="wrap">
    <div class="conc-banner reveal">
      <div class="grid">
        <div>
          <span class="eyebrow">Concierge · always on</span>
          <h2>Ask, and it's <em class="serif-i">arranged</em></h2>
          <p>Jollof knows every residence, price and experience. Ask for a stay, an itinerary, a transfer — or a jollof masterclass on a Tuesday.</p>
          <div class="lang-chips" id="langChips">${["English","Pidgin","Yoruba","Hausa","Igbo","French"].map((l,i)=>`<span>${i===0?`<b>${l}</b>`:l}</span>`).join("")}</div>
          <div class="sample-prompts" style="display:grid;gap:9px;margin-top:20px">
            ${[["Book me something nice in Lekki for next weekend"],["What's the cheapest stay in Lagos? Can I split payments?"],["Plan me a perfect 3-day Lagos itinerary"],["Arrange a boat cruise and a private chef for my anniversary"],["How do escrow payments work?"]].map(p=>`<button style="text-align:left;padding:12px 16px;border-radius:13px;border:1px dashed var(--line);background:var(--card-2);font-size:14px;color:var(--ink-soft);cursor:pointer;transition:.2s" onclick="concAsk('${p[0]}')">${I.spark.replace("<svg","<svg style='width:15px;height:15px;color:var(--accent);vertical-align:-2px'")} ${p[0]}</button>`).join("")}
          </div>
        </div>
        <div class="conc-demo">
          <div class="head">
            <div class="orb">${I.bot}</div>
            <div><div class="nm">Jollof Concierge</div><div class="st">Online · replies in seconds</div></div>
          </div>
          <div class="conc-msgs" id="concMsgs"></div>
          <div class="conc-input">
            <input type="text" id="concInput" placeholder="Try “book me something nice in Lekki…”" autocomplete="off">
            <button id="concSend" aria-label="Send">${I.send}</button>
          </div>
        </div>
      </div>
    </div>

    <div class="sec-head center" style="margin-top:70px"><span class="eyebrow center">Under the hood</span>
      <h2>25+ AI features, <em class="serif-i">all live</em></h2></div>
    <div class="why-grid stagger">
      ${[["search","AI Search & Discovery","Natural-language + visual search: “3-bed flat in VI with a pool under ₦150k.”"],
         ["spark","Personalised Recommendations","Collaborative filtering — guests like you also loved…"],
         ["grid","Dynamic Pricing Alerts","Predicts price drops and the cheapest days to book."],
         ["star","Review Summarisation","Turns 100+ reviews into sentiment, trends and highlights."],
         ["calendar","Trip Itineraries","Day-by-day plans from destination, dates, interests, budget."],
         ["eye","Accessibility Matching","Finds homes that fit mobility, hearing and visual needs."],
         ["gold","Listing Optimiser","A/B tests titles, photos and descriptions for hosts."],
         ["scale","Competitive Analysis","“Properties like yours average ₦85k — you're 12% below.”"],
         ["shield","Guest Screening","Risk scores every booking request before it reaches a host."],
         ["chatBell","Support Triage","Classifies, prioritises and auto-resolves tickets."],
         ["globe","Translation","Real-time messaging translation across 6+ languages."],
         ["leaf","Sustainability Scoring","“Green Stay” badges from energy and waste data."]].map(w=>`
      <div class="why-card"><span class="ai-pill">AI</span><div class="why-ico">${I[w[0]]}</div><h3>${w[1]}</h3><p>${w[2]}</p></div>`).join("")}
    </div>
  </div></div>`;
}
function concSay(txt,who="me"){
  const box=$("#concMsgs"); if(!box) return;
  box.insertAdjacentHTML("beforeend",`<div class="msg ${who}">${txt}</div>`); box.scrollTop=box.scrollHeight;
}
async function concAsk(txt){
  if(!txt||!txt.trim()) return;
  concSay(esc(txt));
  const box=$("#concMsgs");
  box.insertAdjacentHTML("beforeend",`<div class="msg bot"><span class="typing"><i></i><i></i><i></i></span></div>`);
  box.scrollTop=box.scrollHeight;
  /* logged to the concierge thread in MySQL; the server answers from live inventory */
  const r=await api("concierge.php",{text:txt});
  const ty=$(".conc-msgs .typing"); if(ty) ty.closest(".msg").remove();
  concSay((r.ok&&r.data&&r.data.reply)?r.data.reply:concReply(txt),"bot");
}
function bindConcierge(){
  const send=()=>{ const v=$("#concInput").value; $("#concInput").value=""; concAsk(v); };
  $("#concSend").addEventListener("click",send);
  $("#concInput").addEventListener("keydown",e=>{ if(e.key==="Enter") send(); });
  concSay("Welcome to <b>Jollof Living</b> ✨ I'm your AI concierge — bookings, prices, transfers, chefs, itineraries. Ask away, in any language.", "bot");
  const pre=qp("q")||"";
  if(pre) setTimeout(()=>concAsk(decodeURIComponent(pre)),600);
}

/* ---------------- ACCOUNT ---------------- */
function pAccount(){
  if(!USER) return pAuth("signin");
  const U=USER;
  const tier=TIERS.find(t=>t.key===S.tier)||TIERS[0]||{letter:"B",name:"Bronze",mult:"5×"};
  return `${pageHead([["Home",URL("/")],["Account"]],"<em class='serif-i'>Your</em> account","Profile, verification, security and the tools of the Jollof Club.")}
  <div class="page-body"><div class="wrap" style="max-width:880px">
    <div class="panel" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
      <div class="avatar" style="width:76px;height:76px;font-size:30px">${esc((U.name||"G").charAt(0).toUpperCase())}</div>
      <div style="flex:1;min-width:220px"><b style="font-family:var(--fs-serif);font-size:25px">${esc(U.name||"Guest")}</b>
      <div class="small">${esc(U.email||"")}${U.phone?" · "+esc(U.phone):""} · Member since ${esc(U.memberSince||"")}</div>
      <div class="btnrow" style="margin-top:8px">${U.emailVerified?`<span class="badge ok">${I.check} Email verified</span>`:`<span class="badge" style="background:rgba(217,119,6,0.12);color:#d97706;border:1px solid rgba(217,119,6,0.3)">${I.clock} Email unverified</span><button class="btn btn-ghost btn-sm" onclick="resendEmailVerification(this)" style="font-size:11px;padding:3px 9px;margin-left:4px">${I.mail} Resend email</button>`}${U.phone?`<span class="badge ok">${I.check} Phone verified</span>`:""}${U.kyc?`<span class="badge">${I.gold} ID verified</span>`:`<span class="badge">ID pending</span>`}<span class="badge">${tier.letter} ${tier.name}</span></div></div>
      <div style="text-align:right"><div class="small">Jollof Points</div><div style="font-family:var(--fs-serif);font-size:34px;font-weight:600;color:var(--accent)">${S.points.toLocaleString()}</div>
      <div class="small">${tier.name} · ${tier.mult} multiplier</div>
      <div class="btnrow" style="margin-top:10px;justify-content:flex-end">
        ${IS_OWNER?`<a class="btn btn-ghost btn-sm" href="${URL("/host/dashboard")}">Owner dashboard</a>`:""}
        <a class="btn btn-ghost btn-sm" href="${JL.base}logout.php">Log out</a>
      </div></div>
    </div>
    ${!U.emailVerified?`<div class="ai-callout" style="margin-top:14px;background:rgba(217,119,6,0.08);border:1px solid rgba(217,119,6,0.25);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><div>${I.clock} <b>Email verification pending:</b> We sent a verification link to <b>${esc(U.email||"your address")}</b>. Please check your inbox or spam folder to verify.</div><button class="btn btn-ghost btn-sm" onclick="resendEmailVerification(this)">${I.mail} Resend link</button></div>`:""}

    <div class="grid-2" style="margin-top:18px">
      <div class="panel"><h3 style="font-size:18px">Identity verification (KYC)</h3>
        ${[["Government ID uploaded",!!U.kyc],["Selfie match complete",!!U.kyc],["Phone verified",!!U.phone],["Background check (optional)",false]].map(([k,v])=>`<div class="krow"><span class="k">${k}</span><span class="v" style="color:${v?"var(--ok)":"var(--ink-faint)"}">${v?I.check:"pending"}</span></div>`).join("")}
        ${jlFlag("account.documents")?`<div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost btn-sm" onclick="toast('ID details opened — re-upload anytime','doc')">${I.doc} Manage documents</button></div>`:`<p class="small" style="margin-top:8px;color:var(--ink-faint)">${I.shield} The document vault is not provisioned on this install — send ID copies to us via <a href="${URL('/help')}">the help centre</a> and we will attach them to your file.</p>`}
      </div>
      <div class="panel"><h3 style="font-size:18px">Security</h3>
        <div class="krow"><span class="k">Password</span><span class="v"><button class="btn btn-ghost btn-sm" onclick="openPasswordChange()">Change password</button></span></div>
        <div class="krow"><span class="k">Two-factor authentication</span><span class="v" style="color:${jlFlag("account.2fa")?"var(--ok)":"var(--ink-faint)"}">${jlFlag("account.2fa")?I.check+" SMS + authenticator":"not configured yet"}</span></div>
        <div class="krow"><span class="k">Last sign-in</span><span class="v">${esc(U.lastLogin||"just now")}</span></div>
        <div class="krow"><span class="k">NDPR / GDPR</span><span class="v">${jlFlag("account.data_rights")?"Self-service export & erase enabled":"Data export & erase handled on request"}</span></div>
        ${jlFlag("account.2fa")?`<div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost btn-sm" onclick="toast('Security centre opened','lock')">${I.lock} Manage</button></div>`:`<p class="small" style="margin-top:8px;color:var(--ink-faint)">Password changes sign every other device out immediately; two-step sign-in will appear here when the auth service is enabled.</p>`}
      </div>
    </div>

    <div class="grid-2" style="margin-top:18px">
      <div class="panel"><h3 style="font-size:18px">Preferences</h3>
        <div class="frm-row"><label>Language</label><select class="sel"><option>English</option><option>Yoruba</option><option>Hausa</option><option>Igbo</option><option>Pidgin</option><option>French</option></select></div>
        <div class="frm-row"><label>Currency</label><select class="sel" id="accCur"><option value="NGN">NGN ₦</option><option value="USD">USD $</option><option value="GBP">GBP £</option><option value="EUR">EUR €</option></select></div>
        <label class="chk"><input type="checkbox" checked> Email me private openings</label>
        <label class="chk" style="margin-top:8px"><input type="checkbox" checked> WhatsApp booking updates</label>
      </div>
      <div class="panel"><h3 style="font-size:18px">Connected services</h3>
        ${[["Google Calendar & Outlook",jlFlag("channels.google_sync")],["Apple / Google Wallet passes",jlFlag("channels.wallet_passes")],["WhatsApp Business",jlFlag("channels.whatsapp")],["QuickBooks, Xero (host)",jlFlag("channels.accounting")],["Salesforce / HubSpot (business)",jlFlag("channels.crm")]].map(([k,v])=>`<div class="krow"><span class="k">${k}</span><span class="v">${v?`<span style="color:var(--ok)">connected</span>`:"<span style=\"color:var(--ink-faint)\">not live on this install</span>"}</span></div>`).join("")}
      </div>
    </div>

    <div class="panel" style="margin-top:18px;text-align:center">
      <b style="font-family:var(--fs-serif);font-size:19px">Report, block &amp; community</b>
      <p class="small" style="margin:6px 0 14px">Respect is the house rule. Every member is protected by our anti-discrimination policy and community guidelines.</p>
      <div class="btnrow" style="justify-content:center">
        <button class="btn btn-ghost btn-sm" onclick="openReportModal('community','0')">Report a concern</button>
        <button class="btn btn-ghost btn-sm" onclick="toast('Blocked users are never shown your listings','lock')">Blocked users</button>
        <button class="btn btn-ghost btn-sm" data-goto="/about">NDPR &amp; compliance</button>
      </div>
    </div>
  </div></div>`;
}
function openReportModal(targetType, targetId){
  openModal(`<h2 style="margin-bottom:4px">Report a safety concern</h2>
    <p class="small" style="margin-bottom:14px">Our Trust &amp; Safety team investigates every report within 24 hours.</p>
    <div class="frm-row"><label>Reason</label>
      <select class="inp" id="repCat">
        <option value="safety">Safety / security violation</option>
        <option value="scam">Fraud / scam attempt</option>
        <option value="harassment">Harassment / offensive behavior</option>
        <option value="inaccurate">Inaccurate listing details</option>
        <option value="other">Other issue</option>
      </select>
    </div>
    <div class="frm-row"><label>Details</label>
      <textarea class="txa" id="repDetail" rows="3" placeholder="Describe what happened with as much detail as possible..."></textarea>
    </div>
    <div class="btnrow"><button class="btn btn-gold" onclick="submitReportModal('${targetType||"platform"}','${targetId||"0"}')">Submit report</button>
    <button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function submitReportModal(targetType, targetId){
  const detail=($("#repDetail")?.value||"").trim();
  const category=$("#repCat")?.value||"safety";
  if(!detail){ toast("Please explain the concern for our safety team","x"); return; }
  const r=await api("account.php",{action:"report-concern",target_type:targetType,target_id:targetId,category,detail});
  closeModal();
  toast(r.message||"Report submitted to Trust & Safety","shield");
}

async function resendEmailVerification(btn){
  if(btn){ btn.disabled=true; btn.textContent="Sending…"; }
  try {
    const r=await api("auth.php",{action:"resend_verification"});
    if(r.ok){
      toast(r.message||"Verification email sent! Check your inbox.","mail");
      if(btn){
        let s=60;
        btn.textContent=`Resend in ${s}s`;
        const t=setInterval(()=>{
          s--;
          if(s<=0){ clearInterval(t); btn.disabled=false; btn.textContent="Resend email"; }
          else { btn.textContent=`Resend in ${s}s`; }
        },1000);
      }
    } else {
      toast(r.message||"Could not send email","x");
      if(btn){ btn.disabled=false; btn.textContent="Resend email"; }
    }
  } catch(e){
    toast("Network error","x");
    if(btn){ btn.disabled=false; btn.textContent="Resend email"; }
  }
}

function openPasswordChange(){
  openModal(`<h2 style="margin-bottom:4px">Change your password</h2>
    <p class="small" style="margin-bottom:14px">Choose something long — a short phrase beats a scrambled word.</p>
    <div class="frm-row"><label>Current password</label><input class="inp" type="password" id="pwOld"></div>
    <div class="frm-row"><label>New password</label><input class="inp" type="password" id="pwNew" placeholder="At least 8 characters"></div>
    <div class="frm-row"><label>Confirm new password</label><input class="inp" type="password" id="pwNew2"></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="submitPassword()">Update password</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function submitPassword(){
  const oldPw=$("#pwOld").value, np=$("#pwNew").value, np2=$("#pwNew2").value;
  if(np!==np2){ toast("Those new passwords do not match","x"); return; }
  const r=await api("account.php",{action:"password",current:oldPw,password:np,confirm:np2});
  if(!r.ok){ toast(r.message||"Could not update your password","x"); return; }
  closeModal(); toast(r.message||"Password updated ✨","check");
}
function setCurrencyCookie(c){
  document.cookie="jl_currency="+encodeURIComponent(c)+";path=/;max-age="+(60*60*24*365)+";samesite=lax";
}
function bindAccount(){
  const cur=$("#accCur"); if(cur){ cur.value=currency;
    cur.addEventListener("change",()=>{ currency=cur.value; store.set("currency",currency); setCurrencyCookie(currency); render(); toast("Prices now shown in "+currency,"exchange"); });
  }
}

/* ---------------- AUTH ---------------- */
function pAuth(mode){
  const isIn=mode!=="register";
  const next=qp("next")||"";
  // /auth?mode=register&type=owner preselects the owner card, so the
  // "Create an owner account" links land on the right choice.
  const wantOwner=qp("type")==="owner";
  return `<div style="padding:calc(var(--header-h) + 30px) 20px 70px"><div class="auth-shell">
    <div class="auth-card">
      <span class="eyebrow">${isIn?"Welcome back":"Join the inner circle"}</span>
      <h1 style="margin-top:10px">${isIn?"Sign in to Jollof Living":"Create your account"}</h1>
      <p class="small">${isIn?"Trips, wishlists, points — all waiting.":"Email and a password — onboarding takes 90 seconds."}</p>
      <form id="authForm" novalidate autocomplete="on">
        <input type="hidden" id="authNext" value="${esc(next)}">
        ${isIn?"":`
        <div class="frm-row">
          <label>I want to…</label>
          <div class="acct-pick" id="auType" role="radiogroup" aria-label="Account type">
            ${[["customer","Book a stay","Find and book residences, save wishlists, earn points."],
               ["owner","List my property","Publish listings, manage bookings and get paid."]]
              .map(([v,t,d])=>{ const on=(v==="owner")===wantOwner; return `
              <label class="acct-opt${on?" active":""}" data-acct="${v}">
                <input type="radio" name="account_type" value="${v}"${on?" checked":""}>
                <span class="acct-t">${t}</span>
                <span class="acct-d">${d}</span>
              </label>`; }).join("")}
          </div>
        </div>`}
        ${isIn?"":`<div class="frm-row"><label>Full name</label><input class="inp" id="auName" name="name" placeholder="Adebayo Ogunlesi" autocomplete="name" required></div>`}
        <div class="frm-row"><label>Email</label><input class="inp" id="auEmail" name="email" type="email" placeholder="you@example.com" autocomplete="${isIn?"username":"email"}" required></div>
        ${isIn?"":`<div class="frm-row"><label>Phone <span class="small">(optional)</span></label><input class="inp" id="auPhone" name="phone" placeholder="+234 803 555 0123" autocomplete="tel"></div>`}
        <div class="frm-row"><label>Password</label>
          <div class="pw-wrap"><input class="inp" id="auPass" name="password" type="password" placeholder="${isIn?"••••••••":"At least 8 characters"}" autocomplete="${isIn?"current-password":"new-password"}" required>
          <button type="button" id="auEye" aria-label="Show or hide password">${I.eye}</button></div>
        </div>
        ${isIn?"":`<label class="chk" style="margin:6px 0 14px"><input type="checkbox" id="auTerms" checked> I agree to the Terms &amp; Privacy Policy</label>`}
        <button class="btn btn-gold btn-block" id="auSubmit" type="submit">${isIn?"Sign in":"Create account"}</button>
      </form>
      <p class="small" style="text-align:center;margin-top:14px">${isIn
        ? `New here? <a href="${URL("/auth?mode=register")}" style="color:var(--accent)">Create an account</a>`
        : `Already a member? <a href="${URL("/auth")}" style="color:var(--accent)">Sign in</a>`}</p>
      <p class="small" style="text-align:center;margin-top:8px">${I.shield} Passwords are hashed · NDPR/GDPR compliant</p>
    </div>
  </div></div>`;
}
function bindAuth(){
  const f=$("#authForm"); if(!f) return;
  const pick=$("#auType");
  if(pick){
    pick.addEventListener("change",()=>{
      $$(".acct-opt",pick).forEach(o=>o.classList.toggle("active",!!o.querySelector("input").checked));
    });
  }
  const eye=$("#auEye");
  if(eye) eye.addEventListener("click",()=>{ const p=$("#auPass"); p.type=p.type==="password"?"text":"password"; });
  f.addEventListener("submit",async (e)=>{
    e.preventDefault();
    const isRegister=!!$("#auName");
    const btn=$("#auSubmit"); btn.disabled=true;
    const label=btn.textContent; btn.textContent=isRegister?"Creating your account…":"Signing you in…";
    const payload={
      action:isRegister?"register":"login",
      email:($("#auEmail").value||"").trim(),
      password:$("#auPass").value||"",
    };
    if(isRegister){
      payload.name=($("#auName").value||"").trim();
      payload.phone=($("#auPhone").value||"").trim();
      // the API rejects a registration that has not accepted the terms
      const terms=$("#auTerms");
      if(terms && !terms.checked){
        btn.disabled=false; btn.textContent=label;
        toast("Please accept the Terms & Privacy Policy to continue","shield");
        return;
      }
      payload.terms=true;
      const picked=f.querySelector('input[name="account_type"]:checked');
      payload.account_type=picked?picked.value:"customer";
    }
    const otpEl=$("#auOtp");
    if(otpEl && !isRegister) payload.otp=otpEl.value.trim();
    const r=await api("auth.php",payload);
    if(!r.ok){
      btn.disabled=false; btn.textContent=label;
      // Admin sign-in needs the second factor (server-enforced) — reveal the code field.
      if(!isRegister && r.errors && r.errors.needsOtp && !$("#auOtp")){
        const row=document.createElement("div");
        row.className="frm-row";
        row.innerHTML=`<label>Administrator code</label><input class="inp" id="auOtp" inputmode="numeric" placeholder="6-digit code" autocomplete="one-time-code">`;
        btn.before(row);
        row.querySelector("input").focus();
      }
      toast(r.message||"Could not sign you in","x"); return;
    }
    toast(r.message||"Welcome ✨","check");
    // The server decides where to land: owners go to their workspace,
    // customers to their account. An explicit ?next= always wins.
    const next=$("#authNext").value;
    const dest=next || (r.data&&r.data.redirect) || URL("/account");
    setTimeout(()=>{ location.href = dest; },600);
  });
}


/* ===== pages-host.js ===== */
/* ============================================================
   JOLLOF LIVING — pages-host.js
   host landing · onboarding wizard · host dashboard · payments
   ============================================================ */

/* ---------------- HOST LANDING ---------------- */
function pHost(){
  // The call to action depends on who is looking: an owner goes straight to
  // their workspace, a signed-in customer is offered the upgrade, and a
  // visitor is invited to create an owner account.
  const cta = IS_OWNER
    ? `<a class="btn btn-gold" href="${URL('/host/onboarding')}">Add a listing</a><a class="btn btn-ghost" href="${URL('/host/dashboard')}">Open my dashboard</a>`
    : USER
      ? `<button class="btn btn-gold" id="hostUpgrade">Start hosting</button><a class="btn btn-ghost" href="${URL('/host/onboarding')}">See the listing wizard</a>`
      : `<a class="btn btn-gold" href="${URL('/auth?mode=register&type=owner')}">Create an owner account</a><a class="btn btn-ghost" href="${URL('/auth')}">Sign in</a>`;
  const upgradeNote = (!IS_OWNER && USER)
    ? `<div class="ai-callout" style="margin-bottom:24px">${I.spark}<span><b>You're signed in as a guest.</b> Turn on hosting to publish listings and open your owner dashboard — you keep your trips, wishlists and points.</span></div>`
    : "";
  return `${pageHead([["Home",URL("/")],["Host"]],"Host with <em class='serif-i'>Jollof Living</em>","We handle photography, pricing intelligence, guest screening and payouts — you keep 88% and the compliments.",cta)}
  <div class="page-body"><div class="wrap">
    ${upgradeNote}
    <div class="grid-4">
      ${[["88%","Host payout","plus automatic weekly transfers"],["24h","Average time to first booking","for optimised listings"],["−12%","Occupancy lift","from AI pricing and photography"],["120+","Cities & features","one platform, everything included"]].map(s=>`
      <div class="stat-kpi"><div class="lbl">${s[2]}</div><div class="val gold-text">${s[0]}</div><div style="font-size:14px">${s[1]}</div></div>`).join("")}
    </div>

    <div class="host-shell" style="margin-top:56px">
      <div>
        <span class="eyebrow">Everything, handled</span>
        <h2 style="font-size:clamp(1.9rem,4vw,2.8rem);margin:13px 0 10px">From empty apartment to <em class="serif-i">five-star listing</em></h2>
        <ul class="host-checks">
          <li>${I.checkCircle}<span><b>Professional photography</b> — a Jollof-approved photographer captures your property (or AI enhances your own photos).</span></li>
          <li>${I.checkCircle}<span><b>AI listing optimisation</b> — titles, descriptions and photo order tested automatically for conversion.</span></li>
          <li>${I.checkCircle}<span><b>Revenue management</b> — seasonal pricing, weekend premiums, holiday surcharges and min-stay rules.</span></li>
          <li>${I.checkCircle}<span><b>Full safety net</b> — damage protection, verified guests, KYC on both sides, dispute centre.</span></li>
        </ul>
        <div class="btnrow"><a class="btn btn-gold" href="${URL('/host/onboarding')}">List my residence</a><a class="btn btn-ghost" href="${URL('/payments')}">See how payouts work</a></div>
      </div>
      <div class="calc-card">
        <h3>Earnings <em class="serif-i">estimator</em></h3>
        <div class="small" style="margin-bottom:18px">Based on listed residences in Lagos &amp; Abuja</div>
        <div class="slider-row"><div class="lab"><span>Nightly rate</span><b id="hRateVal">₦150,000</b></div>
          <input type="range" id="hRate" min="50" max="500" value="150" step="5"></div>
        <div class="slider-row"><div class="lab"><span>Occupancy</span><b id="hOccVal">65%</b></div>
          <input type="range" id="hOcc" min="30" max="95" value="65" step="1"></div>
        <div class="calc-out">
          <div class="row"><span>Average nightly</span><span id="hAvg">₦150,000</span></div>
          <div class="row"><span>Gross monthly</span><span id="hGross">₦2,925,000 / mo</span></div>
          <div class="row"><span>Host payout (88%)</span><span id="hNet">₦2,574,000 / mo</span></div>
          <div class="row total"><span>Est. annual income</span><b id="hYear">₦30,888,000</b></div>
        </div>
      </div>
    </div>

    <div class="grid-2" style="margin-top:64px;gap:20px">
      <div class="panel"><h3 style="font-size:19px">Verified host badges</h3>
        <p class="muted" style="font-size:14px">Complete KYC, maintain 4.8★+ and a 95% response rate to earn <b>Superhost</b>. Pass an in-person or video inspection for the <b>Jollof Verified</b> property badge — the strongest trust signal in Nigerian luxury travel.</p>
        <div class="btnrow" style="margin-top:14px"><span class="badge ok">${I.gold} Jollof Verified</span><span class="badge">Superhost</span><button class="btn btn-ghost btn-sm" onclick="toast('Your property inspection is being scheduled ✨','camera')">Book property inspection</button></div>
      </div>
      <div class="panel"><h3 style="font-size:19px">Multi-property &amp; team</h3>
        <p class="muted" style="font-size:14px">Manage every listing from one dashboard. Invite co-hosts and property managers with role-based permissions — admin, calendar, messaging, finance.</p>
        <div class="btnrow" style="margin-top:14px">${IS_OWNER?`<button class="btn btn-ghost btn-sm" data-goto="/host/dashboard?tab=team">${I.users} Invite co-host</button>`:""}${IS_OWNER?`<button class="btn btn-ghost btn-sm" data-goto="/host/dashboard?tab=listings">My listings</button>`:""}</div>
      </div>
    </div>
  </div></div>`;
}

function bindHost(){
  /* Earnings estimator — the sliders exist on this page too, and without
     this binding they move but nothing recalculates. */
  const rate=$("#hRate"), occ=$("#hOcc");
  if(rate && occ){
    const rcalc=()=>{
      const r=+rate.value, o=+occ.value;
      const m=r*1000*30*o/100;
      $("#hRateVal").textContent="\u20A6"+(r*1000).toLocaleString();
      $("#hOccVal").textContent=o+"%";
      [rate,occ].forEach(el=>el.style.setProperty("--fill",((el.value-el.min)/(el.max-el.min)*100)+"%"));
      const set=(id,v)=>{ const el=$(id); if(el) el.textContent=v; };
      set("#hAvg","\u20A6"+Math.round(r*1000).toLocaleString()+" / night");
      set("#hGross","\u20A6"+Math.round(m).toLocaleString()+" / mo");
      set("#hNet","\u20A6"+Math.round(m*0.88).toLocaleString()+" / mo");
      set("#hYear","\u20A6"+Math.round(m*12*0.88).toLocaleString());
    };
    [rate,occ].forEach(el=>el.addEventListener("input",rcalc));
    rcalc();
  }

  /* Turn an existing customer account into an owner account. */
  const up=$("#hostUpgrade");
  if(up) up.addEventListener("click",async ()=>{
    up.disabled=true; const label=up.textContent; up.textContent="Setting things up…";
    const r=await api("host-action.php",{action:"upgrade"});
    if(!r.ok){ up.disabled=false; up.textContent=label; toast(r.message||"Could not enable hosting","x"); return; }
    toast(r.message||"Hosting enabled ✨","spark");
    setTimeout(()=>{ location.href=(r.data&&r.data.redirect)||URL("/host/dashboard"); },600);
  });
}

/* wizard constants */
const WIZ_STEPS=["Basics","Details & amenities","Photos","Pricing","Policies & rules","Review & submit"];
const WIZ={ step:0, data:{} };

function pHostOnboarding(){
  return `${pageHead([["Home",URL("/")],["Host",URL("/host")],["Listing wizard"]],"Create your <em class='serif-i'>listing</em>","Guided step-by-step — you can save and return anytime. Progress is never lost.")}
  <div class="page-body"><div class="wrap wizard-shell">
    <div class="wizard-steps" id="wizSteps">${WIZ_STEPS.map((s,i)=>`<div class="ws ${i===0?"done":""}"><span class="lbl">${i+1}. ${s}</span></div>`).join("")}</div>
    <div id="wizStage"></div>
  </div></div>`;
}
function wizRender(){
  $("#wizStage").innerHTML=[
    wizBasics,wizDetails,wizPhotos,wizPricing,wizPolicy,wizReview
  ][WIZ.step]();
  $$("#wizSteps .ws").forEach((w,i)=>w.classList.toggle("done",i<=WIZ.step));
  bindWdrop();
  window.scrollTo({top:0,behavior:"smooth"});
}
function wizVal(fn,d){ return fn(d); }
function wizBasics(){
  const d=WIZ.data;
  return `<div class="wizard-step">
    <h2>Tell us about the property</h2>
    <div class="sub">This becomes the public face of your listing — take your time, the AI will polish it later.</div>
    <div class="frm-grid">
      <div class="frm-row"><label>Listing title *</label><input class="inp" id="wTitle" value="${esc(d.title||"")}" placeholder="e.g. The Emerald Court — garden apartment in Ikoyi"></div>
      <div class="frm-row"><label>Property type</label><select class="sel" id="wType"><option>Penthouse</option><option>Villa</option><option>Suite</option><option>Loft</option><option>Furnished Townhouse</option><option>Heritage Stay</option></select></div>
      <div class="frm-row"><label>City</label><select class="sel" id="wCity"><option>Lagos</option><option>Abuja</option></select></div>
      <div class="frm-row"><label>Neighbourhood</label><input class="inp" id="wArea" value="${esc(d.area||"")}" placeholder="e.g. Ikoyi, Maitama, Lekki Phase 1"></div>
      <div class="frm-row"><label>Guests</label><input class="inp" type="number" id="wGuests" value="${d.guests||4}" min="1" max="16"></div>
      <div class="frm-row"><label>Bedrooms / Bathrooms</label><div style="display:flex;gap:8px"><input class="inp" type="number" id="wBeds" value="${d.beds||2}" min="1"><input class="inp" type="number" id="wBaths" value="${d.baths||2}" min="1"></div></div>
    </div>
    <div class="frm-row"><label>Description</label><textarea class="txa" id="wDesc" placeholder="What makes this place unforgettable?">${esc(d.desc||"")}</textarea></div>
    <div class="ai-callout">${I.spark}<span><b>AI description generator</b> will rewrite this in your chosen tone at the review step.</span></div>
    <div class="wizard-foot"><a class="btn btn-ghost" href="${URL('/host')}">← Cancel</a><button class="btn btn-gold" id="wizNext">Save &amp; continue</button></div>
  </div>`;
}
function wizDetails(){
  const d=WIZ.data;
  return `<div class="wizard-step">
    <h2>Details, amenities &amp; highlights</h2>
    <div class="sub">Amenities drive search results — tick everything that applies.</div>
    <div class="grid-3" id="wAmen">
      ${["Fast Wi-Fi","Gym","Pool","Kitchen","Air conditioning","Smart home","Security","Backup power","Parking","Breakfast","Washing machine","Workspace"].map(a=>`<label class="chk"><input type="checkbox" data-amen="${a}"> ${a}</label>`).join("")}
    </div>
    <div class="frm-grid" style="margin-top:18px">
      <div class="frm-row"><label>Premium amenity tags</label>
        <select class="sel" id="wPremium"><option>None</option><option>Infinity pool</option><option>Private cinema</option><option>Rooftop terrace</option><option>Wine cellar</option><option>Gym</option><option>Smart home</option></select></div>
      <div class="frm-row"><label>Event hosting</label><select class="sel" id="wEvent"><option>Not available</option><option>Intimate events only</option><option>Small receptions</option></select></div>
    </div>
    <div class="panel" style="margin-top:18px">
      <h3 style="font-size:17px">House rules &amp; safety checklist</h3>
      <div class="grid-3" style="margin-top:10px">
        ${["Smoke detectors","Fire extinguisher","First-aid kit","Carbon monoxide alarm","Emergency contact card","Quiet hours 10pm"].map(a=>`<label class="chk"><input type="checkbox" checked> ${a}</label>`).join("")}
      </div>
    </div>
    <div class="wizard-foot"><button class="btn btn-ghost" onclick="wizBack()">← Back</button><button class="btn btn-gold" id="wizNext">Save &amp; continue</button></div>
  </div>`;
}
function wizPhotos(){
  const ph=WIZ.data.photos||[];
  return `<div class="wizard-step">
    <h2>Photos &amp; virtual tour</h2>
    <div class="sub">Listings with professional photography convert 2.6× better. Upload your own photos here, or book a Jollof-approved photographer.</div>
    <div style="border:2px dashed var(--line);border-radius:16px;padding:30px 22px;text-align:center;background:var(--card-2)" id="wDrop">
      <div class="why-ico" style="margin:0 auto 14px">${I.camera}</div>
      <b style="font-family:var(--fs-serif);font-size:20px">${ph.length?`${ph.length} photo${ph.length>1?"s":""} uploaded — keep going`:"Drag &amp; drop up to 30 photos"}</b>
      <div class="small" style="margin:6px 0 14px">JPG or PNG, up to 10MB each. AI will auto-enhance, detect duplicates and order them for maximum impact.</div>
      <div class="btnrow" style="justify-content:center">
        <label class="btn btn-gold btn-sm" style="cursor:pointer" for="wFiles">${I.plus} Choose photos from device
          <input type="file" id="wFiles" accept="image/jpeg,image/png,image/webp,image/*" multiple style="display:none">
        </label>
        <button class="btn btn-ghost btn-sm" onclick="wizAddPhoto()">${I.plus} Use sample photos</button>
        <button class="btn btn-ghost btn-sm" onclick="toast('A Jollof-approved photographer will contact you','camera')">Book photographer</button>
      </div>
      <div class="small" style="margin-top:10px;color:var(--ink-faint)">or tap here to browse: <button class="link-arrow" style="font-size:12px" onclick="document.getElementById('wFiles').click()">open file picker</button></div>
    </div>
    <div class="grid-4" id="wImgRow" style="margin-top:16px">
      ${ph.length? wizThumbs(ph) : wizSampleThumbs()}
    </div>
    <div class="grid-2" style="margin-top:16px">
      <div class="panel"><b style="font-family:var(--fs-serif);font-size:16px">360° virtual tour</b><p class="small" style="margin-top:4px">Add a virtual walkthrough or drone footage of the exterior for premium listings.</p>
      <button class="btn btn-ghost btn-sm" style="margin-top:10px" onclick="toast('Virtual tour scheduled — we\'ll send the capture kit','eye')">Schedule 360° capture</button></div>
      <div class="panel"><b style="font-family:var(--fs-serif);font-size:16px">AI photo analysis</b><p class="small" style="margin-top:4px">Auto-tagging, low-quality detection and optimal ordering — done automatically on upload.</p></div>
    </div>
    <div class="wizard-foot"><button class="btn btn-ghost" onclick="wizBack()">← Back</button><button class="btn btn-gold" id="wizNext">Save &amp; continue</button></div>
  </div>`;
}
function wizThumbs(ph){
  return ph.map((p,i)=>`<div class="panel" style="padding:8px;position:relative">
      <img src="${p.url}" style="border-radius:10px;aspect-ratio:4/3;object-fit:cover;width:100%" alt="${esc(p.name)}">
      <button class="icon-btn" style="position:absolute;top:14px;right:14px;width:28px;height:28px;background:rgba(10,12,9,.68);color:#f2ead2;border:none" onclick="wizRmPhoto(${i})" aria-label="Remove photo" title="Remove">${I.x}</button>
      <div class="small" style="margin-top:6px">${esc(p.name.length>20?p.name.slice(0,20)+"…":p.name)} <span style="color:var(--ok)">✓ ready</span></div>
    </div>`).join("");
}
function wizSampleThumbs(){
  return ["p1","p12","p8","p4"].map(k=>`<div class="panel" style="padding:8px;position:relative"><img src="${img(k)}" style="border-radius:10px;aspect-ratio:4/3;object-fit:cover;width:100%" alt="">
    <div class="small" style="margin-top:6px">${k==="p1"?"Living room":k==="p12"?"Garden":"Bathroom"} <span style="color:var(--ok)">✓ enhanced</span></div></div>`).join("");
}
function wizAddPhotos(input){
  if(!input||!input.files||!input.files.length) return;
  if(!WIZ.data.photos) WIZ.data.photos=[];
  let n=0;
  for(const f of input.files){
    if(WIZ.data.photos.length>=30){ toast("Up to 30 photos — remove some first","x"); break; }
    if(!/^image\//.test(f.type)){ toast(`${f.name} isn't an image — skipped`,"x"); continue; }
    if(f.size>10*1024*1024){ toast(`${f.name} is over 10MB — skipped`,"x"); continue; }
    WIZ.data.photos.push({name:f.name, size:f.size, url:window.URL.createObjectURL(f)});
    n++;
  }
  if(n){ toast(`${n} photo${n>1?"s":""} uploaded — AI enhancement queued ✨`,"camera"); wizRenderPhotos(); }
  input.value="";
}
function wizRmPhoto(i){
  if(!WIZ.data.photos) return;
  const [p]=WIZ.data.photos.splice(i,1);
  if(p&&p.url.startsWith("blob:")) window.URL.revokeObjectURL(p.url);
  toast("Photo removed","x"); wizRenderPhotos();
}
function wizRenderPhotos(){
  const row=$("#wImgRow"), drop=$("#wDrop");
  if(row) row.innerHTML=WIZ.data.photos.length?wizThumbs(WIZ.data.photos):wizSampleThumbs();
  if(drop){ const b=drop.querySelector("b"); if(b) b.textContent=WIZ.data.photos.length?`${WIZ.data.photos.length} photo${WIZ.data.photos.length>1?"s":""} uploaded — keep going`:"Drag &amp; drop up to 30 photos"; }
}
function bindWdrop(){
  const dz=$("#wDrop"), fi=$("#wFiles");
  if(!dz||!fi) return;
  fi.addEventListener("change",()=>wizAddPhotos(fi));
  ["dragover","dragenter"].forEach(ev=>dz.addEventListener(ev,e=>{ e.preventDefault(); dz.style.borderColor="var(--accent)"; dz.style.background="var(--gold-soft)"; }));
  ["dragleave","drop"].forEach(ev=>dz.addEventListener(ev,e=>{ e.preventDefault(); dz.style.borderColor=""; dz.style.background=""; }));
  dz.addEventListener("drop",e=>{ const f=e.dataTransfer&&e.dataTransfer.files; if(f&&f.length) wizAddPhotos({files:f}); });
}
function wizPricing(){
  return `<div class="wizard-step">
    <h2>Pricing &amp; revenue management</h2>
    <div class="sub">You set the base; our AI suggests premium adjustments — the final call is always yours.</div>
    <div class="grid-2">
      <div class="stack">
        <div class="panel"><div class="frm-row"><label>Base nightly rate (₦)</label><input class="inp" type="number" id="wRate" value="${WIZ.data.rate||150000}" step="5000"></div>
        <div class="frm-row"><label>Minimum nights</label><select class="sel" id="wMin"><option>1</option><option>2</option><option>3</option><option>7 (weekly)</option><option>30 (monthly)</option></select></div>
        <div class="frm-row"><label>Weekly discount</label><select class="sel" id="wDisc"><option value="0.10">−10%</option><option value="0.12" selected>−12% (recommended)</option><option value="0.15">−15%</option></select></div>
        <div class="frm-row"><label>Monthly discount</label><select class="sel" id="wDiscM"><option value="0.2">−20%</option><option value="0.25" selected>−25% (recommended)</option><option value="0.3">−30%</option></select></div>
      </div>
      <div class="stack">
        <div class="panel"><h3 style="font-size:16px">Seasonal pricing</h3>
          <div class="krow"><span class="k">Weekend premium (Fri–Sun)</span><span class="v">+15%</span></div>
          <div class="krow"><span class="k">Detty December (Dec 15 – Jan 5)</span><span class="v">+25%</span></div>
          <div class="krow"><span class="k">Easter &amp; Eid weekends</span><span class="v">+18%</span></div>
          <div class="krow"><span class="k">Lean season (Feb – Apr)</span><span class="v">−10%</span></div>
          <button class="btn btn-ghost btn-sm" style="margin-top:10px" onclick="toast('Custom per-date pricing opened — click any date in your calendar','calendar')">Edit per-date pricing</button>
        </div>
        <div class="ai-callout">${I.spark}<span><b>AI smart pricing:</b> for your area and this size, ₦148,000–₦165,000/night is forecast to maximise occupancy × revenue.</span></div>
      </div>
    </div>
    <div class="wizard-foot"><button class="btn btn-ghost" onclick="wizBack()">← Back</button><button class="btn btn-gold" id="wizNext">Save &amp; continue</button></div>
  </div>`;
}
function wizPolicy(){
  return `<div class="wizard-step">
    <h2>Policies &amp; terms</h2>
    <div class="sub">Transparent policies build trust — and trust converts.</div>
    <div class="grid-2">
      <div class="panel"><div class="frm-row"><label>Cancellation policy</label>
        <select class="sel" id="wPol"><option value="flexible">Flexible — full refund to 48h</option><option value="moderate" selected>Moderate — full refund to 5 days</option><option value="strict">Strict — 50% to 14 days</option></select></div>
        <label class="chk" style="margin-top:6px"><input type="checkbox" checked> Instant Book enabled (trusted guests)</label><br>
        <label class="chk" style="margin-top:8px"><input type="checkbox" checked> Accept split payments on 30+ nights</label><br>
        <label class="chk" style="margin-top:8px"><input type="checkbox" checked> Offer welcome packages &amp; housekeeping add-ons</label>
      </div>
      <div class="panel">
        <h3 style="font-size:16px">Payouts &amp; compliance</h3>
        <div class="krow"><span class="k">Commission</span><span class="v">12% · you keep 88%</span></div>
        <div class="krow"><span class="k">Payout schedule</span><span class="v">Weekly, automatic</span></div>
        <div class="krow"><span class="k">Withholding tax (WHT)</span><span class="v">Auto-computed &amp; filed</span></div>
        <div class="krow"><span class="k">Damage protection</span><span class="v">Included up to ₦2m</span></div>
        <div class="krow"><span class="k">Escrow release</span><span class="v">After guest check-out</span></div>
      </div>
    </div>
    <div class="wizard-foot"><button class="btn btn-ghost" onclick="wizBack()">← Back</button><button class="btn btn-gold" id="wizNext">Save &amp; continue</button></div>
  </div>`;
}
function wizReview(){
  const d=WIZ.data;
  return `<div class="wizard-step">
    <h2>Review &amp; submit</h2>
    <div class="sub">Our team verifies every new listing, then it goes live. Estimated time to first booking: <b>24–48 hours</b>.</div>
    <div class="panel" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
      ${(d.photos&&d.photos[0])?`<img src="${d.photos[0].url}" style="width:150px;height:110px;object-fit:cover;border-radius:12px" alt="">`:`<img src="${img('p1')}" style="width:150px;height:110px;object-fit:cover;border-radius:12px" alt="">`}
      <div style="flex:1;min-width:220px"><b style="font-family:var(--fs-serif);font-size:21px">${esc(d.title||"The Emerald Court")}</b>
      <div class="small" style="margin-top:4px">${esc(d.area||"Ikoyi")} · ${d.guests||4} guests · ${d.beds||2} bd · rate ${fmt(d.rate||150000)}/night · ${(d.photos||[]).length||4} photo${(d.photos||[]).length===1?"":"s"}</div>
      <div class="btnrow" style="margin-top:8px"><span class="pill-status info">${I.clock} Awaiting verification</span><span class="pill-status gold">KYC complete</span></div></div>
      <button class="btn btn-ghost btn-sm" onclick="toast('AI descriptor in Luxury tone generated ✨','spark')">${I.spark} AI description · Luxury tone</button>
    </div>
    <div class="ai-callout" style="margin-top:16px">${I.spark}<span><b>AI listing check:</b> your title is missing “ocean view” — adding it could increase views by <b>+30%</b>. We'll apply it automatically.</span></div>
    <div class="frm-row"><label>Anything else we should know?</label><textarea class="txa" placeholder="Special quirks, staff on site, access notes…"></textarea></div>
    <div class="wizard-foot"><button class="btn btn-ghost" onclick="wizBack()">← Back</button><button class="btn btn-gold btn-lg" id="wizSubmit">Submit listing →</button></div>
  </div>`;
}
function wizBack(){ WIZ.step=Math.max(0,WIZ.step-1); wizRender(); }
function wizGather(){
  /* only capture fields present on the current step; never overwrite with "undefined" */
  const txt=(id,k)=>{ const el=$(id); if(el&&el.value) WIZ.data[k]=el.value; };
  const num=(id,k)=>{ const el=$(id); if(el&&el.value!=="") WIZ.data[k]=+el.value; };
  txt("#wTitle","title"); txt("#wArea","area"); txt("#wDesc","desc");
  num("#wGuests","guests"); num("#wBeds","beds"); num("#wBaths","baths"); num("#wRate","rate");
  const am=$$("[data-amen]:checked"); if(am.length) WIZ.data.amens=am.map(x=>x.dataset.amen);
}
function wizAdvance(){
  if(WIZ.step===0){
    const t=$("#wTitle"); if(!t||!t.value.trim()){ toast("Give your listing a title","x"); return; }
  }
  wizGather(); WIZ.step++; wizRender();
}
function bindHostOnboarding(){
  WIZ.step=0;
  WIZ.data.photos=WIZ.data.photos||[];
  wizRender();
  document.addEventListener("click",function wizClick(e){
    const nx=e.target.closest("#wizNext"); if(nx){ wizAdvance(); return; }
    const sub=e.target.closest("#wizSubmit"); if(sub){ wizSubmit(); return; }
  });
}
async function wizSubmit(){
  if(!requireAuth("list your residence")) return;
  wizGather();
  const d=WIZ.data;
  const btn=$("#wizSubmit"); if(btn){ btn.disabled=true; btn.textContent="Submitting…"; }
  const r=await api("listing-create.php",{
    title:d.title, area:d.area, city:d.city||"Lagos", description:d.desc,
    guests:d.guests, beds:d.beds, baths:d.baths, price:d.rate,
    type:d.type||"Apartment", policy:d.policy||"moderate",
    amenities:d.amens||[], photos:(d.photos||[]).map(p=>p.name),
  });
  if(!r.ok){ if(btn){ btn.disabled=false; btn.textContent="Submit for verification"; } toast(r.message||"Could not submit that listing","x"); return; }
  toast(r.message||"Listing submitted — our team will verify within 24h ✨","check");
  setTimeout(()=>nav("/host/dashboard?tab=listings&new=1"),700);
}
function wizAddPhoto(){
  if(!WIZ.data.photos) WIZ.data.photos=[];
  const samples=[["p1","sample-living.jpg"],["p12","sample-garden.jpg"],["p8","sample-bath.jpg"],["p4","sample-bedroom.jpg"]];
  let n=0;
  for(const [k,name] of samples){
    if(WIZ.data.photos.length>=30) break;
    if(WIZ.data.photos.some(p=>p.name===name)) continue;
    WIZ.data.photos.push({name, size:0, url:`${img(k)}`});
    n++;
  }
  if(n){ toast(`${n} sample photo${n>1?"s":""} added — AI enhancement applied ✨`,"camera"); wizRenderPhotos(); }
  else toast("Sample photos already added","check");
}

/* ---------------- HOST DASHBOARD ---------------- */
/* ============================================================
   OWNER (HOST) DASHBOARD
   Every panel below reads HOST, which the server builds from the
   owner's own rows (Repo::hostState). Nothing here is invented:
   when an owner has no data yet the panels say so rather than
   showing someone else's numbers.
   ============================================================ */

/* Small helpers so empty accounts render gracefully. */
function hdStat(){ return HOST.stats||{}; }
function hdEmpty(emoji,title,body,cta){
  return `<div class="panel hd-empty">
    <span class="hd-emoji">${emoji}</span>
    <h3 style="font-size:19px">${esc(title)}</h3>
    <p class="small" style="max-width:420px;margin:6px auto 0">${esc(body)}</p>
    ${cta||""}
  </div>`;
}
const hdAddCta = `<div class="btnrow" style="justify-content:center;margin-top:14px">
  <button class="btn btn-gold btn-sm" data-goto="/host/onboarding">Add a listing</button></div>`;
/* Charts divide by (length-1), so guard against 0 and 1 point. */
function hdSafeSeries(rows){
  const r=(rows||[]).filter(x=>x&&typeof x.v==="number");
  if(!r.length) return null;
  return r.length===1 ? [r[0],{...r[0]}] : r;
}

function pHostDashboard(q){
  const tab=(q&&q.tab)||"overview";
  const nav=[["overview","Overview","grid"],["calendar","Calendar & pricing","calendar"],["listings","Listings","building"],["analytics","Analytics","eye"],["revenue","Revenue management","spark"],["ai","AI tools","bot"],["team","Team & co-hosts","users"],["templates","Message templates","send"],["channels","Channel manager","globe"],["payouts","Payouts","wallet"]];
  const st=hdStat();
  const badges=[
    `<span class="badge">${st.listings||0} listing${(st.listings||0)===1?"":"s"}</span>`,
    st.rating?`<span class="badge ok">${I.gold} ${st.rating} rating</span>`:"",
    `<span class="badge">Take rate ${Math.round((st.takeRate||0.12)*100)}%</span>`,
    `<a class="btn btn-ghost btn-sm" href="${JL.base}logout.php">Log out</a>`,
  ].join("");
  return `${pageHead([["Home",URL("/")],["Host",URL("/host")],["Dashboard"]],"Owner <em class='serif-i'>dashboard</em>","Your listings, bookings, earnings and payouts — all in one place.",badges)}
  <div class="page-body"><div class="wrap">
    <div class="admin-shell">
      <nav class="admin-nav">
        <div class="sec">Owner tools</div>
        ${nav.map(([k,l,i])=>`<a href="${URL("/host/dashboard")}?tab=${k}" class="${tab===k?"active":""}">${I[i]} ${l}</a>`).join("")}
        <div class="sec" style="margin-top:14px">Account</div>
        <a href="${URL("/account")}">${I.users} My account</a>
        <a href="${JL.base}logout.php">${I.lock} Log out</a>
      </nav>
      <div id="hdContent">${(()=>{ const m=[["overview",hdOverview],["calendar",hdCalendar],["listings",hdListings],["analytics",hdAnalytics],["revenue",hdRevenue],["ai",hdAI],["team",hdTeam],["templates",hdTemplates],["channels",hdChannels],["payouts",hdPayouts]].find(([k])=>k===tab)||["overview",hdOverview]; return m[1](); })()}</div>
    </div>
  </div></div>`;
}

function hdOverview(){
  const st=hdStat();
  if(!HOST.listings.length) return hdEmpty("🏠","No listings yet","Once you publish your first residence, your occupancy, earnings and bookings appear here.",hdAddCta);

  const series=hdSafeSeries(HOST.earnings);
  const upcoming=(HOST.bookings||[]).filter(b=>["pending","confirmed"].includes(b.status)).slice(0,5);
  const sources=(HOST.sources||[]).filter(s=>s.v>0);
  const palette=["var(--accent)","var(--green)","var(--gold-soft)","var(--line)"];
  const srcTotal=sources.reduce((a,s)=>a+s.v,0);

  return `
  <div class="grid-4">
    ${[["Occupancy",(st.occupancy||0)+"%","last 90 days"],
       ["ADR (avg daily rate)",st.adr?K(st.adr):"—","per night sold"],
       ["RevPAR",st.revpar?K(st.revpar):"—","revenue per available night"],
       ["Booking lead time",(st.leadTime||0)+" days","average"]].map(k=>`
    <div class="stat-kpi"><div class="lbl">${k[0]}</div><div class="val">${k[1]}</div><div class="small">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-4" style="margin-top:14px">
    ${[["Gross earnings",st.gross?fmt(st.gross):"—","all time"],
       ["Your net",st.net?fmt(st.net):"—",`after ${Math.round((st.takeRate||0.12)*100)}% platform fee`],
       ["Held in escrow",st.escrowHeld?fmt(st.escrowHeld):"—","released after check-in"],
       ["Bookings",String(st.bookings||0),`${st.upcoming||0} upcoming`]].map(k=>`
    <div class="stat-kpi"><div class="lbl">${k[0]}</div><div class="val" style="font-size:22px">${k[1]}</div><div class="small">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="chart-box"><h4>Earnings — last 12 months</h4>
      ${series?lineChart(series,{fmt:v=>"₦"+v+"k",labels:series.map(s=>s.l)}):`<p class="small">No earnings recorded yet.</p>`}</div>
    <div class="chart-box"><h4>Booking sources</h4>
      ${srcTotal?`${donutChart(sources.map((s,i)=>({v:s.v,c:palette[i%palette.length]})),[String(srcTotal),"bookings"])}
      <div class="legend" style="justify-content:center">${sources.map((s,i)=>`<span><i style="background:${palette[i%palette.length]}"></i>${esc(s.l)}</span>`).join("")}</div>`
      :`<p class="small">No bookings yet — sources appear once guests start booking.</p>`}
    </div>
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="panel"><h3 style="font-size:18px">Upcoming bookings</h3>
      ${upcoming.length?upcoming.map(b=>`
      <div class="krow"><span class="k"><b style="font-family:var(--fs-serif);font-size:16px">${esc(b.checkin)} → ${esc(b.checkout)}</b><br><span class="small">${esc(b.property)} · ${esc(b.guest||"Guest")}</span></span>
      <span class="v">${fmt(b.total)}<div class="sub">${b.nights} night${b.nights===1?"":"s"} · ${esc(b.status)}</div></span></div>`).join("")
      :`<p class="small">Nothing on the calendar yet.</p>`}
    </div>
    <div class="panel"><h3 style="font-size:18px">Quick actions</h3>
      <div class="btnrow" style="margin-top:6px;flex-wrap:wrap">
        <button class="btn btn-gold btn-sm" data-goto="/host/onboarding">${I.plus} Add a listing</button>
        <a class="btn btn-ghost btn-sm" href="${URL("/host/dashboard")}?tab=calendar">${I.calendar} Edit calendar</a>
        <a class="btn btn-ghost btn-sm" href="${URL("/host/dashboard")}?tab=payouts">${I.wallet} Payouts</a>
        <a class="btn btn-ghost btn-sm" href="${JL.apiBase}host-report.php?r=earnings">${I.download} Earnings CSV</a>
      </div>
      ${st.listingsPending?`<div class="ai-callout" style="margin-top:14px">${I.clock}<span>${st.listingsPending} listing${st.listingsPending===1?" is":"s are"} still in verification.</span></div>`:""}
    </div>
  </div>`;
}

/* ------------------------------------------------ calendar & pricing */
function hdCalendar(){
  if(!HOST.listings.length) return hdEmpty("📅","No calendar yet","Add a listing and you can set nightly prices and block dates here.",hdAddCta);
  const cal=HOST.calendar||{days:[]};
  const prop=cal.property;
  const opts=HOST.listings.map(l=>`<option value="${l.id}"${prop&&prop.id===l.id?" selected":""}>${esc(l.name)}</option>`).join("");
  return `
  <div class="panel">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
      <h3 style="font-size:19px" id="hdCalTitle">${esc(cal.monthLabel||"")}</h3>
      <select class="sel" id="hdCalProp" style="max-width:260px">${opts}</select>
      <div class="btnrow" style="margin-left:auto">
        <button class="btn btn-ghost btn-sm" id="hdCalPrev">← Prev</button>
        <button class="btn btn-ghost btn-sm" id="hdCalNext">Next →</button>
        <button class="btn btn-ghost btn-sm" id="hdBulk">${I.spark} Bulk edit</button>
      </div>
    </div>
    <p class="small" style="margin-bottom:10px">Click a date to set its price or block it. Booked nights cannot be changed.</p>
    <div class="cal-grid" style="grid-template-columns:repeat(7,1fr);gap:6px" id="hdCal">${hdCalCells(cal)}</div>
    <div class="legend" style="margin-top:12px">
      <span><i style="background:var(--gold-soft)"></i>Weekend</span>
      <span><i style="background:var(--card-2)"></i>Booked</span>
      <span><i style="background:var(--line)"></i>Blocked</span>
    </div>
  </div>`;
}
function hdCalCells(cal){
  const dows=["Mon","Tue","Wed","Thu","Fri","Sat","Sun"].map(d=>`<div class="dow">${d}</div>`).join("");
  const pad=Array.from({length:Math.max(0,cal.firstDow||0)}).map(()=>"<span></span>").join("");
  const cells=(cal.days||[]).map(d=>{
    const cls=["hd-cal-day"];
    if(d.weekend) cls.push("is-weekend");
    if(d.booked) cls.push("is-booked");
    if(d.blocked) cls.push("is-blocked");
    return `<div class="${cls.join(" ")}" data-day="${d.iso}" data-price="${d.price}" data-blocked="${d.blocked?1:0}" data-booked="${d.booked?1:0}" title="${d.booked?"Booked":"Edit price"}">
      <div class="d">${d.day}</div>
      <div class="p">${K(d.price)}</div>
      ${d.booked?`<div class="s" style="color:var(--ok)">booked</div>`:d.blocked?`<div class="s" style="color:var(--muted)">blocked</div>`:""}
    </div>`;
  }).join("");
  return dows+pad+cells;
}

/* -------------------------------------------------------- listings */
function hdListings(){
  if(!HOST.listings.length) return hdEmpty("🏠","No listings yet","Publish your first residence to start receiving bookings.",hdAddCta);
  return `
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Listing</th><th>Nightly</th><th>Occupancy</th><th>Rating</th><th>Earned</th><th>Status</th><th></th></tr></thead>
    <tbody>
      ${HOST.listings.map(l=>{
        const level=l.status==="live"?"ok":(l.status==="paused"?"info":"warn");
        const label=l.status==="live"?"Live":(l.status==="paused"?"Paused":(l.status==="pending"?"In verification":cap(l.status)));
        return `<tr>
        <td><b class="strong">${esc(l.name)}</b><div class="sub">${esc(l.area||"—")}${l.city?" · "+esc(l.city):""}</div></td>
        <td>${fmt(l.price)}</td>
        <td>${l.status==="live"?l.occupancy+"%":"—"}</td>
        <td>${l.reviews?l.rating+" ("+l.reviews+")":"—"}</td>
        <td>${l.revenue?fmt(l.revenue):"—"}</td>
        <td><span class="pill-status ${level}">${label}</span></td>
        <td><div class="btnrow">
          ${l.status==="live"?`<button class="btn btn-ghost btn-sm" data-goto="/stay/${esc(l.slug)}">View</button>`:""}
          <button class="btn btn-ghost btn-sm" data-hd-price="${l.id}" data-price="${l.price}" data-name="${esc(l.name)}">${I.edit} Price</button>
          ${l.status==="live"?`<button class="btn btn-ghost btn-sm" data-hd-status="${l.id}" data-to="paused">Pause</button>`:""}
          ${l.status==="paused"?`<button class="btn btn-green btn-sm" data-hd-status="${l.id}" data-to="live">Go live</button>`:""}
        </div></td>
      </tr>`;}).join("")}
    </tbody></table></div>
  <div class="btnrow" style="margin-top:14px"><button class="btn btn-gold btn-sm" data-goto="/host/onboarding">${I.plus} Add another listing</button></div>`;
}

/* -------------------------------------------------------- analytics */
function hdAnalytics(){
  const st=hdStat();
  if(!HOST.listings.length) return hdEmpty("📊","Nothing to analyse yet","Analytics appear once your first listing is live.",hdAddCta);
  const series=hdSafeSeries(HOST.earnings);
  const byListing=HOST.listings.filter(l=>l.revenue>0).map(l=>({l:l.name.length>16?l.name.slice(0,15)+"…":l.name,v:Math.round(l.revenue/1000)}));
  return `
  <div class="grid-3">
    ${[["Bookings (all time)",String(st.bookings||0),`${st.bookings30||0} in the last 30 days`],
       ["Nights sold",String(st.nightsSold||0),"confirmed and completed"],
       ["Occupancy",(st.occupancy||0)+"%","rolling 90 days"],
       ["Guest rating",st.rating?String(st.rating):"—",`${st.reviews||0} review${(st.reviews||0)===1?"":"s"}`],
       ["Gross revenue",st.gross?fmt(st.gross):"—","before platform fee"],
       ["Awaiting approval",String(st.pending||0),"booking requests"]].map(k=>`
    <div class="stat-kpi"><div class="lbl">${k[0]}</div><div class="val" style="font-size:22px">${k[1]}</div><div class="small">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="chart-box"><h4>Earnings trend</h4>
      ${series?lineChart(series,{fmt:v=>"₦"+v+"k",labels:series.map(s=>s.l)}):`<p class="small">No earnings recorded yet.</p>`}</div>
    <div class="chart-box"><h4>Revenue by listing</h4>
      ${byListing.length?barChart(byListing):`<p class="small">No revenue recorded yet.</p>`}</div>
  </div>
  <div class="panel" style="margin-top:18px"><h3 style="font-size:18px">Export &amp; reports</h3>
    <div class="btnrow"><a class="btn btn-ghost btn-sm" href="${JL.apiBase}host-report.php?r=earnings">${I.download} Earnings CSV</a>
    <a class="btn btn-ghost btn-sm" href="${JL.apiBase}host-report.php?r=payouts">${I.download} Payout statement</a></div>
  </div>`;
}

/* ------------------------------------------------ revenue management */
function hdRevenue(){
  const st=hdStat();
  const rules=HOST.rules||[];
  return `
  <div class="grid-2">
    <div class="panel">
      <h3 style="font-size:18px">Pricing rules</h3>
      ${rules.length?rules.map(r=>`
      <div class="krow"><span class="k">${esc(r.name)}<div class="sub">${esc(cap(r.kind))}${r.starts?` · ${esc(r.starts)}${r.ends?" → "+esc(r.ends):""}`:""}</div></span>
        <span class="v">${r.adjust>0?"+":""}${r.adjust}%
          <div class="btnrow" style="margin-top:6px">
            <button class="btn btn-ghost btn-sm" data-rule-toggle="${r.id}">${r.active?"Pause":"Enable"}</button>
            <button class="btn btn-ghost btn-sm" data-rule-del="${r.id}">Remove</button>
          </div>
        </span></div>`).join("")
      :`<p class="small">No pricing rules yet. Add one to raise prices in peak season or discount quiet weeks.</p>`}
      <button class="btn btn-green btn-sm" style="margin-top:10px" id="hdAddRule">${I.plus} Add rule</button>
    </div>
    <div class="stack">
      <div class="panel"><h3 style="font-size:18px">What-if revenue model</h3>
        <p class="small" style="margin-bottom:8px">Based on your real occupancy (${st.occupancy||0}%) and average rate (${st.adr?fmt(st.adr):"—"}).</p>
        <div class="slider-row"><div class="lab"><span>Price index</span><b id="rwB">100%</b></div>
          <input type="range" id="rwPrice" min="80" max="125" value="100" oninput="$('#rwB').textContent=this.value+'%';rwCalc()"></div>
        <div class="calc-out">
          <div class="row"><span>Projected annual gross</span><span id="rwOut">—</span></div>
          <div class="row"><span>Projected occupancy</span><span id="rwOcc">${st.occupancy||0}%</span></div>
          <div class="row"><span>Your net after fees</span><span id="rwNet">—</span></div>
        </div>
      </div>
      ${st.occupancy>85?`<div class="ai-callout">${I.spark}<span>Occupancy is above 85% — there is room to raise your nightly rate.</span></div>`
        :st.occupancy&&st.occupancy<40?`<div class="ai-callout">${I.spark}<span>Occupancy is under 40% — a small price reduction or wider availability usually helps.</span></div>`:""}
    </div>
  </div>`;
}

function bindHostDashboard(){
  const st=hdStat();

  /* what-if model, driven by the owner's real numbers */
  window.rwCalc=()=>{
    const el=$("#rwPrice"); if(!el) return;
    const pr=+el.value/100;
    // Higher prices soften occupancy; a simple elasticity is honest enough
    // for a planning tool and is clearly labelled as a projection.
    const occ=Math.max(0,Math.min(100,Math.round((st.occupancy||0)*(1-(pr-1)*0.8))));
    const annual=(st.adr||0)*pr*(st.listings||0)*365*(occ/100);
    const out=$("#rwOut"), oc=$("#rwOcc"), net=$("#rwNet");
    if(out) out.textContent=annual?fmt(Math.round(annual)):"—";
    if(oc) oc.textContent=occ+"%";
    if(net) net.textContent=annual?fmt(Math.round(annual*(1-(st.takeRate||0.12)))):"—";
  };
  if($("#rwPrice")) window.rwCalc();

  /* ---------------- calendar ---------------- */
  const calState={month:(HOST.calendar&&HOST.calendar.month)||new Date().toISOString().slice(0,7),
                  property:(HOST.calendar&&HOST.calendar.property&&HOST.calendar.property.id)||0};

  async function calLoad(){
    const r=await api("host-action.php",{action:"calendar",property:calState.property,month:calState.month});
    if(!r.ok){ toast(r.message||"Could not load that month","x"); return; }
    HOST.calendar=r.data.calendar;
    calState.month=HOST.calendar.month;
    const grid=$("#hdCal"), title=$("#hdCalTitle");
    if(grid) grid.innerHTML=hdCalCells(HOST.calendar);
    if(title) title.textContent=HOST.calendar.monthLabel;
  }
  const shift=(n)=>{ const d=new Date(calState.month+"-01T00:00:00"); d.setMonth(d.getMonth()+n);
    calState.month=d.toISOString().slice(0,7); calLoad(); };
  if($("#hdCalPrev")) $("#hdCalPrev").addEventListener("click",()=>shift(-1));
  if($("#hdCalNext")) $("#hdCalNext").addEventListener("click",()=>shift(1));
  if($("#hdCalProp")) $("#hdCalProp").addEventListener("change",(e)=>{ calState.property=+e.target.value; calLoad(); });

  const cal=$("#hdCal");
  if(cal) cal.addEventListener("click",(e)=>{
    const cell=e.target.closest(".hd-cal-day"); if(!cell) return;
    if(cell.dataset.booked==="1"){ toast("That night is booked — cancel the booking first","clock"); return; }
    const iso=cell.dataset.day, price=+cell.dataset.price, blocked=cell.dataset.blocked==="1";
    openModal(`<h2 style="margin-bottom:4px">${iso}</h2>
      <p class="small" style="margin-bottom:14px">Set the nightly rate for this date, or block it.</p>
      <div class="frm-row"><label>Nightly rate (₦)</label><input class="inp" type="number" id="hdDayPrice" value="${price}" step="5000" min="0"></div>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" id="hdDayBlock"${blocked?" checked":""}> Block this date</label>
      <div class="btnrow"><button class="btn btn-gold" id="hdDaySave">Save</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
    const save=$("#hdDaySave");
    if(save) save.addEventListener("click",async ()=>{
      save.disabled=true;
      const r=await api("host-action.php",{action:"set-day",property:calState.property,day:iso,
        price:+($("#hdDayPrice").value||0),blocked:$("#hdDayBlock").checked});
      save.disabled=false;
      if(!r.ok){ toast(r.message||"Could not save","x"); return; }
      HOST.calendar=r.data.calendar;
      const grid=$("#hdCal"); if(grid) grid.innerHTML=hdCalCells(HOST.calendar);
      closeModal(); toast(r.message||"Saved","check");
    });
  });

  if($("#hdBulk")) $("#hdBulk").addEventListener("click",()=>{
    const first=calState.month+"-01";
    openModal(`<h2 style="margin-bottom:4px">Bulk price edit</h2>
      <p class="small" style="margin-bottom:14px">Apply one nightly rate across a date range.</p>
      <div class="frm-row"><label>From</label><input class="inp" type="date" id="hdBkFrom" value="${first}"></div>
      <div class="frm-row"><label>To</label><input class="inp" type="date" id="hdBkTo" value="${first}"></div>
      <div class="frm-row"><label>Nightly rate (₦)</label><input class="inp" type="number" id="hdBkPrice" step="5000" min="1000" placeholder="150000"></div>
      <div class="btnrow"><button class="btn btn-gold" id="hdBkSave">Apply</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
    const save=$("#hdBkSave");
    if(save) save.addEventListener("click",async ()=>{
      save.disabled=true;
      const r=await api("host-action.php",{action:"bulk-price",property:calState.property,
        from:$("#hdBkFrom").value,to:$("#hdBkTo").value,price:+($("#hdBkPrice").value||0)});
      save.disabled=false;
      if(!r.ok){ toast(r.message||"Could not apply","x"); return; }
      HOST.calendar=r.data.calendar;
      const grid=$("#hdCal"); if(grid) grid.innerHTML=hdCalCells(HOST.calendar);
      closeModal(); toast(r.message||"Updated","check");
    });
  });

  /* ---------------- delegated actions across the tabs ---------------- */
  const content=$("#hdContent");
  if(content) content.addEventListener("click",async (e)=>{
    const reload=()=>location.reload();

    const st2=e.target.closest("[data-hd-status]");
    if(st2){ const r=await api("host-action.php",{action:"listing-status",property:+st2.dataset.hdStatus,status:st2.dataset.to});
      toast(r.message||(r.ok?"Updated":"Could not update"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const pr=e.target.closest("[data-hd-price]");
    if(pr){
      const id=+pr.dataset.hdPrice;
      openModal(`<h2 style="margin-bottom:4px">${esc(pr.dataset.name||"Nightly rate")}</h2>
        <div class="frm-row"><label>Nightly rate (₦)</label><input class="inp" type="number" id="hdLp" value="${pr.dataset.price}" step="5000" min="1000"></div>
        <div class="btnrow"><button class="btn btn-gold" id="hdLpSave">Save</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
      const b=$("#hdLpSave");
      if(b) b.addEventListener("click",async ()=>{ b.disabled=true;
        const r=await api("host-action.php",{action:"listing-price",property:id,price:+($("#hdLp").value||0)});
        b.disabled=false; toast(r.message||(r.ok?"Saved":"Could not save"),r.ok?"check":"x");
        if(r.ok){ closeModal(); reload(); } });
      return;
    }

    const rt=e.target.closest("[data-rule-toggle]");
    if(rt){ const r=await api("host-action.php",{action:"rule-toggle",id:+rt.dataset.ruleToggle});
      toast(r.message||(r.ok?"Updated":"Could not update"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const rd=e.target.closest("[data-rule-del]");
    if(rd){ const r=await api("host-action.php",{action:"rule-delete",id:+rd.dataset.ruleDel});
      toast(r.message||(r.ok?"Removed":"Could not remove"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const tv=e.target.closest("[data-team-revoke]");
    if(tv){ const r=await api("host-action.php",{action:"team-revoke",id:+tv.dataset.teamRevoke});
      toast(r.message||(r.ok?"Revoked":"Could not revoke"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const td=e.target.closest("[data-tpl-del]");
    if(td){ const r=await api("host-action.php",{action:"template-delete",id:+td.dataset.tplDel});
      toast(r.message||(r.ok?"Removed":"Could not remove"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const te=e.target.closest("[data-tpl-edit]");
    if(te){ hdTemplateModal(+te.dataset.tplEdit); return; }

    const ch=e.target.closest("[data-channel]");
    if(ch){ const r=await api("host-action.php",{action:"channel-toggle",id:+ch.dataset.channel});
      toast(r.message||(r.ok?"Updated":"Could not update"),r.ok?"check":"x"); if(r.ok) reload(); return; }

    const ai=e.target.closest("[data-insight]");
    if(ai){ toast("Suggestion noted — we'll keep an eye on it","spark"); return; }
  });

  if($("#hdAddRule")) $("#hdAddRule").addEventListener("click",()=>{
    openModal(`<h2 style="margin-bottom:4px">New pricing rule</h2>
      <div class="frm-row"><label>Name</label><input class="inp" id="hdRName" placeholder="Detty December"></div>
      <div class="frm-row"><label>Adjustment (%)</label><input class="inp" type="number" id="hdRPct" value="15" step="1" min="-90" max="300"></div>
      <div class="frm-row"><label>Type</label><select class="sel" id="hdRKind">
        <option value="seasonal">Seasonal</option><option value="weekend">Weekend</option>
        <option value="lastminute">Last minute</option><option value="length">Length of stay</option><option value="custom">Custom</option>
      </select></div>
      <div class="frm-row"><label>Starts <span class="small">(optional)</span></label><input class="inp" type="date" id="hdRFrom"></div>
      <div class="frm-row"><label>Ends <span class="small">(optional)</span></label><input class="inp" type="date" id="hdRTo"></div>
      <div class="btnrow"><button class="btn btn-gold" id="hdRSave">Add rule</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
    const b=$("#hdRSave");
    if(b) b.addEventListener("click",async ()=>{ b.disabled=true;
      const r=await api("host-action.php",{action:"rule-add",name:$("#hdRName").value,
        adjust:+($("#hdRPct").value||0),kind:$("#hdRKind").value,starts:$("#hdRFrom").value,ends:$("#hdRTo").value});
      b.disabled=false; toast(r.message||(r.ok?"Added":"Could not add"),r.ok?"check":"x");
      if(r.ok){ closeModal(); location.reload(); } });
  });

  if($("#hdInvite")) $("#hdInvite").addEventListener("click",()=>{
    openModal(`<h2 style="margin-bottom:4px">Invite a co-host</h2>
      <div class="frm-row"><label>Name</label><input class="inp" id="hdTName" placeholder="Kemi Adeyemi"></div>
      <div class="frm-row"><label>Email</label><input class="inp" type="email" id="hdTEmail" placeholder="kemi@example.com"></div>
      <div class="frm-row"><label>Role</label><select class="sel" id="hdTRole">
        <option value="cohost">Co-host</option><option value="manager">Property manager</option><option value="assistant">Assistant</option>
      </select></div>
      <div class="frm-row"><label>Permissions</label><select class="sel" id="hdTPerm">
        <option value="calendar,messages">Calendar &amp; messages</option>
        <option value="calendar,messages,bookings">Calendar, messages &amp; bookings</option>
        <option value="messages">Messages only</option>
      </select></div>
      <div class="btnrow"><button class="btn btn-gold" id="hdTSave">Send invitation</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
    const b=$("#hdTSave");
    if(b) b.addEventListener("click",async ()=>{ b.disabled=true;
      const r=await api("host-action.php",{action:"team-invite",name:$("#hdTName").value,email:$("#hdTEmail").value,
        role:$("#hdTRole").value,permissions:$("#hdTPerm").value});
      b.disabled=false; toast(r.message||(r.ok?"Invited":"Could not invite"),r.ok?"check":"x");
      if(r.ok){ closeModal(); location.reload(); } });
  });

  if($("#hdNewTpl")) $("#hdNewTpl").addEventListener("click",()=>hdTemplateModal(0));

  if($("#hdPayoutEdit")) $("#hdPayoutEdit").addEventListener("click",()=>{
    const s=HOST.payoutSettings||{};
    openModal(`<h2 style="margin-bottom:4px">Payout settings</h2>
      <div class="frm-row"><label>Schedule</label><select class="sel" id="hdPSch">
        ${["daily","weekly","monthly"].map(x=>`<option value="${x}"${s.schedule===x?" selected":""}>${cap(x)}</option>`).join("")}
      </select></div>
      <div class="frm-row"><label>Bank</label><input class="inp" id="hdPBank" value="${esc(s.bank||"")}" placeholder="Zenith Bank"></div>
      <div class="frm-row"><label>Account name</label><input class="inp" id="hdPName" value="${esc(s.accountName||"")}" placeholder="Adebayo Ogunlesi"></div>
      <div class="frm-row"><label>Account number</label><input class="inp" id="hdPAcct" placeholder="0123456789"></div>
      <div class="btnrow"><button class="btn btn-gold" id="hdPSave">Save</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
    const b=$("#hdPSave");
    if(b) b.addEventListener("click",async ()=>{ b.disabled=true;
      const r=await api("host-action.php",{action:"payout-settings",schedule:$("#hdPSch").value,
        bank:$("#hdPBank").value,account_name:$("#hdPName").value,account:$("#hdPAcct").value});
      b.disabled=false; toast(r.message||(r.ok?"Saved":"Could not save"),r.ok?"check":"x");
      if(r.ok){ closeModal(); location.reload(); } });
  });
}

function hdTemplateModal(id){
  const t=(HOST.templates||[]).find(x=>x.id===id)||{id:0,title:"",body:"",trigger:"manual"};
  openModal(`<h2 style="margin-bottom:4px">${t.id?"Edit template":"New template"}</h2>
    <div class="frm-row"><label>Title</label><input class="inp" id="hdTplTitle" value="${esc(t.title)}" placeholder="Check-in instructions"></div>
    <div class="frm-row"><label>Message</label><textarea class="txa" id="hdTplBody" rows="4" placeholder="Hi {name}, ...">${esc(t.body)}</textarea></div>
    <div class="frm-row"><label>Send automatically</label><select class="sel" id="hdTplTrig">
      ${[["manual","Manually"],["confirmed","When a booking is confirmed"],["checkin","On check-in day"],["checkout","On check-out day"]]
        .map(([v,l])=>`<option value="${v}"${t.trigger===v?" selected":""}>${l}</option>`).join("")}
    </select></div>
    <p class="small" style="margin-bottom:12px">Placeholders: {name}, {property}, {dates}, {code}</p>
    <div class="btnrow"><button class="btn btn-gold" id="hdTplSave">Save</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
  const b=$("#hdTplSave");
  if(b) b.addEventListener("click",async ()=>{ b.disabled=true;
    const r=await api("host-action.php",{action:"template-save",id:t.id,title:$("#hdTplTitle").value,
      body:$("#hdTplBody").value,trigger:$("#hdTplTrig").value});
    b.disabled=false; toast(r.message||(r.ok?"Saved":"Could not save"),r.ok?"check":"x");
    if(r.ok){ closeModal(); location.reload(); } });
}

/* -------------------------------------------------------- AI tools */
function hdAI(){
  const ins=HOST.insights||[];
  const st=hdStat();
  return `
  <div class="panel"><h3 style="font-size:18px">${I.spark} Suggestions for your listings</h3>
    <p class="small" style="margin-bottom:10px">Generated from your own occupancy, pricing and review data.</p>
    ${ins.length?ins.map(i=>`
    <div class="krow"><span class="k"><b>${esc(i.title)}</b><div class="sub">${esc(i.detail)}</div></span>
      <span class="v"><span class="pill-status ${i.level}">${i.level==="ok"?"opportunity":i.level==="warn"?"needs attention":"info"}</span>
      ${i.id?`<div class="btnrow" style="margin-top:6px"><button class="btn btn-ghost btn-sm" data-insight="${i.id}">Note</button></div>`:""}</span></div>`).join("")
    :`<p class="small">No suggestions right now — everything looks healthy.</p>`}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="panel"><h3 style="font-size:18px">Listing health</h3>
      ${HOST.listings.length?HOST.listings.map(l=>{
        const issues=[];
        if(l.status!=="live") issues.push("not live yet");
        if(!l.reviews) issues.push("no reviews");
        if(l.status==="live"&&l.occupancy<40) issues.push("low occupancy");
        return `<div class="krow"><span class="k">${esc(l.name)}</span>
          <span class="v"><span class="pill-status ${issues.length?"warn":"ok"}">${issues.length?issues.join(" · "):"healthy"}</span></span></div>`;
      }).join(""):`<p class="small">Add a listing to see its health here.</p>`}
    </div>
    <div class="panel"><h3 style="font-size:18px">Where you stand</h3>
      <div class="krow"><span class="k">Your average rate</span><span class="v">${st.adr?fmt(st.adr):"—"}</span></div>
      <div class="krow"><span class="k">Your occupancy</span><span class="v">${st.occupancy||0}%</span></div>
      <div class="krow"><span class="k">Your rating</span><span class="v">${st.rating||"—"}</span></div>
      <div class="krow"><span class="k">Nights sold</span><span class="v">${st.nightsSold||0}</span></div>
      <p class="small" style="margin-top:10px">Benchmarks against similar homes appear once we have enough comparable data in your area.</p>
    </div>
  </div>`;
}

/* ------------------------------------------------------------ team */
function hdTeam(){
  const team=HOST.team||[];
  return `<div class="panel"><h3 style="font-size:18px">Co-hosts &amp; team</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl">
      <thead><tr><th>Member</th><th>Role</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <tr><td class="strong">${esc(USER?USER.name:"You")} (owner)</td><td>Owner</td><td class="sub">All access</td><td><span class="pill-status ok">active</span></td><td></td></tr>
        ${team.map(t=>`<tr>
          <td class="strong">${esc(t.name)}<div class="sub">${esc(t.email)}</div></td>
          <td>${esc(cap(t.role))}</td>
          <td class="sub">${esc(t.permissions.split(",").join(", "))}</td>
          <td><span class="pill-status ${t.status==="active"?"ok":"info"}">${esc(t.status)}</span></td>
          <td><div class="btnrow"><button class="btn btn-ghost btn-sm" data-team-revoke="${t.id}">Revoke</button></div></td>
        </tr>`).join("")}
      </tbody></table></div>
    ${team.length?"":`<p class="small" style="margin-top:10px">No co-hosts yet. Invite someone to help manage your calendar and guests.</p>`}
    <div class="btnrow" style="margin-top:14px"><button class="btn btn-gold btn-sm" id="hdInvite">${I.plus} Invite co-host</button></div>
  </div>`;
}

/* ------------------------------------------------------- templates */
function hdTemplates(){
  const tp=HOST.templates||[];
  const trig={manual:"Sent manually",confirmed:"On booking confirmed",checkin:"On check-in day",checkout:"On check-out day"};
  return `<div class="panel"><h3 style="font-size:18px">Message templates</h3>
    <div class="small" style="margin-bottom:12px">Reusable replies for your guests.</div>
    ${tp.length?tp.map(t=>`<div class="panel" style="margin-bottom:10px;background:var(--card-2)">
      <div class="krow" style="border:none;padding:8px 0"><span class="k"><b>${esc(t.title)}</b></span>
        <span class="v">${I[t.icon]||I.send} ${esc(trig[t.trigger]||t.trigger)}</span></div>
      <p class="small">${esc(t.body)}</p>
      <div class="btnrow"><button class="btn btn-ghost btn-sm" data-tpl-edit="${t.id}">${I.edit} Edit</button>
      <button class="btn btn-ghost btn-sm" data-tpl-del="${t.id}">Remove</button></div>
    </div>`).join(""):`<p class="small">No templates yet.</p>`}
    <button class="btn btn-green btn-sm" id="hdNewTpl">${I.plus} New template</button>
  </div>`;
}

/* -------------------------------------------------------- channels */
function hdChannels(){
  const ch=HOST.channels||[];
  return `<div class="grid-2">
    <div class="panel"><h3 style="font-size:18px">Channel manager</h3>
      ${ch.length?ch.map(c=>`
      <div class="krow"><span class="k">${esc(c.channel)}<div class="sub">${c.lastSync?"last sync "+esc(c.lastSync):esc(c.note||"not connected")}</div></span>
        <span class="v"><span class="pill-status ${c.status==="connected"?"ok":"info"}">${esc(c.status)}</span>
        ${c.channel==="Direct"?"":`<div class="btnrow" style="margin-top:6px"><button class="btn btn-ghost btn-sm" data-channel="${c.id}">${c.status==="connected"?"Disconnect":"Connect"}</button></div>`}</span></div>`).join("")
      :`<p class="small">No channels configured.</p>`}
      <div class="small" style="margin-top:8px">Connecting a channel keeps your availability in sync so the same night can never be sold twice.</div>
    </div>
    <div class="panel"><h3 style="font-size:18px">Direct bookings</h3>
      <div class="krow"><span class="k">Your listings on Jollof Living</span><span class="v">${(hdStat().listingsLive)||0} live</span></div>
      <div class="krow"><span class="k">Commission</span><span class="v">${Math.round((hdStat().takeRate||0.12)*100)}%</span></div>
      <div class="krow"><span class="k">Bookings received</span><span class="v">${hdStat().bookings||0}</span></div>
      <p class="small" style="margin-top:10px">Direct bookings through Jollof Living always carry your lowest commission.</p>
    </div>
  </div>`;
}

/* --------------------------------------------------------- payouts */
function hdPayouts(){
  const st=hdStat();
  const ps=HOST.payoutSettings||{};
  const po=HOST.payouts||[];
  return `<div class="grid-3">
    ${[["Available balance",st.available>0?fmt(st.available):fmt(0),"released from escrow"],
       ["Held in escrow",st.escrowHeld?fmt(st.escrowHeld):fmt(0),"releases after check-in"],
       ["Net earned",st.net?fmt(st.net):fmt(0),"after platform fee"]].map(k=>`
    <div class="stat-kpi"><div class="lbl">${k[0]}</div><div class="val" style="font-size:22px">${k[1]}</div><div class="small">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="panel"><h3 style="font-size:18px">Payout settings</h3>
      <div class="krow"><span class="k">Schedule</span><span class="v">${esc(cap(ps.schedule||"weekly"))}</span></div>
      <div class="krow"><span class="k">Bank</span><span class="v">${ps.bank?esc(ps.bank)+(ps.accountLast?" ····"+esc(ps.accountLast):""):"Not set"}</span></div>
      <div class="krow"><span class="k">Account name</span><span class="v">${ps.accountName?esc(ps.accountName):"Not set"}</span></div>
      <div class="krow"><span class="k">Escrow release</span><span class="v">After guest check-in</span></div>
      <button class="btn btn-ghost btn-sm" style="margin-top:10px" id="hdPayoutEdit">${I.edit} Update</button>
      ${ps.bank?"":`<div class="ai-callout" style="margin-top:12px">${I.wallet}<span>Add your bank details so we can pay you.</span></div>`}
    </div>
    <div class="panel"><h3 style="font-size:18px">Recent payouts</h3>
      ${po.length?`<div class="tbl-wrap"><table class="tbl"><tbody>
        ${po.map(p=>`<tr><td class="strong">${esc(p.ref)}</td><td class="sub">${esc(p.date)}${p.bank?" · "+esc(p.bank):""}</td>
        <td>${fmt(p.amount)}</td><td><span class="pill-status ${p.status==="paid"?"ok":"info"}">${esc(p.status)}</span></td></tr>`).join("")}
      </tbody></table></div>`:`<p class="small">No payouts yet. Your first payout is sent once a guest checks in and escrow releases.</p>`}
      <div class="btnrow" style="margin-top:12px"><a class="btn btn-ghost btn-sm" href="${JL.apiBase}host-report.php?r=payouts">${I.download} Statement</a></div>
    </div>
  </div>`;
}

/* ---------------- PAYMENTS GLOBAL PAGE ---------------- */
function pPayments(q){
  const tab=(q&&q.tab)||"invoices";
  return `${pageHead([["Home",URL("/")],["Payments"]],"<em class='serif-i'>Payments</em>","Multi-currency, escrow-protected, and invoiced to the naira — for guests, hosts and finance teams.")}
  <div class="page-body"><div class="wrap">
    <div class="tabs" style="margin-bottom:24px" id="payTabs">
      ${[["invoices","My invoices & receipts"],["earnings","Host earnings & payouts"],["tax","Tax & compliance"],["methods","Payment methods"]].map(([k,l])=>`<button class="tab ${tab===k?"active":""}" data-pt="${k}">${l}</button>`).join("")}
    </div>
    ${[["invoices",()=>payInvoices()],["earnings",()=>payEarnings()],["tax",()=>payTax()],["methods",()=>payMethods()]].find(([k])=>k===tab)[1]()}
  </div></div>`;
}
function payInvoices(){
  return `<div class="panel"><h3 style="font-size:18px">Transactions</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Invoice</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
      ${S.bookings.length?S.bookings.map((b)=>`<tr><td class="strong">${esc(b.ref)}</td><td>${esc(b.name)} · ${b.in} → ${b.out}</td><td>${fmt(b.total)}</td>
      <td><span class="pill-status ${b.status==="confirmed"?"ok":"info"}">${b.status==="confirmed"?"Paid · escrow":esc(b.status)}</span></td>
      <td><button class="btn btn-ghost btn-sm" onclick="openInvoice('${b.ref}')">${I.doc} PDF</button></td></tr>`).join(""):`<tr><td colspan="5" style="text-align:center;padding:18px;color:var(--muted)">No transaction receipts found for your account.</td></tr>`}
    </tbody></table></div>
    <div class="small" style="margin-top:10px">Corporate billing supported — add a PO number to any invoice in your account settings.</div>
  </div>`;
}
function payEarnings(){
  return `<div class="grid-3">
    ${[["Next payout","₦2,574,000","Friday · auto"],["Received YTD","₦38.4m","12% commission applied"],["Escrow now held","₦4,100,000","releases on check-in"]].map(k=>`
    <div class="stat-kpi"><div class="lbl">${k[1]}</div><div class="val" style="font-size:24px">${k[0]}</div><div class="small">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="chart-box"><h4>Host payouts — monthly</h4>${lineChart([1.8,2.2,2.6,2.9,3.3,3.9,4.2,3.9,4.4,4.7,5.1,5.6].map((v,i)=>({v,l:["F","M","A","M","J","J","A","S","O","N","D","J"][i]})),{fmt:v=>"₦"+v+"m"})}</div>
    <div class="panel"><h3 style="font-size:18px">Security deposit handling</h3>
      <div class="krow"><span class="k">Deposit (per stay)</span><span class="v">20% · pre-authorised</span></div>
      <div class="krow"><span class="k">Damage claims (YTD)</span><span class="v">2 · ₦240,000 recovered</span></div>
      <div class="krow"><span class="k">Protection cover</span><span class="v">Up to ₦2m per stay</span></div>
      <div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost btn-sm" onclick="toast('Payout schedule updated — weekly Mondays','wallet')">Edit schedule</button>
      <a class="btn btn-ghost btn-sm" href="${JL.apiBase}host-report.php?r=earnings">${I.download} Export CSV</a></div>
    </div>
  </div>`;
}
function payTax(){
  return `<div class="grid-2">
    <div class="panel"><h3 style="font-size:18px">Tax &amp; compliance (auto)</h3>
      <div class="krow"><span class="k">VAT on guest bookings</span><span class="v">7.5% · remitted monthly</span></div>
      <div class="krow"><span class="k">WHT on host payouts</span><span class="v">5% · e-filed via FIRS</span></div>
      <div class="krow"><span class="k">Jurisdiction logic</span><span class="v">By property location</span></div>
      <div class="krow"><span class="k">NDPR / GDPR</span><span class="v">Compliant · DPO appointed</span></div>
      <div class="krow"><span class="k">PCI DSS</span><span class="v">Compliant · tokenised cards</span></div>
    </div>
    <div class="panel"><h3 style="font-size:18px">Corporate billing</h3>
      <div class="krow"><span class="k">Company invoices</span><span class="v">Monthly consolidated</span></div>
      <div class="krow"><span class="k">PO numbers</span><span class="v">Accepted &amp; displayed</span></div>
      <div class="krow"><span class="k">Travel policy enforcement</span><span class="v">By department · automatic</span></div>
      <button class="btn btn-green btn-sm" style="margin-top:10px" data-goto="/business">Set up business billing</button>
    </div>
  </div>`;
}
function payMethods(){
  return `<div class="grid-2">
    <div class="panel"><h3 style="font-size:18px">Saved payment methods</h3>
      <div class="krow"><span class="k">Visa •••• 4242</span><span class="v"><span class="pill-status ok">default</span></span></div>
      <div class="krow"><span class="k">Zenith bank •••• 0123</span><span class="v">host payouts</span></div>
      <div class="krow"><span class="k">USSD •• 737 code</span><span class="v">linked</span></div>
      <div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost btn-sm" onclick="toast('Card add flow opened (test: 4242 4242 4242 4242)','wallet')">${I.plus} Add card</button></div>
    </div>
    <div class="stack">
      <div class="panel"><h3 style="font-size:18px">Accepted at checkout</h3>
        <div class="tbl-wrap"><table class="tbl"><tbody>
          ${PAY_METHODS.map(p=>`<tr><td>${I[p.ico]} ${p.name}</td><td class="sub">${p.note}</td><td><span class="pill-status ok">live</span></td></tr>`).join("")}
          <tr><td>${I.bolt} Cryptocurrency (BTC / USDT)</td><td class="sub">Optional for international guests</td><td><span class="pill-status warn">beta soon</span></td></tr>
        </tbody></table></div>
      </div>
      <div class="panel"><h3 style="font-size:18px">Promo codes &amp; gift cards</h3>
        <div class="krow"><span class="k">Gift card balance</span><span class="v">₦40,000</span></div>
        <div class="krow"><span class="k">Referral credit</span><span class="v">₦10,000</span></div>
        <div class="btnrow" style="margin-top:8px"><button class="btn btn-ghost btn-sm" data-goto="/giftcards">Buy gift card</button><button class="btn btn-ghost btn-sm" data-goto="/referral">Refer &amp; earn</button></div>
      </div>
    </div>
  </div>`;
}
function bindPayments(){
  $$("#payTabs .tab").forEach(t=>t.addEventListener("click",()=>nav("/payments?tab="+t.dataset.pt)));
}


/* ===== pages-misc.js ===== */
/* ============================================================
   JOLLOF LIVING — pages-misc.js
   membership · blog · help · business · admin · about · app · future · 404
   ============================================================ */

/* ---------------- MEMBERSHIP ---------------- */
function pMembership(){
  const tier=TIERS.find(t=>t.key===S.tier)||TIERS[2];
  const next=TIERS[Math.min(TIERS.indexOf(tier)+1,TIERS.length-1)];
  return `${pageHead([["Home",URL("/")],["Jollof Club"]],"<em class='serif-i'>Jollof Club</em>","Earn Jollof Points on every stay and climb from Bronze to Platinum — with perks that travel as well as you do.",
    `<span class="badge">${tier.letter} ${tier.name} · ${S.points.toLocaleString()} pts</span>`)}
  <div class="page-body"><div class="wrap">
    <div class="tiers stagger" style="margin-bottom:26px">${tierCards().join("")}</div>

    <div class="panel" style="margin-bottom:22px">
      <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px">
        <h3 style="font-size:20px">Your progress to ${next.name}</h3><div class="small">${S.points.toLocaleString()} / ${next.req} points</div></div>
      <div style="height:8px;border-radius:8px;background:var(--line);margin:12px 0 6px;overflow:hidden">
        <div style="width:${Math.min(100,Math.round(S.points/parseInt(next.req)*100))}%;height:100%;background:var(--gold-grad);border-radius:8px;transition:width 1s var(--ease)"></div></div>
      <div class="small">${parseInt(next.req)-S.points} points to go — a 3-night stay at your current multiplier earns ~${Math.min(parseInt(next.req)-S.points, Math.round(3*145000/1000*12))}. </div>
    </div>

    <div class="grid-2">
      <div class="panel"><h3 style="font-size:20px">Points ledger</h3>
        <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><tbody>
          ${POINTS_LEDGER.map(r=>`<tr><td class="sub">${r[0]}</td><td>${r[1]}</td><td class="strong" style="color:${r[2].startsWith("+")?"var(--ok)":"var(--bad)"}">${r[2]}</td></tr>`).join("")}
        </tbody></table></div>
      </div>
      <div class="stack">
        <div class="panel" style="background:linear-gradient(150deg,var(--card),var(--gold-soft))">
          <h3 style="font-size:20px">Refer &amp; earn — give ₦10,000, get ₦10,000</h3>
          <p class="muted" style="font-size:14px">Share your code. When a friend completes their first stay, you each earn ₦10,000 in credits — plus points.</p>
          <div style="display:flex;gap:8px;margin:14px 0">
            <div class="inp" style="display:flex;align-items:center;justify-content:center;letter-spacing:.24em;font-weight:500;color:var(--accent)">${esc(REFERRAL_CODE)}</div>
            <button class="btn btn-gold" onclick="copyText(REFERRAL_CODE,'Referral code copied — share it anywhere')">${I.share} Copy</button>
          </div>
          <div class="btnrow"><a class="btn btn-green btn-sm" target="_blank" rel="noopener"
   href="https://wa.me/?text=${encodeURIComponent("Stay somewhere beautiful in Lagos — use my Jollof Living code "+REFERRAL_CODE+" and we both get ₦10,000. "+location.origin+URL("/"))}">${I.send} WhatsApp</a>
          <button class="btn btn-ghost btn-sm" data-goto="/referral">Referral centre</button></div>
        </div>
        <div class="panel"><h3 style="font-size:20px">Rewards — redeemed with our concierge</h3>
          ${[["Free airport transfer","6,000 pts","redeem"],["Late check-out (2pm)","4,000 pts","redeem"],["Complimentary room upgrade","20,000 pts","redeem"],["Private chef evening","30,000 pts","redeem"],["₦50,000 travel credit","45,000 pts","redeem"]].map(r=>`
          <div class="krow"><span class="k">${r[0]}</span><span class="v"><span class="small" style="color:var(--accent)">${r[1]}</span>
          ${jlFlag("loyalty.redeem")?`<button class="btn btn-gold btn-sm" onclick="toast('Redemption request noted — our concierge confirms before your stay','gift')">Redeem</button>`:`<button class="btn btn-ghost btn-sm" onclick="copyText('Reward request: ${r[0].replace(/'/g,"")} (${r[1]}) — ${S.points} points on file','Reward note copied — paste it into a booking note or send it to support')">How to redeem</button>`}</span></div>`).join("")}
        </div>
        <div class="panel"><h3 style="font-size:20px">Gift cards</h3>
          <p class="muted" style="font-size:14px">Send the gift of a beautiful stay: edible, elegant, never expires.</p>
          <div class="btnrow" style="margin-top:8px"><button class="btn btn-ghost btn-sm" data-goto="/giftcards">Buy a gift card</button></div>
        </div>
      </div>
    </div>
  </div></div>`;
}

/* ---------------- GIFT CARDS ---------------- */
function pGiftCards(){
  return `${pageHead([["Home",URL("/")],["Gift cards"]],"Jollof Living <em class='serif-i'>gift cards</em>","Digital, delivered by email or WhatsApp, redeemable against any stay — and they never expire.")}
  <div class="page-body"><div class="wrap" style="max-width:860px">
    <div class="grid-3">
      ${[[100000,"Weekend escape"],[250000,"The full experience"],[500000,"Detty December"]].map(g=>`
      <div class="panel" style="text-align:center;background:linear-gradient(160deg,var(--card),var(--gold-soft));border-color:var(--line)">
        <div class="medal" style="background:var(--gold-grad);color:#231a05;border-radius:50%;width:52px;height:52px;display:grid;place-items:center;margin:0 auto 14px;font-family:var(--fs-serif);font-size:20px;font-weight:600">${Math.round(g[0]/1000)}</div>
        <h3 style="font-size:26px">${fmt(g[0])}</h3><p class="small" style="margin:4px 0 16px">${esc(g[1])}</p>
        <button class="btn btn-gold btn-sm" onclick="buyGiftCard(${g[0]})">Buy this card</button>
      </div>`).join("")}
    </div>
    <div class="panel" style="margin-top:20px"><h3 style="font-size:18px">How it works</h3>
      <div class="grid-3" style="margin-top:10px">
        ${[["1","Pick an amount","Any amount, any duration."],["2","Send instantly","Email or WhatsApp, beautifully wrapped."],["3","They book","Redeemed at checkout — balance never expires."]].map(s=>`<div><div class="eyebrow">${s[0]}</div><b style="font-family:var(--fs-serif);font-size:18px">${s[1]}</b><p class="small">${s[2]}</p></div>`).join("")}
      </div>
    </div>
  </div></div>`;
}

/* ---------------- REFERRAL ---------------- */
function pReferral(){
  return `${pageHead([["Home",URL("/")],["Referral"]],"Give ₦10,000, <em class='serif-i'>get ₦10,000</em>","The most generous referral in Nigerian travel — share your code, and we credit both of you.")}
  <div class="page-body"><div class="wrap" style="max-width:860px">
    <div class="grid-2">
      <div class="panel" style="background:linear-gradient(150deg,var(--card),var(--gold-soft))">
        <h3 style="font-size:22px">Your code</h3>
        <div style="font-family:var(--fs-serif);font-size:clamp(2rem,6vw,3.4rem);font-weight:600;letter-spacing:.18em;color:var(--accent);margin:12px 0">${esc((USER&&USER.referral)||'JOLLOF10')}</div>
        <div class="btnrow"><button class="btn btn-gold" onclick="copyText(location.origin+URL('/')+'?ref='+encodeURIComponent((USER&&USER.referral)||'JOLLOF10'),'Referral link copied — ready to share')">${I.share} Copy link</button>
        <button class="btn btn-green" onclick="toast('Shared to WhatsApp ✨','send')">${I.send} Share on WhatsApp</button>
        <button class="btn btn-ghost" onclick="toast('Shared to Instagram stories','camera')">${I.camera} Instagram</button></div>
        <div class="krow" style="margin-top:14px"><span class="k">Friends joined</span><span class="v">${(USER&&USER.referralCount)||0}</span></div>
        <div class="krow"><span class="k">Credits earned</span><span class="v" style="color:var(--ok)">${fmt((USER&&USER.referralCredits)||0)}</span></div>
      </div>
      <div class="panel"><h3 style="font-size:22px">Affiliate programme</h3>
        <p class="muted" style="font-size:14px">Travel bloggers, creators and influencers earn <b>8% commission</b> on every booking they refer — with a dashboard, real-time tracking and monthly payouts.</p>
        <a class="btn btn-ghost btn-sm" style="margin-top:12px" href="${URL('/help')}">How the affiliate programme works</a>
      </div>
    </div>
  </div></div>`;
}

/* ---------------- BLOG ---------------- */
function pBlog(){
  return `${pageHead([["Home",URL("/")],["Journal"]],"The <em class='serif-i'>Journal</em>","Travel guides, culture pieces and the stories behind our residences — fresh from the Jollof Living editorial desk.","<button class='btn btn-ghost btn-sm' onclick='toast(\"Subscribed to The Journal ✨\",\"check\")'>Subscribe</button>")}
  <div class="page-body"><div class="wrap">
    <div class="grid-3 stagger">${BLOG.map(postCard).join("")}</div>
  </div></div>`;
}
function pBlogPost(slug){
  const b=BLOG.find(x=>x.slug===slug)||BLOG[0];
  return `<div class="page-head"><div class="wrap">
    <div class="crumbs"><a href="${URL('/')}">Home</a> / <a href="${URL('/blog')}">Journal</a> / ${b.cat}</div>
    <h1>${esc(b.title)}</h1>
    <p>${b.date} · ${b.read} read · by the Jollof Living editorial team</p>
  </div></div>
  <div class="page-body"><div class="wrap" style="max-width:780px">
    <div class="article">
      <div class="cover" style="background-image:url('${img(b.img)}')"></div>
      ${b.body.map((p,i)=>i===0?`<p style="font-size:19px;color:var(--ink);font-family:var(--fs-serif);font-size:22px;font-style:italic">${esc(p)}</p>`:`<p>${esc(p)}</p>`).join("")}
      <div class="panel" style="margin-top:30px;background:linear-gradient(150deg,var(--card),var(--gold-soft))">
        <div class="grid-2" style="align-items:center;gap:20px">
          <div><b style="font-family:var(--fs-serif);font-size:21px">Enjoyed this?</b><p class="small">Get the Journal in your inbox — guides, culture and early access.</p></div>
          <div class="btnrow"><button class="btn btn-gold btn-sm" onclick="subscribeHelp()">Subscribe</button><a class="btn btn-ghost btn-sm" href="${URL('/stays')}">Browse stays</a></div>
        </div>
      </div>
    </div>
  </div></div>`;
}

/* ---------------- HELP ---------------- */
function pHelp(q){
  const search=(q&&q.q)?decodeURIComponent(q.q).toLowerCase():"";
  const filter=(q&&q.cat)||"all";
  const shown=FAQS.filter(f=>{ const match=!search||(f[0]+f[1]).toLowerCase().includes(search); const catMatch=filter==="all"||f[0].toLowerCase().includes({booking:"booking",payments:"payment",hosting:"host",trust:"escrow".slice(0,0)||"trust",account:"account",app:"app"}[filter]||""); return match&&catMatch; });
  return `${pageHead([["Home",URL("/")],["Help centre"]],"How can we <em class='serif-i'>help</em>?","Search answers, get dispute support, or reach a human — 24/7. Median first response: under 3 minutes.")}
  <div class="page-body"><div class="wrap" style="max-width:900px">
    <div style="display:flex;gap:10px;max-width:560px;margin:0 auto 26px">
      <div style="position:relative;flex:1">
        <input class="inp" id="helpSearch" placeholder="Search help articles…" style="padding:14px 18px 14px 44px" value="${search?esc(search):""}">
        <span style="position:absolute;left:16px;top:14px;color:var(--ink-faint)">${I.search}</span>
      </div>
      <button class="btn btn-gold" id="helpGo">Search</button>
    </div>
    <div class="tabs" style="justify-content:center;margin-bottom:24px" id="helpCats">
      <button class="tab ${filter==="all"?"active":""}" data-hc="all">All</button>
      ${HELP_CATEGORIES.map(c=>`<button class="tab ${filter===c.id?"active":""}" data-hc="${c.id}">${c.name}</button>`).join("")}
    </div>
    <div class="faq-list reveal" id="faqList">${shown.map(f=>faqItem(f[0],f[1])).join("")||`<div class="empty-state">${I.search}<b>No results</b>Try “escrow”, “cancel” or “referral”.</div>`}</div>
    <div class="grid-3" style="margin-top:30px">
      ${[["chat","24/7 live chat","Chat with the team or Jollof AI",URL("/concierge")],["phone","Call us","+234 700 JOLLOF (24/7)",URL("/concierge")],["send","Email support","care@jollofliving.com",URL("/concierge")]].map(c=>`
      <div class="panel" style="text-align:center"><div class="why-ico" style="margin:0 auto 12px">${I[c[0]]}</div>
      <b style="font-family:var(--fs-serif);font-size:19px">${c[1]}</b><p class="small">${c[2]}</p>
      ${c[1].indexOf("live chat")>-1?`<button class="link-arrow" style="font-size:11.5px" onclick="liveChatOpen()">Start chatting ${I.arrow}</button>`:`<a class="link-arrow" style="font-size:11.5px" href="${c[3]}">Open ${I.arrow}</a>`}</div>`).join("")}
    </div>
    <div class="grid-2" style="margin-top:24px">
      <div id="disputeCentre">${typeof dcPanel==="function"?dcPanel():""}</div>
      <div class="panel"><h3 style="font-size:20px">${I.chatBell} Emergency assistance</h3>
        <p class="muted" style="font-size:14px;margin-bottom:10px">One-tap access, wherever you are in Nigeria.</p>
        <div class="grid-2">${[["Police","112"],["Fire","112"],["Ambulance","112"]].map(e=>`<button class="btn btn-ghost btn-sm" onclick="toast('Dialling ${e[1]}…','phone')">${e[0]} · ${e[1]}</button>`).join("")}
        <button class="btn btn-gold btn-sm" onclick="toast('Jollof Living emergency line connecting…','chatBell')">Jollof emergency line</button></div>
        <div class="small" style="margin-top:10px">Every residence includes a safety card: detectors, extinguishers, first-aid kit and local emergency contacts.</div>
      </div>
    </div>
  </div></div>`;
}
function faqItem(q,a){
  return `<div class="faq-item"><button class="faq-q" onclick="faqToggle(this)">${esc(q)}<span class="pm">${I.plus}</span></button><div class="faq-a"><div>${esc(a)}</div></div></div>`;
}
function faqToggle(btn){ const item=btn.parentElement, a=$(".faq-a",item), open=item.classList.toggle("open"); a.style.maxHeight=open?a.scrollHeight+"px":0; }
function bindHelp(){
  $("#helpGo").addEventListener("click",()=>{ const v=$("#helpSearch").value.trim();
    nav("/help?q="+encodeURIComponent(v)); });
  $("#helpSearch").addEventListener("keydown",e=>{ if(e.key==="Enter") $("#helpGo").click(); });
  $$("#helpCats .tab").forEach(t=>t.addEventListener("click",()=>nav("/help?cat="+t.dataset.hc)));
  /* the resolution centre is database-backed; wire it once the panel is in the DOM */
  if (typeof bindDisputes === "function") bindDisputes();
}

/* ---------------- BUSINESS ---------------- */
function pBusiness(){
  return `${pageHead([["Home",URL("/")],["Business"]],"<em class='serif-i'>Jollof Living</em> for Business","Corporate stays with centralized billing, travel policy enforcement, and a dedicated account manager.")}
  <div class="page-body"><div class="wrap">
    <div class="grid-4">
      ${[["38","Companies onboarded"],["1,900+","Executive nights booked"],["12 min","Avg. support response"],["100%","Policy compliance"]].map(s=>`
      <div class="stat-kpi"><div class="lbl">${s[0]}</div><div class="val gold-text">${s[1]}</div></div>`).join("")}
    </div>
    <div class="grid-2" style="margin-top:20px">
      <div class="stack">
        ${[["Centralized billing","One monthly invoice for every stay — credit terms available."],["PO & cost-centre support","Attach purchase orders, departments and projects to each booking."],["Travel policy enforcement","Per-tier nightly caps, approved properties and automatic approvals."],["Team travel dashboard","Live spend, upcoming trips and traveller check-ins for admins."]].map(f=>`
        <div class="panel" style="display:flex;gap:14px"><div class="why-ico" style="margin:0;width:44px;height:44px;border-radius:12px">${I.checkCircle}</div>
        <div><b style="font-family:var(--fs-serif);font-size:19px">${f[0]}</b><p class="muted" style="font-size:14px">${f[1]}</p></div></div>`).join("")}
      </div>
      <div class="panel" style="height:fit-content">
        <h3 style="font-size:22px">Request a demo</h3>
        <p class="muted" style="font-size:14px;margin-bottom:14px">Our corporate team will tailor a programme to your travel calendar.</p>
        <div class="frm-row"><label>Work email</label><input class="inp" placeholder="you@company.com"></div>
        <div class="frm-row"><label>Company</label><input class="inp" placeholder="Company Ltd"></div>
        <div class="frm-row"><label>Est. bookings / month</label><select class="sel"><option>1–5</option><option>6–20</option><option>21–100</option><option>100+</option></select></div>
        <button class="btn btn-gold btn-block" onclick="toast('Request received — our team will reach out within one business day ✨','check')">Request demo</button>
        <div class="small" style="text-align:center;margin-top:10px">${I.shield} NDPR-compliant · invoices in NGN, USD, GBP, EUR</div>
      </div>
    </div>
  </div></div>`;
}

/* ---------------- BACK OFFICE SIGN IN ---------------- */
function admIn(){ return IS_ADMIN; }
async function admSignOut(){
  await api("admin-auth.php",{action:"logout"});
  toast("Signed out — recorded in the audit log","lock");
  setTimeout(()=>nav("/admin-login"),500);
}
function admSSO(){ toast("Single sign-on is not configured for this site yet.","shield"); }
function pAdminLogin(){
  return `<div style="padding:calc(var(--header-h) + 40px) 20px 80px">
    <div class="auth-shell">
      <div class="auth-card">
        <div class="adl-badge">${I.lock}</div>
        <span class="eyebrow">Restricted area</span>
        <h1 style="margin-top:10px">Back office <em class="serif-i">sign in</em></h1>
        <p class="small">Operations console for the Jollof Living platform team. Access is role-based and every sign-in is written to the audit log.</p>
        <form id="adForm" novalidate>
          <div class="frm-row"><label>Work email</label><input class="inp" id="adEmail" type="email" placeholder="ops@jollofliving.com" autocomplete="username"></div>
          <div class="frm-row"><label>Password</label>
            <div class="pw-wrap"><input class="inp" id="adPass" type="password" placeholder="••••••••" autocomplete="current-password">
            <button type="button" id="adEye" aria-label="Show or hide password">${I.eye}</button></div>
          </div>
          ${JL.admin2fa?`<div class="frm-row"><label>6-digit authenticator code</label><input class="inp" id="adOtp" inputmode="numeric" placeholder="••• •••"></div>`:""}
          <button class="btn btn-gold btn-block" id="adSubmit" type="submit" style="margin-top:6px">${I.lock} Sign in to back office</button>
          <div class="btnrow" style="gap:10px;margin-top:10px">
            ${jlFlag("admin.sso")?`<button class="btn btn-ghost" type="button" onclick="admSSO()">Okta SSO</button>`:""}
            <a class="btn btn-ghost" href="${URL('/help')}?q=password">Forgot password</a>
          </div>
        </form>
        ${admIn()
          ? `<div class="ai-callout" style="margin-top:16px">${I.check}<span><b>Already signed in.</b> <a href="${URL('/admin')}" style="color:var(--accent)">Continue to the console →</a></span></div>`
          : `<div class="ai-callout" style="margin-top:16px">${I.lock}<span>Use the administrator account created during installation.</span></div>`}
        <p class="small" style="text-align:center;margin-top:12px">${I.shield} NDPR/GDPR · ${JL.admin2fa?"2FA required for staff":"2FA can be switched on for staff"} · sessions end the moment access is revoked</p>
        <div style="text-align:center;margin-top:10px"><a class="link-arrow" href="${URL('/')}">← Back to jollofliving.com</a></div>
      </div>
    </div>
  </div>`;
}
function bindAdminLogin(){
  const f=$("#adForm"); if(!f) return;
  const eye=$("#adEye");
  if(eye) eye.addEventListener("click",()=>{ const p=$("#adPass"); if(p) p.type=(p.type==="password"?"text":"password"); });
  f.addEventListener("submit",async (e)=>{
    e.preventDefault();
    const em=$("#adEmail").value.trim(), pw=$("#adPass").value, otp=$("#adOtp")?$("#adOtp").value.trim():"";
    if(!em){ toast("Enter your work email","x"); return; }
    if(!pw){ toast("Enter your password","x"); return; }
    const btn=$("#adSubmit"); btn.disabled=true; const label=btn.innerHTML; btn.textContent="Verifying…";
    const r=await api("admin-auth.php",{action:"login",email:em,password:pw,otp});
    if(!r.ok){ btn.disabled=false; btn.innerHTML=label; toast(r.message||"Those details do not match our records","x"); return; }
    toast("Welcome back — console unlocked ✨","check");
    setTimeout(()=>{ location.href = qp("next") || URL("/admin"); },500);
  });
}

/* ---------------- ADMIN ---------------- */
function pAdmin(q){
  if(!admIn()) return pAdminLogin();   // gate: back office requires sign-in
  const tab=(q&&q.tab)||"dashboard";
  const nav=[["dashboard","Dashboard","grid"],["moderation","Listings moderation","eye"],["users","User management","users"],["promotions","Promotions & campaigns","gift"],["fraud","Fraud detection","shield"],["chat","Live chat desk","chat"],["disputes","Dispute resolution","scale"],["cms","Content (CMS)","doc"],["reports","Reports & analytics","scale"],["roles","Roles & permissions","lock"],["audit","Audit log","book"]];
  // The header badges report real figures: GMV for the last 30 days and the
  // take rate actually configured in settings, not fixed sample numbers.
  const KH=ADMIN_STATS||{};
  const gmv30=KH.gmv30||0;
  const gmvTxt="₦"+(gmv30>=1000000?(gmv30/1000000).toFixed(1)+"m":gmv30>=1000?Math.round(gmv30/1000)+"k":String(gmv30));
  const takePct=Math.round((KH.takeRate!=null?KH.takeRate:0.12)*100);
  const badges=`<span class='badge'>GMV (30 days) <b>${gmvTxt}</b></span>`
    +`<span class='badge ok'>Take rate ${takePct}%</span>`
    +(KH.moderation?`<span class='badge warn'>${KH.moderation} awaiting moderation</span>`:"")
    +`<button class='btn btn-ghost btn-sm' onclick='admSignOut()'>Sign out</button>`;
  return `${pageHead([["Home",URL("/")],["Platform admin"]],"<em class='serif-i'>Back office</em>","Operate the platform — bookings, revenue, users, trust and growth in one place.",badges)}
  <div class="page-body"><div class="wrap">
    <div class="admin-shell">
      <nav class="admin-nav"><div class="sec">Admin</div>
        ${nav.map(([k,l,i])=>`<a href="${URL("/admin")}?tab=${k}" class="${tab===k?"active":""}">${I[i]} ${l}</a>`).join("")}
      </nav>
      <div>${(()=>{ const m=[["dashboard",admDashboard],["moderation",admModeration],["users",admUsers],["promotions",admPromotions],["fraud",admFraud],["chat",()=>`<div id="admChatHost">${admChatHostHTML()}</div>`],["disputes",()=>`<div id="admDisputeHost">${admDisputesHostHTML()}</div>`],["cms",adCMS],["reports",admReports],["roles",admRoles],["audit",admAudit]].find(([k])=>k===tab)||["dashboard",admDashboard]; return m[1](); })()}</div>
    </div>
  </div></div>`;
}
function admDashboard(){
  const K=ADMIN_STATS||{};
  const m=(n)=>"₦"+(n>=1000000?(n/1000000).toFixed(1)+"m":n>=1000?Math.round(n/1000)+"k":String(n||0));
  const kpis=[
    ["GMV (30 days)",m(K.gmv30||0),(K.bookings30||0)+" bookings","up"],
    ["Lifetime GMV",m(K.gmvAll||0),(K.bookingsAll||0)+" bookings total","up"],
    ["Live listings",String(K.listings||0),(K.listingsPend||0)+" awaiting review","up"],
    ["Registered users",String(K.users||0),(K.hosts||0)+" hosts","up"],
    ["Escrow held",m(K.escrowHeld||0),"released on check-in","up"],
    ["Average rating",String(K.avgRating||0),(K.reviews||0)+" published reviews","up"],
  ];
  const series=(JL.charts&&JL.charts.revenue)||[];
  const byCity=(JL.charts&&JL.charts.byCity)||[];
  return `<div class="grid-4">
    ${kpis.map(k=>`<div class="stat-kpi"><div class="lbl">${k[0]}</div><div class="val" style="font-size:23px">${k[1]}</div><div class="delta ${k[3]}">${k[2]}</div></div>`).join("")}
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="chart-box"><h4>Bookings revenue — last 12 months</h4>${series.length?lineChart(series,{fmt:v=>"₦"+v+"m"}):`<p class="small">No bookings recorded yet — the chart fills in as reservations arrive.</p>`}</div>
    <div class="chart-box"><h4>Revenue by city</h4>
      ${byCity.length?barChart(byCity.map((b,i)=>({l:b.l,v:b.v,c:i===0?"var(--accent)":i===1?"var(--green)":undefined}))):`<p class="small">No revenue recorded yet.</p>`}
    </div>
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="panel"><h3 style="font-size:18px">Live operations</h3>
      <div class="krow"><span class="k">Reservations awaiting host approval</span><span class="v">${K.pending||0}</span></div>
      <div class="krow"><span class="k">Listings awaiting moderation</span><span class="v">${K.moderation||0}</span></div>
      <div class="krow"><span class="k">Open fraud flags</span><span class="v">${K.fraud||0}</span></div>
      <div class="krow"><span class="k">Reviews pending approval</span><span class="v">${K.reviewsPend||0}</span></div>
      <div class="krow"><span class="k">New enquiries</span><span class="v">${K.enquiries||0}</span></div>
      <div class="krow"><span class="k">Escrow held</span><span class="v">${m(K.escrowHeld||0)}</span></div>
    </div>
    <div class="panel"><h3 style="font-size:18px">Audience</h3>
      <div class="krow"><span class="k">Newsletter subscribers</span><span class="v">${K.subscribers||0}</span></div>
      <div class="krow"><span class="k">Hosts on the platform</span><span class="v">${K.hosts||0}</span></div>
      <div class="krow"><span class="k">Published reviews</span><span class="v">${K.reviews||0}</span></div>
      <div class="btnrow" style="margin-top:12px">
        <a class="btn btn-ghost btn-sm" href="${URL("/admin?tab=users")}">Manage users</a>
        <a class="btn btn-ghost btn-sm" href="${URL("/admin?tab=cms")}">Edit content</a>
      </div>
    </div>
  </div>`;
}
function admModeration(){
  const q=ADMIN_STATE.moderation||[];
  // Each row is [item, slug, type, note, level, id, kind, extra]. The kind
  // decides which entity the approve/reject is routed to.
  const entityFor=(kind)=> kind==="listing" ? "moderation" : (kind==="review" ? "review" : "flag");
  const label={listing:"Listing",review:"Review",flag:"Flagged"};
  return `<div class="panel"><h3 style="font-size:18px">Moderation queue ${q.length?`<span class="badge">${q.length} waiting</span>`:""}</h3>
    ${q.length?`<div class="tbl-wrap" style="margin-top:10px"><table class="tbl">
      <thead><tr><th>Item</th><th>Type</th><th>Detail</th><th></th></tr></thead><tbody>
    ${q.map(m=>{
      const kind=m[6]||"flag", ent=entityFor(kind);
      return `<tr>
      <td><b class="strong">${esc(m[0])}</b>${m[1]&&m[1]!=="—"?`<div class="sub">${esc(m[1])}</div>`:""}</td>
      <td><span class="pill-status ${m[4]||"info"}">${esc(label[kind]||"Item")}</span><div class="sub">${esc(m[2]||"")}</div></td>
      <td class="sub">${esc(m[3]||"")}${m[7]?`<div>${esc(m[7])}</div>`:""}</td>
      <td><div class="btnrow">
        ${kind==="listing"?`<button class="btn btn-ghost btn-sm" onclick="admPreview('${esc(m[1])}')">Preview</button>`:""}
        <button class="btn btn-gold btn-sm" onclick="admAction('${ent}','approve',${m[5]})">Approve</button>
        <button class="btn btn-ghost btn-sm" onclick="admAction('${ent}','reject',${m[5]})">Reject</button>
      </div></td></tr>`;}).join("")}
    </tbody></table></div>`
    :`<p class="small" style="margin-top:10px">Nothing waiting — new listings and flagged reviews land here automatically.</p>`}
    <div class="small" style="margin-top:10px">Approving a listing publishes it immediately and notifies the owner. Rejecting sends it back with reviewer notes.</div>
  </div>`;
}
function admPreview(slug){
  if(!slug||slug==="—"){ toast("This item has no public page yet","info"); return; }
  window.open(URL("/stay/"+slug),"_blank");
}
/* User search is applied in the browser over the rows the server sent. */
let ADM_USER_Q = "";
function admUserRows(){
  const q=ADM_USER_Q.trim().toLowerCase();
  const all=ADMIN_STATE.users||[];
  if(!q) return all;
  return all.filter(u=>[u[0],u[1],u[2],u[5]].some(f=>String(f||"").toLowerCase().includes(q)));
}
function admUserBody(){
  const rows=admUserRows();
  if(!rows.length) return `<tr><td colspan="6" class="sub" style="text-align:center;padding:22px">${ADM_USER_Q?`No user matches “${esc(ADM_USER_Q)}”.`:"No users yet."}</td></tr>`;
  return rows.map(u=>`<tr><td class="strong">${esc(u[0])}</td><td class="sub">${esc(u[1])}</td><td>${esc(u[2])}</td><td>${esc(u[3])}</td>
      <td><span class="pill-status ${u[6]==="ok"?"ok":u[6]==="bad"?"warn":"info"}">${esc(u[5])}</span></td>
      <td><div class="btnrow">
      ${u[6]!=="ok"?`<button class="btn btn-ghost btn-sm" onclick="admAction('user','verify',${u[7]})">Verify</button>`:""}
      ${u[6]!=="bad"?`<button class="btn btn-ghost btn-sm" style="border-color:var(--bad);color:var(--bad)" onclick="admAction('user','suspend',${u[7]})">Suspend</button>`
                    :`<button class="btn btn-ghost btn-sm" onclick="admAction('user','restore',${u[7]})">Restore</button>`}
      </div></td></tr>`).join("");
}
function admUserSearch(v){
  ADM_USER_Q=v||"";
  const tb=$("#admUserBody"); if(tb) tb.innerHTML=admUserBody();
  const c=$("#admUserCount"); if(c) c.textContent=`${admUserRows().length} of ${(ADMIN_STATE.users||[]).length}`;
}
function admUsers(){
  const total=(ADMIN_STATE.users||[]).length;
  return `<div class="panel"><h3 style="font-size:18px">User management <span class="badge" id="admUserCount">${total} of ${total}</span></h3>
    <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap">
      <input class="inp" id="admUserSearch" placeholder="Search name, email, role or status…" style="max-width:320px"
             value="${esc(ADM_USER_Q)}" oninput="admUserSearch(this.value)">
      <button class="btn btn-ghost btn-sm" onclick="admUserSearch('')">Clear</button>
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>User</th><th>Role</th><th>Tier</th><th>Joined</th><th>Status</th><th></th></tr></thead>
    <tbody id="admUserBody">${admUserBody()}</tbody></table></div>
  </div>`;
}
function admPromotions(){
  return `<div class="panel"><h3 style="font-size:18px">Promo &amp; campaign manager</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Campaign</th><th>Window</th><th>Status</th><th>Revenue</th><th></th></tr></thead><tbody>
    ${(ADMIN_STATE.campaigns||[]).length?(ADMIN_STATE.campaigns).map(c=>`<tr><td class="strong">${esc(c[1])}<div class="sub">${esc(c[0])}</div></td><td class="sub">${esc(c[2]||"—")}</td>
      <td><span class="pill-status ${c[5]==="ok"?"ok":c[5]==="info"?"info":"warn"}">${esc(c[3])}</span></td><td>${esc(c[4]||"—")}</td>
      <td><button class="btn btn-ghost btn-sm" onclick="campaignEdit(${c[6]})">${I.edit} Edit</button></td></tr>`).join("")
      :`<tr><td colspan="5" class="sub" style="text-align:center;padding:22px">No campaigns yet — create the first one below.</td></tr>`}
    </tbody></table></div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px">
      <button class="btn btn-gold btn-sm" onclick="campaignEdit(0)">${I.plus} New campaign</button>
    </div>
  </div>`;
}
function admFraud(){
  return `<div class="panel"><h3 style="font-size:18px">AI fraud detection — live flags</h3>
    <div style="display:flex;gap:12px;margin:12px 0;flex-wrap:wrap">
      <span class="badge warn">${ADMIN_STATE.fraud.filter(f=>+f[3]>=80).length} high risk</span><span class="badge">${ADMIN_STATE.fraud.filter(f=>+f[3]<80).length} medium</span><span class="badge ok">${ADMIN_STATE.fraud.length?"under review":"system healthy"}</span>
      <span class="small">Models: payment · listing · messaging · payout</span></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Case</th><th>Subject</th><th>Signal</th><th>Risk</th><th></th></tr></thead><tbody>
    ${(ADMIN_STATE.fraud||[]).length?(ADMIN_STATE.fraud).map(f=>`<tr><td class="strong">${esc(f[0])}</td><td class="sub">${esc(f[1])}</td><td>${esc(f[2])}</td>
      <td><span class="pill-status ${+f[3]>=80?"bad":+f[3]>=60?"warn":"info"}">${f[3]}%</span></td>
      <td><div class="btnrow"><button class="btn btn-ghost btn-sm" onclick="admAction('fraud','resolve',${f[5]})">Resolve</button>
      <button class="btn btn-ghost btn-sm" onclick="admAction('fraud','escalate',${f[5]})">Escalate</button></div></td></tr>`).join("")
      :`<tr><td colspan="5" class="sub" style="text-align:center;padding:22px">No open fraud flags — nothing needs attention.</td></tr>`}
    </tbody></table></div>
    <div class="ai-callout" style="margin-top:12px">${I.spark}<span><b>Note:</b> flags are raised on payment, listing, messaging and payout signals. Resolving a case writes an entry to the audit log.</span></div>
  </div>`;
}
function adCMS(){
  return `<div class="panel"><h3 style="font-size:18px">Content management</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Page</th><th>Status</th><th>Last edit</th><th>Editor</th><th></th></tr></thead><tbody>
    ${(ADMIN_STATE.cms||[]).length?(ADMIN_STATE.cms).map(c=>`<tr><td class="strong">${esc(c[0])}</td><td><span class="pill-status ${c[1]==="Live"?"ok":"warn"}">${esc(c[1])}</span></td><td class="sub">${esc(c[2])}</td><td class="sub">${esc(c[3])}</td>
    <td><div class="btnrow"><button class="btn btn-ghost btn-sm" onclick="cmsEdit(${c[4]})">${I.edit} Edit</button>
    <button class="btn btn-ghost btn-sm" onclick="admAction('cms','toggle',${c[4]})">${c[1]==="Live"?"Unpublish":"Publish"}</button></div></td></tr>`).join("")
    :`<tr><td colspan="5" class="sub" style="text-align:center;padding:22px">No content blocks yet — create the first one below.</td></tr>`}
    </tbody></table></div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-gold btn-sm" onclick="cmsEdit(0)">${I.plus} New block</button></div>
  </div>`;
}
function admReports(){
  // Every export the API actually serves, each showing how many rows it
  // currently holds so an empty download is never a surprise.
  const K=ADMIN_STATS||{};
  const rows=[
    ["Bookings","Every reservation with totals","bookings",K.bookingsAll],
    ["Revenue by month","Twelve-month series","revenue",null],
    ["Properties","Listings, pricing and ratings","properties",(K.listings||0)+(K.listingsPend||0)],
    ["Users","Accounts, tiers and points","users",K.users],
    ["Hosts","Owners, listings and payout status","hosts",K.hosts],
    ["Reviews","Published guest reviews","reviews",K.reviews],
    ["Newsletter","Subscriber list","subscribers",K.subscribers],
    ["Audit log","Every administrative action","audit",null],
  ];
  return `<div class="panel"><h3 style="font-size:18px">Reporting &amp; analytics</h3>
    <p class="small" style="margin-bottom:12px">Custom reports on occupancy, revenue by region, demographics and booking trends. Each download is generated live from the database.</p>
    <div class="grid-3">
      ${rows.map(r=>`
      <div class="panel" style="background:var(--card-2)"><b style="font-family:var(--fs-serif);font-size:17px">${r[0]}</b><div class="small" style="margin:4px 0 10px">${r[1]}${r[3]!=null?` · <b>${r[3]}</b> record${r[3]===1?"":"s"}`:""}</div>
      <div class="btnrow"><a class="btn btn-ghost btn-sm" href="${JL.apiBase}report.php?r=${r[2]}&format=csv">${I.download} Download CSV</a></div></div>`).join("")}
    </div>
  </div>`;
}
function admRoles(){
  return `<div class="panel"><h3 style="font-size:18px">Role-based access control</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Role</th><th>Access</th><th>Members</th><th></th></tr></thead><tbody>
    ${(ADMIN_STATE.roles||[]).length?(ADMIN_STATE.roles).map(r=>`
    <tr><td class="strong">${esc(cap(String(r[0])))}</td><td class="sub">${esc(r[1])}</td><td>${r[2]}</td>
    <td><span class="pill-status ${r[2]>0?"ok":"info"}">${r[2]>0?"active":"no members"}</span></td></tr>`).join("")
    :`<tr><td colspan="4" class="sub" style="text-align:center;padding:22px">No roles configured.</td></tr>`}
    </tbody></table></div>
    <div class="small" style="margin-top:10px">Every admin action is written to the immutable audit log — accountability by design.</div>
  </div>`;
}
function admAudit(){
  return `<div class="panel"><h3 style="font-size:18px">Audit log</h3>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th></th></tr></thead><tbody>
    ${(ADMIN_STATE.audit||[]).length?(ADMIN_STATE.audit).map(a=>`<tr><td class="sub">${esc(a[0])}</td><td>${esc(a[1])}</td><td>${esc(a[2])}</td>
    <td><span class="pill-status ${a[3]==="ok"?"ok":a[3]==="warn"?"warn":"info"}">${esc(a[3])}</span></td></tr>`).join("")
    :`<tr><td colspan="4" class="sub" style="text-align:center;padding:22px">No administrative actions recorded yet.</td></tr>`}
    </tbody></table></div>
    <div class="btnrow" style="margin-top:12px"><a class="btn btn-ghost btn-sm" href="${JL.apiBase}report.php?r=audit&format=csv">${I.download} Export CSV</a></div>
  </div>`;
}

/* ---------------- ABOUT ---------------- */
function pAbout(){
  return `${pageHead([["Home",URL("/")],["About"]],"Luxury living, <em class='serif-i'>African soul</em>","Jollof Living exists to show the world that world-class luxury service is Nigerian-born and Nigerian-bred.")}
  <div class="page-body"><div class="wrap">
    <div class="grid-2" style="align-items:center">
      <div class="article">
        <h2>The story</h2>
        <p>We started Jollof Living after years of watching brilliant Nigerian homes — penthouses over the lagoon, courtyard houses in Ikoyi, villas behind Banana Island's guarded bridge — offered with indifferent service on global platforms that never understood them.</p>
        <p>So we built the platform we wanted: the operational depth of the world's best travel companies, the trust of escrow payments, the intelligence of AI — and the warmth of Nigerian hospitality. "Jollof" is the dish that brings everyone to one table. That's the standard.</p>
        <h2>What we believe</h2>
        <p>Every stay should feel considered — nothing average, never indifferent. And every guest, host and team member should be protected by design, not by promise.</p>
      </div>
      <div>
        <div class="collections-grid" style="grid-template-columns:1fr 1fr;gap:12px">
          ${[["p1","Est. 2025","Lagos & Abuja"],["p7","120+ features","25+ AI"],["p12","Jollof Verified","every home"],["p3","4.93★","avg rating"]].map(([ik,b,t])=>`
          <div class="col-card" style="height:170px"><div class="img" style="background-image:url('${img(ik)}')"></div><div class="veil"></div><div class="meta"><h3 style="font-size:17px">${b}</h3><p>${t}</p></div></div>`).join("")}
        </div>
      </div>
    </div>
    <div class="grid-3" style="margin-top:44px">
      ${[["shield","Compliance & privacy","NDPR & GDPR compliant. PCI-DSS secure payments. 2FA everywhere. Data export & erase available on request — a DPO is appointed."],
         ["scale","Anti-discrimination","Fair booking practices enforced on every listing, in every language. Equal access for all guests is a platform rule, not a policy aspiration."],
         ["leaf","Sustainability","Carbon offset at checkout, “Green Stay” badges, and a transparent sustainability score on every residence."]].map(c=>`
      <div class="panel"><div class="why-ico">${I[c[0]]}</div><h3 style="font-size:19px">${c[1]}</h3><p class="muted" style="font-size:14px">${c[2]}</p></div>`).join("")}
    </div>
    <div class="panel" style="margin-top:26px;background:linear-gradient(150deg,var(--card),var(--gold-soft));text-align:center">
      <b style="font-family:var(--fs-serif);font-size:24px">The road ahead</b>
      <p class="muted" style="max-width:60ch;margin:6px auto 14px">Business travel, fractional investment, AR previews — the full roadmap is open.</p>
      <a class="btn btn-gold" href="${URL('/future')}">See the roadmap</a>
    </div>
  </div></div>`;
}

/* ---------------- REVIEWS ---------------- */
function pReviews(){
  const feat=PROPERTIES.filter(p=>p.reviewsList).slice(0,3);
  const avg=(PROPERTIES.reduce((a,p)=>a+p.rating,0)/PROPERTIES.length).toFixed(2);
  const tot=PROPERTIES.reduce((a,p)=>a+p.reviews,0);
  return `${pageHead([["Home",URL("/")],["Reviews"]],"Loved by <em class='serif-i'>thousands</em>","Every review below comes from a verified, completed stay. Nothing else is allowed on Jollof Living — it's in the platform rules.","<span class='badge ok'>${I.star} ${avg} average</span>")}
  <div class="page-body"><div class="wrap">
    <div class="grid-4">
      ${[[avg,"Average rating across residences"],[(tot).toLocaleString(),"Verified guest reviews"],[97,"% would stay again"],[72,"NPS — world-class range"]].map(s=>`
      <div class="stat-kpi"><div class="lbl">${s[0]}</div><div class="val gold-text" style="font-size:26px">${s[1]}</div></div>`).join("")}
    </div>
    <div class="grid-2" style="margin-top:20px">
      <div>
        <h3 style="font-size:20px;margin-bottom:12px">Recent verified reviews</h3>
        <div class="stack">${feat.map(p=>p.reviewsList.slice(0,2).map(r=>`
        <div class="rev-row panel"><div class="hd"><span class="avatar">${r[0][0]}</span>
          <div><b style="font-family:var(--fs-serif);font-size:17px">${esc(r[0])}</b><div class="small">${esc(r[1])} · stayed at <a href="${URL(`/stay/${p.id}`)}">${esc(p.name)}</a></div></div>
          <span class="pill-status ok" style="margin-left:auto">${I.check} Verified stay</span></div>
          <div class="stars" style="margin:8px 0 6px">${I.star.repeat(5)}</div>
          <q>“${esc(r[2])}”</q></div>`).join("")).join("")}</div>
      </div>
      <div class="stack">
        <div class="ai-callout">${I.spark}<span><b>AI review summaries.</b> When a residence passes 20 reviews, Jollof AI distils them into one honest paragraph — guests see the real picture in seconds, hosts get an evidence-based improvement list.</span></div>
        ${feat.map(p=>`<div class="panel"><div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px">
          <b style="font-family:var(--fs-serif);font-size:18px">${esc(p.name)}</b>
          <span class="small">${I.star} ${p.rating.toFixed(2)} · ${p.reviews} reviews</span></div>
          <p class="small" style="margin-top:8px">${esc(p.aiSummary)}</p>
          <a class="link-arrow" style="font-size:11.5px" href="${URL(`/stay/${p.id}`)}">Read ${p.reviews} reviews ${I.arrow}</a></div>`).join("")}
        <div class="panel" style="background:linear-gradient(150deg,var(--card),var(--gold-soft))">
          <h3 style="font-size:20px">How reviews work here</h3>
          ${[["Only verified stays","Bookings on the platform can be reviewed — nothing else."],["No pay-to-play","Hosts can't remove or buy reviews. Period."],["Structured + free-text","Cleanliness, accuracy, check-in, location, value & fairness."],["AI, then humans","Summaries are AI-drafted; wild claims always get human review."]].map(r=>`
          <div class="krow"><span class="k">${I.checkCircle}</span><span class="v">${r[0]} — <span class="muted">${r[1]}</span></span></div>`).join("")}
        </div>
      </div>
    </div>
    <h3 style="font-size:22px;margin:34px 0 14px;text-align:center">Stories from our guests</h3>
    <div class="rev-wall stagger">${TESTIMONIALS.map(t=>`
      <figure class="rev-card"><div class="stars">${I.star.repeat(5)}</div><q>“${esc(t[2])}”</q>
      <figcaption class="rev-who"><span class="avatar">${t[0][0]}</span><div><div class="nm">${esc(t[0])}</div><div class="st">${esc(t[1])}</div></div></figcaption></figure>`).join("")}
    </div>
  </div></div>`;
}

/* ---------------- APP ---------------- */
function pApp(){
  return `${pageHead([["Home",URL("/")],["Mobile app"]],"The <em class='serif-i'>app</em>","Keyless check-in, live messaging, wallet passes, voice booking — the whole platform, in your pocket.")}
  <div class="page-body"><div class="wrap" style="max-width:940px">
    <div class="grid-2" style="align-items:center">
      <div>${phoneMock()}</div>
      <div class="stack">
        ${[["key","Keyless check-in","Smart-lock codes generated per booking, expiring at checkout."],["chat","In-app messaging","Real-time chat with hosts and Jollof — read receipts, media, translation."],["calendar","Trips & wallet passes","Everything in one place: bookings, invoices, boarding passes to your stay."],["bot","Voice booking","“Hey Siri, book my favourite apartment in Abuja for Christmas.”"],["notification","Push, SMS & WhatsApp","Price drops, confirmations and reminders where you already are."],["shield","Biometric security","Face ID + 2FA, with your documents in an encrypted vault."]].map(f=>`
        <div style="display:flex;gap:14px"><div class="why-ico" style="margin:0;width:44px;height:44px;border-radius:12px">${I[f[0]]}</div>
        <div><b style="font-family:var(--fs-serif);font-size:19px">${f[1]}</b><p class="muted" style="font-size:14px">${f[2]}</p></div></div>`).join("")}
        <div class="btnrow" style="margin-top:8px">
          <button class="btn btn-gold" onclick="toast('iPhone demo build — coming to TestFlight','phone')">${I.phone} App Store</button>
          <button class="btn btn-ghost" onclick="toast('Android demo build — coming to Play','phone')">${I.phone} Google Play</button>
        </div>
      </div>
    </div>
  </div></div>`;
}

/* ---------------- ROADMAP ---------------- */
function pFuture(){
  return `${pageHead([["Home",URL("/")],["Roadmap"]],"The <em class='serif-i'>future</em> of Jollof Living","A live product roadmap — what's shipping, what's building, what's dreaming.")}
  <div class="page-body"><div class="wrap">
    <div class="legend" style="margin-bottom:16px">
      <span><i style="background:var(--ok)"></i>Live</span>
      <span><i style="background:var(--accent)"></i>In development</span>
      <span><i style="background:var(--info)"></i>Research</span>
    </div>
    <div class="grid-3">${ROADMAP.map(r=>`
      <div class="road-card"><span class="ph ${r.ph}">${r.status==="live"?"Live":r.status==="dev"?"In development":"Coming soon"}</span>
      <h3>${r.title}</h3><p>${r.desc}</p></div>`).join("")}
    </div>
  </div></div>`;
}

/* ---------------- 404 ---------------- */
function p404(){
  return `<div style="padding:calc(var(--header-h) + 90px) 20px;text-align:center">
    <div style="font-family:var(--fs-serif);font-size:clamp(5rem,16vw,9rem);font-weight:600;line-height:1" class="gold-text">404</div>
    <h1 style="font-size:clamp(1.6rem,4vw,2.4rem);margin:10px 0 8px">This address doesn't exist — yet</h1>
    <p class="muted" style="max-width:44ch;margin:0 auto 22px">The page you're looking for has checked out. Let us take you somewhere beautiful instead.</p>
    <div class="btnrow" style="justify-content:center"><a class="btn btn-gold" href="${URL('/')}">Back home</a><a class="btn btn-ghost" href="${URL('/stays')}">Browse stays</a></div>
  </div>`;
}


/* ---------------- back-office actions (all persisted) ---------------- */
async function admAction(entity,action,id){
  const r=await api("admin-action.php",{entity,action,id});
  if(!r.ok){ toast(r.message||"That action could not be completed","x"); return; }
  toast(r.message||"Done","check");
  setTimeout(()=>location.reload(),600);
}
function cmsEdit(id){
  const block=(ADMIN_STATE.cmsBlocks||[]).find(b=>b.id===id)||{id:0,title:"",body:"",status:"Draft"};
  openModal(`<h2 style="margin-bottom:4px">${id?"Edit content block":"New content block"}</h2>
    <p class="small" style="margin-bottom:14px">Blocks are stored in MySQL and can be rendered on any page.</p>
    <div class="frm-row"><label>Title</label><input class="inp" id="cmsTitle" value="${esc(block.title||"")}"></div>
    <div class="frm-row"><label>Body</label><textarea class="txa" id="cmsBody" rows="7">${esc(block.body||"")}</textarea></div>
    <div class="frm-row"><label>Status</label><select class="sel" id="cmsStatus"><option ${block.status==="Live"?"selected":""}>Live</option><option ${block.status!=="Live"?"selected":""}>Draft</option></select></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="cmsSave(${id})">Save block</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function cmsSave(id){
  const r=await api("admin-action.php",{entity:"cms",action:"save",id,
    title:$("#cmsTitle").value,body:$("#cmsBody").value,status:$("#cmsStatus").value});
  closeModal();
  if(!r.ok){ toast(r.message||"Could not save that block","x"); return; }
  toast("Content saved ✨","check"); setTimeout(()=>location.reload(),600);
}
function campaignEdit(id){
  const c=(JL.campaigns||[]).find(x=>x.id===id)||{id:0,code:"",name:"",window_label:"",status:"Draft"};
  openModal(`<h2 style="margin-bottom:4px">${id?"Edit campaign":"New campaign"}</h2>
    <div class="frm-row"><label>Promo code</label><input class="inp" id="cmCode" value="${esc(c.code||"")}" placeholder="JOLLOF10"></div>
    <div class="frm-row"><label>Name</label><input class="inp" id="cmName" value="${esc(c.name||"")}"></div>
    <div class="frm-row"><label>Window</label><input class="inp" id="cmWindow" value="${esc(c.window_label||"")}" placeholder="Oct 1 – Dec 20"></div>
    <div class="frm-row"><label>Status</label><select class="sel" id="cmStatus">${["Live","Scheduled","Draft","Ended"].map(o=>`<option ${c.status===o?"selected":""}>${o}</option>`).join("")}</select></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="campaignSave(${id})">Save campaign</button><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function campaignSave(id){
  const r=await api("admin-action.php",{entity:"campaign",action:"save",id,
    code:$("#cmCode").value,name:$("#cmName").value,window:$("#cmWindow").value,status:$("#cmStatus").value});
  closeModal();
  if(!r.ok){ toast(r.message||"Could not save that campaign","x"); return; }
  toast("Campaign saved ✨","check"); setTimeout(()=>location.reload(),600);
}


/* ---------------- gift cards (recorded in the database) ---------------- */
function buyGiftCard(amount){
  if(!requireAuth("buy a gift card")) return;
  openModal(`<h2 style="margin-bottom:4px">Gift card · ${fmt(amount)}</h2>
    <p class="small" style="margin-bottom:14px">We email the recipient a code they can redeem at checkout.</p>
    <div class="frm-grid">
      <div class="frm-row"><label>Recipient name</label><input class="inp" id="gcName" placeholder="Ada Obi"></div>
      <div class="frm-row"><label>Recipient email</label><input class="inp" id="gcEmail" type="email" placeholder="ada@example.com"></div>
    </div>
    <div class="frm-row"><label>Message (optional)</label><textarea class="txa" id="gcMsg" rows="3" placeholder="Happy birthday — enjoy Lagos!"></textarea></div>
    <div class="btnrow"><button class="btn btn-gold" onclick="submitGiftCard(${amount})">Buy for ${fmt(amount)}</button>
    <button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}
async function submitGiftCard(amount){
  const name=($("#gcName")?.value||"").trim();
  const email=($("#gcEmail")?.value||"").trim();
  const message=($("#gcMsg")?.value||"").trim();
  if(!name){ toast("Please enter the recipient's name","x"); return; }
  if(!email){ toast("Please enter a valid recipient email","x"); return; }

  const btn=document.querySelector(".modal-body button.btn-gold");
  if(btn){ btn.disabled=true; btn.textContent="Connecting to Paystack…"; }

  const r=await api("pay.php",{
    action:"initiate",
    kind:"giftcard",
    amount,
    name,
    email,
    message,
    buyer_email:(USER&&USER.email)||email,
    buyer_name:(USER&&USER.name)||name
  });

  if(!r.ok){
    if(btn){ btn.disabled=false; btn.textContent=`Buy for ${fmt(amount)}`; }
    toast(r.message||"Payment initialization failed","x");
    return;
  }

  // If live gateway return URL is provided, redirect to Paystack checkout
  if(r.data&&r.data.redirect_url){
    closeModal();
    toast("Redirecting to Paystack secure checkout…","lock");
    setTimeout(()=>{ location.href=r.data.redirect_url; }, 800);
    return;
  }

  // Auto-captured or sandbox mode
  closeModal();
  toast(r.message||`Payment confirmed — gift card code ${r.data&&r.data.code?r.data.code:""} issued! ✨`,"gift");
}
async function subscribeHelp(){
  const em=(USER&&USER.email)||prompt("Which email should we send product updates to?");
  if(!em) return;
  const r=await api("newsletter.php",{email:em,source:"help"});
  toast(r.ok?(r.message||"Subscribed ✨"):(r.message||"Could not subscribe"), r.ok?"check":"x");
}


/* ===== app.js ===== */
/* ============================================================
   JOLLOF LIVING — app.js  (multi-page boot & per-page dispatch)
   Each page is a REAL .html file; this file reads <body data-page>
   and renders that page's view + wires the shared chrome.
   Load order: data.js → ui.js → pages-discovery.js →
   pages-booking.js → pages-comm.js → pages-host.js →
   pages-misc.js → app.js
   ============================================================ */

/* ---------------- per-page dispatch ---------------- */
const PAGE_RENDER = {
  index:            () => ({ html: pHome(), bind: bindHome }),
  stays:            () => ({ html: pStays(qps()), bind: bindStays }),
  map:              () => ({ html: pStays(qps()), bind: bindStays }),
  collections:      () => ({ html: pCollections(), bind: bindCollections }),
  neighborhoods:    () => ({ html: pNeighborhoods() }),
  experiences:      () => ({ html: pExperiences(), bind: bindExperiences }),
  reviews:          () => ({ html: pReviews() }),
  blog:             () => ({ html: pBlog() }),
  blog_post:        (p) => ({ html: pBlogPost(p) }),
  help:             () => ({ html: pHelp(qps()), bind: bindHelp }),
  membership:       () => ({ html: pMembership() }),
  giftcards:        () => ({ html: pGiftCards() }),
  referral:         () => ({ html: pReferral() }),
  business:         () => ({ html: pBusiness() }),
  about:            () => ({ html: pAbout() }),
  app:              () => ({ html: pApp() }),
  future:           () => ({ html: pFuture() }),
  concierge:        () => ({ html: pConcierge(qps()), bind: bindConcierge }),
  agent:            () => ({ html: pAgent(qps()), bind: bindAgent }),
  messages:         () => ({ html: pMessages(qps()), bind: bindMessages }),
  notifications:    () => ({ html: pNotif() }),
  trips:            () => ({ html: pTrips(qps()), bind: bindTrips }),
  wishlist:         () => ({ html: pWishlist(), bind: bindWishlist }),
  compare:          () => ({ html: pCompare() }),
  account:          () => ({ html: pAccount(), bind: bindAccount }),
  auth:             () => ({ html: pAuth(qp("mode") || "signin"), bind: bindAuth }),
  host:             () => ({ html: pHost(), bind: bindHost }),
  host_onboarding:  () => ({ html: pHostOnboarding(), bind: bindHostOnboarding }),
  host_dashboard:   () => ({ html: pHostDashboard(qps()), bind: bindHostDashboard }),
  payments:         () => ({ html: pPayments(qps()), bind: bindPayments }),
  admin:            () => ({ html: pAdmin(qps()), bind: bindAdminLogin }),
  admin_login:      () => ({ html: pAdminLogin(), bind: bindAdminLogin }),
  confirm:          () => ({ html: pConfirm(qp("ref") || "") }),
  notfound:         () => ({ html: p404() }),
  /* dynamic pages keyed by the id carried in <body data-page="stay-onyx"> */
  stay:             (p) => ({ html: pStay(p), bind: () => bindStay(p) }),
  booking:          (p) => ({ html: pBooking(p, qps()), bind: () => bindBooking(p, qps()) }),
  neighborhood:     (p) => ({ html: pNeighborhood(p) || p404() }),
  blog_post:        (p) => ({ html: pBlogPost(p) }),
};

function pageKey() {
  const id = PAGE_ID;                       // e.g. "stay-onyx" | "blog-jollof-100" | "host-dashboard"
  if (id.startsWith("stay-")) return ["stay", id.slice(5)];
  if (id.startsWith("booking-")) return ["booking", id.slice(8)];
  if (id.startsWith("neighborhood-")) return ["neighborhood", id.slice(13)];
  if (id.startsWith("blog-")) return ["blog_post", id.slice(5)];
  return [id.replace(/-/g, "_"), null];    // host-dashboard → host_dashboard
}

function render() {
  const [key, param] = pageKey();
  const entry = PAGE_RENDER[key] || PAGE_RENDER.notfound;
  let out;
  try { out = entry(param); } catch (err) { console.error(err); out = { html: p404() }; }
  const view = $("#view"); if (!view) return;
  view.innerHTML = out.html || p404();
  observeReveals();
  if (out.bind) { try { out.bind(); } catch (err) { console.error("[bind]", err); } }
  renderBadges();
  window.scrollTo(0, 0);
}

/* ---------------- theme ---------------- */
function applyTheme(t) {
  document.documentElement.setAttribute("data-theme", t);
  store.set("theme", t);
  const pick = (d, l) => (t === "dark" ? d : l);
  const wm = $("#brandImg"), dw = $("#drawerLogo"), fl = $("#footLogo");
  if (wm) wm.src = img(pick("wordmark-dark", "wordmark-light"));
  if (dw) dw.src = img(pick("wordmark-dark", "wordmark-light"));
  if (fl) fl.src = img(pick("logo-dark", "logo-light"));
}
$("#themeBtn").addEventListener("click", () => {
  const next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
  applyTheme(next);
  toast(next === "dark" ? "Dark mode — good evening ✦" : "Light mode — good morning ☀", next === "dark" ? "moon" : "sun");
});

/* ---------------- header ---------------- */
addEventListener("scroll", () => { $("#header").classList.toggle("scrolled", scrollY > 30); }, { passive: true });

/* ---------------- active nav state ---------------- */
(function () {
  const id = PAGE_ID;
  const section =
    id === "index" ? "/" :
    id.startsWith("stay") || id.startsWith("booking") || id === "stays" || id === "map" || id === "collections" ? "/stays" :
    id === "experiences" ? "/experiences" :
    id.startsWith("neighborhood") ? "/neighborhoods" :
    id.startsWith("host") ? "/host" :
    id === "membership" ? "/membership" :
    id === "help" ? "/help" : null;
  if (!section) return;
  $$("#navLinks a").forEach(a => a.classList.toggle("active", a.getAttribute("data-r") === section));
})();

/* ---------------- drawer ---------------- */
function openDrawer() { $("#drawer").classList.add("open"); document.body.style.overflow = "hidden"; }
function closeDrawer() { const d = $("#drawer"); if (d) d.classList.remove("open"); document.body.style.overflow = ""; }
$("#burgerBtn").addEventListener("click", openDrawer);
$("#drawer").addEventListener("click", (e) => { if (e.target.closest("a")) closeDrawer(); });

/* drawer content: real page links, adjusted for who is signed in */
(function () {
  const links = [["/", "Home"], ["/stays", "Stays"], ["/experiences", "Experiences"],
    ["/neighborhoods", "Neighbourhoods"], ["/concierge", "AI Concierge"],
    ["/trips", "My trips"], ["/wishlist", "Wishlist"], ["/host", "Host"],
    ["/membership", "Jollof Club"], ["/reviews", "Reviews"], ["/blog", "Journal"], ["/help", "Help"]];
  // Owners get a direct route to their workspace.
  if (IS_OWNER) links.splice(8, 0, ["/host/dashboard", "Owner dashboard"]);
  const dl = $("#drawerLinks");
  if (dl && !dl.children.length) dl.innerHTML = links.map(([h, l]) => `<a href="${URL(h)}">${l}</a>`).join("");

  // Signing out only makes sense when signed in, and offering "Sign in" to
  // someone who already is was the bug users reported. view.php renders
  // these server-side so they survive a JS failure; only fill in if empty.
  const dc = $("#drawerCtas");
  if (dc && !dc.children.length) dc.innerHTML = USER
    ? `<a class="btn btn-gold" href="${URL(IS_OWNER ? "/host/dashboard" : "/host/onboarding")}">${IS_OWNER ? "Owner dashboard" : "List your home"}</a>
       <a class="btn btn-ghost" href="${URL("/account")}">My account</a>
       <a class="btn btn-ghost" href="${JL.base}logout.php" id="drawerLogout">Log out</a>`
    : `<a class="btn btn-gold" href="${URL("/host/onboarding")}">List your home</a>
       <a class="btn btn-ghost" href="${URL("/auth")}">Sign in / Join</a>
       <a class="btn btn-ghost" href="${URL("/auth?mode=register")}">Create account</a>`;
})();

/* ---------------- quick actions (real page links) ---------------- */
$("#notifBtn").addEventListener("click", () => nav("/notifications"));
$("#msgBtn").addEventListener("click", () => nav("/messages"));
$("#wlBtn").addEventListener("click", () => nav("/wishlist"));
/* The floating button opens the live chat desk. The AI concierge stays one tap
   away inside the widget (and in the drawer). */
$("#chatFab").addEventListener("click", () => { if (typeof liveChatOpen === "function") liveChatOpen(); else nav("/concierge"); });

/* ---------------- currency ---------------- */
const curSel = $("#currencySel");
curSel.value = currency;
curSel.addEventListener("change", () => {
  currency = curSel.value; store.set("currency", currency);
  document.cookie = "jl_currency=" + encodeURIComponent(currency) + ";path=/;max-age=" + (60*60*24*365) + ";samesite=lax";
  render();
  toast("Prices shown in " + currency, "exchange");
});

/* ---------------- newsletter ---------------- */
$("#newsBtn").addEventListener("click", async () => {
  const input = $("#newsInput");
  const em = input.value.trim();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { toast("Please enter a valid email", "x"); return; }
  const btn = $("#newsBtn"); btn.disabled = true;
  const r = await api("newsletter.php", { email: em, source: PAGE_ID });
  btn.disabled = false;
  if (!r.ok) { toast(r.message || "Could not subscribe — please try again", "x"); return; }
  input.value = "";
  toast(r.message || "Welcome to the inner circle — check your inbox ✨", "check");
});
$("#newsInput").addEventListener("keydown", (e) => { if (e.key === "Enter") $("#newsBtn").click(); });

/* ---------------- scrims & escape ---------------- */
$$(".modal-root .scrim, .sheet .scrim").forEach((s) => s.addEventListener("click", () => { closeModal(); closeSheet(); closeDrawer(); }));
addEventListener("keydown", (e) => { if (e.key === "Escape") { closeModal(); closeSheet(); closeDrawer(); } });

/* ---------------- reveal on scroll (visual only — never scrolls the page) ---------------- */
let io = null;
function observeReveals() {
  if (!io) io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); } }), { threshold: 0.1 });
  $$(".reveal, .stagger").forEach((el) => io.observe(el));
}

/* ---------------- boot ---------------- */
$("#yearNow").textContent = new Date().getFullYear();
applyTheme(store.get("theme", "dark"));
render();
/* the live chat desk boots behind the scenes: the widget only fetches when opened */
if (typeof liveChatBoot === "function") liveChatBoot();
