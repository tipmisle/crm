function toGoogleDate(iso: string): string {
    return new Date(iso).toISOString().replace(/[-:]|\.\d{3}/g, '');
}

// Follow-ups don't have a fixed duration, so the calendar event is a
// 30-minute placeholder starting at the reminder's due time.
export function googleCalendarUrl(title: string, dueAt: string, details?: string): string {
    const start = new Date(dueAt);
    const end = new Date(start.getTime() + 30 * 60 * 1000);

    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: title,
        dates: `${toGoogleDate(start.toISOString())}/${toGoogleDate(end.toISOString())}`,
    });
    if (details) params.set('details', details);

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
}
