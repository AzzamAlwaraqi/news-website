<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();

$error = null;
if (is_post()) {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));

    if ($action === 'delete') {
        $used = db()->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $used->execute([$id]);
        if ((int) $used->fetchColumn() > 0) {
            $error = 'That category still has articles. Move or delete them first.';
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('Category deleted.');
            redirect('admin/categories.php');
        }
    } elseif ($name === '' || strlen($name) > 40) {
        $error = 'Category names can be up to 40 characters and can’t be empty.';
    } else {
        $same = db()->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?');
        $same->execute([$name, $id]);
        if ((int) $same->fetchColumn() > 0) {
            $error = 'There is already a category with that name.';
        } elseif ($action === 'rename') {
            db()->prepare('UPDATE categories SET name = ?, slug = ? WHERE id = ?')->execute([$name, unique_slug('categories', $name, $id), $id]);
            flash('Category renamed.');
            redirect('admin/categories.php');
        } elseif ($action === 'add') {
            db()->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')->execute([$name, unique_slug('categories', $name)]);
            flash('Category added.');
            redirect('admin/categories.php');
        }
    }
}

$categories = db()->query('SELECT c.*, COUNT(a.id) AS articles FROM categories c LEFT JOIN articles a ON a.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();

admin_head('Categories', 'categories');
?>
<h1>Categories</h1>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

<form class="filters" method="post">
  <?= csrf_field() ?>
  <label class="sr" for="new-category">New category name</label>
  <input id="new-category" name="name" maxlength="40" placeholder="New category name" required>
  <button class="primary" name="action" value="add">Add category</button>
</form>

<div class="table-scroll">
  <table>
    <thead><tr><th>Name</th><th>Articles</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
      <tr>
        <td>
          <form class="inline" id="rename-<?= (int) $category['id'] ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
            <label class="sr" for="name-<?= (int) $category['id'] ?>">Name</label>
            <input id="name-<?= (int) $category['id'] ?>" name="name" value="<?= e($category['name']) ?>" maxlength="40" required>
            <button class="link" name="action" value="rename">Rename</button>
          </form>
        </td>
        <td><?= (int) $category['articles'] ?></td>
        <td class="actions">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
            <button class="link danger" name="action" value="delete" data-confirm="Delete the category “<?= e($category['name']) ?>”?">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php page_foot();
