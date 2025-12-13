(() => {
  const root = document.querySelector('[data-cookie-popup]');
  if (!root) return;
  const enabled = root.dataset.cookieEnabled === '1';
  if (!enabled) return;
  const store = root.dataset.cookieStore === 'session' ? sessionStorage : localStorage;
  const key = root.dataset.cookieKey || 'cookie_policy_accepted';
  if (store.getItem(key) === '1') return;

  const text = root.dataset.cookieText || '';
  const btnText = root.dataset.cookieButton || 'OK';
  const position = root.dataset.cookiePosition || 'bottom-right';

  const styleEl = document.createElement('style');
  styleEl.textContent = `
    .cookie-popup {position:fixed; z-index:5000; max-width:320px; font-family:inherit;}
    .cookie-popup[data-position="bottom-right"] {right:16px; bottom:16px;}
    .cookie-popup[data-position="bottom-left"] {left:16px; bottom:16px;}
    .cookie-popup[data-position="top"] {top:12px; right:12px; left:12px; max-width:none;}
    .cookie-popup__body {padding:14px 16px; border-radius:12px; background:rgba(15,23,42,0.9); color:#e5e7eb; border:1px solid rgba(255,255,255,0.12); box-shadow:0 12px 32px rgba(0,0,0,0.35); display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap;}
    .cookie-popup__text {font-size:14px; line-height:1.4;}
    .cookie-popup__btn {padding:9px 12px; border-radius:10px; border:none; background:linear-gradient(120deg,#ff4f8b,#c86bfa); color:#0b1220; font-weight:700; cursor:pointer;}
  `;
  document.head.appendChild(styleEl);

  const wrap = document.createElement('div');
  wrap.className = 'cookie-popup';
  wrap.innerHTML = `
    <div class="cookie-popup__body">
      <div class="cookie-popup__text">${text}</div>
      <button type="button" class="cookie-popup__btn">${btnText}</button>
    </div>
  `;
  document.body.appendChild(wrap);
  wrap.dataset.position = position;

  const btn = wrap.querySelector('.cookie-popup__btn');
  btn?.addEventListener('click', () => {
    store.setItem(key, '1');
    wrap.remove();
  });
})();
