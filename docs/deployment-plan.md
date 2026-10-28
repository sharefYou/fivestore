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
```

No build here. `npm run build` runs exactly once, in section 5.1, so the code
archive and the asset archive are packed from the same output. Building twice
is how a `manifest.json` ends up describing files that were never shipped.

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

### 3.1 Database credentials for this session

Several later steps need to reach the database without putting a password on a
command line, where it would land in shell history and in the process list.
Write them once, to a file only the account can read:

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net
cd ~
umask 077

php -r '
$e=[]; foreach(file("/home/fivestore/five_store/.env",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){
  if(str_starts_with($l,"#")||!str_contains($l,"="))continue; [$k,$v]=explode("=",$l,2); $e[$k]=trim($v,"\"\x27");}
file_put_contents("/tmp/.dbcnf","[client]\nhost=".$e["DB_HOST"]."\nport=".$e["DB_PORT"]."\nuser=".$e["DB_USERNAME"]."\npassword=\"".$e["DB_PASSWORD"]."\"\n");
chmod("/tmp/.dbcnf",0600); echo $e["DB_DATABASE"];' > /tmp/dbname
chmod 600 /tmp/dbname

ls -l /tmp/.dbcnf /tmp/dbname      # both must show -rw-------
```

**These two files stay in place for the whole deployment.** They are used by:

| Section | Use |
| --- | --- |
| 3.2 | taking the database backup |
| 3.3 | verifying that backup restores |
| 8a | comparing row counts after the deploy |
| 9.3 | exporting prices before a rollback |
| 9.4 | restoring prices from that export |
| 9.6 | verifying any backup is restorable |

They are removed in **section 12**, once everything that needs them is done.
Do not delete them earlier; an earlier draft of this plan removed them at the
end of 3.2, which left every check above with no way to reach the database.

If a rollback happens in a later session, re-run the block above first.

### 3.2 Take the backups

```sh
cd ~
mkdir -p backups && chmod 700 backups
STAMP=$(date +%Y%m%d-%H%M%S)

# Database.
mysqldump --defaults-extra-file=/tmp/.dbcnf --single-transaction \
  --default-character-set=utf8mb4 "$(cat /tmp/dbname)" > ~/backups/pre-price-$STAMP.sql
chmod 600 ~/backups/pre-price-$STAMP.sql

# Uploaded images and the current application files.
tar -czf ~/backups/storage-$STAMP.tar.gz -C ~/five_store storage/app/public
tar -czf ~/backups/app-$STAMP.tar.gz --exclude=vendor --exclude=node_modules \
  --exclude=storage/app/public -C ~ five_store

# What the current release's dependencies are, so they can be rebuilt exactly
# even if its directory is lost. Two small files, not a copy of vendor/.
cp ~/five_store/composer.lock ~/backups/composer.lock-$STAMP
sha256sum ~/five_store/composer.lock > ~/backups/composer.lock-$STAMP.sha256
cp ~/five_store/vendor/composer/installed.php ~/backups/installed.php-$STAMP
chmod 600 ~/backups/composer.lock-$STAMP* ~/backups/installed.php-$STAMP

ls -lh ~/backups
```

`app-$STAMP.tar.gz` excludes `vendor/`, which is right — it is reproducible
from `composer.lock` and would multiply the size of every backup. The
**primary** known-good copy of the dependencies is not a backup at all:
section 6.4 moves the outgoing release to `~/five_store_prev-$RELEASE` with its
`vendor/` untouched, and section 9.0 swaps that directory back. The lock file
above is the fallback for the case where that directory is gone.

Keep `$STAMP` for the rest of the deployment; sections 6 and 9 refer to these
filenames.

### 3.3 Prove the backups are usable

Each file must be non-empty, and the SQL dump must carry the trailer that
`mysqldump` writes only on success:

```sh
tail -1 ~/backups/pre-price-$STAMP.sql     # expect "-- Dump completed"
gzip -t ~/backups/storage-$STAMP.tar.gz && echo "storage archive readable"
gzip -t ~/backups/app-$STAMP.tar.gz && echo "app archive readable"
```

Then run the full restore check in **section 9.6** against
`~/backups/pre-price-$STAMP.sql`. It loads the dump into a scratch database and
compares row counts table by table. A backup that has never been read back is
an assumption, not a backup.

Do not proceed without all three files, the trailer, and a clean 9.6 result.
---

## 4. Why the assets are built locally

`vite build` dies on the server with a bus error (node + esbuild on NFS-backed
shared storage), so the front-end assets are built on your machine and shipped
with the release.

**The build itself is not run here.** It happens exactly once, in section 5.1,
immediately before the two archives are packed from its output — so the code
and the assets can never come from different builds. There is no `npm run
build` anywhere else in this plan.

---

## 5. Build the release archives and upload them

Nothing on the server is replaced in this section. The archives are built from
**one** `npm run build`, verified locally, and parked in a staging directory.
The site keeps serving the current release throughout.

### 5.1 One build, two archives, one release id

The code and the assets must come from the same build: a `manifest.json` that
disagrees with the deployed JavaScript produces a site that loads nothing. Tie
them together with a release id and build both archives in one pass:

