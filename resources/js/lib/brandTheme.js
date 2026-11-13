// The organisation's brand colours (Organisation › Branding) for the whole portal.
// The portal is styled with Tailwind's teal (primary) and indigo (secondary);
// every shade of both is redefined from the chosen colour, so light backgrounds,
// hover states and dark text all follow it. Keep in step with App\Support\Branding::css().

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

// shade => [mix with, % of the brand colour]; 600 is the colour itself.
const SHADES = {
    50: ['white', 8], 100: ['white', 16], 200: ['white', 32], 300: ['white', 52],
    400: ['white', 74], 500: ['white', 88], 600: [null, 100],
    700: ['black', 84], 800: ['black', 68], 900: ['black', 54], 950: ['black', 38],
};

const palette = (name, color) => Object.entries(SHADES)
    .map(([shade, [base, pct]]) => `  --color-${name}-${shade}: ${base ? `color-mix(in oklab, ${color} ${pct}%, ${base})` : color};`)
    .join('\n');

export function brandCss(primary, secondary) {
    const lines = [];
    if (HEX.test(primary || '')) lines.push(palette('teal', primary));
    if (HEX.test(secondary || '')) lines.push(palette('indigo', secondary));
    return lines.length ? `:root {\n${lines.join('\n')}\n}\n` : '';
}
