<?php
/**
 * DATNIX SOLUTIONS - Enquiries Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Enquiry Management';
$breadcrumb = 'Enquiries';
require_once __DIR__ . '/includes/header.php';

// Filter & Search Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$serviceFilter = trim($_GET['service'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = '(reference_no LIKE :srch OR full_name LIKE :srch OR company_name LIKE :srch OR email LIKE :srch OR phone LIKE :srch)';
    $params[':srch'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereClauses[] = 'status = :st';
    $params[':st'] = $statusFilter;
}

if (!empty($serviceFilter)) {
    $whereClauses[] = 'service = :srv';
    $params[':srv'] = $serviceFilter;
}

if (!empty($dateFrom)) {
    $whereClauses[] = 'DATE(created_at) >= :dfrom';
    $params[':dfrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClauses[] = 'DATE(created_at) <= :dto';
    $params[':dto'] = $dateTo;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalCount = 0;
$enquiries = [];

if (!empty($pdo)) {
    try {
        // Count query
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM enquiries $whereSql");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Data query
        $sql = "SELECT * FROM enquiries $whereSql ORDER BY created_at DESC LIMIT :lim OFFSET :off";
        $dataStmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $enquiries = $dataStmt->fetchAll();

    } catch (Throwable $e) {
        error_log('[ENQUIRIES LIST ERROR] ' . $e->getMessage());
    }
}

// Check for zero-loss disk backup enquiries in uploads/enquiries/
$diskEnqDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
if (is_dir($diskEnqDir)) {
    $enqFiles = glob($diskEnqDir . DIRECTORY_SEPARATOR . 'enq_*.json');
    if ($enqFiles) {
        $existingEnqRefs = array_column($enquiries, 'reference_no');
        foreach ($enqFiles as $ef) {
            $raw = @file_get_contents($ef);
            if ($raw) {
                $item = json_decode($raw, true);
                if ($item && isset($item['reference_no']) && !in_array($item['reference_no'], $existingEnqRefs, true)) {
                    $enquiries[] = [
                        'id' => 0,
                        'reference_no' => $item['reference_no'],
                        'full_name' => $item['full_name'] ?? 'Inquirer',
                        'company_name' => $item['company_name'] ?? '',
                        'email' => $item['email'] ?? '',
                        'phone' => $item['phone'] ?? '',
                        'service' => $item['service'] ?? 'General Enquiry',
                        'estimated_volume' => $item['estimated_volume'] ?? '',
                        'project_details' => $item['project_details'] ?? '',
                        'status' => 'New',
                        'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                        'is_disk_backup' => true
                    ];
                    $totalCount++;
                }
            }
        }
    }
}

$totalPages = max(1, (int)ceil($totalCount / $perPage));

// Build query string for export & pagination
$filterQueryParams = $_GET;
unset($filterQueryParams['page']);
$queryString = http_build_query($filterQueryParams);
$exportUrl = 'export-enquiries.php' . (!empty($queryString) ? '?' . $queryString : '');
?>

<div class="page-header-row">
  <div>
    <h1 class="page-title">Customer Enquiries</h1>
    <p class="page-subtitle">Track, filter, and manage inbound business inquiries and pilot project requests</p>
  </div>
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="<?php echo e($exportUrl); ?>" class="btn btn-outline btn-sm">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span>Export Enquiries to CSV</span>
    </a>
  </div>
</div>

<div class="admin-card">
  <!-- Search & Filter Toolbar -->
  <form method="GET" action="enquiries.php" class="toolbar-row">
    <div class="toolbar-left">
      <div class="search-input-wrapper">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="search" class="form-control" placeholder="Search by name, company, email, ref..." value="<?php echo e($search); ?>">
      </div>

      <select name="status" class="form-control" style="width: auto;">
        <option value="">All Statuses</option>
        <?php 
        $statuses = ['New', 'Contacted', 'In Discussion', 'Quotation Sent', 'Converted', 'Closed'];
        foreach ($statuses as $st): 
        ?>
          <option value="<?php echo e($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>>
            <?php echo e($st); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="service" class="form-control" style="width: auto;">
        <option value="">All Services</option>
        <?php
        $services = [
          'Data Entry', 'Data Processing', 'Back Office Support', 'Document Management',
          'Web Research', 'E-commerce Data Management', 'Data Verification',
          'BPO and Process Support', 'General Outsourcing', 'Other'
        ];
        foreach ($services as $srv):
        ?>
          <option value="<?php echo e($srv); ?>" <?php echo $serviceFilter === $srv ? 'selected' : ''; ?>>
            <?php echo e($srv); ?>
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
      <?php if (!empty($search) || !empty($statusFilter) || !empty($serviceFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
        <a href="enquiries.php" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Enquiries Table Body -->
  <div class="admin-card-body p-0">
    <?php if (empty($enquiries)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path></svg>
        <h4>No Matching Enquiries Found</h4>
        <p>Try adjusting your search keywords, service filters, or date range.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Reference No</th>
              <th>Date</th>
              <th>Client Name</th>
              <th>Company</th>
              <th>Service Required</th>
              <th>Contact Details</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enquiries as $enq): ?>
              <tr>
                <td><span class="ref-code"><?php echo e($enq['reference_no']); ?></span></td>
                <td style="white-space: nowrap; font-size: 12px;"><?php echo formatDate($enq['created_at']); ?></td>
                <td><strong><?php echo e($enq['full_name']); ?></strong></td>
                <td><?php echo e($enq['company_name'] ?: '—'); ?></td>
                <td><span style="font-weight: 600; color: var(--admin-navy);"><?php echo e($enq['service']); ?></span></td>
                <td>
                  <div><a href="mailto:<?php echo e($enq['email']); ?>" style="font-size: 12.5px;"><?php echo e($enq['email']); ?></a></div>
                  <?php if (!empty($enq['phone'])): ?>
                    <div style="font-size: 11.5px; color: var(--admin-text-muted);"><?php echo e($enq['phone']); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-status <?php echo getStatusBadgeClass($enq['status']); ?>">
                    <?php echo e($enq['status']); ?>
                  </span>
                </td>
                <td style="white-space: nowrap;">
                  <?php if (!empty($enq['id'])): ?>
                    <a href="enquiry-detail.php?id=<?php echo (int)$enq['id']; ?>" class="btn btn-navy btn-sm">
                      <span>Manage</span>
                      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                  <?php else: ?>
                    <a href="enquiry-detail.php?ref=<?php echo urlencode($enq['reference_no']); ?>" class="btn btn-navy btn-sm">
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
          Showing <strong><?php echo min($totalCount, $offset + 1); ?></strong> to <strong><?php echo min($totalCount, $offset + count($enquiries)); ?></strong> of <strong><?php echo number_format($totalCount); ?></strong> records
        </div>
        <div class="pagination-controls">
          <?php if ($page > 1): ?>
            <a href="enquiries.php?<?php echo e($queryString); ?>&page=<?php echo $page - 1; ?>" class="page-btn">&laquo; Prev</a>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="enquiries.php?<?php echo e($queryString); ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="enquiries.php?<?php echo e($queryString); ?>&page=<?php echo $page + 1; ?>" class="page-btn">Next &raquo;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
