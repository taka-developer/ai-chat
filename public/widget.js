(function () {
  const script = document.currentScript;
  const widgetKey = script.getAttribute('data-key');
  if (!widgetKey) return;

  const BASE_URL = new URL(script.src).origin + new URL(script.src).pathname.replace(/\/widget\.js$/, '');

  // 起動ボタン
  const btn = document.createElement('button');
  btn.id = 'stekwired-chat-btn';
  btn.innerHTML = '💬';
  btn.setAttribute('aria-label', 'チャットを開く');
  Object.assign(btn.style, {
    position: 'fixed', bottom: '24px', right: '24px',
    width: '56px', height: '56px', borderRadius: '50%',
    background: '#2563eb', color: '#fff', border: 'none',
    fontSize: '24px', cursor: 'pointer', boxShadow: '0 4px 12px rgba(0,0,0,.25)',
    zIndex: '999998', display: 'flex', alignItems: 'center', justifyContent: 'center',
  });

  // iframeラッパー
  const wrapper = document.createElement('div');
  wrapper.id = 'stekwired-chat-wrapper';
  Object.assign(wrapper.style, {
    position: 'fixed', bottom: '90px', right: '24px',
    width: '380px', height: '600px', maxHeight: 'calc(100vh - 120px)',
    borderRadius: '16px', overflow: 'hidden',
    boxShadow: '0 8px 32px rgba(0,0,0,.2)', zIndex: '999999',
    display: 'none', border: 'none',
  });

  const iframe = document.createElement('iframe');
  iframe.src = `${BASE_URL}/chat.php?key=${encodeURIComponent(widgetKey)}`;
  iframe.style.cssText = 'width:100%;height:100%;border:none;';
  iframe.allow = 'clipboard-write';
  wrapper.appendChild(iframe);

  document.body.appendChild(btn);
  document.body.appendChild(wrapper);

  let open = false;

  function setOpen(val) {
    open = val;
    wrapper.style.display = open ? 'block' : 'none';
    btn.innerHTML = open ? '✕' : '💬';
    btn.setAttribute('aria-label', open ? 'チャットを閉じる' : 'チャットを開く');
  }

  btn.addEventListener('click', () => setOpen(!open));

  window.addEventListener('message', e => {
    if (e.source !== iframe.contentWindow) return;
    if (e.data === 'sw:close') setOpen(false);
  });
})();
