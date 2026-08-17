import { usePage } from '@inertiajs/vue3';

// Every user-visible datetime must render in the WORKSPACE's timezone, not
// the viewer's browser timezone (an admin or a traveling owner could be in
// a different one). `workspace` is shared on every Inertia page — see
// HandleInertiaRequests — so it's read directly here rather than threading
// a timezone prop through every component that formats a date.
function workspaceTimeZone(): string | undefined {
    return usePage().props?.workspace?.timezone ?? undefined;
}

const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

// Builds a Date from Y-M-D components directly (local, no timezone
// interpretation at all) — the correct way to handle a date-ONLY value
// (due_date, appointment_date: no time-of-day, no instant, no timezone).
// `new Date('2026-08-17')` would instead be parsed as UTC midnight per the
// ES spec, which can render as the previous/next day once formatted.
function dateOnlyToLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function formatMoney(amount: string | number, currency = 'EUR'): string {
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;

    return new Intl.NumberFormat('sl-SI', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(value) ? value : 0);
}

export function normalizeMoneyInput(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';

    const amount = typeof value === 'string' ? Number(value.replace(',', '.')) : value;

    return Number.isFinite(amount) ? amount.toFixed(2) : String(value);
}

export function formatDate(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = {}): string {
    if (!value) return '—';

    // A plain "YYYY-MM-DD" is a calendar date with no time component (e.g.
    // due_date, appointment_date) — format it as-is, with no timezone
    // conversion. Anything else is an absolute instant (created_at,
    // issued_at, …) and must be converted to the workspace's timezone.
    if (DATE_ONLY_PATTERN.test(value)) {
        return new Intl.DateTimeFormat('sl-SI', {
            month: 'short',
            day: 'numeric',
            ...opts,
        }).format(dateOnlyToLocalDate(value));
    }

    return new Intl.DateTimeFormat('sl-SI', {
        month: 'short',
        day: 'numeric',
        timeZone: workspaceTimeZone(),
        ...opts,
    }).format(new Date(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('sl-SI', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: workspaceTimeZone(),
    }).format(new Date(value));
}

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('sl-SI', {
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

    const rtf = new Intl.RelativeTimeFormat('sl', { numeric: 'auto' });

    if (Math.abs(diffMinutes) < 60) return rtf.format(diffMinutes, 'minute');
    if (Math.abs(diffHours) < 24) return rtf.format(diffHours, 'hour');

    return rtf.format(diffDays, 'day');
}

// "YYYY-MM-DD" for the current instant, as a calendar date in the
// workspace's own timezone — the basis for "is this date-only value today
// (or before it)", matching the workspace, not the viewer's browser.
function workspaceTodayYMD(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: workspaceTimeZone(),
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
}

export function isPastDue(dateString: string | null | undefined): boolean {
    if (!dateString) return false;

    if (DATE_ONLY_PATTERN.test(dateString)) {
        return dateString < workspaceTodayYMD();
    }

    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return date < today;
}

export function isToday(dateString: string | null | undefined): boolean {
    if (!dateString) return false;

    if (DATE_ONLY_PATTERN.test(dateString)) {
        return dateString === workspaceTodayYMD();
    }

    const date = new Date(dateString);
    const today = new Date();

    return (
        date.getFullYear() === today.getFullYear() &&
        date.getMonth() === today.getMonth() &&
        date.getDate() === today.getDate()
    );
}
