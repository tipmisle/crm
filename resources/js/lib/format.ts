export function formatMoney(amount: string | number, currency = 'EUR'): string {
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(value) ? value : 0);
}

export function formatDate(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = {}): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        ...opts,
    }).format(new Date(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

export function relativeTime(value: string | null | undefined): string {
    if (!value) return '';

    const date = new Date(value);
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffMinutes = Math.round(diffMs / 60000);
    const diffHours = Math.round(diffMs / 3600000);
    const diffDays = Math.round(diffMs / 86400000);

    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (Math.abs(diffMinutes) < 60) return rtf.format(diffMinutes, 'minute');
    if (Math.abs(diffHours) < 24) return rtf.format(diffHours, 'hour');

    return rtf.format(diffDays, 'day');
}

export function isPastDue(dateString: string | null | undefined): boolean {
    if (!dateString) return false;

    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return date < today;
}

export function isToday(dateString: string | null | undefined): boolean {
    if (!dateString) return false;

    const date = new Date(dateString);
    const today = new Date();

    return (
        date.getFullYear() === today.getFullYear() &&
        date.getMonth() === today.getMonth() &&
        date.getDate() === today.getDate()
    );
}
