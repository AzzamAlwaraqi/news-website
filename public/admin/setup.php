<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';

// Only works while no admin exists, so it can't be used to take over a running site
if (has_admin()) {
    redirect('admin/login.php');
}

$error = null;
$username = '';
if (is_post()) {
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!preg_match('/^[\w.-]{3,40}$/', $username)) {
        $error = 'Use 3 to 40 letters, numbers, dots, dashes or underscores for the username.';
    } else {
        $error = password_problem($password, (string) ($_POST['confirm'] ?? ''));
    }
    if ($error === null) {
        $insert = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        sign_in($username);
        flash('Your admin account is ready.');
        redirect('admin/');
    }
}

admin_head('Create admin account', '');
?>
<form class="panel narrow" method="post">
  <h1>Create the admin account</h1>
  <p class="muted">This page appears once, before any admin exists.</p>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username <input name="username" value="<?= e($username) ?>" required autocomplete="username"></label>
  <label>Password (at least 10 characters) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label>Repeat password <input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
  <button class="primary" type="submit">Create account</button>
</form>
<?php page_foot();
