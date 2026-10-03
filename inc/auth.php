<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function current_user(): ?array
{
    if (empty($_SESSION['auth_user_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['auth_user_id'],
        'name' => (string) ($_SESSION['auth_name'] ?? ''),
        'email' => (string) ($_SESSION['auth_email'] ?? ''),
        'role' => (string) ($_SESSION['auth_role'] ?? 'customer'),
    ];
}

function is_logged_in(): bool { return current_user() !== null; }
function user_id(): ?int { return current_user()['id'] ?? null; }

function role_home_path(?string $role = null): string
{
    $role ??= current_user()['role'] ?? 'guest';
    return match ($role) {
        'admin' => '/AI-CAKE/admin/dashboard.php',
        'staff' => '/AI-CAKE/staff/dashboard.php',
        'customer' => '/AI-CAKE/my_orders.php',
        default => '/AI-CAKE/login.php',
    };
}

function require_login(string $loginPath = '/AI-CAKE/login.php'): void
{
    if (!is_logged_in()) {
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '/AI-CAKE/';
        redirect($loginPath);
    }
}

function require_role(array|string $roles): void
{
    require_login();
    $sessionUser = current_user();
    $allowedRoles = (array) $roles;
    if (!$sessionUser || !in_array($sessionUser['role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }

    // Revalidate the account on every protected request so role changes and deactivation
    // take effect immediately instead of trusting an old session indefinitely.
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        http_response_code(503);
        exit('Account verification is temporarily unavailable.');
    }
    $query = $pdo->prepare('SELECT role,is_active,phone_verified_at FROM users WHERE id=? LIMIT 1');
    $query->execute([$sessionUser['id']]);
    $databaseUser = $query->fetch();
    if (!$databaseUser || !(int) $databaseUser['is_active'] || $databaseUser['role'] !== $sessionUser['role']) {
        logout_user();
        redirect('/AI-CAKE/login.php?session=expired');
    }
}

function require_admin_role(): void { require_role('admin'); }
function require_staff_or_admin(): void { require_role(['staff', 'admin']); }
function require_customer(): void
{
    require_role('customer');
    global $pdo;
    $query = $pdo->prepare('SELECT phone_verified_at FROM users WHERE id=? LIMIT 1');
    $query->execute([user_id()]);
    if (!$query->fetchColumn()) {
        if (!empty($_SESSION['otp_challenge_id'])) {
            redirect('/AI-CAKE/verify_otp.php');
        }
        logout_user();
        redirect('/AI-CAKE/login.php?verification=required');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_name'] = (string) $user['name'];
    $_SESSION['auth_email'] = (string) $user['email'];
    $_SESSION['auth_role'] = (string) $user['role'];
    $_SESSION['user'] = (string) $user['name'];
    $_SESSION['role'] = (string) $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
