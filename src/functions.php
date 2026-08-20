<?php
declare(strict_types=1);

require_once __DIR__ . '/Markdown/Parsedown.php';

// Shared helper functions used across the app.
//
// Pages are plain Markdown files on disk under CONTENT_DIR, one folder per
// category: content/{Category}/{page-slug}.md. This means pages can be added
// directly (via FTP/git/etc) without going through the web editor at all.
// An optional YAML-lite front matter block can set an explicit title:
//   ---
//   title: My Page Title
//   ---

function slugify(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/[^\pL\d]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('/-+/', '-', $text) ?? '';

    return $text !== '' ? $text : 'page-' . bin2hex(random_bytes(3));
}

// Sanitizes a single path segment (category folder name or slug) for safe
// lookup on disk - strips anything that could be used for path traversal,
// but otherwise leaves it alone (existing folders, e.g. synced from GitHub,
// may still legitimately contain spaces).
function sanitize_segment(string $name): string
{
    $name = trim($name);
    $name = str_replace(['/', '\\', "\0"], '', $name);
    $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    $name = trim($name, ". ");

    return $name !== '' ? $name : 'General';
}

// Used specifically when saving a category from user input (the web editor):
// spaces become underscores so newly-created categories never need a
// percent-encoded URL (e.g. "Evolution X CDN" -> "Evolution_X_CDN").
function category_folder_name(string $name): string
{
    return str_replace(' ', '_', sanitize_segment($name));
}

// Turns a category folder name back into a friendly display form, e.g.
// "Evolution_X_CDN" -> "Evolution X CDN".
function display_category(string $category): string
{
    return str_replace('_', ' ', $category);
}

function prettify_slug(string $slug): string
{
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

// Builds the pretty "/Category/slug" URL for a page (rewritten to
// page.php?path=... by .htaccess/nginx config or the local dev router).
function page_url(string $path): string
{
    $segments = array_map('rawurlencode', explode('/', $path, 2));

    return '/' . implode('/', $segments);
}

function render_markdown(string $markdown, ?string $baseDir = null): string
{
    static $parser = null;
    if ($parser === null) {
        $parser = new Parsedown();
        $parser->setSafeMode(true); // strips raw HTML/JS to prevent stored XSS
    }

    if ($baseDir !== null) {
        $markdown = rewrite_relative_image_paths($markdown, $baseDir);
    }

    return $parser->text($markdown);
}

// Lets Markdown files reference images with a relative path (e.g. next to the
// page, like "../uploads/2026/08/photo.jpg") by rewriting them to the
// /media.php URL that actually serves content/uploads/ (which sits outside
// the web root). Anything that doesn't resolve inside content/uploads/ is
// left untouched.
function rewrite_relative_image_paths(string $markdown, string $baseDir): string
{
    $uploadsRoot = realpath(UPLOAD_DIR);
    if ($uploadsRoot === false) {
        return $markdown;
    }

    $pattern = '/!\[([^\]]*)\]\((?!https?:\/\/|\/|#)([^)\s]+)((?:\s+"[^"]*")?)\)/i';

    return preg_replace_callback($pattern, function (array $m) use ($baseDir, $uploadsRoot) {
        $resolved = realpath($baseDir . '/' . rawurldecode($m[2]));
        if ($resolved === false || !str_starts_with($resolved, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return $m[0];
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($resolved, strlen($uploadsRoot)), '/\\'));

        return '![' . $m[1] . '](/media.php?file=' . rawurlencode($relative) . $m[3] . ')';
    }, $markdown) ?? $markdown;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Splits an optional front-matter block from the Markdown body.
function parse_markdown_file(string $absPath): array
{
    $raw = (string) file_get_contents($absPath);
    $title = null;
    $body = $raw;

    if (preg_match('/^---\s*\n(.*?)\n---\s*\n?/s', $raw, $m)) {
        $body = substr($raw, strlen($m[0]));
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^title:\s*(.+)$/i', trim($line), $tm)) {
                $title = trim($tm[1], " \t\"'");
            }
        }
    }

    if ($title === null && preg_match('/^#\s+(.+)$/m', $body, $hm)) {
        $title = trim($hm[1]);
    }

    return ['title' => $title, 'body' => $body];
}

function write_page_file(string $absPath, string $title, string $body): void
{
    $dir = dirname($absPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create directory: {$dir}");
    }

    $frontMatter = "---\ntitle: " . str_replace("\n", ' ', $title) . "\n---\n\n";
    file_put_contents($absPath, $frontMatter . ltrim($body) . "\n");
}

// Resolves a category/slug pair to an absolute file path, guaranteeing the
// result stays within CONTENT_DIR (defends against path traversal).
function resolve_page_file(string $category, string $slug, bool $mustExist = true): ?string
{
    $category = sanitize_segment($category);
    $slug = sanitize_segment($slug);
    if ($category === '' || $slug === '') {
        return null;
    }

    $file = CONTENT_DIR . '/' . $category . '/' . $slug . '.md';

    if (!$mustExist) {
        return $file;
    }

    $real = realpath($file);
    $realRoot = realpath(CONTENT_DIR);
    if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $real;
}

function build_page_entry(string $file, string $category, string $slug): array
{
    $parsed = parse_markdown_file($file);

    return [
        'path' => $category . '/' . $slug,
        'category' => $category,
        'slug' => $slug,
        'title' => $parsed['title'] ?? prettify_slug($slug),
        'updated_at' => date('Y-m-d H:i', (int) filemtime($file)),
    ];
}

function all_pages(): array
{
    $pages = [];
    if (!is_dir(CONTENT_DIR)) {
        return $pages;
    }

    foreach (glob(CONTENT_DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $category = basename($dir);
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $pages[] = build_page_entry($file, $category, basename($file, '.md'));
        }
    }

    usort($pages, fn (array $a, array $b) => [$a['category'], $a['title']] <=> [$b['category'], $b['title']]);

    return $pages;
}

function pages_by_category(): array
{
    $grouped = [];
    foreach (all_pages() as $page) {
        $grouped[$page['category']][] = $page;
    }
    ksort($grouped);

    return $grouped;
}

// $path is "Category/slug" as used in page URLs.
function find_page(string $path): ?array
{
    $parts = explode('/', $path, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$category, $slug] = $parts;
    $file = resolve_page_file($category, $slug);
    if ($file === null || !is_file($file)) {
        return null;
    }

    $parsed = parse_markdown_file($file);
    $category = sanitize_segment($category);
    $slug = sanitize_segment($slug);

    return [
        'path' => $category . '/' . $slug,
        'category' => $category,
        'slug' => $slug,
        'title' => $parsed['title'] ?? prettify_slug($slug),
        'content' => $parsed['body'],
        'updated_at' => date('Y-m-d H:i', (int) filemtime($file)),
        'file' => $file,
    ];
}

function render_layout(string $title, callable $content): void
{
    $pageTitle = $title;
    require APP_ROOT . '/templates/header.php';
    $content();
    require APP_ROOT . '/templates/footer.php';
}
