# docs.awarner.co.uk

A lightweight PHP wiki/docs system with Markdown content and login-based editing,
styled to match [awarner.co.uk](https://awarner.co.uk).

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension
- Apache (with `mod_rewrite`/`.htaccess` support) or Nginx

## Deployment

1. Point your web server's **document root** at the `public/` folder (not the
   repo root). `src/`, `templates/`, `data/` and `content/` must **not** be
   web-accessible directly (they're outside `public/`, and also carry a
   deny-all `.htaccess` for defense in depth on Apache).
2. Ensure `data/` and `content/` are writable by the web server user (the
   SQLite user database, Markdown pages and uploaded images are all created
   automatically on first use).
3. Visit the site. Since no users exist yet, you'll be redirected to
   `/setup.php` to create the first admin account.
4. Log in and start creating pages from the "New Page" button, or just drop
   `.md` files straight into `content/`.

Pages are served at pretty URLs like `/General/getting-started` (rewritten to
`page.php?path=...` under the hood). On Apache this is handled by
`public/.htaccess`; the Nginx config below has an equivalent rule.

For local development with PHP's built-in server (which ignores `.htaccess`),
use the bundled router so pretty URLs work the same way:

```sh
php -S localhost:8080 -t public public/router.php
```

### Nginx example

```nginx
server {
    listen 80;
    server_name docs.awarner.co.uk;
    root /var/www/docs.awarner.co.uk/public;
    index index.php;

    location / {
        try_files $uri $uri/ @pretty;
    }

    # Pretty page URLs: /Category/slug -> page.php?path=Category/slug
    location @pretty {
        rewrite ^/([^/]+)/([^/]+)/?$ /page.php?path=$1/$2&$args last;
        rewrite ^ /index.php?$query_string last;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

## Content: just add Markdown files

Pages are plain `.md` files on disk under `content/`, one sub-folder per
category:

```
content/
  uploads/
    2026/08/abc123....jpg
  General/
    getting-started.md
  Developers/
    api-reference.md
```

- The **folder name** is the category shown in the sidebar tree.
- The **filename** (without `.md`) becomes the page's URL slug.
- An optional front-matter block sets an explicit title; otherwise the first
  `# Heading` in the file (or the filename) is used:

  ```markdown
  ---
  title: Getting Started
  ---

  # Getting Started
  Write your content here...
  ```

You can add/edit these files directly (FTP, git, etc.) with no need to use
the web editor — new files just appear next time the sidebar/list is loaded.
The web editor (`/edit.php`) writes to the same files, so both approaches
are interchangeable. Use git (or backups) on the `content/` folder if you
want version history, since there's no in-app revision tracking.

## Syncing content with GitHub (two-way)

If you'd rather manage pages as a GitHub repo, `content/` can be kept in sync
with it both ways: a cron job pulls in changes pushed from elsewhere (e.g.
your own editor/CLI), and the web editor automatically commits and pushes
back any change made through `/edit.php`, `/delete.php` or an image upload.

1. Create a repo containing your `.md` pages in the same
   `Category/page-slug.md` layout described above.
2. Copy [src/content-repo.example.php](src/content-repo.example.php) to
   `src/content-repo.php` (git-ignored) and set your repo's details:

   ```php
   return [
       'url' => 'https://github.com/yourname/docs-content.git',
       'branch' => 'main',
       'author_name' => 'Docs Wiki',
       'author_email' => 'docs-wiki@localhost',
   ];
   ```

   `author_name`/`author_email` are the **fallback** identity used for
   commits from users who haven't set their own in `/profile.php` (every
   logged-in user has one — click "Profile" in the nav). Their commit
   `name` defaults to their username if they don't set one; email always
   falls back to this config value.

   The credentials need **write** access (not just read) since the web
   editor pushes back: use a fine-scoped token with push rights embedded in
   the HTTPS URL (`https://TOKEN@github.com/...`), or an SSH deploy key with
   write access. Either way, auth must work non-interactively for both the
   cron user and the web server user — for SSH, run `ssh-keyscan github.com`
   into that user's `known_hosts` ahead of time so it never prompts.
3. Run the initial pull manually to check it works: `php bin/sync-content.php`
4. Add it to cron, e.g. every 15 minutes:

   ```cron
   */15 * * * * php /path/to/docs.awarner.co.uk/bin/sync-content.php >> /path/to/docs.awarner.co.uk/data/content-sync.log 2>&1
   ```

How it fits together:

- `bin/sync-content.php` (cron) treats GitHub as the source of truth for its
  pull: it hard-resets `content/` to match `origin/<branch>` on every run.
- `src/ContentRepo.php` (web) commits and pushes whatever `/edit.php`,
  `/delete.php` or image uploads just changed, pulling/rebasing first to
  reduce the chance of a rejected push.
- Both share a lock file (`data/content-repo.lock`) so a cron pull and a
  web-triggered push can never run at the same time and corrupt each other's
  work.
- If a push fails (e.g. conflicting remote changes, no network), your edit
  is still saved locally and the page keeps working — you'll just see a
  flash message saying it wasn't pushed, so you can resolve it (check
  `content/` with `git status`/`git push` by hand) rather than losing work.

## Images

Images live in `content/uploads/YYYY/MM/`, right alongside the Markdown pages,
so the whole `content/` folder — pages and images — can be backed up, moved
or put under git as one unit. When GitHub sync is configured, uploaded images
are committed and pushed just like page edits.

Since `content/` sits outside the web root (`public/`) for security, images
are streamed through [public/media.php](public/media.php) rather than served
directly: reference them in Markdown as `![alt text](/media.php?file=2026/08/xyz.jpg)`.

Logged-in users can upload via the "Upload Image" panel on the edit page,
which validates the file (real image content-type check via `getimagesize` +
`finfo`, 8MB limit), re-encodes it with GD when available to strip any
embedded payloads, and saves it with a random filename — then shows the
Markdown snippet to copy in. You can also drop image files into
`content/uploads/` directly (e.g. via FTP/git) using the same
`YYYY/MM/filename.ext` layout and link to them the same way.

## Features

- Markdown pages stored as files (see above), grouped by category
- Session-based login with hashed passwords (`password_hash`/`password_verify`)
- No open self-registration — the first admin is created via `/setup.php`,
  and only admins can create further accounts (from `/users.php`)
- CSRF tokens on all state-changing forms
- Roles: `admin` (manage users, delete pages) and `editor` (create/edit pages)
- Optional two-way sync of `content/` with a GitHub repo — cron pulls,
  web edits auto-commit and push back (see above)
- Markdown rendered via [Parsedown](https://parsedown.org/) in safe mode (raw
  HTML in page content is stripped to prevent stored XSS)

## Project structure

```
public/        Web root — front controllers, CSS, media.php (image streaming)
src/           Application code (Auth, Database, helpers, Parsedown vendor lib)
bin/           CLI scripts (sync-content.php, run via cron)
templates/     Shared header/footer/sidebar layout
content/       Markdown pages (one folder per category) + uploads/, git-friendly
data/          SQLite database for users only (created automatically, git-ignored)
```
