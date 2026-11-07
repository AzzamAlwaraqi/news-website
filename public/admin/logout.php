<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';

if (is_post()) {
    require_csrf();
    $_SESSION = [];
    session_destroy();
}
redirect('admin/login.php');
