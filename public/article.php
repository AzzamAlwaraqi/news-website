<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$find = db()->prepare("SELECT a.*, c.name AS category_name, c.slug AS category_slug FROM articles a JOIN categories c ON c.id = a.category_id WHERE a.slug = ? AND a.status = 'published'");
$find->execute([(string) ($_GET['slug'] ?? '')]);
$article = $find->fetch() ?: not_found('That article does not exist or has not been published.');

$more = db()->prepare("SELECT slug, title FROM articles WHERE category_id = ? AND status = 'published' AND id <> ? ORDER BY published_at DESC LIMIT 3");
$more->execute([$article['category_id'], $article['id']]);
$related = $more->fetchAll();

page_head($article['title'], $article['category_slug']);
?>
<article class="story">
  <p class="meta"><a href="<?= e(url('index.php?category=' . rawurlencode($article['category_slug']))) ?>"><?= e($article['category_name']) ?></a> · <?= e(format_date($article['published_at'])) ?> · By <?= e($article['author']) ?></p>
  <h1><?= e($article['title']) ?></h1>
  <p class="lede"><?= e($article['summary']) ?></p>
  <?php if (!empty($article['image'])): ?><div class="hero"><?= article_image($article) ?></div><?php endif; ?>
  <?php foreach (preg_split('/\R{2,}/', trim($article['body'])) as $paragraph): ?>
    <p><?= nl2br(e($paragraph)) ?></p>
  <?php endforeach; ?>
</article>

<?php if ($related): ?>
  <aside class="related">
    <h2>More in <?= e($article['category_name']) ?></h2>
    <ul>
      <?php foreach ($related as $item): ?>
        <li><a href="<?= e(url('article.php?slug=' . rawurlencode($item['slug']))) ?>"><?= e($item['title']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
<?php endif; ?>
<?php page_foot();
