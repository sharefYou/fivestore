# Implementation status

Written for whoever picks this up next, or decides whether it can be released.

Last verified: **2026-10-27**, on Windows 11, PHP 8.5.11, MySQL 8.4.3,
Node 24.21. This document was rewritten after an independent pre-release
review pass; section 4 lists what that pass found and changed.

Read this as four separate claims, which it keeps separate on purpose:

1. **Development complete** — the feature is built.
2. **Verified locally** — a command was run here and its result is recorded.
3. **Production configuration** — reviewed and documented, *not* exercised.
4. **Deployed** — **not done.** Nothing was deployed anywhere.

---

## 1. Verdict

**Ready for pre-release review, not yet ready to go live unattended.**

Everything in scope is built, and every check that can be run on a developer
machine passes: 112 automated tests, 58 browser checks across three suites,
style, strict type checking, a production build, and both dependency audits.
The review pass found and closed three security gaps (section 4).

What stands between this and production is not missing code, it is the set of
things that *cannot* be proven on this machine: real HTTPS, a non-root
database user, a proxy-aware configuration, a rehearsed restore on the target
host, and a manual accessibility review. Section 7 lists them with owners.

---

## 2. Commands actually run, and their results

| Command | Result |
| --- | --- |
| `composer install` | Installed |
| `php artisan migrate` (on an **empty** scratch database) | 8 migrations applied from nothing |
| `php artisan migrate:rollback` (scratch database) | All 8 reversed cleanly |
| `php artisan db:seed` (scratch database) | 4 categories, 12 products, 24 images, 1 settings row, **0 users** |
| `php artisan test` | **112 passed**, 719 assertions, 0 failed |
| `./vendor/bin/pint --test` | **Passed** |
| `npm run type-check` (`vue-tsc`, strict) | **Passed**, no type errors |
| `npm run build` | **Succeeded**, app bundle 194 kB (65 kB gzipped), pages code-split |
| `npm run test:e2e` | **15/15** browser checks |
| `npm run test:e2e:admin` | **9/9** browser checks |
| `npm run test:e2e:a11y` | **34/34** checks, including 111 measured contrast samples |
| `composer audit` | No advisories, no abandoned packages |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `mysqldump` → restore into a scratch database | Row counts identical (4/12/24/1) |
| `tar` backup → extract to an isolated directory | 29 files, identical checksums |

Direct database checks, run against MySQL rather than inferred:

- A second primary image for the same product is rejected (error 1062), both
  in the live schema and in the **restored** copy.
- The restored copy preserves the generated column, its unique index, and both
  foreign keys with the correct `RESTRICT` / `CASCADE` rules.
- File/row consistency: 28 rows reference a file, **0 missing files**, and the
  only unreferenced file on the disk is `.gitignore`.

No destructive command was run against the project databases. The scratch
database used for the fresh-install and restore tests was created for that
purpose and dropped afterwards; `five_store`, `five_store_test` and the
unrelated `store_api` were left intact.

---

## 3. Repository state

Every commit in the history is from upstream `laravel/laravel` (Taylor Otwell,
Nuno Maduro, Wendell Adriel, Cas Ebbers). There is **no user-authored commit**,
and at the start of the work the entire tracked tree was in a deleted state, so
there were no local modifications that could have been lost. Files were
restored with `git checkout-index -a`, which writes only absent files and
therefore cannot overwrite anything. No commits were made and no history was
rewritten.

Five tracked files are deleted, each replaced by a working equivalent, and each
recoverable with `git checkout -- <path>`:

| Deleted | Replaced by |
| --- | --- |
| `resources/js/app.js` | `resources/js/app.ts` |
| `vite.config.js` | `vite.config.ts` |
| `resources/views/welcome.blade.php` | the storefront pages |
| `tests/Feature/ExampleTest.php` | the feature suites |
| `tests/Unit/ExampleTest.php` | `tests/Unit/SlugGeneratorTest.php` |

`storage/logs/laravel.log` contains three errors, all timestamped 04:35–04:59,
**before** this work began; they reference a `codezero/browser-locale` package
that is not part of this project and is not referenced anywhere in the current
source or the package cache. The application as it stands has logged no errors.

