<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
?><!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141210">
    <title>Confirm your email | Direwrought Lanternbound</title>
    <link rel="icon" href="assets/direwrought-icon.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="token-page" data-account-mode="verify">
    <main class="token-card" aria-labelledby="token-heading">
        <img class="token-logo" src="assets/direwrought-logo-full.png" alt="Direwrought">
        <p class="eyebrow">Account confirmation</p>
        <h1 id="token-heading">Tempering the final seal</h1>
        <div class="token-status" id="tokenStatus" role="status" aria-live="polite">
            <span class="forge-spinner" aria-hidden="true"></span>
            <p>Confirming your email address...</p>
        </div>
        <a class="button primary token-return" id="tokenReturn" href="index.php" hidden>Enter Lanternbound</a>
    </main>
    <script src="assets/account.js" defer></script>
</body>
</html>
