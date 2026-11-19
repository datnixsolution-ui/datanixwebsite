<?php
/**
 * DATNIX SOLUTIONS - Candidate Applications Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Candidate Applications';
$breadcrumb = 'Applications';
require_once __DIR__ . '/includes/header.php';

// Filter & Search Parameters
$search = trim($_GET['search'] ?? '');
$jobFilter = (int)($_GET['job_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(a.reference_no LIKE :srch OR a.full_name LIKE :srch OR a.email LIKE :srch OR a.phone LIKE :srch OR a.current_location LIKE :srch)';
    $params[':srch'] = '%' . $search . '%';
}

if ($jobFilter > 0) {
    $whereClauses[] = 'a.job_id = :jid';
    $params[':jid'] = $jobFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'a.status = :st';
    $params[':st'] = $statusFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'DATE(a.created_at) >= :dfrom';
    $params[':dfrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'DATE(a.created_at) <= :dto';
    $params[':dto'] = $dateTo;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalCount = 0;
$applications = [];
$allJobs = [];

if (!empty($pdo)) {
    try {
        // Fetch jobs for filter dropdown
        $jobStmt = $pdo->query('SELECT id, title FROM jobs ORDER BY title ASC');
        $allJobs = $jobStmt->fetchAll();

        // Count query
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications a $whereSql");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Data query
        $sql = "
            SELECT a.*, j.title as job_title, j.department, f.id as file_id, f.original_filename, f.file_size
            FROM applications a
            LEFT JOIN jobs j ON a.job_id = j.id
            LEFT JOIN application_files f ON a.id = f.application_id
            $whereSql
            ORDER BY a.created_at DESC
            LIMIT :lim OFFSET :off
        ";
        $dataStmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $applications = $dataStmt->fetchAll();

    } catch (Throwable $e) {
        error_log('[APPLICATIONS LIST ERROR] ' . $e->getMessage());
    }
}

// Check for zero-loss disk backup applications in uploads/applications/
$diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications';
if (is_dir($diskDir)) {
    $jsonFiles = glob($diskDir . DIRECTORY_SEPARATOR . 'app_*.json');
    if ($jsonFiles) {
        $existingRefs = array_column($applications, 'reference_no');
        foreach ($jsonFiles as $jf) {
            $raw = @file_get_contents($jf);
            if ($raw) {
                $item = json_decode($raw, true);
                if ($item && isset($item['reference_no']) && !in_array($item['reference_no'], $existingRefs, true)) {
                    $newDbId = 0;
                    // Auto-sync into MySQL if database is connected
                    if (!empty($pdo)) {
                        try {
                            $ins = $pdo->prepare('
                                INSERT INTO applications (
                                    reference_no, job_id, full_name, email, phone,
                                    experience_years, current_location, notice_period,
                                    additional_info, status, ip_address, user_agent, created_at, updated_at
                                ) VALUES (
                                    :ref, :jid, :name, :email, :phone,
                                    :exp, :loc, :np,
                                    :info, :st, :ip, :ua, :created, NOW()
                                )
                            ');
                            $ins->execute([
                                ':ref'     => $item['reference_no'],
                                ':jid'     => $item['job_id'] ?? 0,
                                ':name'    => $item['full_name'] ?? 'Candidate',
                                ':email'   => $item['email'] ?? '',
                                ':phone'   => $item['phone'] ?? '',
                                ':exp'     => $item['experience_years'] ?? '',
                                ':loc'     => $item['current_location'] ?? ($item['candidate_address'] ?? 'WFH (All India)'),
                                ':np'      => $item['notice_period'] ?? '',
                                ':info'    => $item['additional_info'] ?? null,
                                ':st'      => $item['status'] ?? 'New',
                                ':ip'      => $item['ip_address'] ?? '127.0.0.1',
                                ':ua'      => $item['user_agent'] ?? 'Web Form',
                                ':created' => $item['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                            $newDbId = (int)$pdo->lastInsertId();

                            if (!empty($item['resume_stored'])) {
                                $fIns = $pdo->prepare('
                                    INSERT INTO application_files (
                                        application_id, original_filename, stored_filename, file_path, file_size, mime_type, created_at
                                    ) VALUES (
                                        :aid, :orig, :stored, :path, 0, "application/octet-stream", NOW()
                                    )
                                ');
                                $fIns->execute([
                                    ':aid'    => $newDbId,
                                    ':orig'   => $item['resume_original'] ?? 'Resume',
                                    ':stored' => $item['resume_stored'],
                                    ':path'   => $item['resume_path'] ?? ''
                                ]);
                            }
                        } catch (Throwable $syncEx) {
                            error_log('[APPLICATIONS LIST AUTO-SYNC ERROR] ' . $syncEx->getMessage());
                        }
                    }

                    $applications[] = [
                        'id' => $newDbId,
                        'reference_no' => $item['reference_no'],
                        'job_id' => $item['job_id'] ?? 0,
                        'full_name' => $item['full_name'] ?? 'Candidate',
                        'email' => $item['email'] ?? '',
                        'phone' => $item['phone'] ?? '',
                        'experience_years' => $item['experience_years'] ?? '',
                        'current_location' => $item['current_location'] ?? ($item['candidate_address'] ?? 'WFH (All India)'),
                        'notice_period' => $item['notice_period'] ?? '',
                        'additional_info' => $item['additional_info'] ?? '',
                        'status' => $item['status'] ?? 'New',
                        'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                        'job_title' => 'Data Operations (WFH)',
                        'department' => 'Operations',
                        'file_id' => !empty($item['resume_stored']) ? 1 : null,
                        'original_filename' => $item['resume_original'] ?? (!empty($item['has_resume']) ? 'Resume' : null),
                        'file_size' => 0,
                        'is_disk_backup' => true,
                        'resume_stored' => $item['resume_stored'] ?? null
                    ];
                    $totalCount++;
                }
            }
        }
    }
}

$totalPages = max(1, (int)ceil($totalCount / $perPage));

$filterQueryParams = $_GET;
unset($filterQueryParams['page']);
$queryString = http_build_query($filterQueryParams);
?>

<div class="page-header-row">
  <div>
    <h1 class="page-title">Candidate Job Applications</h1>
    <p class="page-subtitle">Review candidate resumes, screen experience, update application statuses, and add recruiter notes</p>
  </div>
  <div style="display: flex; gap: 0.5rem; align-items: center;">
    <a href="export-applications.php?<?php echo e($queryString); ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span>Export Applications CSV</span>
    </a>
  </div>
</div>

<div class="admin-card">
  <!-- Search & Filter Toolbar -->
  <form method="GET" action="applications.php" class="toolbar-row">
    <div class="toolbar-left">
      <div class="search-input-wrapper">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, city..." value="<?php echo e($search); ?>">
      </div>

      <select name="job_id" class="form-control" style="width: auto;">
        <option value="">All Job Positions</option>
        <?php foreach ($allJobs as $jb): ?>
          <option value="<?php echo (int)$jb['id']; ?>" <?php echo $jobFilter === (int)$jb['id'] ? 'selected' : ''; ?>>
            <?php echo e($jb['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-control" style="width: auto;">
        <option value="">All Statuses</option>
        <?php
        $statuses = ['New', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected'];
        foreach ($statuses as $st):
        ?>
          <option value="<?php echo e($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
            <?php echo e($st); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="toolbar-right">
      <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 12px; color: var(--admin-text-muted);">
        <span>From:</span>
        <input type="date" name="date_from" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 12px;" value="<?php echo e($dateFrom); ?>">
        <span>To:</span>
        <input type="date" name="date_to" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 12px;" value="<?php echo e($dateTo); ?>">
      </div>

      <button type="submit" class="btn btn-navy btn-sm">Filter</button>
      <?php if (!empty($search) || !empty($jobFilter) || !empty($statusFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
        <a href="applications.php" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Applications Table Body -->
  <div class="admin-card-body p-0">
    <?php if (empty($applications)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
        <h4>No Candidate Applications Found</h4>
        <p>Applications submitted on the careers portal will appear here.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Date</th>
              <th>Candidate</th>
              <th>Position Applied</th>
              <th>Experience</th>
              <th>City / Notice</th>
              <th>Resume</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app): ?>
              <tr>
                <td><span class="ref-code"><?php echo e($app['reference_no']); ?></span></td>
                <td style="white-space: nowrap; font-size: 12px;"><?php echo formatDate($app['created_at']); ?></td>
                <td>
                  <strong><?php echo e($app['full_name']); ?></strong>
                  <div style="font-size: 11.5px; color: var(--admin-text-muted);">
                    <a href="mailto:<?php echo e($app['email']); ?>"><?php echo e($app['email']); ?></a> &bull; <?php echo e($app['phone']); ?>
                  </div>
                </td>
                <td>
                  <strong style="color: var(--admin-navy);"><?php echo e($app['job_title'] ?? 'General Pool'); ?></strong>
                </td>
                <td><?php echo e($app['experience_years']); ?></td>
                <td>
                  <div><?php echo e($app['current_location']); ?></div>
                  <div style="font-size: 11.5px; color: var(--admin-text-muted);">NP: <?php echo e($app['notice_period']); ?></div>
                </td>
                <td>
                  <?php if (!empty($app['resume_stored'])): ?>
                    <a href="download-resume.php?file=<?php echo urlencode($app['resume_stored']); ?>" class="btn btn-outline btn-sm" title="Download CV">
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                      <span>CV (Download)</span>
                    </a>
                  <?php elseif (!empty($app['file_id'])): ?>
                    <a href="download-resume.php?id=<?php echo (int)$app['file_id']; ?>" class="btn btn-outline btn-sm" title="Download CV">
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                      <span>CV (<?php echo formatFileSize((int)$app['file_size']); ?>)</span>
                    </a>
                  <?php else: ?>
                    <span style="font-size: 11.5px; color: var(--admin-text-muted);">No file</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-status <?php echo getStatusBadgeClass($app['status']); ?>">
                    <?php echo e($app['status']); ?>
                  </span>
                </td>
                <td style="white-space: nowrap;">
                  <?php if (!empty($app['id'])): ?>
                    <a href="application-detail.php?id=<?php echo (int)$app['id']; ?>" class="btn btn-navy btn-sm">
                      <span>Manage</span>
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                  <?php else: ?>
                    <a href="application-detail.php?ref=<?php echo urlencode($app['reference_no']); ?>" class="btn btn-navy btn-sm">
                      <span>Manage</span>
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-row">
        <div>
          Showing <strong><?php echo min($totalCount, $offset + 1); ?></strong> to <strong><?php echo min($totalCount, $offset + count($applications)); ?></strong> of <strong><?php echo number_format($totalCount); ?></strong> records
        </div>
        <div class="pagination-controls">
          <?php if ($page > 1): ?>
            <a href="applications.php?<?php echo e($queryString); ?>&page=<?php echo $page - 1; ?>" class="page-btn">&laquo; Prev</a>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="applications.php?<?php echo e($queryString); ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="applications.php?<?php echo e($queryString); ?>&page=<?php echo $page + 1; ?>" class="page-btn">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
