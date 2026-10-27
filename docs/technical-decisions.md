# Technical decisions

Written for the engineers who will maintain this catalog.

Every version below was checked against the package registries on 2026-10-27
(Packagist `repo.packagist.org/p2/*.json` and `npm view`) rather than taken
from memory, and the stack was confirmed to install and run together.

## 1. Starting point

The repository was a clone of `laravel/laravel` at tag **v13.11.0** with every
tracked file deleted from the working tree. The files were restored from git
(`git checkout-index -a`, which writes only files that are absent and so
cannot overwrite anything) and the catalog was built on that skeleton. Git
history was not rewritten and no commits were made.

The machine also hosts an unrelated `store_api` database (one `products` table,
a single row, single language, with price and stock). It belongs to a different
project, it was not used as a requirement source, and it was left untouched.

## 2. Versions

| Package | Version | Why |
| --- | --- | --- |
| PHP | 8.5.11 | Already installed; satisfies Laravel 13's `^8.3` and Filament's `^8.2`. `intl` (required by Filament), `gd`, `pdo_mysql`, `fileinfo` and `exif` are all enabled |
| laravel/framework | 13.38 | The skeleton's own constraint `^13.17`; current stable |
| filament/filament | 5.11 | Current stable. `filament/support` declares `illuminate/contracts ^11.28\|^12.0\|^13.0`, so Laravel 13 is supported |
| inertiajs/inertia-laravel | 3.5 | Declares `^11.35\|^12.0\|^13.0` |
| @inertiajs/vue3 | 3.9.1 | Peer dependency `vue ^3.0.0` |
| vue | 3.5.44 | Current stable |
| typescript | 5.9.3 | See below |
| vite | 8.x | The skeleton's constraint; `@vitejs/plugin-vue` 6 accepts `^8.0.0` |
| tailwindcss | 4.x | The skeleton already used Tailwind 4 with `@tailwindcss/vite` |
| MySQL | 8.4.3 LTS | Already running locally and matching the requested 8.4 LTS |
| phpunit/phpunit | 12.5 | The skeleton's own test runner |

**TypeScript 5.9, not 7.0.** TypeScript 7.0.2 is the current `latest`, but
`vue-tsc@3.3.13` only declares `typescript >=5.0.0` and the native TypeScript 7
port's compatibility with vue-tsc's language service was not verified here.
5.9.3 is the newest version proven to work with this type checker. Revisit when
vue-tsc states TypeScript 7 support explicitly.

**Dependencies deliberately not added**

- *Ziggy* — the storefront needs only a handful of URLs, and they are already
  computed on the server in `App\Support\CatalogUrls`; shipping the whole route
  table to the browser would buy nothing.
- *A repository layer over Eloquent* — there is no second data source and no
  behaviour that query scopes do not express.
- *An i18n library for Vue* — the interface strings already exist as Laravel
  translation files, which the admin panel and the server also use. They are
  shared as props and read through a ~60-line composable, so there is one
  source of truth instead of two.
- *`laravel-lang/common`* — used once to generate the Arabic `validation`,
  `auth`, `pagination` and `passwords` files, then removed. The files live in
  `lang/ar/` and need no package at runtime; removing it also removed three
  abandoned transitive packages that `composer audit` reported. Re-add it as a
  dev dependency if those files ever need refreshing.

**Fonts.** Tajawal (Arabic) and Inter (Latin) are both SIL OFL 1.1. They are
installed as the `@fontsource/*` npm packages and bundled by Vite, so the site
makes no third-party request on page load, the licence files travel with the
dependency, and the Content-Security-Policy can stay `font-src 'self'`. The
skeleton's `bunny()` font helper was removed for the same reason.

## 3. Architecture

A single Laravel application, one database, two clearly separated front ends:

- **Storefront** — Inertia + Vue 3 + TypeScript under `/{locale}`, served by
  thin controllers in `App\Http\Controllers\Storefront`.
- **Admin** — Filament (Livewire) under `/admin`, in the same application.

No separate API was built: nothing consumes one. Inertia already carries typed
props from the controllers to Vue, so adding API Resources would be a layer
with no reader. If a mobile client ever appears, that is the moment to add a
real API and API Resources with it.

Where logic lives:

