<?php
declare(strict_types=1);

function theme_toggle(): string
{
    return '<button type="button" class="theme" id="theme-toggle" aria-pressed="false">Dark mode</button>';
}

function page_head(string $title, ?string $active = null): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(SITE_NAME) ?></title>
  <script src="<?= e(url('assets/theme.js')) ?>"></script>
  <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url()) ?>"><?= e(SITE_NAME) ?></a>
    <button type="button" class="menu" id="menu-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" class="nav" aria-label="Sections">
      <a href="<?= e(url()) ?>"<?= $active === 'home' ? ' aria-current="page"' : '' ?>>Home</a>
      <?php foreach (all_categories() as $category): ?>
        <a href="<?= e(url('index.php?category=' . rawurlencode($category['slug']))) ?>"<?= $active === $category['slug'] ? ' aria-current="page"' : '' ?>><?= e($category['name']) ?></a>
      <?php endforeach; ?>
      <?= theme_toggle() ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php
}

function admin_head(string $title, string $active): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title) ?> · Admin</title>
  <script src="<?= e(url('assets/theme.js')) ?>"></script>
  <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body class="admin">
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('admin/')) ?>">Admin</a>
    <button type="button" class="menu" id="menu-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" class="nav" aria-label="Admin">
      <?php if (current_admin() !== null): ?>
        <a href="<?= e(url('admin/')) ?>"<?= $active === 'articles' ? ' aria-current="page"' : '' ?>>Articles</a>
        <a href="<?= e(url('admin/categories.php')) ?>"<?= $active === 'categories' ? ' aria-current="page"' : '' ?>>Categories</a>
        <a href="<?= e(url('admin/account.php')) ?>"<?= $active === 'account' ? ' aria-current="page"' : '' ?>>Account</a>
      <?php endif; ?>
      <a href="<?= e(url()) ?>">View site</a>
      <?= theme_toggle() ?>
      <?php if (current_admin() !== null): ?>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="link" type="submit">Log out</button></form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php
    $message = flash();
    if ($message !== null) {
        echo '<p class="flash" role="status">' . e($message) . '</p>';
    }
}

function page_foot(): void
{
    ?>
</main>
<footer class="footer">
  <div class="wrap">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · Sample articles are made up for demonstration · <a href="<?= e(url('admin/')) ?>">Admin</a></div>
</footer>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
