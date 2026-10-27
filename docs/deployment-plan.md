# Deployment plan — price, pagination and catalog release

Written for whoever runs this deployment. Prepared **2026-10-28**.

**Nothing in this plan has been executed.** The live site at
`https://fivestore.alwaysdata.net` is still running the previous release. Read
section 1 before starting; it contains the one decision that must be made
first.

---

## 1. What changes on the server, and the one rule

This release adds a `price` column, paginates every listing, and adds the
image fetcher. It does **not** change authentication, storage layout, or the
site's own settings.

> **The catalog seeder must never run on production.**
> `RealisticCatalogSeeder` deletes every category and product — including any
> the shop owner created by hand — before rebuilding. It now refuses to run
> when `APP_ENV=production` unless `CATALOG_SEEDER_ALLOW_DESTRUCTIVE=true` is
> set, which should stay unset. Production content is entered through `/admin`.

The production catalog is currently **empty**; nothing was ever seeded there.
After this release the storefront will still show empty listings until the
owner adds categories and products. That is expected, not a failure.

---

## 2. Pre-flight, before touching anything

Run from your machine:

```sh
# The release must be green locally first.
php artisan test
./vendor/bin/pint --test
npm run type-check
npm run build        # assets are built here: the server cannot (section 4)
```

Then on the server, confirm the database accepts the new column shape before
the migration runs for real. This touches a temporary table only:

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net
cd ~/five_store
php artisan tinker --execute="
DB::statement('CREATE TEMPORARY TABLE _price_check (price DECIMAL(10,2) UNSIGNED NULL)');
DB::statement('INSERT INTO _price_check (price) VALUES (1250.00), (NULL)');
echo 'rows: '.DB::table('_price_check')->count().PHP_EOL;
"
```

`UNSIGNED` on `DECIMAL` is deprecated in MySQL 8.0.17+ and still supported by
MariaDB; production runs **MariaDB 11.8.9**, where it is accepted. Confirm the
statement succeeds before continuing. If it fails, stop — the migration would
fail the same way, and the fix is to drop `->unsigned()` and keep `min:0`
validation, which already enforces the same rule.

---

## 3. Backups — mandatory, before the migration

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net
cd ~
mkdir -p backups && chmod 700 backups
STAMP=$(date +%Y%m%d-%H%M%S)

# Database. Credentials come from .env; never put them on the command line.
php -r '
$e=[]; foreach(file("/home/fivestore/five_store/.env",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){
  if(str_starts_with($l,"#")||!str_contains($l,"="))continue; [$k,$v]=explode("=",$l,2); $e[$k]=trim($v,"\"\x27");}
file_put_contents("/tmp/.dbcnf","[client]\nhost=".$e["DB_HOST"]."\nuser=".$e["DB_USERNAME"]."\npassword=\"".$e["DB_PASSWORD"]."\"\n");
chmod("/tmp/.dbcnf",0600); echo $e["DB_DATABASE"];' > /tmp/dbname
mysqldump --defaults-extra-file=/tmp/.dbcnf --single-transaction \
  --default-character-set=utf8mb4 "$(cat /tmp/dbname)" > ~/backups/pre-price-$STAMP.sql
rm -f /tmp/.dbcnf /tmp/dbname
chmod 600 ~/backups/pre-price-$STAMP.sql

# Uploaded images and the current application files.
tar -czf ~/backups/storage-$STAMP.tar.gz -C ~/five_store storage/app/public
tar -czf ~/backups/app-$STAMP.tar.gz --exclude=vendor --exclude=node_modules \
  --exclude=storage/app/public -C ~ five_store

ls -lh ~/backups
```

Check each file is non-empty and the SQL dump ends with `-- Dump completed`:

```sh
tail -1 ~/backups/pre-price-$STAMP.sql
```

Do not proceed without all three.

---

## 4. Build the assets locally

`vite build` dies on the server with a bus error (node + esbuild on NFS-backed
shared storage), so assets are built on your machine and uploaded:

```sh
npm run build
php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true);
 $miss=0; foreach($m as $e){foreach(array_merge([$e["file"]],$e["css"]??[]) as $f){ if(!is_file("public/build/".$f)) $miss++; }}
 echo "entries: ".count($m).", missing: ".$miss.PHP_EOL;'
```

