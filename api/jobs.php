<?php
/**
 * DATNIX SOLUTIONS - Public Jobs API
 * Endpoint: /api/jobs.php
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300'); // Cache for 5 mins

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT id, title, slug, department, location, employment_type, experience_req, short_description, responsibilities, required_skills, benefits, created_at 
        FROM jobs 
        WHERE status = "Active" AND is_published = 1 
        ORDER BY created_at DESC
    ');
    $stmt->execute();
    $jobs = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($jobs),
        'jobs'    => $jobs
    ]);
} catch (Throwable $e) {
    error_log('[PUBLIC JOBS API ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Unable to load current career listings at this time.'
    ]);
}
