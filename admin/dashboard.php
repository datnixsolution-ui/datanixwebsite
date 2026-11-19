<?php
/**
 * DATNIX SOLUTIONS - Admin Operational Dashboard
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Executive Dashboard';
$breadcrumb = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Initialize stats with defaults
$stats = [
    'total_enquiries'      => 0,
    'new_enquiries'        => 0,
    'contacted_enquiries'  => 0,
    'converted_enquiries'  => 0,
    'active_jobs'          => 0,
    'total_applications'   => 0,
    'new_applications'     => 0,
    'under_review_apps'    => 0,
];

$recentEnquiries = [];
$recentApplications = [];
$recentLogs = [];

if (!empty($pdo)) {
    try {
        // Enquiries Stats
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'New' THEN 1 ELSE 0 END) as new_count,
            SUM(CASE WHEN status = 'Contacted' THEN 1 ELSE 0 END) as contacted_count,
            SUM(CASE WHEN status = 'Converted' THEN 1 ELSE 0 END) as converted_count
            FROM enquiries");
        $enqStats = $stmt->fetch();
        if ($enqStats) {
            $stats['total_enquiries']     = (int)($enqStats['total'] ?? 0);
            $stats['new_enquiries']       = (int)($enqStats['new_count'] ?? 0);
            $stats['contacted_enquiries'] = (int)($enqStats['contacted_count'] ?? 0);
            $stats['converted_enquiries'] = (int)($enqStats['converted_count'] ?? 0);
        }

        // Jobs & Applications Stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'Active' AND is_published = 1");
        $stats['active_jobs'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'New' THEN 1 ELSE 0 END) as new_count,
            SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as review_count
            FROM applications");
        $appStats = $stmt->fetch();
        if ($appStats) {
            $stats['total_applications'] = (int)($appStats['total'] ?? 0);
            $stats['new_applications']   = (int)($appStats['new_count'] ?? 0);
            $stats['under_review_apps']  = (int)($appStats['review_count'] ?? 0);
        }

        // Recent Enquiries (Latest 6)
        $stmt = $pdo->query("SELECT id, reference_no, full_name, company_name, email, service, status, created_at 
                             FROM enquiries 
                             ORDER BY created_at DESC 
                             LIMIT 6");
        $recentEnquiries = $stmt->fetchAll();

        // Recent Applications (Latest 6)
        $stmt = $pdo->query("SELECT a.id, a.reference_no, a.full_name, a.email, a.status, a.created_at, j.title as job_title 
                             FROM applications a
                             LEFT JOIN jobs j ON a.job_id = j.id
                             ORDER BY a.created_at DESC 
                             LIMIT 6");
        $recentApplications = $stmt->fetchAll();

        // Recent Activity Logs (Latest 6)
        $stmt = $pdo->query("SELECT l.*, ad.username, ad.full_name as admin_name 
                             FROM activity_logs l
                             LEFT JOIN admins ad ON l.admin_id = ad.id
                             ORDER BY l.created_at DESC 
                             LIMIT 6");
        $recentLogs = $stmt->fetchAll();

    } catch (Throwable $e) {
        error_log('[DASHBOARD STATS ERROR] ' . $e->getMessage());
    }
}
?>

<div class="page-header-row">
  <div>
    <h1 class="page-title">Executive Overview</h1>
    <p class="page-subtitle">Welcome back, <strong><?php echo e($admin['full_name']); ?></strong> &bull; Operational throughput and lead tracking summary</p>
  </div>
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="enquiries.php" class="btn btn-navy btn-sm">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path></svg>
      <span>Review Enquiries</span>
    </a>
    <a href="job-edit.php" class="btn btn-primary btn-sm">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Post New Job Opening</span>
    </a>
  </div>
</div>

<!-- Primary Stats Grid (MODULE 2) -->
<div class="stats-grid">
  <!-- 1. Total Enquiries -->
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Total Enquiries</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
      </div>
    </div>
    <div class="stat-value"><?php echo number_format($stats['total_enquiries']); ?></div>
    <div class="stat-footer">
      <span>Database recorded leads</span>
    </div>
  </div>

  <!-- 2. New Enquiries (Needs Action) -->
  <div class="stat-card stat-accent">
    <div class="stat-card-header">
      <span class="stat-label">New Enquiries</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
      </div>
    </div>
    <div class="stat-value" style="color: var(--admin-orange);"><?php echo number_format($stats['new_enquiries']); ?></div>
    <div class="stat-footer">
      <span>Awaiting initial response</span>
    </div>
  </div>

  <!-- 3. Contacted Enquiries -->
  <div class="stat-card stat-info">
    <div class="stat-card-header">
      <span class="stat-label">Contacted Enquiries</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
      </div>
    </div>
    <div class="stat-value"><?php echo number_format($stats['contacted_enquiries']); ?></div>
    <div class="stat-footer">
      <span>In active communication</span>
    </div>
  </div>

  <!-- 4. Converted Enquiries -->
  <div class="stat-card stat-success">
    <div class="stat-card-header">
      <span class="stat-label">Converted Enquiries</span>
      <div class="stat-icon" style="color: #10B981; background: #ECFDF5;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
      </div>
    </div>
    <div class="stat-value" style="color: #059669;"><?php echo number_format($stats['converted_enquiries']); ?></div>
    <div class="stat-footer">
      <span>Converted to client engagements</span>
    </div>
  </div>

  <!-- 5. Active Job Openings -->
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Active Job Openings</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
      </div>
    </div>
    <div class="stat-value"><?php echo number_format($stats['active_jobs']); ?></div>
    <div class="stat-footer">
      <span>Published on public careers page</span>
    </div>
  </div>

  <!-- 6. Total Applications -->
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Total Applications</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
      </div>
    </div>
    <div class="stat-value"><?php echo number_format($stats['total_applications']); ?></div>
    <div class="stat-footer">
      <span>Candidate resumes received</span>
    </div>
  </div>

  <!-- 7. New Applications -->
  <div class="stat-card stat-accent">
    <div class="stat-card-header">
      <span class="stat-label">New Applications</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
      </div>
    </div>
    <div class="stat-value" style="color: var(--admin-orange);"><?php echo number_format($stats['new_applications']); ?></div>
    <div class="stat-footer">
      <span>Unreviewed submissions</span>
    </div>
  </div>

  <!-- 8. Applications Under Review -->
  <div class="stat-card stat-info">
    <div class="stat-card-header">
      <span class="stat-label">Under Review</span>
      <div class="stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
    </div>
    <div class="stat-value"><?php echo number_format($stats['under_review_apps']); ?></div>
    <div class="stat-footer">
      <span>Candidate profiles in screening</span>
    </div>
  </div>
</div>

<!-- Layout: 2 Column (Recent Enquiries & Recent Applications) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">
  
  <!-- Recent Enquiries Panel -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="card-title">Recent Customer Enquiries</h3>
      <a href="enquiries.php" class="btn btn-outline btn-sm">View All &rarr;</a>
    </div>
    <div class="admin-card-body p-0">
      <?php if (empty($recentEnquiries)): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path></svg>
          <h4>No Enquiries Logged Yet</h4>
          <p>Website form submissions will automatically appear here.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Client / Firm</th>
                <th>Service</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEnquiries as $enq): ?>
                <tr>
                  <td><span class="ref-code"><?php echo e($enq['reference_no']); ?></span></td>
                  <td>
                    <strong><?php echo e($enq['full_name']); ?></strong>
                    <?php if (!empty($enq['company_name']) && $enq['company_name'] !== 'Not specified'): ?>
                      <div style="font-size: 11.5px; color: var(--admin-text-muted);"><?php echo e($enq['company_name']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo e($enq['service']); ?></td>
                  <td><span class="badge-status <?php echo getStatusBadgeClass($enq['status']); ?>"><?php echo e($enq['status']); ?></span></td>
                  <td>
                    <a href="enquiry-detail.php?id=<?php echo (int)$enq['id']; ?>" class="btn btn-outline btn-sm">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Candidate Applications Panel -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="card-title">Recent Career Applications</h3>
      <a href="applications.php" class="btn btn-outline btn-sm">View All &rarr;</a>
    </div>
    <div class="admin-card-body p-0">
      <?php if (empty($recentApplications)): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
          <h4>No Applications Yet</h4>
          <p>Candidate resumes submitted via the careers page will appear here.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Candidate</th>
                <th>Position</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentApplications as $app): ?>
                <tr>
                  <td><span class="ref-code"><?php echo e($app['reference_no']); ?></span></td>
                  <td>
                    <strong><?php echo e($app['full_name']); ?></strong>
                    <div style="font-size: 11.5px; color: var(--admin-text-muted);"><?php echo e($app['email']); ?></div>
                  </td>
                  <td><?php echo e($app['job_title'] ?? 'General Application'); ?></td>
                  <td><span class="badge-status <?php echo getStatusBadgeClass($app['status']); ?>"><?php echo e($app['status']); ?></span></td>
                  <td>
                    <a href="application-detail.php?id=<?php echo (int)$app['id']; ?>" class="btn btn-outline btn-sm">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Activity Audit Logs -->
<div class="admin-card">
  <div class="admin-card-header">
    <h3 class="card-title">Recent System &amp; Administrative Activity</h3>
    <a href="logs.php" class="btn btn-outline btn-sm">View Complete Audit Log &rarr;</a>
  </div>
  <div class="admin-card-body p-0">
    <?php if (empty($recentLogs)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        <h4>No Activity Recorded</h4>
        <p>Administrative actions are securely recorded here.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Administrator</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Details</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLogs as $log): ?>
              <tr>
                <td style="white-space: nowrap;"><?php echo formatDate($log['created_at']); ?></td>
                <td>
                  <strong><?php echo e($log['admin_name'] ?? ($log['username'] ?? 'System')); ?></strong>
                </td>
                <td>
                  <span class="badge-status badge-status-default"><?php echo e($log['action']); ?></span>
                </td>
                <td><?php echo ucfirst(e($log['entity_type'])); ?> #<?php echo (int)($log['entity_id'] ?? 0); ?></td>
                <td><?php echo e($log['details'] ?? '—'); ?></td>
                <td><code><?php echo e($log['ip_address']); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
