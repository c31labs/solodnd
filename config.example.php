<?php
declare(strict_types=1);

/**
 * Example configuration only.
 *
 * Copy this file to config.php only in an authorised deployment. Never commit
 * config.php, credentials, API keys, or production database details.
 */
return [
    'environment' => 'production',
    'app_url' => 'https://example.com/lanternbound',
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=lanternbound;charset=utf8mb4',
        'user' => 'lanternbound_user',
        'password' => 'replace-with-a-long-random-password',
    ],
    'mail' => [
        'brevo_api_key' => 'replace-with-a-private-brevo-api-key',
        'sender_email' => 'accounts@example.com',
        'sender_name' => 'Direwrought',
        'reply_to' => 'support@example.com',
    ],
    'auto_migrate' => false,
    'session_idle_minutes' => 120,
];
