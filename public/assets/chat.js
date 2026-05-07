(function () {
  'use strict';

  const WIDGET_KEY = window.WIDGET_KEY;
  const BASE_URL   = window.BASE_URL || '';
  const MAX_LEN    = 200;
  const SEND_COOL  = 3000;

  const messagesEl  = document.getElementById('messages');
  const inputEl     = document.getElementById('user-input');
  const sendBtn     = document.getElementById('send-btn');
  const suggestEl   = document.getElementById('suggestions');
  const charWarn    = document.getElementById('char-warning');
  const closeBtn    = document.getElementById('close-btn');

  let sessionId = sessionStorage.getItem('sw_session') || crypto.randomUUID();
  sessionStorage.setItem('sw_session', sessionId);

  let sending = false;
  let cooldownTimer = null;

  const INIT_SUGGESTIONS = [
    'よくある質問は？',
    '料金を教えてください',
    '営業時間は？',
    'お問い合わせ方法',
    '返品・キャンセルについて',
  ];

  function init() {
    console.log('[Chat] 初期化 widget_key=' + WIDGET_KEY + ' session=' + sessionId);
    appendBotMsg('こんにちは！何でもお気軽にご質問ください。');
    renderSuggestions(INIT_SUGGESTIONS);
    closeBtn.addEventListener('click', () => window.parent.postMessage('sw:close', '*'));
    inputEl.addEventListener('input', onInput);
    inputEl.addEventListener('keydown', onKeydown);
    sendBtn.addEventListener('click', doSend);
  }

  function onInput() {
    const len = inputEl.value.length;
    charWarn.hidden = len <= MAX_LEN;
    sendBtn.disabled = len === 0 || len > MAX_LEN || sending;
    autoResize();
  }

  function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!sendBtn.disabled) doSend();
    }
  }

  function autoResize() {
    inputEl.style.height = 'auto';
    inputEl.style.height = inputEl.scrollHeight + 'px';
  }

  async function doSend() {
    const text = inputEl.value.trim();
    if (!text || text.length > MAX_LEN || sending) return;

    console.log('[Chat] 送信開始:', text);
    sending = true;
    sendBtn.disabled = true;
    inputEl.value = '';
    autoResize();
    suggestEl.innerHTML = '';

    appendUserMsg(text);
    const typingEl = appendTyping();

    try {
      console.log('[Chat] stream.php へリクエスト送信');
      const res = await fetch(`${BASE_URL}/api/stream.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ widget_key: WIDGET_KEY, message: text, session_id: sessionId }),
      });

      typingEl.remove();

      if (!res.ok) {
        console.error('[Chat] HTTPエラー:', res.status);
        throw new Error('http_error');
      }

      console.log('[Chat] ストリーミング開始');
      const botEl = appendBotMsg('');
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buf = '';
      let chunkCount = 0;

      while (true) {
        const { done, value } = await reader.read();
        if (done) {
          console.log('[Chat] ストリーミング完了 チャンク数=' + chunkCount);
          break;
        }
        buf += decoder.decode(value, { stream: true });
        const lines = buf.split('\n');
        buf = lines.pop();

        let currentEvent = '';
        for (const line of lines) {
          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim();
            console.log('[Chat] イベント:', currentEvent);
            continue;
          }
          if (line.startsWith('data: ')) {
            const data = JSON.parse(line.slice(6));
            if (currentEvent === 'chunk') {
              botEl.textContent += data;
              chunkCount++;
              scrollBottom();
            } else if (currentEvent === 'error') {
              console.error('[Chat] サーバーエラー:', data);
              botEl.textContent = errorMessage(data);
            } else if (currentEvent === 'usage') {
              const usageList = JSON.parse(data);
              let totalIn = 0, totalOut = 0;
              console.group('[Claude API] トークン使用量');
              usageList.forEach(u => {
                console.log(`  📡 ${u.call} — model: ${u.model} | input: ${u.input_tokens} | output: ${u.output_tokens}`);
                totalIn  += u.input_tokens;
                totalOut += u.output_tokens;
              });
              console.log(`  📊 合計 — input: ${totalIn} | output: ${totalOut} | total: ${totalIn + totalOut} tokens`);
              console.groupEnd();
            } else if (currentEvent === 'done') {
              console.log('[Chat] done受信');
            }
            currentEvent = '';
          }
        }
      }

      appendFeedback();

    } catch (err) {
      console.error('[Chat] 例外:', err);
      typingEl?.remove();
      appendBotMsg('申し訳ありません。一時的なエラーが発生しました。しばらく後にお試しください。');
    }

    clearTimeout(cooldownTimer);
    cooldownTimer = setTimeout(() => {
      sending = false;
      sendBtn.disabled = inputEl.value.trim() === '' || inputEl.value.length > MAX_LEN;
      console.log('[Chat] クールダウン終了 再送信可能');
    }, SEND_COOL);
  }

  function appendUserMsg(text) {
    const el = document.createElement('div');
    el.className = 'msg msg-user';
    el.textContent = text;
    messagesEl.appendChild(el);
    scrollBottom();
    return el;
  }

  function appendBotMsg(text) {
    const el = document.createElement('div');
    el.className = 'msg msg-bot';
    el.textContent = text;
    messagesEl.appendChild(el);
    scrollBottom();
    return el;
  }

  function appendTyping() {
    const el = document.createElement('div');
    el.className = 'msg msg-bot typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    messagesEl.appendChild(el);
    scrollBottom();
    return el;
  }

  function appendFeedback() {
    const el = document.createElement('div');
    el.className = 'feedback';
    el.innerHTML = `
      <span>参考になりましたか？</span>
      <button data-v="1" title="はい">👍</button>
      <button data-v="0" title="いいえ">👎</button>
    `;
    messagesEl.appendChild(el);
    scrollBottom();

    el.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', function () {
        const positive = this.dataset.v === '1';
        console.log('[Chat] フィードバック:', positive ? '👍' : '👎');
        el.innerHTML = positive
          ? '<span>ありがとうございました！</span>'
          : '<span>ご不便をおかけしました。</span><div class="feedback-contact"><a href="#" id="contact-link" target="_blank" rel="noopener noreferrer">担当者へ相談する →</a></div>';

        const link = el.querySelector('#contact-link');
        if (link && window.CONTACT_URL) {
          link.href = window.CONTACT_URL;
        }
      });
    });
  }

  function renderSuggestions(items) {
    suggestEl.innerHTML = '';
    items.slice(0, 5).forEach(item => {
      const btn = document.createElement('button');
      btn.className = 'suggestion-btn';
      btn.textContent = item;
      btn.addEventListener('click', () => {
        inputEl.value = item;
        autoResize();
        onInput();
        doSend();
      });
      suggestEl.appendChild(btn);
    });
  }

  function scrollBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function errorMessage(code) {
    if (code === 'rate_limit_exceeded') return '送信回数の制限に達しました。しばらく後にお試しください。';
    return '申し訳ありません。一時的なエラーが発生しました。';
  }

  init();
})();
