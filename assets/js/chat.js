/* ============================================================
   JOLLOF LIVING — chat.js
   Live chat widget · agent console · dispute resolution centre
   ============================================================
   Three pieces, all backed by the database through api/chat.php and
   api/dispute.php:

     1. Live chat widget   the visitor side of the desk, on every page
     2. Agent console      /agent.php — the workspace agents sign into
     3. Dispute centre     the resolution centre on /help + the back office

   The file is loaded BEFORE site.js so the page dispatch can reach the
   renderers. Nothing here runs at parse time, so the helpers site.js
   defines ($, $$, api, toast, openModal, URL…) are only touched once a
   page has actually booted.
   ============================================================ */

/* ---------------- local helpers ---------------- */
const cOne = (s, el) => (el || document).querySelector(s);
const cMany = (s, el) => [...(el || document).querySelectorAll(s)];
const cEsc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const cGet = (k, d) => { try { const v = localStorage.getItem("jl_" + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } };
const cSet = (k, v) => { try { localStorage.setItem("jl_" + k, JSON.stringify(v)); } catch (e) {} };
const cDel = (k) => { try { localStorage.removeItem("jl_" + k); } catch (e) {} };
const cToast = (m, i) => { if (typeof toast === "function") toast(m, i || "check"); };
const cUrl = (p) => (typeof URL === "function" ? URL(p) : p);
const cMoney = (n, cur) => (typeof fmt === "function" ? fmt(n || 0) : "₦" + (n || 0));

async function cApi(endpoint, payload) {
  const base = (JL && JL.apiBase) || "api/";
  const opts = {
    method: payload ? "POST" : "GET",
    headers: { "X-CSRF-Token": (JL && JL.csrf) || "", "X-Requested-With": "fetch" },
    credentials: "same-origin",
  };
  if (payload) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(Object.assign({ csrf: (JL && JL.csrf) || "" }, payload));
  }
  try {
    const res = await fetch(base + endpoint, opts);
    const body = await res.json();
    return body && typeof body === "object" ? body : { ok: false, message: "Unexpected response." };
  } catch (e) {
    return { ok: false, message: "Network problem — please try again." };
  }
}

/* ============================================================
   1. LIVE CHAT WIDGET
   ============================================================ */
const CW = { booted: false, cfg: null, open: false, ref: null, token: null, bundle: null, timer: null, tick: null, lastId: 0, typingAt: 0, dept: "", mode: "home", unread: 0, subject: "" };

function liveChatBoot() {
  if (CW.booted) return;
  CW.booted = true;
  CW.ref = cGet("chat_ref", null);
  CW.token = cGet("chat_token", null);

  const shell = document.createElement("aside");
  shell.className = "cw";
  shell.id = "cw";
  shell.setAttribute("aria-live", "polite");
  shell.hidden = true;
  document.body.appendChild(shell);

  const fab = document.getElementById("chatFab");
  if (fab && !document.getElementById("cwBadge")) {
    const b = document.createElement("span");
    b.className = "cw-badge";
    b.id = "cwBadge";
    b.hidden = true;
    fab.appendChild(b);
  }

  window.addEventListener("beforeunload", () => { if (CW.ref) cSet("chat_ref", CW.ref); });
}

/* Re-open a chat the visitor already had, when there is one. */
async function liveChatOpen() {
  liveChatBoot();
  CW.open = true;
  const shell = cOne("#cw");
  shell.hidden = false;
  shell.classList.add("open");
  if (!CW.cfg) {
    cwPaint("loading");
    const r = await cApi("chat.php", { action: "config" });
    if (r.ok) CW.cfg = r.data;
    else { CW.cfg = { enabled: false, offline: r.message || "Live chat is unavailable right now." }; }
  }
  if (CW.ref && CW.token) {
    const p = await cApi("chat.php", { action: "poll", ref: CW.ref, token: CW.token, after: 0, as: "visitor" });
    if (p.ok && p.data) {
      const st = p.data.status;
      CW.bundle = p.data;
      CW.lastId = (p.data.messages || []).reduce((a, m) => Math.max(a, m.id), 0);
      CW.subject = p.data.subject || "";
      if (st === "queued" || st === "active") { cwPaint("chat"); cwPollLoop(); return; }
      if (st === "closed" && (p.data.ratingOn && !p.data.rating)) { cwPaint("rate"); return; }
    }
    cwForget();
  }
  cwPaint("home");
}

function liveChatClose() {
  CW.open = false;
  const shell = cOne("#cw");
  if (shell) { shell.classList.remove("open"); shell.hidden = true; }
  if (CW.tick) { clearInterval(CW.tick); CW.tick = null; }
  cwBadge(0);
}

function cwForget() {
  cDel("chat_ref"); cDel("chat_token");
  CW.ref = null; CW.token = null; CW.bundle = null; CW.lastId = 0;
  if (CW.tick) { clearInterval(CW.tick); CW.tick = null; }
}

function cwBadge(n) {
  CW.unread = n;
  const b = document.getElementById("cwBadge");
  if (b) { b.textContent = n > 9 ? "9+" : String(n); b.hidden = !n; }
}

function cwPaint(mode, extra) {
  const shell = cOne("#cw");
  if (!shell) return;
  CW.mode = mode || CW.mode;
  let body = "";
  if (CW.mode === "loading") {
    body = `<div class="cw-wait">Opening the desk…</div>`;
  } else if (CW.mode === "home") {
    body = cwHomeHTML();
  } else if (CW.mode === "chat") {
    body = cwChatHTML();
  } else if (CW.mode === "rate") {
    body = cwRateHTML();
  }
  shell.innerHTML = `<div class="cw-panel" role="dialog" aria-label="Live chat">
      <div class="cw-head">
        <span class="cw-avatar">${typeof I !== "undefined" && I.chat ? I.chat : ""}</span>
        <div class="cw-id"><b>${cEsc((CW.bundle && CW.bundle.agent && CW.bundle.agent.name) || "Jollof Living support")}</b>
          <span>${cEsc(cwHeadline())}</span></div>
        <button class="cw-x" aria-label="Close chat" onclick="liveChatClose()">&times;</button>
      </div>
      <div class="cw-body">${body}</div>
      ${CW.mode === "chat" ? cwFootHTML() : ""}
    </div>`;
  cwWire();
  if (CW.mode === "chat") cwScroll();
}

function cwHeadline() {
  const b = CW.bundle;
  if (!b) return (CW.cfg && CW.cfg.hours) || "Live chat";
  if (b.status === "queued") return "Waiting for an agent · position " + (b.position || 1);
  if (b.status === "active") return (b.agent ? "Online now" : "An agent will join shortly");
  return "Chat ended";
}

function cwHomeHTML() {
  const cfg = CW.cfg || {};
  if (cfg.enabled === false) {
    return `<div class="cw-note">${cEsc(cfg.offline || "Our team is offline right now.")}</div>
      <a class="btn btn-gold btn-block" href="${cUrl("/concierge")}">Ask the AI concierge</a>
      <a class="btn btn-ghost btn-block" href="${cUrl("/help")}">Browse the help centre</a>`;
  }
  const deps = (cfg.departments || []).filter((d) => d.active !== false);
  return `<div class="cw-hello">${cEsc(cfg.welcome || "Hello! How can we help?")}</div>
    <div class="cw-lab">What is this about?</div>
    <div class="cw-deps">
      ${deps.map((d) => `<button type="button" class="cw-dep ${CW.dept === d.slug ? "on" : ""}" data-dep="${cEsc(d.slug)}">
        <b>${cEsc(d.name)}</b><span>${cEsc(d.hours || "")}${d.agents ? ` · ${d.agents} ${d.agents === 1 ? "agent" : "agents"} on duty` : ""}</span></button>`).join("")}
    </div>
    ${JL.user ? "" : `<div class="cw-two">
      <input class="inp" id="cwName" placeholder="Your name" autocomplete="name">
      <input class="inp" id="cwEmail" type="email" placeholder="Email (optional)" autocomplete="email"></div>`}
    <textarea class="txa" id="cwFirst" rows="3" placeholder="Tell us what you need — a booking reference helps."></textarea>
    <button class="btn btn-gold btn-block" id="cwStart">Start chat</button>
    <div class="cw-alt">No time to chat? <a href="${cUrl("/concierge")}">Ask the AI concierge</a> · <a href="${cUrl("/help")}">Help centre</a></div>`;
}

function cwChatHTML() {
  const b = CW.bundle || {};
  const msgs = b.messages || [];
  let lastDay = "";
  const rows = msgs.map((m) => {
    const day = m.day || "";
    const head = day && day !== lastDay ? (lastDay = day, `<div class="day">${cEsc(day)}</div>`) : "";
    if (m.from === "system") return head + `<div class="cw-sys">${cEsc(m.body)}</div>`;
    if (m.from === "note") return head + "";
    const mine = m.from === "visitor";
    return head + `<div class="msg ${mine ? "me" : "bot"}">${cEsc(m.body).replace(/\n/g, "<br>")}
      <span class="mtime">${cEsc(m.time || "")}${m.name && !mine ? " · " + cEsc(m.name) : ""}</span></div>`;
  }).join("");
  const typing = b.agentTyping ? `<div class="msg bot cw-typing"><span class="typing"><i></i><i></i><i></i></span></div>` : "";
  const queue = b.status === "queued"
    ? `<div class="cw-queue">You are number <b>${b.position || 1}</b> in the queue — an agent will be with you shortly. Mean wait ${cEsc(b.wait || "under a minute")}.</div>` : "";
  return `${queue}${rows}${typing}${msgs.length ? "" : `<div class="cw-wait">Connecting you to the team…</div>`}`;
}

