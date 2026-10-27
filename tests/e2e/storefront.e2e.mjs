/**
 * End-to-end checks that run the storefront in a real browser.
 *
 * These complement the PHPUnit suite, which can only see the props a page is
 * given: here the Vue application actually mounts and renders, so a broken
 * component or a wrong text direction is caught.
 *
 * Requires a running server and a Chromium-based browser installed locally
 * (Microsoft Edge by default; no browser is downloaded):
 *
 *   php artisan serve --port=8123
 *   npm run test:e2e
 */
import { chromium } from 'playwright-core';

const BASE = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8123';
const CHANNEL = process.env.E2E_BROWSER_CHANNEL ?? 'msedge';

const results = [];

function check(name, condition, detail = '') {
    results.push({ name, ok: Boolean(condition), detail });
    const mark = condition ? 'PASS' : 'FAIL';
    console.log(`${mark}  ${name}${condition || !detail ? '' : ` — ${detail}`}`);
}

async function run() {
    const browser = await chromium.launch({ channel: CHANNEL });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    // Errors are recorded with the page that produced them, so the checks
    // below can ignore the ones this script asks for on purpose (the 404 and
    // the redirect to the login form).
    const consoleErrors = [];
    const expectedFailure = (url) =>
        url.includes('this-does-not-exist') || url.includes('/admin');

    page.on('console', (message) => {
        if (message.type() === 'error' && !expectedFailure(page.url())) {
            consoleErrors.push(`${page.url()} :: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => {
        if (!expectedFailure(page.url())) {
            consoleErrors.push(`${page.url()} :: ${error}`);
        }
    });

    // 1. The Arabic home page mounts and renders right to left.
    await page.goto(`${BASE}/ar`, { waitUntil: 'networkidle' });
    check('Arabic home page mounts the Vue app', (await page.locator('#app > div').count()) > 0);
    check(
        'Arabic page direction is RTL',
        (await page.getAttribute('html', 'dir')) === 'rtl',
        `dir=${await page.getAttribute('html', 'dir')}`,
    );
    const headingAr = (await page.locator('h1').first().innerText()).trim();
    check('Arabic hero heading is rendered', headingAr.length > 0, headingAr);

    // 2. Categories come from the database and are clickable.
    await page.goto(`${BASE}/ar/categories`, { waitUntil: 'networkidle' });
    const categoryCards = await page.locator('article').count();
    check('Category listing renders cards', categoryCards > 0, `${categoryCards} cards`);

    // Inertia navigates without a page load, so wait for the URL itself
    // rather than for a load event that never fires.
    await page.locator('article h3 a').first().click();
    await page.waitForURL(/\/ar\/categories\/.+/, { timeout: 10000 }).catch(() => {});
    check('A category opens its own page', /\/ar\/categories\/.+/.test(page.url()), page.url());

    // 3. The language switch keeps the visitor on the same record.
    const arabicCategoryUrl = page.url();
    await page.locator('nav[aria-label] a[lang="en"]').first().click();
    await page.waitForURL(/\/en\//, { timeout: 10000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    check(
        'Switching to English stays on a category page',
        page.url().includes('/en/categories/') && page.url() !== arabicCategoryUrl,
        page.url(),
    );
    check(
        'English page direction is LTR',
        (await page.getAttribute('html', 'dir')) === 'ltr',
    );

    // 4. A product page shows its gallery and switches image.
    await page.goto(`${BASE}/en/products`, { waitUntil: 'networkidle' });
    await page.locator('article h3 a').first().click();
    await page.waitForURL(/\/en\/products\/.+/, { timeout: 10000 }).catch(() => {});
    check('A product page opens', /\/en\/products\/.+/.test(page.url()), page.url());

    const thumbnails = await page.locator('[role="group"] button').count();
    if (thumbnails > 1) {
        const firstSrc = await page.locator('.card img').first().getAttribute('src');
        await page.locator('[role="group"] button').nth(1).click();
        await page.waitForTimeout(250);
        const secondSrc = await page.locator('.card img').first().getAttribute('src');
        check('Gallery thumbnail changes the main image', firstSrc !== secondSrc);
    } else {
        check('Gallery thumbnail changes the main image', true, 'skipped: product has one image');
    }

    // 5. Search runs against the server.
    await page.goto(`${BASE}/en/products`, { waitUntil: 'networkidle' });
    const firstName = (await page.locator('article h3 a').first().innerText()).trim();
    await page.fill('#search-term', firstName.split(' ')[0]);
    await page.click('button[type="submit"]');
    await page.waitForURL(/[?&]q=/, { timeout: 10000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    check('Search returns results', (await page.locator('article').count()) > 0, page.url());

    await page.fill('#search-term', 'zzzz-nothing-matches-zzzz');
    await page.click('button[type="submit"]');
    await page.waitForURL(/zzzz-nothing-matches-zzzz/, { timeout: 10000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    check(
        'An empty result set shows the empty state, not a crash',
        (await page.locator('article').count()) === 0,
    );

    // 6. Unpublished content stays unreachable from the browser too.
    const missing = await page.goto(`${BASE}/en/products/this-does-not-exist`);
    check('Unknown product returns 404', missing?.status() === 404, `status=${missing?.status()}`);

    // 7. The admin panel is closed to anonymous visitors.
    const admin = await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });
    check(
        'Anonymous visitor is sent to the admin login',
        page.url().includes('/admin/login'),
        `status=${admin?.status()} url=${page.url()}`,
    );

    // 8. Keyboard users get the skip link first.
    await page.goto(`${BASE}/en`, { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => document.activeElement?.getAttribute('href') ?? '');
    check('First tab stop is the skip link', focused === '#main', `focus=${focused}`);

    check(
        'No JavaScript or resource errors on the pages under test',
        consoleErrors.length === 0,
        consoleErrors.slice(0, 5).join(' | '),
    );

    await browser.close();

    const failed = results.filter((result) => !result.ok);
    console.log(`\n${results.length - failed.length}/${results.length} checks passed.`);

    if (failed.length > 0) {
        process.exitCode = 1;
    }
}

run().catch((error) => {
    console.error('E2E run failed:', error);
    process.exitCode = 1;
});
