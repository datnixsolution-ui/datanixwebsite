<?php
/**
 * DATNIX SOLUTIONS - First-Admin Setup Wizard
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * 
 * SECURITY LOCKOUT:
 * This script will automatically lock itself once an administrator account
 * exists in the database. It cannot be used to overwrite or create additional
 * accounts once the initial administrator has been provisioned.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$setupLocked = false;

if (empty($pdo)) {
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Database Offline</h2><p>Cannot connect to MySQL database. Please verify credentials in <code>config/config.php</code> and run <code>database.sql</code> schema first.</p></body></html>');
}

// Check if any admin account already exists
try {
    $checkStmt = $pdo->query('SELECT COUNT(*) FROM admins');
    $adminCount = (int)$checkStmt->fetchColumn();
    if ($adminCount > 0) {
        $setupLocked = true;
    }
} catch (Throwable $e) {
    // If table doesn't exist, provide helpful schema notice
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;"><h2>Database Schema Not Installed</h2><p>The <code>admins</code> table was not found. Please import <code>database.sql</code> into your MySQL database before running setup.</p></body></html>');
}

if ($setupLocked) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Setup Locked | DATNIX SOLUTIONS</title><link rel="stylesheet" href="assets/css/admin.css"></head><body class="login-body"><div class="login-card" style="padding:2.5rem;text-align:center;"><div class="login-logo-card"><img src="../assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" height="38"></div><h2 style="color:#092D78;margin-bottom:0.75rem;">Setup Wizard Locked</h2><p style="color:#64748B;margin-bottom:1.5rem;line-height:1.6;">An administrator account already exists. For security reasons, the initial setup wizard has been locked. To manage administrator accounts, sign in to the admin panel.</p><a href="login.php" class="btn btn-primary">Go to Admin Login &rarr;</a></div></body></html>');
}

// Process setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        $error = 'Security validation failed (Invalid CSRF token). Please refresh and try again.';
    } else {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required to create the primary administrator account.';
        } elseif (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_\.-]+$/', $username)) {
            $error = 'Username must be between 3 and 50 alphanumeric characters (letters, numbers, dot, underscore, dash).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid administrative email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } elseif (!preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
            $error = 'Password must contain a combination of letters and numbers for administrative security.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter your password.';
        } else {
            try {
                // Double check race condition
                $checkStmt = $pdo->query('SELECT COUNT(*) FROM admins');
                if ((int)$checkStmt->fetchColumn() > 0) {
                    die('Setup has already been completed by another process.');
                }

                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('
                    INSERT INTO admins (username, email, password_hash, full_name, role, status, created_at, updated_at)
                    VALUES (:username, :email, :hash, :full_name, "superadmin", "active", NOW(), NOW())
                ');
                $stmt->execute([
                    ':username'  => $username,
                    ':email'     => $email,
                    ':hash'      => $hash,
                    ':full_name' => $fullName
                ]);

                $newAdminId = (int)$pdo->lastInsertId();

                // Log initial activity
                logActivity($pdo, $newAdminId, 'setup_first_admin', 'admin', $newAdminId, 'Initial Superadmin created: ' . $username);

                // Redirect to login
                header('Location: login.php?setup_complete=1');
                exit;
            } catch (Throwable $e) {
                error_log('[SETUP ERROR] ' . $e->getMessage());
                $error = 'Failed to create administrator account: ' . $e->getMessage();
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
  <title>First-Admin Setup | DATNIX SOLUTIONS</title>
  
  <link rel="icon" type="image/x-icon" href="../favicon.ico">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-body">

<div class="login-card" style="max-width: 500px;">
  <div class="login-header">
    <div class="login-logo-card">
      <img src="../assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" width="160" height="42">
    </div>
    <h1 class="login-title">First Administrator Setup</h1>
    <p class="login-subtitle">Initialize your primary superadmin account to manage DATNIX operations</p>
  </div>

  <div class="login-body-form">
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <div class="alert alert-info" style="font-size: 12.5px; margin-bottom: 1.25rem;">
      <strong>Security Notice:</strong> This wizard is active only when zero administrators exist. After completion, this setup route is automatically permanently locked.
    </div>

    <form method="POST" action="setup.php" autocomplete="off">
      <?php echo csrfField(); ?>

      <div class="form-group">
        <label for="full_name" class="form-label">Full Name</label>
        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Rahul Sharma" required value="<?php echo e($_POST['full_name'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin or rahul.datnix" required value="<?php echo e($_POST['username'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Official Email Address</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="e.g. datnixsolution@gmail.com" required value="<?php echo e($_POST['email'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Admin Password (min. 8 characters with letters &amp; numbers)</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
      </div>

      <div class="form-group">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••••••" required>
      </div>

      <div style="margin-top: 1.75rem;">
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          <span>Complete Setup &amp; Lock Wizard</span>
        </button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