function cwFootHTML() {
  const b = CW.bundle || {};
  if (b.status === "closed") {
    return `<div class="cw-foot"><button class="btn btn-ghost btn-block" onclick="liveChatReset()">Start a new chat</button></div>`;
  }
  return `<div class="cw-foot">
    <div class="cw-tools">
      <button type="button" class="btn btn-ghost btn-sm" onclick="liveChatEnd()">End chat</button>
      <span class="cw-status">${b.status === "queued" ? "In the queue" : "Connected"}</span>
    </div>
    <div class="cw-send">
      <textarea id="cwInp" rows="1" placeholder="Write a message…" aria-label="Message"></textarea>
      <button class="btn btn-gold" id="cwSend" aria-label="Send">${typeof I !== "undefined" && I.send ? I.send : "Send"}</button>
    </div>
  </div>`;
}

function cwRateHTML() {
  const b = CW.bundle || {};
  return `<div class="cw-note">This chat has ended.${b.agent ? ` ${cEsc(b.agent.name)} was your agent.` : ""}</div>
    <div class="cw-lab">How did we do?</div>
    <div class="cw-stars">${[1, 2, 3, 4, 5].map((n) => `<button type="button" data-star="${n}" title="${n} star${n > 1 ? "s" : ""}">★</button>`).join("")}</div>
    <textarea class="txa" id="cwRateNote" rows="2" placeholder="Anything we should know? (optional)"></textarea>
    <button class="btn btn-ghost btn-block" onclick="liveChatReset()">Start a new chat</button>`;
}

function cwWire() {
  if (CW.mode === "home") {
    cMany("#cw .cw-dep").forEach((b) => b.addEventListener("click", () => {
      CW.dept = b.dataset.dep;
      cMany("#cw .cw-dep").forEach((x) => x.classList.toggle("on", x === b));
    }));
    const s = cOne("#cwStart");
    if (s) s.addEventListener("click", cwStart);
    const f = cOne("#cwFirst");
    if (f) f.addEventListener("keydown", (e) => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) cwStart(); });
  } else if (CW.mode === "chat") {
    const inp = cOne("#cwInp"), send = cOne("#cwSend");
    if (send) send.addEventListener("click", cwSend);
    if (inp) {
      inp.addEventListener("keydown", (e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); cwSend(); } });
      inp.addEventListener("input", cwTyping);
    }
  } else if (CW.mode === "rate") {
    cMany("#cw .cw-stars button").forEach((b) => b.addEventListener("click", () => cwRate(parseInt(b.dataset.star, 10))));
  }
}

function cwScroll() { const b = cOne("#cw .cw-body"); if (b) b.scrollTop = b.scrollHeight; }

async function cwStart() {
  const first = (cOne("#cwFirst") && cOne("#cwFirst").value || "").trim();
  if (!first) { cToast("Tell us what you need first — one line is enough.", "chat"); return; }
  const btn = cOne("#cwStart");
  if (btn) { btn.disabled = true; btn.textContent = "Connecting…"; }
  const r = await cApi("chat.php", {
    action: "start",
    department: CW.dept || undefined,
    message: first,
    name: cOne("#cwName") ? cOne("#cwName").value : undefined,
    email: cOne("#cwEmail") ? cOne("#cwEmail").value : undefined,
    page: location.href,
    token: CW.token || undefined,
  });
  if (!r.ok) { cToast(r.message || "Could not start the chat.", "x"); if (btn) { btn.disabled = false; btn.textContent = "Start chat"; } return; }
  CW.bundle = r.data;
  CW.ref = r.data.ref;
  CW.token = r.data.token;
  cSet("chat_ref", CW.ref); cSet("chat_token", CW.token);
  CW.subject = r.data.subject || "";
  CW.lastId = (r.data.messages || []).reduce((a, m) => Math.max(a, m.id), 0);
  cwPaint("chat");
  cwPollLoop();
  cToast(r.message || "Connected.", "chat");
}

function cwPollLoop() {
  if (CW.tick) clearInterval(CW.tick);
  CW.tick = setInterval(async () => {
    if (!CW.open || !CW.ref || !CW.token) return;
    const r = await cApi("chat.php", { action: "poll", ref: CW.ref, token: CW.token, after: 0, as: "visitor" });
    if (!r.ok || !r.data) return;
    const prevStatus = CW.bundle ? CW.bundle.status : "";
    CW.bundle = r.data;
    CW.lastId = (r.data.messages || []).reduce((a, m) => Math.max(a, m.id), 0);
    if (r.data.status === "closed" && prevStatus !== "closed") {
      clearInterval(CW.tick); CW.tick = null;
      cwPaint(r.data.ratingOn && !r.data.rating ? "rate" : "chat");
      cToast("The agent ended the chat.", "chat");
      return;
    }
    cwPaint("chat");
  }, 4000);
}

async function cwSend() {
  const inp = cOne("#cwInp");
  const txt = (inp && inp.value || "").trim();
  if (!txt || !CW.ref) return;
  inp.value = "";
  const body = cOne("#cw .cw-body");
  if (body) {
    body.insertAdjacentHTML("beforeend", `<div class="msg me">${cEsc(txt)}<span class="mtime">sending…</span></div>`);
    cwScroll();
  }
  cApi("chat.php", { action: "post", ref: CW.ref, token: CW.token, body: txt }).then((r) => {
    if (!r.ok) { cToast(r.message || "Message not delivered.", "x"); return; }
    const m = body && body.querySelector(".msg.me:last-child .mtime");
    if (m) m.textContent = (r.data && r.data.time ? r.data.time : "now") + " · delivered";
    if (r.data && r.data.bundle) { CW.bundle = r.data.bundle; }
  });
}

function cwTyping() {
  const now = Date.now();
  if (now - CW.typingAt < 3500 || !CW.ref) return;
  CW.typingAt = now;
  cApi("chat.php", { action: "typing", ref: CW.ref, token: CW.token, as: "visitor" });
}

async function cwRate(stars) {
  if (!CW.ref) return;
  const note = cOne("#cwRateNote") ? cOne("#cwRateNote").value : "";
  const r = await cApi("chat.php", { action: "rate", ref: CW.ref, token: CW.token, stars, comment: note });
  cToast(r.ok ? (r.message || "Thank you.") : (r.message || "Could not save that."), r.ok ? "check" : "x");
  if (r.ok) { cDel("chat_ref"); cDel("chat_token"); CW.ref = null; CW.token = null; cwPaint("home"); }
}

function liveChatEnd() {
  openModal(`<h3 style="font-size:20px;margin-bottom:8px">End this chat?</h3>
    <p class="muted" style="font-size:14px">You can start a new chat any time. The transcript is emailed to you when one is on file.</p>
    <div class="btnrow" style="margin-top:14px">
      <button class="btn btn-ghost" onclick="closeModal()">Keep chatting</button>
      <button class="btn btn-gold" id="cwEndYes">End chat</button></div>`);
  const y = cOne("#cwEndYes");
  if (y) y.addEventListener("click", async () => {
    closeModal();
    const r = await cApi("chat.php", { action: "close", ref: CW.ref, token: CW.token, as: "visitor" });
    if (!r.ok) { cToast(r.message || "Could not end the chat.", "x"); return; }
    if (r.data) CW.bundle = r.data;
    if (CW.tick) { clearInterval(CW.tick); CW.tick = null; }
    cwPaint((CW.bundle && CW.bundle.ratingOn && !CW.bundle.rating) ? "rate" : "chat");
  });
}

function liveChatReset() {
  cwForget();
  cwPaint("home");
}

/* ============================================================
   2. AGENT CONSOLE  (/agent.php)
   ============================================================ */
const AC = {
  loaded: false, data: null, me: null, tab: "live", list: "waiting",
  ref: null, session: null, messages: [], visitor: null, events: [],
  lastId: 0, timer: null, agentTimer: null, busy: false, deny: null, cases: null,
};

function pAgent(q) {
  AC.tab = (q && q.tab) || AC.tab || "live";
  return `${pageHead([["Home", cUrl("/")], ["Live chat desk"]], "<em class='serif-i'>Chat desk</em>",
    "Work the queues, reply to guests and keep every conversation on the record.",
    `<span class="ac-badge" id="acKpis"></span>`)}
  <div class="page-body"><div class="wrap">
    <div id="acBody"><div class="empty-state">Loading the desk…</div></div>
  </div></div>`;
}

function bindAgent() {
  acRefresh();
  if (AC.timer) clearInterval(AC.timer);
  AC.timer = setInterval(() => {
    if (document.hidden) return;
    acRefresh(true);
    if (AC.ref) acPollChat();
  }, 6000);
  document.addEventListener("visibilitychange", () => { if (!document.hidden) acRefresh(true); });
}

async function acRefresh(silent) {
  const body = cOne("#acBody");
  const r = await cApi("chat.php", { action: "console" });
  if (!r.ok) {
    AC.deny = r;
    if (!silent || !AC.loaded) acDenied(r);
    return;
  }
  AC.deny = null;
  AC.data = r.data;
  AC.me = r.data.me;
  if (!AC.loaded) { AC.loaded = true; }
  acPaint();
  if (AC.ref) { /* keep the open transcript honest */ }
  if (!silent && !AC.ref) {
    const first = (AC.data.state.mine || [])[0] || (AC.data.state.waiting || [])[0];
    if (first && AC.tab === "live" && !AC.autoOpened) { AC.autoOpened = true; acOpen(first.ref); }
  }
}

function acDenied(r) {
  const body = cOne("#acBody");
  if (!body) return;
  if (r.errors && r.errors.requiresAgent) {
    body.innerHTML = `<div class="panel" style="max-width:620px;margin:0 auto;text-align:center">
      <h3 style="font-size:22px">Agent access needed</h3>
      <p class="muted" style="font-size:14.5px;margin:10px 0 16px">You are signed in, but this account is not on the chat desk yet.
      An administrator can add you from <b>Admin → Live chat</b>.</p>
      <div class="btnrow" style="justify-content:center">
        <a class="btn btn-gold" href="${cUrl("/admin?tab=chat")}">Open the back office</a>
        <a class="btn btn-ghost" href="${cUrl("/messages")}">Go to messages</a>
      </div></div>`;
  } else {
    body.innerHTML = `<div class="panel" style="max-width:620px;margin:0 auto;text-align:center">
      <h3 style="font-size:22px">Sign in to work the desk</h3>
      <p class="muted" style="font-size:14.5px;margin:10px 0 16px">${cEsc(r.message || "Please sign in with your agent account.")}</p>
      <a class="btn btn-gold" href="${cUrl("/auth?next=/agent")}">Sign in</a></div>`;
  }
}

