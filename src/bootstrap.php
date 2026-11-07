<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/public/uploads');
define('SITE_NAME', 'News Website');
define('PER_PAGE', 9);

foreach ([DATA_DIR, DATA_DIR . '/sessions', UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

session_save_path(DATA_DIR . '/sessions');
session_name('newssession');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

/** Escapes text for HTML. */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds a link that also works when the site lives in a subfolder. */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim((string) preg_replace('#/admin$#', '', $dir), '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function not_found(string $message = 'That page could not be found.'): never
{
    http_response_code(404);
    page_head('Not found');
    echo '<div class="notice"><h1>Not found</h1><p>' . e($message) . '</p><p><a href="' . e(url()) . '">Back to the news</a></p></div>';
    page_foot();
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Stops any form post that did not come from this site's own pages. */
function require_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('This form expired. Go back, reload the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $saved = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $saved;
}

function has_admin(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function current_admin(): ?string
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): void
{
    if (!has_admin()) {
        redirect('admin/setup.php');
    }
    if (current_admin() === null) {
        redirect('admin/login.php');
    }
}

function sign_in(string $username): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = $username;
}

/** Password rules, shared by first-time setup and the account page. */
function password_problem(string $password, string $confirm): ?string
{
    if (strlen($password) < 10) {
        return 'Use a password of at least 10 characters.';
    }
    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return null;
}

/** Turns a title into a link name, keeping Arabic and other letters. */
function slugify(string $text): string
{
    $slug = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', strtolower($text)), '-');
    if (preg_match('/^.{1,80}/u', $slug, $match)) {
        return rtrim($match[0], '-');
    }
    return 'item';
}

/** Makes a link name unique within a table, ignoring the row being edited. */
function unique_slug(string $table, string $text, int $ignoreId = 0): string
{
    $base = slugify($text);
    $slug = $base;
    $check = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?");
    for ($n = 2; ; $n++) {
        $check->execute([$slug, $ignoreId]);
        if ((int) $check->fetchColumn() === 0) {
            return $slug;
        }
        $slug = "$base-$n";
    }
}

/** Saves an uploaded picture under a random name. Returns the file name, or null when none was sent. */
function save_upload(array $file, ?string &$error): ?string
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > 2_000_000) {
        $error = 'The picture is too large. The limit is 2 MB.';
        return null;
    }
    if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'The picture could not be uploaded. Please try again.';
        return null;
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$type]) || @getimagesize($file['tmp_name']) === false) {
        $error = 'The picture must be a JPG, PNG or WebP file.';
        return null;
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$type];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        $error = 'The picture could not be saved.';
        return null;
    }
    return $name;
}

function delete_upload(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) && is_file(UPLOAD_DIR . '/' . $name)) {
        unlink(UPLOAD_DIR . '/' . $name);
    }
}

function format_date(?string $date): string
{
    return $date ? date('j M Y', strtotime($date)) : '';
}

/** Renders the picture for an article, or a coloured block with its category. */
function article_image(array $article): string
{
    if (!empty($article['image'])) {
        return '<img src="' . e(url('uploads/' . $article['image'])) . '" alt="" loading="lazy">';
    }
    $tone = (int) ($article['category_id'] ?? 0) % 6;
    return '<span class="placeholder tone-' . $tone . '">' . e($article['category_name'] ?? '') . '</span>';
}
