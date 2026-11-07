<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$authError = '';
$authMode = ($_GET['view'] ?? '') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (!hash_equals(csrf_token(), $postedToken)) {
        $authError = 'Your session check failed. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
            header('Clear-Site-Data: "cache", "cookies", "storage"');
            header('Location: index.php');
            exit;
        }

        if ($action === 'login') {
            $authMode = 'login';
            $email = mb_strtolower(clean_text($_POST['email'] ?? '', 254));
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $rateKey = rate_limit_key($email);
            if (login_rate_limited($rateKey)) {
                $authError = 'Too many attempts. Wait fifteen minutes before trying again.';
            } else {
                $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                $valid = $user && password_verify($password, (string) $user['password_hash']);
                record_login_attempt($rateKey, (bool) $valid);
                if ($valid) {
                    if (password_needs_rehash((string) $user['password_hash'], password_algorithm())) {
                        $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        $rehash->execute([password_hash($password, password_algorithm()), $user['id']]);
                    }
                    $update = db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
                    $update->execute([now_utc(), $user['id']]);
                    login_user((int) $user['id']);
                    header('Location: index.php');
                    exit;
                }
                $authError = 'The email or password was not recognised.';
            }
        }

        if ($action === 'register') {
            $authMode = 'register';
            $displayName = clean_text($_POST['display_name'] ?? '', 60);
            $email = mb_strtolower(clean_text($_POST['email'] ?? '', 254));
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            $rateKey = rate_limit_key($email);

            if (login_rate_limited($rateKey)) {
                $authError = 'Too many attempts. Wait fifteen minutes before trying again.';
            } elseif (mb_strlen($displayName) < 2) {
                $authError = 'Choose a name with at least two characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $authError = 'Enter a valid email address.';
            } elseif (strlen($password) < 12 || strlen($password) > 200) {
                $authError = 'Use a password between twelve and two hundred characters.';
            } elseif (!hash_equals($password, $confirm)) {
                $authError = 'The passwords do not match.';
            } else {
                try {
                    $hash = password_hash($password, password_algorithm());
                    $stmt = db()->prepare('INSERT INTO users (email, display_name, password_hash, created_at, last_login_at) VALUES (?, ?, ?, ?, ?)');
                    $now = now_utc();
                    $stmt->execute([$email, $displayName, $hash, $now, $now]);
                    $userId = (int) db()->lastInsertId();
                    seed_adventure($userId, $displayName);
                    record_login_attempt($rateKey, true);
                    login_user($userId);
                    $mailConfigured = mail_is_configured();
                    try {
                        $verificationSent = send_verification_email($userId);
                    } catch (Throwable $mailError) {
                        error_log('Direwrought verification setup failed after registration.');
                        $verificationSent = false;
                    }
                    if ($verificationSent) {
                        $_SESSION['notice'] = 'Your journey is ready. We sent a verification link to your email.';
                    } elseif ($mailConfigured) {
                        $_SESSION['notice'] = 'Your journey is ready. The email could not be sent, so please retry from Account.';
                    } else {
                        $_SESSION['notice'] = 'Your journey is ready. Email delivery will begin once Direwrought mail is connected.';
                    }
                    header('Location: index.php');
                    exit;
                } catch (Throwable) {
                    record_login_attempt($rateKey, false);
                    $authError = 'An account could not be created with those details.';
                }
            }
        }
    }
}

$userId = current_user_id();
$notice = is_string($_SESSION['notice'] ?? null) ? $_SESSION['notice'] : '';
unset($_SESSION['notice']);
?><!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141210">
    <title><?= $userId ? 'Your journey | Direwrought Lanternbound' : 'Direwrought Lanternbound | A free solo fantasy journey' ?></title>
    <meta name="description" content="A free and private fifth edition compatible solo fantasy roleplaying companion from Direwrought.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="https://direwrought.com/lanternbound/index.php">

    <meta property="og:locale" content="en_AU">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Direwrought">
    <meta property="og:title" content="Lanternbound | A Free Solo Fantasy Adventure">
    <meta property="og:description" content="Create a hero, follow the bell into Briarwatch, and let the Oracle turn every choice into your private story.">
    <meta property="og:url" content="https://direwrought.com/lanternbound/index.php">
    <meta property="og:image" content="https://direwrought.com/lanternbound/assets/lanternbound-social.jpg">
    <meta property="og:image:secure_url" content="https://direwrought.com/lanternbound/assets/lanternbound-social.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="A lone hero facing the moonlit bell tower of Briarwatch">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Lanternbound | A Free Solo Fantasy Adventure">
    <meta name="twitter:description" content="Create a hero, follow the bell into Briarwatch, and let the Oracle turn every choice into your private story.">
    <meta name="twitter:image" content="https://direwrought.com/lanternbound/assets/lanternbound-social.jpg">
    <meta name="twitter:image:alt" content="A lone hero facing the moonlit bell tower of Briarwatch">

    <link rel="image_src" href="https://direwrought.com/lanternbound/assets/lanternbound-social.jpg">
    <link rel="icon" href="assets/direwrought-icon.png" type="image/png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="../assets/site-nav.css?v=1">
    <link rel="stylesheet" href="assets/site-nav-bridge.css?v=1">
