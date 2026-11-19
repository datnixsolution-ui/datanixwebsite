<?php
/**
 * DATNIX SOLUTIONS - Hardened & Ultra-Reliable Backend Handler for Enquiry Form
 * Endpoint: /api/send-enquiry.php
 * Runtime: PHP 7.4+ / PHP 8+ (Apache / Nginx / PHP-FPM / Shared Hosting)
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, no-cache, must-revalidate, private');

// 1. CORS Configuration (Permissive and Robust)
$trustedOrigins = [
    'https://datnixsolutions.com',
    'https://www.datnixsolutions.com',
    'http://datnixs.42web.io',
    'https://datnixs.42web.io',
    'http://www.datnixs.42web.io',
    'https://www.datnixs.42web.io',
    'https://datnixsolution-ui.github.io',
    'http://datnixsolution-ui.github.io',
    'http://localhost:3000',
    'http://localhost:5000',
    'http://localhost:5500',
    'http://localhost:8000',
    'http://127.0.0.1:5500',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8080'
];

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverHost = $_SERVER['HTTP_HOST'] ?? '';

if (!empty($requestOrigin)) {
    if (in_array($requestOrigin, $trustedOrigins, true) ||
        (!empty($serverHost) && ($requestOrigin === "https://$serverHost" || $requestOrigin === "http://$serverHost")) ||
        preg_match('/^https?:\/\/[a-z0-9\-]+\.(netlify\.app|vercel\.app|github\.io|42web\.io)$/i', $requestOrigin)) {
        header("Access-Control-Allow-Origin: $requestOrigin");
        header('Access-Control-Allow-Credentials: true');
    } else {
        header("Access-Control-Allow-Origin: *");
    }
} else {
    header("Access-Control-Allow-Origin: *");
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle CORS Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Request Method Restriction
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Only POST requests are accepted.']);
    exit;
}

// Client IP Resolution
function resolveClientIp() {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
}

$clientIp = resolveClientIp();

// Safe Scratch Directory for Rate Limiting
$tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0755, true);
}
if (!is_dir($tempDir) || !is_writable($tempDir)) {
    $tempDir = sys_get_temp_dir();
}

// Rate Limiting (Permissive 10 requests / 10 minutes, fail-open)
$rateLimitFile = $tempDir . DIRECTORY_SEPARATOR . 'datnix_rl_' . md5($clientIp) . '.json';
$now = time();
$window = 600;
$maxReq = 15;

$fp = @fopen($rateLimitFile, 'c+');
if ($fp) {
    @flock($fp, LOCK_EX);
    $size = (int)@filesize($rateLimitFile);
    $content = $size > 0 ? @fread($fp, $size) : '';
    $rlData = !empty($content) ? json_decode($content, true) : null;
    if (!is_array($rlData) || !isset($rlData['timestamps'])) {
        $rlData = ['timestamps' => []];
    }
    $validTimestamps = [];
    foreach ($rlData['timestamps'] as $ts) {
        if (($now - $ts) < $window) {
            $validTimestamps[] = $ts;
        }
    }
    if (count($validTimestamps) >= $maxReq) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Submission frequency limit reached. Please wait a few minutes before submitting again, or contact us directly on WhatsApp (+91 73870 10393) or email (datnixsolution@gmail.com).'
        ]);
        exit;
    }
    $validTimestamps[] = $now;
    @ftruncate($fp, 0);
    @rewind($fp);
    @fwrite($fp, json_encode(['timestamps' => $validTimestamps]));
    @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);
}

// 2. Multi-Format Payload Extraction (Supports standard FormData, URL-encoded, or raw JSON)
$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} else {
    $rawInput = @file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = @json_decode($rawInput, true);
        if (is_array($json)) {
            $data = $json;
        } else {
            parse_str($rawInput, $data);
        }
    }
}

// Prototype Pollution / Injected keys check
$forbiddenKeys = ['__proto__', 'constructor', 'prototype'];
foreach (array_keys($data) as $k) {
    if (in_array(strtolower($k), $forbiddenKeys, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payload format.']);
        exit;
    }
}

// Standardized Fields Extraction
$fullName        = trim((string)($data['fullName'] ?? $data['Full Name'] ?? $data['full_name'] ?? $data['name'] ?? ''));
$companyName     = trim((string)($data['companyName'] ?? $data['Company Name'] ?? $data['company_name'] ?? $data['company'] ?? ''));
$email           = strtolower(trim((string)($data['email'] ?? $data['emailAddress'] ?? $data['Email Address'] ?? '')));
$phone           = trim((string)($data['phone'] ?? $data['mobileNumber'] ?? $data['Mobile Number'] ?? $data['mobile'] ?? ''));
$serviceInput    = trim((string)($data['service'] ?? $data['serviceRequired'] ?? $data['Service Required'] ?? 'Data Entry'));
$estimatedVolume = trim((string)($data['estimatedVolume'] ?? $data['Estimated Project Volume'] ?? $data['estimated_volume'] ?? ''));
$projectDetails  = trim((string)($data['projectDetails'] ?? $data['Project Details'] ?? $data['project_details'] ?? $data['message'] ?? ''));
$honeypot        = trim((string)($data['honeypot'] ?? $data['_gotcha'] ?? ''));

// Anti-Spam Honeypot Verification
if (!empty($honeypot)) {
    echo json_encode(['success' => true, 'message' => 'Enquiry received.']);
    exit;
}

// CRLF Injection Guard
$singleLineFields = [$fullName, $companyName, $email, $phone, $serviceInput, $estimatedVolume];
foreach ($singleLineFields as $val) {
    if (preg_match('/[\r\n]/', $val)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid characters detected in form submission.']);
        exit;
    }
}

// Validation
$errors = [];

// Full Name: 2 - 100 characters
if (strlen($fullName) < 2 || strlen($fullName) > 100) {
    $errors[] = 'Full Name must be between 2 and 100 characters.';
}

// Email: Standard RFC-compliant format
if (strlen($email) < 5 || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid business email address is required.';
}

// Phone: Optional, 8-20 characters if provided
if (!empty($phone)) {
    $cleanDigits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanDigits) < 8 || strlen($cleanDigits) > 16) {
        $errors[] = 'Contact number must contain between 8 and 16 digits.';
    }
}

// Company Name: Optional, max 120 chars
if (!empty($companyName) && strlen($companyName) > 120) {
    $errors[] = 'Company Name must not exceed 120 characters.';
}

// Service Canonicalization
$canonicalService = 'Data Entry (All Languages & Multilingual)';
$allowedServices = [
    'Data Entry',
    'Data Entry (All Languages & Multilingual)',
    'Data Entry (All Languages &amp; Multilingual)',
    'OCR Processing & Data Conversion (ePUB/XML)',
    'OCR Processing &amp; Data Conversion (ePUB/XML)',
    'Arabic Data Conversion & Localization',
    'Arabic Data Conversion &amp; Localization',
    'Data Processing',
    'Back Office Support',
    'Document Management',
    'Web Research',
    'E-commerce Data Management',
    'Data Verification',
    'Data Quality & BPO',
    'BPO and Process Support',
    'General Outsourcing',
    'Other / Custom Inquiry',
    'Other'
];

$decodedService = html_entity_decode($serviceInput, ENT_QUOTES | ENT_HTML5, 'UTF-8');
foreach ($allowedServices as $s) {
    if (strcasecmp($s, $serviceInput) === 0 || strcasecmp($s, $decodedService) === 0) {
        $canonicalService = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        break;
    }
}
if (empty($canonicalService)) {
    if (stripos($serviceInput, 'data entry') !== false) {
        $canonicalService = 'Data Entry (All Languages & Multilingual)';
    } elseif (stripos($serviceInput, 'ocr') !== false || stripos($serviceInput, 'epub') !== false) {
        $canonicalService = 'OCR Processing & Data Conversion (ePUB/XML)';
    } elseif (stripos($serviceInput, 'arabic') !== false) {
        $canonicalService = 'Arabic Data Conversion & Localization';
    } elseif (stripos($serviceInput, 'back office') !== false) {
        $canonicalService = 'Back Office Support';
    } elseif (stripos($serviceInput, 'document') !== false) {
        $canonicalService = 'Document Management';
    } elseif (stripos($serviceInput, 'research') !== false) {
        $canonicalService = 'Web Research';
    } elseif (stripos($serviceInput, 'commerce') !== false || stripos($serviceInput, 'catalog') !== false) {
        $canonicalService = 'E-commerce Data Management';
    } elseif (stripos($serviceInput, 'verification') !== false || stripos($serviceInput, 'quality') !== false) {
        $canonicalService = 'Data Verification';
    } else {
        $canonicalService = !empty($decodedService) ? htmlspecialchars($decodedService, ENT_QUOTES, 'UTF-8') : 'Data Entry Operations';
    }
}

// Estimated Volume: Optional, max 100 chars
if (!empty($estimatedVolume) && strlen($estimatedVolume) > 100) {
    $errors[] = 'Estimated Volume must not exceed 100 characters.';
}

// Project Details: 3 - 5,000 characters (Relaxed minimum to accommodate brief scopes like "Data entry")
if (strlen($projectDetails) < 3 || strlen($projectDetails) > 5000) {
    $errors[] = 'Project Details must be between 3 and 5,000 characters.';
}

// Detect AJAX vs Direct Browser Form Submission
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) ||
          (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
          (!empty($_POST['is_ajax'])) ||
          (!empty($data['is_ajax']));

if (!empty($errors)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
        exit;
    } else {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $redirectPage = (strpos($referer, 'index.html') !== false) ? '../index.html' : '../contact.html';
        $errStr = urlencode(implode(' ', $errors));
        header('Location: ' . $redirectPage . '?enquiry_status=error&msg=' . $errStr . '#quote');
        exit;
    }
}

// Double-Click Rapid Duplicate Prevention (3 seconds)
$dupFile = $tempDir . DIRECTORY_SEPARATOR . 'datnix_dup_' . md5($email . ':' . $canonicalService . ':' . substr($projectDetails, 0, 40)) . '.txt';
$nowTime = time();
if (file_exists($dupFile)) {
    $prevTime = (int)@file_get_contents($dupFile);
    if (($nowTime - $prevTime) < 3) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Duplicate enquiry submission detected. Please wait a moment.'
        ]);
        exit;
    }
}
@file_put_contents($dupFile, (string)$nowTime);

// 3. Database & Shared Modules
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$referenceNo = function_exists('generateReferenceNo') ? generateReferenceNo('ENQ') : ('DNX-ENQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))));
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
$savedToDb = false;

// Connect to Database
$pdo = null;
try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    $pdo = null;
}

if ($pdo) {
    try {
        $insertStmt = $pdo->prepare('
            INSERT INTO enquiries (
                reference_no, full_name, company_name, email, phone,
                service, estimated_volume, project_details, status,
                ip_address, user_agent, created_at, updated_at
            ) VALUES (
                :ref, :name, :company, :email, :phone,
                :service, :volume, :details, "New",
                :ip, :ua, NOW(), NOW()
            )
        ');

        $inserted = $insertStmt->execute([
            ':ref'     => $referenceNo,
            ':name'    => $fullName,
            ':company' => !empty($companyName) ? $companyName : null,
            ':email'   => $email,
            ':phone'   => !empty($phone) ? $phone : null,
            ':service' => $canonicalService,
            ':volume'  => !empty($estimatedVolume) ? $estimatedVolume : null,
            ':details' => $projectDetails,
            ':ip'      => $clientIp,
            ':ua'      => $userAgent
        ]);

        if ($inserted) {
            $savedToDb = true;
            $enquiryId = (int)$pdo->lastInsertId();
            if (function_exists('logActivity')) {
                logActivity($pdo, null, 'enquiry_submitted', 'enquiry', $enquiryId, "New enquiry $referenceNo from $fullName ($email)");
            }
        }
    } catch (Throwable $e) {
        error_log('[ENQUIRY DB ERROR] ' . $e->getMessage());
        $savedToDb = false;
    }
}

// 4. Dual-Save Architecture: Always record JSON backup to disk
$backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
$backupPayload = [
    'reference_no'     => $referenceNo,
    'full_name'        => $fullName,
    'company_name'     => $companyName,
    'email'            => $email,
    'phone'            => $phone,
    'service'          => $canonicalService,
    'estimated_volume' => $estimatedVolume,
    'project_details'  => $projectDetails,
    'status'           => 'New',
    'ip_address'       => $clientIp,
    'user_agent'       => $userAgent,
    'created_at'       => date('Y-m-d H:i:s'),
    'source'           => 'web_enquiry_form'
];
@file_put_contents(
    $backupDir . DIRECTORY_SEPARATOR . 'enq_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $referenceNo) . '.json',
    json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// 5. Deliver Confirmation (JSON for AJAX, Redirect for native browser POST)
if ($isAjax) {
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'     => true,
        'message'     => 'Thank you! Your enquiry has been received successfully and logged under Reference ID ' . $referenceNo . '. Our operations pod will review your specifications and contact you shortly.',
        'referenceNo' => $referenceNo,
        'service'     => $canonicalService
    ]);
    exit;
} else {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $redirectPage = (strpos($referer, 'index.html') !== false) ? '../index.html' : '../contact.html';
    header('Location: ' . $redirectPage . '?enquiry_status=success&ref=' . urlencode($referenceNo) . '#quote');
    exit;
}
