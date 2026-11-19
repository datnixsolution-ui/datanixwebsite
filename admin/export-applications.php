<?php
/**
 * DATNIX SOLUTIONS - Export Candidate Job Applications to CSV
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * Corporate Office: 20, Satkar Complex, Chimanlal Girdharlal Rd, Ahmedabad - 380006
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

if (empty($pdo)) {
    die('Database connection offline.');
}

$admin = getCurrentAdmin();

// Extract filter parameters
$search = trim($_GET['search'] ?? '');
$jobFilter = (int)($_GET['job_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

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
            a.total_experience,
            a.notice_period,
            a.current_ctc,
            a.expected_ctc,
            a.cover_note,
            a.resume_filename,
            a.status,
            a.ip_address,
            j.title AS job_title,
            j.department AS job_department
        FROM job_applications a
        LEFT JOIN jobs j ON a.job_id = j.id
        $whereSql
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Log export activity in audit trail
    logActivity($pdo, $admin['id'], 'export_applications_csv', 'job_application', null, 'Exported ' . count($rows) . ' candidate applications to CSV');

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
        'Current CTC (INR)',
        'Expected CTC (INR)',
        'Application Status',
        'Resume File',
        'Candidate Notes / Cover Letter',
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
            $row['total_experience'] ?: 'Not specified',
            $row['notice_period'] ?: 'Immediate / Not specified',
            $row['current_ctc'] ?: 'Confidential',
            $row['expected_ctc'] ?: 'Negotiable',
            $row['status'],
            $row['resume_filename'] ?: 'None',
            $row['cover_note'] ?: '',
            $row['ip_address']
        ]);
    }

    fclose($output);
    exit;

} catch (Throwable $e) {
    error_log('[APPLICATIONS CSV EXPORT ERROR] ' . $e->getMessage());
    die('Error generating CSV export: ' . $e->getMessage());
}
