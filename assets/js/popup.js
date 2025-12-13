document.addEventListener('DOMContentLoaded', () => {
  const popupConfig = window.STEELROOT_POPUP || {};
  if (!popupConfig.enabled) return;
  const delay = popupConfig.delay || 5;
  setTimeout(showPopup, delay * 1000);

  function showPopup() {
    const overlay = document.createElement('div');
    overlay.className = 'popup-overlay';
    const themeClass = document.body.dataset.theme === 'dark' ? 'dark' : '';
    overlay.innerHTML = `
      <div class="popup ${themeClass}">
        <button class="close-btn" aria-label="Close">&times;</button>
        <h3>${popupConfig.title || 'Info'}</h3>
        <div class="popup-body">${popupConfig.content || ''}</div>
        <div class="actions">
          ${popupConfig.cta_url ? `<a class="btn primary" href="${popupConfig.cta_url}" target="_blank">${popupConfig.cta_text || 'Learn more'}</a>` : ''}
          <button class="btn secondary close-btn">Ок</button>
        </div>
      </div>
    `;
    overlay.style.display = 'flex';
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay || e.target.classList.contains('close-btn')) {
        document.body.removeChild(overlay);
      }
    });
    document.body.appendChild(overlay);
  }
});