function acPaint() {
  const body = cOne("#acBody");
  if (!body || !AC.data) return;
  const st = AC.data;
  const k = st.metrics || {};
  const kp = cOne("#acKpis");
  if (kp) {
    const parts = [
      ["Queued", k.queued || 0], ["Live", k.active || 0], ["Agents online", k.online || 0],
      ["Chats today", k.today || 0], ["Avg first reply", k.avgFirst || "now"],
    ];
    if (k.rated) parts.push(["Rating", (k.rating || 0) + "★"]);
    kp.innerHTML = parts.map(([l, v]) => `<span class="badge">${cEsc(l)} <b>${cEsc(String(v))}</b></span>`).join("");
  }
  const me = AC.me || {};
  const statuses = [["online", "Online"], ["away", "Away"], ["busy", "Busy"], ["offline", "Offline"]];
  const tabs = [["live", "Live chats"], ["disputes", "Disputes"], ["agents", "Agents"], ["queues", "Queues"], ["replies", "Saved replies"], ["settings", "Settings"]];
  body.innerHTML = `
    <div class="ac-top">
      <div class="ac-me">
        <span class="ac-dot ${cEsc(me.status || "offline")}"></span>
        <b>${cEsc(me.name || "Administrator")}</b>
        <span class="muted small">${me.id ? cEsc((me.role || "agent") + " · " + (me.load || 0) + "/" + (me.max || 0) + " chats") : "not on the agent roster"}</span>
        ${me.id ? `<span class="cw-status">${cEsc((me.coversAll ? "All queues" : (me.queues || []).length + " queues"))}</span>` : ""}
      </div>
      <div class="ac-status">
        ${statuses.map(([s, l]) => `<button class="btn btn-ghost btn-sm ${me.status === s ? "on" : ""}" ${me.id ? `onclick="acSetStatus('${s}')"` : "disabled"}>${l}</button>`).join("")}
      </div>
    </div>
    <div class="tabs ac-tabs">
      ${tabs.map(([k2, l]) => `<button class="tab ${AC.tab === k2 ? "active" : ""}" onclick="acTab('${k2}')">${l}</button>`).join("")}
    </div>
    <div id="acView">${acViewHTML()}</div>`;
  acWireView();
}

function acTab(t) {
  AC.tab = t;
  if (t === "disputes") { acLoadDisputes(); return; }
  acPaint();
}

function acViewHTML() {
  if (AC.tab === "agents") return acAgentsHTML();
  if (AC.tab === "queues") return acQueuesHTML();
  if (AC.tab === "replies") return acCannedHTML();
  if (AC.tab === "settings") return acSettingsHTML();
  if (AC.tab === "disputes") return acDisputesHTML();
  return acLiveHTML();
}

function acLiveHTML() {
  const st = AC.data.state || {};
  const list = AC.list;
  const sessions = list === "waiting" ? (st.waiting || []) : list === "mine" ? (st.mine || []) : list === "team" ? (st.team || []) : (st.recent || []);
  const counts = { waiting: (st.waiting || []).length, mine: (st.mine || []).length, team: (st.team || []).length, recent: (st.recent || []).length };
  return `<div class="chat-page ac-desk">
    <aside class="conv-list">
      <div class="ac-filters">
        ${[["waiting", "Waiting"], ["mine", "Mine"], ["team", "Team"], ["recent", "Recent"]].map(([k, l]) =>
    `<button class="ac-filter ${list === k ? "on" : ""}" onclick="acList('${k}')">${l} <b>${counts[k]}</b></button>`).join("")}
      </div>
      <div class="ac-chats">
        ${sessions.length ? sessions.map((s) => `
          <div class="conv ac-chat ${AC.ref === s.ref ? "active" : ""}" onclick="acOpen('${cEsc(s.ref)}')">
            <span class="avatar ivory">${cEsc((s.visitor || "G")[0].toUpperCase())}</span>
            <div class="inf">
              <div class="nm">${cEsc(s.visitor)} ${s.unread ? `<span class="badge warn">${s.unread}</span>` : ""}</div>
              <div class="last">${cEsc(s.subject || s.dept || "")}${s.agent ? " · " + cEsc(s.agent) : ""}</div>
            </div>
            <span class="t">${cEsc(s.status === "queued" ? s.wait : s.openedAgo)}</span>
          </div>`).join("") : `<div class="empty-state" style="padding:26px 12px"><b>Nothing here</b>${list === "waiting" ? "No one is waiting — nice." : "Chats will appear here as they arrive."}</div>`}
      </div>
    </aside>
    <div class="chat-main" id="acChat">${acChatHTML()}</div>
  </div>`;
}

function acChatHTML() {
  const st = AC.data.state || {};
  const me = AC.me || {};
  if (!AC.ref || !AC.session) {
    const deps = (st.departments || []).map((d) => `<div class="krow"><span class="k">${cEsc(d.name)}</span><span class="v small">${(d.agents || 0)} agents · ${(d.open_chats || d.open || 0)} open</span></div>`).join("");
    return `<div class="chat-head"><div><div class="nm">Choose a conversation</div><div class="st">Waiting chats appear first</div></div></div>
      <div class="chat-body"><div class="empty-state">${typeof I !== "undefined" && I.chat ? I.chat : ""}<b>No chat open</b>Pick a visitor on the left, or wait for the next request to land.</div>
      <div class="panel" style="margin-top:14px"><h4 style="font-size:16px;margin-bottom:8px">Queues</h4>${deps || "<p class='small'>No queues configured.</p>"}
      ${(st.agents || []).length ? `<h4 style="font-size:16px;margin:14px 0 8px">Agents on duty</h4>` +
        st.agents.map((a) => `<div class="krow"><span class="k"><span class="ac-dot ${cEsc(a.status)}"></span> ${cEsc(a.name)}</span><span class="v small">${cEsc(a.statusLabel || a.status)} · ${a.load}/${a.max}</span></div>`).join("") : ""}
      </div></div>`;
  }
  const s = AC.session;
  const msgs = AC.messages || [];
  let lastDay = "";
  const rows = msgs.map((m) => {
    const day = m.day || "";
    const head = day && day !== lastDay ? (lastDay = day, `<div class="day">${cEsc(day)}</div>`) : "";
    if (m.from === "system") return head + `<div class="cw-sys">${cEsc(m.body)}</div>`;
    if (m.from === "note") return head + `<div class="msg note">${cEsc(m.body)}<span class="mtime">${cEsc(m.time)} · internal note</span></div>`;
    const mine = m.from === "agent";
    return head + `<div class="msg ${mine ? "me" : "bot"}">${cEsc(m.body).replace(/\n/g, "<br>")}
      <span class="mtime">${cEsc(m.name ? m.name + " · " : "")}${cEsc(m.time)}</span></div>`;
  }).join("");
  const canReply = s.status !== "closed";
  const mineTheirs = !s.agentId || s.agentId === (me.id || 0) || (AC.data.state.isSupervisor);
  const typing = s.status === "active" && AC.visitorTyping ? `<div class="msg bot cw-typing"><span class="typing"><i></i><i></i><i></i></span></div>` : "";
  return `<div class="chat-head">
      <span class="avatar ivory">${cEsc((s.visitor || "G")[0].toUpperCase())}</span>
      <div><div class="nm">${cEsc(s.visitor)} <span class="pill-status ${s.status === "queued" ? "info" : s.status === "closed" ? "gold" : "ok"}">${cEsc(s.status)}</span></div>
        <div class="st">${cEsc(s.subject || "Live chat")} · ${cEsc(s.dept || "")} ${s.email ? "· " + cEsc(s.email) : ""}</div></div>
      <div class="btnrow" style="margin-left:auto">
        ${s.status === "queued" ? `<button class="btn btn-gold btn-sm" onclick="acClaim()">Claim chat</button>` : ""}
        ${s.status === "active" && !s.agentId ? `<button class="btn btn-gold btn-sm" onclick="acClaim()">Take over</button>` : ""}
        ${AC.data.state.isSupervisor && s.status !== "closed" ? `<button class="btn btn-ghost btn-sm" onclick="acTransferBox()">Transfer</button>` : ""}
        ${s.status !== "closed" ? `<button class="btn btn-ghost btn-sm" onclick="acCloseChat()">Close</button>` : ""}
      </div>
    </div>
    <div class="chat-body" id="acScroll">${rows}${typing}${msgs.length ? "" : `<div class="cw-wait">No messages yet.</div>`}</div>
    ${(AC.visitor && AC.visitor.bookings && AC.visitor.bookings.length) || (AC.visitor && AC.visitor.disputes && AC.visitor.disputes.length) ? `<div class="ac-dossier">
      ${(AC.visitor.bookings || []).map((b) => `<span class="badge">Booking ${cEsc(b.ref)} · ${cEsc(b.status)}</span>`).join("")}
      ${(AC.visitor.disputes || []).map((d) => `<span class="badge warn">Dispute ${cEsc(d.ref)} · ${cEsc(d.status)}</span>`).join("")}
    </div>` : ""}
    ${canReply ? `<div class="quick-replies" id="acCanned">${(AC.data.state.canned || []).slice(0, 6).map((c) =>
      `<button type="button" onclick="acUseCanned(${c.id})" title="${cEsc(c.body)}">${cEsc(c.shortcut || c.title)}</button>`).join("")}
      <button type="button" class="ac-more" onclick="acCannedBox()">more…</button></div>
      <div class="chat-foot">
        <textarea class="inp ac-input" id="acInp" rows="1" placeholder="Reply to ${cEsc(s.visitor)}… (Ctrl+Enter to send)"></textarea>
        <button class="btn btn-ghost btn-sm" onclick="acSend(true)" title="Internal note — the visitor never sees this">Note</button>
        <button class="btn btn-gold" id="acSend" onclick="acSend(false)">${typeof I !== "undefined" && I.send ? I.send : "Send"}</button>
      </div>` : `<div class="chat-foot"><span class="muted small">This chat is closed. Start a new one from the widget if the visitor returns.</span></div>`}`;
}

