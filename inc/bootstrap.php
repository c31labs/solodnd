<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';

date_default_timezone_set('UTC');
ini_set('display_errors', $config['environment'] === 'local' ? '1' : '0');
ini_set('log_errors', '1');

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; connect-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self'");
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, private');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

$secure = request_is_https();
session_name($secure ? '__Host-lanternbound' : 'lanternbound_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        $config['database']['dsn'],
        $config['database']['user'],
        $config['database']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    if (str_starts_with($config['database']['dsn'], 'sqlite:')) {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        if ($config['auto_migrate']) {
            migrate_sqlite($pdo);
        }
    }

    return $pdo;
}

function migrate_sqlite(PDO $pdo): void
{
    $schema = file_get_contents(dirname(__DIR__) . '/database/sqlite.sql');
    if ($schema === false) {
        throw new RuntimeException('The local database schema is missing.');
    }
    $pdo->exec($schema);
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('session_version', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('email_verified_at', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT');
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        json_response(['error' => 'Your session check failed. Refresh the page and try again.'], 403);
    }
}

function reject_cross_site_write(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        json_response(['error' => 'Cross site request rejected.'], 403);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 100000) {
        json_response(['error' => 'Invalid request body.'], 400);
    }
    try {
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['error' => 'Invalid JSON.'], 400);
    }
    if (!is_array($data)) {
        json_response(['error' => 'Invalid request data.'], 400);
    }
    return $data;
}

