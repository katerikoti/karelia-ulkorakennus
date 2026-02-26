/*
 * chatbot.js — Karelia Ulkorakennus Oy
 * Real AI chatbot powered by Google Gemini via chatbot-api.php
 * API key is safely stored in chatbot-api.php — never exposed here.
 */

(function () {

  // ── CONFIG ────────────────────────────────────────────────────────────────
  const MAX_MESSAGES = 10;
  const BOT_NAME     = 'Karel-botti';
  const ACCENT       = '#C97D4E';
  const PRIMARY      = '#2E2E3A';
  const API_ENDPOINT = 'chatbot-api.php';
  // ─────────────────────────────────────────────────────────────────────────

  let open = false;
  let userMsgCount = 0;

  // ── Styles ────────────────────────────────────────────────────────────────
  const style = document.createElement('style');
  style.textContent = `
    #cb-toggle {
      position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
      width: 56px; height: 56px; border-radius: 50%;
      background: ${ACCENT}; color: #fff; border: none; cursor: pointer;
      box-shadow: 0 4px 16px rgba(0,0,0,.2);
      font-size: 1.4rem; display: flex; align-items: center; justify-content: center;
      transition: transform .2s, background .2s;
    }
    #cb-toggle:hover { transform: scale(1.08); background: #A8612C; }
    /* pulse attention */
    #cb-toggle.pulse { animation: cb-pulse 1.6s infinite; }
    @keyframes cb-pulse { 0%{transform:scale(1)} 50%{transform:scale(1.08)} 100%{transform:scale(1)} }
    /* ensure svg icon is centered and fits the circular button */
    #cb-toggle svg { display: block; width: 55%; height: 55%; }
    /* center and size the robot avatar SVG inside the header circle */
    #cb-avatar { display: flex; align-items: center; justify-content: center; }
    #cb-avatar svg { display: block; width: 70%; height: 70%; transform: translateY(1px); transform-origin: center; }
    #cb-badge {
      position: absolute; top: -4px; right: -4px;
      background: #e53935; color: #fff;
      width: 18px; height: 18px; border-radius: 50%;
      font-size: .7rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }
    #cb-window {
      position: fixed; bottom: 5rem; right: 1.5rem; z-index: 9998;
      width: 340px; max-width: calc(100vw - 2rem);
      background: #FAF7F2; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
      display: flex; flex-direction: column;
      overflow: hidden; font-family: 'Segoe UI', Arial, sans-serif;
      transform: scale(.92) translateY(12px); opacity: 0; pointer-events: none;
      transition: transform .22s ease, opacity .22s ease;
    }
    #cb-window.open { transform: scale(1) translateY(0); opacity: 1; pointer-events: all; }
    #cb-header {
      background: ${PRIMARY}; color: #fff;
      padding: .85rem 1rem; display: flex; align-items: center; gap: .65rem;
    }
    #cb-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: ${ACCENT}; display: flex; align-items: center; justify-content: center;
      font-size: 1rem; flex-shrink: 0;
    }
    #cb-header-text { flex: 1; }
    #cb-header-text strong { display: block; font-size: .95rem; }
    #cb-header-text span { font-size: .78rem; color: #A8C7A0; }
    #cb-close {
      background: none; border: none; color: #aaa; cursor: pointer;
      font-size: 1.1rem; padding: .2rem; transition: color .2s;
    }
    #cb-close:hover { color: #fff; }
    #cb-messages {
      flex: 1; overflow-y: auto; padding: 1rem;
      display: flex; flex-direction: column; gap: .7rem;
      max-height: 320px; min-height: 200px;
    }
    .cb-msg { display: flex; gap: .5rem; align-items: flex-end; }
    .cb-msg.user { flex-direction: row-reverse; }
    .cb-bubble {
      max-width: 82%; padding: .6rem .9rem; border-radius: 14px;
      font-size: .9rem; line-height: 1.5;
    }
    .cb-msg.bot .cb-bubble {
      background: #fff; color: ${PRIMARY};
      border: 1px solid #E2DDD6; border-bottom-left-radius: 4px;
    }
    .cb-msg.user .cb-bubble {
      background: ${ACCENT}; color: #fff; border-bottom-right-radius: 4px;
    }
    .cb-msg.bot .cb-bubble a { color: ${ACCENT}; }
    .cb-typing span {
      display: inline-block; width: 7px; height: 7px;
      background: #ccc; border-radius: 50%; margin: 0 1px;
      animation: cb-bounce .9s infinite;
    }
    .cb-typing span:nth-child(2) { animation-delay: .15s; }
    .cb-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes cb-bounce {
      0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-5px)}
    }
    #cb-quick { padding: .5rem 1rem .75rem; display: flex; flex-wrap: wrap; gap: .4rem; }
    .cb-qbtn {
      background: #fff; border: 1px solid #E2DDD6; border-radius: 20px;
      padding: .3rem .8rem; font-size: .8rem; cursor: pointer; color: ${PRIMARY};
      transition: background .15s, border-color .15s; font-family: inherit;
    }
    .cb-qbtn:hover { background: ${ACCENT}; color: #fff; border-color: ${ACCENT}; }
    #cb-input-row {
      display: flex; gap: .5rem; padding: .75rem 1rem;
      border-top: 1px solid #E2DDD6; background: #fff;
    }
    #cb-input {
      flex: 1; border: 1px solid #E2DDD6; border-radius: 20px;
      padding: .5rem .9rem; font-size: .88rem; font-family: inherit;
      outline: none; background: #FAF7F2; color: ${PRIMARY};
    }
    #cb-input:focus { border-color: ${ACCENT}; }
    #cb-input:disabled { opacity: .5; }
    #cb-send {
      background: ${ACCENT}; color: #fff; border: none;
      border-radius: 50%; width: 36px; height: 36px; cursor: pointer;
      font-size: 1rem; display: flex; align-items: center; justify-content: center;
      transition: background .2s; flex-shrink: 0;
    }
    #cb-send:hover { background: #A8612C; }
    #cb-send:disabled { background: #ccc; cursor: not-allowed; }
    #cb-counter {
      text-align: right; font-size: .72rem; color: #bbb;
      padding: .2rem 1rem 0; background: #fff;
    }
    #cb-limit-msg {
      text-align: center; font-size: .82rem; color: #888;
      padding: .5rem 1rem 1rem;
    }
    #cb-limit-msg a { color: ${ACCENT}; }
  `;
  document.head.appendChild(style);

  // ── Build UI ──────────────────────────────────────────────────────────────
  const toggle = document.createElement('button');
  toggle.id = 'cb-toggle';
  toggle.setAttribute('aria-label', 'Avaa chat');
  // SVG icons
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
  win.setAttribute('role', 'dialog');
  win.setAttribute('aria-label', 'Karelia-botti chat');
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
      <button id="cb-close" aria-label="Sulje chat">✕</button>
    </div>
    <div id="cb-messages"></div>
    <div id="cb-quick"></div>
    <div id="cb-counter"></div>
    <div id="cb-input-row">
      <input id="cb-input" type="text" placeholder="Kirjoita viesti..." maxlength="300" autocomplete="off">
      <button id="cb-send" aria-label="Lähetä">➤</button>
    </div>
  `;

  document.body.appendChild(toggle);
  document.body.appendChild(win);

  // ── Refs ──────────────────────────────────────────────────────────────────
  const msgs   = win.querySelector('#cb-messages');
  const qArea  = win.querySelector('#cb-quick');
  const input  = win.querySelector('#cb-input');
  const sendBtn= win.querySelector('#cb-send');
  const counter= win.querySelector('#cb-counter');
  const badge  = toggle.querySelector('#cb-badge');

  // ── Local FAQ/KB fallback (used when API is unavailable) ────────────────
  const KB = [
    { keys: ['hei','moi','terve'], reply: 'Hei! 👋 Olen Karelia Ulkorakennus Oy:n chatbotti. Mistä haluaisit tietää lisää?', quick: ['Terassit','Hinnoittelu','Toimialue','Avaa UKK-sivu'] },
    { keys: ['terassi','terassit'], reply: 'Rakennamme kestäviä ja tyylikkäitä terasseja. Haluatko tietää materiaaleista tai hinnoista?', quick: ['Materiaalit','Hinnoittelu','Pyydä tarjous'] },
    { keys: ['hinta','hinnoittelu','tarjous'], reply: 'Annamme aina ilmaisen kirjallisen tarjouksen ennen töiden aloittamista.', quick: ['Pyydä tarjous'] },
    { keys: ['yhteystiedot','yhteydenotto','puhelin','sähköposti'], reply: 'Tavoitat meidät: 050 123 4567 · info@kareliarakennus.fi', quick: ['Avaa yhteydenotto'] },
  ];
  const DEFAULT_REPLY = 'En valitettavasti saa vastausta palvelimelta. Löydät vastauksia myös UKK-sivultamme: <a href="ukk.html">UKK</a>.';

  const FAQ_ENTRIES = [];
  (function loadFaq(){
    try {
      fetch('faq.html').then(r => r.ok ? r.text() : null).then(html => {
        if (!html) return;
        const doc = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll('.faq-item').forEach(it => {
          const q = (it.querySelector('.faq-q') || {}).textContent || '';
          const a = (it.querySelector('.faq-a') || {}).textContent || '';
          if (q || a) FAQ_ENTRIES.push({ q: q.trim(), a: a.trim(), text: (q + ' ' + a).toLowerCase() });
        });
      }).catch(()=>{});
    } catch(e){}
  })();

  function getBotReply(userMsg){
    const msg = userMsg.toLowerCase();
    const tokens = msg.split(/\W+/).filter(t=>t.length>2);
    if (tokens.length && FAQ_ENTRIES.length){
      let best=null; let bestScore=0;
      for (const f of FAQ_ENTRIES){ let score=0; for(const t of tokens) if (f.text.includes(t)) score++; if (score>bestScore){bestScore=score;best=f;} }
      const threshold = tokens.length>=3?2:1; if (best && bestScore>=threshold) return { text: best.a, quick: ['Avaa UKK-sivu','Pyydä tarjous'] };
    }
    for (const e of KB) if (e.keys.some(k=>msg.includes(k))) return { text: e.reply, quick: e.quick||[] };
    return { text: DEFAULT_REPLY, quick: ['Avaa UKK-sivu','Avaa yhteydenotto','Pyydä tarjous'] };
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  function addMsg(text, who) {
    const wrap = document.createElement('div');
    wrap.className = `cb-msg ${who}`;
    const bubble = document.createElement('div');
    bubble.className = 'cb-bubble';
    bubble.innerHTML = text.replace(/\n/g, '<br>');
    wrap.appendChild(bubble);
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
    return wrap;
  }

  function showQuick(btns) {
    qArea.innerHTML = '';
    btns.forEach(label => {
      const b = document.createElement('button');
      b.className = 'cb-qbtn';
      b.textContent = label;
      b.onclick = () => {
        if (label === 'Avaa UKK-sivu')     { window.location.href = 'ukk.html'; return; }
        if (label === 'Ota yhteyttä')       { window.location.href = 'yhteydenotto.html'; return; }
        if (label === 'Varaa aika')         { window.location.href = 'ajanvaraus.html'; return; }
        sendMessage(label);
      };
      qArea.appendChild(b);
    });
  }

  function updateCounter() {
    const remaining = MAX_MESSAGES - userMsgCount;
    counter.textContent = remaining > 0 ? `${remaining} viestiä jäljellä` : '';
  }

  function setLoading(on) {
    input.disabled = on;
    sendBtn.disabled = on;
  }

  function showTyping() {
    const wrap = document.createElement('div');
    wrap.className = 'cb-msg bot';
    wrap.id = 'cb-typing';
    wrap.innerHTML = '<div class="cb-bubble cb-typing"><span></span><span></span><span></span></div>';
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function removeTyping() {
    const t = document.getElementById('cb-typing');
    if (t) t.remove();
  }

  function lockChat() {
    win.querySelector('#cb-input-row').style.display = 'none';
    counter.style.display = 'none';
    qArea.innerHTML = '';
    const lim = document.createElement('div');
    lim.id = 'cb-limit-msg';
    lim.innerHTML = 'Olet käyttänyt chat-rajoituksen. 😊<br><a href="yhteydenotto.html">→ Yhteydenottolomake</a> · <a href="ajanvaraus.html">→ Varaa aika</a>';
    win.appendChild(lim);
  }

  // ── Send message ──────────────────────────────────────────────────────────
  async function sendMessage(text) {
    if (!text.trim() || userMsgCount >= MAX_MESSAGES) return;

    addMsg(text, 'user');
    qArea.innerHTML = '';
    input.value = '';
    userMsgCount++;
    updateCounter();
    setLoading(true);
    showTyping();

    try {
        const res = await fetch(API_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text })
        });
        if (!res.ok) {
          // fallback to local KB/FAQ when API is unavailable
          removeTyping();
          const { text: reply, quick } = getBotReply(text);
          addMsg(reply, 'bot');
          showQuick(quick);
        } else {
          const data = await res.json();
          removeTyping();
          const reply = data.reply || 'Pahoittelen, yritä uudelleen tai ota yhteyttä suoraan!';
          addMsg(reply, 'bot');
          showQuick(['Avaa UKK-sivu', 'Ota yhteyttä', 'Varaa aika']);
        }
    } catch (err) {
      // On network or other errors, use local fallback so chat still responds
      removeTyping();
      const { text: reply, quick } = getBotReply(text);
      addMsg(reply, 'bot');
      showQuick(quick);
    }

    setLoading(false);

    if (userMsgCount >= MAX_MESSAGES) {
      addMsg('Olet käyttänyt tämän istunnon viestirajoituksen. Ota yhteyttä suoraan tai varaa aika! 😊', 'bot');
      lockChat();
    }
  }

  // ── Events ────────────────────────────────────────────────────────────────
  toggle.addEventListener('click', () => {
    open = !open;
    win.classList.toggle('open', open);
    // swap icon and control pulse
    toggle.innerHTML = open ? CLOSE_ICON : CHAT_ICON;
    if (open) toggle.classList.remove('pulse'); else toggle.classList.add('pulse');

    if (open && msgs.children.length === 0) {
      setTimeout(() => {
        addMsg('Hei! 👋 Olen Karelia Ulkorakennus Oy:n tekoälyavustaja. Voin vastata palveluihimme liittyviin kysymyksiin. Mistä haluaisit tietää?', 'bot');
        showQuick(['Terassit', 'Hinnoittelu', 'Toimialue', 'Varaa aika']);
        updateCounter();
      }, 300);
    }
  });

  win.querySelector('#cb-close').addEventListener('click', () => {
    open = false;
    win.classList.remove('open');
    toggle.innerHTML = CHAT_ICON;
    toggle.classList.add('pulse');
  });

  sendBtn.addEventListener('click', () => sendMessage(input.value));
  input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(input.value); });

  // Show badge after 4 seconds
  setTimeout(() => {
    if (!open) {
      const b = toggle.querySelector('#cb-badge') || toggle.querySelector('span');
      if (b) { b.style.display = 'flex'; b.textContent = '1'; }
      // start pulse together with the badge
      toggle.classList.add('pulse');
    }
  }, 4000);

})();
