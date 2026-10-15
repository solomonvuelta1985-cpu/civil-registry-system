(function () {
    'use strict';

    var bellButtons = Array.prototype.slice.call(document.querySelectorAll('[data-notification-bell]'));
    var panel = document.getElementById('notificationPanel');
    if (!bellButtons.length || !panel) return;

    var apiUrl = bellButtons[0].getAttribute('data-api-url');
    var csrfToken = bellButtons[0].getAttribute('data-csrf-token') || '';
    var userId = bellButtons[0].getAttribute('data-user-id') || 'current';
    var animationSeenKey = 'crdms.notification-animation.v1:' + userId;
    var list = panel.querySelector('[data-notification-list]');
    var announcement = panel.querySelector('[data-notification-announcement]');
    var emptyState = panel.querySelector('[data-notification-empty]');
    var errorState = panel.querySelector('[data-notification-error]');
    var markAllButton = panel.querySelector('[data-notification-mark-all]');
    var closeButton = panel.querySelector('[data-notifications-close]');
    var animationSeenIds = Object.create(null);
    try {
        var savedAnimationIds = JSON.parse(window.localStorage.getItem(animationSeenKey) || '[]');
        if (Array.isArray(savedAnimationIds)) {
            savedAnimationIds.forEach(function (id) { animationSeenIds[String(id)] = true; });
        }
    } catch (error) {
        // Keep the in-memory set so blocked storage does not replay alerts during this page view.
    }
    var activeButton = null;
    var requestInFlight = false;
    var queuedRefreshes = [];
    var notificationItems = [];
    var pollTimer = null;
    var toastRegion = null;

    function persistAnimationSeenIds() {
        try {
            var ids = Object.keys(animationSeenIds);
            window.localStorage.setItem(animationSeenKey, JSON.stringify(ids.slice(-200)));
        } catch (error) {
            // Notifications continue to work when browser storage is unavailable.
        }
    }

    function unreadCountOf(items) {
        return items.filter(function (item) { return !Number(item.is_read); }).length;
    }

    function updateBadges(count) {
        bellButtons.forEach(function (button) {
            var badge = button.querySelector('[data-notification-count]');
            var number = Math.max(0, Number(count) || 0);
            if (badge) {
                badge.hidden = number < 1;
                badge.textContent = number > 99 ? '99+' : String(number);
            }
            button.setAttribute('aria-label', number === 1 ? 'Notifications, 1 unread' : 'Notifications, ' + number + ' unread');
        });
        if (announcement) {
            var message = count === 1 ? '1 unread notification' : (count || 0) + ' unread notifications';
            if (announcement.textContent !== message) announcement.textContent = message;
        }
        if (markAllButton) markAllButton.hidden = !(Number(count) > 0);
    }

    function readableCategory(category) {
        if (category === 'calendar') return 'Calendar';
        if (category === 'note') return 'Notes';
        return 'Maintenance';
    }

    function readableTime(value) {
        if (!value) return '';
        var date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        try {
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        } catch (error) {
            return date.toLocaleString();
        }
    }

    function addText(parent, className, value) {
        var element = document.createElement('span');
        element.className = className;
        element.textContent = value || '';
        parent.appendChild(element);
        return element;
    }

    function renderItems(items) {
        list.replaceChildren();
        notificationItems = items;
        items.forEach(function (item) {
            var isRead = Number(item.is_read) === 1;
            var target = typeof item.target_url === 'string' ? item.target_url : '';
            var node = target ? document.createElement('a') : document.createElement('button');
            node.className = 'notification-item' + (isRead ? '' : ' is-unread');
            node.setAttribute('role', 'listitem');
            if (target) {
                node.href = target;
                node.setAttribute('data-notification-target', target);
            } else {
                node.type = 'button';
            }
            node.setAttribute('data-notification-id', String(item.id));
            node.setAttribute('aria-label', (isRead ? '' : 'Unread. ') + (item.title || 'Notification'));

            var topline = document.createElement('span');
            topline.className = 'notification-item-topline';
            addText(topline, 'notification-category', readableCategory(item.category));
            if (!isRead) {
                var dot = document.createElement('span');
                dot.className = 'notification-unread-dot';
                dot.setAttribute('aria-label', 'Unread');
                topline.appendChild(dot);
            }
            node.appendChild(topline);
            addText(node, 'notification-item-title', item.title);
            if (item.message) addText(node, 'notification-item-message', item.message);
            if (item.created_at) addText(node, 'notification-item-time', readableTime(item.created_at));
            list.appendChild(node);
        });
        emptyState.hidden = items.length !== 0;
    }

    function showToast(item) {
        if (!toastRegion) {
            toastRegion = document.createElement('div');
            toastRegion.className = 'notification-toast-region';
            toastRegion.setAttribute('role', 'region');
            toastRegion.setAttribute('aria-label', 'New notifications');
            toastRegion.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastRegion);
        }
        var toast = document.createElement('div');
        toast.className = 'notification-toast';
        var title = document.createElement('strong');
        title.textContent = item.title || 'New notification';
        toast.appendChild(title);
        if (item.message) {
            var message = document.createElement('span');
            message.textContent = item.message;
            toast.appendChild(message);
        }
        toastRegion.appendChild(toast);
        bellButtons.forEach(function (button) {
            button.classList.remove('is-ringing');
            void button.offsetWidth;
            button.classList.add('is-ringing');
            window.setTimeout(function () { button.classList.remove('is-ringing'); }, 1500);
        });
        window.setTimeout(function () {
            toast.style.transition = 'opacity .2s ease, transform .2s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-5px)';
            window.setTimeout(function () { toast.remove(); }, 230);
        }, 6500);
    }

    function setError(message) {
        if (!errorState) return;
        errorState.textContent = message || 'Notifications could not be loaded. Try again shortly.';
        errorState.hidden = false;
    }

    async function fetchFeed(announceNew) {
        if (!apiUrl) return;
        if (requestInFlight) {
            return new Promise(function (resolve) {
                queuedRefreshes.push({ announceNew: !!announceNew, resolve: resolve });
            });
        }
        requestInFlight = true;
        try {
            var response = await fetch(apiUrl, { credentials: 'same-origin', cache: 'no-store' });
            var data = await response.json();
            if (!response.ok || !data || data.success !== true) {
                throw new Error((data && data.message) || 'Notifications could not be loaded.');
            }
            if (errorState) errorState.hidden = true;

            var items = Array.isArray(data.notifications) ? data.notifications : [];
            var justArrived = [];
            var hasUnseenUnread = false;
            items.forEach(function (item) {
                var id = String(item.id);
                if (Number(item.is_read) !== 1 && !animationSeenIds[id]) {
                    hasUnseenUnread = true;
                    animationSeenIds[id] = true;
                    if (announceNew) justArrived.push(item);
                }
            });
            if (hasUnseenUnread) persistAnimationSeenIds();
            renderItems(items);
            updateBadges(Number(data.unread_count) || 0);

            justArrived.slice(0, 2).forEach(showToast);
        } catch (error) {
            setError(error.message);
        } finally {
            requestInFlight = false;
            if (queuedRefreshes.length) {
                var queued = queuedRefreshes;
                queuedRefreshes = [];
                var shouldAnnounce = queued.some(function (request) { return request.announceNew; });
                fetchFeed(shouldAnnounce).then(function () {
                    queued.forEach(function (request) { request.resolve(); });
                });
            }
        }
    }

    async function postAction(payload) {
        var response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });
        var data = await response.json();
        if (!response.ok || !data || data.success !== true) {
            throw new Error((data && data.message) || 'Your read status could not be saved.');
        }
        await fetchFeed(false);
    }

    function positionPanel(button) {
        var rect = button.getBoundingClientRect();
        var panelWidth = panel.getBoundingClientRect().width;
        var left = Math.max(12, Math.min(rect.right - panelWidth, window.innerWidth - panelWidth - 12));
        panel.style.left = left + 'px';
        panel.style.right = 'auto';
        panel.style.top = Math.min(rect.bottom + 8, window.innerHeight - 120) + 'px';
    }

    function isVisible(button) {
        return !!button && button.getClientRects().length > 0;
    }

    function closePanel(restoreFocus) {
        panel.hidden = true;
        bellButtons.forEach(function (button) { button.setAttribute('aria-expanded', 'false'); });
        if (restoreFocus && activeButton) activeButton.focus();
        activeButton = null;
    }

    function openPanel(button) {
        activeButton = button;
        panel.hidden = false;
        bellButtons.forEach(function (candidate) {
            candidate.setAttribute('aria-expanded', candidate === button ? 'true' : 'false');
        });
        positionPanel(button);
        fetchFeed(false);
        closeButton.focus();
    }

    bellButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            if (!panel.hidden && activeButton === button) {
                closePanel(false);
            } else {
                openPanel(button);
            }
        });
    });

    closeButton.addEventListener('click', function () { closePanel(true); });
    markAllButton.addEventListener('click', function () {
        postAction({ action: 'read_all' }).catch(setError);
    });

    list.addEventListener('click', function (event) {
        var itemNode = event.target.closest('[data-notification-id]');
        if (!itemNode) return;
        var id = Number(itemNode.getAttribute('data-notification-id'));
        var target = itemNode.getAttribute('data-notification-target');
        if (target && (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0)) return;

        if (target) {
            event.preventDefault();
            postAction({ action: 'read', notification_id: id })
                .then(function () { window.location.assign(target); })
                .catch(setError);
        } else {
            postAction({ action: 'read', notification_id: id }).catch(setError);
        }
    });

    document.addEventListener('click', function (event) {
        if (panel.hidden) return;
        var clickedBell = bellButtons.some(function (button) { return button.contains(event.target); });
        if (!clickedBell && !panel.contains(event.target)) closePanel(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            closePanel(true);
        }
    });

    window.addEventListener('resize', function () {
        if (panel.hidden || !activeButton) return;
        if (!isVisible(activeButton)) {
            activeButton = bellButtons.find(isVisible) || null;
            if (!activeButton) {
                closePanel(false);
                return;
            }
        }
        positionPanel(activeButton);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') fetchFeed(true);
    });

    fetchFeed(true);
    pollTimer = window.setInterval(function () {
        if (document.visibilityState === 'visible') fetchFeed(true);
    }, 30000);
    window.addEventListener('pagehide', function () {
        if (pollTimer) window.clearInterval(pollTimer);
    });
})();
