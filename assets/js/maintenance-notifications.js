(function () {
    'use strict';

    var script = document.currentScript;
    var statusUrl = script && script.getAttribute('data-status-url');
    var maintenanceUrl = script && script.getAttribute('data-maintenance-url');
    if (!statusUrl || !maintenanceUrl) return;

    var pollMs = 30000;
    var redirected = false;
    var memorySeen = {};
    var storageKey = 'iscanMaintenanceEvents.v1';

    function seenIds() {
        try {
            var values = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(values) ? values : [];
        } catch (error) {
            return Object.keys(memorySeen);
        }
    }

    function remember(id) {
        memorySeen[id] = true;
        try {
            var ids = seenIds().filter(function (value) { return value !== id; });
            ids.push(id);
            localStorage.setItem(storageKey, JSON.stringify(ids.slice(-60)));
        } catch (error) {
            // Per-page memory still prevents duplicate polls when storage is blocked.
        }
    }

    function showToast(event) {
        var region = document.getElementById('maintenanceToastRegion');
        if (!region) {
            region = document.createElement('div');
            region.id = 'maintenanceToastRegion';
            region.setAttribute('role', 'region');
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-label', 'System notifications');
            region.style.cssText = 'position:fixed;right:20px;top:20px;z-index:100000;display:flex;flex-direction:column;gap:10px;width:min(390px,calc(100vw - 32px));';
            document.body.appendChild(region);
        }

        var card = document.createElement('div');
        card.style.cssText = 'background:#111827;color:#fff;border-left:4px solid #f59e0b;border-radius:10px;padding:14px 16px;box-shadow:0 12px 32px rgba(15,23,42,.24);font:14px/1.45 Inter,Segoe UI,sans-serif;';
        var title = document.createElement('strong');
        title.textContent = event.title || 'System maintenance update';
        title.style.cssText = 'display:block;margin-bottom:4px;font-size:14px;';
        card.appendChild(title);

        if (event.message) {
            var message = document.createElement('div');
            message.textContent = event.message;
            card.appendChild(message);
        }
        if (event.starts_at || event.ends_at) {
            var windowText = document.createElement('div');
            windowText.textContent = [event.starts_at, event.ends_at].filter(Boolean).join(' — ');
            windowText.style.cssText = 'margin-top:7px;color:#d1d5db;font-size:12px;';
            card.appendChild(windowText);
        }
        region.appendChild(card);
        window.setTimeout(function () {
            card.style.transition = 'opacity .25s ease,transform .25s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(12px)';
            window.setTimeout(function () { card.remove(); }, 280);
        }, 9000);
    }

    function poll() {
        if (redirected || document.visibilityState === 'hidden') return;
        fetch(statusUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) return;
                var known = seenIds();
                var newToastShown = false;
                (Array.isArray(data.notifications) ? data.notifications : []).forEach(function (event) {
                    if (!event || !event.id || known.indexOf(event.id) !== -1 || memorySeen[event.id]) return;
                    remember(event.id);
                    showToast(event);
                    newToastShown = true;
                });
                if (data.logout === true) {
                    redirected = true;
                    window.setTimeout(function () {
                        window.location.replace(maintenanceUrl);
                    }, newToastShown ? 1800 : 0);
                }
            })
            .catch(function () {
                // Retry on the next interval after a network interruption.
            });
    }

    poll();
    window.setInterval(poll, pollMs);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') poll();
    });
})();
