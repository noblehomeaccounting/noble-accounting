// sw.js — Service Worker ng NobleHome (ilagay sa ROOT ng site, katabi ng index.php)
// Tumatakbo kahit sarado ang browser o naka-logout: tumatanggap ng push at nagpapakita ng notification.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('push', event => {
    let d = {};
    try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'NobleHome', body: event.data ? event.data.text() : '' }; }
    const title = d.title || 'NobleHome';
    event.waitUntil(self.registration.showNotification(title, {
        body: d.body || '',
        icon: d.icon,
        badge: d.badge,
        tag: d.tag || undefined,          // parehong tag = papalitan ang lumang notification
        renotify: !!d.tag,
        data: { url: d.url || self.registration.scope },
        vibrate: [80, 40, 80],
    }));
});

// Pindot sa notification → buksan ang page (o i-focus kung bukas na)
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || self.registration.scope;
    event.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of wins) {
            if (w.url.split('#')[0] === url && 'focus' in w) return w.focus();
        }
        const first = wins.find(w => w.url.startsWith(self.registration.scope));
        if (first && 'navigate' in first) { await first.navigate(url); return first.focus(); }
        return self.clients.openWindow(url);
    })());
});