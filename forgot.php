<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (hash_equals(csrf_token(), $postedToken)) {
        $email = mb_strtolower(clean_text($_POST['email'] ?? '', 254));
        $rateKey = rate_limit_key('reset:' . $email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && !login_rate_limited($rateKey)) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                send_password_reset_email((int) $userId);
            }
            record_login_attempt($rateKey, false);
        }
        $submitted = true;
    }
}
?><!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141210">
    <title>Reset your password | Direwrought Lanternbound</title>
    <meta name="description" content="Request a secure Direwrought password reset link.">
    <link rel="icon" href="assets/direwrought-icon.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-story auth-story-forge" aria-labelledby="forgot-story-title">
            <a class="brand brand-full" href="https://direwrought.com/" aria-label="Direwrought home">
                <img src="assets/direwrought-logo-full.png" alt="Direwrought, a hammer striking an anvil bound in thorns">
            </a>
            <div class="auth-copy">
                <p class="eyebrow">A key can be reforged</p>
                <h1 id="forgot-story-title">The journey is not lost.</h1>
                <p>Your character, chronicle, threads, and unanswered questions remain sealed until you return.</p>
            </div>
        </section>
        <section class="auth-card" aria-labelledby="forgot-title">
            <div>
                <p class="eyebrow">Account recovery</p>
                <h2 id="forgot-title">Request a reset link</h2>
                <p class="muted">Enter the email used for your Direwrought account.</p>
            </div>
            <?php if ($submitted): ?>
                <div class="success-panel" role="status">
                    <h3>Check your inbox</h3>
                    <p>If an account matches and email delivery is configured, a one time link will arrive. It expires in one hour.</p>
                </div>
            <?php else: ?>
                <form method="post" action="forgot.php" class="auth-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>Email
                        <input name="email" type="email" autocomplete="email" maxlength="254" required>
                    </label>
                    <button class="button primary wide" type="submit">Send the reset link</button>
                </form>
            <?php endif; ?>
            <p class="auth-switch"><a href="index.php">Return to sign in</a></p>
        </section>
    </main>
</body>
</html>
