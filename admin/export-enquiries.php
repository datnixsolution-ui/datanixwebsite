<?php
/**
 * DATNIX SOLUTIONS - Export Enquiries to CSV
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$admin = getCurrentAdmin();

// Extract filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$serviceFilter = trim($_GET['service'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$rows = [];

if (!empty($pdo)) {
    $whereClauses = [];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = '(reference_no LIKE :srch OR full_name LIKE :srch OR company_name LIKE :srch OR email LIKE :srch OR phone LIKE :srch)';
        $params[':srch'] = '%' . $search . '%';
    }

    if (!empty($statusFilter)) {
        $whereClauses[] = 'status = :st';
        $params[':st'] = $statusFilter;
    }

    if (!empty($serviceFilter)) {
        $whereClauses[] = 'service = :srv';
        $params[':srv'] = $serviceFilter;
    }

    if (!empty($dateFrom)) {
        $whereClauses[] = 'DATE(created_at) >= :dfrom';
        $params[':dfrom'] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $whereClauses[] = 'DATE(created_at) <= :dto';
        $params[':dto'] = $dateTo;
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM enquiries $whereSql ORDER BY created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (function_exists('logActivity')) {
            logActivity($pdo, $admin['id'] ?? 1, 'export_enquiries_csv', 'enquiry', null, 'Exported ' . count($rows) . ' enquiries to CSV');
        }
    } catch (Throwable $e) {
        error_log('[CSV EXPORT DB ERROR] ' . $e->getMessage());
    }
}

// Merge disk backup enquiries if not in DB rows
$diskDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'enquiries';
if (is_dir($diskDir)) {
    $existingRefs = array_column($rows, 'reference_no');
    $matches = glob($diskDir . DIRECTORY_SEPARATOR . 'enq_*.json');
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
                        'full_name' => $item['full_name'] ?? 'Inquirer',
                        'company_name' => $item['company_name'] ?? '',
                        'email' => $item['email'] ?? '',
                        'phone' => $item['phone'] ?? '',
                        'service' => $item['service'] ?? 'General Outsourcing',
                        'estimated_volume' => $item['estimated_volume'] ?? '',
                        'project_details' => $item['project_details'] ?? '',
                        'status' => $item['status'] ?? 'New',
                        'ip_address' => $item['ip_address'] ?? '127.0.0.1'
                    ];
                }
            }
        }
    }
}

$filename = 'datnix_enquiries_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, [
    'Reference ID',
    'Submission Date (UTC/IST)',
    'Client Full Name',
    'Company Name',
    'Business Email',
    'Phone Number',
    'Service Requested',
    'Estimated Volume',
    'Project Requirements',
    'Status',
    'Client IP'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['reference_no'],
        $row['created_at'],
        $row['full_name'],
        $row['company_name'] ?: 'Not specified',
        $row['email'],
        $row['phone'] ?: 'Not provided',
        $row['service'],
        $row['estimated_volume'] ?: 'Not specified',
        $row['project_details'],
        $row['status'],
        $row['ip_address'] ?? '127.0.0.1'
    ]);
}

fclose($output);
exit;
