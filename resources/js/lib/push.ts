export function isPushSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window;
}

function urlBase64ToUint8Array(base64String: string): BufferSource {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0))).buffer as ArrayBuffer;
}

// Idempotent: safe to call every time a reminder is created — resolves
// instantly if permission/subscription are already in place, and never
// throws (denial or an unsupported browser just means no push, silently).
export async function ensurePushSubscribed(vapidPublicKey: string): Promise<boolean> {
    if (!isPushSupported() || !vapidPublicKey) return false;

    try {
        const registration = await navigator.serviceWorker.register('/sw.js');

        let permission = Notification.permission;
        if (permission === 'default') permission = await Notification.requestPermission();
        if (permission !== 'granted') return false;

        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
        }

        await window.axios.post(route('push-subscriptions.store'), subscription.toJSON());
        return true;
    } catch {
        return false;
    }
}
