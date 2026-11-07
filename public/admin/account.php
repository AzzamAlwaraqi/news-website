<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();

$error = null;
if (is_post()) {
    require_csrf();
    $find = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $find->execute([current_admin()]);
    $admin = $find->fetch();
    $password = (string) ($_POST['password'] ?? '');
    if (!$admin || !password_verify((string) ($_POST['current'] ?? ''), $admin['password_hash'])) {
        $error = 'Your current password is wrong.';
    } else {
        $error = password_problem($password, (string) ($_POST['confirm'] ?? ''));
    }
    if ($error === null) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        session_regenerate_id(true);
        flash('Password changed.');
        redirect('admin/account.php');
    }
}

admin_head('Account', 'account');
?>
<form class="panel narrow" method="post">
  <h1>Change password</h1>
  <p class="muted">Signed in as <?= e(current_admin()) ?></p>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
  <label>New password (at least 10 characters) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label>Repeat new password <input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
  <button class="primary" type="submit">Change password</button>
</form>
<?php page_foot();
