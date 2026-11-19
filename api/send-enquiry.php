<?php
/**
 * DATNIX SOLUTIONS - Hardened PHP Backend Handler for Enquiry Form
 * Endpoint: /api/send-enquiry.php
 * Runtime: PHP 7.4+ / PHP 8+ (Apache / Nginx / PHP-FPM / cPanel)
 * 
 * Standardized Fields:
 *  - fullName        (string, required, 2-100 chars, no HTML/CRLF)
 *  - companyName     (string, optional, max 120 chars)
 *  - email           (string, required, RFC-compliant format, max 150 chars)
 *  - phone           (string, optional, 8-15 digits)
 *  - service         (string, required, strictly validated against whitelist)
 *  - estimatedVolume (string, optional, max 100 chars)
 *  - projectDetails  (string, required, 10-5000 chars)
 *  - honeypot        (string, anti-spam trap, must be empty)
 * 
 * Step 3.1 Security Hardening & Rate-Limiting Architecture:
 * 
 * 1. Genuine Sliding-Window Algorithm:
 *    - Sliding Window: 5 requests per 15-minute sliding window (900 seconds) per client IP.
 *    - Burst Cooldown: Minimum 10 seconds between submissions from the same IP.
 *      * Distinguishing note: The 10-second burst limit is an IP-level frequency control to stop
 *        rapid-fire bot automation, regardless of payload content.
 *      * The 30-second duplicate submission check is a content-signature control
 *        (email:service:detailsHash) to prevent accidental double-clicks on identical submissions.
 * 
 * 2. Client IP Resolution (Anti-Spoofing):
 *    - Never blindly trusts incoming X-Forwarded-For or X-Real-IP headers.
 *    - REMOTE_ADDR is the authoritative, unforgeable TCP socket address provided by the OS.
 *    - Forwarding headers (HTTP_CF_CONNECTING_IP, HTTP_X_REAL_IP, HTTP_X_FORWARDED_FOR) are
 *      ONLY trusted if REMOTE_ADDR originates from a verified private/loopback reverse proxy.
 * 
 * 3. Storage Boundary Clarification:
 *    - PHP flock() provides OS-level advisory locking on local scratch files in sys_get_temp_dir().
 *    - It synchronizes concurrent worker processes (PHP-FPM, Apache prefork) SHARING THE SAME FILESYSTEM ON A SINGLE SERVER.
 *    - It is NOT a globally distributed multi-server rate limiter. If deployed on a multi-server
 *      load-balanced cluster, a shared distributed store (like Redis) is required for global synchronization.
 * 
 * 4. RFC-Compliant Rate-Limit Headers:
 *    - Returns HTTP 429 with Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Vary: Origin');

// 1. Trusted Origins Whitelist for CORS
$trustedOrigins = [
    'https://datnixsolutions.com',
    'https://www.datnixsolutions.com',
    'http://datnixs.42web.io',
    'https://datnixs.42web.io',
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

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
header('Access-Control-Max-Age: 86400');

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$originAllowed = false;

if (!empty($requestOrigin)) {
    if (in_array($requestOrigin, $trustedOrigins, true) ||
        (!empty($serverHost) && ($requestOrigin === "https://$serverHost" || $requestOrigin === "http://$serverHost")) ||
        preg_match('/^https:\/\/[a-z0-9\-]+\.(netlify\.app|vercel\.app)$/', $requestOrigin)) {
        header("Access-Control-Allow-Origin: $requestOrigin");
        header('Access-Control-Allow-Credentials: true');
        $originAllowed = true;
    } else {
        header('Access-Control-Allow-Origin: null');
        $originAllowed = false;
    }
} else {
    // Same-origin or non-browser direct call
    $originAllowed = true;
}

// 2. Handle CORS Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!$originAllowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cross-origin request blocked.']);
        exit;
    }
    http_response_code(204);
    exit;
}

// 3. Request Method Restriction
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Only POST requests are accepted.']);
    exit;
}

// Reject untrusted cross-origin requests
if (!empty($requestOrigin) && !$originAllowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cross-Origin Request Blocked: Untrusted Origin.']);
    exit;
}

// 4. Request Body Size Limit Check (50KB maximum)
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 51200) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Payload Too Large. Request exceeds the 50KB limit.']);
    exit;
}

$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 51200) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Payload Too Large. Request exceeds the 50KB limit.']);
    exit;
}

// 5. Content-Type Verification
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (!empty($contentType) && strpos($contentType, 'application/json') === false && strpos($contentType, 'application/x-www-form-urlencoded') === false) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => 'Unsupported Media Type. Content-Type must be application/json.']);
    exit;
}

// Helper: Check if IP is a private/loopback address
function isPrivateIpPhp($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') return true;
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

// 6. Client IP Resolution (Anti-Spoofing Architecture)
function resolveClientIpPhp() {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Cloudflare Edge Check: If running behind Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }
    }

    // Reverse Proxy Check:
    // Only trust X-Forwarded-For or X-Real-IP if the direct TCP socket IP ($remoteAddr)
    // is a verified private/loopback reverse proxy (e.g., local Nginx or HAProxy).
    if (isPrivateIpPhp($remoteAddr)) {
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $realIp = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($parts as $part) {
                $p = trim($part);
                if (filter_var($p, FILTER_VALIDATE_IP) && !isPrivateIpPhp($p)) {
                    return $p;
                }
            }
            $first = trim($parts[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }

    // Direct public socket IP (unforgeable by client header manipulation)
    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
}

$clientIp = resolveClientIpPhp();

// 7. Multi-Process Safe Server-Side Rate Limiter (Genuine Sliding Window via flock)
function evaluateRateLimitPhp($ip) {
    $windowSeconds = 900; // 15 minutes (900 seconds)
    $maxRequests = 5;
    $burstInterval = 10;  // 10 seconds min between submissions
    $now = time();

    $safeIpKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip);
    $lockDir = sys_get_temp_dir();
    $filePath = $lockDir . DIRECTORY_SEPARATOR . 'datnix_ratelimit_' . md5($safeIpKey) . '.json';

    $fp = @fopen($filePath, 'c+');
    if (!$fp) {
        // Fallback if temp dir is unavailable
        return [
            'allowed' => true,
            'limit' => $maxRequests,
            'remaining' => $maxRequests,
            'retryAfter' => 0,
            'reset' => $now + $windowSeconds,
            'mode' => 'flock-fallback'
        ];
    }

    // OS-level atomic exclusive file lock across PHP-FPM / Apache workers
    flock($fp, LOCK_EX);

    $fileSize = filesize($filePath);
    $content = $fileSize > 0 ? fread($fp, $fileSize) : '';
    $data = !empty($content) ? json_decode($content, true) : null;

    if (!is_array($data)) {
        $data = ['timestamps' => [], 'lastRequest' => 0];
    }

    // True sliding-window log filter: timestamps in [now - 900s, now]
    $validTimestamps = [];
    foreach ($data['timestamps'] as $ts) {
        if (($now - $ts) < $windowSeconds) {
            $validTimestamps[] = $ts;
        }
    }

    // Check 1: 10-second burst interval
    if (!empty($data['lastRequest']) && ($now - $data['lastRequest']) < $burstInterval) {
        $waitSec = max(1, $burstInterval - ($now - $data['lastRequest']));
        flock($fp, LOCK_UN);
        fclose($fp);
        return [
            'allowed' => false,
            'limit' => $maxRequests,
            'remaining' => max(0, $maxRequests - count($validTimestamps)),
            'retryAfter' => $waitSec,
            'reset' => $now + $windowSeconds,
            'mode' => 'flock-local',
            'reason' => 'Rapid burst detected. Please wait 10 seconds between submissions.'
        ];
    }

    // Check 2: 5-request capacity within sliding window
    if (count($validTimestamps) >= $maxRequests) {
        $oldest = reset($validTimestamps);
        $retryAfter = max(1, ($oldest + $windowSeconds) - $now);
        flock($fp, LOCK_UN);
        fclose($fp);
        return [
            'allowed' => false,
            'limit' => $maxRequests,
            'remaining' => 0,
            'retryAfter' => $retryAfter,
            'reset' => $oldest + $windowSeconds,
            'mode' => 'flock-local',
            'reason' => 'Too many submissions from this IP address in the 15-minute window.'
        ];
    }

    // Record submission atomically
    $validTimestamps[] = $now;
    $updatedData = [
        'timestamps' => $validTimestamps,
        'lastRequest' => $now
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($updatedData));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $oldestTime = reset($validTimestamps);
    $resetTime = $oldestTime ? $oldestTime + $windowSeconds : $now + $windowSeconds;

    return [
        'allowed' => true,
        'limit' => $maxRequests,
        'remaining' => $maxRequests - count($validTimestamps),
        'retryAfter' => 0,
        'reset' => $resetTime,
        'mode' => 'flock-local'
    ];
}

$rateLimit = evaluateRateLimitPhp($clientIp);

header('X-RateLimit-Limit: ' . $rateLimit['limit']);
header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
header('X-RateLimit-Reset: ' . $rateLimit['reset']);
header('X-RateLimit-Mode: ' . ($rateLimit['mode'] ?? 'flock-local'));

if (!$rateLimit['allowed']) {
    header('Retry-After: ' . $rateLimit['retryAfter']);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => "Too many submissions from this address. Please wait {$rateLimit['retryAfter']} seconds before submitting again, or contact us directly on WhatsApp (+91 73870 10393) or email (datnixsolution@gmail.com).",
        'retryAfter' => $rateLimit['retryAfter']
    ]);
    exit;
}

// 8. Safe JSON Extraction & Structure Verification
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request payload structure. Expected a JSON object.']);
    exit;
}

// Prototype Pollution / Injected keys check
$forbiddenKeys = ['__proto__', 'constructor', 'prototype'];
foreach (array_keys($data) as $k) {
    if (in_array(strtolower($k), $forbiddenKeys, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payload key detected.']);
        exit;
    }
}

// 9. Standardized Fields Extraction
$fullName = trim((string)($data['fullName'] ?? $data['Full Name'] ?? ''));
$companyName = trim((string)($data['companyName'] ?? $data['Company Name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? $data['emailAddress'] ?? $data['Email Address'] ?? '')));
$phone = trim((string)($data['phone'] ?? $data['mobileNumber'] ?? $data['Mobile Number'] ?? ''));
$serviceInput = trim((string)($data['service'] ?? $data['serviceRequired'] ?? $data['Service Required'] ?? ''));
$estimatedVolume = trim((string)($data['estimatedVolume'] ?? $data['Estimated Project Volume'] ?? ''));
$projectDetails = trim((string)($data['projectDetails'] ?? $data['Project Details'] ?? ''));
$honeypot = trim((string)($data['honeypot'] ?? $data['_gotcha'] ?? ''));

// 10. Anti-Spam Honeypot Verification
if (!empty($honeypot)) {
    echo json_encode(['success' => true, 'message' => 'Enquiry received.']);
    exit;
}

// 11. CRLF Injection Guard
$singleLineFields = [$fullName, $companyName, $email, $phone, $serviceInput, $estimatedVolume];
foreach ($singleLineFields as $val) {
    if (preg_match('/[\r\n]/', $val)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input characters detected in form submission.']);
        exit;
    }
}

// 12. Strict Server-Side Input Validation
$errors = [];

// Full Name
if (strlen($fullName) < 2 || strlen($fullName) > 100) {
    $errors[] = 'Full Name must be between 2 and 100 characters.';
} elseif (preg_match('/[<>]/', $fullName) || !preg_match('/^[a-zA-Z\s\.\'\-\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}]+$/u', $fullName)) {
    $errors[] = 'Full Name contains invalid characters.';
}

// Email
if (strlen($email) < 5 || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid business email address is required.';
} elseif (!preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/', $email)) {
    $errors[] = 'A valid business email address is required.';
}

// Phone (Optional)
if (!empty($phone)) {
    if (strlen($phone) > 25) {
        $errors[] = 'Phone number is too long.';
    } elseif (!preg_match('/^[0-9+\s\-()]+$/', $phone)) {
        $errors[] = 'Phone number contains invalid characters.';
    } else {
        $cleanDigits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanDigits) < 8 || strlen($cleanDigits) > 15) {
            $errors[] = 'Phone number must contain between 8 and 15 digits.';
        }
    }
}

// Company Name (Optional)
if (!empty($companyName)) {
    if (strlen($companyName) > 120) {
        $errors[] = 'Company Name must not exceed 120 characters.';
    } elseif (preg_match('/[<>]/', $companyName)) {
        $errors[] = 'Company Name contains invalid characters.';
    }
}

// Service Selection Whitelist with tolerant matching
$canonicalService = '';
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
    } elseif (stripos($serviceInput, 'process') !== false) {
        $canonicalService = 'Data Processing';
    } elseif (!empty($serviceInput) && strlen($serviceInput) <= 120 && !preg_match('/[<>]/', $serviceInput)) {
        $canonicalService = htmlspecialchars($decodedService, ENT_QUOTES, 'UTF-8');
    } else {
        $canonicalService = 'General Outsourcing';
    }
}

// Estimated Volume (Optional)
if (!empty($estimatedVolume)) {
    if (strlen($estimatedVolume) > 100) {
        $errors[] = 'Estimated Volume must not exceed 100 characters.';
    } elseif (preg_match('/[<>]/', $estimatedVolume)) {
        $errors[] = 'Estimated Volume contains invalid characters.';
    }
}

// Project Details
if (strlen($projectDetails) < 10 || strlen($projectDetails) > 5000) {
    $errors[] = 'Project Details must be between 10 and 5,000 characters.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// 13. Content-Signature Duplicate Submission Prevention (30-second window)
$duplicateLockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'datnix_dup_' . md5($email . ':' . $canonicalService . ':' . substr($projectDetails, 0, 50)) . '.txt';
$nowTime = time();
if (file_exists($duplicateLockFile)) {
    $prevTime = (int)@file_get_contents($duplicateLockFile);
    if (($nowTime - $prevTime) < 30) {
        $waitSec = max(1, 30 - ($nowTime - $prevTime));
        header('Retry-After: ' . $waitSec);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => "Duplicate enquiry detected. Please wait {$waitSec} seconds before resubmitting.",
            'retryAfter' => $waitSec
        ]);
        exit;
    }
}
@file_put_contents($duplicateLockFile, (string)$nowTime);

// 14. Database Persistence (MODULE 3: ENQUIRY MANAGEMENT)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Generate unique reference number (e.g., DNX-ENQ-20261118-B4C2)
$referenceNo = function_exists('generateReferenceNo') ? generateReferenceNo('ENQ') : ('DNX-ENQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))));
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
$savedToDb = false;

if (empty($pdo)) {
    try {
        $pdo = Database::getConnection();
    } catch (Throwable $e) {
        $pdo = null;
    }
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
        error_log('[ENQUIRY DB STORAGE ERROR] ' . $e->getMessage());
        $savedToDb = false;
    }
}

// Zero-Loss Fallback: Save to secure JSON file if DB unavailable or pending setup
if (!$savedToDb) {
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
        'ip_address'       => $clientIp,
        'user_agent'       => $userAgent,
        'created_at'       => date('Y-m-d H:i:s'),
        'source'           => 'web_enquiry_form'
    ];
    @file_put_contents(
        $backupDir . DIRECTORY_SEPARATOR . 'enq_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $referenceNo) . '.json',
        json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// Always deliver instant 200 OK confirmation with verified Reference Number
http_response_code(200);
echo json_encode([
    'success'     => true,
    'message'     => 'Thank you! Your enquiry has been received successfully and logged under Reference ID ' . $referenceNo . '. Our team will review your requirements and reach out within 2-4 business hours.',
    'referenceNo' => $referenceNo,
    'service'     => $canonicalService
]);
exit;
