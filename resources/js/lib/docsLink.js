import api from './axios';

/**
 * Open a page of the public documentation library in a new tab, signed in as the current
 * staff member (so restricted manuals and chapters are readable there too).
 */
export async function openInLibrary(path) {
    // Open the tab first, while we still have the click, so popup blockers allow it.
    const tab = window.open('about:blank', '_blank');
    try {
        const res = await api.post('/auth/docs-link', { path });
        if (tab) tab.location = res.data.url;
        else window.location.href = res.data.url;
    } catch {
        // Fall back to the page itself; the reader can sign in there.
        if (tab) tab.location = path;
        else window.location.href = path;
    }
}
