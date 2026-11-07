<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
?><!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141210">
    <title>Choose a new password | Direwrought Lanternbound</title>
    <link rel="icon" href="assets/direwrought-icon.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="../assets/site-nav.css?v=1">
    <link rel="stylesheet" href="assets/site-nav-bridge.css?v=1">
</head>
<body class="token-page" data-account-mode="reset">
    <header class="direwrought-nav">
    <a class="direwrought-nav-logo" href="/" aria-label="Direwrought home"><img src="/assets/direwrought-logo-full.png" alt=""></a>
    <nav aria-label="Direwrought site">
      <a href="/#adventures">Adventures</a>
      <a href="/#soundtrack">Soundtrack</a>
      <a href="/lanternbound/">Lanternbound</a>
      <a href="/adventurers-league/">AL Ledger</a>
      <a href="https://www.youtube.com/@unhallowedechoes" aria-label="Unhallowed Echoes on YouTube">YouTube</a>
    </nav>
  </header>
    <main class="token-card" aria-labelledby="reset-heading">
        <img class="token-logo" src="assets/direwrought-logo-full.png" alt="Direwrought">
        <p class="eyebrow">Account recovery</p>
        <h1 id="reset-heading">Reforge your key</h1>
        <p class="muted">Use at least twelve characters. Completing this reset ends every earlier session.</p>
        <form id="resetForm" class="auth-form">
            <label>New password
                <input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="200" required>
            </label>
            <label>Confirm new password
                <input name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="200" required>
            </label>
            <button class="button primary wide" type="submit">Set the new password</button>
        </form>
        <div class="token-status" id="tokenStatus" role="status" aria-live="polite"></div>
        <a class="button secondary token-return" id="tokenReturn" href="index.php" hidden>Return to sign in</a>
    </main>
    <script src="assets/account.js" defer></script>
</body>
</html>
