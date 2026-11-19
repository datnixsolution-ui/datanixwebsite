<?php
/**
 * DATNIX SOLUTIONS - Candidate Application Detail & Review
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Application Review';
$breadcrumb = 'Application Detail';
require_once __DIR__ . '/includes/header.php';

$appId = (int)($_GET['id'] ?? 0);
if ($appId <= 0 || empty($pdo)) {
    setFlashMessage('danger', 'Invalid application record ID.');
    header('Location: applications.php');
    exit;
}

// Handle Status & Notes Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        setFlashMessage('danger', 'CSRF validation failed.');
        header("Location: application-detail.php?id=$appId");
        exit;
    }

    $action = $_POST['form_action'] ?? '';

    // Action 1: Update Status
    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['New', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected'];
        if (in_array($newStatus, $allowed, true)) {
            try {
                $stmt = $pdo->prepare('UPDATE applications SET status = :st, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':st' => $newStatus, ':id' => $appId]);

                logActivity($pdo, $admin['id'], 'application_status_updated', 'application', $appId, "Status updated to '$newStatus'");
                setFlashMessage('success', "Candidate application status updated to '$newStatus'.");
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Failed to update status: ' . $e->getMessage());
            }
        }
        header("Location: application-detail.php?id=$appId");
        exit;
    }

    // Action 2: Add Recruiter Note
    if ($action === 'add_note') {
        $noteText = trim($_POST['note'] ?? '');
        if (!empty($noteText)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO application_notes (application_id, admin_id, note, created_at) VALUES (:aid, :admin, :note, NOW())');
                $stmt->execute([
                    ':aid'   => $appId,
                    ':admin' => $admin['id'],
                    ':note'  => $noteText
                ]);

                logActivity($pdo, $admin['id'], 'application_note_added', 'application', $appId, 'Added review note');
                setFlashMessage('success', 'Recruitment note added successfully.');
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Failed to save note: ' . $e->getMessage());
            }
        }
        header("Location: application-detail.php?id=$appId");
        exit;
    }
}

// Fetch Application Record with Job Details and Resume File
$app = null;
$resumeFile = null;
$notes = [];
$auditLogs = [];

try {
    $stmt = $pdo->prepare('
        SELECT a.*, j.title as job_title, j.department, j.location as job_location
        FROM applications a
        LEFT JOIN jobs j ON a.job_id = j.id
        WHERE a.id = :id
    ');
    $stmt->execute([':id' => $appId]);
    $app = $stmt->fetch();

    if (!$app) {
        setFlashMessage('danger', 'Application record not found.');
        header('Location: applications.php');
        exit;
    }

    // Fetch Resume File
    $fStmt = $pdo->prepare('SELECT * FROM application_files WHERE application_id = :id LIMIT 1');
    $fStmt->execute([':id' => $appId]);
    $resumeFile = $fStmt->fetch();

    // Fetch Internal Notes
    $nStmt = $pdo->prepare('
        SELECT n.*, a.full_name as author_name, a.username 
        FROM application_notes n
        JOIN admins a ON n.admin_id = a.id
        WHERE n.application_id = :aid
        ORDER BY n.created_at DESC
    ');
    $nStmt->execute([':aid' => $appId]);
    $notes = $nStmt->fetchAll();

    // Fetch Activity Logs
    $lStmt = $pdo->prepare('
        SELECT l.*, a.full_name as admin_name 
        FROM activity_logs l
        LEFT JOIN admins a ON l.admin_id = a.id
        WHERE l.entity_type = "application" AND l.entity_id = :aid
        ORDER BY l.created_at DESC
    ');
    $lStmt->execute([':aid' => $appId]);
    $auditLogs = $lStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[APPLICATION DETAIL ERROR] ' . $e->getMessage());
}
?>

<div class="page-header-row">
  <div>
    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
      <a href="applications.php" style="color: var(--admin-text-muted);">&larr; Back to Applications</a>
      <span style="color: #CBD5E1;">&bull;</span>
      <span class="ref-code" style="font-size: 13.5px;"><?php echo e($app['reference_no']); ?></span>
      <span class="badge-status <?php echo getStatusBadgeClass($app['status']); ?>"><?php echo e($app['status']); ?></span>
    </div>
    <h1 class="page-title"><?php echo e($app['full_name']); ?></h1>
    <p class="page-subtitle">Applied for <strong><?php echo e($app['job_title'] ?? 'General Talent Pool'); ?></strong> on <?php echo formatDate($app['created_at']); ?></p>
  </div>

  <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
    <?php if ($resumeFile): ?>
      <a href="download-resume.php?id=<?php echo (int)$resumeFile['id']; ?>" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Download Resume (<?php echo e($resumeFile['original_filename']); ?>)</span>
      </a>
    <?php endif; ?>
    <a href="mailto:<?php echo e($app['email']); ?>?subject=Re:%20DATNIX%20SOLUTIONS%20Application%20<?php echo urlencode($app['reference_no']); ?>" class="btn btn-navy btn-sm">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
      <span>Contact Candidate</span>
    </a>
  </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.75rem; align-items: start;">
  <!-- Left Column: Candidate Profile & Notes -->
  <div>
    <!-- Profile Card -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Candidate Profile &amp; Qualifications</h3>
        <span style="font-size: 12px; color: var(--admin-text-muted);">Ref: <strong><?php echo e($app['reference_no']); ?></strong></span>
      </div>
      <div class="admin-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: var(--admin-bg); padding: 1rem; border-radius: var(--radius-sm);">
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Total Experience</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo e($app['experience_years']); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Current Location</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo e($app['current_location']); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Notice Period</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo e($app['notice_period']); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Applied Role</div>
            <div style="font-weight: 600; color: var(--admin-navy); margin-top: 2px;"><?php echo e($app['job_title'] ?? 'General Pool'); ?></div>
          </div>
        </div>

        <?php if (!empty($app['additional_info'])): ?>
          <div style="margin-bottom: 0.5rem; font-weight: 700; color: var(--admin-navy-dark); font-size: 13px; text-transform: uppercase;">
            Candidate Cover Information:
          </div>
          <div style="background: #FFFFFF; border: 1px solid var(--admin-border); border-radius: var(--radius-sm); padding: 1.25rem; font-size: 14px; line-height: 1.6; color: #1E293B; white-space: pre-wrap; margin-bottom: 1.5rem;">
            <?php echo e($app['additional_info']); ?>
          </div>
        <?php endif; ?>

        <!-- Attached Resume File Box -->
        <div style="margin-bottom: 0.5rem; font-weight: 700; color: var(--admin-navy-dark); font-size: 13px; text-transform: uppercase;">
          Attached Resume Document:
        </div>
        <?php if ($resumeFile): ?>
          <div style="border: 1px solid var(--admin-border); border-radius: var(--radius-sm); padding: 1.15rem; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <div style="width: 40px; height: 40px; border-radius: var(--radius-sm); background: rgba(9, 45, 120, 0.1); color: var(--admin-navy); display: flex; align-items: center; justify-content: center;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
              </div>
              <div>
                <strong style="color: var(--admin-navy-dark);"><?php echo e($resumeFile['original_filename']); ?></strong>
                <div style="font-size: 12px; color: var(--admin-text-muted);">
                  Size: <?php echo formatFileSize((int)$resumeFile['file_size']); ?> &bull; Type: <?php echo e($resumeFile['mime_type']); ?>
                </div>
              </div>
            </div>
            <a href="download-resume.php?id=<?php echo (int)$resumeFile['id']; ?>" class="btn btn-navy btn-sm">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              <span>Download CV</span>
            </a>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">No resume document file associated with this candidate record.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recruiter Notes Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Recruiter Screening Notes</h3>
        <span style="font-size: 11.5px; color: var(--admin-orange); font-weight: 700;">Private / Internal Only</span>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="application-detail.php?id=<?php echo $appId; ?>" style="margin-bottom: 1.75rem;">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_action" value="add_note">
          <div class="form-group">
            <label for="note" class="form-label">Add Candidate Review Note</label>
            <textarea id="note" name="note" class="form-control" placeholder="Log notes on screening interview, typing test score, technical evaluation, salary expectations..." required></textarea>
          </div>
          <button type="submit" class="btn btn-navy btn-sm">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Save Screening Note</span>
          </button>
        </form>

        <div class="notes-timeline">
          <?php if (empty($notes)): ?>
            <div style="text-align: center; padding: 1.5rem; color: var(--admin-text-muted); font-size: 13px;">
              No recruiter notes recorded for this candidate yet.
            </div>
          <?php else: ?>
            <?php foreach ($notes as $n): ?>
              <div class="note-item">
                <div class="note-header">
                  <span class="note-author"><?php echo e($n['author_name']); ?></span>
                  <span><?php echo formatDate($n['created_at']); ?></span>
                </div>
                <div class="note-text"><?php echo e($n['note']); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Status & Contact Info -->
  <div>
    <!-- Application Status Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Recruitment Stage</h3>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="application-detail.php?id=<?php echo $appId; ?>">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_action" value="update_status">
          <div class="form-group">
            <label for="status" class="form-label">Application Status</label>
            <select id="status" name="status" class="form-control" style="font-weight: 600;">
              <?php
              $statuses = ['New', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected'];
              foreach ($statuses as $st):
              ?>
                <option value="<?php echo e($st); ?>" <?php echo $app['status'] === $st ? 'selected' : ''; ?>>
                  <?php echo e($st); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
            <span>Update Stage</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Candidate Contact Details Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Contact Information</h3>
      </div>
      <div class="admin-card-body" style="font-size: 13.5px;">
        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Full Name</div>
          <div style="font-weight: 700; color: var(--admin-navy-dark); font-size: 15px; margin-top: 2px;"><?php echo e($app['full_name']); ?></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Email Address</div>
          <div><a href="mailto:<?php echo e($app['email']); ?>" style="word-break: break-all;"><?php echo e($app['email']); ?></a></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Mobile Phone</div>
          <div><a href="tel:<?php echo e($app['phone']); ?>"><?php echo e($app['phone']); ?></a></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Location</div>
          <div><?php echo e($app['current_location']); ?></div>
        </div>

        <div style="border-top: 1px solid var(--admin-border); padding-top: 0.75rem; font-size: 12px; color: var(--admin-text-muted);">
          <div>Submitted: <?php echo formatDate($app['created_at']); ?></div>
          <div>Applicant IP: <code><?php echo e($app['ip_address']); ?></code></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