Both numbers must be sane (entries > 0, missing = 0) before uploading.

---

## 5. Upload the code

Build one archive that excludes everything the server owns or generates:

```sh
tar -czf /tmp/release.tar.gz \
  --exclude='./.git' --exclude='./.github' --exclude='./node_modules' \
  --exclude='./vendor' --exclude='./.env' --exclude='./tests' \
  --exclude='./public/storage' --exclude='./storage/app/public' \
  --exclude='./storage/app/private' --exclude='./storage/logs' \
  --exclude='./storage/framework/cache/data' --exclude='./storage/framework/sessions' \
  --exclude='./storage/framework/views' \
  --exclude='./.claude' --exclude='./.mcp.json' --exclude='./boost.json' \
  --exclude='./CLAUDE.md' --exclude='./AGENTS.md' \
  --exclude='./docs' --exclude='./.phpunit.result.cache' \
  --exclude='./tsconfig.tsbuildinfo' --exclude='./fivestore-security-fixes.patch' \
  --exclude='*.log' .
```

Then **inspect the archive that was produced**, rather than trusting the
command. Note the `$` anchors: an unanchored `\.env` also matches
`.env.example`, which belongs in the release and would read as a false alarm.

```sh
# Each of these must print 0.
tar -tzf /tmp/release.tar.gz | grep -cE '^\./\.env$'
tar -tzf /tmp/release.tar.gz | grep -cE '^\./(vendor|node_modules)/'
tar -tzf /tmp/release.tar.gz | grep -cE '^\./(\.claude/|\.mcp\.json|boost\.json)'
tar -tzf /tmp/release.tar.gz | grep -cE '^\./(CLAUDE|AGENTS)\.md$'
tar -tzf /tmp/release.tar.gz | grep -cE '^\./(tests|docs|\.git)/'
tar -tzf /tmp/release.tar.gz | grep -cE 'phpunit\.result\.cache|tsbuildinfo|security-fixes\.patch'

# And these must be present.
tar -tzf /tmp/release.tar.gz | grep -cE '^\./(artisan|composer\.json|composer\.lock)$'  # 3
tar -tzf /tmp/release.tar.gz | grep -c '^\./app/'                                        # > 0
tar -tzf /tmp/release.tar.gz | grep -cE '^\./public/build/manifest\.json$'               # 1
```

Run on this working tree, the archive came to **623 files, 3.0 MB**, with every
"must be 0" check reading `0`.

Upload only after every check above reads as expected:

```sh

scp /tmp/release.tar.gz fivestore@ssh-fivestore.alwaysdata.net:/tmp/
```

On the server, extract over the existing tree. This replaces code only;
`.env`, `storage/`, `vendor/` and `public/build` are untouched by the archive:

```sh
cd ~/five_store
tar -xzf /tmp/release.tar.gz
rm -f /tmp/release.tar.gz
```

Then upload the built assets separately:

```sh
# from your machine
tar -czf /tmp/build.tar.gz public/build
scp /tmp/build.tar.gz fivestore@ssh-fivestore.alwaysdata.net:/tmp/
# on the server
cd ~/five_store && rm -rf public/build && tar -xzf /tmp/build.tar.gz && rm -f /tmp/build.tar.gz
```

No `rsync --delete` anywhere: the remote tree is never pruned by this process.

---

## 6. Dependencies, migration, caches

```sh
cd ~/five_store
php artisan down --retry=30          # brief maintenance window

composer install --no-dev --optimize-autoloader

php artisan migrate --force          # adds the nullable price column
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

The migration is additive: one nullable column, no data rewritten, no table
rebuilt beyond the `ALTER`. Existing products simply have `price = NULL` and
the storefront shows no price for them, which is the intended behaviour.

**Do not run `db:seed`.** See section 1.

---

## 7. Storage and permissions check

```sh
cd ~/five_store
ls -ld storage bootstrap/cache .env          # expect 750, 750, 600
ls -l public/storage                          # must be a symlink to storage/app/public
php artisan storage:link                      # only if the symlink is missing

