<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$query = trim((string) ($_GET['q'] ?? ''));
$slug = (string) ($_GET['category'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$category = null;
if ($slug !== '') {
    $find = db()->prepare('SELECT * FROM categories WHERE slug = ?');
    $find->execute([$slug]);
    $category = $find->fetch() ?: not_found('That category does not exist.');
}

$where = ["a.status = 'published'"];
$params = [];
if ($category) {
    $where[] = 'a.category_id = ?';
    $params[] = $category['id'];
}
if ($query !== '') {
    $where[] = "(a.title LIKE ? ESCAPE '\\' OR a.summary LIKE ? ESCAPE '\\' OR a.body LIKE ? ESCAPE '\\')";
    array_push($params, like_pattern($query), like_pattern($query), like_pattern($query));
}
$clause = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM articles a WHERE $clause");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($page, $pages);

$list = db()->prepare("SELECT a.*, c.name AS category_name FROM articles a JOIN categories c ON c.id = a.category_id WHERE $clause ORDER BY a.published_at DESC, a.id DESC LIMIT ? OFFSET ?");
$list->execute([...$params, PER_PAGE, ($page - 1) * PER_PAGE]);
$articles = $list->fetchAll();

$heading = $category ? $category['name'] : 'Top news';
page_head($heading, $category ? $category['slug'] : 'home');

$link = function (int $number) use ($slug, $query): string {
    return url('index.php?' . http_build_query(array_filter(['category' => $slug, 'q' => $query, 'page' => $number > 1 ? $number : null])));
};
?>
<div class="page-head">
  <h1><?= e($heading) ?></h1>
  <form class="search" method="get" action="<?= e(url('index.php')) ?>" role="search">
    <?php if ($slug !== ''): ?><input type="hidden" name="category" value="<?= e($slug) ?>"><?php endif; ?>
    <label class="sr" for="q">Search articles</label>
    <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="Search articles">
    <button type="submit">Search</button>
  </form>
</div>

<?php if ($query !== ''): ?>
  <p class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($query) ?>”</p>
<?php endif; ?>

<?php if (!$articles): ?>
  <div class="notice"><h2>No articles here yet</h2><p><a href="<?= e(url()) ?>">See all the news</a></p></div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($articles as $article): ?>
      <article class="card">
        <a class="thumb" href="<?= e(url('article.php?slug=' . rawurlencode($article['slug']))) ?>" tabindex="-1" aria-hidden="true"><?= article_image($article) ?></a>
        <div class="card-body">
          <p class="meta"><?= e($article['category_name']) ?> · <?= e(format_date($article['published_at'])) ?></p>
          <h2><a href="<?= e(url('article.php?slug=' . rawurlencode($article['slug']))) ?>"><?= e($article['title']) ?></a></h2>
          <p><?= e($article['summary']) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>">Newer</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>">Older</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php page_foot();
