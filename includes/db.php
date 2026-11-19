<?php
/**
 * DATNIX SOLUTIONS - Shared Database Helper
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    // If running in CLI / offline testing, $pdo can be null
    $pdo = null;
}