---

## 4. The review pass: what it found and changed

### 4.1 Livewire temporary uploads were written to a public disk — fixed

Livewire stores in-progress uploads on the *default* filesystem disk. This
project sets `FILESYSTEM_DISK=public` so catalog images can be served, so
temporary uploads inherited it and would have been written to
`storage/app/public/livewire-tmp` — reachable under `/storage`, with
Livewire's default rules (any file type, up to 12 MB) and a 24-hour lifetime.

An administrator could therefore have placed an arbitrary file at a public,
same-origin URL before any validation accepted it. Post-authentication and
admin-only, so not critical, but real.

Fixed by pinning temporary uploads to a private disk in
`AppServiceProvider::register()` — so it holds regardless of a deployment's
`.env` — and documenting `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` in
`.env.example`. Verified: the effective disk resolves to
`storage/app/private`. Covered by `FileStorageExposureTest`.

### 4.2 The admin panel received no security headers at all — fixed

`SecurityHeaders` was appended to the `web` middleware group, but Filament
panels define their own middleware stack and do not use that group. The panel
was therefore served with **no** `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy` or `Permissions-Policy` — confirmed by inspecting a real
`/admin/login` response, which returned `NULL` for all four.

Earlier documentation claimed the panel was excluded from the
Content-Security-Policy only. That was wrong, and is corrected here.

Fixed by making the middleware accept a mode and adding
`SecurityHeaders:no-csp` to the panel stack: the panel now gets the four plain
headers, while the CSP stays storefront-only because Livewire and Filament
depend on inline scripts. Verified by test and by re-running the admin browser
suite, which still passes 9/9 (the anti-framing header does not break Filament).

### 4.3 `package.json` carried a UTF-8 BOM — fixed

Written by an earlier PowerShell command. It broke `JSON.parse` for at least
one tool during this review. Rewritten without the BOM.

### 4.4 Findings deliberately *not* changed

- **`ListRecords::authorizeAccess()` is empty in Filament upstream**, so the
  list pages rely on middleware rather than a second in-component check. That
  is sound here because Filament registers its `Authenticate` middleware as
  Livewire *persistent* middleware, so follow-up Livewire requests re-run it.
  This is now asserted by a test, so a future change that removes it fails the
  build rather than silently opening the panel.
- **The admin panel has a single role.** Any administrator can do anything in
  the catalog. That is the documented design, not an oversight.

---

## 5. Test coverage

**112 PHP tests.** Added during this review: `AuthorizationDepthTest` (9),
`FileStorageExposureTest` (6), `TranslationCoverageTest` (8), plus an admin
header assertion.

- **Authorization in depth** (9) — every panel URL turns away a guest and
  refuses a signed-in non-administrator; every catalog ability denied to a
  non-admin and allowed to an admin; a user model loaded without the
  `is_admin` column is refused rather than crashing; Filament authentication
  is asserted to persist across Livewire requests; the settings page
  authorizes on mount and on save; no unpublished record is exposed by any
  public route or named in the sitemap.
- **File storage exposure** (6) — temporary uploads never resolve to a public
  disk; images land in their own buckets; an unknown bucket is refused;
  removing an image deletes its file but not its siblings; purging a product
  leaves no orphan files; a soft-deleted record keeps its file so a restore is
  complete.
- **Translation coverage** (8) — every storefront and admin string exists in
  both languages with no empty values (151 assertions); the panel's direction
  follows the locale; product name and description fall back in both
  directions; the Arabic plural string carries all five forms; Arabic
  validation messages resolve.
- **Publishing rules** (9), **localization** (8), **search and pagination**
  (9), **storefront smoke** (6), **admin access** (6), **category
  management** (9), **product management** (13), **image handling** (14),
  **response hardening** (7), **slug generation** (8 unit).

**58 browser checks** across three suites: storefront behaviour (15), admin
panel (9) and accessibility (34).

---

## 6. Accessibility

Mechanical checks pass on five pages in both languages: correct `lang` and
`dir`, exactly one `h1`, the page landmarks present, every image carrying an
`alt` attribute, every control having an accessible name, the skip link as the
first tab stop, a visible focus outline, and the language switcher being plain
same-origin links.

