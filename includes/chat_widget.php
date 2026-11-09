  <!-- ===== Floating chat widget ===== -->
  <div id="mms-chat-launcher">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span id="mms-chat-dot"></span>
  </div>

  <div id="mms-chat-panel">
    <div id="mms-chat-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <img src="assets/logo.png" alt="MM'S" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
        <div>
          <div style="font-family:var(--serif);font-weight:700;font-size:15px;">MM'S Boutique</div>
          <div style="font-size:11px;color:rgba(243,235,219,.7);">costuma responder em minutos</div>
        </div>
      </div>
      <button id="mms-chat-close" aria-label="Fechar">✕</button>
    </div>

    <div id="mms-chat-tabs">
      <button class="mms-tab active" data-tab="ai">Assistente virtual</button>
      <button class="mms-tab" data-tab="wa">WhatsApp</button>
    </div>

    <!-- AI assistant tab -->
    <div id="mms-tab-ai" class="mms-tab-content active">
      <div id="mms-ai-messages">
        <div class="mms-msg mms-msg-bot">Oi! Sou a assistente virtual da MM'S 💚 Posso ajudar com dúvidas sobre produtos, tamanhos, entregas ou nossas lojas. Pergunta à vontade!</div>
      </div>
      <div id="mms-ai-input-row">
        <input id="mms-ai-input" type="text" placeholder="Digite sua pergunta...">
        <button id="mms-ai-send" aria-label="Enviar">➤</button>
      </div>
    </div>

    <!-- WhatsApp tab -->
    <div id="mms-tab-wa" class="mms-tab-content">
      <p style="font-size:13px;color:var(--ink-soft,rgba(46,31,24,.7));margin-bottom:16px;">Escolha a loja e continue no WhatsApp:</p>
      <a class="mms-wa-btn" href="https://api.whatsapp.com/send?phone=5584988013059&text=Oi!%20Vim%20do%20site%20da%20MM'S%20Boutique%20e%20queria%20tirar%20uma%20d%C3%BAvida." target="_blank" rel="noopener">
        <span>💬</span> Loja Igapó
      </a>
      <a class="mms-wa-btn" href="https://wa.me/5584999001179?text=Oi!%20Vim%20do%20site%20da%20MM'S%20Boutique%20e%20queria%20tirar%20uma%20d%C3%BAvida." target="_blank" rel="noopener">
        <span>💬</span> Loja SuperFácil
      </a>
    </div>
  </div>

  <style>
    #mms-chat-launcher{
      position:fixed; bottom:24px; right:24px; z-index:9999;
      width:58px; height:58px; border-radius:50%;
      background:var(--emerald-deep); color:var(--cream);
      display:flex; align-items:center; justify-content:center;
      box-shadow:0 8px 22px rgba(0,0,0,.28); cursor:pointer;
      transition:transform .2s ease;
    }
    #mms-chat-launcher:hover{ transform:scale(1.06); }
    #mms-chat-dot{
      position:absolute; top:4px; right:4px; width:12px; height:12px; border-radius:50%;
      background:var(--gold); border:2px solid var(--cream);
    }
    #mms-chat-panel{
      position:fixed; bottom:92px; right:24px; z-index:9999;
      width:340px; max-width:90vw; height:460px; max-height:70vh;
      background:var(--cream); border-radius:14px; overflow:hidden;
      box-shadow:0 16px 40px rgba(0,0,0,.3);
      display:none; flex-direction:column;
      font-family:var(--sans);
    }
    #mms-chat-panel.open{ display:flex; }
    #mms-chat-header{
      background:var(--emerald-deep); color:var(--cream);
      padding:14px 16px; display:flex; align-items:center; justify-content:space-between;
    }
    #mms-chat-close{ background:none; border:none; color:var(--cream); font-size:16px; cursor:pointer; opacity:.8; }
    #mms-chat-close:hover{ opacity:1; }
    #mms-chat-tabs{ display:flex; border-bottom:1px solid rgba(46,31,24,.12); }
    .mms-tab{
      flex:1; padding:11px; background:none; border:none; font-size:12.5px; font-weight:700;
      letter-spacing:.02em; color:rgba(46,31,24,.55); cursor:pointer; border-bottom:2px solid transparent;
    }
    .mms-tab.active{ color:var(--emerald-deep); border-bottom-color:var(--gold); }
    .mms-tab-content{ display:none; flex:1; flex-direction:column; overflow:hidden; padding:14px; }
    .mms-tab-content.active{ display:flex; }
    #mms-ai-messages{ flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:10px; padding-right:4px; }
    .mms-msg{ font-size:13px; line-height:1.5; padding:9px 12px; border-radius:12px; max-width:85%; }
    .mms-msg-bot{ background:var(--cream-2,#EFE6D1); align-self:flex-start; border-bottom-left-radius:2px; }
    .mms-msg-user{ background:var(--emerald-deep); color:var(--cream); align-self:flex-end; border-bottom-right-radius:2px; }
    #mms-ai-input-row{ display:flex; gap:8px; margin-top:12px; }
    #mms-ai-input{
      flex:1; border:1px solid rgba(46,31,24,.2); border-radius:20px; padding:9px 14px; font-size:13px; outline:none;
    }
    #mms-ai-input:focus{ border-color:var(--emerald-deep); }
    #mms-ai-send{
      width:36px; height:36px; border-radius:50%; border:none; background:var(--emerald-deep); color:var(--cream);
      cursor:pointer; font-size:14px; flex-shrink:0;
    }
    .mms-wa-btn{
      display:flex; align-items:center; gap:10px; padding:13px 14px; margin-bottom:10px;
      background:#fff; border:1px solid rgba(46,31,24,.15); border-radius:10px;
      text-decoration:none; color:var(--choc,#2E1F18); font-size:13.5px; font-weight:600;
      transition:border-color .2s;
    }
    .mms-wa-btn:hover{ border-color:var(--emerald-deep); }
    @media (max-width:480px){
      #mms-chat-panel{ right:12px; bottom:82px; width:calc(100vw - 24px); }
      #mms-chat-launcher{ right:16px; bottom:16px; }
    }
  </style>

  <script>
    (function(){
      const launcher = document.getElementById('mms-chat-launcher');
      const panel = document.getElementById('mms-chat-panel');
      const closeBtn = document.getElementById('mms-chat-close');
      const tabs = document.querySelectorAll('.mms-tab');
      const tabContents = document.querySelectorAll('.mms-tab-content');

      launcher.addEventListener('click', () => panel.classList.toggle('open'));
      closeBtn.addEventListener('click', () => panel.classList.remove('open'));

      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          tabs.forEach(t => t.classList.remove('active'));
          tabContents.forEach(c => c.classList.remove('active'));
          tab.classList.add('active');
          document.getElementById('mms-tab-' + tab.dataset.tab).classList.add('active');
        });
      });

      // ---- Menu mobile ----
      const hamburgerBtn = document.getElementById('hamburgerBtn');
      const mobileDrawer = document.getElementById('mobileDrawer');
      const closeDrawerBtn = document.getElementById('closeDrawerBtn');
      if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', () => mobileDrawer.classList.add('open'));
        closeDrawerBtn.addEventListener('click', () => mobileDrawer.classList.remove('open'));
        mobileDrawer.addEventListener('click', (e) => { if (e.target === mobileDrawer) mobileDrawer.classList.remove('open'); });
      }

      // ---- AI assistant ----
      const messagesEl = document.getElementById('mms-ai-messages');
      const inputEl = document.getElementById('mms-ai-input');
      const sendBtn = document.getElementById('mms-ai-send');
      let history = [];

      function addMessage(text, who){
        const div = document.createElement('div');
        div.className = 'mms-msg ' + (who === 'user' ? 'mms-msg-user' : 'mms-msg-bot');
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
      }

      async function sendMessage(){
        const text = inputEl.value.trim();
        if(!text) return;
        addMessage(text, 'user');
        history.push({ role: 'user', content: text });
        inputEl.value = '';
        sendBtn.disabled = true;

        const thinkingEl = document.createElement('div');
        thinkingEl.className = 'mms-msg mms-msg-bot';
        thinkingEl.textContent = '...';
        messagesEl.appendChild(thinkingEl);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        try {
          const response = await fetch("chat_proxy.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ messages: history })
          });
          const data = await response.json();
          if (data.error) throw new Error(data.error);
          const reply = data.content?.find(b => b.type === 'text')?.text || "Desculpa, não consegui responder agora. Tenta de novo ou fala com a gente no WhatsApp!";
          thinkingEl.textContent = reply;
          history.push({ role: 'assistant', content: reply });
        } catch (err) {
          thinkingEl.textContent = "Ops, tive um probleminha aqui. Tenta de novo ou chama no WhatsApp!";
        } finally {
          sendBtn.disabled = false;
          messagesEl.scrollTop = messagesEl.scrollHeight;
        }
      }

      sendBtn.addEventListener('click', sendMessage);
      inputEl.addEventListener('keydown', (e) => { if(e.key === 'Enter') sendMessage(); });
    })();
  </script>



