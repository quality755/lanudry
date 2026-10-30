<?php
declare(strict_types=1);

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    $user = auth_user();
    return ($user['role'] ?? '') === 'admin';
}

function require_auth(): void
{
    if (!auth_user()) {
        $_SESSION['intended'] = $_GET['r'] ?? 'dashboard';
        redirect('login');
    }
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        flash('error', 'Only administrators can open that screen.');
        redirect('dashboard');
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    log_activity('Signed in', $user['email']);
    return true;
}

function logout(): void
{
    log_activity('Signed out');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
