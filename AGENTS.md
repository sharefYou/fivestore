# Five Store — agent guidelines

Laravel Boost is already installed in this repository, and it has written the
project's working guidelines into **[CLAUDE.md](CLAUDE.md)**. Read that file
first; it covers the conventions, verification commands, testing rules and
framework-version notes for this application.

Do **not** re-run `composer require laravel/boost` or `php artisan boost:install`:
this file previously carried the one-time bootstrap instructions for that, and
they have been completed.

## Project-specific rules that outrank convenience

- **Never run `RealisticCatalogSeeder` against production.** It deletes every
  category and product — including any entered by hand — before rebuilding.
  It refuses to run when `APP_ENV=production`; leave
  `CATALOG_SEEDER_ALLOW_DESTRUCTIVE` unset.
- **Build front-end assets locally**, not on the server: `vite build` fails
  there with a bus error. Upload `public/build` with the release.
- **Tests run against MySQL**, not SQLite, because the schema relies on a
  generated column with a unique index. See `phpunit.xml`.
- **`docs/deployment-plan.md`** is the deployment procedure. Follow it rather
  than improvising; it exists because several steps are order-dependent.