# Uploads must land on a private disk, not the public one.
php artisan tinker --execute="
echo 'temp upload disk: '.(config('livewire.temporary_file_upload.disk') ?: config('filesystems.default')).PHP_EOL;
echo 'catalog disk    : '.config('catalog.images.disk').PHP_EOL;"
```

Expect `local` for temporary uploads and `public` for catalog images.

---

## 8. Verification after deployment

Automated, from your machine:

```sh
E2E_BASE_URL=https://fivestore.alwaysdata.net npm run test:e2e:live
```

Then by hand, over HTTPS:

| Check | Expected |
| --- | --- |
| `/` | redirects to `/ar` or `/en` |
| `/ar`, `/en` | render, correct `dir`, no console errors |
| `/ar/categories`, `/en/products` | render (empty until content is added) |
| `/en/products?page=2` with ≤12 products | **404**, not an empty page |
| `/en/products?page=abc` | **404**, not a 500 |
| `/admin/login` | renders; signing in works |
| Admin → create a product **with** a price | saves; price shows as `SAR 1,250.00` on `/en` and `1,250.00 ر.س` on `/ar` |
| Admin → create a product **without** a price | saves; no price shown anywhere, never `0.00` |
| Admin → upload a product image | appears under `/storage/products/...` and renders on the storefront |
| Admin → try a negative price | rejected with a validation message |
| `/.env`, `/composer.json`, `/storage/logs/laravel.log` | **404** |

Check the log is quiet afterwards:

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net \
  "cd ~/five_store && grep -c 'production.ERROR' storage/logs/laravel.log || echo 0"
```

### 8a. Post-deployment checklist

Short enough to run in a few minutes, and the thing to tick off before
handing the site back.

**Prices, in both languages**

- [ ] A product with a price shows `SAR 1,250.00` on `/en/...`
- [ ] The same product shows `1,250.00 ر.س` on `/ar/...`
- [ ] A product with **no** price shows no price anywhere — never `0.00`
- [ ] Prices appear in listings as well as on the product page
- [ ] `/admin` rejects a negative and a non-numeric price

**Images**

- [ ] Existing product and category images still render
- [ ] A newly uploaded image appears on the storefront under `/storage/...`
- [ ] Image URLs are same-origin — nothing hotlinked from a remote host
- [ ] Replacing an image does not leave the old file behind
- [ ] `php artisan storage:link` reports the link already exists

**Admin panel**

- [ ] `/admin` redirects an anonymous visitor to the login
- [ ] A real administrator signs in; the dashboard counts match the catalog
- [ ] Creating, editing and publishing a product all work and reach the storefront
- [ ] A non-administrator account receives 403, not a rendered page

**Site data integrity**

- [ ] Categories and products present before the deploy are still there, with
      the same counts as the pre-deploy backup
- [ ] Site settings unchanged: shop name, contact email, phone, address
- [ ] Administrator accounts unchanged and still able to sign in
- [ ] Uploaded image count unchanged (`ls storage/app/public/products | wc -l`)
- [ ] No row lost: compare `products`, `categories`, `product_images`,
      `users` and `site_settings` counts against the backup

**Pagination and errors**

- [ ] Listings show 12 per page; page links keep an active search term
- [ ] `?page=abc` and a page past the end both return 404, not 500
- [ ] `/.env` and `/storage/logs/laravel.log` return 404
- [ ] `storage/logs/laravel.log` shows no new `production.ERROR` entries

---

## 8b. Entering the real catalog — required before announcing the shop

Deploying this release does **not** put any products on the site. The
production catalog is empty, and it stays empty until someone enters content.
That has to be a decision, not something discovered after launch.

**The demo catalog is not an option here.** `RealisticCatalogSeeder` is for
development only: it deletes the whole catalog before rebuilding, and its 320
products are invented examples with photographs from Unsplash. Do not run it
on production and do not set `CATALOG_SEEDER_ALLOW_DESTRUCTIVE`.

Before the shop is announced as ready, through `/admin` only:

- [ ] Site settings filled in: shop name in both languages, logo, hero text,
      contact details, footer, default meta description.
- [ ] At least one real **category** per section the shop sells, each with an
      Arabic and English name, a description in both, an image, and published.
- [ ] Real **products** entered, each with: both names, both descriptions, a
      correct SKU, a price (or deliberately none), at least one image with one
      marked primary, and the right category.
