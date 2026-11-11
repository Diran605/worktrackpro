import { useAuthStore } from '../stores/auth';

/**
 * The signed-in user's organisation timezone (undefined = the browser's own timezone).
 */
export const orgTimezone = () => {
    try {
        return useAuthStore().user?.organisation?.timezone || undefined;
    } catch {
        return undefined;
    }
};

/**
 * Today's date as YYYY-MM-DD in the organisation's timezone.
 * (new Date().toISOString() gives the UTC date, which is yesterday or tomorrow near midnight.)
 */
export const todayInOrg = () => {
    const parts = { year: 'numeric', month: '2-digit', day: '2-digit' };
    try {
        return new Intl.DateTimeFormat('en-CA', { ...parts, timeZone: orgTimezone() }).format(new Date());
    } catch {
        // Unknown timezone name: fall back to the browser's local date
        return new Intl.DateTimeFormat('en-CA', parts).format(new Date());
    }
};
