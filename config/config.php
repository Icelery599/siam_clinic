<?php
/**
 * App bootstrap: session, DB connection, CSRF helpers, auth/RBAC helpers.
 * Include this at the top of every page:
 *   require_once __DIR__ . '/../config/config.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable once served over HTTPS
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

define('BASE_URL', '/siam-clinic'); // adjust to match your XAMPP htdocs subfolder

// ---------------------------------
// CSRF protection
// ---------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

// ---------------------------------
// Auth helpers
// ---------------------------------
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function require_role(array $roles): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        die('Access denied: you do not have permission to view this page.');
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function redirect_to_dashboard(): void
{
    $map = [
        'admin'   => '/admin/dashboard.php',
        'patient' => '/patient/dashboard.php',
    ];
    $role = $_SESSION['user_role'] ?? null;
    header('Location: ' . BASE_URL . ($map[$role] ?? '/login.php'));
    exit;
}

// ---------------------------------
// Flash messages
// ---------------------------------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ---------------------------------
// Small helpers
// ---------------------------------
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function audit_log(?int $userId, string $action, string $details = ''): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $action, $details]);
}
