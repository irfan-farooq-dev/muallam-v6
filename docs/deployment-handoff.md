# Muallam deployment: handoff for a new session

Context for continuing the muallam.com go-live in a fresh chat. Read this file, then `docs/go-live-checklist.html` (sections 1 to 5 are the step-by-step server guide; the `.txt` copy has the same content).

## Project in one paragraph

Muallam v5 is a blog built in `~/Projects/PHP/muallam-v5`. It runs on Laravel 13 and PHP 8.4, with Blade, Bootstrap 5.3 and Alpine. There is no build step (no Vite, Tailwind or npm). Locally it is served by Herd at http://muallam-v5.test with MySQL 9.7 (DBngin). Features 1 to 17 are done and merged: public site, auth, profiles, posts, likes, comments, restricted sharing, SEO and the admin panel. The admin panel covers dashboard, users, categories, posts with admin-edit highlights, and comments. There are 450 PHPUnit tests, all passing. `CLAUDE.md` holds the project rules, and the database design is in `docs/database.md`.

## Working agreements with the user

- One git branch per feature. Report the changes plus a manual test checklist.
- The user tests in the UI, then commits, pushes and merges. **Never commit unless asked, and never `git push`** (push is denied in `.claude/settings.local.json`).
- Composer require/remove/update, `rm`, git commit/merge/reset and `db:wipe` need the user's approval.
- Never read or edit `.env`. `.env.prod` exists locally; it is the user's file and is ignored by git (`.env.*`). Don't open it either.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Run tests with `php artisan test --compact`.

## Current state (8 Nov 2026)

- `main` = `Dev` = `777a970` ("Merge branch 'feature/deployment' into Dev"). The working tree is clean.
- Upload package already built: `~/Projects/PHP/muallam-deploy/muallam-777a970-20261107-214530.zip` (18.5 MB).
- **The user is now uploading it to the server.** Nothing has been confirmed on the server yet. The next step is to help with whatever `deploy.log` (or a cron error) shows.

## The server (facts)

| | |
|---|---|
| Hosting | cPanel shared hosting. **No SSH/terminal**: the user uses FTP (FileZilla) or zip upload plus File Manager. |
| PHP | 8.4 (8.4.27 seen in the phpMyAdmin export header) |
| Database | **MariaDB 10.11** (`10.11.19-MariaDB-cll-lve`, so CloudLinux). Use the `mysql` driver with `utf8mb4_unicode_ci` collation. |
| Email | The host's own SMTP (`no-reply@muallam.com`, usually `mail.muallam.com`, port 465, `MAIL_SCHEME=smtps`) |
| Old site | Was live on muallam.com but inactive: no posts, comments or likes. **Only its users are imported.** The old database stays on the same server. |
| Layout | App in `/home/CPANELUSER/muallam` (outside the web root). Public files in `/home/CPANELUSER/public_html`. |

## How deployment works (built in `feature/deployment`)

1. **`php artisan site:package`** (`app/Console/Commands/PackageSite.php`), run on the Mac:
   - Takes **committed** code via `git archive` and runs `composer install --no-dev` into it.
   - Drops dev files (tests, docs, `.ai`, `.claude`, `CLAUDE.md` and so on).
   - Moves `public/` to `public_html/` and rewrites `index.php` to load `../muallam/...`.
   - Writes a `RELEASE` file (`<sha>-<UTC time>`) and zips everything into `../muallam-deploy/`.
   - Options: `--ref`, `--app-folder=muallam`, `--output`.
