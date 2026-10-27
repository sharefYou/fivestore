/**
 * Post-deployment checks against the real site, in a real browser.
 *
 * Unlike tests/e2e/storefront.e2e.mjs, this makes no assumption that the
 * catalog has content: a freshly deployed site is empty, and an empty catalog
 * is a valid state that must still render.
 *
 *   E2E_BASE_URL=https://fivestore.alwaysdata.net npm run test:e2e:live
 */
import { chromium } from 'playwright-core';

const BASE = process.env.E2E_BASE_URL ?? 'https://fivestore.alwaysdata.net';
const CHANNEL = process.env.E2E_BROWSER_CHANNEL ?? 'msedge';

const results = [];

function check(name, condition, detail = '') {
    results.push({ name, ok: Boolean(condition) });
    console.log(`${condition ? 'PASS' : 'FAIL'}  ${name}${condition || !detail ? '' : ` — ${detail}`}`);
}

async function run(page) {
    // Errors are attributed to the page that produced them, so the check
    // below can ignore the 404 this script requests on purpose.
    const errors = [];
    const deliberate = () => page.url().includes('/products/none');
    page.on('console', (m) => {
        if (m.type() === 'error' && !deliberate()) errors.push(`${page.url()} :: ${m.text()}`);
    });
    page.on('pageerror', (e) => {
        if (!deliberate()) errors.push(`${page.url()} :: ${e}`);
    });

    // The site is served over HTTPS and the root picks a language.
    const root = await page.goto(BASE, { waitUntil: 'networkidle' });
    check('HTTPS responds and the root selects a language',
        root?.status() === 200 && /\/(ar|en)$/.test(page.url()), page.url());

    // Arabic page mounts and is genuinely right to left.
    await page.goto(`${BASE}/ar`, { waitUntil: 'networkidle' });
    check('Arabic page mounts the Vue application', (await page.locator('#app > div').count()) > 0);
    check('Arabic page direction is RTL', (await page.getAttribute('html', 'dir')) === 'rtl');
    check('Arabic heading renders', ((await page.locator('h1').first().innerText()).trim().length) > 0);

    const font = await page.evaluate(() =>
        getComputedStyle(document.body).fontFamily.toLowerCase());
    check('Arabic page uses the Arabic font', font.includes('tajawal'), font);

    // English page.
    await page.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    check('English page direction is LTR', (await page.getAttribute('html', 'dir')) === 'ltr');

    // Language switching is a real navigation between two addresses.
    const before = page.url();
    await page.locator('nav a[lang="ar"]').first().click();
    await page.waitForURL(/\/ar/, { timeout: 15000 }).catch(() => {});
    check('The language switcher navigates', page.url() !== before && page.url().includes('/ar'), page.url());

    // Listing pages render even with nothing published yet.
    for (const path of ['/ar/categories', '/en/products']) {
        const r = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
        const text = (await page.locator('main').innerText()).trim();
        check(`${path} renders`, r?.status() === 200 && text.length > 0, `status=${r?.status()}`);
    }

    // The admin panel is reachable and closed.
    const admin = await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });
    check('The admin panel redirects anonymous visitors to the login',
        page.url().includes('/admin/login'), `status=${admin?.status()} url=${page.url()}`);
    check('The login form renders', (await page.locator('input[type="email"]').count()) > 0);

    // A missing page is a localized 404, not a stack trace.
    const missing = await page.goto(`${BASE}/ar/products/none`, { waitUntil: 'networkidle' });
    const body = await page.locator('body').innerText();
    check('Unknown page returns a localized 404', missing?.status() === 404);
    check('No framework internals leak on errors',
        !/Illuminate\\|Stack trace|vendor\//.test(body), body.slice(0, 80));

    // Styling actually arrived (the stylesheet is applied, not just linked).
    await page.goto(`${BASE}/ar`, { waitUntil: 'networkidle' });
    const styled = await page.evaluate(() => {
        const bg = getComputedStyle(document.body).backgroundColor;
        return bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent';
    });
    check('The production stylesheet is applied', styled);

    check('No JavaScript errors on the deployed site', errors.length === 0, errors.slice(0, 3).join(' | '));
}

const browser = await chromium.launch({ channel: CHANNEL });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

try {
    await run(page);
} catch (error) {
    console.error('Live check failed:', error.message);
    process.exitCode = 1;
} finally {
    await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed against ${BASE}`);
if (failed.length > 0) process.exitCode = 1;
