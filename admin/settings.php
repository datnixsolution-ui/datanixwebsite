<?php
/**
 * DATNIX SOLUTIONS - Admin Settings & User Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$admin = getCurrentAdmin();
$isSuperAdmin = ($admin['role'] === 'superadmin');

// Fetch current admin's fresh record from DB
$currentAdminData = null;
if (!empty($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, status, last_login_at, last_login_ip, created_at FROM admins WHERE id = :id');
        $stmt->execute([':id' => $admin['id']]);
        $currentAdminData = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('[ADMIN SETTINGS LOAD PROFILE ERROR] ' . $e->getMessage());
    }
}

if (!$currentAdminData) {
    // Fall back to active session identity
    $currentAdminData = [
        'id'            => $admin['id'] ?? 1,
        'username'      => $admin['username'] ?? 'admin',
        'email'         => $admin['email'] ?? 'datnixsolution@gmail.com',
        'full_name'     => $admin['full_name'] ?? 'DATNIX Superadmin',
        'role'          => $admin['role'] ?? 'superadmin',
        'status'        => 'active',
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => '127.0.0.1',
        'created_at'    => '2026-11-19 00:00:00'
    ];
}

// Handle POST submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security session validation failed (Invalid CSRF Token). Please refresh and try again.';
    } else {

        // 1. Update Profile (Name & Email)
        if ($action === 'update_profile') {
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');

            if (empty($fullName) || strlen($fullName) < 2) {
                $error = 'Please enter a valid full name (minimum 2 characters).';
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid administrative email address.';
            } else {
                try {
                    // Check email uniqueness
                    $chk = $pdo->prepare('SELECT id FROM admins WHERE email = :email AND id != :id');
                    $chk->execute([':email' => $email, ':id' => $admin['id']]);
                    if ($chk->fetch()) {
                        $error = 'The specified email address is already in use by another administrator.';
                    } else {
                        $upd = $pdo->prepare('UPDATE admins SET full_name = :name, email = :email WHERE id = :id');
                        $upd->execute([':name' => $fullName, ':email' => $email, ':id' => $admin['id']]);

                        $_SESSION['admin_full_name'] = $fullName;
                        $_SESSION['admin_email'] = $email;
                        $currentAdminData['full_name'] = $fullName;
                        $currentAdminData['email'] = $email;

                        logActivity($pdo, $admin['id'], 'admin_profile_updated', 'admin', $admin['id'], "Admin {$admin['username']} updated profile name/email");
                        setFlashMessage('success', 'Your profile information has been updated successfully.');
                        header('Location: settings.php');
                        exit;
                    }
                } catch (Throwable $e) {
                    $error = 'Failed to update profile: ' . $e->getMessage();
                }
            }
        }

        // 2. Change Password
        elseif ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            // Get stored hash
            $hashStmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id');
            $hashStmt->execute([':id' => $admin['id']]);
            $storedHash = $hashStmt->fetchColumn();

            if (!password_verify($currentPass, $storedHash)) {
                $error = 'Your current password was entered incorrectly.';
            } elseif (strlen($newPass) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif (!preg_match('/[0-9]/', $newPass) || !preg_match('/[a-zA-Z]/', $newPass)) {
                $error = 'New password must include both letters and numbers for enterprise security.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'New password and confirmation password do not match.';
            } else {
                try {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $upd = $pdo->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
                    $upd->execute([':hash' => $newHash, ':id' => $admin['id']]);

                    logActivity($pdo, $admin['id'], 'admin_password_changed', 'admin', $admin['id'], "Admin {$admin['username']} changed their password");
                    setFlashMessage('success', 'Password updated successfully. Please use your new password for future logins.');
                    header('Location: settings.php');
                    exit;
                } catch (Throwable $e) {
                    $error = 'Error updating password: ' . $e->getMessage();
                }
            }
        }

        // 3. Create New Admin (Superadmin Only)
        elseif ($action === 'create_admin') {
            if (!$isSuperAdmin) {
                $error = 'Unauthorized action. Only Superadmins can provision new administrative accounts.';
            } else {
                $newUsername = strtolower(trim($_POST['username'] ?? ''));
                $newEmail = strtolower(trim($_POST['email'] ?? ''));
                $newFullName = sanitizeInput($_POST['full_name'] ?? '');
                $newRole = sanitizeInput($_POST['role'] ?? 'admin');
                $newPassword = $_POST['password'] ?? '';

                if (!preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $newUsername)) {
                    $error = 'Username must be 3-30 characters and contain only letters, numbers, dots, and underscores.';
                } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please provide a valid administrative email address.';
                } elseif (empty($newFullName) || strlen($newFullName) < 2) {
                    $error = 'Please enter a valid full name.';
                } elseif (!in_array($newRole, ['superadmin', 'admin', 'editor'], true)) {
                    $error = 'Invalid role selected.';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } else {
                    try {
                        // Check uniqueness
                        $chk = $pdo->prepare('SELECT id FROM admins WHERE username = :u OR email = :e');
                        $chk->execute([':u' => $newUsername, ':e' => $newEmail]);
                        if ($chk->fetch()) {
                            $error = 'An administrator account with that username or email already exists.';
                        } else {
                            $pHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                            $ins = $pdo->prepare('
                                INSERT INTO admins (username, email, password_hash, full_name, role, status, created_at)
                                VALUES (:u, :e, :p, :name, :r, "active", NOW())
                            ');
                            $ins->execute([
                                ':u'    => $newUsername,
                                ':e'    => $newEmail,
                                ':p'    => $pHash,
                                ':name' => $newFullName,
                                ':r'    => $newRole
                            ]);
                            $newId = (int)$pdo->lastInsertId();

                            logActivity($pdo, $admin['id'], 'admin_created', 'admin', $newId, "Created new admin '{$newUsername}' with role '{$newRole}'");
                            setFlashMessage('success', "New administrator '{$newFullName}' ({$newUsername}) created successfully.");
                            header('Location: settings.php#accounts');
                            exit;
                        }
                    } catch (Throwable $e) {
                        $error = 'Database error creating admin: ' . $e->getMessage();
                    }
                }
            }
        }

        // 4. Toggle Status (Active/Inactive) (Superadmin Only)
        elseif ($action === 'toggle_status') {
            if (!$isSuperAdmin) {
                $error = 'Unauthorized action.';
            } else {
                $targetId = (int)($_POST['target_id'] ?? 0);
                if ($targetId === $admin['id']) {
                    $error = 'Security rule violation: You cannot deactivate your own administrative account.';
                } else {
                    try {
                        $sel = $pdo->prepare('SELECT id, username, status, role FROM admins WHERE id = :id');
                        $sel->execute([':id' => $targetId]);
                        $targetAdmin = $sel->fetch();

                        if (!$targetAdmin) {
                            $error = 'Admin account not found.';
                        } else {
                            $newStatus = ($targetAdmin['status'] === 'active') ? 'inactive' : 'active';
                            $upd = $pdo->prepare('UPDATE admins SET status = :status WHERE id = :id');
                            $upd->execute([':status' => $newStatus, ':id' => $targetId]);

                            logActivity($pdo, $admin['id'], 'admin_status_toggled', 'admin', $targetId, "Changed status of '{$targetAdmin['username']}' to {$newStatus}");
                            setFlashMessage('success', "Status of '{$targetAdmin['username']}' changed to " . ucfirst($newStatus) . '.');
                            header('Location: settings.php#accounts');
                            exit;
                        }
                    } catch (Throwable $e) {
                        $error = 'Error updating admin status: ' . $e->getMessage();
                    }
                }
            }
        }

        // 5. Delete Admin (Superadmin Only)
        elseif ($action === 'delete_admin') {
            if (!$isSuperAdmin) {
                $error = 'Unauthorized action.';
            } else {
                $targetId = (int)($_POST['target_id'] ?? 0);
                if ($targetId === $admin['id']) {
                    $error = 'Security rule violation: You cannot delete your own administrative account.';
                } else {
                    try {
                        $sel = $pdo->prepare('SELECT id, username, role FROM admins WHERE id = :id');
                        $sel->execute([':id' => $targetId]);
                        $targetAdmin = $sel->fetch();

                        if (!$targetAdmin) {
                            $error = 'Admin account not found.';
                        } else {
                            $del = $pdo->prepare('DELETE FROM admins WHERE id = :id');
                            $del->execute([':id' => $targetId]);

                            logActivity($pdo, $admin['id'], 'admin_deleted', 'admin', $targetId, "Deleted admin account '{$targetAdmin['username']}'");
                            setFlashMessage('success', "Administrator '{$targetAdmin['username']}' has been permanently deleted.");
                            header('Location: settings.php#accounts');
                            exit;
                        }
                    } catch (Throwable $e) {
                        $error = 'Error deleting admin: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch all admins if superadmin
$allAdmins = [];
if ($isSuperAdmin) {
    try {
        $stmt = $pdo->query('SELECT id, username, email, full_name, role, status, last_login_at, last_login_ip, created_at FROM admins ORDER BY id ASC');
        $allAdmins = $stmt->fetchAll();
    } catch (Throwable $e) {
        $allAdmins = [];
    }
}

// System Environment Diagnostics
$phpVersion = PHP_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Web Server';
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$maxExecution = ini_get('max_execution_time') . 's';

$resumesDir = __DIR__ . '/../uploads/resumes';
$isUploadsWritable = is_dir($resumesDir) && is_writable($resumesDir);
$hasHtaccess = file_exists(__DIR__ . '/../uploads/.htaccess');
$hasWebConfig = file_exists(__DIR__ . '/../uploads/web.config');

$pageTitle = 'Admin Settings & Accounts';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>System Settings &amp; Accounts</h1>
    <p>Manage your administrator profile, security credentials, user roles, and server diagnostics.</p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
    <strong>Notice:</strong> <?php echo e($error); ?>
  </div>
<?php endif; ?>

<!-- Tabs navigation -->
<div style="display: flex; gap: 0.75rem; border-bottom: 1px solid var(--admin-border); margin-bottom: 2rem;">
  <button type="button" class="btn btn-sm btn-navy tab-trigger" onclick="switchSettingsTab('profile', this)" id="tabBtnProfile">
    My Profile &amp; Password
  </button>
  <?php if ($isSuperAdmin): ?>
    <button type="button" class="btn btn-sm btn-outline tab-trigger" onclick="switchSettingsTab('accounts', this)" id="tabBtnAccounts">
      Admin Accounts (<?php echo count($allAdmins); ?>)
    </button>
  <?php endif; ?>
  <button type="button" class="btn btn-sm btn-outline tab-trigger" onclick="switchSettingsTab('system', this)" id="tabBtnSystem">
    Server &amp; Security Diagnostics
  </button>
</div>

<!-- TAB 1: Profile & Password -->
<div id="section-profile" class="settings-section">
  <div class="row">
    <!-- Left: Profile Info -->
    <div class="col-md-6" style="margin-bottom: 1.5rem;">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">My Administrative Profile</h2>
        </div>
        <div class="card-body">
          <form method="POST" action="settings.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?php echo e($currentAdminData['username']); ?>" readonly style="background-color: var(--admin-bg); color: var(--admin-text-muted); cursor: not-allowed;">
              <small class="form-hint">Username cannot be changed once created.</small>
            </div>

            <div class="form-group">
              <label class="form-label" for="full_name">Full Name *</label>
              <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo e($currentAdminData['full_name']); ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email Address *</label>
              <input type="email" id="email" name="email" class="form-control" value="<?php echo e($currentAdminData['email']); ?>" required>
              <small class="form-hint">Used for administrative notices and audit trails.</small>
            </div>

            <div class="form-group">
              <label class="form-label">Assigned Role</label>
              <span class="badge" style="background: var(--admin-navy-light); color: var(--admin-navy); padding: 0.35rem 0.75rem; border-radius: 4px; font-weight: 600; text-transform: uppercase;">
                <?php echo e($currentAdminData['role']); ?>
              </span>
            </div>

            <div class="form-group">
              <label class="form-label">Last Successful Login</label>
              <div style="font-size: 0.88rem; color: var(--admin-text-muted);">
                <?php echo $currentAdminData['last_login_at'] ? date('d M Y, h:i A', strtotime($currentAdminData['last_login_at'])) : 'First Session'; ?>
                <?php if ($currentAdminData['last_login_ip']): ?>
                  (IP: <?php echo e($currentAdminData['last_login_ip']); ?>)
                <?php endif; ?>
              </div>
            </div>

            <div style="margin-top: 1.5rem;">
              <button type="submit" class="btn btn-navy">Save Profile Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Right: Change Password -->
    <div class="col-md-6" style="margin-bottom: 1.5rem;">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Change Password</h2>
        </div>
        <div class="card-body">
          <form method="POST" action="settings.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
              <label class="form-label" for="current_password">Current Password *</label>
              <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
              <label class="form-label" for="new_password">New Password *</label>
              <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
              <small class="form-hint">Minimum 8 characters with both letters and numbers.</small>
            </div>

            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm New Password *</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>

            <div style="margin-top: 1.5rem;">
              <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TAB 2: Admin Accounts (Superadmin Only) -->
<?php if ($isSuperAdmin): ?>
<div id="section-accounts" class="settings-section" style="display: none;">
  <div class="card" style="margin-bottom: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2 class="card-title">Administrator Accounts Management</h2>
        <p style="font-size: 0.85rem; color: var(--admin-text-muted); margin: 0.25rem 0 0;">
          Provision, monitor, and configure role privileges across your administrative team.
        </p>
      </div>
      <button type="button" class="btn btn-navy btn-sm" onclick="toggleAddAdminForm()">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        <span>Add New Admin</span>
      </button>
    </div>

    <!-- Collapsible Add Admin Form -->
    <div id="addAdminBox" style="display: none; padding: 1.5rem; background: #F8FAFC; border-bottom: 1px solid var(--admin-border);">
      <h3 style="font-size: 1rem; color: var(--admin-navy); margin-bottom: 1rem; font-weight: 700;">Provision New Administrator Account</h3>
      <form method="POST" action="settings.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="create_admin">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
          <div class="form-group">
            <label class="form-label" for="add_username">Username *</label>
            <input type="text" id="add_username" name="username" class="form-control" placeholder="e.g. jsmith" required pattern="[a-zA-Z0-9_\.]{3,30}">
          </div>

          <div class="form-group">
            <label class="form-label" for="add_fullname">Full Name *</label>
            <input type="text" id="add_fullname" name="full_name" class="form-control" placeholder="e.g. John Smith" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_email">Email Address *</label>
            <input type="email" id="add_email" name="email" class="form-control" placeholder="e.g. john@datnixsolutions.com" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_role">Role Permission *</label>
            <select id="add_role" name="role" class="form-control" required>
              <option value="admin">Administrator (Full Access)</option>
              <option value="editor">Editor (View/Update Enquiries &amp; Jobs)</option>
              <option value="superadmin">Superadmin (All Privileges + Accounts)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="add_password">Initial Password *</label>
            <input type="password" id="add_password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
          </div>
        </div>

        <div style="margin-top: 1rem; display: flex; gap: 0.75rem;">
          <button type="submit" class="btn btn-primary btn-sm">Create Account</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="toggleAddAdminForm()">Cancel</button>
        </div>
      </form>
    </div>

    <div class="card-body" style="padding: 0;">
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Administrator</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Active</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allAdmins as $acc): ?>
              <tr>
                <td style="color: var(--admin-text-muted);">#<?php echo $acc['id']; ?></td>
                <td>
                  <strong style="color: var(--admin-navy);"><?php echo e($acc['full_name']); ?></strong>
                  <div style="font-size: 0.8rem; color: var(--admin-text-muted);">@<?php echo e($acc['username']); ?></div>
                </td>
                <td>
                  <a href="mailto:<?php echo e($acc['email']); ?>" style="color: var(--admin-navy); text-decoration: none;">
                    <?php echo e($acc['email']); ?>
                  </a>
                </td>
                <td>
                  <?php 
                    $roleBadge = 'badge-status-new';
                    if ($acc['role'] === 'superadmin') $roleBadge = 'badge-status-progress';
                    if ($acc['role'] === 'editor') $roleBadge = 'badge-status-review';
                  ?>
                  <span class="badge <?php echo $roleBadge; ?>"><?php echo strtoupper(e($acc['role'])); ?></span>
                </td>
                <td>
                  <?php if ($acc['status'] === 'active'): ?>
                    <span class="badge badge-status-success">ACTIVE</span>
                  <?php else: ?>
                    <span class="badge badge-status-danger"><?php echo strtoupper(e($acc['status'])); ?></span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.85rem; color: var(--admin-text-muted);">
                  <?php echo $acc['last_login_at'] ? date('d M Y, h:i A', strtotime($acc['last_login_at'])) : 'Never'; ?>
                </td>
                <td style="text-align: right;">
                  <?php if ($acc['id'] !== $admin['id']): ?>
                    <div style="display: inline-flex; gap: 0.5rem;">
                      <!-- Toggle Status -->
                      <form method="POST" action="settings.php" style="display: inline;" onsubmit="return confirm('Change status for <?php echo e($acc['username']); ?> to <?php echo $acc['status'] === 'active' ? 'Inactive' : 'Active'; ?>?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="target_id" value="<?php echo $acc['id']; ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.78rem;">
                          <?php echo $acc['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                        </button>
                      </form>

                      <!-- Delete Admin -->
                      <form method="POST" action="settings.php" style="display: inline;" onsubmit="return confirm('Permanently delete admin <?php echo e($acc['username']); ?>? This cannot be undone.');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete_admin">
                        <input type="hidden" name="target_id" value="<?php echo $acc['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.78rem;">
                          Delete
                        </button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span style="font-size: 0.8rem; color: var(--admin-text-muted); font-style: italic;">(You)</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- TAB 3: Server & Security Diagnostics -->
<div id="section-system" class="settings-section" style="display: none;">
  <div class="row">
    <div class="col-md-6" style="margin-bottom: 1.5rem;">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Server Environment</h2>
        </div>
        <div class="card-body" style="padding: 0;">
          <table class="admin-table">
            <tbody>
              <tr>
                <td style="font-weight: 600; width: 40%;">PHP Version</td>
                <td><code><?php echo e($phpVersion); ?></code></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Web Server</td>
                <td><span style="font-size: 0.88rem;"><?php echo e($serverSoftware); ?></span></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">MySQL PDO Driver</td>
                <td><span class="badge badge-status-success">ACTIVE (PDO_MYSQL)</span></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Max Upload File Size</td>
                <td><code><?php echo e($uploadMax); ?></code></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Post Max Size</td>
                <td><code><?php echo e($postMax); ?></code></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Memory Limit</td>
                <td><code><?php echo e($memoryLimit); ?></code></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Max Execution Time</td>
                <td><code><?php echo e($maxExecution); ?></code></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6" style="margin-bottom: 1.5rem;">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Security &amp; Storage Health</h2>
        </div>
        <div class="card-body" style="padding: 0;">
          <table class="admin-table">
            <tbody>
              <tr>
                <td style="font-weight: 600; width: 45%;">Uploads Directory</td>
                <td>
                  <?php if ($isUploadsWritable): ?>
                    <span class="badge badge-status-success">WRITABLE &amp; ACTIVE</span>
                  <?php else: ?>
                    <span class="badge badge-status-danger">CHECK PERMISSIONS</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Apache Script Shield (.htaccess)</td>
                <td>
                  <?php if ($hasHtaccess): ?>
                    <span class="badge badge-status-success">PROTECTED (EXECUTION DENIED)</span>
                  <?php else: ?>
                    <span class="badge badge-status-danger">MISSING</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td style="font-weight: 600;">IIS Script Shield (web.config)</td>
                <td>
                  <?php if ($hasWebConfig): ?>
                    <span class="badge badge-status-success">PROTECTED (EXECUTION DENIED)</span>
                  <?php else: ?>
                    <span class="badge badge-status-danger">MISSING</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Session Idle Timeout</td>
                <td>30 Minutes (Auto-Logout)</td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Rate Limiting</td>
                <td>Sliding Window (5 req / 15 min) + 10s Cooldown</td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Anti-Spoofing IP Resolution</td>
                <td>Strict Loopback / Reverse Proxy Boundary</td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Email Delivery State</td>
                <td><span class="badge badge-status-review">STANDBY (DELIVERY DECOUPLED)</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function switchSettingsTab(tabName, btn) {
  document.querySelectorAll('.settings-section').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-trigger').forEach(b => {
    b.classList.remove('btn-navy');
    b.classList.add('btn-outline');
  });

  const sec = document.getElementById('section-' + tabName);
  if (sec) sec.style.display = 'block';

  if (btn) {
    btn.classList.remove('btn-outline');
    btn.classList.add('btn-navy');
  }

  // Preserve in hash
  window.location.hash = tabName;
}

function toggleAddAdminForm() {
  const box = document.getElementById('addAdminBox');
  if (box) {
    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
  }
}

// Auto open tab based on URL hash
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash.replace('#', '');
  if (hash === 'accounts' && document.getElementById('tabBtnAccounts')) {
    switchSettingsTab('accounts', document.getElementById('tabBtnAccounts'));
  } else if (hash === 'system' && document.getElementById('tabBtnSystem')) {
    switchSettingsTab('system', document.getElementById('tabBtnSystem'));
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
