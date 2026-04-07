(function () {
    function getStateKey(url) {
        return 'tag_gallery_state:' + url.pathname + url.search;
    }

    function buildHistoryUrl(url, param, page) {
        var nextUrl = new URL(url, window.location.origin);
        if (!param) {
            return nextUrl;
        }

        if (page <= 1) {
            nextUrl.searchParams.delete(param);
        } else {
            nextUrl.searchParams.set(param, String(page));
        }

        return nextUrl;
    }

    function syncHistory(grid, page) {
        if (grid.dataset.historyEnabled !== '1') {
            grid.dataset.currentPage = String(page);
            return;
        }

        var param = grid.dataset.historyParam || '';
        var nextUrl = buildHistoryUrl(window.location.href, param, page);
        var state = {
            tagGalleryLoadMore: true,
            path: window.location.pathname,
            search: nextUrl.search,
            page: page,
            param: param,
            scrollY: window.scrollY || window.pageYOffset || 0
        };

        if (!param || !window.history || typeof window.history.pushState !== 'function') {
            grid.dataset.currentPage = String(page);
            try {
                sessionStorage.setItem(getStateKey(nextUrl), JSON.stringify(state));
            } catch (_) {}
            return;
        }

        window.history.pushState(state, '', nextUrl.toString());
        grid.dataset.currentPage = String(page);
        try {
            sessionStorage.setItem(getStateKey(nextUrl), JSON.stringify(state));
        } catch (_) {}
    }

    function replaceCurrentState(grid) {
        if (grid.dataset.historyEnabled !== '1') {
            return;
        }

        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var currentUrl = new URL(window.location.href);
        var currentPage = parseInt(grid.dataset.currentPage || '1', 10) || 1;
        var param = grid.dataset.historyParam || '';
        var state = Object.assign({}, window.history.state || {}, {
            tagGalleryLoadMore: true,
            path: window.location.pathname,
            search: currentUrl.search,
            page: currentPage,
            param: param,
            scrollY: window.scrollY || window.pageYOffset || 0
        });

        window.history.replaceState(state, '', currentUrl.toString());
        try {
            sessionStorage.setItem(getStateKey(currentUrl), JSON.stringify(state));
        } catch (_) {}
    }

    function readSavedState() {
        var grid = document.getElementById('gallery-grid');
        if (!grid || grid.dataset.historyEnabled !== '1') {
            return null;
        }

        if (window.history && window.history.state && window.history.state.tagGalleryLoadMore) {
            return window.history.state;
        }
        return null;
    }

    function restoreScroll(scrollY) {
        if (typeof scrollY !== 'number' || scrollY < 0) {
            return;
        }

        window.requestAnimationFrame(function () {
            window.scrollTo(0, scrollY);
        });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setLocalFlag(key) {
        if (window.localStorage) {
            localStorage.setItem(key, '1');
        }
    }

    function hasLocalFlag(key) {
        return !!(window.localStorage && localStorage.getItem(key) === '1');
    }

    function showToast(message, level) {
        if (window.showToast) {
            window.showToast(message, level);
        }
    }

    function hideLoadMore(button, wrap, buttonLabel) {
        if (buttonLabel) {
            buttonLabel.textContent = button.dataset.idleLabel || 'Ещё фото';
        }
        button.disabled = true;
        if (wrap) {
            wrap.hidden = true;
            wrap.style.display = 'none';
        } else {
            button.hidden = true;
            button.style.display = 'none';
        }
    }

    function initLike(buttonEl) {
        if (!buttonEl || buttonEl.dataset.likeBound === '1') {
            return;
        }

        var type = buttonEl.dataset.likeType;
        var id = buttonEl.dataset.id;
        if (!type || !id) {
            return;
        }

        var storageKey = 'liked_' + type + '_' + id;
        if (hasLocalFlag(storageKey)) {
            buttonEl.classList.add('active');
        }

        buttonEl.dataset.likeBound = '1';
        buttonEl.addEventListener('click', async function (event) {
            event.preventDefault();
            event.stopPropagation();

            try {
                var response = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {Accept: 'application/json'},
                    body: new URLSearchParams({type: type, id: id})
                });
                if (!response.ok) {
                    throw new Error('bad');
                }

                var data = await response.json();
                if (typeof data.likes !== 'undefined') {
                    document.querySelectorAll('[data-like-type="' + type + '"][data-id="' + id + '"]').forEach(function (node) {
                        var count = node.querySelector('[data-like-count]');
                        if (count) {
                            count.textContent = data.likes;
                        }
                        node.classList.add('active');
                    });
                }

                setLocalFlag(storageKey);
                showToast(data.already ? 'Уже лайкнули' : 'Лайк засчитан', 'success');
            } catch (error) {
                showToast('Не удалось поставить лайк', 'danger');
            }
        });
    }

    function initMasterLike(buttonEl) {
        if (!buttonEl || buttonEl.dataset.masterLikeBound === '1') {
            return;
        }

        var id = buttonEl.dataset.id;
        var token = buttonEl.dataset.token;
        if (!id || !token) {
            return;
        }

        var storageKey = 'master_liked_gallery_' + id;
        if (hasLocalFlag(storageKey)) {
            buttonEl.classList.add('active');
        }

        buttonEl.dataset.masterLikeBound = '1';
        buttonEl.addEventListener('click', async function (event) {
            event.preventDefault();
            event.stopPropagation();

            try {
                var response = await fetch('/api/v1/gallery/master-like', {
                    method: 'POST',
                    headers: {Accept: 'application/json'},
                    body: new URLSearchParams({gallery_item_id: id, _token: token})
                });
                var data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || data.error || 'bad');
                }

                var current = parseInt(buttonEl.querySelector('.g-master-likes')?.textContent || '0', 10) || 0;
                var fresh = parseInt(data.master_likes ?? current, 10) || 0;
                var count = data.already ? Math.max(current, fresh) : Math.max(current + 1, fresh);
                document.querySelectorAll('.master-like-chip[data-id="' + id + '"] .g-master-likes').forEach(function (node) {
                    node.textContent = String(count);
                });
                document.querySelectorAll('.master-like-chip[data-id="' + id + '"]').forEach(function (node) {
                    node.classList.add('active');
                });

                setLocalFlag(storageKey);
                showToast(data.message || (data.already ? 'Уже отмечено' : 'Признание мастера засчитано'), 'success');
            } catch (error) {
                showToast(error.message || 'Не удалось отметить работу', 'danger');
            }
        });
    }

    function renderSimpleItem(item) {
        var anchor = document.createElement('a');
        var title = item.title || '';
        var frame = document.createElement('div');
        var image = document.createElement('img');
        var meta = document.createElement('div');
        var views = document.createElement('span');
        var viewsCount = document.createElement('span');
        var likeButton = document.createElement('button');
        var likeCount = document.createElement('span');

        anchor.className = 'masonry-item';
        anchor.href = item.href || '#';
        anchor.setAttribute('data-id', String(item.id || 0));
        if (item.slug) {
            anchor.setAttribute('data-slug', item.slug);
        }

        frame.className = 'frame';

        image.src = item.thumb || item.full || '';
        image.alt = title;
        image.loading = 'lazy';
        image.decoding = 'async';
        frame.appendChild(image);

        meta.className = 'meta-floating';

        views.textContent = '👁 ';
        viewsCount.className = 'g-views';
        viewsCount.textContent = String(item.views || 0);
        views.appendChild(viewsCount);
        meta.appendChild(views);

        likeButton.type = 'button';
        likeButton.className = 'like-chip';
        likeButton.dataset.likeType = 'gallery';
        likeButton.dataset.id = String(item.id || 0);
        likeButton.dataset.likes = String(item.likes || 0);
        likeButton.appendChild(document.createTextNode('❤ '));
        likeCount.className = 'g-likes';
        likeCount.setAttribute('data-like-count', '');
        likeCount.textContent = String(item.likes || 0);
        likeButton.appendChild(likeCount);
        meta.appendChild(likeButton);

        frame.appendChild(meta);

        if (title) {
            var caption = document.createElement('div');
            caption.className = 'caption';
            caption.textContent = title;
            frame.appendChild(caption);
        }

        anchor.appendChild(frame);

        return anchor;
    }

    function renderMasterBadge(item) {
        if (!item.submitted_by_master || !item.author_name) {
            return '';
        }

        var initial = item.author_name.trim().charAt(0).toUpperCase();
        var badge = document.createElement('span');
        var avatarWrap = document.createElement('span');
        var name = document.createElement('span');

        badge.className = 'gallery-master-badge';
        badge.setAttribute('aria-label', item.author_name);

        avatarWrap.className = 'gallery-master-badge__avatar';
        if (item.author_avatar) {
            var avatar = document.createElement('img');
            avatar.src = item.author_avatar;
            avatar.alt = item.author_name;
            avatarWrap.appendChild(avatar);
        } else {
            avatarWrap.textContent = initial;
        }

        name.className = 'gallery-master-badge__name';
        name.textContent = item.author_name;

        badge.appendChild(avatarWrap);
        badge.appendChild(name);

        return badge;
    }

    function renderMasterLike(item, grid) {
        var count = String(item.master_likes || 0);
        var icon =
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
                '<path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>' +
                '<path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>' +
            '</svg>';
        var countNode = document.createElement('span');
        countNode.className = 'g-master-likes';
        countNode.textContent = count;

        if (grid.dataset.masterLikeEnabled === '1' && item.can_master_like) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'master-like-chip master-like-chip--action';
            button.dataset.id = String(item.id || 0);
            button.dataset.token = grid.dataset.masterLikeToken || '';
            button.innerHTML = icon;
            button.appendChild(countNode);
            return button;
        }

        var passive = document.createElement('span');
        passive.className = 'master-like-chip';
        passive.dataset.id = String(item.id || 0);
        passive.innerHTML = icon;
        passive.appendChild(countNode);
        return passive;
    }

    function renderGalleryListItem(item, grid) {
        var article = document.createElement('article');
        var title = item.title || '';
        var lightboxEnabled = grid.dataset.lightboxEnabled === '1';
        var href = lightboxEnabled ? (item.full || item.href || '#') : (item.href || '#');
        var link = document.createElement('a');
        var image = document.createElement('img');
        var meta = document.createElement('div');
        var views = document.createElement('span');
        var viewsCount = document.createElement('span');
        var likeButton = document.createElement('button');
        var likeCount = document.createElement('span');
        var masterLike = renderMasterLike(item, grid);
        var badge = renderMasterBadge(item);

        article.className = 'masonry-item';
        link.className = lightboxEnabled ? 'frame lightbox-trigger' : 'frame';
        link.href = href;
        link.dataset.id = String(item.id || 0);
        if (item.slug) {
            link.dataset.slug = item.slug;
        }
        if (lightboxEnabled) {
            link.dataset.full = item.full || item.thumb || '';
            link.dataset.title = title;
            link.dataset.likes = String(item.likes || 0);
            link.dataset.masterLikes = String(item.master_likes || 0);
            link.dataset.canMasterLike = item.can_master_like ? '1' : '0';
            link.dataset.views = String(item.views || 0);
        }

        image.src = item.thumb || item.full || '';
        image.alt = title;
        image.loading = 'lazy';
        image.decoding = 'async';
        link.appendChild(image);

        if (badge) {
            link.appendChild(badge);
        }

        if (title) {
            var caption = document.createElement('div');
            caption.className = 'caption';
            caption.textContent = title;
            link.appendChild(caption);
        }

        meta.className = 'meta-floating';
        views.textContent = '👁 ';
        viewsCount.className = 'g-views';
        viewsCount.textContent = String(item.views || 0);
        views.appendChild(viewsCount);
        meta.appendChild(views);

        likeButton.type = 'button';
        likeButton.className = 'like-chip';
        likeButton.dataset.likeType = 'gallery';
        likeButton.dataset.id = String(item.id || 0);
        likeButton.dataset.likes = String(item.likes || 0);
        likeButton.appendChild(document.createTextNode('❤ '));
        likeCount.className = 'g-likes';
        likeCount.setAttribute('data-like-count', '');
        likeCount.textContent = String(item.likes || 0);
        likeButton.appendChild(likeCount);
        meta.appendChild(likeButton);

        meta.appendChild(masterLike);
        article.appendChild(link);
        article.appendChild(meta);

        return article;
    }

    function initGrid(grid) {
        var button = document.getElementById('gallery-load-more');
        var wrap = document.getElementById('gallery-more-wrap');
        if (!grid || !button) {
            return;
        }
        var buttonLabel = button.querySelector('.gallery-load-more-btn__label') || button.querySelector('span:last-child') || button;

        var renderMode = grid.dataset.renderMode || 'simple';
        var renderItem = renderMode === 'gallery-list'
            ? function (item) { return renderGalleryListItem(item, grid); }
            : renderSimpleItem;

        var loadingPromise = null;

        async function loadMoreInternal(targetPage) {
            var nextPage = parseInt(grid.dataset.nextPage || '0', 10);
            var idleLabel = button.dataset.idleLabel || 'Ещё фото';
            var loadingLabel = button.dataset.loadingLabel || 'Загрузка...';

            if (!nextPage) {
                hideLoadMore(button, wrap, buttonLabel);
                return;
            }

            button.disabled = true;
            buttonLabel.textContent = loadingLabel;

            try {
                var url = new URL(grid.dataset.api, window.location.origin);
                url.searchParams.set('page', String(nextPage));
                if (grid.dataset.sort) {
                    url.searchParams.set('sort', grid.dataset.sort);
                }

                var response = await fetch(url.toString(), {headers: {Accept: 'application/json'}});
                if (!response.ok) {
                    throw new Error('bad');
                }

                var payload = await response.json();
                (payload.items || []).forEach(function (item) {
                    var node = renderItem(item);
                    grid.appendChild(node);
                    node.querySelectorAll('[data-like-type]').forEach(initLike);
                    node.querySelectorAll('.master-like-chip--action').forEach(initMasterLike);
                });

                if (payload.pagination && payload.pagination.page) {
                    syncHistory(grid, parseInt(payload.pagination.page, 10) || nextPage);
                }

                if (payload.pagination && payload.pagination.has_more && payload.pagination.next_page) {
                    grid.dataset.nextPage = String(payload.pagination.next_page);
                    button.disabled = false;
                    buttonLabel.textContent = idleLabel;
                } else {
                    grid.dataset.nextPage = '';
                    hideLoadMore(button, wrap, buttonLabel);
                }
            } catch (error) {
                button.disabled = false;
                buttonLabel.textContent = idleLabel;
                showToast('Не удалось загрузить ещё фото', 'danger');
            }
        }

        function loadMore() {
            if (loadingPromise) {
                return loadingPromise;
            }
            loadingPromise = loadMoreInternal().finally(function () {
                loadingPromise = null;
            });
            return loadingPromise;
        }

        async function ensureRenderedPage(targetPage) {
            var desired = parseInt(targetPage || '1', 10) || 1;
            var rendered = parseInt(grid.dataset.currentPage || '1', 10) || 1;
            if (desired <= rendered) {
                return;
            }

            while (rendered < desired) {
                if (!grid.dataset.nextPage) {
                    break;
                }
                await loadMore();
                rendered = parseInt(grid.dataset.currentPage || '1', 10) || 1;
            }
        }

        function navigateToState(page, scrollY) {
            if (grid.dataset.historyEnabled !== '1') {
                return;
            }

            var param = grid.dataset.historyParam || '';
            var targetUrl = buildHistoryUrl(window.location.href, param, page);
            try {
                sessionStorage.setItem(getStateKey(targetUrl), JSON.stringify({
                    tagGalleryLoadMore: true,
                    path: targetUrl.pathname,
                    search: targetUrl.search,
                    page: page,
                    param: param,
                    scrollY: scrollY
                }));
            } catch (_) {}
            window.location.href = targetUrl.toString();
        }

        async function reconcileState(state) {
            if (!state) {
                return;
            }

            var desiredPage = parseInt(state.page || '1', 10) || 1;
            var renderedPage = parseInt(grid.dataset.currentPage || '1', 10) || 1;

            if (desiredPage < renderedPage) {
                navigateToState(desiredPage, state.scrollY || 0);
                return;
            }

            await ensureRenderedPage(desiredPage);
            restoreScroll(state.scrollY || 0);
        }

        grid.querySelectorAll('[data-like-type]').forEach(initLike);
        grid.querySelectorAll('.master-like-chip--action').forEach(initMasterLike);
        button.addEventListener('click', loadMore);

        if (grid.dataset.historyEnabled === '1') {
            var scrollTicking = false;
            window.addEventListener('scroll', function () {
                if (scrollTicking) {
                    return;
                }
                scrollTicking = true;
                window.requestAnimationFrame(function () {
                    replaceCurrentState(grid);
                    scrollTicking = false;
                });
            }, {passive: true});

            document.addEventListener('click', function (event) {
                var anchor = event.target.closest('a');
                if (!anchor) {
                    return;
                }
                if (anchor.closest('#gallery-grid')) {
                    replaceCurrentState(grid);
                }
            }, true);

            window.addEventListener('pagehide', function () {
                replaceCurrentState(grid);
            });

            reconcileState(readSavedState());
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var grid = document.getElementById('gallery-grid');
        initGrid(grid);

        if (!grid) {
            return;
        }

        window.addEventListener('popstate', function (event) {
            if (grid.dataset.historyEnabled !== '1') {
                return;
            }

            var param = grid.dataset.historyParam || '';
            if (!param) {
                return;
            }
            var state = event.state && event.state.tagGalleryLoadMore ? event.state : null;
            if (!state) {
                return;
            }

            var desiredPage = parseInt(state.page || '1', 10) || 1;
            var renderedPage = parseInt(grid.dataset.currentPage || '1', 10) || 1;
            if (desiredPage < renderedPage) {
                navigateToState(desiredPage, state.scrollY || 0);
                return;
            }
            ensureRenderedPage(desiredPage).then(function () {
                restoreScroll(state.scrollY || 0);
            });
        });
    });
})();
