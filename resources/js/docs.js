// Small enhancements for the public documentation library. Every page works without this script.

// Dropdown menus (<details class="docs-menu">): one open at a time, close on outside click / Escape.
const menus = () => document.querySelectorAll('details.docs-menu');

document.addEventListener('click', (event) => {
    menus().forEach((menu) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') menus().forEach((menu) => { menu.open = false; });
});

menus().forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (menu.open) menus().forEach((other) => { if (other !== menu) other.open = false; });
        const filter = menu.querySelector('[data-az-filter]');
        if (menu.open && filter) setTimeout(() => filter.focus(), 0);
    });
});

// "Systems A–Z" filter box
document.querySelectorAll('[data-az-filter]').forEach((input) => {
    const list = document.querySelector(input.dataset.azFilter);
    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        list?.querySelectorAll('[data-az-item]').forEach((item) => {
            item.hidden = term !== '' && !item.dataset.azItem.toLowerCase().includes(term);
        });
    });
});

// Copy link
document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyLink || window.location.href);
            const label = button.querySelector('[data-label]');
            const original = label?.textContent;
            if (label) label.textContent = 'Link copied';
            setTimeout(() => { if (label) label.textContent = original; }, 2000);
        } catch {
            window.prompt('Copy this link:', button.dataset.copyLink || window.location.href);
        }
    });
});

// Mobile: show/hide the header navigation and the contents tree
document.querySelectorAll('[data-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector(button.dataset.toggle)?.classList.toggle('hidden');
    });
});
