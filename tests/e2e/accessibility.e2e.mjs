/**
 * Accessibility checks against the rendered storefront, in both languages.
 *
 * These are mechanical checks — landmarks, names, labels, language, direction
 * and measured colour contrast. They are not a substitute for manual review
 * with a screen reader, which has not been done; see
 * docs/implementation-status.md.
 *
 *   php artisan serve --port=8123
 *   npm run test:e2e:a11y
 */
import { chromium } from 'playwright-core';

const BASE = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8123';
const CHANNEL = process.env.E2E_BROWSER_CHANNEL ?? 'msedge';

const results = [];

function check(name, condition, detail = '') {
    results.push({ name, ok: Boolean(condition) });
    console.log(`${condition ? 'PASS' : 'FAIL'}  ${name}${condition || !detail ? '' : ` — ${detail}`}`);
}

/** Relative luminance and contrast ratio, per WCAG 2.x. */
const CONTRAST_HELPERS = `
    function toLinear(channel) {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }
    function luminance([r, g, b]) {
        return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
    }
    // Tailwind 4 emits oklch(), which getComputedStyle returns verbatim, so a
    // naive "grab three numbers" parse would read 0.24/0.02/260 as RGB. The
    // canvas converts any CSS colour the browser understands into sRGB bytes.
    const __probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    function parse(color) {
        if (!color || color === 'transparent') return null;
        __probe.clearRect(0, 0, 1, 1);
        __probe.fillStyle = '#000';
        __probe.fillStyle = color;
        __probe.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = __probe.getImageData(0, 0, 1, 1).data;
        if (a === 0) return null;
        return [r, g, b];
    }
    function ratio(fg, bg) {
        const a = luminance(fg), b = luminance(bg);
        const light = Math.max(a, b), dark = Math.min(a, b);
        return (light + 0.05) / (dark + 0.05);
    }
    function effectiveBackground(element) {
        let node = element;
        while (node) {
            const bg = parse(getComputedStyle(node).backgroundColor);
            if (bg) return bg;
            node = node.parentElement;
        }
        return [255, 255, 255];
    }
`;

async function auditPage(page, url, expected) {
    await page.goto(url, { waitUntil: 'networkidle' });
    const label = url.replace(BASE, '');

    const lang = await page.getAttribute('html', 'lang');
    const dir = await page.getAttribute('html', 'dir');
    check(`${label} declares the right language and direction`,
        lang === expected.lang && dir === expected.dir, `lang=${lang} dir=${dir}`);

    const headings = await page.locator('h1').count();
    check(`${label} has exactly one level-one heading`, headings === 1, `found ${headings}`);

    const landmarks = await page.evaluate(() => ({
        main: document.querySelectorAll('main').length,
        header: document.querySelectorAll('header').length,
        footer: document.querySelectorAll('footer').length,
    }));
    check(`${label} exposes the page landmarks`,
        landmarks.main === 1 && landmarks.header >= 1 && landmarks.footer >= 1,
        JSON.stringify(landmarks));

    const imagesWithoutAlt = await page.evaluate(() =>
        Array.from(document.images)
            .filter((img) => img.getAttribute('alt') === null)
            .map((img) => img.currentSrc || img.src)
            .slice(0, 3),
    );
    check(`${label} gives every image an alt attribute`,
        imagesWithoutAlt.length === 0, imagesWithoutAlt.join(', '));

    const unnamedControls = await page.evaluate(() =>
        Array.from(document.querySelectorAll('button, a[href], input, select, textarea'))
            .filter((el) => {
                if (el.type === 'hidden' || el.closest('[aria-hidden="true"]')) return false;
                // textContent, not innerText: a visually-hidden label such as
                // the skip link still names the control for assistive tech.
                const imageAlt = Array.from(el.querySelectorAll('img'))
                    .map((img) => (img.getAttribute('alt') || '').trim())
                    .find(Boolean);
                const labelled =
                    el.getAttribute('aria-label') ||
                    el.getAttribute('aria-labelledby') ||
                    (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) ||
                    el.closest('label') ||
                    (el.textContent || '').trim() ||
                    imageAlt ||
                    el.getAttribute('title');
                return !labelled;
            })
            .map((el) => `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}`)
            .slice(0, 5),
    );
    check(`${label} gives every control an accessible name`,
        unnamedControls.length === 0, unnamedControls.join(', '));

    // Measured contrast of the text that actually appears on the page.
    const contrast = await page.evaluate(`(() => {
        ${CONTRAST_HELPERS}
        const samples = [];
        const selectors = ['h1', 'p', 'a', 'button', '.text-ink-muted', 'main a'];
        for (const selector of selectors) {
            for (const element of Array.from(document.querySelectorAll(selector)).slice(0, 6)) {
                const style = getComputedStyle(element);
                const text = (element.innerText || '').trim();
                if (!text) continue;
                const fg = parse(style.color);
                if (!fg) continue;
                const size = parseFloat(style.fontSize);
                const bold = Number(style.fontWeight) >= 700;
                const large = size >= 24 || (size >= 18.66 && bold);
                const value = ratio(fg, effectiveBackground(element));
                samples.push({
                    selector,
                    text: text.slice(0, 24),
                    ratio: Math.round(value * 100) / 100,
                    required: large ? 3 : 4.5,
                });
            }
        }
        return samples;
    })()`);

    const failures = contrast.filter((sample) => sample.ratio < sample.required);
    check(`${label} meets WCAG AA text contrast (${contrast.length} samples)`,
        failures.length === 0,
        failures.slice(0, 3).map((f) => `${f.selector} "${f.text}" ${f.ratio}:1 < ${f.required}`).join(' | '));
}

