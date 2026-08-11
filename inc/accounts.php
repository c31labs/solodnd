<?php
declare(strict_types=1);

function app_url(string $path = ''): string
{
    global $config;
    $base = rtrim((string) $config['app_url'], '/');
    return $base . '/' . ltrim($path, '/');
}

function mail_is_configured(): bool
{
    global $config;
    $scheme = parse_url((string) $config['app_url'], PHP_URL_SCHEME);
    return function_exists('curl_init')
        && (string) $config['mail']['brevo_api_key'] !== ''
        && filter_var($config['mail']['sender_email'], FILTER_VALIDATE_EMAIL)
        && filter_var($config['app_url'], FILTER_VALIDATE_URL)
        && in_array($scheme, ['http', 'https'], true);
}

function brevo_send_email(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlContent,
    string $textContent,
    string $tag
): bool {
    global $config;
    if (!mail_is_configured() || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $payload = [
        'sender' => [
            'email' => (string) $config['mail']['sender_email'],
            'name' => (string) $config['mail']['sender_name'],
        ],
        'to' => [[
            'email' => $recipientEmail,
            'name' => $recipientName,
        ]],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
        'textContent' => $textContent,
        'tags' => [$tag],
    ];
    if (filter_var($config['mail']['reply_to'], FILTER_VALIDATE_EMAIL)) {
        $payload['replyTo'] = ['email' => (string) $config['mail']['reply_to']];
    }

    $handle = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($handle === false) {
        return false;
    }
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . (string) $config['mail']['brevo_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_errno($handle);
    curl_close($handle);

    if ($curlError !== 0 || $status !== 201) {
        error_log('Direwrought transactional email failed with HTTP status ' . $status . '.');
        return false;
    }
    return true;
}

function email_shell(string $preheader, string $heading, string $body, string $buttonLabel, string $buttonUrl): string
{
    $safePreheader = e($preheader);
    $safeHeading = e($heading);
    $safeBody = nl2br(e($body));
    $safeButtonLabel = e($buttonLabel);
    $safeButtonUrl = e($buttonUrl);
    return '<!doctype html><html><body style="margin:0;background:#141210;color:#e8e0d2;font-family:Georgia,serif">'
        . '<span style="display:none;max-height:0;overflow:hidden">' . $safePreheader . '</span>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#141210"><tr><td align="center" style="padding:32px 16px">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#1c1916;border:1px solid #35302a">'
        . '<tr><td style="padding:28px 32px;border-bottom:2px solid #d64518;color:#f05e2a;font-family:Georgia,serif;font-size:14px;letter-spacing:3px;text-transform:uppercase">DIREWROUGHT</td></tr>'
        . '<tr><td style="padding:36px 32px"><h1 style="margin:0 0 18px;color:#e8e0d2;font-size:30px;font-weight:normal">' . $safeHeading . '</h1>'
        . '<p style="margin:0 0 28px;color:#b7ad9c;font-size:17px;line-height:1.65">' . $safeBody . '</p>'
        . '<p style="margin:0"><a href="' . $safeButtonUrl . '" style="display:inline-block;padding:14px 22px;background:#d64518;color:#ffffff;text-decoration:none;font-weight:bold;letter-spacing:1px">' . $safeButtonLabel . '</a></p>'
        . '<p style="margin:28px 0 0;color:#746b5e;font-size:13px;line-height:1.5">If the button does not open, copy this address into your browser:<br>' . $safeButtonUrl . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function create_auth_token(int $userId, string $purpose, int $lifetimeSeconds): string
{
    if (!in_array($purpose, ['verify_email', 'password_reset'], true)) {
        throw new InvalidArgumentException('Invalid account token purpose.');
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $lifetimeSeconds);
    $now = now_utc();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $remove = $pdo->prepare('DELETE FROM auth_tokens WHERE user_id = ? AND purpose = ?');
        $remove->execute([$userId, $purpose]);
        $insert = $pdo->prepare('INSERT INTO auth_tokens (user_id, purpose, token_hash, expires_at, used_at, created_at) VALUES (?, ?, ?, ?, NULL, ?)');
        $insert->execute([$userId, $purpose, $tokenHash, $expiresAt, $now]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    return $rawToken;
}

function auth_token_record(string $rawToken, string $purpose): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $rawToken)) {
        return null;
    }
    $stmt = db()->prepare('SELECT t.id AS token_id, t.user_id, u.email, u.display_name FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at >= ? LIMIT 1');
    $stmt->execute([hash('sha256', $rawToken), $purpose, now_utc()]);
    $record = $stmt->fetch();
    return $record ?: null;
}

function send_verification_email(int $userId): bool
{
    $stmt = db()->prepare('SELECT email, display_name, email_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['email_verified_at']) {
        return false;
    }
    $rawToken = create_auth_token($userId, 'verify_email', 86400);
    $url = app_url('verify.php#token=' . rawurlencode($rawToken));
    $body = 'Welcome to Direwrought, ' . $user['display_name'] . ".\n\nConfirm that this email belongs to you. This link expires in twenty four hours and can be used only once.";
    return brevo_send_email(
        (string) $user['email'],
        (string) $user['display_name'],
        'Confirm your Direwrought journey',
        email_shell('Confirm your Direwrought account', 'Temper the final seal', $body, 'Confirm my email', $url),
        $body . "\n\nConfirm your email: " . $url,
        'account-verification'
    );
}

function send_password_reset_email(int $userId): bool
{
    $stmt = db()->prepare('SELECT email, display_name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }
    $rawToken = create_auth_token($userId, 'password_reset', 3600);
    $url = app_url('reset.php#token=' . rawurlencode($rawToken));
    $body = 'A password reset was requested for your Direwrought account. This link expires in one hour and can be used only once. If you did not request it, your existing password remains unchanged.';
    return brevo_send_email(
        (string) $user['email'],
        (string) $user['display_name'],
        'Reset your Direwrought password',
        email_shell('Reset your Direwrought password', 'Reforge your key', $body, 'Choose a new password', $url),
        $body . "\n\nChoose a new password: " . $url,
        'password-reset'
    );
}

function send_deletion_receipt(string $email, string $displayName): bool
{
    $body = 'Your Direwrought account and its stored journeys have been permanently deleted. No action is required. You are welcome at the forge again whenever the road turns back.';
    return brevo_send_email(
        $email,
        $displayName,
        'Your Direwrought account was deleted',
        email_shell('Direwrought account deletion confirmed', 'The ledger is closed', $body, 'Return to Direwrought', app_url()),
        $body . "\n\n" . app_url(),
        'account-deletion'
    );
}