```sh
cd /path/to/five_store

# The one and only build of this deployment.
npm run build

# Sanity-check its output before anything is packed from it.
php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true);
 $miss=0; foreach($m as $e){foreach(array_merge([$e["file"]],$e["css"]??[]) as $f){ if(!is_file("public/build/".$f)) $miss++; }}
 echo "entries: ".count($m).", missing: ".$miss.PHP_EOL;'
# entries must be > 0 and missing must be 0.

RELEASE="$(date +%Y%m%d-%H%M%S)-$(git rev-parse --short HEAD)"
MANIFEST_HASH="$(sha256sum public/build/manifest.json | cut -c1-16)"
echo "$RELEASE $MANIFEST_HASH"

# Code archive — excludes public/build, which ships in the asset archive.
tar -czf "/tmp/code-$RELEASE.tar.gz" \
  --exclude='./.git' --exclude='./.github' --exclude='./node_modules' \
  --exclude='./vendor' --exclude='./.env' --exclude='./tests' \
  --exclude='./public/build' --exclude='./public/storage' \
  --exclude='./storage/app/public' --exclude='./storage/app/private' \
  --exclude='./storage/logs' --exclude='./storage/framework/cache/data' \
  --exclude='./storage/framework/sessions' --exclude='./storage/framework/views' \
  --exclude='./storage/inertia-devtools' \
  --exclude='./bootstrap/cache/packages.php' --exclude='./bootstrap/cache/services.php' \
  --exclude='./.claude' --exclude='./.mcp.json' --exclude='./boost.json' \
  --exclude='./CLAUDE.md' --exclude='./AGENTS.md' \
  --exclude='./docs' --exclude='./.phpunit.result.cache' \
  --exclude='./tsconfig.tsbuildinfo' --exclude='./fivestore-security-fixes.patch' \
  --exclude='*.log' .

# Asset archive — public/build and nothing else.
tar -czf "/tmp/assets-$RELEASE.tar.gz" public/build
```

Two of those excludes are easy to miss, because both directories sit in
`.gitignore` and so never appear in a `git status`:

- `storage/inertia-devtools/` is Inertia's local profiling dump — 248 JSON
  files, 4.6 MB of one developer's browsing, with no function on a server.
- `bootstrap/cache/packages.php` and `services.php` are Laravel's package
  manifest, and the locally built copy names `laravel/boost`, `laravel/pail`,
  `nunomaduro/collision` and three other **dev-only** providers. Shipping it
  writes the one dependency section 10 says must not reach production into the
  server's boot path by name. `composer install` regenerates it correctly in
  section 6.2, so it does not belong in the archive at all.

Both archives carry the same `$RELEASE` in their names. Keep
`$MANIFEST_HASH` to hand; section 6 checks it again on the server.

### 5.2 Verify the code archive

Inspect what was produced, rather than trusting the command. Note the `$`
anchors: an unanchored `\.env` also matches `.env.example`, which belongs in
the release and would read as a false alarm.

```sh
CODE="/tmp/code-$RELEASE.tar.gz"

# Each of these must print 0.
tar -tzf "$CODE" | grep -cE '^\./\.env$'
tar -tzf "$CODE" | grep -cE '^\./(vendor|node_modules)/'
tar -tzf "$CODE" | grep -cE '^\./(\.claude/|\.mcp\.json|boost\.json)'
tar -tzf "$CODE" | grep -cE '^\./(CLAUDE|AGENTS)\.md$'
tar -tzf "$CODE" | grep -cE '^\./(tests|docs|\.git)/'
tar -tzf "$CODE" | grep -cE 'phpunit\.result\.cache|tsbuildinfo|security-fixes\.patch'
tar -tzf "$CODE" | grep -cE '^\./public/build/'        # assets must NOT be here
tar -tzf "$CODE" | grep -cE '^\./storage/inertia-devtools/'
tar -tzf "$CODE" | grep -cE '^\./bootstrap/cache/[^/]+\.php$'

# And these must be present.
tar -tzf "$CODE" | grep -cE '^\./(artisan|composer\.json|composer\.lock)$'   # 3
tar -tzf "$CODE" | grep -c '^\./app/'                                        # > 0
tar -tzf "$CODE" | grep -c '^\./database/migrations/'                        # > 0
```

### 5.3 Verify the asset archive

The manifest must be present, and every file it names must be in the archive —
a manifest pointing at a chunk that was not packed is exactly the failure that
leaves a blank page.

```sh
ASSETS="/tmp/assets-$RELEASE.tar.gz"

tar -tzf "$ASSETS" | grep -cE '^public/build/manifest\.json$'    # 1
tar -tzf "$ASSETS" | grep -c '^public/build/assets/'             # > 0
tar -tzf "$ASSETS" | grep -cvE '^public/build/'                  # 0 - nothing else

# Every file referenced by the manifest is actually packed.
tar -tzf "$ASSETS" > /tmp/packed.txt
tar -xzOf "$ASSETS" public/build/manifest.json > /tmp/manifest.json

php -r '
$manifestPath = $argv[1]; $packedPath = $argv[2];
$raw = @file_get_contents($manifestPath);
if ($raw === false) { fwrite(STDERR, "FAIL: cannot read the manifest at $manifestPath\n"); exit(1); }
$m = json_decode($raw, true);
if (!is_array($m) || $m === []) { fwrite(STDERR, "FAIL: manifest did not decode, or is empty\n"); exit(1); }
$want = [];
foreach ($m as $e) { foreach (array_merge([$e["file"]], $e["css"] ?? []) as $f) { $want[$f] = true; } }
if (count($want) === 0) { fwrite(STDERR, "FAIL: manifest references no files\n"); exit(1); }
$lines = @file($packedPath);
if ($lines === false) { fwrite(STDERR, "FAIL: cannot read the archive listing at $packedPath\n"); exit(1); }
$have = [];
foreach ($lines as $l) { $have[preg_replace("#^public/build/#", "", trim($l))] = true; }
$missing = array_diff_key($want, $have);
printf("referenced by manifest: %d, missing from archive: %d\n", count($want), count($missing));
foreach (array_slice(array_keys($missing), 0, 5) as $f) { echo "  MISSING $f\n"; }
exit(count($missing) === 0 ? 0 : 1);' /tmp/manifest.json /tmp/packed.txt
```

