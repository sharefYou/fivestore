/**
 * End-to-end checks for the admin panel, in a real browser.
 *
 * Proves that the panel's forms actually do something: that an administrator
 * can sign in, that a wrong password cannot, that creating a category works,
 * and that publishing it puts it on the public storefront.
 *
 * Requires a running server, an administrator account, and a Chromium-based
 * browser installed locally (no browser is downloaded):
 *
 *   php artisan serve --port=8123
 *   php artisan app:create-admin
 *   E2E_ADMIN_EMAIL=... E2E_ADMIN_PASSWORD=... npm run test:e2e:admin
 */
import { chromium } from 'playwright-core';

const BASE = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8123';
const CHANNEL = process.env.E2E_BROWSER_CHANNEL ?? 'msedge';
const EMAIL = process.env.E2E_ADMIN_EMAIL;
const PASSWORD = process.env.E2E_ADMIN_PASSWORD;

if (!EMAIL || !PASSWORD) {
    console.error('Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD before running this script.');
    process.exit(1);
}

const results = [];

function check(name, condition, detail = '') {
    results.push({ name, ok: Boolean(condition) });
    console.log(`${condition ? 'PASS' : 'FAIL'}  ${name}${condition || !detail ? '' : ` — ${detail}`}`);
}

/** Filament field ids contain a dot, which is a class selector in CSS. */
const field = (name) => `[id="form.${name}"]`;

const stamp = Date.now().toString().slice(-6);
const nameEn = `E2E Category ${stamp}`;
const nameAr = `فئة اختبار ${stamp}`;
const slugEn = `e2e-category-${stamp}`;
const slugAr = `فئة-اختبار-${stamp}`;

async function run(page) {
    // 1. A wrong password is refused.
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', 'definitely-not-the-password');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    check('A wrong password does not sign anyone in', page.url().includes('/admin/login'), page.url());

    // 2. The real credentials do work.
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }).catch(() => {}),
        page.click('button[type="submit"]'),
    ]);
    check('An administrator signs in', !page.url().includes('/login'), page.url());

    // 3. The dashboard shows figures read from the database.
    await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });
    const dashboard = await page.locator('body').innerText();
    check('The dashboard renders catalog statistics', /\d/.test(dashboard) && dashboard.length > 200);

    // 4. Create a category through the form, filling both language tabs.
    await page.goto(`${BASE}/admin/categories/create`, { waitUntil: 'networkidle' });

    const tabs = page.locator('[role="tab"]');
    await tabs.nth(0).click();
    await page.fill(field('name_ar'), nameAr);
    await page.fill(field('slug_ar'), slugAr);

    await tabs.nth(1).click();
    await page.waitForTimeout(300);
    await page.fill(field('name_en'), nameEn);
    await page.fill(field('slug_en'), slugEn);

    // Publish it, so the storefront should pick it up.
    const publish = page.locator(field('is_active'));
    if ((await publish.getAttribute('aria-checked')) !== 'true') {
        await publish.click();
    }
    check('The publish toggle is on', (await publish.getAttribute('aria-checked')) === 'true');

    // The logout control is also a submit button, so target the form's own.
    await page.locator('form button[type="submit"]').last().click();
    await page.waitForURL((url) => !url.pathname.endsWith('/create'), { timeout: 20000 }).catch(() => {});

    // Filament stays on the create page when validation fails, so leaving it
    // is the signal that the record was written.
    check(
        'The category form saves without validation errors',
        !page.url().includes('/create'),
        page.url(),
    );

    // 5. The record is in the admin listing.
    await page.goto(`${BASE}/admin/categories`, { waitUntil: 'networkidle' });
    check(
        'The new category appears in the admin listing',
        (await page.getByText(nameEn, { exact: false }).count()) > 0,
    );

    // 6. And on the public storefront, which is the point of publishing.
    await page.goto(`${BASE}/en/categories`, { waitUntil: 'networkidle' });
    const onStorefront = (await page.getByText(nameEn, { exact: false }).count()) > 0;
    check('A published category reaches the storefront', onStorefront);

    const response = await page.goto(`${BASE}/en/categories/${slugEn}`, { waitUntil: 'networkidle' });
    check('Its own page opens for visitors', response?.status() === 200, `status=${response?.status()}`);

    // 7. The Arabic URL of the same category works too.
    const arabic = await page.goto(`${BASE}/ar/categories/${encodeURIComponent(slugAr)}`, {
        waitUntil: 'networkidle',
    });
    check('The Arabic slug resolves to the same category', arabic?.status() === 200, `status=${arabic?.status()}`);
}

const browser = await chromium.launch({ channel: CHANNEL });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

try {
    await run(page);
} catch (error) {
    console.error('Admin E2E run failed:', error.message);
    process.exitCode = 1;
} finally {
    // Always close, otherwise the open browser keeps this process alive.
    await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed.`);
console.log(`Created test category "${nameEn}" — delete it from /admin/categories when done.`);

if (failed.length > 0) {
    process.exitCode = 1;
}