function clean_text(mixed $value, int $maxLength, bool $allowNewlines = false): string
{
    $text = is_string($value) ? trim($value) : '';
    $text = $allowNewlines
        ? preg_replace('/[^\P{C}\n\r\t]/u', '', $text)
        : preg_replace('/[^\P{C}]/u', '', $text);
    return mb_substr($text ?? '', 0, $maxLength);
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function current_user_id(): ?int
{
    global $config;
    if (!isset($_SESSION['user_id'], $_SESSION['last_seen'])) {
        return null;
    }
    $idleLimit = (int) $config['session_idle_minutes'] * 60;
    if (time() - (int) $_SESSION['last_seen'] > $idleLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $versionStmt = db()->prepare('SELECT session_version FROM users WHERE id = ? LIMIT 1');
    $versionStmt->execute([(int) $_SESSION['user_id']]);
    $currentVersion = $versionStmt->fetchColumn();
    if ($currentVersion === false || !isset($_SESSION['session_version']) || (int) $currentVersion !== (int) $_SESSION['session_version']) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_seen'] = time();
    return (int) $_SESSION['user_id'];
}

function require_auth(): int
{
    $userId = current_user_id();
    if ($userId === null) {
        json_response(['error' => 'Please sign in again.'], 401);
    }
    return $userId;
}

function login_user(int $userId): void
{
    $versionStmt = db()->prepare('SELECT session_version FROM users WHERE id = ? LIMIT 1');
    $versionStmt->execute([$userId]);
    $sessionVersion = $versionStmt->fetchColumn();
    if ($sessionVersion === false) {
        throw new RuntimeException('Unable to start the account session.');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['session_version'] = (int) $sessionVersion;
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function password_algorithm(): string|int|null
{
    return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
        ? PASSWORD_ARGON2ID
        : PASSWORD_DEFAULT;
}

function rate_limit_key(string $email): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', mb_strtolower($email) . '|' . $ip);
}

function login_rate_limited(string $key): bool
{
    $stmt = db()->prepare('SELECT attempt_count, window_started FROM login_attempts WHERE key_hash = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $windowAge = time() - strtotime((string) $row['window_started']);
    return $windowAge < 900 && (int) $row['attempt_count'] >= 8;
}

function record_login_attempt(string $key, bool $success): void
{
    if ($success) {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE key_hash = ?');
        $stmt->execute([$key]);
        return;
    }

    $stmt = db()->prepare('SELECT attempt_count, window_started FROM login_attempts WHERE key_hash = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $now = now_utc();
    if (!$row) {
        $insert = db()->prepare('INSERT INTO login_attempts (key_hash, attempt_count, window_started) VALUES (?, 1, ?)');
        $insert->execute([$key, $now]);
        return;
    }

    $expired = time() - strtotime((string) $row['window_started']) >= 900;
    $update = db()->prepare('UPDATE login_attempts SET attempt_count = ?, window_started = ? WHERE key_hash = ?');
    $update->execute([$expired ? 1 : (int) $row['attempt_count'] + 1, $expired ? $now : $row['window_started'], $key]);
}

function class_defaults(string $class): array
{
    $classes = [
        'Barbarian' => ['scores' => [15, 13, 14, 10, 12, 8], 'hit_die' => 12, 'features' => ['Rage', 'Unarmored Defense', 'Weapon Mastery']],
        'Bard' => ['scores' => [8, 14, 12, 13, 10, 15], 'hit_die' => 8, 'features' => ['Bardic Inspiration', 'Spellcasting', 'Weapon Mastery']],
        'Cleric' => ['scores' => [14, 8, 13, 10, 15, 12], 'hit_die' => 8, 'features' => ['Divine Order', 'Spellcasting']],
        'Druid' => ['scores' => [8, 12, 14, 13, 15, 10], 'hit_die' => 8, 'features' => ['Druidic', 'Primal Order', 'Spellcasting']],
        'Fighter' => ['scores' => [15, 14, 13, 8, 10, 12], 'hit_die' => 10, 'features' => ['Fighting Style', 'Second Wind', 'Weapon Mastery']],
        'Monk' => ['scores' => [12, 15, 13, 10, 14, 8], 'hit_die' => 8, 'features' => ['Martial Arts', 'Unarmored Defense']],
        'Paladin' => ['scores' => [15, 10, 13, 8, 12, 14], 'hit_die' => 10, 'features' => ['Lay On Hands', 'Spellcasting', 'Weapon Mastery']],
        'Ranger' => ['scores' => [12, 15, 13, 8, 14, 10], 'hit_die' => 10, 'features' => ['Favored Enemy', 'Spellcasting', 'Weapon Mastery']],
        'Rogue' => ['scores' => [12, 15, 13, 14, 10, 8], 'hit_die' => 8, 'features' => ['Expertise', 'Sneak Attack', 'Thieves Cant', 'Weapon Mastery']],
        'Sorcerer' => ['scores' => [10, 13, 14, 8, 12, 15], 'hit_die' => 6, 'features' => ['Spellcasting', 'Innate Sorcery']],
        'Warlock' => ['scores' => [8, 14, 13, 12, 10, 15], 'hit_die' => 8, 'features' => ['Eldritch Invocations', 'Pact Magic']],
        'Wizard' => ['scores' => [8, 12, 13, 15, 14, 10], 'hit_die' => 6, 'features' => ['Spellcasting', 'Ritual Adept', 'Arcane Recovery']],
    ];
    return $classes[$class] ?? $classes['Fighter'];
}

function seed_adventure(int $userId, string $displayName): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $now = now_utc();
        $adventure = $pdo->prepare('INSERT INTO adventures (user_id, title, current_scene, chaos_factor, created_at, updated_at) VALUES (?, ?, 1, 5, ?, ?)');
        $adventure->execute([$userId, 'The Bell Beneath Briarwatch', $now, $now]);
        $adventureId = (int) $pdo->lastInsertId();

        $defaults = class_defaults('Fighter');
        [$str, $dex, $con, $int, $wis, $cha] = $defaults['scores'];
        $character = $pdo->prepare('INSERT INTO characters (adventure_id, name, class_name, species, background, alignment, level, xp, hp_current, hp_max, armor_class, speed, strength, dexterity, constitution, intelligence, wisdom, charisma, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?, ?, ?, 30, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $hp = $defaults['hit_die'] + intdiv($con - 10, 2);
        $character->execute([$adventureId, $displayName, 'Fighter', 'Human', 'Soldier', 'Neutral Good', $hp, $hp, 10 + intdiv($dex - 10, 2), $str, $dex, $con, $int, $wis, $cha, 'A stranger to this valley, carrying a journal whose first page was already written.', $now, $now]);

        $entries = [
            ['prologue', 'The night the bell remembered', 'Rain silvered the library windows when the abandoned bell above Briarwatch rang once. Its rope was cut twenty years ago. Every candle bent toward the northern hill, and a page appeared in your journal in an unfamiliar hand: When the bell speaks again, follow before the road forgets you.'],
            ['scene', 'Scene 1: The road to Briarwatch', 'The rain has stopped. A stair of black stone climbs through wet pines toward the ruined tower. Halfway up, a child shaped figure waits beneath a red umbrella. No one in the valley admits to owning one. Decide what you expect to happen, open the scene, then ask the Oracle your first question.'],
            ['guide', 'Your solo play rhythm', 'Picture the situation. Decide what your character attempts. Use the 5E rules when success is uncertain and the outcome depends on ability. Ask the Oracle when the world must answer. Interpret the result in context, write what happens, then begin the next scene when the situation changes.'],
        ];
        $entryStmt = $pdo->prepare('INSERT INTO journal_entries (adventure_id, entry_type, title, body, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($entries as [$type, $title, $body]) {
            $entryStmt->execute([$adventureId, $type, $title, $body, '{}', $now]);
        }

        $thread = $pdo->prepare('INSERT INTO threads (adventure_id, name, status, progress, created_at) VALUES (?, ?, ?, 0, ?)');
        $thread->execute([$adventureId, 'Discover why the bell rings', 'active', $now]);
        $thread->execute([$adventureId, 'Learn who wrote in the journal', 'active', $now]);

        $npc = $pdo->prepare('INSERT INTO npcs (adventure_id, name, role, status, created_at) VALUES (?, ?, ?, ?, ?)');
        $npc->execute([$adventureId, 'Mara Vell', 'Keeper of the valley library. She heard the bell before you did.', 'unknown', $now]);

        $pdo->commit();
        return $adventureId;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

require_once __DIR__ . '/story.php';

function state_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT a.*, u.display_name, u.email, u.email_verified_at FROM adventures a JOIN users u ON u.id = a.user_id WHERE a.user_id = ? ORDER BY a.id LIMIT 1');
    $stmt->execute([$userId]);
    $adventure = $stmt->fetch();
    if (!$adventure) {
        $user = db()->prepare('SELECT display_name FROM users WHERE id = ?');
        $user->execute([$userId]);
        $name = (string) ($user->fetchColumn() ?: 'Wanderer');
        seed_adventure($userId, $name);
        return state_for_user($userId);
    }

    $adventureId = (int) $adventure['id'];
    $characterStmt = db()->prepare('SELECT * FROM characters WHERE adventure_id = ? LIMIT 1');
    $characterStmt->execute([$adventureId]);
    $journalStmt = db()->prepare('SELECT * FROM journal_entries WHERE adventure_id = ? ORDER BY id DESC LIMIT 100');
    $journalStmt->execute([$adventureId]);
    $threadStmt = db()->prepare('SELECT * FROM threads WHERE adventure_id = ? ORDER BY status, id');
    $threadStmt->execute([$adventureId]);
    $npcStmt = db()->prepare('SELECT * FROM npcs WHERE adventure_id = ? ORDER BY id');
    $npcStmt->execute([$adventureId]);
    $rollStmt = db()->prepare('SELECT * FROM oracle_rolls WHERE adventure_id = ? ORDER BY id DESC LIMIT 20');
    $rollStmt->execute([$adventureId]);

    $character = $characterStmt->fetch();
    $journal = $journalStmt->fetchAll();

    return [
        'adventure' => $adventure,
        'character' => $character,
        'journal' => $journal,
        'threads' => $threadStmt->fetchAll(),
        'npcs' => $npcStmt->fetchAll(),
        'oracle_history' => $rollStmt->fetchAll(),
        'story_guide' => guided_story_state($adventureId, (string) ($character['name'] ?? $adventure['display_name'] ?? 'Wanderer')),
        'csrf' => csrf_token(),
    ];
}

require_once __DIR__ . '/accounts.php';
db();
