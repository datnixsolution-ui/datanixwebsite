<?php
/**
 * DATNIX SOLUTIONS - Admin Authentication Middleware & Session Guard
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

// Load configuration
$config = require __DIR__ . '/../../config/config.php';
$sessConfig = $config['session'];

// Harden session configuration before session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $sessConfig['lifetime'] ?? 7200,
            'path'     => $sessConfig['path'] ?? '/',
            'domain'   => $sessConfig['domain'] ?? '',
            'secure'   => (bool)($sessConfig['secure'] ?? false),
            'httponly' => true,
            'samesite' => $sessConfig['samesite'] ?? 'Lax'
        ]);
    } else {
        session_set_cookie_params(
            $sessConfig['lifetime'] ?? 7200,
            ($sessConfig['path'] ?? '/') . '; samesite=' . ($sessConfig['samesite'] ?? 'Lax'),
            $sessConfig['domain'] ?? '',
            (bool)($sessConfig['secure'] ?? false),
            true
        );
    }

    session_name($sessConfig['name'] ?? 'DATNIX_ADMIN_SESS');
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

/**
 * Enforce authentication for protected admin pages
 * Redirects unauthenticated visitors to login.php
 */
function requireAuth(): void {
    global $config;
    $idleTimeout = $config['session']['idle_timeout'] ?? 1800; // 30 mins

    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
        $requestedUrl = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
        header('Location: /admin/login.php?redirect=' . urlencode($requestedUrl));
        exit;
    }

    // Check idle timeout
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleTimeout) {
        logoutAdmin();
        header('Location: /admin/login.php?timeout=1');
        exit;
    }

    // User-agent fingerprint check to prevent session hijacking
    $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!empty($_SESSION['admin_user_agent']) && !hash_equals($_SESSION['admin_user_agent'], md5($currentUa))) {
        logoutAdmin();
        header('Location: /admin/login.php?error=session_invalid');
        exit;
    }

    // Periodic session ID rotation to eliminate long-lived session fixation
    $rotationInterval = $config['session']['rotation_time'] ?? 900; // 15 mins
    if (empty($_SESSION['last_regenerated'])) {
        $_SESSION['last_regenerated'] = time();
    } elseif ((time() - $_SESSION['last_regenerated']) > $rotationInterval) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }

    // Refresh activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Enforce role-based access control
 * 
 * @param array $allowedRoles E.g. ['superadmin', 'admin']
 */
function requireRole(array $allowedRoles): void {
    requireAuth();
    $role = $_SESSION['admin_role'] ?? 'editor';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>403 Forbidden</h2><p>You do not have administrative permission to access this resource.</p><p><a href="/admin/dashboard.php">Return to Dashboard</a></p></body></html>';
        exit;
    }
}

/**
 * Return current logged-in admin data
 * 
 * @return array
 */
function getCurrentAdmin(): array {
    return [
        'id'        => (int)($_SESSION['admin_id'] ?? 0),
        'username'  => $_SESSION['admin_username'] ?? '',
        'email'     => $_SESSION['admin_email'] ?? '',
        'full_name' => $_SESSION['admin_full_name'] ?? 'Administrator',
        'role'      => $_SESSION['admin_role'] ?? 'admin'
    ];
}

/**
 * Log out and securely destroy admin session & cookies
 */
function logoutAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION = [];

    // Expire session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    @session_destroy();
}
