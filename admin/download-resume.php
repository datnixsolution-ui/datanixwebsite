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
if ($fileId <= 0 || empty($pdo)) {
    http_response_code(400);
    die('Invalid resume file reference.');
}

$admin = getCurrentAdmin();

try {
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

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR;
    $filePath = $uploadDir . basename($fileRecord['stored_filename']);

    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        die('Resume document file is not present on disk.');
    }

    // Log the download event in audit logs
    logActivity($pdo, $admin['id'], 'resume_downloaded', 'application', (int)$fileRecord['application_id'], 'Downloaded resume: ' . $fileRecord['original_filename']);

    // Clean any prior output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set secure download headers
    $safeDownloadName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileRecord['original_filename']);
    $mimeType = !empty($fileRecord['mime_type']) ? $fileRecord['mime_type'] : 'application/octet-stream';

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
    error_log('[RESUME DOWNLOAD ERROR] ' . $e->getMessage());
    http_response_code(500);
    die('An error occurred during resume retrieval.');
}