- [ ] Each product checked on the storefront in **both** languages after
      publishing.
- [ ] A small number of products marked featured, so the home page is not bare.
- [ ] Any product still being prepared left **unpublished** rather than
      published with placeholder text.

Sign-off: the shop is ready to announce only when someone has opened
`https://fivestore.alwaysdata.net/ar` and `/en` as a visitor and confirmed the
catalog reads like a real shop. If the decision is to launch with an empty
catalog, record that it was deliberate.

---

## 9. Rollback

If the site is broken after deployment:

```sh
cd ~
php ~/five_store/artisan down 2>/dev/null || true

# 1. Code back to the previous release
rm -rf ~/five_store_broken && mv ~/five_store ~/five_store_broken
tar -xzf ~/backups/app-$STAMP.tar.gz -C ~
cp -a ~/five_store_broken/.env ~/five_store/.env
cp -a ~/five_store_broken/vendor ~/five_store/vendor 2>/dev/null || \
  (cd ~/five_store && composer install --no-dev --optimize-autoloader)

# 2. Images, only if they were lost
#    tar -xzf ~/backups/storage-$STAMP.tar.gz -C ~/five_store

cd ~/five_store
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

### 9.1 Rolling back the price migration destroys every price

`down()` drops the column, and dropping a column deletes its data. Tested on a
copy of the real catalog (320 priced products), not assumed:

```
before rollback           : 320 products with a price, sum 139780.00, FUR-1002 = 2850.00
after  rollback           : column `price` no longer exists
after  re-running migrate : 0 products with a price, sum 0
```

**Re-applying the migration does not bring the prices back.** It recreates an
empty column.

> **The backup from section 3 cannot restore prices.**
> It is taken *before* the migration runs, so its `products` table has no
> `price` column at all. It is the right artifact for restoring the catalog as
> it stood before this release, and it is useless for recovering prices entered
> afterwards. Any procedure that claims otherwise is wrong.

That splits a rollback into two different situations:

| When the rollback happens | What the prices are worth | What is needed |
| --- | --- | --- |
| Immediately after deploy, before anyone enters a price | Nothing is lost — there are no prices yet | Section 3's backup is sufficient |
| Any time after real prices have been entered | Every price is destroyed | A **fresh export taken at rollback time** (section 9.3) |

### 9.2 Prefer a forward fix

The column is nullable and additive: older code ignores it, so **reverting the
code does not require reverting the schema.** Rolling back is almost never the
right answer.

| Problem | Forward fix (preferred) | Rolling back |
| --- | --- | --- |
| Prices display wrongly | Change `App\Support\Money` or the Vue component and redeploy | Destroys data over a presentation bug |
| Precision or range is wrong | New migration: `$table->decimal('price', 12, 2)->nullable()->change();` | Destroys data |
| `UNSIGNED` rejected by the database | New migration dropping only the unsigned attribute; `min:0` validation already enforces the same rule | Destroys data |
| A price was entered incorrectly | Fix it in `/admin` | Destroys every other price too |
| The feature must be withdrawn | Deploy the previous code and **leave the column in place**, unused and ignored | Destroys data for no gain |

Roll the schema back only when the column itself is the problem and no forward
migration can address it.

### 9.3 If the column really must go: export the prices first

**Mandatory before any rollback.** This export is what makes recovery possible;
section 3's backup will not do it.

```sh
cd ~/five_store
STAMP=$(date +%Y%m%d-%H%M%S)

# Rebuild the credentials file and database name as in section 3 if needed.
mysql --defaults-extra-file=/tmp/.dbcnf -N -B \
  -e "SELECT id, COALESCE(sku,''), price FROM products WHERE price IS NOT NULL" \
  "$(cat /tmp/dbname)" > ~/backups/prices-$STAMP.tsv
chmod 600 ~/backups/prices-$STAMP.tsv

# Verify the export before trusting it.
wc -l < ~/backups/prices-$STAMP.tsv
awk -F'\t' '{s+=$3} END {printf "%.2f\n", s}' ~/backups/prices-$STAMP.tsv

