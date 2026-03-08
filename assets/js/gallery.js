/**
 * Gallery Lightbox
 * Loaded only on pages with {nanogallery} shortcode.
 * Vanilla JS. Supports keyboard + touch swipe.
 */
(function () {
    'use strict';

    var items = [];
    var current = 0;
    var lightbox, lbImage, lbCaption, lbCounter;

    function init() {
        var grids = Array.from(document.querySelectorAll('[data-gallery]'));
        if (!grids.length) return;

        initLazyThumbs(grids);

        lightbox  = document.getElementById('galleryLightbox');
        lbImage   = document.getElementById('galleryLightboxImage');
        lbCaption = document.getElementById('galleryLightboxCaption');
        lbCounter = document.getElementById('galleryCounter');
        if (!lightbox || !lbImage) return;
        if (lightbox.parentNode !== document.body) {
            document.body.appendChild(lightbox);
        }

        items = [];
        grids.forEach(function (grid) {
            items = items.concat(Array.from(grid.querySelectorAll('.gallery-item')));
        });
        if (!items.length) return;

        items.forEach(function (el, idx) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                open(idx);
            });
        });

        lightbox.querySelector('.gallery-close').addEventListener('click', close);
        lightbox.querySelector('.gallery-prev').addEventListener('click', prev);
        lightbox.querySelector('.gallery-next').addEventListener('click', next);
        lightbox.querySelector('.gallery-lightbox-overlay').addEventListener('click', close);
        lbImage.addEventListener('click', onImageTap);

        document.addEventListener('keydown', onKey);

        // Touch swipe (horizontal only, with threshold)
        var touchX = 0;
        var touchY = 0;
        var touchTime = 0;
        var lastX = 0;
        var lastTime = 0;
        var touchActive = false;
        lightbox.addEventListener('touchstart', function (e) {
            if (!lightbox.classList.contains('active') || !e.changedTouches || !e.changedTouches[0]) return;
            touchX = e.changedTouches[0].screenX;
            touchY = e.changedTouches[0].screenY;
            touchTime = Date.now();
            lastX = touchX;
            lastTime = touchTime;
            touchActive = true;
        }, { passive: true });
        lightbox.addEventListener('touchmove', function (e) {
            if (!touchActive || !e.changedTouches || !e.changedTouches[0]) return;
            lastX = e.changedTouches[0].screenX;
            lastTime = Date.now();
        }, { passive: true });
        lightbox.addEventListener('touchend', function (e) {
            if (!touchActive || !lightbox.classList.contains('active') || !e.changedTouches || !e.changedTouches[0]) return;
            touchActive = false;
            var endX = e.changedTouches[0].screenX;
            var endY = e.changedTouches[0].screenY;
            var diffX = touchX - endX;
            var diffY = touchY - endY;
            var elapsed = Math.max(1, Date.now() - touchTime);
            var velocity = Math.abs(diffX) / elapsed; // px/ms
            var moveElapsed = Math.max(1, Date.now() - lastTime);
            var moveVelocity = Math.abs(lastX - endX) / moveElapsed; // px/ms
            var isFastSwipe = velocity > 0.45 || moveVelocity > 0.45;
            if (Math.abs(diffX) < 50 && !(isFastSwipe && Math.abs(diffX) >= 24)) return;
            if (Math.abs(diffX) <= Math.abs(diffY)) return;
            diffX > 0 ? next() : prev();
        });
    }

    function initLazyThumbs(grids) {
        var thumbs = [];
        grids.forEach(function (grid) {
            thumbs = thumbs.concat(Array.from(grid.querySelectorAll('img[data-src]')));
        });
        if (!thumbs.length) return;

        function loadThumb(img) {
            var src = img.getAttribute('data-src');
            if (!src) return;
            img.removeAttribute('data-src');
            img.onload = function () {
                img.classList.add('is-loaded');
                img.onload = null;
            };
            img.onerror = function () {
                img.classList.add('is-loaded'); // reveal even on error
                img.onerror = null;
            };
            img.src = src;
        }

        if (!('IntersectionObserver' in window)) {
            var pending = thumbs.slice();
            var ticking = false;
            var buffer = 300;

            function checkFallback() {
                ticking = false;
                pending = pending.filter(function (img) {
                    var rect = img.getBoundingClientRect();
                    var inRange = rect.bottom >= -buffer && rect.top <= (window.innerHeight + buffer);
                    if (inRange) {
                        loadThumb(img);
                        return false;
                    }
                    return true;
                });
                if (!pending.length) {
                    window.removeEventListener('scroll', onFallbackScroll, { passive: true });
                    window.removeEventListener('resize', onFallbackScroll);
                }
            }

            function onFallbackScroll() {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(checkFallback);
            }

            checkFallback();
            window.addEventListener('scroll', onFallbackScroll, { passive: true });
            window.addEventListener('resize', onFallbackScroll);
            return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                loadThumb(entry.target);
                obs.unobserve(entry.target);
            });
        }, {
            rootMargin: '300px 0px',
            threshold: 0
        });

        thumbs.forEach(function (img) { observer.observe(img); });
    }

    function open(idx) {
        current = idx;
        show(current);
        lightbox.classList.add('active');
        document.documentElement.classList.add('gallery-lightbox-open');
        document.body.classList.add('gallery-lightbox-open');
    }

    function close() {
        lightbox.classList.remove('active');
        document.documentElement.classList.remove('gallery-lightbox-open');
        document.body.classList.remove('gallery-lightbox-open');
    }

    function show(idx) {
        var el      = items[idx];
        var src     = el.getAttribute('href');
        var caption = el.getAttribute('data-description') || '';

        lbImage.src = src;
        lbImage.alt = caption;

        if (caption) {
            lbCaption.textContent = caption;
            lbCaption.style.display = 'block';
        } else {
            lbCaption.style.display = 'none';
        }

        lbCounter.textContent = (idx + 1) + ' / ' + items.length;

        // Preload neighbours
        preload((idx - 1 + items.length) % items.length);
        preload((idx + 1) % items.length);
    }

    function preload(idx) {
        var img = new Image();
        img.src = items[idx].getAttribute('href');
    }

    function prev() { current = (current - 1 + items.length) % items.length; show(current); }
    function next() { current = (current + 1) % items.length; show(current); }

    function onImageTap(e) {
        if (!lightbox.classList.contains('active')) return;
        var rect = lbImage.getBoundingClientRect();
        var midX = rect.left + (rect.width / 2);
        if (e.clientX < midX) {
            prev();
        } else {
            next();
        }
    }

    function onKey(e) {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape')      close();
        if (e.key === 'ArrowLeft')   prev();
        if (e.key === 'ArrowRight')  next();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