</head>
<?php if (!$userId): ?>
<body class="auth-page">
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
    <main class="auth-shell">
        <section class="auth-story" aria-labelledby="welcome-title">
            <a class="auth-brand" href="https://direwrought.com/" aria-label="Direwrought home">
                <img src="assets/direwrought-logo-full.png" alt="Direwrought">
                <span>Lanternbound solo adventures</span>
            </a>
            <div class="auth-copy">
                <p class="eyebrow">Your table is waiting</p>
                <h1 id="welcome-title">A world awakens when you ask.</h1>
                <p>Create a hero, follow the bell into Briarwatch, and let the Oracle answer the questions a Game Master once held.</p>
                <ul class="promise-list">
                    <li>One private journey for every player</li>
                    <li>Character, story, Oracle, and dice together</li>
                    <li>No subscription and no paid narrator required</li>
                </ul>
            </div>
            <p class="art-note">A free solo adventure engine forged by Direwrought</p>
        </section>
        <section class="auth-card" aria-labelledby="auth-title">
            <div>
                <p class="eyebrow">Begin tonight</p>
                <h2 id="auth-title"><?= $authMode === 'register' ? 'Create your account' : 'Return to your journey' ?></h2>
                <p class="muted"><?= $authMode === 'register' ? 'Your first adventure will be waiting as soon as you enter.' : 'Your journal remembers where the road last left you.' ?></p>
            </div>
            <?php if ($authError): ?>
                <div class="alert" role="alert"><?= e($authError) ?></div>
            <?php endif; ?>
            <?php if ($authMode === 'register'): ?>
                <form method="post" action="index.php?view=register" class="auth-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="register">
                    <label>Hero name
                        <input name="display_name" autocomplete="nickname" minlength="2" maxlength="60" required value="<?= e($_POST['display_name'] ?? '') ?>">
                    </label>
                    <label>Email
                        <input name="email" type="email" autocomplete="email" maxlength="254" required value="<?= e($_POST['email'] ?? '') ?>">
                    </label>
                    <label>Password
                        <input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="200" required aria-describedby="password-help">
                    </label>
                    <p class="field-help" id="password-help">Use at least twelve characters. A short phrase is welcome.</p>
                    <label>Confirm password
                        <input name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="200" required>
                    </label>
                    <button class="button primary wide" type="submit">Open the first page</button>
                </form>
                <p class="auth-switch">Already travelling? <a href="index.php">Sign in</a></p>
            <?php else: ?>
                <form method="post" action="index.php" class="auth-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>Email
                        <input name="email" type="email" autocomplete="email" maxlength="254" required value="<?= e($_POST['email'] ?? '') ?>">
                    </label>
                    <label>Password
                        <input name="password" type="password" autocomplete="current-password" maxlength="200" required>
                    </label>
                    <button class="button primary wide" type="submit">Continue the journey</button>
                </form>
                <p class="auth-assist"><a href="forgot.php">Forgot your password?</a></p>
                <p class="auth-switch">New to Lanternbound? <a href="index.php?view=register">Create an account</a></p>
            <?php endif; ?>
            <p class="legal-small">Free to play through Direwrought. Fifth edition compatible. See the licence notes for source credits.</p>
            <nav class="auth-resource-links" aria-label="Help and privacy">
                <a href="how-to-play.html">How to play</a>
                <a href="privacy.html">Privacy and cookies</a>
            </nav>
        </section>
    </main>
    <aside class="cookie-notice" id="cookieNotice" hidden role="region" aria-label="Cookie notice">
        <div>
            <p class="eyebrow">Essential cookies only</p>
            <p>Lanternbound uses one essential session cookie to keep you signed in and protect your journey. There are no advertising, analytics, or tracking cookies.</p>
        </div>
        <div class="cookie-actions">
            <a href="privacy.html">Privacy and cookies</a>
            <button class="button secondary" type="button" id="dismissCookieNotice">Understood</button>
        </div>
    </aside>
    <script src="assets/app.js" defer></script>
