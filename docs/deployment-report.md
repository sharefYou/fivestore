# Deployment report — Alwaysdata

Written for whoever operates this deployment. Date: **2026-10-27**.

**Status: live and verified at https://fivestore.alwaysdata.net** over HTTPS,
with 15/15 browser checks passing against the real address.

One operational task remains before the site can be administered: no
administrator account exists yet (section 7, item 1).

---

## 1. Environment

| Item | Value |
| --- | --- |
| Site | `https://fivestore.alwaysdata.net` |
| Host (SSH) | `ssh-fivestore.alwaysdata.net`, port 22, user `fivestore` |
| Application path | `/home/fivestore/five_store` (outside the web root) |
| Document Root | `/home/fivestore/www` (bridge — see section 5) |
| Server OS | Linux 6.18.57-alwaysdata, x86_64 |
| PHP (CLI and site) | 8.4.27 — the site's version was measured, not assumed |
| Composer | 2.9.8 |
| Node / npm | 24.22.1 / 11.19.1 |
| Database | **MariaDB 11.8.9** at `mysql-fivestore.alwaysdata.net:3306`, database `fivestore_store` |
| Laravel | 13.38.0 |
| Memory limit / upload max | 256M / 256M |

No secrets appear in this report. `.env` is at the application root, outside
any web-served directory, mode `600`.

---

## 2. What was done, and the result

| Step | Result |
| --- | --- |
| SSH key generated, registered, connection established | ✅ `uid=559824(fivestore)` |
| File upload | ✅ 505 files (1.9 MB) via tar + scp into `~/five_store` |
| Excluded from upload | `.env`, `node_modules`, `vendor`, `.git`, `tests`, dev logs, dev images, local build |
| `composer install --no-dev --optimize-autoloader` | ✅ 6 927 classes |
| PHP extensions required by the lock file | ✅ all 23 present (`intl`, `gd`, `pdo_mysql`, `zip`, `exif`, …) |
| Asset build | ✅ built locally, uploaded — manifest 79 entries, **0 missing files** |
| `APP_KEY` | ✅ generated **on the server**; the development key was never copied |
| `storage:link` | ✅ |
| Permissions | ✅ `storage` and `bootstrap/cache` = 750, `.env` = 600; no `chmod 777` |
| Pre-migration backup | ✅ `~/backups/pre-migrate-20261027-070737.sql`, mode 600, outside the web root |
| Migrations | ✅ 8/8 via `migrate --force`; no `migrate:fresh`, no `db:wipe` |
| Production caches | ✅ `config:cache`, `route:cache`, `view:cache` |
| Live HTTPS | ✅ valid certificate for `*.alwaysdata.net` |
| Live browser verification | ✅ **15/15** (section 4) |

---

## 3. The database compatibility question, answered

The schema guarantees one primary image per product with a unique index over a
**virtual generated column**. That was designed against MySQL 8.4 locally,
while Alwaysdata runs **MariaDB 11.8.9**, so it could not be assumed to work.

It was proven on the target database, in a throwaway table, **before** any
migration ran:

```
server version: 11.8.9-MariaDB
1. CREATE TABLE with virtual generated column + unique index: OK
2. one primary + two secondary rows accepted: OK
3. second primary rejected by the database: OK
4. a different product may have its own primary: OK
5. preflight table dropped
```

After migrating, the live table carries the design intact:

```
`primary_flag` tinyint(4) GENERATED ALWAYS AS (case when `is_primary` = 1 then 1 else NULL end) VIRTUAL,
UNIQUE KEY `product_images_single_primary_unique` (`product_id`,`primary_flag`),
CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
```

Delete rules on the server: `products → categories: RESTRICT`,
`product_images → products: CASCADE`.

**No migration was modified**, because none needed to be.

---

## 4. Verification against the live site

`npm run test:e2e:live` (real browser, real HTTPS address) — **15/15**:

HTTPS responds and the root selects a language · Arabic page mounts the Vue
application · Arabic direction is RTL · Arabic heading renders · Arabic page
uses the Tajawal font · English direction is LTR · the language switcher
navigates · `/ar/categories` renders · `/en/products` renders · the admin
panel redirects anonymous visitors to the login · the login form renders ·
unknown page returns a localized 404 · no framework internals leak on errors ·
the production stylesheet is applied · no JavaScript errors.

HTTP-level results:

| URL | Result |
| --- | --- |
| `/` | 302 → `/ar` |
| `/ar`, `/en` | 200 — titles `Five Store` |
| `/ar/categories` | 200 — `الفئات — Five Store` |
| `/en/products` | 200 — `Products — Five Store` |
| `/sitemap.xml` | 200 |
| `/admin/login` | 200 |
| `/en/nope` | 404 — `الصفحة غير موجودة — Five Store` |

Assets served over HTTPS from the site's own domain: `app-*.css` 34 307 B and
`app-*.js` 194 010 B, both 200. A browser sweep of `/ar`, `/en`,
`/ar/categories`, `/en/products` and `/admin/login` recorded **no failed
requests**.

Security headers on the live site (the strict production policy, confirming
`APP_ENV=production`):

```
X-Content-Type-Options  nosniff
X-Frame-Options         DENY
Referrer-Policy         strict-origin-when-cross-origin
Permissions-Policy      camera=(), microphone=(), geolocation=(), interest-cohort=()
Content-Security-Policy default-src 'self'; base-uri 'self'; frame-ancestors 'none';
                        form-action 'self'; object-src 'none'; img-src 'self' data:;
                        font-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
                        connect-src 'self'
```

