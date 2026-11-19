<?php
/**
 * DATNIX SOLUTIONS - Job Application & Resume Upload Backend
 * Endpoint: /api/apply.php
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Only POST is accepted.']);
    exit;
}

$clientIp = getClientIp();

// Anti-Spam Honeypot Check
$honeypot = trim((string)($_POST['honeypot'] ?? ''));
if (!empty($honeypot)) {
    // Silently trap bot
    echo json_encode(['success' => true, 'message' => 'Application received.']);
    exit;
}

// Rate Limiting: Max 5 applications per 30 minutes from same IP
$lockDir = sys_get_temp_dir();
$ipHash = md5('datnix_apply_' . $clientIp);
$rateLimitFile = $lockDir . DIRECTORY_SEPARATOR . 'apply_rl_' . $ipHash . '.json';
$now = time();

$fp = @fopen($rateLimitFile, 'c+');
if ($fp) {
    flock($fp, LOCK_EX);
    $size = filesize($rateLimitFile);
    $content = $size > 0 ? fread($fp, $size) : '';
    $data = !empty($content) ? json_decode($content, true) : ['timestamps' => []];
    if (!is_array($data) || !isset($data['timestamps'])) {
        $data = ['timestamps' => []];
    }
    
    // Filter last 1800s (30m)
    $validTimestamps = array_filter($data['timestamps'], function($ts) use ($now) {
        return ($now - $ts) < 1800;
    });

    if (count($validTimestamps) >= 5) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Submission limit reached for candidate applications. Please wait a while before submitting again.'
        ]);
        exit;
    }

    $validTimestamps[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(['timestamps' => array_values($validTimestamps)]));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

// Database Connection
try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    error_log('[APPLY DB ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database service currently unavailable. Please email your resume to datnixsolutions@gmail.com.']);
    exit;
}

// Input Fields Extraction
$fullName       = trim((string)($_POST['fullName'] ?? ''));
$email          = strtolower(trim((string)($_POST['email'] ?? '')));
$phone          = trim((string)($_POST['phone'] ?? ''));
$jobId          = (int)($_POST['jobId'] ?? 0);
$experience     = trim((string)($_POST['experience'] ?? ''));
$location       = trim((string)($_POST['location'] ?? ''));
$noticePeriod   = trim((string)($_POST['noticePeriod'] ?? ''));
$additionalInfo = trim((string)($_POST['additionalInfo'] ?? ''));
$privacyConsent = !empty($_POST['privacyConsent']);

// Validation
$errors = [];

if (strlen($fullName) < 2 || strlen($fullName) > 100) {
    $errors[] = 'Full Name must be between 2 and 100 characters.';
} elseif (preg_match('/[<>]/', $fullName)) {
    $errors[] = 'Full Name contains invalid characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
    $errors[] = 'Please enter a valid phone number (8-15 digits).';
}

if (empty($experience)) {
    $errors[] = 'Total experience is required.';
}

if (empty($location)) {
    $errors[] = 'Current location is required.';
}

if (empty($noticePeriod)) {
    $errors[] = 'Notice period is required.';
}

if (!$privacyConsent) {
    $errors[] = 'You must agree to the privacy policy to submit your application.';
}

// Validate Job ID (if > 0, verify it exists in jobs table; otherwise null for General Talent Pool)
$targetJobId = null;
if ($jobId > 0) {
    $jobCheck = $pdo->prepare('SELECT id FROM jobs WHERE id = :id');
    $jobCheck->execute([':id' => $jobId]);
    if ($jobCheck->fetch()) {
        $targetJobId = $jobId;
    }
}

// Resume File Validation & Secure Storage
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please upload a valid resume document.';
} else {
    $resume = $_FILES['resume'];
    $maxSize = 5242880; // 5MB

    if ($resume['size'] > $maxSize) {
        $errors[] = 'Resume file exceeds the maximum 5MB size limit.';
    }

    $originalName = basename($resume['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    // Reject double extensions (e.g. file.php.pdf or file.phtml.docx)
    if (preg_match('/\.(php|phtml|php\d|phps|cgi|pl|py|sh|bash|exe|bat|cmd|vbs|js|jar|wsf)\./i', $originalName)) {
        $errors[] = 'Suspicious double extension detected in filename.';
    }

    $allowedExts = ['pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowedExts, true)) {
        $errors[] = 'Invalid file extension. Only PDF, DOC, and DOCX resumes are accepted.';
    }

    // MIME type check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $resume['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/x-pdf',
        'application/octet-stream' // Some Windows word uploads report octet-stream with doc/docx
    ];

    if (!in_array($mimeType, $allowedMimes, true)) {
        $errors[] = 'Invalid file content type. Please upload a genuine PDF or Word document.';
    }

    // Deep Magic Byte Header Verification & Polyglot Injection Shield
    $handle = @fopen($resume['tmp_name'], 'rb');
    if (!$handle) {
        $errors[] = 'Unable to inspect resume file content.';
    } else {
        $headerBytes = (string)fread($handle, 16);
        $fileSample = $headerBytes . (string)fread($handle, 4096);
        fclose($handle);

        // Check for executable script signatures inside document (Polyglot defense)
        if (preg_match('/<\?(?:php|=)|<script\b/i', $fileSample)) {
            $errors[] = 'Security Alert: Active script signatures detected inside uploaded document.';
        }

        // Validate binary magic signatures
        if ($ext === 'pdf') {
            if (strncmp($headerBytes, '%PDF-', 5) !== 0) {
                $errors[] = 'Invalid PDF document structure. File header does not match genuine PDF.';
            }
        } elseif ($ext === 'docx') {
            if (strncmp($headerBytes, "PK\x03\x04", 4) !== 0) {
                $errors[] = 'Invalid DOCX document structure. File header does not match OpenXML format.';
            }
        } elseif ($ext === 'doc') {
            $docSignature = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
            if (strncmp($headerBytes, $docSignature, 8) !== 0 && strncmp($headerBytes, "PK\x03\x04", 4) !== 0) {
                $errors[] = 'Invalid Word document structure. File header does not match binary format.';
            }
        }
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Generate Secure Random Stored Filename
$randomFilename = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0750, true);
}

$destPath = $uploadDir . $randomFilename;

if (!move_uploaded_file($resume['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to store resume on server. Please try again.']);
    exit;
}

// Ensure non-executable permissions
@chmod($destPath, 0640);

// Generate Application Reference ID
$referenceNo = generateReferenceNo('APP');
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

try {
    $pdo->beginTransaction();

    // 1. Insert into applications table
    $appStmt = $pdo->prepare('
        INSERT INTO applications (
            reference_no, job_id, full_name, email, phone,
            experience_years, current_location, notice_period,
            additional_info, status, ip_address, user_agent, created_at, updated_at
        ) VALUES (
            :ref, :job_id, :name, :email, :phone,
            :exp, :loc, :notice,
            :info, "New", :ip, :ua, NOW(), NOW()
        )
    ');

    $appStmt->execute([
        ':ref'     => $referenceNo,
        ':job_id'  => $targetJobId,
        ':name'    => $fullName,
        ':email'   => $email,
        ':phone'   => $phone,
        ':exp'     => $experience,
        ':loc'     => $location,
        ':notice'  => $noticePeriod,
        ':info'    => !empty($additionalInfo) ? $additionalInfo : null,
        ':ip'      => $clientIp,
        ':ua'      => $userAgent
    ]);

    $applicationId = (int)$pdo->lastInsertId();

    // 2. Insert into application_files table
    $fileStmt = $pdo->prepare('
        INSERT INTO application_files (
            application_id, original_filename, stored_filename, file_path, file_size, mime_type, created_at
        ) VALUES (
            :app_id, :orig, :stored, :path, :size, :mime, NOW()
        )
    ');

    $fileStmt->execute([
        ':app_id' => $applicationId,
        ':orig'   => $originalName,
        ':stored' => $randomFilename,
        ':path'   => $destPath,
        ':size'   => (int)$resume['size'],
        ':mime'   => $mimeType
    ]);

    // 3. Log activity
    logActivity($pdo, null, 'application_submitted', 'application', $applicationId, "New candidate application $referenceNo from $fullName ($email)");

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success'     => true,
        'message'     => 'Thank you! Your job application has been successfully submitted under Reference ID ' . $referenceNo . '.',
        'referenceNo' => $referenceNo
    ]);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean up uploaded file on failure
    if (file_exists($destPath)) {
        @unlink($destPath);
    }
    error_log('[APPLICATION SAVE ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'A database error occurred while registering your application. Please try again or email your resume directly to datnixsolutions@gmail.com.'
    ]);
    exit;
}