2. **The user** uploads the zip to the home folder and extracts it, which creates or overwrites `muallam/` and `public_html/`.
3. **One cron job**, every minute:
   `cd /home/CPANELUSER/muallam && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
   - If that PHP path is wrong, try `/usr/local/bin/ea-php84` or `/usr/local/bin/php`.
4. The scheduler (`routes/console.php`) runs **`site:deploy`** every minute (`app/Console/Commands/DeploySite.php`):
   - It does nothing unless `RELEASE` differs from `storage/app/deployed-release`.
   - When there is a new release, it runs in order:
     1. `migrate --force`
     2. `db:seed --class=CategorySeeder --force`
     3. `legacy:import-users`, only while `LEGACY_DB_DATABASE` is set
     4. `storage:link`, which uses `PUBLIC_STORAGE_LINK` from `.env`
     5. `optimize`
   - Output is appended to `storage/logs/deploy.log`. A failed step is retried every minute.
   - The scheduler uses file locks (`Schedule::useCache('file')`).
5. **To re-run a deploy without uploading** (for example after editing `.env`, since config is cached): edit `muallam/RELEASE` in File Manager and append `-1`.

### Production `.env` essentials

The full template is in checklist section 2.
- `APP_ENV=production` and `APP_DEBUG=false`.
- `APP_KEY` generated on the Mac with `php artisan key:generate --show`.
- **`CACHE_STORE=file`** (required: with `database`, the scheduler crashes on a fresh database because it checks the `cache` table first).
- `DB_*` for the new database.
- `LEGACY_DB_DATABASE` set to the old database's name. Add `LEGACY_DB_USERNAME`/`PASSWORD` only if the new DB user can't read it. Remove all `LEGACY_DB_*` lines after the import.
- `PUBLIC_STORAGE_LINK=/home/CPANELUSER/public_html/storage`.
- `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=database`.
- SMTP `MAIL_*`.

## Old-member import (`legacy:import-users`)

- Code: `app/Services/LegacyUserImporter.php` and `app/Console/Commands/ImportLegacyUsers.php`, with the `legacy` connection in `config/database.php`. Tests: `tests/Feature/ImportLegacyUsersTest.php`.
- Old `users` table columns: name, slug, email, password (bcrypt), role (10/20 = admin, 30 = user), status (0 = disabled), phone, gender (Male/Female) and timestamps.
- Mapping:
  - Passwords are copied as they are (bcrypt), and Laravel rehashes them on login.
  - Role 10 or 20 becomes admin; everyone else becomes author.
  - Gender is lowercased. Invalid phones are dropped.
  - The old slug is kept if it is valid, free and not reserved. Otherwise a new one is generated.
- Skipped:
  - Emails that already exist (so it is safe to re-run).
  - Names containing links (1 scam account).
  - Random-letter bot names (5 accounts). The rule is one word, 12 or more letters, with at least 3 capitals after the first letter.
  - Non-bcrypt passwords and invalid emails.
- Expected on the live data: **20 imported, 6 skipped**. `admin@muallam.com` ("Admin User") becomes an admin with slug `admin-user` (`admin` is reserved).
- No old email was ever verified: members verify from the dashboard before writing. One old avatar file was not carried over.
- `--dry-run` shows the result without saving. A local copy of the old dump is loaded in the local database `muallam_legacy` (the dump itself is in `~/Projects/PHP/muallam-import/`, outside the repo).

## Old-URL redirects (`routes/web.php`, end of file)

All are permanent (301):
- `/blogs` → `/`
- `/blogs/{slug}` → `/posts/{slug}`
- `/signup` → `/register`
- `/{slug}/blogs` → `/users/{slug}/posts`

## Rehearsal done (local, passed)

Extracted the package into a fake home folder with a blank database, then ran `schedule:run`:
- Migrations, categories, the import, the storage symlink into `public_html` and the caches all succeeded.
- Requests through `public_html/index.php` served Home, Sign in, the admin redirect, the old-URL redirect, `robots.txt` and the styled 404.
- A second run was a no-op.

## Likely next steps or issues in the new session

1. **No `deploy.log` after a few minutes:** the cron isn't running.
   - Usually the PHP path is wrong, or the cron uses an old PHP.
   - Ask the user to log cron errors to a file: replace `>> /dev/null 2>&1` with `>> /home/CPANELUSER/cron.log 2>&1`.
2. **A step fails in `deploy.log`:**
   - Wrong `DB_*` credentials (cPanel names are prefixed with `CPANELUSER_`).
   - The DB user has no access to the old database.
   - `PUBLIC_STORAGE_LINK` path is wrong, or symlinks are not allowed.
3. **500 error on the site:**
   - Check `muallam/storage/logs/laravel.log`.
   - Common causes: `APP_KEY` missing, `storage/` or `bootstrap/cache` not writable, or the deploy hasn't finished yet (the `sessions` table is missing).
4. **Images not showing:** the `public_html/storage` symlink is missing or broken. The host may need `SymLinksIfOwnerMatch`.
5. **After it is live**, work through checklist sections 6 to 9:
   - Email: SPF, DKIM and DMARC through Email Deliverability, then test verification, password reset and invites.
   - Smoke test.
   - SEO: robots.txt, sitemap, Search Console.
   - Backups.
   - Then remove the `LEGACY_DB_*` lines and keep a copy of `.env` with `APP_KEY`.
6. **Later updates:** commit, run `php artisan site:package`, upload and extract over the existing folders. The cron deploys it automatically.

## Decisions still open (checklist section 10)

- Time zone: `UTC` or `Asia/Karachi`. Decide before writers schedule posts.
- Whether replies under a hidden comment still count towards the comment total.
- A like button on post cards.
- GIF support.

Deferred features are listed in checklist section 11.
