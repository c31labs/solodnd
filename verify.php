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
    <link rel="stylesheet" href="../assets/site-nav.css?v=1">
    <link rel="stylesheet" href="assets/site-nav-bridge.css?v=1">
</head>
<body class="token-page" data-account-mode="verify">
    <header class="direwrought-nav">
    <a class="direwrought-nav-logo" href="/" aria-label="Direwrought home"><img src="/assets/direwrought-logo-full.png" alt=""></a>
    <nav aria-label="Direwrought site">
      <a href="/#adventures">Adventures</a>
      <a href="/#soundtrack">Soundtrack</a>
      <a href="/lanternbound/">Lanternbound</a>
      <a href="/adventurers-league/">AL Ledger</a>
      <a href="/about/">About</a>
      <a href="https://www.youtube.com/@unhallowedechoes" aria-label="Unhallowed Echoes on YouTube">YouTube</a>
    </nav>
  </header>
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
