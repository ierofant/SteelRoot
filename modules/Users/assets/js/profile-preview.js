document.addEventListener('DOMContentLoaded', () => {
  const faqItems = Array.from(document.querySelectorAll('.users-faq-item'));
  if (faqItems.length > 0) {
    faqItems.forEach((item) => {
      item.addEventListener('toggle', () => {
        if (!item.open) return;
        faqItems.forEach((other) => {
          if (other !== item) {
            other.open = false;
          }
        });
      });
    });
  }

  const root = document.querySelector('[data-watermark-preview]');
  if (!root) return;

  const enabled = root.querySelector('[name="photo_copyright_enabled"]');
  const text = root.querySelector('[name="photo_copyright_text"]');
  const font = root.querySelector('[name="photo_copyright_font"]');
  const colorInput = root.querySelector('[data-watermark-color-input]');
  const colorPicker = root.querySelector('[data-watermark-color-picker]');
  const preview = root.querySelector('[data-watermark-preview-text]');
  const state = root.querySelector('[data-watermark-preview-state]');

  if (!enabled || !text || !font || !preview || !state) return;

  const normalizeColor = (value) => {
    const raw = (value || '').trim();
    return /^#?[0-9a-fA-F]{6}$/.test(raw) ? '#' + raw.replace('#', '').toLowerCase() : '#f8f0eb';
  };

  const render = () => {
    const active = enabled.checked;
    const value = (text.value || '').trim();
    const fontClass = font.value || 'oswald';
    const color = normalizeColor(colorInput ? colorInput.value : (colorPicker ? colorPicker.value : '#f8f0eb'));

    preview.textContent = value !== '' ? value : '@artist';
    preview.className = 'users-watermark-preview__mark font-' + fontClass;
    preview.style.color = color;
    root.dataset.enabled = active ? '1' : '0';
    state.textContent = active ? 'Watermark enabled' : 'Watermark disabled';
    if (colorPicker) colorPicker.value = color;
    if (colorInput) colorInput.value = color;
  };

  enabled.addEventListener('change', render);
  text.addEventListener('input', render);
  font.addEventListener('change', render);
  if (colorInput) colorInput.addEventListener('input', render);
  if (colorPicker) colorPicker.addEventListener('input', () => {
    if (colorInput) colorInput.value = colorPicker.value;
    render();
  });
  render();
});
