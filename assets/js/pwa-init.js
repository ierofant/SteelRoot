(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    function hasManifest() {
        return document.querySelector('link[rel="manifest"]') !== null;
    }

    function notify(message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'info');
            return;
        }
        console.info(message);
    }

    var refreshing = false;

    function showUpdateBanner(worker) {
        if (document.getElementById('pwa-update-banner')) {
            return;
        }

        var banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.className = 'pwa-update-banner';
        banner.setAttribute('role', 'alert');

        var text = document.createElement('span');
        text.className = 'pwa-update-banner__text';
        text.textContent = 'Доступна новая версия сайта.';

        var reloadBtn = document.createElement('button');
        reloadBtn.className = 'pwa-update-banner__reload';
        reloadBtn.type = 'button';
        reloadBtn.textContent = 'Обновить';

        var closeBtn = document.createElement('button');
        closeBtn.className = 'pwa-update-banner__close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Закрыть');
        closeBtn.textContent = '×';

        banner.appendChild(text);
        banner.appendChild(reloadBtn);
        banner.appendChild(closeBtn);
        document.body.appendChild(banner);

        requestAnimationFrame(function () {
            banner.classList.add('visible');
        });

        reloadBtn.addEventListener('click', function () {
            banner.remove();
            if (worker && worker.postMessage) {
                worker.postMessage({ type: 'SKIP_WAITING' });
            } else {
                window.location.reload();
            }
        });

        closeBtn.addEventListener('click', function () {
            banner.classList.remove('visible');
            setTimeout(function () { banner.remove(); }, 300);
        });
    }

    function registerInstallPrompt() {
        var deferredPrompt = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            window.promptPwaInstall = function () {
                if (!deferredPrompt) {
                    return Promise.resolve(false);
                }
                return deferredPrompt.prompt().then(function () {
                    return deferredPrompt.userChoice;
                }).then(function (choice) {
                    deferredPrompt = null;
                    return choice && choice.outcome === 'accepted';
                });
            };
            window.dispatchEvent(new CustomEvent('pwa:install-available'));
        });

        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            window.promptPwaInstall = null;
            notify('App installed');
        });
    }

    function registerServiceWorker() {
        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });

        navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then(function (registration) {
            var updateShown = false;

            function onInstalling(worker) {
                if (!worker) {
                    return;
                }
                worker.addEventListener('statechange', function () {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller && !updateShown) {
                        updateShown = true;
                        showUpdateBanner(worker);
                    }
                });
            }

            if (registration.waiting && !updateShown) {
                updateShown = true;
                showUpdateBanner(registration.waiting);
            }

            registration.addEventListener('updatefound', function () {
                onInstalling(registration.installing);
            });
        }).catch(function (error) {
            console.warn('PWA registration failed:', error);
        });
    }

    function init() {
        if (!hasManifest()) {
            return;
        }
        registerInstallPrompt();
        registerServiceWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