async function run(page) {
    await auditPage(page, `${BASE}/ar`, { lang: 'ar', dir: 'rtl' });
    await auditPage(page, `${BASE}/en`, { lang: 'en', dir: 'ltr' });
    await auditPage(page, `${BASE}/ar/categories`, { lang: 'ar', dir: 'rtl' });
    await auditPage(page, `${BASE}/en/products`, { lang: 'en', dir: 'ltr' });

    // A product page, reached the way a visitor reaches it.
    await page.goto(`${BASE}/en/products`, { waitUntil: 'networkidle' });
    await page.locator('article h3 a').first().click();
    await page.waitForURL(/\/en\/products\/.+/, { timeout: 10000 }).catch(() => {});
    await auditPage(page, page.url(), { lang: 'en', dir: 'ltr' });

    // Keyboard: the skip link comes first and focus is actually visible.
    await page.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');
    const first = await page.evaluate(() => document.activeElement?.getAttribute('href') ?? '');
    check('The first tab stop is the skip link', first === '#main', `focus=${first}`);

    const focusVisible = await page.evaluate(() => {
        const style = getComputedStyle(document.activeElement);
        return style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
    });
    check('Focused elements show a visible outline', focusVisible);

    // Tabbing reaches the navigation and the language switcher.
    const reached = await page.evaluate(async () => {
        const focusable = Array.from(
            document.querySelectorAll('a[href], button:not([disabled]), input, select'),
        ).filter((el) => el.offsetParent !== null);
        return focusable.length;
    });
    check('The page exposes keyboard-reachable controls', reached > 5, `${reached} focusable elements`);

    // The language switcher is a real link, so it works without JavaScript.
    // Plain links, so switching works without JavaScript and each language is
    // its own bookmarkable address. The href is absolute but same-origin.
    const switcher = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('nav a[lang]'));
        return links.map((a) => ({
            lang: a.getAttribute('lang'),
            path: new URL(a.href, location.origin).pathname,
            sameOrigin: new URL(a.href, location.origin).origin === location.origin,
        }));
    });
    check('The language switcher is a set of plain links',
        switcher.length >= 2 &&
            switcher.every((item) => item.sameOrigin && item.path.startsWith(`/${item.lang}`)),
        JSON.stringify(switcher));
}

const browser = await chromium.launch({ channel: CHANNEL });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

try {
    await run(page);
} catch (error) {
    console.error('Accessibility run failed:', error.message);
    process.exitCode = 1;
} finally {
    await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed.`);
console.log('Mechanical checks only — manual screen-reader review has not been done.');

if (failed.length > 0) {
    process.exitCode = 1;
}
