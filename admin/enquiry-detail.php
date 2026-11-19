<?php
/**
 * DATNIX SOLUTIONS - Enquiry Detail & Notes Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Enquiry Detail';
$breadcrumb = 'Enquiry Detail';
require_once __DIR__ . '/includes/header.php';

$enquiryId = (int)($_GET['id'] ?? 0);
if ($enquiryId <= 0 || empty($pdo)) {
    setFlashMessage('danger', 'Invalid enquiry record ID specified.');
    header('Location: enquiries.php');
    exit;
}

// Handle Form Submissions (Status Update or Internal Note)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        setFlashMessage('danger', 'CSRF validation failed. Please try again.');
        header("Location: enquiry-detail.php?id=$enquiryId");
        exit;
    }

    $action = $_POST['form_action'] ?? '';

    // Action 1: Update Status
    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['New', 'Contacted', 'In Discussion', 'Quotation Sent', 'Converted', 'Closed'];
        if (in_array($newStatus, $allowed, true)) {
            try {
                $stmt = $pdo->prepare('UPDATE enquiries SET status = :st, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':st' => $newStatus, ':id' => $enquiryId]);

                logActivity($pdo, $admin['id'], 'enquiry_status_updated', 'enquiry', $enquiryId, "Status updated to '$newStatus'");
                setFlashMessage('success', "Enquiry status updated to '$newStatus' successfully.");
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Failed to update status: ' . $e->getMessage());
            }
        }
        header("Location: enquiry-detail.php?id=$enquiryId");
        exit;
    }

    // Action 2: Add Internal Note
    if ($action === 'add_note') {
        $noteText = trim($_POST['note'] ?? '');
        if (!empty($noteText)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO enquiry_notes (enquiry_id, admin_id, note, created_at) VALUES (:eid, :aid, :note, NOW())');
                $stmt->execute([
                    ':eid'  => $enquiryId,
                    ':aid'  => $admin['id'],
                    ':note' => $noteText
                ]);

                logActivity($pdo, $admin['id'], 'enquiry_note_added', 'enquiry', $enquiryId, 'Added an internal note');
                setFlashMessage('success', 'Internal note recorded successfully.');
            } catch (Throwable $e) {
                setFlashMessage('danger', 'Failed to save note: ' . $e->getMessage());
            }
        }
        header("Location: enquiry-detail.php?id=$enquiryId");
        exit;
    }
}

// Fetch Enquiry Record
$enquiry = null;
$notes = [];
$auditLogs = [];

try {
    $stmt = $pdo->prepare('SELECT * FROM enquiries WHERE id = :id');
    $stmt->execute([':id' => $enquiryId]);
    $enquiry = $stmt->fetch();

    if (!$enquiry) {
        setFlashMessage('danger', 'Enquiry record not found.');
        header('Location: enquiries.php');
        exit;
    }

    // Fetch Internal Notes
    $noteStmt = $pdo->prepare('
        SELECT n.*, a.full_name as author_name, a.username 
        FROM enquiry_notes n
        JOIN admins a ON n.admin_id = a.id
        WHERE n.enquiry_id = :eid
        ORDER BY n.created_at DESC
    ');
    $noteStmt->execute([':eid' => $enquiryId]);
    $notes = $noteStmt->fetchAll();

    // Fetch Audit Trail for this enquiry
    $logStmt = $pdo->prepare('
        SELECT l.*, a.full_name as admin_name 
        FROM activity_logs l
        LEFT JOIN admins a ON l.admin_id = a.id
        WHERE l.entity_type = "enquiry" AND l.entity_id = :eid
        ORDER BY l.created_at DESC
    ');
    $logStmt->execute([':eid' => $enquiryId]);
    $auditLogs = $logStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[ENQUIRY DETAIL ERROR] ' . $e->getMessage());
}

$cleanPhone = preg_replace('/[^0-9]/', '', $enquiry['phone'] ?? '');
$waLink = !empty($cleanPhone) ? 'https://wa.me/' . $cleanPhone : '';
?>

<div class="page-header-row">
  <div>
    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
      <a href="enquiries.php" style="color: var(--admin-text-muted);">&larr; Back to Enquiries</a>
      <span style="color: #CBD5E1;">&bull;</span>
      <span class="ref-code" style="font-size: 13.5px;"><?php echo e($enquiry['reference_no']); ?></span>
      <span class="badge-status <?php echo getStatusBadgeClass($enquiry['status']); ?>"><?php echo e($enquiry['status']); ?></span>
    </div>
    <h1 class="page-title"><?php echo e($enquiry['full_name']); ?> <?php echo !empty($enquiry['company_name']) ? '&mdash; ' . e($enquiry['company_name']) : ''; ?></h1>
    <p class="page-subtitle">Submitted on <?php echo formatDate($enquiry['created_at']); ?> &bull; Logged from IP: <code><?php echo e($enquiry['ip_address']); ?></code></p>
  </div>

  <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
    <a href="mailto:<?php echo e($enquiry['email']); ?>?subject=Re:%20DATNIX%20SOLUTIONS%20Enquiry%20<?php echo urlencode($enquiry['reference_no']); ?>" class="btn btn-navy btn-sm">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
      <span>Email Client</span>
    </a>
    <?php if (!empty($waLink)): ?>
      <a href="<?php echo e($waLink); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
        <span>WhatsApp Client</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.75rem; align-items: start;">
  <!-- Left Column: Details & Notes -->
  <div>
    <!-- Scope & Details Card -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Enquiry Scope &amp; Specifications</h3>
        <span style="font-size: 12px; color: var(--admin-text-muted);">Service: <strong><?php echo e($enquiry['service']); ?></strong></span>
      </div>
      <div class="admin-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: var(--admin-bg); padding: 1rem; border-radius: var(--radius-sm);">
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Service Required</div>
            <div style="font-weight: 600; color: var(--admin-navy); margin-top: 2px;"><?php echo e($enquiry['service']); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Estimated Volume</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo e($enquiry['estimated_volume'] ?: 'Flexible / Not specified'); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Client IP</div>
            <div style="font-family: monospace; font-size: 12px; margin-top: 2px;"><?php echo e($enquiry['ip_address']); ?></div>
          </div>
        </div>

        <div style="margin-bottom: 0.5rem; font-weight: 700; color: var(--admin-navy-dark); font-size: 13px; text-transform: uppercase;">
          Project Requirements &amp; Scope:
        </div>
        <div style="background: #FFFFFF; border: 1px solid var(--admin-border); border-radius: var(--radius-sm); padding: 1.25rem; font-size: 14px; line-height: 1.7; color: #1E293B; white-space: pre-wrap;">
          <?php echo e($enquiry['project_details']); ?>
        </div>
      </div>
    </div>

    <!-- Internal Notes (MODULE 3: Strict Privacy - Never exposed to visitors) -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Internal Team Notes &amp; Activity Log</h3>
        <span style="font-size: 11.5px; color: var(--admin-orange); font-weight: 700;">Strictly Confidential / Internal Only</span>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="enquiry-detail.php?id=<?php echo $enquiryId; ?>" style="margin-bottom: 1.75rem;">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_action" value="add_note">
          <div class="form-group">
            <label for="note" class="form-label">Add Progress Note / Call Summary</label>
            <textarea id="note" name="note" class="form-control" placeholder="Log details of phone call, quotation sent, custom rate agreed, or next follow-up date..." required></textarea>
          </div>
          <button type="submit" class="btn btn-navy btn-sm">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Save Internal Note</span>
          </button>
        </form>

        <div class="notes-timeline">
          <?php if (empty($notes)): ?>
            <div style="text-align: center; padding: 1.5rem; color: var(--admin-text-muted); font-size: 13px;">
              No internal notes recorded yet for this enquiry.
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

  <!-- Right Column: Status & Contact Profile -->
  <div>
    <!-- Status Control Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Enquiry Status</h3>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="enquiry-detail.php?id=<?php echo $enquiryId; ?>">
          <?php echo csrfField(); ?>
          <input type="hidden" name="form_action" value="update_status">
          <div class="form-group">
            <label for="status" class="form-label">Operational Stage</label>
            <select id="status" name="status" class="form-control" style="font-weight: 600;">
              <?php
              $statuses = ['New', 'Contacted', 'In Discussion', 'Quotation Sent', 'Converted', 'Closed'];
              foreach ($statuses as $st):
              ?>
                <option value="<?php echo e($st); ?>" <?php echo $enquiry['status'] === $st ? 'selected' : ''; ?>>
                  <?php echo e($st); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
            <span>Update Status</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Contact Information Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Contact Profile</h3>
      </div>
      <div class="admin-card-body" style="font-size: 13.5px;">
        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Full Name</div>
          <div style="font-weight: 700; color: var(--admin-navy-dark); font-size: 15px; margin-top: 2px;"><?php echo e($enquiry['full_name']); ?></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Company / Firm</div>
          <div><?php echo e($enquiry['company_name'] ?: 'Not specified'); ?></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Business Email</div>
          <div><a href="mailto:<?php echo e($enquiry['email']); ?>" style="word-break: break-all;"><?php echo e($enquiry['email']); ?></a></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Mobile / Phone</div>
          <div>
            <?php if (!empty($enquiry['phone'])): ?>
              <a href="tel:<?php echo e($enquiry['phone']); ?>"><?php echo e($enquiry['phone']); ?></a>
            <?php else: ?>
              <span style="color: var(--admin-text-muted);">Not provided</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="border-top: 1px solid var(--admin-border); padding-top: 0.75rem; font-size: 12px; color: var(--admin-text-muted);">
          <div>Registered: <?php echo formatDate($enquiry['created_at']); ?></div>
          <div>Last Updated: <?php echo formatDate($enquiry['updated_at']); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