function acWireView() {
  const inp = cOne("#acInp");
  if (inp) inp.addEventListener("keydown", (e) => { if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); acSend(false); } });
  const sc = cOne("#acScroll");
  if (sc) sc.scrollTop = sc.scrollHeight;
}

function acList(l) { AC.list = l; acPaint(); }

async function acOpen(ref) {
  const r = await cApi("chat.php", { action: "open", ref });
  if (!r.ok) { cToast(r.message || "Could not open that chat.", "x"); return; }
  AC.ref = ref;
  AC.session = Object.assign({}, r.data.session, { agentId: r.data.session.agentId });
  AC.messages = r.data.messages || [];
  AC.visitor = r.data.visitor || null;
  AC.events = r.data.events || [];
  AC.lastId = AC.messages.reduce((a, m) => Math.max(a, m.id), 0);
  if (AC.tab !== "live") AC.tab = "live";
  acPaint();
}

async function acPollChat() {
  if (!AC.ref) return;
  const r = await cApi("chat.php", { action: "poll", ref: AC.ref, after: AC.lastId, as: "agent" });
  if (!r.ok) return;
  const d = r.data || {};
  if (d.visitor) AC.visitor = d.visitor;
  if (d.visitorTyping !== undefined) AC.visitorTyping = d.visitorTyping;
  if (d.status && AC.session) AC.session.status = d.status;
  if (d.agent) AC.session.agent = d.agent.name;
  if (d.agent && AC.session) AC.session.agentId = d.agent.id;
  const incoming = d.messages || [];
  if (incoming.length) {
    AC.messages = AC.messages.concat(incoming);
    AC.lastId = incoming.reduce((a, m) => Math.max(a, m.id), AC.lastId);
    if (AC.tab === "live") { acPaint(); return; }
  }
  if (AC.tab === "live" && (d.visitorTyping !== undefined)) acPaint();
}

async function acSend(note) {
  const inp = cOne("#acInp");
  const txt = (inp && inp.value || "").trim();
  if (!txt || !AC.ref || AC.busy) return;
  AC.busy = true;
  inp.value = "";
  const r = await cApi("chat.php", note ? { action: "note", ref: AC.ref, body: txt } : { action: "post", ref: AC.ref, body: txt, as: "agent" });
  AC.busy = false;
  if (!r.ok) { cToast(r.message || "Message not sent.", "x"); inp.value = txt; return; }
  acPollChat();
  acRefresh(true);
}

async function acClaim() {
  const r = await cApi("chat.php", { action: "claim", ref: AC.ref });
  cToast(r.ok ? (r.message || "Chat claimed.") : (r.message || "Could not claim."), r.ok ? "check" : "x");
  if (r.ok) { acRefresh(true); setTimeout(() => acOpen(AC.ref), 200); }
}

function acTransferBox() {
  const agents = (AC.data.agents || AC.data.state.agents || []).filter((a) => a.active && a.id !== (AC.me ? AC.me.id : 0));
  openModal(`<h3 style="font-size:20px;margin-bottom:10px">Transfer this chat</h3>
    ${agents.length ? `<div class="ac-pick">${agents.map((a) => `<button class="cw-dep" onclick="acTransfer(${a.id})">
      <b>${cEsc(a.name)}</b><span>${cEsc(a.statusLabel || a.status)} · ${a.load}/${a.max} chats</span></button>`).join("")}</div>`
      : `<p class="muted small">There are no other agents to transfer to yet.</p>`}
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button></div>`);
}

async function acTransfer(agentId) {
  closeModal();
  const r = await cApi("chat.php", { action: "transfer", ref: AC.ref, agent: agentId });
  cToast(r.ok ? (r.message || "Transferred.") : (r.message || "Could not transfer."), r.ok ? "check" : "x");
  if (r.ok) { AC.ref = null; AC.session = null; acRefresh(true); }
}

function acCloseChat() {
  openModal(`<h3 style="font-size:20px;margin-bottom:8px">Close this chat?</h3>
    <div class="frm-row"><label>What was the outcome? (optional)</label><input class="inp" id="acCloseNote" placeholder="e.g. late check-out arranged"></div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
    <button class="btn btn-gold" id="acCloseYes">Close chat</button></div>`);
  const y = cOne("#acCloseYes");
  if (y) y.addEventListener("click", async () => {
    const note = cOne("#acCloseNote") ? cOne("#acCloseNote").value : "";
    closeModal();
    const r = await cApi("chat.php", { action: "close", ref: AC.ref, as: "agent", note });
    cToast(r.ok ? (r.message || "Chat closed.") : (r.message || "Could not close."), r.ok ? "check" : "x");
    if (r.ok) { AC.ref = null; AC.session = null; AC.messages = []; acRefresh(); }
  });
}

async function acUseCanned(id) {
  if (!AC.ref) return;
  const r = await cApi("chat.php", { action: "use-canned", ref: AC.ref, id });
  if (!r.ok) { cToast(r.message || "Could not send that reply.", "x"); return; }
  acPollChat();
}

function acCannedBox() {
  const list = (AC.data.state && AC.data.state.canned) || [];
  openModal(`<h3 style="font-size:20px;margin-bottom:10px">Saved replies</h3>
    <div class="ac-pick">${list.map((c) => `<button class="cw-dep" onclick="acUseCannedFromBox(${c.id})">
      <b>${cEsc(c.title)}</b><span>${cEsc((c.body || "").slice(0, 90))}</span></button>`).join("")}</div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="closeModal()">Close</button></div>`);
}
function acUseCannedFromBox(id) { closeModal(); acUseCanned(id); }

