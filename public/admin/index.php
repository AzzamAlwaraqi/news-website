<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();

if (is_post()) {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $find = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $find->execute([$id]);
    $article = $find->fetch();
    if ($article && $action === 'delete') {
        db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
        delete_upload($article['image']);
        flash('Article deleted.');
    } elseif ($article && $action === 'publish') {
        db()->prepare("UPDATE articles SET status = 'published', published_at = COALESCE(published_at, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        flash('Article published.');
    } elseif ($article && $action === 'unpublish') {
        db()->prepare("UPDATE articles SET status = 'draft', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        flash('Article moved to drafts.');
    }
    redirect('admin/');
}

$status = in_array($_GET['status'] ?? '', ['draft', 'published'], true) ? $_GET['status'] : '';
$query = trim((string) ($_GET['q'] ?? ''));
$where = ['1 = 1'];
$params = [];
if ($status !== '') {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($query !== '') {
    $where[] = "a.title LIKE ? ESCAPE '\\'";
    $params[] = like_pattern($query);
}
$list = db()->prepare('SELECT a.id, a.title, a.slug, a.status, a.author, a.published_at, a.updated_at, c.name AS category_name FROM articles a JOIN categories c ON c.id = a.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.updated_at DESC, a.id DESC');
$list->execute($params);
$articles = $list->fetchAll();

$stats = db()->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'published'), 0) AS published, COALESCE(SUM(status = 'draft'), 0) AS drafts FROM articles")->fetch();
$categoryCount = count(all_categories());

admin_head('Articles', 'articles');
?>
<div class="page-head">
  <h1>Articles</h1>
  <a class="button primary" href="<?= e(url('admin/article.php')) ?>">New article</a>
</div>

<dl class="stats">
  <div><dt>Total</dt><dd><?= (int) $stats['total'] ?></dd></div>
  <div><dt>Published</dt><dd><?= (int) $stats['published'] ?></dd></div>
  <div><dt>Drafts</dt><dd><?= (int) $stats['drafts'] ?></dd></div>
  <div><dt>Categories</dt><dd><?= $categoryCount ?></dd></div>
</dl>

<form class="filters" method="get">
  <label class="sr" for="admin-q">Search titles</label>
  <input id="admin-q" type="search" name="q" value="<?= e($query) ?>" placeholder="Search titles">
  <label class="sr" for="admin-status">Status</label>
  <select id="admin-status" name="status">
    <option value="">All</option>
    <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
    <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Drafts</option>
  </select>
  <button type="submit">Filter</button>
</form>

<?php if (!$articles): ?>
  <div class="notice"><p>No articles match.</p></div>
<?php else: ?>
  <div class="table-scroll">
    <table>
      <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Published</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($articles as $article): ?>
        <tr>
          <td><a href="<?= e(url('admin/article.php?id=' . $article['id'])) ?>"><?= e($article['title']) ?></a></td>
          <td><?= e($article['category_name']) ?></td>
          <td><span class="tag <?= e($article['status']) ?>"><?= $article['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
          <td><?= e(format_date($article['published_at'])) ?: '—' ?></td>
          <td class="actions">
            <a href="<?= e(url('admin/article.php?id=' . $article['id'])) ?>">Edit</a>
            <?php if ($article['status'] === 'published'): ?>
              <a href="<?= e(url('article.php?slug=' . rawurlencode($article['slug']))) ?>">View</a>
            <?php endif; ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
              <button class="link" name="action" value="<?= $article['status'] === 'published' ? 'unpublish' : 'publish' ?>"><?= $article['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button>
              <button class="link danger" name="action" value="delete" data-confirm="Delete “<?= e($article['title']) ?>” for good?">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php page_foot();
