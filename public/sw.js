self.addEventListener('push', (event) => {
    if (!event.data) return;

    const payload = event.data.json();
    const title = payload.title ?? 'Opomnik';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body,
            icon: payload.icon ?? '/favicon.ico',
            badge: payload.badge,
            tag: payload.tag,
            data: payload.data,
            actions: payload.actions ?? [],
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            return self.clients.openWindow(url);
        }),
    );
});
