(function () {
    const box = document.getElementById('lightbox');
    if (!box) return;
    const img = document.getElementById('lightbox-image');
    const cap = document.getElementById('lightbox-caption');
    const items = Array.from(document.querySelectorAll('.lightbox-trigger'));
    if (!items.length) return;
    let current = -1;

    function openAt(idx) {
        const link = items[idx];
        if (!link) return;
        current = idx;
        img.src = link.dataset.full || link.href;
        img.alt = link.dataset.title || '';
        cap.textContent = link.dataset.title || '';
        box.hidden = false;
        document.body.classList.add('no-scroll');
    }

    function close() {
        box.hidden = true;
        img.src = '';
        cap.textContent = '';
        current = -1;
        document.body.classList.remove('no-scroll');
    }

    function next() {
        if (!items.length) return;
        current = (current + 1) % items.length;
        openAt(current);
    }

    function prev() {
        if (!items.length) return;
        current = (current - 1 + items.length) % items.length;
        openAt(current);
    }

    box.addEventListener('click', (e) => {
        if (e.target === box || e.target.classList.contains('lightbox__backdrop')) {
            close();
        }
    });
    const closeBtn = box.querySelector('.lightbox__close');
    const prevBtn = box.querySelector('.lightbox__prev');
    const nextBtn = box.querySelector('.lightbox__next');
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); prev(); });
    if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); next(); });

    items.forEach((link, i) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openAt(i);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !box.hidden) close();
        if (e.key === 'ArrowRight' && !box.hidden) next();
        if (e.key === 'ArrowLeft' && !box.hidden) prev();
    });
})();