- `App\Services\SlugGenerator` — slug creation and uniqueness.
- `App\Services\ImageStorage` — storing and deleting image files.
- `App\Services\SiteSettings` — the cached settings row.
- `App\Support\CatalogUrls` / `CatalogPresenter` / `Seo` — URL building and the
  shaping of what a visitor is allowed to see.
- `App\Observers\*` — keeping files and records consistent, so behaviour is the
  same whether a change comes from the panel, a seeder or a console command.
- `App\Policies\*` — every administrative permission.

## 4. Data model

```
categories 1────* products 1────* product_images
site_settings (one row)
users (is_admin flag)
```

**Publishing.** `Product::scopePublished()` requires the product to be active
*and* its category to be active. The rule lives in the query used to find the
record, not in a filter applied afterwards, so knowing the id or the slug of
something unpublished is not access to it.

**Deleting a category.** The foreign key is `restrictOnDelete`, and
`CategoryPolicy::delete()` refuses while any product still references the
category — *including soft-deleted products*, because those rows still exist
and the database would refuse the delete anyway. The administrator is told why
rather than shown a failure.

**Soft deletes** on categories and products; product images are deleted
outright with their files. A soft-deleted record keeps its images and its
slugs, so restoring brings back a complete, still-addressable record.

**One primary image.** `product_images` has a generated column

```sql
primary_flag AS (CASE WHEN is_primary = 1 THEN 1 ELSE NULL END)
```

with a unique index on `(product_id, primary_flag)`. Secondary rows store NULL
there, and SQL unique indexes do not compare NULLs, so a product may have many
secondary images but only one primary. This was verified directly against
MySQL (the second primary insert fails with error 1062) and is covered by a
test. Form validation reports the same rule in the panel, but the database is
what guarantees it.

**SKU** is optional and unique. A unique index permits repeated NULLs, so any
number of products may have no SKU while a stated SKU must be unique.

**Indexes** follow the queries that exist: `(is_active, sort_order)` for the
category listing, `(category_id, is_active, sort_order)` for a category page,
`(is_featured, is_active)` for the home page, plus the unique slug indexes.

**Price and currency are not in the schema.** This is a catalog; adding columns
"just in case" would invite a storefront that displays them and a stakeholder
who assumes they are maintained.

## 5. Language and URLs

```
/                                 → redirect to a supported locale
/{locale}                         → home
/{locale}/categories              → category listing
/{locale}/categories/{slug}       → one category
/{locale}/products                → search and listing
/{locale}/products/{slug}         → one product
/sitemap.xml                      → published pages, with hreflang alternates
```

`{locale}` is restricted to `ar|en` by the route pattern, so an unknown
language is a 404 before any controller runs; `SetLocale` checks it again.

**Slugs are per language** (`slug_ar`, `slug_en`), each unique across the whole
table including soft-deleted rows — matching the unique index, so a restored
record cannot collide and a trashed record's URL is not silently reassigned.
On collision the generator appends `-2`, `-3`, and so on. Arabic slugs keep
their letters: `Str::slug()` transliterates to ASCII and would erase Arabic
entirely, so Arabic titles get a Unicode-preserving slug instead.

**Switching language keeps the page.** Each response carries the current page's
URL in every locale (`seo.alternates`), built from that record's own slugs.
The switcher is a plain link per language, so switching is a real navigation
that can be bookmarked and crawled, and the server sends the correct `dir` and
font for the new language.

**Translation fallback** is one policy in one place
(`HasTranslatedAttributes::translate()`): ask for the active locale, and if
that translation is empty fall back to the other locale rather than rendering
an empty block.

**The bare `/`** uses, in order: the language the visitor last read (a cookie
holding only `ar` or `en`), the browser's `Accept-Language`, then the default.

## 6. SEO and rendering

The storefront is client-rendered; server-side rendering is **not** enabled.
Because crawlers and link previews do not run JavaScript, the full metadata for
each URL — title, description, canonical, `hreflang` (plus `x-default`), Open
Graph and Product structured data — is rendered on the server in the Inertia
root template, where it is correct for that exact URL. During client-side
navigation Inertia updates the document title; the other tags are refreshed on
the next full load. Enabling Inertia SSR is the upgrade path if the page body
itself ever needs to be in the HTML.

Image URLs are **root-relative** (`/storage/...`). An absolute URL built from
`APP_URL` breaks whenever `APP_URL` and the serving hostname disagree, and is
then also blocked by the same-origin image policy — this was observed in a
browser during development. Only `og:image` and structured data, which are read
off-site, use absolute URLs.

