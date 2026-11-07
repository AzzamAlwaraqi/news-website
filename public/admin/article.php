<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$article = ['title' => '', 'summary' => '', 'body' => '', 'author' => (string) current_admin(), 'category_id' => 0, 'status' => 'draft', 'image' => null, 'slug' => ''];
if ($id > 0) {
    $find = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $find->execute([$id]);
    $article = $find->fetch() ?: not_found('That article does not exist.');
}
$categories = all_categories();
$errors = [];

if (is_post()) {
    require_csrf();
    foreach (['title', 'summary', 'body', 'author'] as $field) {
        $article[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $article['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $article['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';

    if ($article['title'] === '' || strlen($article['title']) > 200) {
        $errors[] = 'Give the article a title of up to 200 characters.';
    }
    if ($article['summary'] === '' || strlen($article['summary']) > 400) {
        $errors[] = 'Write a summary of up to 400 characters.';
    }
    if ($article['body'] === '') {
        $errors[] = 'The article needs some text.';
    }
    if ($article['author'] === '') {
        $errors[] = 'Add the author’s name.';
    }
    if (!in_array($article['category_id'], array_map(fn ($c) => (int) $c['id'], $categories), true)) {
        $errors[] = 'Pick a category.';
    }

    if (!$errors) {
        $uploadError = null;
        $newImage = save_upload($_FILES['image'] ?? [], $uploadError);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        } else {
            $oldImage = $article['image'];
            if ($newImage !== null) {
                $article['image'] = $newImage;
            } elseif (!empty($_POST['remove_image'])) {
                $article['image'] = null;
            }
            $values = [$article['title'], $article['summary'], $article['body'], $article['author'], $article['image'], $article['category_id'], $article['status']];
            if ($id > 0) {
                $update = db()->prepare("UPDATE articles SET title = ?, summary = ?, body = ?, author = ?, image = ?, category_id = ?, status = ?, published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, CURRENT_TIMESTAMP) ELSE published_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([...$values, $article['status'], $id]);
                flash('Article saved.');
            } else {
                $insert = db()->prepare("INSERT INTO articles (title, summary, body, author, image, category_id, status, slug, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'published' THEN CURRENT_TIMESTAMP END)");
                $insert->execute([...$values, unique_slug('articles', $article['title']), $article['status']]);
                flash('Article created.');
            }
            if ($oldImage !== $article['image']) {
                delete_upload($oldImage);
            }
            redirect('admin/');
        }
    }
}

admin_head($id > 0 ? 'Edit article' : 'New article', 'articles');
?>
<form class="panel" method="post" enctype="multipart/form-data">
  <h1><?= $id > 0 ? 'Edit article' : 'New article' ?></h1>
  <?php if ($errors): ?>
    <div class="error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="2000000">
  <label>Title <input name="title" value="<?= e($article['title']) ?>" maxlength="200" required></label>
  <div class="two">
    <label>Category
      <select name="category_id" required>
        <option value="">Choose…</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>"<?= (int) $article['category_id'] === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Author <input name="author" value="<?= e($article['author']) ?>" maxlength="80" required></label>
  </div>
  <label>Summary <textarea name="summary" rows="2" maxlength="400" required><?= e($article['summary']) ?></textarea></label>
  <label>Article text (leave a blank line between paragraphs) <textarea name="body" rows="12" required><?= e($article['body']) ?></textarea></label>
  <label>Picture (JPG, PNG or WebP, up to 2 MB) <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
  <?php if (!empty($article['image'])): ?>
    <div class="current-image">
      <img src="<?= e(url('uploads/' . $article['image'])) ?>" alt="Current picture">
      <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove this picture</label>
    </div>
  <?php endif; ?>
  <label>Status
    <select name="status">
      <option value="draft"<?= $article['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden from visitors)</option>
      <option value="published"<?= $article['status'] === 'published' ? ' selected' : '' ?>>Published</option>
    </select>
  </label>
  <div class="row">
    <button class="primary" type="submit">Save article</button>
    <a href="<?= e(url('admin/')) ?>">Cancel</a>
  </div>
</form>
<?php page_foot();
