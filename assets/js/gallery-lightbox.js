(function () {
    const box = document.getElementById('lightbox');
    if (!box) return;
    const img       = document.getElementById('lightbox-image');
    const cap       = document.getElementById('lightbox-caption');
    const counter   = document.getElementById('lightbox-counter');
    const viewsEl   = document.getElementById('lightbox-views');
    const likesEl   = document.getElementById('lightbox-likes');
    const likeBtn   = document.getElementById('lightbox-like');
    const openLink  = document.getElementById('lightbox-open');
    const items     = Array.from(document.querySelectorAll('.lightbox-trigger'));
    if (!items.length) return;

    let current = -1;
    const viewed = new Set();

    function sendView(id) {
        if (!id || viewed.has(id)) return;
        viewed.add(id);
        fetch('/api/v1/view', {
            method: 'POST',
            headers: {'Accept': 'application/json'},
            body: new URLSearchParams({type: 'gallery', id})
        }).then(r => r.ok ? r.json() : null).then(data => {
            if (!data || data.views === undefined) return;
            document.querySelectorAll(`a[data-id="${id}"] .g-views`).forEach(el => {
                el.textContent = data.views;
            });
            if (viewsEl && items[current] && items[current].dataset.id === id) {
                viewsEl.textContent = data.views;
            }
        }).catch(() => {});
    }

    function openAt(idx) {
        const link = items[idx];
        if (!link) return;
        current = idx;
        const id   = link.dataset.id;
        const slug = link.dataset.slug || '';

        img.src = link.dataset.full || link.href;
        img.alt = link.dataset.title || '';

        if (cap)     cap.textContent     = link.dataset.title || '';
        if (counter) counter.textContent = (idx + 1) + ' / ' + items.length;

        // Views: prefer live DOM value, fallback to data attr
        if (viewsEl) {
            const gridView = document.querySelector(`a[data-id="${id}"] .g-views`);
            viewsEl.textContent = gridView ? gridView.textContent : (link.dataset.views || '0');
        }

        // Likes: prefer live DOM value, fallback to data attr
        if (likesEl) {
            const gridLike = document.querySelector(`.like-chip[data-id="${id}"] .g-likes`);
            likesEl.textContent = gridLike ? gridLike.textContent : (link.dataset.likes || '0');
        }

        // Like button state from localStorage
        if (likeBtn) {
            likeBtn.dataset.id = id;
            likeBtn.classList.toggle('active', localStorage.getItem('liked_gallery_' + id) === '1');
        }

        // Open-page link
        if (openLink) {
            if (slug) {
                openLink.href   = '/gallery/photo/' + encodeURIComponent(slug);
                openLink.hidden = false;
            } else {
                openLink.hidden = true;
            }
        }

        box.hidden = false;
        document.body.classList.add('no-scroll');
        sendView(id);

        // Preload adjacent images
        [-1, 1].forEach(function (d) {
            var n = (idx + d + items.length) % items.length;
            var pre = new Image();
            pre.src = items[n].dataset.full || '';
        });
    }

    function close() {
        box.hidden = true;
        img.src    = '';
        if (cap)     cap.textContent     = '';
        if (counter) counter.textContent = '';
        current = -1;
        document.body.classList.remove('no-scroll');
    }

    function next() { openAt((current + 1) % items.length); }
    function prev() { openAt((current - 1 + items.length) % items.length); }

    // Like from lightbox
    if (likeBtn) {
        likeBtn.addEventListener('click', async function () {
            var id = likeBtn.dataset.id;
            if (!id) return;
            try {
                var res = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {'Accept': 'application/json'},
                    body: new URLSearchParams({type: 'gallery', id: id})
                });
                if (!res.ok) throw new Error('bad');
                var data = await res.json();
                var likes = data.likes ?? 0;
                if (likesEl) likesEl.textContent = likes;
                // Sync grid chips
                document.querySelectorAll('.like-chip[data-id="' + id + '"] .g-likes').forEach(function (el) { el.textContent = likes; });
                document.querySelectorAll('.like-chip[data-id="' + id + '"]').forEach(function (el) { el.classList.add('active'); });
                likeBtn.classList.add('active');
                localStorage.setItem('liked_gallery_' + id, '1');
                if (window.showToast) window.showToast(data.already ? 'Уже лайкнули' : 'Лайк засчитан', 'success');
            } catch (_) {
                if (window.showToast) window.showToast('Не удалось поставить лайк', 'danger');
            }
        });
    }

    // Backdrop click → close
    box.addEventListener('click', function (e) {
        if (e.target === box || e.target.classList.contains('lightbox__backdrop')) close();
    });

    // Buttons
    var closeBtn = document.getElementById('lightbox-close');
    var prevBtn  = document.getElementById('lightbox-prev');
    var nextBtn  = document.getElementById('lightbox-next');
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (prevBtn)  prevBtn.addEventListener('click',  function (e) { e.preventDefault(); prev(); });
    if (nextBtn)  nextBtn.addEventListener('click',  function (e) { e.preventDefault(); next(); });

    // Item clicks
    items.forEach(function (link, i) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            openAt(i);
        });
    });

    // Keyboard
    document.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'Escape')     close();
        if (e.key === 'ArrowRight') next();
        if (e.key === 'ArrowLeft')  prev();
    });

    // Touch swipe
    var touchX = 0, touchY = 0, touchT = 0;
    box.addEventListener('touchstart', function (e) {
        touchX = e.touches[0].clientX;
        touchY = e.touches[0].clientY;
        touchT = Date.now();
    }, {passive: true});
    box.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - touchX;
        var dy = e.changedTouches[0].clientY - touchY;
        var dt = Date.now() - touchT;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50 && dt < 400) {
            dx < 0 ? next() : prev();
        }
    }, {passive: true});
})();
