<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

reject_cross_site_write();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Unknown request.'], 404);
}

$body = json_body();
$action = clean_text($body['action'] ?? '', 40);

if ($action === 'verify_email') {
    $rawToken = clean_text($body['token'] ?? '', 64);
    $record = auth_token_record($rawToken, 'verify_email');
    if (!$record) {
        json_response(['error' => 'This confirmation link is invalid or has expired. Request a new one from Account settings.'], 422);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $mark = $pdo->prepare('UPDATE auth_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $mark->execute([now_utc(), $record['token_id']]);
        if ($mark->rowCount() !== 1) {
            throw new RuntimeException('The account token has already been used.');
        }
        $verify = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?');
        $verify->execute([now_utc(), $record['user_id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        json_response(['error' => 'This confirmation could not be completed. Request a new link.'], 422);
    }
    json_response(['ok' => true, 'message' => 'Your email is confirmed. The forge now knows your seal.']);
}

if ($action === 'reset_password') {
    $rawToken = clean_text($body['token'] ?? '', 64);
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    $confirm = is_string($body['confirm_password'] ?? null) ? $body['confirm_password'] : '';
    if (strlen($password) < 12 || strlen($password) > 200) {
        json_response(['error' => 'Use a password between twelve and two hundred characters.'], 422);
    }
    if (!hash_equals($password, $confirm)) {
        json_response(['error' => 'The passwords do not match.'], 422);
    }
    $record = auth_token_record($rawToken, 'password_reset');
    if (!$record) {
        json_response(['error' => 'This reset link is invalid or has expired. Request a new one.'], 422);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $mark = $pdo->prepare('UPDATE auth_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $mark->execute([now_utc(), $record['token_id']]);
        if ($mark->rowCount() !== 1) {
            throw new RuntimeException('The account token has already been used.');
        }
        $reset = $pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?');
        $reset->execute([password_hash($password, password_algorithm()), $record['user_id']]);
        $expire = $pdo->prepare("UPDATE auth_tokens SET used_at = COALESCE(used_at, ?) WHERE user_id = ? AND purpose = 'password_reset'");
        $expire->execute([now_utc(), $record['user_id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        json_response(['error' => 'Your password could not be reset. Request a new link.'], 422);
    }
    json_response(['ok' => true, 'message' => 'Your password has been reforged. All earlier account sessions have ended.']);
}

$userId = require_auth();
require_csrf();

if ($action === 'resend_verification') {
    $stmt = db()->prepare('SELECT email, email_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        json_response(['error' => 'Please sign in again.'], 401);
    }
    if ($user['email_verified_at']) {
        json_response(['ok' => true, 'message' => 'Your email is already confirmed.']);
    }
    if (!mail_is_configured()) {
        json_response(['error' => 'Transactional email is not configured on this server yet.'], 503);
    }
    $rateKey = rate_limit_key('verify:' . (string) $user['email']);
    if (login_rate_limited($rateKey)) {
        json_response(['error' => 'Too many confirmation emails were requested. Wait fifteen minutes and try again.'], 429);
    }
    $sent = send_verification_email($userId);
    record_login_attempt($rateKey, false);
    if (!$sent) {
        json_response(['error' => 'The confirmation email could not be sent. Try again shortly.'], 502);
    }
    json_response(['ok' => true, 'message' => 'A fresh confirmation link is on its way.']);
}

if ($action === 'delete_account') {
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';
    $confirmation = clean_text($body['confirmation'] ?? '', 40);
    if ($confirmation !== 'DELETE MY ACCOUNT') {
        json_response(['error' => 'Type DELETE MY ACCOUNT exactly to confirm.'], 422);
    }
    $stmt = db()->prepare('SELECT email, display_name, password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        json_response(['error' => 'The current password was not recognised.'], 422);
    }

    $delete = db()->prepare('DELETE FROM users WHERE id = ?');
    $delete->execute([$userId]);
    if ($delete->rowCount() !== 1) {
        json_response(['error' => 'The account could not be deleted.'], 500);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Clear-Site-Data: "cache", "cookies", "storage"');
    send_deletion_receipt((string) $user['email'], (string) $user['display_name']);
    json_response(['ok' => true, 'message' => 'Your account and stored journeys were permanently deleted.', 'redirect' => 'index.php']);
}

json_response(['error' => 'Unknown account action.'], 404);
