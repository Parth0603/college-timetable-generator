<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function login_required(): void
{
    start_secure_session();

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    $lastActivity = $_SESSION['last_activity'] ?? time();
    if (time() - (int)$lastActivity > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function current_admin(): ?array
{
    start_secure_session();
    return $_SESSION['admin'] ?? null;
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    start_secure_session();
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(419);
        exit('Invalid or expired security token. Please reload the page.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    start_secure_session();
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array
{
    start_secure_session();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