Exposure check — all returned **404**: `/.env`, `/composer.json`, `/artisan`,
`/storage/logs/laravel.log`, `/vendor/autoload.php`, `/index.php/../.env`,
`/.git/config`.

The two entries in `storage/logs/laravel.log` are from deployment commands,
not the application ("Please provide a valid cache path" from a
`composer install` that ran before `storage/framework` existed, fixed
immediately; and "Required." from an `artisan` command invoked without an
argument). **The application has logged no runtime errors.**

---

## 5. How the document root is wired, and why

The site's Document Root is `/home/fivestore/www`, set in the Alwaysdata
panel. Two approaches were tried:

**Attempt 1 — symlink `~/www` → `~/five_store/public`. Failed, reverted.**
Apache passed the unresolved symlink path to PHP, so `__DIR__` became
`/home/fivestore/www` and Laravel's `require __DIR__.'/../vendor/autoload.php'`
resolved to `/home/fivestore/vendor/autoload.php`, which does not exist. The
browser showed a PHP fatal error — raised *before* Laravel boots, so
`APP_DEBUG=false` could not suppress it. Reverted in the same session; the
original page was restored from `~/backups/www-original` and verified.

**Attempt 2 — a front-controller bridge. In place and working.**
`~/www` is a real directory containing only:

- `index.php` — a copy of Laravel's front controller with the base path set
  explicitly to `/home/fivestore/five_store`, so `__DIR__` is irrelevant.
- `.htaccess` — a copy of Laravel's, for the rewrite rules.
- symlinks to `build`, `css`, `js`, `fonts`, `storage`, `favicon.ico`,
  `robots.txt` in the application's `public` directory.
- `index.html.disabled` — the original Alwaysdata placeholder, kept.

The application's source, `.env`, `vendor` and `storage` stay outside the web
root; only the files above are reachable over HTTP, which the exposure check
in section 4 confirms.

**The cleaner alternative** remains available: set Document Root to
`/home/fivestore/five_store/public` in **Web → Sites**. After that the bridge
is redundant and `~/www` can be restored from `~/backups/www-original`. Both
arrangements are functionally equivalent; the bridge exists because the panel
setting had not taken effect.

---

## 6. Deploying an update

```sh
# 1. Build assets locally (the server cannot — see section 7, item 5)
npm run build

# 2. Upload source and assets (exclude .env, node_modules, vendor, .git, tests)
#    then on the server:
ssh fivestore@ssh-fivestore.alwaysdata.net
cd ~/five_store
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # take a backup first
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The `~/www` bridge needs no change on an update: it links to the application's
`public` directory rather than copying from it.

---

## 7. Open items and risks

| # | Item | Severity | Action |
| --- | --- | --- | --- |
| 1 | **No administrator account exists on production.** `/admin` is reachable but nobody can sign in | Blocking for administration | Over SSH, in an interactive terminal: `cd ~/five_store && php artisan app:create-admin`. It prompts for a password that is never echoed, logged, or passed as an argument. It was deliberately not created from this session, so no production password is known to it |
| 2 | **The database password passed through a chat transcript**, at the operator's choice | Medium | Rotate it in the panel, update `DB_PASSWORD` in `~/five_store/.env`, then `php artisan config:cache` |
| 3 | **SSH host fingerprints were trusted on first contact**, not confirmed against the panel | Medium | Compare with Remote access → SSH. ED25519: `SHA256:5i/vJYokzNsnXAeHkwzEm+3kxPQWwsRzwXFPQ7oOvNI` |
| 4 | **PHP displays fatal errors to the browser** when a failure happens before Laravel boots (observed in section 5) | Medium | Set `display_errors = Off` for the site's PHP configuration in the panel |
| 5 | **Assets cannot be built on the server**: `vite build` dies with a bus error (node + esbuild on NFS-backed shared storage) | Low | Keep building locally and uploading `public/build`; re-verify the manifest after each build |
| 6 | **The catalog is empty.** No demo data was seeded into production on purpose | Low | Add real content through `/admin` |
| 7 | **Backups are manual.** One pre-migration dump exists | Medium | Schedule recurring dumps of the database and `storage/app/public`, stored outside the web root, and rehearse a restore |
| 8 | **No HSTS.** The application deliberately does not send it | Low | Enable in the site configuration once HTTPS is confirmed on every address |
| 9 | **The copy of `docs/` on the server is from the upload** and predates this report | Cosmetic | Re-upload `docs/` if the server copy should match |
| 10 | **No scheduler or queue worker configured.** Nothing currently dispatches jobs | Low | Only needed if queued work is added later |

---

## 8. Rollback

- **Application:** `~/five_store` can be replaced wholesale; nothing outside it
  was modified except `~/www`.
- **Document root:** restore the original page with
  `cp -a ~/backups/www-original/index.html ~/www/index.html` and remove the
  bridge files, or point Document Root back at a plain directory.
- **Database:** `~/backups/pre-migrate-20261027-070737.sql` holds the state
  before the migrations ran (the database was empty at that point).
- **Uploaded images:** live in `~/five_store/storage/app/public` and are not
  touched by a code update.