</body>
<?php else: ?>
<body class="app-page" data-csrf="<?= e(csrf_token()) ?>" data-notice="<?= e($notice) ?>">
    <div class="lantern-masthead">
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
    <header class="topbar">
        <a class="brand" href="index.php" aria-label="Direwrought Lanternbound journey home">
            <img class="brand-icon" src="assets/direwrought-icon.png" alt="">
            <span class="brand-words"><strong>Direwrought</strong><small>Lanternbound</small></span>
        </a>
        <div class="journey-heading">
            <span id="adventureTitle">Loading your journey</span>
            <span class="scene-chip" id="sceneChip">Scene 1</span>
        </div>
        <nav class="top-actions" aria-label="Account and reference">
            <button class="text-button" type="button" id="openCompendium">Compendium</button>
            <a class="text-button" href="api.php?action=export">Export</a>
            <a class="text-button" href="how-to-play.html">Guide</a>
            <button class="text-button" type="button" id="openAccount">Account</button>
            <form method="post" action="index.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="text-button" type="submit">Sign out</button>
            </form>
        </nav>
    </header>
    </div>

    <section class="email-banner" id="emailBanner" hidden aria-label="Email verification">
        <span><strong>Protect your journey.</strong> Verify your email so you can recover access later.</span>
        <button class="text-button" type="button" id="bannerVerify">Send verification email</button>
    </section>

    <div class="mobile-tabs" role="tablist" aria-label="Journey panels">
        <button class="mobile-tab active" type="button" data-panel="story">Adventure</button>
        <button class="mobile-tab" type="button" data-panel="character">Character</button>
        <button class="mobile-tab" type="button" data-panel="oracle">Oracle</button>
    </div>

    <main class="game-grid" id="gameRoot" aria-busy="true">
        <aside class="game-panel character-panel" data-panel-name="character" aria-labelledby="character-heading">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">The hero</p>
                    <h2 id="character-heading">Character</h2>
                </div>
                <span class="save-state" id="saveState">Ready</span>
            </div>
            <form id="characterForm" class="character-form">
                <label class="full">Name
                    <input name="name" maxlength="80" required>
                </label>
                <label>Class
                    <select name="class_name" id="classSelect"></select>
                </label>
                <label>Species
                    <select name="species" id="speciesSelect"></select>
                </label>
                <label>Background
                    <select name="background" id="backgroundSelect"></select>
                </label>
                <label>Alignment
                    <select name="alignment" id="alignmentSelect"></select>
                </label>
                <label>Level
                    <input name="level" type="number" min="1" max="20">
                </label>
                <label>Experience
                    <input name="xp" type="number" min="0" max="9999999">
                </label>
                <div class="vital-row full">
                    <label>Current HP
                        <input name="hp_current" type="number" min="0" max="999">
                    </label>
                    <span class="vital-divider">of</span>
                    <label>Maximum HP
                        <input name="hp_max" type="number" min="1" max="999">
                    </label>
                    <label>Armour
                        <input name="armor_class" type="number" min="1" max="40">
                    </label>
                    <label>Speed
                        <input name="speed" type="number" min="0" max="200">
                    </label>
                </div>
                <fieldset class="ability-grid full">
                    <legend>Ability scores</legend>
                    <label>Strength<input name="strength" type="number" min="1" max="30"><output data-mod="strength">+0</output></label>
                    <label>Dexterity<input name="dexterity" type="number" min="1" max="30"><output data-mod="dexterity">+0</output></label>
                    <label>Constitution<input name="constitution" type="number" min="1" max="30"><output data-mod="constitution">+0</output></label>
                    <label>Intelligence<input name="intelligence" type="number" min="1" max="30"><output data-mod="intelligence">+0</output></label>
                    <label>Wisdom<input name="wisdom" type="number" min="1" max="30"><output data-mod="wisdom">+0</output></label>
                    <label>Charisma<input name="charisma" type="number" min="1" max="30"><output data-mod="charisma">+0</output></label>
                </fieldset>
                <section class="feature-card full" aria-labelledby="features-title">
                    <p class="eyebrow" id="features-title">Level one features</p>
                    <div id="classFeatures" class="tag-list"></div>
                </section>
                <label class="full">Notes
                    <textarea name="notes" rows="4" maxlength="4000"></textarea>
                </label>
                <button class="button secondary full" type="submit">Save character</button>
            </form>
        </aside>

        <section class="game-panel story-panel active" data-panel-name="story" aria-labelledby="story-heading">
            <div class="story-hero">
                <div>
                    <p class="eyebrow">The living chronicle</p>
                    <h1 id="story-heading">The Bell Beneath Briarwatch</h1>
                    <p>What happens next belongs to your choices and the questions you dare to ask.</p>
                </div>
            </div>
            <section class="story-guide" id="storyGuide" aria-labelledby="storyGuideTitle" aria-live="polite">
                <div class="story-guide-loading">
                    <p class="eyebrow">The story guide</p>
                    <h2 id="storyGuideTitle">Opening the next page</h2>
                    <p>The road is remembering where it wanted you to go.</p>
                </div>
            </section>
            <section class="composer" aria-labelledby="journal-heading">
                <div class="panel-heading compact">
                    <div>
                        <p class="eyebrow">Write the next truth</p>
                        <h2 id="journal-heading">Adventure journal</h2>
                    </div>
                </div>
                <form id="journalForm">
                    <div class="composer-line">
                        <select name="entry_type" aria-label="Entry type">
                            <option value="narrative">Narrative</option>
                            <option value="action">Hero action</option>
                            <option value="dialogue">Dialogue</option>
                            <option value="discovery">Discovery</option>
                        </select>
                        <input name="title" placeholder="Moment title" maxlength="140" required>
                    </div>
                    <textarea name="body" rows="4" maxlength="12000" placeholder="Describe what your hero does, what the world reveals, or what changes..." required></textarea>
                    <div class="composer-actions">
                        <span class="muted">Saved to your private journey</span>
                        <button class="button primary" type="submit">Add to chronicle</button>
                    </div>
                </form>
            </section>
            <div class="timeline" id="journalTimeline" aria-live="polite"></div>
        </section>

        <aside class="game-panel oracle-panel" data-panel-name="oracle" aria-labelledby="oracle-heading">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Ask the unknown</p>
                    <h2 id="oracle-heading">The Oracle</h2>
                </div>
                <div class="chaos-control" aria-label="Chaos factor">
                    <button type="button" id="chaosDown" aria-label="Reduce chaos">−</button>
                    <span><small>Chaos</small><strong id="chaosValue">5</strong></span>
                    <button type="button" id="chaosUp" aria-label="Increase chaos">+</button>
                </div>
            </div>
            <form id="oracleForm" class="oracle-form">
                <label>Ask a clear yes or no question
                    <textarea name="question" rows="3" maxlength="300" placeholder="Is the child beneath the umbrella waiting for me?" required></textarea>
                </label>
                <label>How likely is yes?
                    <select name="odds">
                        <option value="impossible">Nearly impossible</option>
                        <option value="unlikely">Unlikely</option>
                        <option value="even" selected>Even chance</option>
                        <option value="likely">Likely</option>
                        <option value="certain">Nearly certain</option>
                    </select>
                </label>
                <button class="button oracle-button" type="submit">Ask the Oracle</button>
            </form>
            <section class="oracle-result empty" id="oracleResult" aria-live="assertive">
                <span class="result-sigil">?</span>
                <div>
                    <p class="eyebrow">The answer waits</p>
                    <h3>Ask when the world, not your hero, decides.</h3>
                </div>
            </section>

            <section class="tool-section" aria-labelledby="scene-title">
                <div class="section-title-row">
                    <h3 id="scene-title">Open a scene</h3>
                    <span class="help-tip" title="High chaos makes alterations and interruptions more likely.">?</span>
                </div>
                <form id="sceneForm">
                    <textarea name="expectation" rows="2" maxlength="400" placeholder="I expect to reach the tower without being seen." required></textarea>
                    <button class="button secondary wide" type="submit">Test the expectation</button>
                </form>
                <div id="sceneResult" class="mini-result" aria-live="polite"></div>
            </section>

            <section class="tool-section meaning-section" aria-labelledby="meaning-title">
                <div class="section-title-row">
                    <h3 id="meaning-title">Meaning spark</h3>
                    <button class="text-button" type="button" id="meaningButton">Draw two words</button>
                </div>
                <div class="meaning-words" id="meaningWords"><span>Listen</span><span>Threshold</span></div>
                <p class="muted">Interpret the pair through what is already true in your story.</p>
            </section>

            <section class="tool-section" aria-labelledby="threads-title">
                <h3 id="threads-title">Threads</h3>
                <form class="inline-add" id="threadForm">
                    <input name="name" maxlength="180" placeholder="Add an unresolved goal" required>
                    <button type="submit" aria-label="Add thread">+</button>
                </form>
                <ul class="tracker-list" id="threadList"></ul>
            </section>

            <section class="tool-section" aria-labelledby="npcs-title">
                <h3 id="npcs-title">Characters met</h3>
                <form class="inline-add" id="npcForm">
                    <input name="name" maxlength="100" placeholder="Add a person or creature" required>
                    <button type="submit" aria-label="Add character">+</button>
                </form>
                <ul class="tracker-list" id="npcList"></ul>
            </section>
        </aside>
    </main>

    <section class="dice-dock" aria-label="Dice roller">
        <span class="dice-label">Roll</span>
        <div class="dice-buttons">
            <button type="button" data-die="4">d4</button>
            <button type="button" data-die="6">d6</button>
            <button type="button" data-die="8">d8</button>
            <button type="button" data-die="10">d10</button>
            <button type="button" data-die="12">d12</button>
            <button type="button" data-die="20" class="d20">d20</button>
            <button type="button" data-die="100">d100</button>
        </div>
        <label class="modifier-label">Modifier
            <input id="diceModifier" type="number" min="-20" max="20" value="0">
        </label>
        <output class="dice-output" id="diceOutput">Ready</output>
    </section>

    <dialog id="compendiumDialog" class="compendium-dialog">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">Open rules at the table</p>
                <h2>5E compatible compendium</h2>
            </div>
            <button class="dialog-close" type="button" id="closeCompendium" aria-label="Close compendium">×</button>
        </div>
        <p class="muted">This first release includes the open character options needed to begin at level one. The full searchable SRD compendium is the next expansion.</p>
        <div class="compendium-tabs" role="tablist">
            <button class="active" type="button" data-reference="classes">Classes</button>
            <button type="button" data-reference="species">Species</button>
            <button type="button" data-reference="backgrounds">Backgrounds</button>
            <button type="button" data-reference="solo">Solo play</button>
        </div>
        <div id="compendiumContent" class="compendium-content"></div>
        <footer class="dialog-footer">
            <p>Rules material is drawn from SRD 5.2.1 under CC BY 4.0. Solo concepts are credited in the licence notes.</p>
        </footer>
    </dialog>

    <dialog id="accountDialog" class="compendium-dialog account-dialog">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">Your Direwrought journey</p>
                <h2>Account</h2>
            </div>
            <button class="dialog-close" type="button" id="closeAccount" aria-label="Close account settings">×</button>
        </div>
        <div class="account-content">
            <section class="account-section">
                <p class="account-label">Email</p>
                <p class="account-email" id="accountEmail"></p>
                <div class="verification-row">
                    <span class="status-pill" id="verificationStatus">Checking status</span>
                    <button class="button secondary" type="button" id="resendVerification">Send verification email</button>
                </div>
                <p class="muted">Verification protects password recovery. Your current adventure remains available while you confirm the address.</p>
                <form method="post" action="index.php" class="account-signout">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="button secondary" type="submit">Sign out of this session</button>
                </form>
            </section>
            <section class="account-section account-resources">
                <p class="eyebrow">Help and privacy</p>
                <div class="resource-links">
                    <a class="button secondary" href="how-to-play.html">How to play</a>
                    <a class="button secondary" href="privacy.html">Privacy and cookies</a>
                </div>
            </section>
            <section class="account-section danger-zone">
                <p class="eyebrow">Permanent deletion</p>
                <h3>Delete this account and its journey</h3>
                <p class="muted">This removes your character, chronicle, Oracle history, threads, and people met. Export first if you want to keep a copy.</p>
                <form id="deleteAccountForm" class="delete-account-form">
                    <label>Current password
                        <input name="password" type="password" autocomplete="current-password" maxlength="200" required>
                    </label>
                    <label>Type DELETE MY ACCOUNT
                        <input name="confirmation" maxlength="17" autocomplete="off" required>
                    </label>
                    <button class="button danger wide" type="submit">Permanently delete my account</button>
                </form>
            </section>
        </div>
    </dialog>

    <aside class="cookie-notice" id="cookieNotice" hidden role="region" aria-label="Cookie notice">
        <div>
            <p class="eyebrow">Essential cookies only</p>
            <p>Lanternbound uses one essential session cookie to keep you signed in and protect your journey. There are no advertising, analytics, or tracking cookies.</p>
        </div>
        <div class="cookie-actions">
            <a href="privacy.html">Privacy and cookies</a>
            <button class="button secondary" type="button" id="dismissCookieNotice">Understood</button>
        </div>
    </aside>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="assets/app.js" defer></script>
</body>
<?php endif; ?>
</html>