The guards matter. An earlier version of this check read the manifest from a
hard-coded path, and when that path did not resolve it reported
`missing: 0` — a pass, from having compared nothing at all. Paths are passed as
arguments now, and the script exits non-zero if the manifest cannot be read,
does not decode, or references no files.

### 5.4 Upload to a staging directory

Uploading does not touch the running application. Put both archives somewhere
outside the web root and leave them there until section 6.

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net 'mkdir -p ~/releases && chmod 700 ~/releases'
scp "/tmp/code-$RELEASE.tar.gz" "/tmp/assets-$RELEASE.tar.gz" \
    fivestore@ssh-fivestore.alwaysdata.net:~/releases/

# Confirm both arrived intact.
ssh fivestore@ssh-fivestore.alwaysdata.net "ls -lh ~/releases/*-$RELEASE.tar.gz && \
  gzip -t ~/releases/code-$RELEASE.tar.gz && gzip -t ~/releases/assets-$RELEASE.tar.gz && echo 'both archives are readable'"
```

At this point the site is still serving the old release and nothing has been
replaced. **Do not continue unless every check in 5.2 and 5.3 passed.**

### 5.5 Measured on this working tree

Both archives were built and inspected with the commands above. Counts are of
regular files; `tar` also records a directory entry per directory.

```
RELEASE        20261028-044556-0fc4392c
MANIFEST_HASH  5573e3afcf48d5a3