Contrast was **measured**, not assumed: 111 samples of real rendered text
against their effective backgrounds, all meeting the WCAG AA threshold for
their size (4.5:1, or 3:1 for large text).

**WCAG 2.2 AA conformance is still not claimed.** What has not been done:
review with an actual screen reader (NVDA/VoiceOver), testing at 200% zoom and
400% reflow, a full axe-core audit, reduced-motion verification by a person,
and checking the Filament admin panel itself, which is third-party UI and was
not audited. Mechanical checks cannot replace any of that.

---

## 7. Open risks, by priority

| # | Risk | Severity | Owner / action before launch |
| --- | --- | --- | --- |
| 1 | **No production environment has been exercised.** Everything was verified on one local machine, never over real HTTPS, behind a proxy, or under load | High | Deploy to staging and re-run the browser suites against it |
| 2 | **`TrustProxies` is not configured.** Behind a load balancer `isSecure()` returns false, so redirects and the locale cookie's `Secure` flag would be wrong | High | Configure trusted proxies in `bootstrap/app.php` for the real topology |
| 3 | **Restore has not been rehearsed on the target host.** It works here, into a scratch database, with local tools | High | Rehearse on staging, including the uploads archive |
| 4 | **A local admin account exists in the development database** (`admin@five-store.test`) with a password known to this session | Medium | Delete it; it is in no seeder, migration or source file, so it cannot reach production by itself |
| 5 | **No manual accessibility review** (section 6) | Medium | Screen-reader and zoom review before a public launch |
| 6 | **No independent security review.** The protections are implemented and tested, but not reviewed by someone who did not write them | Medium | Commission one for a public launch |
| 7 | **No CSP on the admin panel**, and `style-src` on the storefront allows `'unsafe-inline'` | Medium | Accepted limitation; revisit with a nonce-based pipeline |
| 8 | **Livewire's temporary upload rules still accept any file type** (now on a private disk, 24-hour lifetime, admin-only) | Low | Optional: publish `config/livewire.php` and restrict `temporary_file_upload.rules` to the image mimes |
| 9 | **Images are not resized or re-encoded**; a 4000×4000 JPEG is served at full size | Low | Add a resize step if large uploads become common |
| 10 | **Search uses `LIKE`**; fine at this scale, will not scale to tens of thousands of products | Low | Full-text index or a search engine when needed |
| 11 | **No rate limiting on storefront search**; only the admin login is throttled | Low | Add a limiter if exposed to heavy automated traffic |
| 12 | **The settings cache clears on save only**; editing the row directly in the database needs `php artisan cache:clear` | Low | Documented behaviour |
| 13 | **No audit log.** "Recently updated" is built from timestamps and cannot say *who* changed something | Low | Add one if accountability is required |
| 14 | **No server-side rendering.** Metadata is server-rendered per URL, but the page body is not | Low | Enable Inertia SSR if required |

---

## 8. Not implemented, and why

- **Server-side rendering** — metadata is rendered server-side so crawlers and
  link previews are correct; the body is not.
- **Multi-factor authentication for administrators** — Filament 5 ships the
  parts, but enrolment, recovery codes and a reset path are real scope that was
  not requested. Recorded rather than silently skipped.
- **Password reset** — no configured mailer; administrators are created from
  the command line.
- **A user-management screen**, sub-categories, price and currency — out of
  scope by the brief.

---

## 9. Pre-launch checklist

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` on real HTTPS,
   `SESSION_SECURE_COOKIE=true`.
2. A database user with only the privileges the application needs — not root.
3. Trusted proxies configured if behind a load balancer (risk 2).
4. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`,
   then `config:cache`, `route:cache`, `view:cache`.
5. HTTPS confirmed on every hostname, and only then HSTS in the web server.
6. Write access limited to `storage/` and `bootstrap/cache/`.
7. A real administrator created with `php artisan app:create-admin`, and the
   development account removed (risk 4).
8. Backups scheduled for both the database and `storage/app/public`, and a
   restore rehearsed on the target host (risk 3).
9. Upload size limit at the web server matching `config/catalog.php`, and log
   monitoring in place.
10. The browser suites re-run against staging (risk 1).