async function acSetStatus(status) {
  const r = await cApi("chat.php", { action: "status", status });
  cToast(r.ok ? (r.message || "Status updated.") : (r.message || "Could not update your status."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

/* ---------------- console: administration panels ---------------- */
function acAgentsHTML() {
  const list = AC.data.agents || [];
  const isSup = AC.data.state.isSupervisor;
  return `<div class="panel">
    <div class="ac-panel-head"><h3 style="font-size:19px">Agents <span class="badge">${list.length}</span></h3>
      ${isSup ? `<button class="btn btn-gold btn-sm" onclick="acAgentForm()">Add an agent</button>` : ""}</div>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl">
      <thead><tr><th>Agent</th><th>Role</th><th>Queues</th><th>Status</th><th>Load</th><th></th></tr></thead>
      <tbody>${list.length ? list.map((a) => `<tr>
        <td><b class="strong">${cEsc(a.name)}</b><div class="sub">${cEsc(a.email)}</div></td>
        <td>${cEsc(a.role)}<div class="sub">${a.userId ? "signs in as #" + a.userId : "console only"}</div></td>
        <td>${cEsc(a.coversAll ? "All queues" : (a.queues || []).length + " assigned")}</td>
        <td><span class="pill-status ${a.active ? "ok" : "warn"}">${cEsc(a.statusLabel || a.status)}</span></td>
        <td>${a.load}/${a.max}<div class="sub">${a.handled || 0} handled</div></td>
        <td><div class="btnrow">
          ${isSup ? `<button class="btn btn-ghost btn-sm" onclick="acAgentForm(${a.id})">Edit</button>
          <button class="btn btn-ghost btn-sm" onclick="acToggleAgent(${a.id}, ${a.active ? "false" : "true"})">${a.active ? "Pause" : "Resume"}</button>
          <button class="btn btn-ghost btn-sm" onclick="acDeleteAgent(${a.id}, '${cEsc(a.name)}')">Delete</button>` : ""}
        </div></td></tr>`).join("") : `<tr><td colspan="6"><div class="empty-state"><b>No agents yet</b>Add the first agent and chats will start routing to them.</div></td></tr>`}</tbody>
    </table></div></div>`;
}

function acAgentForm(id) {
  const a = id ? (AC.data.agents || []).find((x) => x.id === id) : null;
  const deps = AC.data.departments || (AC.data.state && AC.data.state.departments) || [];
  openModal(`<h3 style="font-size:21px;margin-bottom:4px">${a ? "Edit agent" : "Add an agent"}</h3>
    <p class="muted small" style="margin-bottom:12px">Agents sign in with their email address and work the queues you assign here.</p>
    <div class="frm-grid">
      <div class="frm-row"><label>Name</label><input class="inp" id="agName" value="${cEsc(a ? a.name : "")}" placeholder="Nia Okafor"></div>
      <div class="frm-row"><label>Email</label><input class="inp" id="agEmail" type="email" value="${cEsc(a ? a.email : "")}" placeholder="nia@jollofliving.com"></div>
    </div>
    <div class="frm-grid">
      <div class="frm-row"><label>Role</label><select class="sel" id="agRole">
        ${["agent", "supervisor", "admin"].map((r) => `<option value="${r}" ${a && a.role === r ? "selected" : ""}>${cEsc(r[0].toUpperCase() + r.slice(1))}</option>`).join("")}
      </select></div>
      <div class="frm-row"><label>Status</label><select class="sel" id="agStatus">
        ${["online", "away", "busy", "offline"].map((r) => `<option value="${r}" ${a && a.status === r ? "selected" : ""}>${cEsc(r[0].toUpperCase() + r.slice(1))}</option>`).join("")}
      </select></div>
      <div class="frm-row"><label>Concurrent chats</label><input class="inp" id="agMax" type="number" min="1" max="20" value="${a ? a.max : 3}"></div>
    </div>
    <div class="frm-row"><label>Queues</label>
      <div class="ac-queues">${deps.map((d) => `<label class="chk"><input type="checkbox" class="agQ" value="${d.id}" ${a && (a.queues || []).includes(d.id) ? "checked" : ""}> ${cEsc(d.name)}${d.hours ? ` <span class="sub">${cEsc(d.hours)}</span>` : ""}</label>`).join("") || "<span class='small'>No queues yet — create one first.</span>"}</div></div>
    <div class="frm-row"><label>Signature (shown to visitors)</label><input class="inp" id="agSig" value="${cEsc(a ? a.signature : "")}" placeholder="Jollof Living support"></div>
    ${a ? "" : `<div class="frm-row"><label>Temporary password (optional — creates a sign-in account)</label><input class="inp" id="agPass" type="text" placeholder="At least 8 characters"></div>`}
    <label class="chk" style="margin:8px 0"><input type="checkbox" id="agActive" ${!a || a.active ? "checked" : ""}> Agent is active and can take chats</label>
    <div class="btnrow" style="margin-top:12px">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-gold" onclick="acSaveAgent(${a ? a.id : 0})">${a ? "Save changes" : "Create agent"}</button>
    </div>`);
}

async function acSaveAgent(id) {
  const queues = cMany("#modalBody .agQ").filter((c) => c.checked).map((c) => parseInt(c.value, 10));
  const payload = {
    action: "save-agent",
    id: id || undefined,
    name: cOne("#agName").value,
    email: cOne("#agEmail").value,
    role: cOne("#agRole").value,
    status: cOne("#agStatus").value,
    max: parseInt(cOne("#agMax").value, 10) || 3,
    signature: cOne("#agSig").value,
    queues: queues,
    department: queues[0] || 0,
    active: cOne("#agActive").checked,
  };
  const pass = cOne("#agPass");
  if (pass && pass.value) payload.password = pass.value;
  const r = await cApi("chat.php", payload);
  if (!r.ok) { cToast(r.message || "Could not save the agent.", "x"); return; }
  closeModal();
  cToast(r.message || "Agent saved.", "check");
  acRefresh(true);
}

async function acToggleAgent(id, active) {
  const r = await cApi("chat.php", { action: "toggle-agent", id, active });
  cToast(r.ok ? (r.message || "Updated.") : (r.message || "Could not update."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

function acDeleteAgent(id, name) {
  openModal(`<h3 style="font-size:20px;margin-bottom:8px">Remove ${cEsc(name)} from the desk?</h3>
    <p class="muted small">Their sign-in account stays; they simply stop receiving chats. Closed chats keep their transcript.</p>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
    <button class="btn btn-gold" onclick="acDeleteAgentYes(${id})">Remove agent</button></div>`);
}
async function acDeleteAgentYes(id) {
  closeModal();
  const r = await cApi("chat.php", { action: "delete-agent", id });
  cToast(r.ok ? (r.message || "Agent removed.") : (r.message || "Could not remove the agent."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

function acQueuesHTML() {
  const deps = AC.data.departments || [];
  return `<div class="panel">
    <div class="ac-panel-head"><h3 style="font-size:19px">Queues</h3>
      <button class="btn btn-gold btn-sm" onclick="acDeptForm()">Add a queue</button></div>
    <p class="muted small" style="margin-bottom:10px">Every queue has its own routing rule and welcome line; visitors pick one in the widget.</p>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Queue</th><th>Routing</th><th>Hours</th><th>Agents</th><th>Open</th><th></th></tr></thead>
      <tbody>${deps.map((d) => `<tr>
        <td><b class="strong">${cEsc(d.name)}</b><div class="sub">${cEsc(d.slug)}</div></td>
        <td>${cEsc(d.routing)}</td><td>${cEsc(d.hours || "—")}</td>
        <td>${d.agents || 0}</td><td>${d.open || 0}</td>
        <td><div class="btnrow"><button class="btn btn-ghost btn-sm" onclick="acDeptForm(${d.id})">Edit</button></div></td></tr>`).join("")}</tbody>
    </table></div></div>`;
}

function acDeptForm(id) {
  const d = id ? (AC.data.departments || []).find((x) => x.id === id) : null;
  openModal(`<h3 style="font-size:21px;margin-bottom:10px">${d ? "Edit queue" : "Add a queue"}</h3>
    <div class="frm-grid">
      <div class="frm-row"><label>Name</label><input class="inp" id="dpName" value="${cEsc(d ? d.name : "")}" placeholder="Reservations desk"></div>
      <div class="frm-row"><label>Slug</label><input class="inp" id="dpSlug" value="${cEsc(d ? d.slug : "")}" placeholder="reservations"></div>
    </div>
    <div class="frm-grid">
      <div class="frm-row"><label>Routing</label><select class="sel" id="dpRouting">
        ${[["fewest", "Fewest open chats"], ["round_robin", "Round robin"], ["manual", "Manual (supervisor assigns)"]].map(([k, l]) => `<option value="${k}" ${d && d.routing === k ? "selected" : ""}>${l}</option>`).join("")}
      </select></div>
      <div class="frm-row"><label>Hours</label><input class="inp" id="dpHours" value="${cEsc(d ? d.hours : "")}" placeholder="08:00 – 22:00 WAT"></div>
    </div>
    <div class="frm-row"><label>Description</label><input class="inp" id="dpDesc" value="${cEsc(d ? d.description : "")}" placeholder="New bookings, modifications and availability."></div>
    <div class="frm-row"><label>Welcome line</label><textarea class="txa" id="dpWelcome" rows="2">${cEsc(d ? d.welcome : "")}</textarea></div>
    <label class="chk" style="margin:8px 0"><input type="checkbox" id="dpActive" ${!d || d.active ? "checked" : ""}> Show this queue in the widget</label>
    <div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-gold" onclick="acSaveDept(${d ? d.id : 0})">${d ? "Save queue" : "Create queue"}</button></div>`);
}

async function acSaveDept(id) {
  const r = await cApi("chat.php", {
    action: "save-department",
    id: id || undefined,
    name: cOne("#dpName").value,
    slug: cOne("#dpSlug").value,
    routing: cOne("#dpRouting").value,
    hours: cOne("#dpHours").value,
    description: cOne("#dpDesc").value,
    welcome: cOne("#dpWelcome").value,
    active: cOne("#dpActive").checked,
  });
  if (!r.ok) { cToast(r.message || "Could not save the queue.", "x"); return; }
  closeModal();
  cToast(r.message || "Queue saved.", "check");
  acRefresh(true);
}

function acCannedHTML() {
  const list = AC.data.canned || [];
  return `<div class="panel">
    <div class="ac-panel-head"><h3 style="font-size:19px">Saved replies</h3>
      <button class="btn btn-gold btn-sm" onclick="acCannedForm()">New reply</button></div>
    <p class="muted small" style="margin-bottom:10px">Type <b>/shortcut</b> in the composer or tap the chips above it. <b>{agent}</b> is replaced with the agent's name.</p>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Reply</th><th>Shortcut</th><th>Scope</th><th>Uses</th><th></th></tr></thead>
      <tbody>${list.map((c) => `<tr>
        <td><b class="strong">${cEsc(c.title)}</b><div class="sub">${cEsc((c.body || "").slice(0, 90))}</div></td>
        <td>${cEsc(c.shortcut || "—")}</td><td>${cEsc(c.dept || c.scope)}</td><td>${c.uses || 0}</td>
        <td><div class="btnrow"><button class="btn btn-ghost btn-sm" onclick="acCannedForm(${c.id})">Edit</button>
          <button class="btn btn-ghost btn-sm" onclick="acDeleteCanned(${c.id})">Delete</button></div></td></tr>`).join("")}</tbody>
    </table></div></div>`;
}

function acCannedForm(id) {
  const c = id ? (AC.data.canned || []).find((x) => x.id === id) : null;
  openModal(`<h3 style="font-size:21px;margin-bottom:10px">${c ? "Edit reply" : "New saved reply"}</h3>
    <div class="frm-grid">
      <div class="frm-row"><label>Title</label><input class="inp" id="cnTitle" value="${cEsc(c ? c.title : "")}" placeholder="Cancellation tiers"></div>
      <div class="frm-row"><label>Shortcut</label><input class="inp" id="cnShort" value="${cEsc(c ? c.shortcut : "")}" placeholder="/cancel"></div>
    </div>
    <div class="frm-row"><label>Body</label><textarea class="txa" id="cnBody" rows="4">${cEsc(c ? c.body : "")}</textarea></div>
    <label class="chk" style="margin:8px 0"><input type="checkbox" id="cnActive" ${!c || c.active ? "checked" : ""}> Active</label>
    <div class="btnrow" style="margin-top:10px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-gold" onclick="acSaveCanned(${c ? c.id : 0})">${c ? "Save reply" : "Create reply"}</button></div>`);
}

async function acSaveCanned(id) {
  const r = await cApi("chat.php", {
    action: "save-canned",
    id: id || undefined,
    title: cOne("#cnTitle").value,
    shortcut: cOne("#cnShort").value,
    body: cOne("#cnBody").value,
    scope: "global",
    active: cOne("#cnActive").checked,
  });
  if (!r.ok) { cToast(r.message || "Could not save the reply.", "x"); return; }
  closeModal();
  cToast(r.message || "Reply saved.", "check");
  acRefresh(true);
}

async function acDeleteCanned(id) {
  const r = await cApi("chat.php", { action: "delete-canned", id });
  cToast(r.ok ? (r.message || "Reply removed.") : (r.message || "Could not remove it."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

function acSettingsHTML() {
  const s = AC.data.settings || {};
  return `<div class="panel" style="max-width:760px">
    <h3 style="font-size:19px">Chat settings</h3>
    <p class="muted small" style="margin-bottom:12px">These control the widget on the public site.</p>
    <label class="chk" style="margin:6px 0"><input type="checkbox" id="csEnabled" ${s.enabled ? "checked" : ""}> Live chat is open</label>
    <label class="chk" style="margin:6px 0"><input type="checkbox" id="csRating" ${s.rating ? "checked" : ""}> Ask visitors to rate a chat</label>
    <label class="chk" style="margin:6px 0"><input type="checkbox" id="csTranscript" ${s.transcript ? "checked" : ""}> Email the transcript when a chat closes</label>
    <div class="frm-row" style="margin-top:10px"><label>Welcome line</label><textarea class="txa" id="csWelcome" rows="2">${cEsc(s.welcome || "")}</textarea></div>
    <div class="frm-row"><label>Offline message</label><textarea class="txa" id="csOffline" rows="2">${cEsc(s.offline || "")}</textarea></div>
    <div class="frm-grid">
      <div class="frm-row"><label>Opening hours label</label><input class="inp" id="csHours" value="${cEsc(s.hours || "")}"></div>
      <div class="frm-row"><label>Default routing</label><select class="sel" id="csRouting">
        ${[["fewest", "Fewest open chats"], ["round_robin", "Round robin"], ["manual", "Manual"]].map(([k, l]) => `<option value="${k}" ${s.routing === k ? "selected" : ""}>${l}</option>`).join("")}
      </select></div>
      <div class="frm-row"><label>Max queued chats</label><input class="inp" id="csMax" type="number" min="1" value="${s.maxQueue || 25}"></div>
      <div class="frm-row"><label>Auto-close idle after (min)</label><input class="inp" id="csAuto" type="number" min="5" value="${s.autoClose || 30}"></div>
    </div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-gold" onclick="acSaveSettings()">Save settings</button>
      <button class="btn btn-ghost" onclick="acSweep()">Tidy stale chats</button></div>
  </div>`;
}

async function acSaveSettings() {
  const r = await cApi("chat.php", {
    action: "save-settings",
    enabled: cOne("#csEnabled").checked,
    rating: cOne("#csRating").checked,
    transcript: cOne("#csTranscript").checked,
    welcome: cOne("#csWelcome").value,
    offline: cOne("#csOffline").value,
    hours: cOne("#csHours").value,
    routing: cOne("#csRouting").value,
    maxQueue: parseInt(cOne("#csMax").value, 10) || 25,
    autoClose: parseInt(cOne("#csAuto").value, 10) || 30,
  });
  cToast(r.ok ? (r.message || "Settings saved.") : (r.message || "Could not save the settings."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

async function acSweep() {
  const r = await cApi("chat.php", { action: "sweep" });
  cToast(r.ok ? (r.message || "Tidied up.") : (r.message || "Could not tidy the desk."), r.ok ? "check" : "x");
  if (r.ok) acRefresh(true);
}

/* ---------------- console: mediation queue ---------------- */
async function acLoadDisputes() {
  acPaint();
  const view = cOne("#acView");
  if (view) view.innerHTML = `<div class="empty-state">Loading the mediation queue…</div>`;
  const r = await cApi("dispute.php", { action: "queue", status: "open" });
  if (r.ok && r.data) AC.cases = r.data;
  AC.tab = "disputes";
  acPaint();
}

function acDisputesHTML() {
  const d = AC.cases;
  if (!d) return `<div class="empty-state">Loading the mediation queue…</div>`;
  const s = d.stats || {};
  const cases = d.cases || [];
  return `<div class="panel">
    <div class="ac-panel-head"><h3 style="font-size:19px">Mediation queue <span class="badge">${cases.length}</span></h3>
      <button class="btn btn-ghost btn-sm" onclick="acLoadDisputes()">Refresh</button></div>
    <div class="ac-kpis">
      <span class="stat-kpi"><span class="lbl">Open</span><b>${s.open || 0}</b></span>
      <span class="stat-kpi"><span class="lbl">Overdue</span><b>${s.overdue || 0}</b></span>
      <span class="stat-kpi"><span class="lbl">Avg decision</span><b>${s.avgHours || 0}h</b></span>
      <span class="stat-kpi"><span class="lbl">On time</span><b>${s.onTime || 100}%</b></span>
    </div>
    <div class="tbl-wrap" style="margin-top:10px"><table class="tbl">
      <thead><tr><th>Case</th><th>Category</th><th>Status</th><th>Mediator</th><th>SLA</th><th></th></tr></thead>
      <tbody>${cases.length ? cases.map((c) => `<tr>
        <td><b class="strong">${cEsc(c.ref)} · ${cEsc(c.subject)}</b><div class="sub">${cEsc(c.claimant)} · ${cEsc(c.email || "no email")}</div></td>
        <td>${cEsc(c.category)}<div class="sub">${cEsc(c.want)}${c.amount ? " · " + cMoney(c.amount, c.currency) : ""}</div></td>
        <td><span class="pill-status ${cEsc(c.level)}">${cEsc(c.label)}</span></td>
        <td>${cEsc(c.mediator || "unassigned")}</td>
        <td class="${c.overdue ? "bad" : ""}">${cEsc(c.slaDue || "—")}</td>
        <td><button class="btn btn-ghost btn-sm" onclick="dcOpenCase('${cEsc(c.ref)}', '', true)">Open case</button></td></tr>`).join("")
      : `<tr><td colspan="6"><div class="empty-state"><b>Nothing open</b>When a guest raises a dispute it lands here first.</div></td></tr>`}</tbody>
    </table></div></div>`;
}

/* ============================================================
   3. DISPUTE RESOLUTION CENTRE
   ============================================================ */
const DC = { cats: null, wants: null, stats: null, mine: [], case: null, role: null, token: "" };

function dcPanel() {
  const st = (JL.data && JL.data.disputeStats) || DC.stats || null;
  const cats = (JL.data && JL.data.disputeCategories) || DC.cats || [];
  DC.cats = cats;
  DC.wants = (JL.data && JL.data.disputeWants) || DC.wants;
  DC.stats = st;
  const k = st ? `<div class="ac-kpis">
      <span class="stat-kpi"><span class="lbl">Open cases</span><b>${st.open || 0}</b></span>
      <span class="stat-kpi"><span class="lbl">Resolved</span><b>${st.resolved || 0}</b></span>
      <span class="stat-kpi"><span class="lbl">Avg decision</span><b>${st.avgHours || 0}h</b></span>
      <span class="stat-kpi"><span class="lbl">On time</span><b>${st.onTime || 100}%</b></span>
    </div>` : "";
  return `<div class="panel" id="dcPanel">
    <h3 style="font-size:20px">${typeof I !== "undefined" && I.scale ? I.scale : ""} Dispute resolution</h3>
    <p class="muted" style="font-size:14px;margin-bottom:8px">Structured, fair and fast — your case is logged, a named mediator is assigned, and the outcome is on the record.</p>
    ${[[1, "Raise a dispute", "Pick a category — each has its own service level."], [2, "Both sides share evidence", "Photos, messages, receipts, on the case file."], [3, "A mediator decides", "Refunds, rebooking, repairs or a warning — recorded in the booking."]].map((s) => `<div class="krow"><span class="k"><b class="gold-text">${s[0]}</b> ${s[1]}</span><span class="v small">${s[2]}</span></div>`).join("")}
    ${k}
    <div class="btnrow" style="margin-top:12px">
      <button class="btn btn-gold btn-sm" onclick="dcWizard()">File a dispute</button>
      <button class="btn btn-ghost btn-sm" onclick="dcTrack()">Track a case</button>
      ${JL.isAdmin ? `<a class="btn btn-ghost btn-sm" href="${cUrl("/admin?tab=disputes")}">Mediation queue</a>` : ""}
    </div>
    <div id="dcMine" style="margin-top:10px"></div>
  </div>`;
}

function dcMineRender() {
  const box = cOne("#dcMine");
  if (!box) return;
  const mine = (JL.data && JL.data.disputes) || DC.mine || [];
  const saved = Object.keys(cGet("dc_cases", {}) || {});
  if (!JL.user && !saved.length) { box.innerHTML = ""; return; }
  const rows = mine.map((c) => `<div class="krow"><span class="k"><b>${cEsc(c.ref)}</b> ${cEsc(c.subject)}<div class="sub">${cEsc(c.category)} · ${cEsc(c.openedAgo)}</div></span>
      <span class="v"><span class="pill-status ${cEsc(c.level)}">${cEsc(c.label)}</span> <button class="btn btn-ghost btn-sm" onclick="dcOpenCase('${cEsc(c.ref)}', dcTokenFor('${cEsc(c.ref)}'))">Open</button></span></div>`).join("");
  const guestRows = saved.filter((r) => !mine.some((m) => m.ref === r)).map((r) => `<div class="krow"><span class="k"><b>${cEsc(r)}</b><div class="sub">guest case on this device</div></span>
      <span class="v"><button class="btn btn-ghost btn-sm" onclick="dcOpenCase('${cEsc(r)}', dcTokenFor('${cEsc(r)}'))">Open</button></span></div>`).join("");
  box.innerHTML = (rows || guestRows) ? `<h4 style="font-size:16px;margin:6px 0 4px">${JL.user ? "Your cases" : "Cases on this device"}</h4>${rows}${guestRows}` : "";
}

function dcTokenFor(ref) { const m = cGet("dc_cases", {}) || {}; return m[ref] || ""; }
function dcRemember(ref, token) { const m = cGet("dc_cases", {}) || {}; m[ref] = token; cSet("dc_cases", m); }

function bindDisputes() {
  if (!DC.cats || !DC.cats.length) {
    cApi("dispute.php", { action: "categories" }).then((r) => {
      if (r.ok && r.data) { DC.cats = r.data.categories || []; DC.wants = r.data.wants || DC.wants; DC.stats = r.data.stats || DC.stats; dcMineRender(); }
    });
  }
  dcMineRender();
  const q = (typeof qps === "function" ? qps() : {});
  if (q && q.dispute) dcOpenCase(q.dispute, dcTokenFor(q.dispute));
}

/* ---------------- wizard ---------------- */
function dcWizard() {
  const cats = DC.cats || [];
  const wants = DC.wants || { refund: "A refund", partial: "A partial refund", rebooking: "To be rebooked elsewhere", repair: "The issue put right", apology: "An apology and a warning", other: "Something else" };
  const bookings = (S && S.bookings) || [];
  openModal(`<h3 style="font-size:22px;margin-bottom:2px">File a dispute</h3>
    <p class="muted small" style="margin-bottom:14px">Your case is logged with a reference, and a mediator responds within the service level shown. No account needed.</p>
    <div class="frm-row"><label>What went wrong?</label>
      <div class="ac-queues" id="dwCats">${cats.map((c, i) => `<label class="chk"><input type="radio" name="dwCat" value="${c.id}" ${i === 0 ? "checked" : ""} data-sla="${c.sla}"> ${cEsc(c.name)} <span class="sub">SLA ${c.sla}h</span></label>`).join("") || "<span class='small'>Categories could not be loaded.</span>"}</div></div>
    <div class="frm-grid">
      ${bookings.length ? `<div class="frm-row"><label>Reservation (optional)</label><select class="sel" id="dwBooking">
        <option value="">Not about a specific stay</option>
        ${bookings.map((b) => `<option value="${cEsc(b.ref)}">${cEsc(b.ref)} · ${cEsc(b.property || "")}</option>`).join("")}
      </select></div>` : `<div class="frm-row"><label>Reservation reference (optional)</label><input class="inp" id="dwBookingRef" placeholder="JL-2026-1042"></div>`}
      <div class="frm-row"><label>What would resolve it?</label><select class="sel" id="dwWant">
        ${Object.keys(wants).map((k) => `<option value="${k}">${cEsc(wants[k])}</option>`).join("")}
      </select></div>
    </div>
    <div class="frm-row"><label>Subject</label><input class="inp" id="dwSubject" maxlength="160" placeholder="The pool was closed for the whole stay"></div>
    <div class="frm-row"><label>What happened?</label><textarea class="txa" id="dwBody" rows="5" placeholder="Dates, what you expected, what you found, anything you already tried."></textarea></div>
    <div class="frm-grid">
      <div class="frm-row"><label>Amount in dispute (optional)</label><input class="inp" id="dwAmount" type="number" min="0" step="1000" placeholder="120000"></div>
      <div class="frm-row"><label>Who is this about? (optional)</label><input class="inp" id="dwAgainst" placeholder="Host, guest or the platform"></div>
    </div>
    ${JL.user ? "" : `<div class="frm-grid">
      <div class="frm-row"><label>Your name</label><input class="inp" id="dwName" autocomplete="name"></div>
      <div class="frm-row"><label>Your email</label><input class="inp" id="dwEmail" type="email" autocomplete="email"></div>
    </div>`}
    <div class="btnrow" style="margin-top:12px">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-gold" onclick="dcSubmit()">Open the case</button>
    </div>`);
}

async function dcSubmit() {
  const cat = cOne("#modalBody input[name=dwCat]:checked");
  const subject = cOne("#dwSubject").value.trim();
  const body = cOne("#dwBody").value.trim();
  if (!cat) { cToast("Choose the category that fits best.", "scale"); return; }
  if (!subject) { cToast("Give the case a short subject.", "scale"); return; }
  if (body.length < 20) { cToast("Describe what happened — at least 20 characters.", "scale"); return; }
  const bookingEl = cOne("#dwBooking") || cOne("#dwBookingRef");
  const payload = {
    action: "open",
    category: cat.value,
    subject,
    description: body,
    want: cOne("#dwWant").value,
    amount: cOne("#dwAmount").value ? parseInt(cOne("#dwAmount").value, 10) : 0,
    against: cOne("#dwAgainst").value,
    booking: bookingEl ? bookingEl.value : "",
    name: cOne("#dwName") ? cOne("#dwName").value : undefined,
    email: cOne("#dwEmail") ? cOne("#dwEmail").value : undefined,
  };
  const r = await cApi("dispute.php", payload);
  if (!r.ok) { cToast(r.message || "Could not open the dispute.", "x"); return; }
  dcRemember(r.data.ref, r.data.token);
  if (typeof syncState === "function") syncState(false);
  openModal(`<h3 style="font-size:22px;margin-bottom:6px">Case ${cEsc(r.data.ref)} is open</h3>
    <p class="muted" style="font-size:14px;margin-bottom:10px">${cEsc(r.message || "")}</p>
    <div class="cw-note">Keep this reference — it and the private link below are how you follow the case, even without signing in.</div>
    <div class="frm-row" style="margin-top:10px"><label>Your private link</label>
      <input class="inp" readonly value="${cEsc(location.origin + cUrl("/help?dispute=" + r.data.ref))}" onclick="this.select()"></div>
    <div class="btnrow" style="margin-top:12px">
      <button class="btn btn-ghost" onclick="closeModal()">Done</button>
      <button class="btn btn-gold" onclick="dcOpenCase('${cEsc(r.data.ref)}', '${cEsc(r.data.token)}')">Open the case file</button>
    </div>`);
}

function dcTrack() {
  openModal(`<h3 style="font-size:21px;margin-bottom:8px">Track a dispute</h3>
    <p class="muted small" style="margin-bottom:10px">Enter the reference from your confirmation. If you filed without an account, paste the private token too.</p>
    <div class="frm-row"><label>Reference</label><input class="inp" id="dtRef" placeholder="D-2026-1042"></div>
    <div class="frm-row"><label>Private token (guest filings)</label><input class="inp" id="dtToken" placeholder="only needed when you were not signed in"></div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-gold" onclick="dcTrackGo()">Open case</button></div>`);
}

function dcTrackGo() {
  const ref = cOne("#dtRef").value.trim().toUpperCase();
  const token = cOne("#dtToken").value.trim();
  if (!ref) { cToast("Enter the case reference.", "scale"); return; }
  dcOpenCase(ref, token || dcTokenFor(ref));
}

async function dcOpenCase(ref, token, staff) {
  token = token || "";
  const r = await cApi("dispute.php", { action: "view", ref, token: token || undefined });
  if (!r.ok) { cToast(r.message || "Could not open that case.", "x"); return; }
  DC.case = r.data.case;
  DC.role = r.data.role;
  DC.token = token;
  DC.staff = !!(r.data.staff || staff);
  if (DC.token) dcRemember(ref, DC.token);
  const box = cOne("#dcCaseBody");
  const html = dcCaseHTML();
  if (box) { box.innerHTML = html; dcCaseWire(); return; }
  openModal(html, "wide");
  dcCaseWire();
}

function dcCaseHTML() {
  const c = DC.case || {};
  const ev = (c.events || []);
  let lastDay = "";
  const rows = ev.map((e) => {
    const day = (e.created || "").slice(0, 10);
    const head = day && day !== lastDay ? (lastDay = day, `<div class="day">${cEsc(day)}</div>`) : "";
    if (e.kind === "opened") return head + `<div class="cw-sys">${cEsc(e.body)}</div>`;
    const mine = (DC.role === "guest" && e.role === "guest") || (DC.role !== "guest" && e.role === "staff");
    const cls = e.visibility === "staff" ? "note" : (mine ? "me" : "bot");
    return head + `<div class="msg ${cls}">${cEsc(e.body).replace(/\n/g, "<br>")}
      ${e.attachment ? `<div><a class="link-arrow" href="${cEsc(e.attachment)}" target="_blank" rel="noopener">${typeof I !== "undefined" && I.doc ? I.doc : ""} evidence</a></div>` : ""}
      <span class="mtime">${cEsc(e.name || e.role)} · ${cEsc(e.label || "")}${e.visibility === "staff" ? " · internal" : ""}</span></div>`;
  }).join("");
  const canPost = c.canMessage !== false && (DC.staff || DC.role !== null);
  return `<h3 style="font-size:22px;margin-bottom:4px">${cEsc(c.ref)} · ${cEsc(c.subject)}</h3>
    <div class="ac-kpis" style="margin:8px 0">
      <span class="pill-status ${cEsc(c.level)}">${cEsc(c.label)}</span>
      <span class="badge">${cEsc(c.category)}</span>
      <span class="badge">${cEsc(c.want)}${c.amount ? " · " + cMoney(c.amount, c.currency) : ""}</span>
      ${c.booking ? `<span class="badge">Booking ${cEsc(c.booking)}</span>` : ""}
      ${c.overdue ? `<span class="badge warn">${cEsc(c.slaDue || "overdue")}</span>` : `<span class="badge ok">${cEsc(c.slaDue || "")}</span>`}
      ${c.mediator ? `<span class="badge">Mediator: ${cEsc(c.mediator)}</span>` : ""}
    </div>
    <div class="cw-note">${cEsc(c.hint || "")}</div>
    <div class="chat-body" id="dcScroll" style="max-height:42vh">${rows || `<div class="cw-wait">No messages yet.</div>`}</div>
    ${c.outcome || c.note ? `<div class="panel" style="margin-top:10px"><h4 style="font-size:16px">Decision</h4>
      <div class="krow"><span class="k">${cEsc(c.outcome || "—")}</span><span class="v">${c.refund ? cMoney(c.refund, c.currency) : ""}</span></div>
      <p class="muted small" style="margin-top:6px">${cEsc(c.note || "")}</p></div>` : ""}
    ${DC.staff ? dcStaffActions() : ""}
    ${c.satisfaction ? `<div class="cw-note">Rated ${c.satisfaction}/5</div>` : ""}
    ${canPost ? `<div class="chat-foot" style="margin-top:10px">
        <textarea class="inp ac-input" id="dcInp" rows="1" placeholder="Add a message…"></textarea>
        <button class="btn btn-ghost btn-sm" onclick="dcSend('evidence')">Add evidence</button>
        <button class="btn btn-gold" onclick="dcSend('message')">${typeof I !== "undefined" && I.send ? I.send : "Send"}</button>
      </div>` : `<div class="chat-foot"><span class="muted small">This case is closed.${c.satisfaction ? "" : " You can still rate the outcome below."}</span></div>`}
    <div class="btnrow" style="margin-top:10px">
      ${["resolved", "rejected"].includes(c.status) && !c.satisfaction ? `<button class="btn btn-ghost btn-sm" onclick="dcRate(5)">Rate 5★</button><button class="btn btn-ghost btn-sm" onclick="dcRate(4)">4★</button><button class="btn btn-ghost btn-sm" onclick="dcRate(2)">2★</button>` : ""}
      ${c.canMessage && DC.role === "guest" ? `<button class="btn btn-ghost btn-sm" onclick="dcWithdraw()">Withdraw</button>` : ""}
      <button class="btn btn-ghost btn-sm" onclick="closeModal()">Close</button>
    </div>`;
}

function dcStaffActions() {
  const c = DC.case || {};
  const st = (AC.data && AC.data.settings) || {};
  const statuses = [["under_review", "Under review"], ["evidence", "Evidence needed"], ["mediation", "In mediation"], ["escalated", "Escalate"], ["rejected", "Close · no action"], ["resolved", "Reopen (decision stands)"], ["withdrawn", "Reopen"]];
  return `<div class="panel" style="margin-top:10px"><h4 style="font-size:16px">Mediation tools</h4>
    <div class="btnrow" style="flex-wrap:wrap">
      ${c.status === "submitted" ? `<button class="btn btn-gold btn-sm" onclick="dcStaff('/dispute.php',{action:'claim',ref:DC.case.ref},true)">Claim case</button>` : ""}
      <select class="sel" style="max-width:180px" onchange="if(this.value){dcStaff('/dispute.php',{action:'assign',ref:DC.case.ref,mediator:parseInt(this.value,10)},true);}">
        <option value="">Assign to…</option>
        ${((AC.cases && AC.cases.mediators) || []).map((m) => `<option value="${m.id}">${cEsc(m.name)}</option>`).join("")}
      </select>
      ${statuses.filter(([s]) => s !== "resolved" || ["resolved", "rejected"].includes(c.status)).map(([s, l]) =>
    `<button class="btn btn-ghost btn-sm" onclick="dcStaff('/dispute.php',{action:'status',ref:DC.case.ref,status:'${s}'},true)">${l}</button>`).join("")}
      <button class="btn btn-gold btn-sm" onclick="dcResolveForm()">Record decision</button>
    </div></div>`;
}

function dcResolveForm() {
  const c = DC.case || {};
  const outcomes = (JL.data && JL.data.disputeOutcomes) || {
    full_refund: "Full refund", partial_refund: "Partial refund", rebooking: "Rebooking / credit",
    compensation: "Goodwill compensation", repair: "Host to fix / replace",
    no_action: "No action — evidence does not support it", warning: "Warning issued to the other party", split: "Split decision",
  };
  openModal(`<h3 style="font-size:21px;margin-bottom:8px">Decision on ${cEsc(c.ref)}</h3>
    <div class="frm-row"><label>Outcome</label><select class="sel" id="drOutcome">
      ${Object.keys(outcomes).map((k) => `<option value="${k}">${cEsc(outcomes[k])}</option>`).join("")}
    </select></div>
    <div class="frm-row"><label>Refund amount (${cEsc(c.currency || "NGN")})</label><input class="inp" id="drRefund" type="number" min="0" step="1000" value="${c.amount || 0}"></div>
    <div class="frm-row"><label>Decision note (sent to both sides)</label><textarea class="txa" id="drNote" rows="4" placeholder="What you found and why this outcome is fair."></textarea></div>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="dcOpenCase(DC.case.ref, DC.token, true)">Back to the case</button>
      <button class="btn btn-gold" onclick="dcResolve()">Record decision</button></div>`);
}

async function dcResolve() {
  const outcome = cOne("#drOutcome").value;
  const note = cOne("#drNote").value.trim();
  if (note.length < 5) { cToast("Write the decision note — both sides receive it.", "scale"); return; }
  const r = await cApi("dispute.php", {
    action: "resolve", ref: DC.case.ref, outcome, note,
    refund: parseInt(cOne("#drRefund").value, 10) || 0,
  });
  if (!r.ok) { cToast(r.message || "Could not record the decision.", "x"); return; }
  cToast(r.message || "Decision recorded.", "check");
  dcOpenCase(DC.case.ref, DC.token, true);
  if (AC.tab === "disputes") acLoadDisputes();
}

async function dcStaff(endpoint, payload, staffView) {
  const r = await cApi(endpoint.replace("/", ""), payload);
  if (!r.ok) { cToast(r.message || "That action failed.", "x"); return; }
  cToast(r.message || "Done.", "check");
  if (DC.case) dcOpenCase(DC.case.ref, DC.token, staffView || DC.staff);
  if (AC.tab === "disputes" && AC.data) acLoadDisputes();
}

function dcCaseWire() {
  const sc = cOne("#dcScroll");
  if (sc) sc.scrollTop = sc.scrollHeight;
  const inp = cOne("#dcInp");
  if (inp) inp.addEventListener("keydown", (e) => { if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); dcSend("message"); } });
}

async function dcSend(kind) {
  const inp = cOne("#dcInp");
  const txt = (inp && inp.value || "").trim();
  if (kind === "message" && !txt) { cToast("Write a message first.", "scale"); return; }
  if (kind === "evidence" && !txt) { cToast("Paste a link to the photo, receipt or file.", "scale"); return; }
  const r = await cApi("dispute.php", { action: "post", ref: DC.case.ref, token: DC.token || undefined, body: txt, kind, attachment: kind === "evidence" ? txt : "" });
  if (!r.ok) { cToast(r.message || "Could not add that to the case.", "x"); return; }
  if (inp) inp.value = "";
  DC.case = Object.assign(DC.case, r.data.case || {});
  DC.case.events = r.data.events || DC.case.events;
  const box = cOne("#dcCaseBody");
  if (box) { box.innerHTML = dcCaseHTML(); dcCaseWire(); }
  else { const m = cOne("#modalBody"); if (m) { m.innerHTML = dcCaseHTML(); dcCaseWire(); } }
}

function dcWithdraw() {
  openModal(`<h3 style="font-size:20px;margin-bottom:8px">Withdraw this dispute?</h3>
    <p class="muted small">The case is closed and the other side is told. You can open a new one at any time.</p>
    <div class="btnrow" style="margin-top:12px"><button class="btn btn-ghost" onclick="dcOpenCase(DC.case.ref, DC.token)">Keep it open</button>
      <button class="btn btn-gold" id="dcWithdrawYes">Withdraw</button></div>`);
  cOne("#dcWithdrawYes").addEventListener("click", async () => {
    const r = await cApi("dispute.php", { action: "withdraw", ref: DC.case.ref, token: DC.token || undefined });
    cToast(r.ok ? (r.message || "Withdrawn.") : (r.message || "Could not withdraw the case."), r.ok ? "check" : "x");
    if (r.ok) { closeModal(); dcOpenCase(DC.case.ref, DC.token); }
  });
}

async function dcRate(stars) {
  const r = await cApi("dispute.php", { action: "rate", ref: DC.case.ref, token: DC.token || undefined, stars, comment: "" });
  cToast(r.ok ? (r.message || "Thank you.") : (r.message || "Could not save the rating."), r.ok ? "check" : "x");
  if (r.ok) dcOpenCase(DC.case.ref, DC.token);
}

/* ---------------- back office tabs ---------------- */
/* The admin console renders panels synchronously, so these mount a host
   element straight away and fill it when the fetch lands. */
function admChatHostHTML() {
  if (AC.data) return admChatHTML();
  if (!AC.loading) {
    AC.loading = true;
    cApi("chat.php", { action: "console" }).then((r) => {
      AC.loading = false;
      if (r.ok) { AC.data = r.data; AC.me = r.data.me; AC.loaded = true; }
      const h = cOne("#admChatHost");
      if (h) h.innerHTML = AC.data ? admChatHTML() : admChatDeniedHTML(r);
    });
  }
  return `<div class="empty-state">Loading the chat desk…</div>`;
}

function admChatDeniedHTML(r) {
  return `<div class="panel"><p class="muted small">${cEsc((r && r.message) || "Could not load the chat desk.")}</p>
    <div class="btnrow" style="margin-top:10px"><a class="btn btn-ghost btn-sm" href="${cUrl("/agent")}">Open the chat desk</a></div></div>`;
}

function admChatHTML() {
  const m = AC.data.metrics || {};
  return `<div class="panel" style="margin-bottom:16px"><h3 style="font-size:19px">Live chat overview</h3>
      <div class="ac-kpis" style="margin-top:8px">
        ${[["Queued", m.queued || 0], ["Live now", m.active || 0], ["Agents online", m.online || 0],
    ["Chats today", m.today || 0], ["Avg first reply", m.avgFirst || "now"], ["Rating", (m.rating || 0) + "★"]]
      .map(([l, v]) => `<span class="stat-kpi"><span class="lbl">${cEsc(l)}</span><b>${cEsc(String(v))}</b></span>`).join("")}
      </div>
      <div class="btnrow" style="margin-top:12px">
        <a class="btn btn-gold btn-sm" href="${cUrl("/agent")}">Open the chat desk</a>
        <button class="btn btn-ghost btn-sm" onclick="acTab('settings');acRefresh()">Widget settings</button>
      </div></div>
    ${acAgentsHTML()}
    <div style="height:16px"></div>
    ${acQueuesHTML()}
    <div style="height:16px"></div>
    ${acCannedHTML()}`;
}

function admDisputesHostHTML() {
  if (AC.cases) return acDisputesHTML();
  if (!AC.caseLoading) {
    AC.caseLoading = true;
    cApi("dispute.php", { action: "queue", status: "open" }).then((r) => {
      AC.caseLoading = false;
      if (r.ok && r.data) AC.cases = r.data;
      const h = cOne("#admDisputeHost");
      if (h) h.innerHTML = AC.cases ? acDisputesHTML() : `<div class="panel"><p class="muted small">${cEsc(r.message || "Could not load the mediation queue.")}</p></div>`;
    });
  }
  return `<div class="empty-state">Loading the mediation queue…</div>`;
}

function admDisputeReload() { AC.cases = null; acPaint(); }
