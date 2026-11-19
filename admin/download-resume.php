<?php
/**
 * DATNIX SOLUTIONS - Secure Resume Download Controller
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * 
 * SECURITY:
 * Requires authenticated administrator session.
 * Prevents arbitrary path traversal and serves files outside web execution.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$fileId = (int)($_GET['id'] ?? 0);
$fileNameParam = trim($_GET['file'] ?? '');

if ($fileId <= 0 && empty($fileNameParam)) {
    http_response_code(400);
    die('Invalid resume file reference.');
}

$admin = getCurrentAdmin();
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR;
$filePath = null;
$safeDownloadName = 'Candidate_Resume';
$mimeType = 'application/octet-stream';
$appId = 0;

try {
    if (!empty($fileNameParam)) {
        // Direct file reference from disk backup
        $safeFile = basename($fileNameParam);
        $filePath = $uploadDir . $safeFile;
        $safeDownloadName = 'Candidate_Resume_' . $safeFile;

        // Auto-detect extension mime
        $ext = strtolower(pathinfo($safeFile, PATHINFO_EXTENSION));
        if ($ext === 'pdf') $mimeType = 'application/pdf';
        elseif ($ext === 'doc') $mimeType = 'application/msword';
        elseif ($ext === 'docx') $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) $mimeType = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

    } elseif ($fileId > 0 && !empty($pdo)) {
        $stmt = $pdo->prepare('
            SELECT f.*, a.reference_no, a.full_name 
            FROM application_files f
            JOIN applications a ON f.application_id = a.id
            WHERE f.id = :id
        ');
        $stmt->execute([':id' => $fileId]);
        $fileRecord = $stmt->fetch();

        if (!$fileRecord) {
            http_response_code(404);
            die('Resume record not found in database.');
        }

        $filePath = $uploadDir . basename($fileRecord['stored_filename']);
        $safeDownloadName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileRecord['original_filename']);
        if (!empty($fileRecord['mime_type'])) {
            $mimeType = $fileRecord['mime_type'];
        }
        $appId = (int)$fileRecord['application_id'];
    }

    if (!$filePath || !file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        die('Resume document file is not present on disk.');
    }

    // Log the download event in audit logs if PDO is active
    if (!empty($pdo) && function_exists('logActivity')) {
        logActivity($pdo, $admin['id'] ?? 1, 'resume_downloaded', 'application', $appId, 'Downloaded resume: ' . $safeDownloadName);
    }

    // Clean any prior output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $safeDownloadName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));

    // Stream file in 8KB chunks
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
    exit;

} catch (Throwable $e) {
    error_log('[RESUME DOWNLOAD CONTROLLER ERROR] ' . $e->getMessage());
    http_response_code(500);
    die('Internal error processing resume file: ' . $e->getMessage());
}
