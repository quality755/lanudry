<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/app/routes.php';

$route = trim((string) ($_GET['r'] ?? 'dashboard'), '/');
if ($route === '') {
    $route = 'dashboard';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    handle_action();
}

$table = routes()[$route] ?? null;

if ($table === null) {
    http_response_code(404);
    $table = ['file' => 'pages/404.php', 'title' => 'Not found', 'auth' => true];
}

if (($table['auth'] ?? true) === true) {
    if (auth_user() === null) {
        $_SESSION['intended'] = $route;
        flash('error', 'Sign in to keep working.');
        redirect('login');
    }
    if (($table['admin'] ?? false) === true && !is_admin()) {
        flash('error', 'That area is admin only.');
        redirect('dashboard');
    }
} elseif (auth_user() !== null && $route === 'login') {
    redirect('dashboard');
}

$pageTitle = $table['title'];
$layout = $table['layout'] ?? 'app';

// CSV export has to run before any markup is written.
if ($route === 'reports' && query('export') === 'csv') {
    require APP_ROOT . '/pages/reports_export.php';
}

require APP_ROOT . '/views/layout/header.php';
require APP_ROOT . '/' . $table['file'];
require APP_ROOT . '/views/layout/footer.php';
