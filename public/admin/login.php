<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';

if (!has_admin()) {
    redirect('admin/setup.php');
}
if (current_admin() !== null) {
    redirect('admin/');
}

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 900;

$error = null;
$username = '';
if (is_post()) {
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - LOCK_SECONDS]);
    $count = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $count->execute([$ip]);

    if ((int) $count->fetchColumn() >= MAX_ATTEMPTS) {
        http_response_code(429);
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $find = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $find->execute([$username]);
        $admin = $find->fetch();
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            sign_in($admin['username']);
            redirect('admin/');
        }
        db()->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([$ip, time()]);
        $error = 'Wrong username or password.';
    }
}

admin_head('Log in', '');
?>
<form class="panel narrow" method="post">
  <h1>Admin log in</h1>
  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username <input name="username" value="<?= e($username) ?>" required autocomplete="username"></label>
  <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
  <button class="primary" type="submit">Log in</button>
</form>
<?php page_foot();
