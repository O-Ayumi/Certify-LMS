const API = '/api/v1/notifications';

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const list = root.querySelector('[data-notification-popover-items]');
    const empty = root.querySelector('[data-notification-popover-empty]');
    const loading = root.querySelector('[data-notification-popover-loading]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const unreadCount = root.querySelector('[data-notification-popover-unread-count]');
    const markAll = root.querySelector('[data-notification-popover-mark-all]');
    const template = root.querySelector('[data-notification-popover-row-template]');

    let notifications = [];
    let tab = 'all';
    let csrfReady = false;

    async function ensureCsrfCookie() {
        if (csrfReady) return;

        const response = await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
        if (!response.ok) throw new Error('CSRF cookie could not be initialized.');
        csrfReady = true;
    }

    function xsrfToken() {
        const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
        return decodeURIComponent(token);
    }

    async function request(url, options = {}) {
        await ensureCsrfCookie();

        const response = await fetch(url, {
            credentials: 'include',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
                ...(options.headers ?? {}),
            },
        });

        if (response.status === 401 || response.status === 419) {
            window.location.href = '/login';
            return null;
        }
        if (!response.ok) throw new Error(`Notification request failed: ${response.status}`);

        return response.json();
    }

    function setUnreadCount(count) {
        const value = Number(count) || 0;
        const label = value > 99 ? '99+' : String(value);

        unreadCount.textContent = label;
        unreadCount.classList.toggle('hidden', value === 0);
        badge.textContent = label;
        badge.classList.toggle('hidden', value === 0);
        trigger.setAttribute('aria-label', `通知 (${value} 件未読)`);
    }

    function render() {
        list.replaceChildren();

        const rows = tab === 'unread'
            ? notifications.filter((notification) => !notification.is_read)
            : notifications;
        empty.classList.toggle('hidden', rows.length !== 0);

        rows.forEach((notification) => {
            const row = template.content.cloneNode(true);
            const link = row.querySelector('[data-notification-popover-row]');

            link.href = notification.url;
            link.dataset.unread = String(!notification.is_read);
            row.querySelector('[data-notification-popover-row-title]').textContent = notification.title;
            row.querySelector('[data-notification-popover-row-message]').textContent = notification.message;
            row.querySelector('[data-notification-popover-row-time]').textContent = new Date(notification.created_at)
                .toLocaleString('ja-JP');
            row.querySelector('[data-notification-popover-row-dot]')
                .classList.toggle('invisible', notification.is_read);

            link.addEventListener('click', async (event) => {
                event.preventDefault();
                try {
                    await request(`${API}/${notification.id}/read`, { method: 'POST' });
                    window.location.href = notification.url;
                } catch (_) {
                    // 既読化に失敗した場合は対象画面へ遷移しない。
                }
            });
            list.append(row);
        });
    }

    async function load() {
        loading.classList.remove('hidden');
        empty.classList.add('hidden');

        try {
            const result = await request(API);
            if (result) {
                notifications = result.data;
                setUnreadCount(result.unread_count);
                render();
            }
        } catch (_) {
            list.replaceChildren();
            empty.textContent = '通知を読み込めませんでした。';
            empty.classList.remove('hidden');
        } finally {
            loading.classList.add('hidden');
        }
    }

    function close() {
        panel.classList.add('hidden', 'opacity-0', '-translate-y-1');
        panel.classList.remove('opacity-100', 'translate-y-0');
        panel.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', () => {
        if (!panel.classList.contains('hidden')) {
            close();
            return;
        }

        panel.classList.remove('hidden', 'opacity-0', '-translate-y-1');
        panel.classList.add('opacity-100', 'translate-y-0');
        panel.style.display = 'flex';
        trigger.setAttribute('aria-expanded', 'true');
        load();
    });

    root.querySelectorAll('[data-notification-popover-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            tab = button.dataset.notificationPopoverTab;
            root.querySelectorAll('[data-notification-popover-tab]').forEach((item) => {
                item.setAttribute('aria-selected', String(item === button));
            });
            render();
        });
    });

    markAll.addEventListener('click', async () => {
        try {
            await request(`${API}/read-all`, { method: 'POST' });
            await load();
        } catch (_) {
            // API失敗時は現在の表示を維持する。
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
}
