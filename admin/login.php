<?php
/**
 * DATNIX SOLUTIONS - Secure Admin Login Handler
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

// Initialize session with secure params
$config = require __DIR__ . '/../config/config.php';
$sessConfig = $config['session'];

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

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already authenticated
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$notice = '';
$clientIp = getClientIp();
$lockoutWindow = $config['security']['login_lockout_time'] ?? 900; // 15 mins
$maxAttempts = $config['security']['login_max_attempts'] ?? 5;

// Check if first-admin setup is needed
$needsSetup = false;
if (!empty($pdo)) {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM admins');
        if ((int)$stmt->fetchColumn() === 0) {
            $needsSetup = true;
        }
    } catch (Throwable $e) {
        // Table may not yet be initialized
        $needsSetup = true;
    }
}

// Check message query params
if (isset($_GET['logged_out'])) {
    $notice = 'You have been successfully logged out.';
} elseif (isset($_GET['timeout'])) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
} elseif (isset($_GET['setup_complete'])) {
    $notice = 'Initial Administrator account created successfully! You can now log in.';
}

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        $error = 'Security validation failed (Invalid CSRF token). Please refresh and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Check IP brute-force attempts in login_attempts table
            $isBlocked = false;
            $remainingWait = 0;

            if (!empty($pdo)) {
                try {
                    $attemptStmt = $pdo->prepare('
                        SELECT COUNT(*), MIN(attempted_at) 
                        FROM login_attempts 
                        WHERE ip_address = :ip AND attempted_at > (NOW() - INTERVAL :window SECOND)
                    ');
                    $attemptStmt->execute([
                        ':ip'     => $clientIp,
                        ':window' => $lockoutWindow
                    ]);
                    $attemptData = $attemptStmt->fetch(PDO::FETCH_NUM);
                    $attemptCount = (int)($attemptData[0] ?? 0);

                    if ($attemptCount >= $maxAttempts) {
                        $isBlocked = true;
                        $oldestAttempt = strtotime($attemptData[1] ?? 'now');
                        $remainingWait = max(1, ($oldestAttempt + $lockoutWindow) - time());
                        $error = "Too many failed login attempts from your IP. Access locked for {$remainingWait} seconds.";
                    }
                } catch (Throwable $e) {
                    error_log('[LOGIN ATTEMPT CHECK ERROR] ' . $e->getMessage());
                }
            }

            if (!$isBlocked) {
                if (empty($pdo)) {
                    $error = 'Database connection is offline. Please check your MySQL server configuration.';
                } else {
                    try {
                        // Look up admin by username or email
                        $stmt = $pdo->prepare('
                            SELECT id, username, email, password_hash, full_name, role, status, failed_attempts, locked_until 
                            FROM admins 
                            WHERE username = :usr OR email = :usr 
                            LIMIT 1
                        ');
                        $stmt->execute([':usr' => $username]);
                        $admin = $stmt->fetch();

                        // Check if account is locked
                        if ($admin && !empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
                            $wait = strtotime($admin['locked_until']) - time();
                            $error = "This admin account is temporarily locked due to multiple failed logins. Please wait {$wait} seconds.";
                        } elseif ($admin && $admin['status'] === 'inactive') {
                            $error = 'This administrator account has been deactivated. Please contact your system superadmin.';
                        } elseif ($admin && password_verify($password, $admin['password_hash'])) {
                            // Successful authentication!
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);

                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = (int)$admin['id'];
                            $_SESSION['admin_username'] = $admin['username'];
                            $_SESSION['admin_email'] = $admin['email'];
                            $_SESSION['admin_full_name'] = $admin['full_name'];
                            $_SESSION['admin_role'] = $admin['role'];
                            $_SESSION['admin_user_agent'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
                            $_SESSION['admin_ip_subnet'] = md5(preg_replace('/\.\d+$/', '.0', $clientIp));
                            $_SESSION['last_activity'] = time();
                            $_SESSION['last_regenerated'] = time();

                            // Reset failed attempts & update last login
                            $updateStmt = $pdo->prepare('
                                UPDATE admins 
                                SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = :ip 
                                WHERE id = :id
                            ');
                            $updateStmt->execute([
                                ':ip' => $clientIp,
                                ':id' => $admin['id']
                            ]);

                            // Clean old login attempts for this IP
                            $cleanupStmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip');
                            $cleanupStmt->execute([':ip' => $clientIp]);

                            // Log activity
                            logActivity($pdo, $admin['id'], 'admin_login', 'admin', $admin['id'], 'Successful login from IP: ' . $clientIp);

                            // Refresh CSRF token for authenticated session
                            regenerateCsrfToken();

                            $redirect = $_GET['redirect'] ?? 'dashboard.php';
                            // Disallow open redirects
                            if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $redirect) || strpos($redirect, 'http') === 0 || strpos($redirect, '//') === 0) {
                                $redirect = 'dashboard.php';
                            }
                            header("Location: $redirect");
                            exit;
                        } else {
                            // Failed login
                            // Record attempt in login_attempts
                            $recStmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (:ip, :usr, NOW())');
                            $recStmt->execute([':ip' => $clientIp, ':usr' => $username]);

                            // Increment failed attempts on admin record if account matched username
                            if ($admin) {
                                $newFailed = (int)$admin['failed_attempts'] + 1;
                                $lockSql = '';
                                if ($newFailed >= $maxAttempts) {
                                    $lockSql = ', locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE), status = "locked"';
                                }
                                $failStmt = $pdo->prepare("UPDATE admins SET failed_attempts = :cnt $lockSql WHERE id = :id");
                                $failStmt->execute([':cnt' => $newFailed, ':id' => $admin['id']]);
                            }

                            // Log failed attempt
                            logActivity($pdo, null, 'admin_login_failed', 'admin', null, "Failed login attempt for username: '$username' from IP: $clientIp");

                            $error = 'Invalid administrator credentials. Please check your username and password.';
                        }
                    } catch (Throwable $e) {
                        error_log('[LOGIN EXCEPTION] ' . $e->getMessage());
                        $error = 'An error occurred during authentication. Please try again.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Admin Login | DATNIX SOLUTIONS</title>
  
  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="../favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-body">

<div class="login-card">
  <div class="login-header">
    <div class="login-logo-card">
      <img src="../assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" width="160" height="42">
    </div>
    <h1 class="login-title">Business Management System</h1>
    <p class="login-subtitle">Secure administrative access &bull; DATNIX SOLUTIONS</p>
  </div>

  <div class="login-body-form">
    <?php if ($needsSetup): ?>
      <div class="alert alert-warning" style="display: block;">
        <strong>First-Time Setup Required:</strong> No administrator accounts found in the database.
        <div style="margin-top: 0.5rem;">
          <a href="setup.php" class="btn btn-primary btn-sm">Create First Administrator &rarr;</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($notice)): ?>
      <div class="alert alert-success">
        <?php echo e($notice); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" autocomplete="off">
      <?php echo csrfField(); ?>

      <div class="form-group">
        <label for="username" class="form-label">Username or Business Email</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="admin or name@datnixsolutions.com" required autofocus value="<?php echo e($_POST['username'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          <span>Sign In to Admin Panel</span>
        </button>
      </div>
    </form>

    <div style="margin-top: 1.75rem; text-align: center; border-top: 1px solid var(--admin-border); padding-top: 1.25rem;">
      <a href="../index.html" style="font-size: 13px; color: var(--admin-text-muted); display: inline-flex; align-items: center; gap: 4px;">
        &larr; Back to DATNIX SOLUTIONS Website
      </a>
    </div>
  </div>
</div>

</body>
</html>