## 7. Security decisions

- **Panel access** is `FilamentUser::canAccessPanel()` plus a policy on every
  resource. `is_admin` is not fillable, and the check reads the raw attribute
  and defaults to *false*, so a user model loaded without that column is
  refused rather than crashing or being let in.
- **Login throttling and session regeneration** come from Filament: five
  attempts per minute, and `session()->regenerate()` on success.
  `AuthenticateSession` invalidates the account's other sessions on logout.
- **Uploads** must decode as JPEG, PNG or WebP and fall inside configured
  dimension and size bounds. SVG is rejected outright: it can carry scripts and
  would be served from the site's own origin. The stored name is a server
  generated UUID. One definition of "acceptable image" (`App\Support\ImageRules`)
  is shared by the panel and by validation so the two cannot drift.
- **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy` and `Permissions-Policy` are sent on **both** the
  storefront and the admin panel. Filament panels do not use Laravel's `web`
  middleware group, so the panel has to list the middleware itself; it runs it
  as `SecurityHeaders:no-csp`.
- **Content-Security-Policy** is storefront-only, and relaxed in local
  development where Vite needs its dev server and websocket. `style-src`
  includes `'unsafe-inline'` because Vue writes inline styles on elements it
  sizes; removing that would need a nonce-based pipeline and was judged not
  worth the complexity here. The panel is excluded because Livewire and
  Filament depend on inline scripts, and a policy loose enough for them would
  be mostly decorative. That is a deliberate, stated limitation.
- **Livewire temporary uploads are pinned to a private disk.** Livewire writes
  in-progress uploads to the *default* filesystem disk, and this application
  points that at `public` so catalog images can be served — which would have
  published every half-finished upload under `/storage`, before validation,
  with Livewire's permissive default rules. The disk is therefore forced to a
  private one in `AppServiceProvider` rather than left to `.env`, so the
  protection does not depend on a deployment remembering it.
- **HSTS is not sent by the application.** It is only safe once HTTPS is known
  to work on every hostname the site answers on, so it belongs in the web
  server configuration for the real domain.
- **Errors** render plain Blade pages with no exception details; diagnostics
  stay in the log.
- **Search terms** are bound parameters, and LIKE wildcards typed by a visitor
  are escaped so they are matched as text rather than executed as patterns.
- **MFA for administrators** was *not* implemented. Filament 5 ships the
  building blocks (it already requires `pragmarx/google2fa`), but turning it on
  means recovery codes, a reset path and an enrolment flow — real scope that was
  not requested. The decision is recorded rather than silently skipped.

## 8. Testing decisions

The PHP suite runs against **MySQL**, not an in-memory SQLite database,
because the schema depends on MySQL behaviour (the generated column with its
unique index, repeated NULLs under a unique SKU index). Testing on a different
engine than production would test a different schema.

Storefront assertions read **Inertia props** rather than HTML. The page body is
rendered in the browser, so asserting on HTML text would silently pass for
Latin strings that happen to appear in the JSON payload and fail for Arabic,
which is `\u`-escaped there.

**Browser tests** use `playwright-core` driving an already-installed Edge, so
no browser binaries are downloaded. They cover what server-side tests cannot
see: that Vue mounts, that direction is genuinely RTL, that navigation and the
language switcher work, and that the panel's forms actually write records that
reach the storefront.

## 9. Assumptions made

Recorded because the brief left them open:

1. **Site name** "Five Store" / "متجر فايف", from the directory and database
   name. Changeable in the admin panel without touching code.
2. **Catalog type** — the demo data is home furnishings, chosen only to make
   the seeded catalog coherent. It carries no weight in the code.
3. **Visual identity** — a teal-based neutral palette, chosen for contrast
   rather than brand, since no brand exists yet. Defined as tokens in
   `resources/css/app.css` and changeable in one place.
4. **Arabic is the default** (`APP_LOCALE=ar`), with English the fallback.
5. **One administrative role.** No user-management screen was built, as the
   brief says not to assume one is wanted. `php artisan app:create-admin`
   creates and promotes administrators.
6. **Sub-categories were not implemented** — not requested, and not present in
   any existing data.
7. **The admin panel's language follows `APP_LOCALE`** for the whole
   installation; there is no per-user language switch inside the panel.
