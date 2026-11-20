<?php
/**
 * DATNIX SOLUTIONS - Configuration Template
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to config.php in production if not already present.
 * 2. Configure with your actual production MySQL credentials.
 * 3. Never use 'root' with an empty password in production (strictly blocked).
 */

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'YOUR_PRODUCTION_DATABASE_NAME',
        'username' => getenv('DB_USER') ?: 'YOUR_PRODUCTION_DATABASE_USER',
        'password' => getenv('DB_PASS') ?: 'YOUR_STRONG_DATABASE_PASSWORD',
        'charset'  => 'utf8mb4',
    ],

    'app' => [
        'name'        => 'DATNIX SOLUTIONS Business Management System',
        'url'         => getenv('APP_URL') ?: 'https://datnixs.42web.io',
        'admin_url'   => getenv('ADMIN_URL') ?: 'https://datnixs.42web.io/admin',
        'environment' => getenv('APP_ENV') ?: 'production',
        'debug'       => false,
        'secret_key'  => getenv('APP_KEY') ?: '', // Leave empty to auto-generate secure persistent key
        'timezone'    => 'Asia/Kolkata',
    ],

    'session' => [
        'name'            => 'DATNIX_ADMIN_SESS',
        'lifetime'        => 7200, // 2 hours
        'idle_timeout'    => 1800, // 30 minutes
        'path'            => '/',
        'domain'          => '',
        'secure'          => true,
        'httponly'        => true,
        'samesite'        => 'Lax',
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
        'max_resume_size'    => 5242880, // 5MB in bytes
    ],

    'paths' => [
        'resumes' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes' . DIRECTORY_SEPARATOR,
    ],

    'notifications' => [
        'email_enabled'   => false, // Keep disabled until production email credentials provided
        'official_email'  => 'datnixsolution@gmail.com',
        'official_phone'  => '+91 73870 10393',
        'whatsapp_number' => '917387010393',
    ]
];
