/**
 * Gallery master-like handler.
 * Public likes are handled globally by /assets/js/likes.js.
 *
 * Buttons: .master-like-chip--action[data-id][data-token]
 * Counters: .master-like-chip[data-id] .g-master-likes
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.master-like-chip--action').forEach(initMasterLike);
    document.querySelectorAll('[data-gallery-share]').forEach(initGalleryShare);

    const masterBtn = document.getElementById('master-like-gallery');
    if (masterBtn) initMasterLike(masterBtn);
});

function initMasterLike(btn) {
    const key = 'master_liked_gallery_' + btn.dataset.id;
    if (localStorage.getItem(key) === '1') btn.classList.add('active');

    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id    = btn.dataset.id;
        const token = btn.dataset.token;
        if (!id || !token) return;

        try {
            const res = await fetch('/api/v1/gallery/master-like', {
                method:  'POST',
                headers: { Accept: 'application/json' },
                body:    new URLSearchParams({ gallery_item_id: id, _token: token }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || data.error || 'bad');

            const visible = parseInt(btn.querySelector('.g-master-likes')?.textContent ?? '0', 10) || 0;
            const server  = parseInt(data.master_likes ?? visible, 10) || 0;
            const count   = data.already ? Math.max(visible, server) : Math.max(visible + 1, server);

            document.querySelectorAll('.master-like-chip[data-id="' + id + '"] .g-master-likes')
                .forEach(el => { el.textContent = String(count); });

            // Lightbox counter
            const lbMaster = document.getElementById('lightbox-master-likes');
            if (lbMaster) lbMaster.textContent = String(count);

            btn.classList.add('active');
            localStorage.setItem(key, '1');

            if (window.showToast) {
                window.showToast(data.message || (data.already ? 'Уже отмечено' : 'Признание мастера засчитано'), 'success');
            } else if (data.message) {
                window.alert(data.message);
            }
        } catch (err) {
            if (window.showToast) {
                window.showToast(err.message || 'Не удалось отметить работу', 'danger');
            } else {
                window.alert(err.message || 'Не удалось отметить работу');
            }
        }
    });
}

function initGalleryShare(shareRoot) {
    if (!shareRoot || shareRoot.dataset.galleryShareBound === '1') return;

    const toggle = shareRoot.querySelector('[data-gallery-share-toggle]');
    const closeBtn = shareRoot.querySelector('[data-gallery-share-close]');
    const sheet = shareRoot.querySelector('.gallery-share__sheet');
    const copyBtn = shareRoot.querySelector('[data-gallery-share-copy]');

    if (!toggle || !sheet) return;

    shareRoot.dataset.galleryShareBound = '1';

    const setOpen = (open) => {
        sheet.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        shareRoot.classList.toggle('is-open', open);
    };

    toggle.addEventListener('click', () => {
        setOpen(sheet.hidden);
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            setOpen(false);
        });
    }

    document.addEventListener('click', (event) => {
        if (!shareRoot.contains(event.target)) {
            setOpen(false);
        }
    });

    if (copyBtn) {
        bindGalleryShareCopy(copyBtn);
    }
}

function bindGalleryShareCopy(copyBtn) {
    if (!copyBtn || copyBtn.dataset.galleryShareCopyBound === '1') return;

    copyBtn.dataset.galleryShareCopyBound = '1';

    copyBtn.addEventListener('click', async () => {
        const value = copyBtn.getAttribute('data-gallery-share-copy') || '';
        if (!value) return;

        const label = copyBtn.querySelector('span');
        const defaultLabel = copyBtn.getAttribute('data-gallery-share-copy-label') || '';
        const copiedLabel = copyBtn.getAttribute('data-gallery-share-copied-label') || defaultLabel;

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const input = document.createElement('input');
                input.value = value;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }

            copyBtn.classList.add('is-copied');
            if (label) label.textContent = copiedLabel;

            window.setTimeout(() => {
                copyBtn.classList.remove('is-copied');
                if (label) label.textContent = defaultLabel;
            }, 1600);
        } catch (err) {
        }
    });
}