php artisan tinker --execute="
echo 'live rows: '.App\Models\Product::whereNotNull('price')->count().PHP_EOL;
echo 'live sum : '.App\Models\Product::sum('price').PHP_EOL;"
```

The exported row count and sum **must match** the live figures. If they do not,
stop: the export is incomplete and the rollback would destroy prices that
cannot be recovered.

Only then:

```sh
php artisan migrate:rollback --step=1 --force
```

**Tested.** Run against a scratch database holding a copy of the real priced
catalog (`five_store_rbtest`, 320 products, all priced):

```
live figures            : 320 products, 320 priced, sum 139780.00
export (wc -l)          : 320 rows
export (awk sum)        : 139780.00          <- matches
first rows              : 334  FUR-1001  420.00
                          335  FUR-1002  2850.00
after migrate:rollback  : column `price` no longer exists
after migrate (re-apply): 0 priced, sum 0.00
```

### 9.4 Restoring prices from the export

```sh
cd ~/five_store
php artisan migrate --force          # recreates the column, empty

# Targeted UPDATEs: only the price column is written, nothing else is touched.
awk -F'\t' '{printf "UPDATE products SET price=%s WHERE id=%s;\n", $3, $1}' \
  ~/backups/prices-$STAMP.tsv > /tmp/restore-prices.sql
wc -l < /tmp/restore-prices.sql      # must equal the exported row count

mysql --defaults-extra-file=/tmp/.dbcnf "$(cat /tmp/dbname)" < /tmp/restore-prices.sql
rm -f /tmp/restore-prices.sql

php artisan tinker --execute="
echo 'restored rows: '.App\Models\Product::whereNotNull('price')->count().PHP_EOL;
echo 'restored sum : '.App\Models\Product::sum('price').PHP_EOL;"
```

Both numbers must equal what section 9.3 recorded. A count of 0 means nothing
matched — read the next paragraph before re-running anything.

**If product rows were recreated in between**, their ids will have changed and
an id match silently updates nothing. Match on the SKU instead, which is
unique:

```sh
awk -F'\t' '$2 != "" {printf "UPDATE products SET price=%s WHERE sku='"'"'%s'"'"';\n", $3, $2}' \
  ~/backups/prices-$STAMP.tsv > /tmp/restore-prices.sql
```

**Both restore paths were tested** on the same scratch database, continuing
from the rollback in section 9.3:

```
by id   : 320 UPDATE statements generated
          UPDATE products SET price=420.00 WHERE id=334;
          after applying -> 320 rows, sum 139780.00, FUR-1002 = 2850.00   <- exact match

prices then cleared again to retest the fallback:

by sku  : 320 UPDATE statements generated
          UPDATE products SET price=420.00 WHERE sku='FUR-1001';
          after applying -> 320 rows, sum 139780.00                        <- exact match
```

The scratch database was dropped afterwards; the figures above come from
running these exact commands, not from reasoning about them.

**If the only artifact is a full dump taken after prices existed** — a
scheduled backup, not section 3's — the same copy-one-column approach works.
This path was tested end to end:

```sh
mysql --defaults-extra-file=/tmp/.dbcnf -e \
  "DROP DATABASE IF EXISTS fivestore_restore; CREATE DATABASE fivestore_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --defaults-extra-file=/tmp/.dbcnf fivestore_restore < <dump-containing-prices>.sql

# Check what the dump holds before touching live data.
mysql --defaults-extra-file=/tmp/.dbcnf -e \
  "SELECT COUNT(*) AS products, SUM(price) AS price_sum FROM fivestore_restore.products;"

# Copy across the one column, then verify and clean up.
mysql --defaults-extra-file=/tmp/.dbcnf -e \
  "UPDATE <live_db>.products p JOIN fivestore_restore.products r ON p.id = r.id SET p.price = r.price;"
