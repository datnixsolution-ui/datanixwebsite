<?php
/**
 * DATNIX SOLUTIONS - Admin Sidebar Component
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

$currentScript = basename($_SERVER['PHP_SELF']);
$admin = getCurrentAdmin();

// Count badges from DB if connection is active
$newEnquiriesCount = 0;
$newApplicationsCount = 0;
$activeJobsCount = 0;

if (!empty($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'New'");
        $newEnquiriesCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'New'");
        $newApplicationsCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'Active' AND is_published = 1");
        $activeJobsCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Suppress on first run before migrations
    }
}
?>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo-card">
      <img src="../assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" height="28">
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Core Operations</div>

    <a href="dashboard.php" class="nav-item <?php echo $currentScript === 'dashboard.php' ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span>Dashboard</span>
      </div>
    </a>

    <a href="enquiries.php" class="nav-item <?php echo in_array($currentScript, ['enquiries.php', 'enquiry-detail.php']) ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        <span>Enquiries</span>
      </div>
      <?php if ($newEnquiriesCount > 0): ?>
        <span class="nav-badge"><?php echo $newEnquiriesCount; ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section-title">Talent &amp; Careers</div>

    <a href="careers.php" class="nav-item <?php echo in_array($currentScript, ['careers.php', 'job-edit.php']) ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        <span>Job Openings</span>
      </div>
      <?php if ($activeJobsCount > 0): ?>
        <span class="nav-badge" style="background: var(--admin-navy-light);"><?php echo $activeJobsCount; ?></span>
      <?php endif; ?>
    </a>

    <a href="applications.php" class="nav-item <?php echo in_array($currentScript, ['applications.php', 'application-detail.php']) ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Applications</span>
      </div>
      <?php if ($newApplicationsCount > 0): ?>
        <span class="nav-badge"><?php echo $newApplicationsCount; ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section-title">System &amp; Audit</div>

    <a href="logs.php" class="nav-item <?php echo $currentScript === 'logs.php' ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        <span>Activity Logs</span>
      </div>
    </a>

    <a href="settings.php" class="nav-item <?php echo $currentScript === 'settings.php' ? 'active' : ''; ?>">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span>Admin Settings</span>
      </div>
    </a>

    <div style="margin-top: auto; padding-top: 1.5rem;">
      <a href="../index.html" target="_blank" class="nav-item">
        <div class="nav-item-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          <span>View Public Site</span>
        </div>
      </a>
      <a href="logout.php" class="nav-item" style="color: #F87171;" data-confirm="Are you sure you want to log out of the admin panel?">
        <div class="nav-item-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          <span>Sign Out</span>
        </div>
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div><strong>DATNIX SOLUTIONS</strong></div>
    <div>DATA. PROCESS. PERFORMANCE.</div>
  </div>
</aside>
