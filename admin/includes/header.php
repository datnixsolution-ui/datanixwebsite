<?php
/**
 * DATNIX SOLUTIONS - Admin Layout Header
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$admin = getCurrentAdmin();
$flash = getFlashMessage();
$pageTitle = $pageTitle ?? 'Admin Dashboard';
$breadcrumb = $breadcrumb ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo e($pageTitle); ?> | DATNIX SOLUTIONS Admin</title>
  
  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="../favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16x16.png">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="assets/css/admin.css">
  <?php echo csrfMeta(); ?>
</head>
<body>

<div class="admin-wrapper">
  <?php require __DIR__ . '/sidebar.php'; ?>

  <div class="admin-main">
    <!-- Topbar -->
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle-btn" id="adminSidebarToggle" aria-label="Toggle sidebar menu">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div class="page-breadcrumb">
          <a href="dashboard.php">Admin</a> <span>/</span> <strong><?php echo e($breadcrumb); ?></strong>
        </div>
      </div>

      <div class="topbar-right">
        <a href="../index.html" target="_blank" class="btn btn-outline btn-sm" title="View Public Website">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          <span>Website</span>
        </a>

        <div class="admin-user-pill">
          <div class="user-avatar-circle">
            <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
          </div>
          <div class="user-info-text">
            <div class="user-name"><?php echo e($admin['full_name']); ?></div>
            <div class="user-role"><?php echo ucfirst(e($admin['role'])); ?></div>
          </div>
        </div>

        <a href="logout.php" class="btn btn-outline btn-sm" data-confirm="Are you sure you want to log out?" title="Sign Out">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          <span>Logout</span>
        </a>
      </div>
    </header>

    <!-- Main Container Content -->
    <main class="admin-content">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?> alert-autoclose">
          <strong>Notice:</strong> <?php echo e($flash['message']); ?>
        </div>
      <?php endif; ?>
