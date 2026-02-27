/*
 * chatbot.js — Karelia Ulkorakennus Oy
 * Gemini AI version (safe fallback included)
 */

(function () {
  console.log('chatbot.js loaded');

  const MAX_MESSAGES = 8;
  const BOT_NAME     = 'Karel-botti';
  const ACCENT       = '#C97D4E';
  const PRIMARY      = '#2E2E3A';
  const API_ENDPOINT = 'chatbot-api.php';
  const STORAGE_KEY  = 'karel_chat_state_v1';

  let open = false;
  let userMsgCount = 0;
  const MAX_CONTEXT_TURNS = 8;
  const transcript = [];
  const conversationHistory = [];

  function pushHistory(role, content) {
    conversationHistory.push({ role, content });
    if (conversationHistory.length > MAX_CONTEXT_TURNS) {
      conversationHistory.splice(0, conversationHistory.length - MAX_CONTEXT_TURNS);
    }
  }

  function resetSessionMemory() {
    conversationHistory.length = 0;
    transcript.length = 0;
    userMsgCount = 0;
    updateCounter();
    saveState();
  }

  // Basic styles to keep widget visible regardless of page CSS
  const style = document.createElement('style');
  style.textContent = `
    #cb-toggle{
      position:fixed;
      right:1.5rem;
      bottom:1.5rem;
      width:56px;
      height:56px;
      border:none;
      border-radius:50%;
      background:${ACCENT};
      color:#fff;
      font-size:1.35rem;
      cursor:pointer;
      z-index:9999;
      box-shadow:0 8px 24px rgba(0,0,0,.25);
      display:flex;
      align-items:center;
      justify-content:center;
      transition:transform .2s, background .2s;
    }
    #cb-toggle:hover{ transform:scale(1.08); background:#A8612C; }
    #cb-toggle.pulse{ animation:cb-pulse 1.6s infinite; }
    @keyframes cb-pulse { 0%{transform:scale(1)} 50%{transform:scale(1.08)} 100%{transform:scale(1)} }
    #cb-toggle svg{ display:block; width:55%; height:55%; }
    #cb-badge{
      position:absolute;
      top:-4px;
      right:-4px;
      width:18px;
      height:18px;
      border-radius:50%;
      background:#e53935;
      color:#fff;
      font-size:.7rem;
      font-weight:700;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    #cb-window{
      position: fixed; bottom: 5rem; right: 1.5rem; z-index: 9998;
      width: 340px; max-width: calc(100vw - 2rem);
      background: #FAF7F2; border-radius: 12px;
      border: 0.5px solid rgba(255,255,255,.7);
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
      display: flex; flex-direction: column;
      overflow: hidden; font-family: 'Segoe UI', Arial, sans-serif;
      transform: scale(.92) translateY(12px); opacity: 0; pointer-events: none;
      transition: transform .22s ease, opacity .22s ease;
    }
    #cb-window.open { transform: scale(1) translateY(0); opacity: 1; pointer-events: all; }
    #cb-header{
      background: ${PRIMARY}; color: #fff;
      padding: .85rem 1rem; display: flex; align-items: center; gap: .65rem;
    }
    #cb-avatar{
      width:34px;
      height:34px;
      border-radius:50%;
      background:${ACCENT};
      display:flex;
      align-items:center;
      justify-content:center;
      flex-shrink:0;
    }
    #cb-avatar svg{
      display:block;
      width:70%;
      height:70%;
      transform:translateY(1px);
      transform-origin:center;
    }
    #cb-header-text{
      flex:1;
    }
    #cb-header-text strong{
      display: block; font-size: .95rem; line-height: 1.1;
    }
    #cb-header-text span{ font-size: .78rem; color: #A8C7A0; }
    #cb-close{
      background: none; border: none; color: #aaa; cursor: pointer;
      font-size: 1.1rem; padding: .2rem; transition: color .2s;
    }
    #cb-close:hover { color: #fff; }
    #cb-messages {
      flex: 1; overflow-y: auto; padding: 1rem;
      display: flex; flex-direction: column; gap: .7rem;
      max-height: min(60vh, 520px); min-height: 240px;
    }
    #cb-quick { padding: .5rem 1rem .75rem; min-height: 0; }
    #cb-counter {
      text-align: right; font-size: .72rem; color: #bbb;
      padding: .2rem 1rem 0; background: #fff;
    }
    #cb-input-row{
      display: flex; gap: .5rem; padding: .75rem 1rem;
      border-top: 1px solid #E2DDD6; background: #fff;
    }
    #cb-input{
      flex: 1; border: 1px solid #E2DDD6; border-radius: 20px;
      padding: .5rem .9rem; font-size: .88rem; font-family: inherit;
      outline: none; background: #FAF7F2; color: ${PRIMARY};
    }
    #cb-input:focus { border-color: ${ACCENT}; }
    #cb-input:disabled { opacity: .5; }
    #cb-send{
      background: ${ACCENT}; color: #fff; border: none;
      border-radius: 50%; width: 36px; height: 36px; cursor: pointer;
      font-size: 1rem; display: flex; align-items: center; justify-content: center;
      transition: background .2s; flex-shrink: 0;
    }
    #cb-send:hover { background: #A8612C; }
    #cb-send:disabled { background: #ccc; cursor: not-allowed; }
  `;
  document.head.appendChild(style);

  // ================= LOCAL FAQ FALLBACK =================
  const KB = [
    { q: 'terassi', a: 'Terassin hinta riippuu koosta ja materiaalista. Lähetä mitat niin laskemme tarjouksen.' },
    { q: 'aidat', a: 'Rakennamme erilaisia aitoja puusta ja metallista. Ota yhteyttä tarjouspyynnöllä.' },
    { q: 'pergola', a: 'Pergolan hinta riippuu rakenteesta ja koosta. Voimme suunnitella sen pihasi mukaan.' },
    { q: 'hinnoittelu', a: 'Hinnoittelu perustuu työn laajuuteen ja materiaaleihin. Pyydä tarjous!' },
  ];

  function getBotReply(userMsg) {
    const low = userMsg.toLowerCase();
    for (const k of KB) {
      if (low.includes(k.q)) return k.a;
    }
    return 'Pahoittelen, en löytänyt tarkkaa vastausta. Ota yhteyttä: 050 123 4567 tai käytä lomaketta.';
  }

  // ================= UI (unchanged logic) =================
  const toggle = document.createElement('button');
  toggle.id = 'cb-toggle';
  const CHAT_ICON = `
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
      <circle cx="8" cy="10.5" r="1.25" fill="currentColor"></circle>
      <circle cx="12" cy="10.5" r="1.25" fill="currentColor"></circle>
      <circle cx="16" cy="10.5" r="1.25" fill="currentColor"></circle>
    </svg>
    <span id="cb-badge" style="display:none">1</span>
  `;
  const CLOSE_ICON = `
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
      <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></line>
      <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></line>
    </svg>
  `;
  toggle.innerHTML = CHAT_ICON;

  const win = document.createElement('div');
  win.id = 'cb-window';
  win.innerHTML = `
    <div id="cb-header">
      <div id="cb-avatar">
        <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <rect x="3" y="4" width="18" height="12" rx="2" fill="#fff" />
          <rect x="7" y="10" width="3" height="2" rx="1" fill="#C97D4E" />
          <rect x="14" y="10" width="3" height="2" rx="1" fill="#C97D4E" />
          <rect x="9" y="13" width="6" height="1.5" rx="0.6" fill="#C97D4E" />
          <circle cx="12" cy="3.5" r="1" fill="#fff" />
        </svg>
      </div>
      <div id="cb-header-text">
        <strong>${BOT_NAME}</strong>
        <span>Vastaa heti</span>
      </div>
      <button id="cb-close">✕</button>
    </div>
    <div id="cb-messages"></div>
    <div id="cb-quick"></div>
    <div id="cb-counter"></div>
    <div id="cb-input-row">
      <input id="cb-input" type="text" placeholder="Kirjoita viesti..." />
      <button id="cb-send">➤</button>
    </div>
  `;

  document.body.appendChild(toggle);
  document.body.appendChild(win);

  const msgs = win.querySelector('#cb-messages');
  const qArea = win.querySelector('#cb-quick');
  const input = win.querySelector('#cb-input');
  const sendBtn = win.querySelector('#cb-send');
  const counter = win.querySelector('#cb-counter');

  function saveState() {
    try {
      const state = {
        open,
        userMsgCount,
        transcript,
        conversationHistory
      };
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (e) {}
  }

  function loadState() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function addMsg(text, who, persist = true) {
    const div = document.createElement('div');
    div.style.margin = '6px 0';
    div.style.textAlign = who === 'user' ? 'right' : 'left';
    let rendered = text;
    if (who === 'bot') {
      rendered = rendered
        .replace(/\b(varauskalenteri|ajanvaraussivulla|ajanvaraussivullamme|ajanvaraus)\b/gi, '<a href="ajanvaraus.html">$1</a>')
        .replace(/\b(yhteydenottolomake|yhteydenottolomakkeella)\b/gi, '<a href="yhteydenotto.html">$1</a>')
        .replace(/\b(ukk|usein kysytyt kysymykset)\b/gi, '<a href="faq.html">$1</a>');
    }
    div.innerHTML = `<span style="background:${who==='user'?ACCENT:'#eee'};color:${who==='user'?'#fff':'#000'};padding:6px 10px;border-radius:12px;display:inline-block;max-width:80%;white-space:normal;overflow-wrap:anywhere;word-break:break-word;">${rendered}</span>`;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    if (persist) {
      transcript.push({ who, text });
      saveState();
    }
  }

  function showTyping() {
    const div = document.createElement('div');
    div.id = 'typing';
    div.textContent = 'Kirjoittaa...';
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function removeTyping() {
    const t = document.getElementById('typing');
    if (t) t.remove();
  }

  function updateCounter() {
    const remaining = Math.max(0, MAX_MESSAGES - userMsgCount);
    counter.textContent = `${remaining} viestiä jäljellä`;
  }

  async function sendMessage(text) {
    if (!text.trim() || userMsgCount >= MAX_MESSAGES) return;

    addMsg(text, 'user');
    pushHistory('user', text);
    input.value = '';
    userMsgCount++;
    updateCounter();
    showTyping();

    const normalized = text.trim().toLowerCase();
    const isConversationEnd = /\b(ei kiitos|ei kiitoksia|ei muuta|ei,? kiitos|mukavaa päivää|hyvää päivänjatkoa|heippa|moikka)\b/i.test(normalized);
    const isThanks = /\b(kiitos|kiitoksia|kiitti|thanks|thx)\b/i.test(normalized);

    if (isConversationEnd) {
      removeTyping();
      const endReply = 'Mukavaa päivää.';
      addMsg(endReply, 'bot');
      resetSessionMemory();
      return;
    }

    if (isThanks) {
      removeTyping();
      const thanksReply = 'Ole hyvä! Autan mielelläni lisää. Onko jotain muuta, missä voin auttaa?';
      addMsg(thanksReply, 'bot');
      resetSessionMemory();
      return;
    }

    try {
      const historyForApi = conversationHistory.slice(-MAX_CONTEXT_TURNS);
      const res = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: text,
          history: historyForApi
        })
      });

      const data = await res.json();
      removeTyping();

      if (data.reply) {
        addMsg(data.reply, 'bot');
        pushHistory('assistant', data.reply);
      } else {
        const fallbackReply = getBotReply(text);
        addMsg(fallbackReply, 'bot');
        pushHistory('assistant', fallbackReply);
      }

    } catch (err) {
      removeTyping();
      const fallbackReply = getBotReply(text);
      addMsg(fallbackReply, 'bot');
      pushHistory('assistant', fallbackReply);
    }

    if (userMsgCount >= MAX_MESSAGES) {
      addMsg('Viestiraja on täynnä tältä istunnolta. Jatka yhteydenottolomakkeella.', 'bot');
      input.disabled = true;
      sendBtn.disabled = true;
      qArea.innerHTML = '';
      saveState();
    }
  }

  toggle.addEventListener('click', () => {
    open = !open;
    win.classList.toggle('open', open);
    toggle.innerHTML = open ? CLOSE_ICON : CHAT_ICON;
    if (open) {
      toggle.classList.remove('pulse');
    } else {
      toggle.classList.add('pulse');
    }
    if (open && msgs.children.length === 0) {
      addMsg('Hei! 👋 Miten voin auttaa?', 'bot');
      qArea.innerHTML = '';
      updateCounter();
    }
    saveState();
  });

  win.querySelector('#cb-close').addEventListener('click', () => {
    open = false;
    win.classList.remove('open');
    toggle.innerHTML = CHAT_ICON;
    toggle.classList.add('pulse');
    saveState();
  });

  sendBtn.addEventListener('click', () => sendMessage(input.value));
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter') sendMessage(input.value);
  });

  setTimeout(() => {
    if (!open) {
      const b = toggle.querySelector('#cb-badge') || toggle.querySelector('span');
      if (b) {
        b.style.display = 'flex';
        b.textContent = '1';
      }
      toggle.classList.add('pulse');
    }
  }, 4000);

  function clearStateOnReload() {
    try {
      const nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
      const isReload = nav ? nav.type === 'reload' : performance.navigation && performance.navigation.type === 1;
      if (isReload) {
        sessionStorage.removeItem(STORAGE_KEY);
      }
    } catch (e) {}
  }

  // Restore session state across page navigations
  (function restoreState() {
    clearStateOnReload();
    const state = loadState();
    if (!state) return;

    if (Array.isArray(state.transcript)) {
      state.transcript.forEach(m => {
        if (!m || typeof m.text !== 'string' || (m.who !== 'user' && m.who !== 'bot')) return;
        transcript.push({ who: m.who, text: m.text });
        addMsg(m.text, m.who, false);
      });
    }

    if (Array.isArray(state.conversationHistory)) {
      state.conversationHistory.forEach(m => {
        if (!m || (m.role !== 'user' && m.role !== 'assistant') || typeof m.content !== 'string') return;
        conversationHistory.push({ role: m.role, content: m.content });
      });
    }

    if (Number.isInteger(state.userMsgCount) && state.userMsgCount >= 0) {
      userMsgCount = state.userMsgCount;
    }

    updateCounter();
    showQuick();

    if (userMsgCount >= MAX_MESSAGES) {
      input.disabled = true;
      sendBtn.disabled = true;
    }

    if (state.open) {
      open = true;
      win.classList.add('open');
      toggle.innerHTML = CLOSE_ICON;
      toggle.classList.remove('pulse');
    }
  })();

})();
