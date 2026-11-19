<?php
/**
 * DATNIX SOLUTIONS - Enquiry Detail & Notes Management
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$enquiryId = (int)($_POST['enquiry_id'] ?? ($_GET['id'] ?? 0));
$refParam = trim((string)($_POST['ref'] ?? ($_GET['ref'] ?? '')));

if ($enquiryId <= 0 && empty($refParam)) {
    setFlashMessage('danger', 'Invalid enquiry record specified.');
    header('Location: enquiries.php');
    exit;
}

// 1. Fetch Enquiry Record
$enquiry = null;
$notes = [];
$auditLogs = [];

// Try DB fetch by ID
if ($enquiryId > 0 && !empty($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM enquiries WHERE id = :id');
        $stmt->execute([':id' => $enquiryId]);
        $enquiry = $stmt->fetch();
        if ($enquiry && empty($refParam)) {
            $refParam = $enquiry['reference_no'];
        }
    } catch (Throwable $e) {
        error_log('[ENQUIRY DETAIL DB ERROR] ' . $e->getMessage());
    }
}

// Try DB fetch by reference_no
if (!$enquiry && !empty($refParam) && !empty($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM enquiries WHERE reference_no = :ref');
        $stmt->execute([':ref' => $refParam]);
        $enquiry = $stmt->fetch();
        if ($enquiry) {
            $enquiryId = (int)$enquiry['id'];
        }
    } catch (Throwable $e) {
        error_log('[ENQUIRY DETAIL REF DB ERROR] ' . $e->getMessage());
    }
}

// Check disk backup if not loaded from DB
$diskMatchedFile = null;
if (!$enquiry && !empty($refParam)) {
    $cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '', $refParam);
    $diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
    if (is_dir($diskDir)) {
        $matches = glob($diskDir . DIRECTORY_SEPARATOR . 'enq_*' . $cleanRef . '*.json');
        if ($matches) {
            $diskMatchedFile = $matches[0];
            $raw = @file_get_contents($diskMatchedFile);
            $item = json_decode($raw, true);
            if ($item) {
                // If database is available, auto-sync this disk enquiry into MySQL
                if (!empty($pdo)) {
                    try {
                        $ins = $pdo->prepare('
                            INSERT INTO enquiries (
                                reference_no, full_name, company_name, email, phone,
                                service, estimated_volume, project_details, status,
                                ip_address, user_agent, created_at, updated_at
                            ) VALUES (
                                :ref, :name, :company, :email, :phone,
                                :service, :volume, :details, :st,
                                :ip, :ua, :created, NOW()
                            )
                        ');
                        $ins->execute([
                            ':ref'     => $item['reference_no'],
                            ':name'    => $item['full_name'] ?? 'Inquirer',
                            ':company' => $item['company_name'] ?? null,
                            ':email'   => $item['email'] ?? '',
                            ':phone'   => $item['phone'] ?? null,
                            ':service' => $item['service'] ?? 'General Outsourcing',
                            ':volume'  => $item['estimated_volume'] ?? null,
                            ':details' => $item['project_details'] ?? '',
                            ':st'      => $item['status'] ?? 'New',
                            ':ip'      => $item['ip_address'] ?? '127.0.0.1',
                            ':ua'      => $item['user_agent'] ?? 'Web Form',
                            ':created' => $item['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                        $enquiryId = (int)$pdo->lastInsertId();
                    } catch (Throwable $syncErr) {
                        error_log('[AUTO SYNC ENQUIRY ERROR] ' . $syncErr->getMessage());
                    }
                }

                $enquiry = [
                    'id'               => $enquiryId,
                    'reference_no'     => $item['reference_no'],
                    'full_name'        => $item['full_name'] ?? 'Inquirer',
                    'company_name'     => $item['company_name'] ?? '',
                    'email'            => $item['email'] ?? '',
                    'phone'            => $item['phone'] ?? '',
                    'service'          => $item['service'] ?? 'General Outsourcing',
                    'estimated_volume' => $item['estimated_volume'] ?? '',
                    'project_details'  => $item['project_details'] ?? '',
                    'status'           => $item['status'] ?? 'New',
                    'created_at'       => $item['created_at'] ?? date('Y-m-d H:i:s'),
                    'ip_address'       => $item['ip_address'] ?? 'Unknown'
                ];

                if (!empty($item['notes']) && is_array($item['notes'])) {
                    foreach ($item['notes'] as $n) {
                        $notes[] = [
                            'author_name' => $n['author_name'] ?? 'Admin',
                            'note'        => $n['note'] ?? '',
                            'created_at'  => $n['created_at'] ?? date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
        }
    }
}

// If loaded from database, also fetch notes and activity logs
if ($enquiry && $enquiry['id'] > 0 && !empty($pdo)) {
    try {
        $noteStmt = $pdo->prepare('
            SELECT n.*, a.full_name as author_name, a.username 
            FROM enquiry_notes n
            JOIN admins a ON n.admin_id = a.id
            WHERE n.enquiry_id = :eid
            ORDER BY n.created_at DESC
        ');
        $noteStmt->execute([':eid' => $enquiry['id']]);
        $dbNotes = $noteStmt->fetchAll();
        if ($dbNotes) {
            $notes = array_merge($notes, $dbNotes);
        }

        $logStmt = $pdo->prepare('
            SELECT l.*, a.full_name as admin_name 
            FROM activity_logs l
            LEFT JOIN admins a ON l.admin_id = a.id
            WHERE l.entity_type = "enquiry" AND l.entity_id = :eid
            ORDER BY l.created_at DESC
        ');
        $logStmt->execute([':eid' => $enquiry['id']]);
        $auditLogs = $logStmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[ENQUIRY DETAIL SUB-FETCH ERROR] ' . $e->getMessage());
    }
}

if (!$enquiry) {
    setFlashMessage('danger', 'Enquiry record not found.');
    header('Location: enquiries.php');
    exit;
}

// 2. Handle Form Submissions (Status Update or Internal Note)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        setFlashMessage('danger', 'CSRF validation failed. Please try again.');
        $redir = $enquiryId > 0 ? "enquiry-detail.php?id=$enquiryId" : "enquiry-detail.php?ref=" . urlencode($enquiry['reference_no']);
        header("Location: $redir");
        exit;
    }

    $action = $_POST['form_action'] ?? '';

    // Action 1: Update Status
    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['New', 'Contacted', 'In Discussion', 'Quotation Sent', 'Converted', 'Closed'];
        if (in_array($newStatus, $allowed, true)) {
            // Update in MySQL
            if (!empty($pdo)) {
                try {
                    if ($enquiryId > 0) {
                        $stmt = $pdo->prepare('UPDATE enquiries SET status = :st, updated_at = NOW() WHERE id = :id');
                        $stmt->execute([':st' => $newStatus, ':id' => $enquiryId]);
                    } elseif (!empty($refParam)) {
                        $stmt = $pdo->prepare('UPDATE enquiries SET status = :st, updated_at = NOW() WHERE reference_no = :ref');
                        $stmt->execute([':st' => $newStatus, ':ref' => $refParam]);
                    }
                    if (function_exists('logActivity')) {
                        logActivity($pdo, $admin['id'] ?? 1, 'enquiry_status_updated', 'enquiry', $enquiryId, "Status updated to '$newStatus'");
                    }
                } catch (Throwable $dbErr) {
                    error_log('[ENQUIRY DB STATUS UPDATE ERROR] ' . $dbErr->getMessage());
                }
            }

            // Dual-Save: Update in disk JSON file
            $targetRef = !empty($refParam) ? $refParam : $enquiry['reference_no'];
            $cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '', $targetRef);
            if (!empty($cleanRef)) {
                $diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
                $matches = glob($diskDir . DIRECTORY_SEPARATOR . 'enq_*' . $cleanRef . '*.json');
                if ($matches) {
                    foreach ($matches as $matchFile) {
                        $raw = @file_get_contents($matchFile);
                        if ($raw) {
                            $data = json_decode($raw, true);
                            if ($data) {
                                $data['status'] = $newStatus;
                                $data['updated_at'] = date('Y-m-d H:i:s');
                                @file_put_contents($matchFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            }
                        }
                    }
                }
            }

            setFlashMessage('success', "Enquiry status updated to '$newStatus' successfully.");
        } else {
            setFlashMessage('danger', 'Invalid status selection.');
        }

        $redir = ($newStatus === 'Closed' || !empty($_POST['return_to_list']))
            ? 'enquiries.php'
            : ("enquiry-detail.php?id=" . (int)$enquiryId . "&ref=" . urlencode($enquiry['reference_no']));
        header("Location: $redir");
        exit;
    }

    // Action 2: Add Internal Note
    if ($action === 'add_note') {
        $noteText = trim($_POST['note'] ?? '');
        if (!empty($noteText)) {
            // Save in MySQL
            if ($enquiryId > 0 && !empty($pdo)) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO enquiry_notes (enquiry_id, admin_id, note, created_at) VALUES (:eid, :aid, :note, NOW())');
                    $stmt->execute([
                        ':eid'  => $enquiryId,
                        ':aid'  => $admin['id'] ?? 1,
                        ':note' => $noteText
                    ]);
                    if (function_exists('logActivity')) {
                        logActivity($pdo, $admin['id'] ?? 1, 'enquiry_note_added', 'enquiry', $enquiryId, 'Added an internal note');
                    }
                } catch (Throwable $e) {
                    error_log('[ENQUIRY DB NOTE ERROR] ' . $e->getMessage());
                }
            }

            // Dual-Save: Save in disk JSON file
            $targetRef = !empty($refParam) ? $refParam : $enquiry['reference_no'];
            $cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '', $targetRef);
            if (!empty($cleanRef)) {
                $diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
                $matches = glob($diskDir . DIRECTORY_SEPARATOR . 'enq_*' . $cleanRef . '*.json');
                if ($matches) {
                    foreach ($matches as $matchFile) {
                        $raw = @file_get_contents($matchFile);
                        if ($raw) {
                            $data = json_decode($raw, true);
                            if ($data) {
                                if (!isset($data['notes']) || !is_array($data['notes'])) {
                                    $data['notes'] = [];
                                }
                                $data['notes'][] = [
                                    'author_name' => $admin['full_name'] ?? 'Admin',
                                    'note'        => $noteText,
                                    'created_at'  => date('Y-m-d H:i:s')
                                ];
                                @file_put_contents($matchFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            }
                        }
                    }
                }
            }

            setFlashMessage('success', 'Internal note recorded successfully.');
        }

        $redir = ($newStatus === 'Closed' || !empty($_POST['return_to_list']))
            ? 'enquiries.php'
            : ("enquiry-detail.php?id=" . (int)$enquiryId . "&ref=" . urlencode($enquiry['reference_no']));
        header("Location: $redir");
        exit;
    }
}

$pageTitle = 'Enquiry Detail';
$breadcrumb = 'Enquiry Detail';
require_once __DIR__ . '/includes/header.php';

$pageTargetUrl = 'enquiry-detail.php?id=' . (int)$enquiryId . '&ref=' . urlencode($enquiry['reference_no']);
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
    <h1 class="page-title"><?php echo e($enquiry['full_name']); ?></h1>
    <p class="page-subtitle">Submitted on <?php echo formatDate($enquiry['created_at']); ?> &bull; Service: <strong><?php echo e($enquiry['service']); ?></strong></p>
  </div>

  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <?php if (!empty($waLink)): ?>
      <a href="<?php echo e($waLink); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        <span>WhatsApp Lead</span>
      </a>
    <?php endif; ?>
    <a href="mailto:<?php echo e($enquiry['email']); ?>" class="btn btn-navy btn-sm">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
      <span>Email Client</span>
    </a>
  </div>
</div>

<div class="content-grid">
  <!-- Left Column: Project Scope & Activity -->
  <div>
    <!-- Scope Details Card -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Project Scope &amp; Specifications</h3>
        <span style="font-size: 12px; color: var(--admin-text-muted);">Ref: <strong><?php echo e($enquiry['reference_no']); ?></strong></span>
      </div>
      <div class="admin-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; background: var(--admin-bg); padding: 1rem; border-radius: var(--radius-sm);">
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Service Category</div>
            <div style="font-weight: 700; color: var(--admin-navy); margin-top: 2px;"><?php echo e($enquiry['service']); ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Estimated Volume</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo !empty($enquiry['estimated_volume']) ? e($enquiry['estimated_volume']) : 'Not specified'; ?></div>
          </div>
          <div>
            <div style="font-size: 11.5px; text-transform: uppercase; color: var(--admin-text-muted); font-weight: 700;">Company / Org</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark); margin-top: 2px;"><?php echo !empty($enquiry['company_name']) ? e($enquiry['company_name']) : 'Individual / Direct'; ?></div>
          </div>
        </div>

        <div style="margin-bottom: 0.5rem; font-weight: 700; color: var(--admin-navy-dark); font-size: 13px; text-transform: uppercase;">
          Client Project Brief:
        </div>
        <div style="background: #FFFFFF; border: 1px solid var(--admin-border); border-radius: var(--radius-sm); padding: 1.25rem; font-size: 14px; line-height: 1.6; color: #1E293B; white-space: pre-wrap;">
          <?php echo e($enquiry['project_details']); ?>
        </div>
      </div>
    </div>

    <!-- Internal Notes & CRM Activity -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Internal Notes &amp; Communication Log</h3>
        <span style="font-size: 11.5px; color: var(--admin-orange); font-weight: 700;">Private / Team Eyes Only</span>
      </div>
      <div class="admin-card-body">
        <form method="POST" action="<?php echo e($pageTargetUrl); ?>" style="margin-bottom: 1.75rem;">
          <?php echo csrfField(); ?>
          <input type="hidden" name="enquiry_id" value="<?php echo (int)$enquiryId; ?>">
          <input type="hidden" name="ref" value="<?php echo e($enquiry['reference_no']); ?>">
          <input type="hidden" name="form_action" value="add_note">
          <div class="form-group">
            <label for="note" class="form-label">Add Note / Log Call Details</label>
            <textarea id="note" name="note" class="form-control" placeholder="Log details of phone call, pricing discussion, sample batches, NDA signing, or next follow-up dates..." required></textarea>
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
        <form method="POST" action="<?php echo e($pageTargetUrl); ?>">
          <?php echo csrfField(); ?>
          <input type="hidden" name="enquiry_id" value="<?php echo (int)$enquiryId; ?>">
          <input type="hidden" name="ref" value="<?php echo e($enquiry['reference_no']); ?>">
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

    <!-- Client Profile Box -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="card-title">Client Information</h3>
      </div>
      <div class="admin-card-body" style="font-size: 13.5px;">
        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Full Name</div>
          <div style="font-weight: 700; color: var(--admin-navy-dark); font-size: 15px; margin-top: 2px;"><?php echo e($enquiry['full_name']); ?></div>
        </div>

        <?php if (!empty($enquiry['company_name'])): ?>
          <div style="margin-bottom: 1rem;">
            <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Company</div>
            <div style="font-weight: 600; color: var(--admin-navy-dark);"><?php echo e($enquiry['company_name']); ?></div>
          </div>
        <?php endif; ?>

        <div style="margin-bottom: 1rem;">
          <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Email Address</div>
          <div><a href="mailto:<?php echo e($enquiry['email']); ?>" style="word-break: break-all;"><?php echo e($enquiry['email']); ?></a></div>
        </div>

        <?php if (!empty($enquiry['phone'])): ?>
          <div style="margin-bottom: 1rem;">
            <div style="font-size: 11.5px; color: var(--admin-text-muted); text-transform: uppercase; font-weight: 700;">Mobile Phone</div>
            <div><a href="tel:<?php echo e($enquiry['phone']); ?>"><?php echo e($enquiry['phone']); ?></a></div>
          </div>
        <?php endif; ?>

        <div style="border-top: 1px solid var(--admin-border); padding-top: 0.75rem; font-size: 12px; color: var(--admin-text-muted);">
          <div>Submitted: <?php echo formatDate($enquiry['created_at']); ?></div>
          <div>Origin IP: <code><?php echo e($enquiry['ip_address'] ?? '127.0.0.1'); ?></code></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
