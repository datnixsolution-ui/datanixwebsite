<?php
/**
 * DATNIX SOLUTIONS - Job Openings Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Careers & Job Openings';
$breadcrumb = 'Job Openings';

// Handle Action Toggles (Publish/Unpublish, Delete, Close, Reopen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        setFlashMessage('danger', 'CSRF validation failed.');
        header('Location: careers.php');
        exit;
    }

    $action = $_POST['form_action'] ?? '';
    $jobId = (int)($_POST['job_id'] ?? 0);

    if ($jobId > 0 && !empty($pdo)) {
        if ($action === 'toggle_publish') {
            try {
                $stmt = $pdo->prepare('UPDATE jobs SET is_published = NOT is_published, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':id' => $jobId]);
                logActivity($pdo, $admin['id'], 'job_publish_toggled', 'job', $jobId, 'Toggled job publish status');
                setFlashMessage('success', 'Job visibility updated successfully.');
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Error updating job: ' . $e->getMessage());
            }
        } elseif ($action === 'close_job') {
            try {
                $stmt = $pdo->prepare('UPDATE jobs SET status = "Closed", updated_at = NOW() WHERE id = :id');
                $stmt->execute([':id' => $jobId]);
                logActivity($pdo, $admin['id'], 'job_closed', 'job', $jobId, 'Marked job opening as Closed');
                setFlashMessage('success', 'Job opening marked as Closed.');
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Error closing job: ' . $e->getMessage());
            }
        } elseif ($action === 'reopen_job') {
            try {
                $stmt = $pdo->prepare('UPDATE jobs SET status = "Active", updated_at = NOW() WHERE id = :id');
                $stmt->execute([':id' => $jobId]);
                logActivity($pdo, $admin['id'], 'job_reopened', 'job', $jobId, 'Reopened job opening as Active');
                setFlashMessage('success', 'Job opening reopened and set to Active.');
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Error reopening job: ' . $e->getMessage());
            }
        } elseif ($action === 'delete_job') {
            try {
                // Check if applications exist for this job
                $chk = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE job_id = :id');
                $chk->execute([':id' => $jobId]);
                $appCount = (int)$chk->fetchColumn();

                if ($appCount > 0) {
                    setFlashMessage('warning', "Cannot delete job with $appCount existing candidate applications. You can set its status to 'Closed' or unpublish it instead.");
                } else {
                    $del = $pdo->prepare('DELETE FROM jobs WHERE id = :id');
                    $del->execute([':id' => $jobId]);
                    logActivity($pdo, $admin['id'], 'job_deleted', 'job', $jobId, 'Deleted job opening');
                    setFlashMessage('success', 'Job opening deleted successfully.');
                }
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Error deleting job: ' . $e->getMessage());
            }
        }
    }

    $returnTo = trim((string)($_POST['return_to'] ?? ''));
    if (empty($returnTo) || !preg_match('/^[a-zA-Z0-9_\-\.\?\=\&\/]+$/', $returnTo)) {
        $returnTo = 'careers.php';
    }
    header("Location: $returnTo");
    exit;
}

// Fetch all jobs with application count
$jobs = [];
$deptFilter = trim($_GET['dept'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($deptFilter)) {
    $where[] = 'j.department = :dept';
    $params[':dept'] = $deptFilter;
}
if (!empty($statusFilter)) {
    $where[] = 'j.status = :st';
    $params[':st'] = $statusFilter;
}
$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

if (!empty($pdo)) {
    try {
        $sql = "
            SELECT j.*, COUNT(a.id) as applications_count 
            FROM jobs j 
            LEFT JOIN applications a ON j.id = a.job_id 
            $whereSql
            GROUP BY j.id 
            ORDER BY j.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[CAREERS LIST ERROR] ' . $e->getMessage());
    }
}

// Fallback if database is cold / unseeded
if (empty($jobs)) {
    $jobs = [
        [
            'id' => 1,
            'title' => 'Data Entry Operator (Fresher & Experienced)',
            'slug' => 'data-entry-operator-fresher-experienced',
            'department' => 'Data Entry Operations',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '0 - 2 Years (Freshers Welcome)',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'title' => 'Senior Data Entry Specialist (High-Volume & Speed)',
            'slug' => 'senior-data-entry-specialist',
            'department' => 'Data Entry Operations',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '2 - 4 Years',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 3,
            'title' => 'Online Form Filling & Data Entry Assistant',
            'slug' => 'online-form-filling-data-entry-assistant',
            'department' => 'Data Operations',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '0 - 1 Year (Freshers Welcome)',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 4,
            'title' => 'Data Quality & Verification Executive (WFH)',
            'slug' => 'data-quality-verification-executive',
            'department' => 'Quality Assurance',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '1 - 3 Years',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 5,
            'title' => 'Excel & Spreadsheet Data Entry Associate',
            'slug' => 'excel-spreadsheet-data-entry-associate',
            'department' => 'Spreadsheet Management',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '0 - 2 Years',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 6,
            'title' => 'Bilingual / Multilingual Data Entry Operator (WFH)',
            'slug' => 'multilingual-data-entry-operator',
            'department' => 'Multilingual Operations',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '0 - 2 Years',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 7,
            'title' => 'Document Scanning & Indexing Data Entry Operator',
            'slug' => 'document-scanning-indexing-data-entry-operator',
            'department' => 'Document Processing',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '0 - 2 Years (Freshers Welcome)',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 8,
            'title' => 'Back-Office Data Entry & Processing Executive',
            'slug' => 'back-office-data-entry-executive',
            'department' => 'Back-Office Support',
            'location' => 'Work From Home (WFH) / Remote (All India)',
            'employment_type' => 'Full-Time / WFH',
            'experience_req' => '1 - 3 Years',
            'status' => 'Active',
            'is_published' => 1,
            'applications_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header-row">
  <div>
    <h1 class="page-title">Career Opportunities &amp; Job Openings</h1>
    <p class="page-subtitle">Publish, manage, and monitor company job positions on the public careers portal</p>
  </div>
  <div>
    <a href="job-edit.php" class="btn btn-primary btn-sm">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Create New Job Opening</span>
    </a>
  </div>
</div>

<div class="admin-card">
  <!-- Toolbar Filter -->
  <form method="GET" action="careers.php" class="toolbar-row">
    <div class="toolbar-left">
      <select name="status" class="form-control" style="width: auto;">
        <option value="">All Statuses</option>
        <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
        <option value="Closed" <?php echo $statusFilter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
        <option value="Draft" <?php echo $statusFilter === 'Draft' ? 'selected' : ''; ?>>Draft</option>
      </select>

      <button type="submit" class="btn btn-navy btn-sm">Filter</button>
      <?php if (!empty($deptFilter) || !empty($statusFilter)): ?>
        <a href="careers.php" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </div>
    <div class="toolbar-right">
      <span style="font-size: 13px; color: var(--admin-text-muted);">
        Total Positions: <strong><?php echo count($jobs); ?></strong>
      </span>
    </div>
  </form>

  <div class="admin-card-body p-0">
    <?php if (empty($jobs)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <h4>No Job Openings Found</h4>
        <p>Click "Create New Job Opening" above to publish your first career listing.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Department</th>
              <th>Location &amp; Type</th>
              <th>Experience</th>
              <th>Applications</th>
              <th>Visibility</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $job): ?>
              <tr>
                <td>
                  <strong><?php echo e($job['title']); ?></strong>
                  <div style="font-size: 11.5px; color: var(--admin-text-muted);">Slug: <code><?php echo e($job['slug']); ?></code></div>
                </td>
                <td><?php echo e($job['department']); ?></td>
                <td>
                  <div><?php echo e($job['location']); ?></div>
                  <div style="font-size: 11.5px; color: var(--admin-text-muted);"><?php echo e($job['employment_type']); ?></div>
                </td>
                <td><?php echo e($job['experience_req']); ?></td>
                <td>
                  <a href="applications.php?job_id=<?php echo (int)$job['id']; ?>" class="btn btn-outline btn-sm" style="font-weight: 700;">
                    <?php echo (int)$job['applications_count']; ?> Applications
                  </a>
                </td>
                <td>
                  <form method="POST" action="careers.php" style="display: inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="form_action" value="toggle_publish">
                    <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                    <input type="hidden" name="return_to" value="<?php echo e($_SERVER['REQUEST_URI'] ?? 'careers.php'); ?>">
                    <?php if ($job['is_published']): ?>
                      <button type="submit" class="btn btn-sm btn-outline" style="color: #047857; border-color: #A7F3D0; background: #ECFDF5;" title="Click to Unpublish">
                        &bull; Published
                      </button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-outline" style="color: #64748B; background: #F1F5F9;" title="Click to Publish">
                        Hidden
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
                <td>
                  <span class="badge-status <?php echo getStatusBadgeClass($job['status']); ?>">
                    <?php echo e($job['status']); ?>
                  </span>
                </td>
                <td style="white-space: nowrap;">
                  <div style="display: flex; gap: 0.35rem;">
                    <a href="job-edit.php?id=<?php echo (int)$job['id']; ?>" class="btn btn-outline btn-sm" title="Edit Job Opening">
                      Edit
                    </a>
                    
                    <?php if ($job['status'] === 'Active'): ?>
                      <form method="POST" action="careers.php" style="display: inline;" data-confirm="Are you sure you want to mark this job opening as Closed?">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="form_action" value="close_job">
                        <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                        <input type="hidden" name="return_to" value="<?php echo e($_SERVER['REQUEST_URI'] ?? 'careers.php'); ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: #B45309;" title="Close Job Opening">
                          Close
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" action="careers.php" style="display: inline;" data-confirm="Are you sure you want to reopen and set this job opening to Active?">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="form_action" value="reopen_job">
                        <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                        <input type="hidden" name="return_to" value="<?php echo e($_SERVER['REQUEST_URI'] ?? 'careers.php'); ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: #047857;" title="Reopen as Active">
                          Reopen
                        </button>
                      </form>
                    <?php endif; ?>

                    <form method="POST" action="careers.php" style="display: inline;" data-confirm="Are you sure you want to delete this job opening? This cannot be undone.">
                      <?php echo csrfField(); ?>
                      <input type="hidden" name="form_action" value="delete_job">
                      <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                      <input type="hidden" name="return_to" value="<?php echo e($_SERVER['REQUEST_URI'] ?? 'careers.php'); ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Job Opening">
                        &times;
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
