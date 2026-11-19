<?php
/**
 * DATNIX SOLUTIONS - Export Candidate Job Applications to CSV
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$admin = getCurrentAdmin();

// Extract filter parameters
$search = trim($_GET['search'] ?? '');
$jobFilter = (int)($_GET['job_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$rows = [];

if (!empty($pdo)) {
    $whereClauses = [];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = '(a.reference_no LIKE :srch OR a.full_name LIKE :srch OR a.email LIKE :srch OR a.phone LIKE :srch OR a.current_location LIKE :srch)';
        $params[':srch'] = '%' . $search . '%';
    }

    if ($jobFilter > 0) {
        $whereClauses[] = 'a.job_id = :jid';
        $params[':jid'] = $jobFilter;
    }

    if (!empty($statusFilter)) {
        $whereClauses[] = 'a.status = :st';
        $params[':st'] = $statusFilter;
    }

    if (!empty($dateFrom)) {
        $whereClauses[] = 'DATE(a.created_at) >= :dfrom';
        $params[':dfrom'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $whereClauses[] = 'DATE(a.created_at) <= :dto';
        $params[':dto'] = $dateTo;
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.reference_no,
                a.created_at,
                a.full_name,
                a.email,
                a.phone,
                a.current_location,
                a.experience_years,
                a.notice_period,
                a.additional_info,
                a.status,
                a.ip_address,
                j.title AS job_title,
                j.department AS job_department,
                f.original_filename AS resume_filename
            FROM applications a
            LEFT JOIN jobs j ON a.job_id = j.id
            LEFT JOIN application_files f ON a.id = f.application_id
            $whereSql
            ORDER BY a.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (function_exists('logActivity')) {
            logActivity($pdo, $admin['id'] ?? 1, 'export_applications_csv', 'application', null, 'Exported ' . count($rows) . ' candidate applications to CSV');
        }
    } catch (Throwable $e) {
        error_log('[APPLICATIONS CSV EXPORT DB ERROR] ' . $e->getMessage());
    }
}

// Merge disk backup applications if not in DB rows
$diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications';
if (is_dir($diskDir)) {
    $existingRefs = array_column($rows, 'reference_no');
    $matches = glob($diskDir . DIRECTORY_SEPARATOR . 'app_*.json');
    if ($matches) {
        foreach ($matches as $mf) {
            $raw = @file_get_contents($mf);
            if ($raw) {
                $item = json_decode($raw, true);
                if ($item && isset($item['reference_no']) && !in_array($item['reference_no'], $existingRefs, true)) {
                    $rows[] = [
                        'id' => 0,
                        'reference_no' => $item['reference_no'],
                        'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                        'full_name' => $item['full_name'] ?? 'Candidate',
                        'email' => $item['email'] ?? '',
                        'phone' => $item['phone'] ?? '',
                        'current_location' => $item['current_location'] ?? ($item['candidate_address'] ?? 'WFH (All India)'),
                        'experience_years' => $item['experience_years'] ?? '',
                        'notice_period' => $item['notice_period'] ?? '',
                        'additional_info' => $item['additional_info'] ?? '',
                        'status' => $item['status'] ?? 'New',
                        'ip_address' => $item['ip_address'] ?? '127.0.0.1',
                        'job_title' => 'Data Operations (WFH)',
                        'job_department' => 'Operations',
                        'resume_filename' => $item['resume_original'] ?? (!empty($item['has_resume']) ? 'Resume' : 'None')
                    ];
                }
            }
        }
    }
}

$filename = 'datnix_job_applications_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, [
    'Application Reference ID',
    'Submission Date (IST/UTC)',
    'Candidate Full Name',
    'Email Address',
    'Mobile Phone',
    'Position Applied',
    'Department',
    'Current City/Location',
    'Total Experience',
    'Notice Period',
    'Application Status',
    'Resume File',
    'Candidate Cover Note',
    'Submission IP'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['reference_no'],
        $row['created_at'],
        $row['full_name'],
        $row['email'],
        $row['phone'] ?: 'Not provided',
        $row['job_title'] ?: 'General Talent Pool (Open)',
        $row['job_department'] ?: 'Operations',
        $row['current_location'] ?: 'Not specified',
        $row['experience_years'] ?: 'Not specified',
        $row['notice_period'] ?: 'Immediate / Not specified',
        $row['status'],
        $row['resume_filename'] ?: 'None',
        $row['additional_info'] ?: '',
        $row['ip_address'] ?? '127.0.0.1'
    ]);
}

fclose($output);
exit;
