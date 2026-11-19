<?php
/**
 * DATNIX SOLUTIONS - Core Utility Functions
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/csrf.php';

/**
 * Escape HTML output to prevent Cross-Site Scripting (XSS)
 * 
 * @param mixed $value
 * @return string
 */
function e($value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize string input
 * 
 * @param mixed $input
 * @return string
 */
function sanitizeInput($input): string {
    if ($input === null) {
        return '';
    }
    $str = trim((string)$input);
    // Strip null bytes
    return str_replace(chr(0), '', $str);
}

/**
 * Generate unique, human-readable reference number
 * Format: DNX-{PREFIX}-YYYYMMDD-{RANDOM_HEX}
 * Example: DNX-ENQ-20261118-8F2B
 * 
 * @param string $prefix E.g. 'ENQ', 'APP', 'JOB'
 * @return string
 */
function generateReferenceNo(string $prefix = 'ENQ'): string {
    $datePart = date('Ymd');
    $randPart = strtoupper(bin2hex(random_bytes(2))); // 4 hex characters
    return sprintf('DNX-%s-%s-%s', strtoupper($prefix), $datePart, $randPart);
}

/**
 * Generate URL-friendly slug from string
 * 
 * @param string $text
 * @return string
 */
function generateSlug(string $text): string {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);
    return empty($text) ? 'item-' . time() : $text;
}

/**
 * Anti-Spoofing Client IP Resolution
 * (Preserves Step 3.1 architecture)
 * 
 * @return string
 */
function getClientIp(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Cloudflare Edge Check
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }
    }

    // Reverse Proxy Check
    $isPrivate = ($remoteAddr === '127.0.0.1' || $remoteAddr === '::1' || $remoteAddr === 'localhost')
        || !filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

    if ($isPrivate) {
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
                if (filter_var($p, FILTER_VALIDATE_IP) && filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $p;
                }
            }
            $first = trim($parts[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
}

/**
 * Log administrative activity to database
 * 
 * @param PDO|null $pdo
 * @param int|null $adminId
 * @param string $action E.g., 'login', 'enquiry_status_updated'
 * @param string $entityType E.g., 'enquiry', 'job', 'application', 'admin'
 * @param int|null $entityId
 * @param string|null $details
 * @return bool
 */
function logActivity(?PDO $pdo, ?int $adminId, string $action, string $entityType, ?int $entityId = null, ?string $details = null): bool {
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('
            INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, created_at)
            VALUES (:admin_id, :action, :entity_type, :entity_id, :details, :ip_address, NOW())
        ');
        return $stmt->execute([
            ':admin_id'    => $adminId,
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':details'     => $details,
            ':ip_address'  => getClientIp()
        ]);
    } catch (Throwable $e) {
        error_log('[DATNIX LOG ERROR] ' . $e->getMessage());
        return false;
    }
}

/**
 * Flash message management for redirects
 */
function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessage(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Return CSS badge class for enquiry/application statuses
 */
function getStatusBadgeClass(string $status): string {
    switch ($status) {
        case 'New':
            return 'badge-status-new';
        case 'Contacted':
        case 'Under Review':
            return 'badge-status-review';
        case 'In Discussion':
        case 'Shortlisted':
            return 'badge-status-progress';
        case 'Quotation Sent':
        case 'Interview Scheduled':
            return 'badge-status-scheduled';
        case 'Converted':
        case 'Selected':
        case 'Active':
            return 'badge-status-success';
        case 'Closed':
        case 'Rejected':
            return 'badge-status-danger';
        case 'Draft':
            return 'badge-status-draft';
        default:
            return 'badge-status-default';
    }
}

/**
 * Format timestamp nicely
 */
function formatDate(?string $datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime)) {
        return '—';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Throwable $e) {
        return (string)$datetime;
    }
}

/**
 * Format file size in human-readable units
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Safe redirect helper
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}
