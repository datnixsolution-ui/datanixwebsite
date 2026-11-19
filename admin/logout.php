<?php
/**
 * DATNIX SOLUTIONS - Admin Logout Handler
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';

$admin = getCurrentAdmin();
if (!empty($admin['id']) && !empty($pdo)) {
    logActivity($pdo, $admin['id'], 'admin_logout', 'admin', $admin['id'], 'Admin logged out: ' . $admin['username']);
}

logoutAdmin();
header('Location: login.php?logged_out=1');
exit;