code archive   198 files (288 entries), 1.7 MB
  .env / vendor / node_modules ............ 0
  .claude / .mcp.json / boost.json ........ 0
  CLAUDE.md / AGENTS.md ................... 0
  tests/ docs/ .git/ ...................... 0
  dev artifacts ........................... 0
  storage/inertia-devtools/ ............... 0
  bootstrap/cache/*.php ................... 0
  public/build/ ........................... 0   <- assets excluded
  database/seeders/CatalogSeeder.php ...... 0   <- deleted from Git this release
  artisan + composer.json + composer.lock . 3
  app/ files .............................. 53
  database/migrations/ .................... 9

asset archive  80 files, 1.1 MB
  manifest.json ........................... 1
  assets/ files ........................... 80
  anything outside public/build ........... 0
  referenced by manifest .................. 79
  missing from archive .................... 0

pairing        manifest hash in archive == hash recorded at build time
```

The code archive was 288 entries, down from 541 before the two excludes added
in 5.1: 251 of those entries were `storage/inertia-devtools/` and 2 were
`bootstrap/cache/*.php`. The drop is accounted for exactly, which is the point
of counting rather than reading the command.

No `rsync --delete` anywhere: the remote tree is never pruned by this process.

---

## 6. The maintenance window

The release is assembled in a directory of its own, beside the running one, and
swapped in. Nothing is extracted over the live tree and nothing is installed
into it.

Two reasons, both of them failures this plan used to have:

- **Extracting over the live tree never removes anything.** A file deleted from
  Git stays on the server indefinitely. `database/seeders/CatalogSeeder.php` was
  deleted in this release; under the old step it would still have been sitting
  in `database/seeders/` afterwards, loadable by `db:seed`.
- **`composer install` run inside the live tree rewrites the dependencies the
  running site is using.** Composer deletes a package's directory before
  unpacking its replacement. Interrupted in between, it leaves the live release
  unable to boot — and leaves a damaged `vendor/` as the only thing a rollback
  has to copy from.

Only 6.4 onward changes what visitors see. Sections 6.1 to 6.3 run while the old
release is still serving normally.

### 6.1 Build the new release in its own directory

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net
RELEASE=<the id from section 5>
NEXT=~/five_store_next-$RELEASE

rm -rf "$NEXT" && mkdir -p "$NEXT"
tar -xzf ~/releases/code-$RELEASE.tar.gz   -C "$NEXT"
tar -xzf ~/releases/assets-$RELEASE.tar.gz -C "$NEXT"     # creates public/build

# The archives carry no writable runtime directories — section 5 excludes them
# all. Extracting over an existing tree used to hide that; a clean directory
# does not, so create them.
mkdir -p "$NEXT"/storage/logs "$NEXT"/storage/app/public "$NEXT"/storage/app/private \
         "$NEXT"/storage/framework/cache/data "$NEXT"/storage/framework/sessions \
         "$NEXT"/storage/framework/views

# The one server-side file that is never in an archive.
cp -a ~/five_store/.env "$NEXT/.env"

# The assets on disk must be the ones verified locally.
sha256sum "$NEXT/public/build/manifest.json" | cut -c1-16      # must equal $MANIFEST_HASH
```

Confirm the new tree holds only what the release contains — in particular that
files deleted from Git are not in it:

```sh
test -e "$NEXT/database/seeders/CatalogSeeder.php" \
  && echo "UNEXPECTED: a file deleted from Git is present" || echo "deleted files are absent"
ls "$NEXT/database/seeders/"      # DatabaseSeeder.php  RealisticCatalogSeeder.php  data
```

`~/five_store` has not been touched by anything above.

### 6.2 Install the dependencies in the new directory

```sh
cd "$NEXT"
composer install --no-dev --optimize-autoloader
```

This is the slow step and the one most likely to fail, and here it costs nothing
if it does: the running release's `vendor/` is never opened for writing. On
failure, `rm -rf "$NEXT"`, fix the cause, start again — the site has not been
touched and there is nothing to roll back.

It also regenerates `bootstrap/cache/packages.php` for the production dependency
set. Section 5 excludes that file from the archive precisely so that this step
is what produces it: the copy built on a development machine names
`laravel/boost` and five other dev-only providers, and shipping it would leave
the server one failed `composer install` away from a fatal "class not found" on
every request.

### 6.3 Prove the dependencies are complete

**An exit code of 0 is not evidence that `vendor/` is complete** — not from
`composer install`, and not from `cp`. Check the tree against its own
`composer.lock`:

```sh
cat > ~/vendor-check.php <<'PHPEOF'
<?php
$root = rtrim($argv[1] ?? '.', '/');
$fail = function (string $m) { fwrite(STDERR, "VENDOR CHECK FAILED: $m\n"); exit(1); };
foreach (['composer.lock', 'vendor/autoload.php', 'vendor/composer/installed.php'] as $f) {
    is_file("$root/$f") || $fail("$f is missing");
}
$lock = json_decode(file_get_contents("$root/composer.lock"), true);
$want = [];
foreach ($lock['packages'] as $p) { $want[$p['name']] = $p['version']; }   // prod only
$have = (require "$root/vendor/composer/installed.php")['versions'] ?? [];
$notInstalled = $wrongVersion = $missingDir = [];
foreach ($want as $name => $version) {
    if (! isset($have[$name])) { $notInstalled[] = $name; continue; }
    if (($have[$name]['pretty_version'] ?? null) !== $version) {
        $wrongVersion[] = $name;
    }
    $dir = "$root/vendor/$name";
    if (! is_dir($dir) || count(scandir($dir)) <= 2) { $missingDir[] = $name; }
}
$dev = array_intersect(array_keys($have), array_column($lock['packages-dev'], 'name'));
printf("required by composer.lock          : %d\n", count($want));
printf("absent from installed.php          : %d\n", count($notInstalled));
printf("version mismatch                   : %d\n", count($wrongVersion));
printf("listed but directory missing/empty : %d\n", count($missingDir));
printf("dev packages present (must be 0)   : %d\n", count($dev));
foreach (array_slice(array_merge($notInstalled, $wrongVersion, $missingDir, $dev), 0, 5) as $p) {
    echo "  BAD $p\n";
}
if ($notInstalled || $wrongVersion || $missingDir || $dev) { $fail('vendor does not match composer.lock'); }
echo "vendor matches composer.lock\n";
PHPEOF

php ~/vendor-check.php "$NEXT"        # must print "vendor matches composer.lock"
echo "gate exit: $?"                  # must be 0

(cd "$NEXT" && php artisan --version) # must print "Laravel Framework 13.x"
```

Composer removes a package's directory before unpacking its replacement, so an
interrupted run leaves directories missing while `vendor/composer/installed.php`
still lists them. That is precisely what this compares. The `dev packages` line
guards the other direction: `--no-dev` must not have left `laravel/boost` or
`nunomaduro/collision` on the server.

**Do not open the maintenance window unless both commands pass.** Everything up
to this point is undone by deleting a directory.

### 6.4 Open the window and swap the directories

```sh
cd ~/five_store
php artisan down --retry=30
curl -s -o /dev/null -w '%{http_code}\n' https://fivestore.alwaysdata.net/   # expect 503
```

`down` is run by the current, working release, before any file of it is
replaced. Only once visitors are shut out can uploads no longer change, so the
images are copied now rather than earlier:

```sh
cp -a ~/five_store/storage/app/public/. "$NEXT/storage/app/public/"

ls ~/five_store/storage/app/public/products | wc -l     # these two numbers
ls "$NEXT"/storage/app/public/products      | wc -l     # must match

# storage/app/private holds in-progress uploads and nothing else — see below.
# Confirm that is still true instead of assuming it.
PRIV=~/five_store/storage/app/private
if [ -d "$PRIV" ]; then
    find "$PRIV" -mindepth 1 -maxdepth 1 -not -name livewire-tmp -not -name .gitignore
    #  ^ must print nothing. Anything listed is written by something this plan
    #    does not know about: stop and find out what, before continuing.
    cp -a "$PRIV/." "$NEXT/storage/app/private/"
    echo "private disk carried over"
else
    echo "no storage/app/private on the server yet — nothing to carry over"
fi
```

**On `storage/app/private`.** It is the root of the `local` disk
(`config/filesystems.php`), and in this application exactly one thing writes
there: Livewire's temporary uploads, pinned to that disk in
`AppServiceProvider::register()` so that half-finished uploads are never
published under `/storage` before validation has accepted them. They land in
`livewire-tmp/`, and Livewire deletes anything older than 24 hours whenever an
upload form is opened — `WithFileUploads::cleanupOldUploads()`, confirmed in the
installed v4.4.8 source, enabled by default. Every file this application keeps
goes through `App\Services\ImageStorage` onto the `public` disk instead, and no
database row points into the private disk.

**So the whole cost of losing this directory is that an administrator who had a
file selected in `/admin` but had not yet saved the form must select it again.**
Nothing else. Given a maintenance window of well under a minute, that is close
to unreachable in practice.

That is a reason to state the impact, not to hide the error. The previous
version of this step ended in `2>/dev/null || true`, which would equally have
swallowed a failure from a full disk or wrong permissions, and would silently
drop the contents if some later feature ever stored something real there. The
`find` is what notices that; the missing directory is reported rather than
ignored; and the `cp` itself now fails loudly if it fails at all.

The maintenance flag lives in `storage/framework/maintenance.php` **inside the
release directory**, so it does not travel across a swap. Close the new release
too, before it becomes the live one:

```sh
(cd "$NEXT" && php artisan down --retry=30)
test -f "$NEXT/storage/framework/maintenance.php" && echo "new release is closed"

mv ~/five_store "$HOME/five_store_prev-$RELEASE"
mv "$NEXT" ~/five_store

cd ~/five_store
test -f storage/framework/maintenance.php && echo "still closed after the swap"
php artisan storage:link        # the link stores an absolute path, so remake it here
```

**Keep `~/five_store_prev-$RELEASE`.** It is a complete, working release whose
`vendor/` nothing has written to, and section 9 rolls back by swapping it
straight back in.

### 6.5 Migration and caches

```sh
cd ~/five_store
php artisan migrate --force          # adds the nullable price column

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The migration is additive: one nullable column, no data rewritten, no table
rebuilt beyond the `ALTER`. Existing products simply have `price = NULL` and the
storefront shows no price for them, which is the intended behaviour.

**Do not run `db:seed`.** See section 1.

### 6.6 Verify before reopening

Run section 7 (storage and permissions) and section 8 (verification), **while
the site is still down**. The checks that need a browser can be run against the
maintenance-bypass URL, or deferred to 6.7 and treated as blocking.

```sh
php artisan about --only=environment
php artisan tinker --execute="
echo 'db       : '.DB::connection()->getDatabaseName().PHP_EOL;
echo 'products : '.App\Models\Product::count().PHP_EOL;
echo 'images   : '.App\Models\ProductImage::count().PHP_EOL;
echo 'price col: '.(Schema::hasColumn('products','price') ? 'present' : 'MISSING').PHP_EOL;"

# No developer tooling reached the server.
composer show laravel/boost 2>&1 | tail -1          # expect "not found"
ls storage/inertia-devtools 2>/dev/null | wc -l     # expect 0
```

### 6.7 Reopen only on success

```sh
php artisan up
curl -s -o /dev/null -w '%{http_code}\n' https://fivestore.alwaysdata.net/   # expect 302 to /ar or /en
```

Then immediately run the section 8a checklist against the live site.

### 6.8 If anything fails

**Leave the site in maintenance.** Do not run `php artisan up` to "see if it
works" — a broken release serving real visitors is worse than a maintenance
page.

1. Capture the error (`storage/logs/laravel.log`, the failing command's output).
2. Go to section 9 and swap the previous release back.
3. Re-run 6.3 and 6.6 against the restored release.
4. Only then `php artisan up`.

Where the failure happened decides how much work this is:

| Failure during | Site state | What to do |
| --- | --- | --- |
| 6.1 – 6.3 (staging) | still serving the old release | `rm -rf "$NEXT"` and start again; nothing to roll back |
| 6.4 (the swap) | closed | section 9.0 — swap `five_store_prev-$RELEASE` back |
| 6.5 – 6.6 | closed | section 9.0, plus section 9.3 if prices must be preserved |

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

```sh
# The `local` disk is storage/app/private, and only Livewire's in-progress
# uploads belong in it. Anything else is unexpected (section 6.4).
find storage/app/private -mindepth 1 -maxdepth 1 -not -name livewire-tmp -not -name .gitignore
ls storage/app/private/livewire-tmp 2>/dev/null | wc -l   # stale temp uploads, if any
```

The second number is informational: Livewire clears entries older than 24 hours
by itself, so a large count means uploads are being started and abandoned, not
that anything is broken.

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

**The site stays closed for all of this.** If the failure happened inside the
window of section 6, it is already down — leave it down. If it failed in 6.1
to 6.3 the site was never closed and never changed: delete the staging
directory and start again. If it surfaced after
`php artisan up`, close the site again before touching anything.

```sh
cd ~/five_store
php artisan down --retry=30 2>/dev/null || true
curl -s -o /dev/null -w '%{http_code}\n' https://fivestore.alwaysdata.net/   # expect 503
```

### 9.0 Swap the previous release back

Section 6.4 moved the old release to `~/five_store_prev-$RELEASE` instead of
overwriting it. It is a complete, working tree, and — this is the point — its
`vendor/` has not been written to since that release installed it. Section 6.2
installs into the staging directory, never into a live release, so no failed
`composer install` can have touched it.

`php artisan down` writes `storage/framework/maintenance.php` **inside the
release directory**, so the flag does not survive a swap. The previous release
is therefore closed before it is moved back, not after.

**The whole procedure is one `if`/`else`,** so that pasting it in a single
block is safe: if the previous release is not usable, nothing inside runs at
all.

```sh
RELEASE=<the id from section 5>
PREV="$HOME/five_store_prev-$RELEASE"

# --- Precondition. Everything below depends on it. -------------------------
if [ ! -d "$PREV" ] || [ ! -f "$PREV/artisan" ] || [ ! -f "$PREV/vendor/autoload.php" ]; then
    echo "STOP: no usable previous release at $PREV"
    echo "      Run nothing further in 9.0. Go to section 9.0.1."
else
    # Carry over the things that belong to the server, not to a release.
    cp -a ~/five_store/.env "$PREV/.env"
    mkdir -p "$PREV/storage/app/public" "$PREV/storage/app/private"
    cp -a ~/five_store/storage/app/public/. "$PREV/storage/app/public/"

    ls ~/five_store/storage/app/public/products | wc -l     # these two numbers
    ls "$PREV"/storage/app/public/products      | wc -l     # must match

    # The private disk, under the rule in 6.4: checked, copied, never silenced.
    PRIV=~/five_store/storage/app/private
    if [ -d "$PRIV" ]; then
        find "$PRIV" -mindepth 1 -maxdepth 1 -not -name livewire-tmp -not -name .gitignore
        cp -a "$PRIV/." "$PREV/storage/app/private/"
    fi

    # Close it before it becomes the live one.
    (cd "$PREV" && php artisan down --retry=30)
    test -f "$PREV/storage/framework/maintenance.php" && echo "previous release is closed"

    rm -rf ~/five_store_broken
    mv ~/five_store ~/five_store_broken
    mv "$PREV" ~/five_store

    cd ~/five_store
    test -f storage/framework/maintenance.php && echo "still closed after the swap"
    php artisan storage:link --force    # the tree carries a link from its own deploy
    echo "swap complete — continue at 9.0.2"
fi
```

**Why the precondition is a branch and not a warning.** An earlier version of
this plan opened with
`test -d "$PREV" || echo "previous release is gone"`, which printed a line and
carried on. With `$PREV` missing, the `mkdir -p` two lines later would have
*created* it, the copies would have filled it with `.env` and the images and
nothing else, `php artisan down` would have failed inside a tree with no
`artisan`, and the two `mv`s would then have installed that near-empty
directory as the live site — turning a recoverable failed deployment into a
destroyed one. The test also covers a directory that exists but has been
gutted, which a bare `-d` would have accepted.

Keep `~/five_store_broken` until the restored site has been checked properly; it
holds the logs that explain what went wrong.

Go to 9.0.2 before reopening.

### 9.0.1 If the previous release directory is gone

Only when `~/five_store_prev-$RELEASE` no longer exists — a rollback in a much
later session, or a cleanup that went too far. Assemble the tree outside the
live path, exactly as 9.0 does, and do **not** take `vendor/` from
`~/five_store`: that is the release the deployment was in the middle of
modifying, and if the failure was a half-finished `composer install` it is the
damaged copy.

```sh
RESTORE=~/five_store_restore
rm -rf "$RESTORE" && mkdir "$RESTORE"

# 1. Unpack the previous release next to the live one, never over it.
tar -xzf ~/backups/app-$STAMP.tar.gz -C "$RESTORE" --strip-components=1

mkdir -p "$RESTORE"/storage/logs "$RESTORE"/storage/app/public \
         "$RESTORE"/storage/app/private "$RESTORE"/storage/framework/cache/data \
         "$RESTORE"/storage/framework/sessions "$RESTORE"/storage/framework/views

# 2. Rebuild the dependencies from the composer.lock that came out of the
#    backup — the previous release's own lock, not the new one's.
cd "$RESTORE"
sha256sum composer.lock                       # compare with ~/backups/composer.lock-$STAMP.sha256
composer install --no-dev --optimize-autoloader

# 3. The server-side state the backup deliberately leaves out.
cp -a ~/five_store/.env "$RESTORE/.env"
cp -a ~/five_store/storage/app/public/. "$RESTORE/storage/app/public/"

PRIV=~/five_store/storage/app/private
if [ -d "$PRIV" ]; then
    cp -a "$PRIV/." "$RESTORE/storage/app/private/"
fi
```

If `composer install` cannot run at all — no network, packagist unreachable —
the last resort is a copy of `vendor/` from `~/five_store`, and then **only**
if the gate in 9.0.2 passes on it. A copy that the gate rejects is not a
rollback, it is a second outage.

If images were lost rather than merely stale, restore them from their own
backup instead of step 3's copy:

```sh
tar -xzf ~/backups/storage-$STAMP.tar.gz -C "$RESTORE"
```

Then close the restored tree and swap it in — under the same precondition as
9.0, and for the same reason. The `tar` or the `composer install` above may
have failed, and an incomplete `$RESTORE` must never be moved into place:

```sh
if [ ! -f "$RESTORE/artisan" ] || [ ! -f "$RESTORE/vendor/autoload.php" ]; then
    echo "STOP: $RESTORE is incomplete — nothing was swapped."
    echo "      Fix the extraction or the composer install and repeat 9.0.1."
else
    (cd "$RESTORE" && php artisan down --retry=30)
    test -f "$RESTORE/storage/framework/maintenance.php" && echo "restored tree is closed"

    rm -rf ~/five_store_broken
    mv ~/five_store ~/five_store_broken
    mv "$RESTORE" ~/five_store

    cd ~/five_store
    test -f storage/framework/maintenance.php && echo "still closed after the swap"
    php artisan storage:link --force    # the tree carries a link from its own deploy
    echo "swap complete — continue at 9.0.2"
fi
```

### 9.0.2 Prove the restored release runs, and only then reopen

The site is still closed. Nothing below reopens it until every check has
passed.

```sh
cd ~/five_store

# 1. The dependencies are complete — checked against composer.lock, not assumed
#    from the exit code of cp or composer.
php ~/vendor-check.php ~/five_store      # the script from section 6.3
echo "gate exit: $?"                     # must be 0

# 2. The framework actually boots.
php artisan --version                    # must print "Laravel Framework 13.x"

# 3. Only now rebuild the caches. Doing this before step 2 caches a broken
#    application and hides the failure behind a stale config.
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 4. The application answers, and the data is intact.
php artisan about --only=environment
php artisan tinker --execute="
echo 'db         : '.DB::connection()->getDatabaseName().PHP_EOL;
echo 'categories : '.App\Models\Category::count().PHP_EOL;
echo 'products   : '.App\Models\Product::count().PHP_EOL;
echo 'images     : '.App\Models\ProductImage::count().PHP_EOL;
echo 'image files: '.count(Storage::disk(config('catalog.images.disk'))->allFiles('products')).PHP_EOL;"
```

The counts must match what section 8a recorded before the deploy. Then run the
rest of the section 6.6 checks. Only once all of them pass:

```sh
php artisan up
curl -s -o /dev/null -w '%{http_code}\n' https://fivestore.alwaysdata.net/   # expect 302
```

If any check fails, the site stays closed. That is the correct outcome: a
maintenance page is recoverable, a half-restored shop is not.

### 9.0.3 Tested

Run against an isolated copy of this application — the real `vendor/` (113
packages after `--no-dev`), the real `artisan`, and a section-3.2 backup taken
before the window.

A failing `composer install` was reproduced the way composer actually fails:
three package directories (`symfony/console`, `nesbot/carbon`,
`league/flysystem`) removed while `vendor/composer/installed.php` still listed
them, which is the state an interrupted run leaves behind.

```
baseline live vendor                         : 14611 files, fingerprint b587430593f02a8a

composer install fails in the STAGING directory (this plan):
  staging gate                               : FAIL  (3 listed but directory missing)
  staging boots                              : no
  live vendor files                          : 14611  (unchanged)
  live vendor byte-for-byte identical        : YES
  live gate                                  : PASS
  live boots                                 : Laravel Framework 13.38.0
  site state                                 : LIVE - the window was never opened
  after deleting the staging dir and retrying: PASS

composer install fails in the LIVE tree (what the previous plan did):
  live gate                                  : FAIL
  live boots                                 : no
  rollback copies vendor from the live tree:
    cp -a exit code                          : 0      <- reports success
    restored release gate                    : FAIL
    restored release boots                   : no

same rollback, vendor taken from the untouched previous release:
  restored release gate                      : PASS   <- checked, not assumed
  restored release boots                     : Laravel Framework 13.38.0
  product images carried over                : 3 of 3
  restored tree closed before the swap       : DOWN
  after the swap                             : DOWN, gate PASS, boots 13.38.0
  only then, artisan up                      : LIVE
```

And the deleted-file problem, on the same copy:

```
extracting the release over the live tree (the previous step 6.2):
  database/seeders/CatalogSeeder.php         : *** STILL PRESENT ***
  CatalogSeeder.php inside the archive       : no

building a clean release directory (step 6.1):
  database/seeders/CatalogSeeder.php         : absent
  seeders present    : DatabaseSeeder.php  RealisticCatalogSeeder.php  data
  .env carried over  : yes
  images carried over: 3 of 3
  dev telemetry files shipped                : 0
  new tree boots                             : Laravel Framework 13.38.0
```

Finally, the commands in 6.1 and 6.3 were extracted from this file and run as
written — not paraphrased — against the archives section 5.1 actually produced
(`20261028-044556-0fc4392c`):

```
section 6.1, clean directory from the real archives:
  manifest hash on disk                      : 5573e3afcf48d5a3
  manifest hash recorded at build time       : 5573e3afcf48d5a3
  database/seeders/CatalogSeeder.php         : absent
  seeders present                            : DatabaseSeeder.php RealisticCatalogSeeder.php data
  bootstrap/cache/ contents                  : .gitignore   (no dev-built manifest)
  storage/inertia-devtools/                  : absent
  release boots                              : Laravel Framework 13.38.0

section 6.3, the gate script as written in this file:
  against a complete vendor  -> "vendor matches composer.lock", exit 0
  against symfony/console removed:
     listed but directory missing/empty      : 1
     BAD symfony/console
     VENDOR CHECK FAILED                     , exit 1
```

A check that cannot fail is not a check; both directions were exercised.

The 9.0 precondition was tested the same way — the block extracted from this
file and run as written, against an isolated copy carrying the real `artisan`
and `vendor` (18 510 files) and four product images.

```
previous release MISSING:
  output                      : "STOP: no usable previous release at ..."
                                "Run nothing further in 9.0. Go to section 9.0.1."
  five_store_prev-TESTREL     : not created
  five_store_broken           : not created
  live tree                   : 18510 files, fingerprint unchanged
  live tree boots             : Laravel Framework 13.38.0
  images                      : 4 of 4
  maintenance flag            : absent - the live release was never touched

previous release EXISTS but gutted (1 file, no artisan, no vendor):
  output                      : same STOP - a bare `test -d` would have accepted it
  live tree fingerprint       : unchanged
  .env copied into it         : no
  five_store_broken           : not created

previous release present and complete:
  images carried over         : 4 of 4
  .env and private disk       : carried over
  previous release closed before the swap, still DOWN after it
  restored tree is the previous release (marker file present)
  restored tree boots         : Laravel Framework 13.38.0
  failed release preserved    : five_store_broken, 18510 files
  after 9.0.2's checks and `artisan up` : LIVE
```

And the same block **as it stood in commit `c57cbb22`**, with the previous
release missing, on a lightweight tree — the damage lands before `artisan` is
ever reached, so no `vendor/` is needed to show it:

```
  output : "previous release is gone - use 9.0.1 instead"
           cp: cannot create .../five_store_prev-TESTREL/.env: No such file or directory
           Could not open input file: artisan      (x2)
  home   : five_store  five_store_broken
  five_store is now          : 4 files - the product images and nothing else
  artisan                    : *** GONE ***
  app/ code                  : 0 files
  the real release           : moved aside into five_store_broken (31 files)
```

The warning printed, and every destructive step ran anyway.

One detail the test surfaced: the restored tree carries a `public/storage`
symlink from its own deployment, so a plain `php artisan storage:link` reports
"link already exists" as an **error** in the middle of a rollback. `--force`
avoids that — `StorageLinkCommand::isRemovableSymlink()` is `is_link($link) &&
$force`, so with a real symlink it is removed and recreated. That branch was
read from the installed framework source rather than executed: on Windows
`is_link()` is false even for a working link, so the local fixture cannot
represent it.

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
| 6 | Boost's dev dependency reaching production | Low | `composer install --no-dev` excludes it; the locally built `bootstrap/cache/packages.php` that names it is excluded from the archive (5.1) and regenerated on the server (6.2); confirm with `composer show laravel/boost` returning nothing |
| 7 | A file deleted from Git survives on the server and stays loadable | Medium | The release is built in a clean directory and swapped in (6.1, 6.4), so the tree holds only what the archive holds; checked explicitly for `CatalogSeeder.php` |
| 8 | `composer install` fails partway and leaves `vendor/` incomplete | Medium | It runs in the staging directory, never in a live release (6.2); completeness is checked against `composer.lock` rather than inferred from an exit code (6.3, 9.0.2) |
| 9 | A rollback restores an incomplete `vendor/` and the site stays down | Medium | 9.0 swaps back the previous release directory, whose `vendor/` nothing wrote to; the same gate runs again before `artisan up` (9.0.2) |

---

## 11. Decisions to confirm before running this

1. **Deploy window** — the maintenance window is section 6.4 to 6.7 only.
   Extracting the release and running `composer install` now happen beforehand,
   in a staging directory, while the old release keeps serving; what remains
   inside the window is an image copy, two `mv`s, one `ALTER TABLE` and the
   caches. It is still downtime.
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

---

## 12. Close out

Run this **last**, once the deployment has succeeded and section 8a has been
ticked off — or, if the deployment was rolled back, once section 9 is complete
and the restored site has been verified.

```sh
ssh fivestore@ssh-fivestore.alwaysdata.net

# 1. Remove the database credentials written in section 3.1.
shred -u /tmp/.dbcnf /tmp/dbname 2>/dev/null || rm -f /tmp/.dbcnf /tmp/dbname
ls -l /tmp/.dbcnf /tmp/dbname 2>&1 | head -2      # expect "No such file"

# 2. Remove the uploaded release archives, keeping the previous release for
#    one cycle so a rollback does not depend on rebuilding anything.
ls -lh ~/releases
#    rm -f ~/releases/code-<older-release>.tar.gz ~/releases/assets-<older-release>.tar.gz

# 3. Confirm no stray scratch databases were left behind.
#    (needs the credentials, so run it BEFORE step 1 if you want to check)
#    mysql --defaults-extra-file=/tmp/.dbcnf -e "SHOW DATABASES LIKE 'fivestore_%';"

# 4. Confirm what is left in the home directory: the live release, and the
#    previous one kept for rollback. No staging directory should remain.
ls -ld ~/five_store*
#    expected: five_store, five_store_prev-<release>
#    five_store_next-* means a staging build was abandoned - remove it
#    five_store_broken  is a failed release kept for its logs - remove when read

# 5. The vendor check script from section 6.3 is no longer needed.
rm -f ~/vendor-check.php
```

**Keep `~/five_store_prev-$RELEASE` until the next deployment.** It is the
rollback path in section 9.0, and the only copy of the previous release's
`vendor/` that is known to be complete. Removing it does not break the
rollback, but it downgrades it from a two-command swap to rebuilding the
dependencies from `composer.lock` (section 9.0.1).

Backups in `~/backups` are **not** removed here. They stay until the retention
policy says otherwise, and `prices-*.tsv` in particular is the only artifact
that can recover prices after a rollback (section 9.3).

If a rollback becomes necessary in a later session, re-create the credentials
with the block in section 3.1 before running anything in section 9.
