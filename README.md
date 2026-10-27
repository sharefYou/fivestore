# Five Store — bilingual product catalog

A product catalog with a public storefront in Arabic and English, and a
protected admin panel for managing categories, products and images.

It is a **catalog, not a shop**: there is no cart, no checkout, no orders and
no stock. Price and currency are deliberately absent — see
[docs/technical-decisions.md](docs/technical-decisions.md).

## Features

**Storefront** (`/ar`, `/en`)

- Home page with a hero, categories, featured products and recent products.
- Category listing, category pages with paginated products, product pages
  with an image gallery and related products.
- Search across Arabic names, English names and SKU, filterable by category.
- Full right-to-left Arabic and left-to-right English, including fonts,
  layout and the direction of every control.
- Language switching that keeps the visitor on the same record, because each
  record has its own slug per language.
- Server-rendered metadata per URL: title, description, canonical, `hreflang`
  alternates, Open Graph tags and Product structured data, plus a sitemap at
  `/sitemap.xml`.

**Admin panel** (`/admin`)

- Dashboard with counts read from the database and a recently-updated list.
- Category and product management: both languages, slugs, publishing, display
  order, featured flag, SKU, and multiple product images with one primary
  image and a drag-to-reorder list.
- A small site settings page: names, hero texts, logo, contact details,
  social links, footer and default meta descriptions.

## Requirements

Verified on the versions below.

| Component | Version used | Notes |
| --- | --- | --- |
| PHP | 8.5.11 | Laravel 13 requires `^8.3`; `intl`, `gd`, `pdo_mysql`, `fileinfo` must be enabled |
| Composer | 2.8.12 | |
| Laravel | 13.38 | |
| MySQL | 8.4.3 LTS | the schema uses a generated column and a unique index on it |
| Node.js | 24.21 | |
| npm | 11.19 | |

## Installation

```sh
git clone <repository> five_store
cd five_store

composer install
cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE five_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=five_store
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Then run the migrations, link the storage directory and build the assets:

```sh
php artisan migrate
php artisan storage:link      # serves uploaded images from /storage
npm install
npm run build
```

Optionally load a demo catalog (categories, products and generated placeholder
images, including deliberately unpublished rows so the publishing rules are
visible):

```sh
php artisan db:seed
```

### Creating an administrator

No administrator is seeded, because a seeded account means a password in the
repository. Create one interactively; the password is never echoed, never
passed as an argument and never logged:

```sh
php artisan app:create-admin
```

The same command grants admin access to an existing user. Sign in at `/admin`.

### Running it

```sh
php artisan serve          # http://localhost:8000
npm run dev                # Vite dev server with hot reload
```

`/` redirects to the visitor's language; the storefront lives under `/ar` and
`/en`.

## Tests and quality checks

```sh
php artisan test           # 112 tests
./vendor/bin/pint --test   # PHP code style
npm run type-check         # TypeScript, strict mode
npm run build              # production asset build
composer audit             # dependency advisories
npm audit                  # dependency advisories
```

The PHP suite runs against **MySQL**, not SQLite, because the schema depends on
MySQL behaviour. Create the test database once:

```sql
CREATE DATABASE five_store_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Browser tests

These drive a Chromium-based browser that is already installed (Edge by
default) through `playwright-core`; no browser is downloaded. Start a server
first, then:

```sh
php artisan serve --port=8123

npm run test:e2e           # storefront: rendering, RTL/LTR, navigation, search
npm run test:e2e:a11y      # landmarks, names, labels, measured colour contrast
E2E_ADMIN_EMAIL=you@example.com E2E_ADMIN_PASSWORD=... npm run test:e2e:admin
```

Set `E2E_BROWSER_CHANNEL=chrome` to use Chrome instead, or `E2E_BASE_URL` for a
different address. The admin script creates a category and tells you to delete
it afterwards.

## Images and storage

Uploads go to the `public` disk under `storage/app/public`, served through the
`public/storage` symlink. In-progress uploads are held on a **private** disk
(`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local`) so a half-finished file is never
reachable under `/storage` before validation accepts it. Only JPEG, PNG and
WebP are accepted; SVG is rejected because it can carry scripts. Files are stored under a UUID the
server generates, never under the name the browser sent, and every upload is
checked by decoding the image rather than by trusting its extension.

Image files are removed when the record that owns them is removed, and when an
image is replaced. A soft-deleted category or product keeps its files so a
restore brings back a complete record.

## Deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to the real
   HTTPS address.
2. Set `SESSION_SECURE_COOKIE=true` and serve the whole site over HTTPS.
3. `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
4. `php artisan migrate --force` and `php artisan storage:link`.
5. `php artisan config:cache route:cache view:cache` (re-run on every deploy).
6. Point the web server's document root at `public/`.
7. Give the web server write access to `storage/` and `bootstrap/cache/` only.
8. Create the administrator with `php artisan app:create-admin`.
9. Enable HSTS in the web server **after** confirming HTTPS works on every
   hostname the site answers on.

The application sends `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy` and a Content-Security-Policy on
storefront responses. HSTS is left to the web server on purpose.

### Backup and restore

```sh
# Backup: the database and the uploaded files are both required.
mysqldump --single-transaction --default-character-set=utf8mb4 five_store > backup.sql
tar -czf uploads.tar.gz storage/app/public

# Restore (into a non-production environment first)
mysql five_store < backup.sql
tar -xzf uploads.tar.gz
php artisan storage:link
```

A restore that brings back the database without the files leaves records
pointing at images that no longer exist, so always restore both together.
Restoring has not been exercised in this environment — do a trial restore on a
staging copy before relying on it.

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| Images are broken | `php artisan storage:link` was not run, or `FILESYSTEM_DISK` is not `public` |
| Images are blocked in the console | `APP_URL` does not match the hostname in use; image URLs are relative, so also check the `/storage` link |
| `/admin` returns 403 after signing in | The user is not an administrator: run `php artisan app:create-admin` with that email |
| Tests fail to connect | The `five_store_test` database does not exist, or the credentials in `.env` cannot reach it |
| A category refuses to be deleted | It still has products, including soft-deleted ones. Move or permanently delete them first |
| Arabic text renders in the wrong font | Assets were not rebuilt: `npm run build` |
| Changes in the admin panel are not visible | Site settings are cached; they are cleared automatically on save, otherwise `php artisan cache:clear` |

## Documentation

- [docs/technical-decisions.md](docs/technical-decisions.md) — versions chosen
  and why, architecture, data model, language and URL policy, alternatives
  rejected, assumptions made.
- [docs/implementation-status.md](docs/implementation-status.md) — what is
  done, what was actually run and its results, what is not done, known risks.
