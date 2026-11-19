<?php
/**
 * DATNIX SOLUTIONS - CSRF Protection Library
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Session should be initialized with secure parameters
    @session_start();
}

/**
 * Generate or retrieve existing CSRF token for current session
 * 
 * @return string 64-character hex token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerate CSRF token (recommended after authentication / privilege escalation)
 * 
 * @return string
 */
function regenerateCsrfToken(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Validate submitted CSRF token using timing-safe comparison
 * 
 * @param string|null $token
 * @return bool
 */
function validateCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF input field for forms
 * 
 * @return string HTML input string
 */
function csrfField(): string {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Output meta tag for AJAX requests
 * 
 * @return string HTML meta tag string
 */
function csrfMeta(): string {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">';
}
