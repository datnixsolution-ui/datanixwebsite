<?php
/**
 * DATNIX SOLUTIONS - Activity Audit Logs
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$admin = getCurrentAdmin();
$isSuperAdmin = ($admin['role'] === 'superadmin');

// Handle Prune Logs action (Superadmin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'prune_old_logs') {
    if (!$isSuperAdmin) {
        setFlashMessage('danger', 'Unauthorized: Only Superadmins can prune activity audit logs.');
        header('Location: logs.php');
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        setFlashMessage('danger', 'Invalid security token. Please try again.');
        header('Location: logs.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
        $stmt->execute();
        $prunedCount = $stmt->rowCount();

        logActivity($pdo, $admin['id'], 'logs_pruned', 'system', null, "Pruned {$prunedCount} audit records older than 90 days");
        setFlashMessage('success', "Audit trail maintenance complete: {$prunedCount} old log records removed.");
    } catch (Throwable $e) {
        setFlashMessage('danger', 'Error pruning logs: ' . $e->getMessage());
    }

    header('Location: logs.php');
    exit;
}

// Filters
$search = sanitizeInput($_GET['search'] ?? '');
$filterAction = sanitizeInput($_GET['action_filter'] ?? '');
$filterEntity = sanitizeInput($_GET['entity_type'] ?? '');
$filterAdmin = sanitizeInput($_GET['admin_id'] ?? '');
$dateFrom = sanitizeInput($_GET['date_from'] ?? '');
$dateTo = sanitizeInput($_GET['date_to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(al.action LIKE :search OR al.details LIKE :search OR al.ip_address LIKE :search OR a.username LIKE :search OR a.full_name LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filterAction)) {
    $whereClauses[] = 'al.action = :action';
    $params[':action'] = $filterAction;
}

if (!empty($filterEntity)) {
    $whereClauses[] = 'al.entity_type = :entity';
    $params[':entity'] = $filterEntity;
}

if ($filterAdmin !== '') {
    if ($filterAdmin === 'system') {
        $whereClauses[] = 'al.admin_id IS NULL';
    } else {
        $whereClauses[] = 'al.admin_id = :admin_id';
        $params[':admin_id'] = (int)$filterAdmin;
    }
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'al.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereClauses[] = 'al.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = implode(' AND ', $whereClauses);

$totalLogs = 0;
$totalPages = 1;
$logs = [];
$distinctActions = [];
$allAdminsList = [];

if (!empty($pdo)) {
    try {
        $countSql = "
            SELECT COUNT(*)
            FROM activity_logs al
            LEFT JOIN admins a ON al.admin_id = a.id
            WHERE $whereSql
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalLogs = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalLogs / $perPage));

        // Fetch paginated logs
        $sql = "
            SELECT 
                al.*,
                a.username AS admin_username,
                a.full_name AS admin_full_name,
                a.role AS admin_role
            FROM activity_logs al
            LEFT JOIN admins a ON al.admin_id = a.id
            WHERE $whereSql
            ORDER BY al.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        // Fetch distinct actions for filter dropdown
        $actionsStmt = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC');
        if ($actionsStmt) {
            $distinctActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Fetch admins list for filter dropdown
        $adminsListStmt = $pdo->query('SELECT id, full_name, username FROM admins ORDER BY full_name ASC');
        if ($adminsListStmt) {
            $allAdminsList = $adminsListStmt->fetchAll();
        }

    } catch (Throwable $e) {
        error_log('[LOGS QUERY ERROR] ' . $e->getMessage());
    }
}

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>System Activity &amp; Audit Logs</h1>
    <p>Comprehensive, immutable audit trail tracking administrative actions, enquiry lifecycle updates, and system operations.</p>
  </div>
  <?php if ($isSuperAdmin): ?>
    <div class="page-header-actions">
      <form method="POST" action="logs.php" onsubmit="return confirm('Prune all audit logs older than 90 days?');">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="prune_old_logs">
        <button type="submit" class="btn btn-outline btn-sm" title="Retain last 90 days of logs">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          <span>Prune &gt;90 Days</span>
        </button>
      </form>
    </div>
  <?php endif; ?>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 1.5rem;">
  <div class="card-body" style="padding: 1.25rem;">
    <form method="GET" action="logs.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Search Details / IP</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search keyword..." value="<?php echo e($search); ?>">
      </div>

      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Action Type</label>
        <select name="action_filter" class="form-control form-control-sm">
          <option value="">All Actions</option>
          <?php foreach ($distinctActions as $act): ?>
            <option value="<?php echo e($act); ?>" <?php echo $filterAction === $act ? 'selected' : ''; ?>>
              <?php echo e($act); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Entity Scope</label>
        <select name="entity_type" class="form-control form-control-sm">
          <option value="">All Entities</option>
          <option value="enquiry" <?php echo $filterEntity === 'enquiry' ? 'selected' : ''; ?>>Enquiry</option>
          <option value="application" <?php echo $filterEntity === 'application' ? 'selected' : ''; ?>>Application</option>
          <option value="job" <?php echo $filterEntity === 'job' ? 'selected' : ''; ?>>Job Opening</option>
          <option value="admin" <?php echo $filterEntity === 'admin' ? 'selected' : ''; ?>>Admin / User</option>
          <option value="system" <?php echo $filterEntity === 'system' ? 'selected' : ''; ?>>System</option>
        </select>
      </div>

      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Operator</label>
        <select name="admin_id" class="form-control form-control-sm">
          <option value="">All Users</option>
          <option value="system" <?php echo $filterAdmin === 'system' ? 'selected' : ''; ?>>System / Public Client</option>
          <?php foreach ($allAdminsList as $adm): ?>
            <option value="<?php echo $adm['id']; ?>" <?php echo $filterAdmin === (string)$adm['id'] ? 'selected' : ''; ?>>
              <?php echo e($adm['full_name']); ?> (@<?php echo e($adm['username']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">From Date</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo e($dateFrom); ?>">
      </div>

      <div>
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">To Date</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo e($dateTo); ?>">
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button type="submit" class="btn btn-navy btn-sm" style="flex: 1;">Filter</button>
        <?php if (!empty($search) || !empty($filterAction) || !empty($filterEntity) || $filterAdmin !== '' || !empty($dateFrom) || !empty($dateTo)): ?>
          <a href="logs.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Logs Table -->
<div class="card">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h2 class="card-title">Audit Records (<?php echo number_format($totalLogs); ?> total)</h2>
    <span style="font-size: 0.82rem; color: var(--admin-text-muted);">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
  </div>
  <div class="card-body" style="padding: 0;">
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 170px;">Timestamp</th>
            <th style="width: 180px;">Initiator</th>
            <th style="width: 150px;">Action</th>
            <th style="width: 140px;">Entity Ref</th>
            <th>Details</th>
            <th style="width: 130px; text-align: right;">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: var(--admin-text-muted);">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <p>No audit activity found matching your criteria.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td style="font-size: 0.82rem; color: var(--admin-text-muted); white-space: nowrap;">
                  <?php echo date('d M Y, h:i:s A', strtotime($log['created_at'])); ?>
                </td>
                <td>
                  <?php if ($log['admin_id']): ?>
                    <strong style="color: var(--admin-navy); font-size: 0.88rem;"><?php echo e($log['admin_full_name'] ?? 'Admin'); ?></strong>
                    <div style="font-size: 0.78rem; color: var(--admin-text-muted);">@<?php echo e($log['admin_username'] ?? 'user'); ?></div>
                  <?php else: ?>
                    <span style="font-size: 0.82rem; color: #475569; font-weight: 500;">
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>
                      Website Client / System
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                    $badgeCls = 'badge-status-review';
                    if (strpos($log['action'], 'login') !== false || strpos($log['action'], 'submitted') !== false || strpos($log['action'], 'created') !== false) {
                        $badgeCls = 'badge-status-success';
                    } elseif (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'pruned') !== false) {
                        $badgeCls = 'badge-status-danger';
                    } elseif (strpos($log['action'], 'updated') !== false || strpos($log['action'], 'note') !== false) {
                        $badgeCls = 'badge-status-progress';
                    }
                  ?>
                  <span class="badge <?php echo $badgeCls; ?>" style="font-size: 0.75rem;">
                    <?php echo e($log['action']); ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($log['entity_type'])): ?>
                    <span style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; color: var(--admin-text-muted);">
                      <?php echo e($log['entity_type']); ?>
                    </span>
                    <?php if (!empty($log['entity_id'])): ?>
                      <?php if ($log['entity_type'] === 'enquiry'): ?>
                        <a href="enquiry-detail.php?id=<?php echo (int)$log['entity_id']; ?>" style="font-size: 0.82rem; color: var(--admin-navy); font-weight: 600; margin-left: 4px;">
                          #<?php echo (int)$log['entity_id']; ?>
                        </a>
                      <?php elseif ($log['entity_type'] === 'application'): ?>
                        <a href="application-detail.php?id=<?php echo (int)$log['entity_id']; ?>" style="font-size: 0.82rem; color: var(--admin-navy); font-weight: 600; margin-left: 4px;">
                          #<?php echo (int)$log['entity_id']; ?>
                        </a>
                      <?php elseif ($log['entity_type'] === 'job'): ?>
                        <a href="job-edit.php?id=<?php echo (int)$log['entity_id']; ?>" style="font-size: 0.82rem; color: var(--admin-navy); font-weight: 600; margin-left: 4px;">
                          #<?php echo (int)$log['entity_id']; ?>
                        </a>
                      <?php else: ?>
                        <span style="font-size: 0.82rem; color: var(--admin-text-muted);">#<?php echo (int)$log['entity_id']; ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color: var(--admin-text-muted);">&mdash;</span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.88rem; line-height: 1.4;">
                  <?php echo e($log['details'] ?? 'No details provided'); ?>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 0.8rem; color: var(--admin-text-muted);">
                  <?php echo e($log['ip_address']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="card-footer" style="display: flex; justify-content: center; gap: 0.35rem; padding: 1rem;">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php
          $queryArgs = $_GET;
          $queryArgs['page'] = $i;
          $pageUrl = 'logs.php?' . http_build_query($queryArgs);
        ?>
        <a href="<?php echo e($pageUrl); ?>" class="btn btn-sm <?php echo $i === $page ? 'btn-navy' : 'btn-outline'; ?>" style="min-width: 36px; text-align: center;">
          <?php echo $i; ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
