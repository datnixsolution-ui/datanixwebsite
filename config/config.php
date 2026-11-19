<?php
/**
 * DATNIX SOLUTIONS - Active Application Configuration
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * 
 * NOTE: Production credentials must be supplied via environment variables
 * or edited directly into this file on your deployment server.
 * Insecure defaults (e.g. root with empty password) are blocked by the database layer.
 */

// Auto-detect HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Cryptographically secure, unguessable application secret key
$secretKey = getenv('APP_KEY');
if (empty($secretKey)) {
    $secretFile = __DIR__ . '/.app_key';
    if (file_exists($secretFile)) {
        $secretKey = trim((string)@file_get_contents($secretFile));
    }
    if (empty($secretKey) || strlen($secretKey) < 32) {
        $secretKey = bin2hex(random_bytes(32));
        @file_put_contents($secretFile, $secretKey);
    }
}

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'sql209.infinityfree.com',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'if0_43479474_datnix',
        'username' => getenv('DB_USER') ?: 'if0_43479474',
        'password' => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : 'Datnixs121',
        'charset'  => 'utf8mb4',
    ],

    'app' => [
        'name'        => 'DATNIX SOLUTIONS Business Management System',
        'url'         => getenv('APP_URL') ?: 'http://datnixs.42web.io',
        'admin_url'   => getenv('ADMIN_URL') ?: '/admin',
        'environment' => getenv('APP_ENV') ?: 'production',
        'debug'       => (getenv('APP_DEBUG') === 'true'),
        'secret_key'  => $secretKey,
        'timezone'    => 'Asia/Kolkata',
    ],

    'session' => [
        'name'            => 'DATNIX_ADMIN_SESS',
        'lifetime'        => 7200, // 2 hours
        'idle_timeout'    => 1800, // 30 minutes
        'rotation_time'   => 900,  // 15 minutes automatic session ID regeneration
        'path'            => '/',
        'domain'          => '',
        'secure'          => $isHttps,
        'httponly'        => true,
        'samesite'        => 'Strict',
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_time' => 900, // 15 minutes
        'csrf_token_name'    => 'csrf_token',
        'allowed_resume_ext' => ['pdf', 'doc', 'docx'],
        'allowed_resume_mime'=> [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
        'max_resume_size'    => 5242880, // 5MB limit
    ],

    'paths' => [
        'resumes' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR,
    ],

    'notifications' => [
        'email_enabled'   => false, // Decoupled and disabled per requirements
        'official_email'  => 'datnixsolutions@gmail.com',
        'official_phone'  => '+91 73870 10393',
        'whatsapp_number' => '917387010393',
    ]
];