mysql --defaults-extra-file=/tmp/.dbcnf -e "DROP DATABASE fivestore_restore;"
```

Measured on the test run: 320 rows, sum `139780.00` before and after — an exact
match.

### 9.5 Never restore an old full dump over production

Importing a whole dump over the live database **silently discards everything
added since it was taken**. Here that includes products and categories entered
through `/admin`, image rows, site settings, administrator accounts and
password changes, and active sessions.

A full restore over production is permitted only when all of the following are
true, and written down:

- [ ] The live data is damaged beyond a targeted repair.
- [ ] The age of the dump is known, and **what was created since has been
      listed** — not guessed.
- [ ] Losing that newer data is explicitly accepted by whoever owns the shop.
- [ ] A dump of the **current** (damaged) database has been taken first, so the
      decision is reversible.
- [ ] The restore has been rehearsed into a scratch database and its row counts
      checked (section 9.6).

Otherwise: load the old dump into a scratch database and copy across only the
rows or columns actually needed, as in section 9.4.

### 9.6 Verifying a backup is restorable

A dump that has never been read back is an assumption, not a backup. Check each
one when it is taken:

```sh
BACKUP=~/backups/pre-price-$STAMP.sql

# 1. Complete: mysqldump writes this trailer only on success.
tail -1 "$BACKUP" | grep -q "Dump completed" && echo "complete" || echo "INCOMPLETE - do not rely on it"

# 2. Not empty, and holds the tables expected.
ls -lh "$BACKUP"
grep -c "CREATE TABLE" "$BACKUP"

# 3. It restores — into a scratch database, never over the live one.
mysql --defaults-extra-file=/tmp/.dbcnf -e \
  "DROP DATABASE IF EXISTS fivestore_verify; CREATE DATABASE fivestore_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --defaults-extra-file=/tmp/.dbcnf fivestore_verify < "$BACKUP"

# 4. Row counts match the live database.
for t in categories products product_images users site_settings; do
  live=$(mysql --defaults-extra-file=/tmp/.dbcnf -N -B -e "SELECT COUNT(*) FROM $t" "$(cat /tmp/dbname)")
  copy=$(mysql --defaults-extra-file=/tmp/.dbcnf -N -B -e "SELECT COUNT(*) FROM $t" fivestore_verify)
  printf "%-16s live=%-6s backup=%-6s %s\n" "$t" "$live" "$copy" "$([ "$live" = "$copy" ] && echo OK || echo MISMATCH)"
done

# 5. Clean up.
mysql --defaults-extra-file=/tmp/.dbcnf -e "DROP DATABASE fivestore_verify;"
```

Any `MISMATCH`, or a missing trailer, means the backup is not usable and the
deployment must not proceed until a good one exists.

## 10. Risks specific to this release

| # | Risk | Severity | Mitigation |
| --- | --- | --- | --- |
| 1 | The seeder is run on production by mistake and wipes the catalog | High | Guard refuses under `APP_ENV=production`; keep `CATALOG_SEEDER_ALLOW_DESTRUCTIVE` unset; backup taken in section 3 |
| 2 | `DECIMAL ... UNSIGNED` rejected by the database | Medium | Proven on a temporary table in section 2 before the real migration |
| 3 | Assets and code out of step, so pages reference missing chunks | Medium | Build and upload in the same pass (sections 4–5); manifest checked before upload |
| 4 | Migration runs while traffic is served | Low | `php artisan down` around the step; the `ALTER` is fast on a small table |
| 5 | Image fetcher reaching the network from production | None in normal use | It is only called by the seeder, which does not run there |
| 6 | Boost's dev dependency reaching production | Low | `composer install --no-dev` excludes it; confirm with `composer show laravel/boost` returning nothing on the server |

---

## 11. Decisions to confirm before running this

1. **Deploy window** — the maintenance window in section 6 is under a minute,
   but it is still downtime.
2. **Production content** — section 8b. The catalog is empty and stays empty
   until someone enters real products through `/admin`. Either complete that
   checklist before announcing the shop, or record that launching empty was
   deliberate.
3. **Approval to run** — this plan has not been executed. No production file,
   database row or setting has been touched.

### Settled already

- **Boost files** (`.claude/`, `.mcp.json`, `boost.json`) were reviewed: no
  secrets, no machine-specific paths, `.mcp.json` contains only
  `php artisan boost:mcp`. They are kept in the repository as shared developer
  tooling and **excluded from the release archive** (section 5).
- **`AGENTS.md`** was rewritten: it previously still carried the one-time
  "install Laravel Boost" bootstrap, which was stale and sent agents in a
  loop. It now points at `CLAUDE.md` and lists the project rules that must not
  be broken.
