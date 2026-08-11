<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

reject_cross_site_write();
$userId = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$body = $method === 'POST' ? json_body() : [];
$action = clean_text($body['action'] ?? $_GET['action'] ?? 'state', 40);

if ($method === 'POST') {
    require_csrf();
}

function adventure_id_for_user(int $userId): int
{
    $stmt = db()->prepare('SELECT id FROM adventures WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $user = db()->prepare('SELECT display_name FROM users WHERE id = ?');
        $user->execute([$userId]);
        return seed_adventure($userId, (string) ($user->fetchColumn() ?: 'Wanderer'));
    }
    return (int) $id;
}

function bounded_int(mixed $value, int $minimum, int $maximum): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) {
        return $minimum;
    }
    return max($minimum, min($maximum, (int) $number));
}

function allowed_value(mixed $value, array $allowed, string $fallback): string
{
    $clean = clean_text($value, 60);
    return in_array($clean, $allowed, true) ? $clean : $fallback;
}

function meaning_pair(): array
{
    $actions = [
        'Awaken', 'Bind', 'Break', 'Carry', 'Challenge', 'Conceal', 'Cross', 'Defend', 'Delay', 'Discover',
        'Divide', 'Echo', 'Escape', 'Exchange', 'Follow', 'Gather', 'Guard', 'Guide', 'Heal', 'Invite',
        'Judge', 'Listen', 'Lose', 'Mend', 'Open', 'Protect', 'Question', 'Remember', 'Reveal', 'Risk',
        'Seek', 'Silence', 'Steal', 'Transform', 'Travel', 'Trust', 'Uncover', 'Warn', 'Witness', 'Yield',
    ];
    $subjects = [
        'Ash', 'Beacon', 'Bloodline', 'Bridge', 'Child', 'Crown', 'Debt', 'Door', 'Dream', 'Enemy',
        'Forest', 'Friend', 'Gift', 'Guardian', 'Home', 'Hunger', 'Key', 'Library', 'Map', 'Memory',
        'Mirror', 'Moon', 'Oath', 'Path', 'Prisoner', 'Promise', 'Relic', 'River', 'Ruin', 'Secret',
        'Shadow', 'Song', 'Storm', 'Stranger', 'Threshold', 'Tower', 'Truth', 'Voice', 'Weapon', 'World',
    ];
    return [$actions[random_int(0, count($actions) - 1)], $subjects[random_int(0, count($subjects) - 1)]];
}

function event_focus(): string
{
    $focuses = [
        'A distant event becomes important',
        'An existing character takes action',
        'A new character enters the story',
        'A thread moves closer to resolution',
        'A thread becomes more difficult',
        'Your hero faces a cost',
        'Your hero gains an unexpected advantage',
        'The environment changes',
        'A hidden connection is revealed',
        'The immediate situation escalates',
    ];
    return $focuses[random_int(0, count($focuses) - 1)];
}

function resolve_oracle_question(string $question, string $odds, int $chaos): array
{
    $oddsMap = ['impossible' => 10, 'unlikely' => 30, 'even' => 50, 'likely' => 70, 'certain' => 90];
    if ($question === '' || !isset($oddsMap[$odds])) {
        throw new InvalidArgumentException('Ask one clear yes or no question.');
    }

    $threshold = max(5, min(95, $oddsMap[$odds] + (($chaos - 5) * 3)));
    $roll = random_int(1, 100);
    $yes = $roll <= $threshold;
    $exceptionalYes = $yes && $roll <= max(1, (int) floor($threshold * 0.2));
    $exceptionalNoFloor = $threshold + (int) ceil((100 - $threshold) * 0.8);
    $exceptionalNo = !$yes && $roll >= $exceptionalNoFloor;
    $exceptional = $exceptionalYes || $exceptionalNo;
    $answer = $exceptionalYes ? 'Exceptional yes' : ($exceptionalNo ? 'Exceptional no' : ($yes ? 'Yes' : 'No'));
    $digits = str_pad((string) $roll, 2, '0', STR_PAD_LEFT);
    $double = $roll < 100 && $digits[0] === $digits[1] && $digits[0] !== '0';
    $eventTriggered = $double && (int) $digits[0] <= $chaos;
    $focus = $eventTriggered ? event_focus() : '';
    [$actionWord, $subjectWord] = $eventTriggered ? meaning_pair() : ['', ''];

    return [
        'question' => $question,
        'odds' => $odds,
        'answer' => $answer,
        'roll' => $roll,
        'threshold' => $threshold,
        'exceptional' => $exceptional,
        'event_triggered' => $eventTriggered,
        'event_focus' => $focus,
        'event_meaning' => $eventTriggered ? $actionWord . ' ' . $subjectWord : '',
    ];
}

function store_oracle_result(PDO $pdo, int $adventureId, array $result): void
{
    $stmt = $pdo->prepare('INSERT INTO oracle_rolls (adventure_id, question, odds, roll, threshold_value, answer, exceptional, event_triggered, event_focus, event_meaning, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $adventureId,
        $result['question'],
        $result['odds'],
        $result['roll'],
        $result['threshold'],
        $result['answer'],
        $result['exceptional'] ? 1 : 0,
        $result['event_triggered'] ? 1 : 0,
        $result['event_focus'],
        $result['event_meaning'],
        now_utc(),
    ]);
}

if ($action === 'state' && $method === 'GET') {
    json_response(state_for_user($userId));
}

if ($action === 'export' && $method === 'GET') {
    $state = state_for_user($userId);
    unset($state['csrf']);
    unset($state['adventure']['email']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="lanternbound-journey-' . gmdate('Y-m-d') . '.json"');
    echo json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if ($method !== 'POST') {
    json_response(['error' => 'Unknown request.'], 404);
}

$adventureId = adventure_id_for_user($userId);

if ($action === 'save_character') {
    $classes = ['Barbarian', 'Bard', 'Cleric', 'Druid', 'Fighter', 'Monk', 'Paladin', 'Ranger', 'Rogue', 'Sorcerer', 'Warlock', 'Wizard'];
    $species = ['Dragonborn', 'Dwarf', 'Elf', 'Gnome', 'Goliath', 'Halfling', 'Human', 'Orc', 'Tiefling'];
    $backgrounds = ['Acolyte', 'Criminal', 'Sage', 'Soldier'];
    $alignments = ['Lawful Good', 'Neutral Good', 'Chaotic Good', 'Lawful Neutral', 'Neutral', 'Chaotic Neutral'];
    $values = [
        clean_text($body['name'] ?? '', 80) ?: 'Unnamed wanderer',
        allowed_value($body['class_name'] ?? '', $classes, 'Fighter'),
        allowed_value($body['species'] ?? '', $species, 'Human'),
        allowed_value($body['background'] ?? '', $backgrounds, 'Soldier'),
        allowed_value($body['alignment'] ?? '', $alignments, 'Neutral Good'),
        bounded_int($body['level'] ?? 1, 1, 20),
        bounded_int($body['xp'] ?? 0, 0, 9999999),
        bounded_int($body['hp_current'] ?? 1, 0, 999),
        bounded_int($body['hp_max'] ?? 1, 1, 999),
        bounded_int($body['armor_class'] ?? 10, 1, 40),
        bounded_int($body['speed'] ?? 30, 0, 200),
        bounded_int($body['strength'] ?? 10, 1, 30),
        bounded_int($body['dexterity'] ?? 10, 1, 30),
        bounded_int($body['constitution'] ?? 10, 1, 30),
        bounded_int($body['intelligence'] ?? 10, 1, 30),
        bounded_int($body['wisdom'] ?? 10, 1, 30),
        bounded_int($body['charisma'] ?? 10, 1, 30),
        clean_text($body['notes'] ?? '', 4000, true),
        now_utc(),
        $adventureId,
    ];
    $stmt = db()->prepare('UPDATE characters SET name = ?, class_name = ?, species = ?, background = ?, alignment = ?, level = ?, xp = ?, hp_current = ?, hp_max = ?, armor_class = ?, speed = ?, strength = ?, dexterity = ?, constitution = ?, intelligence = ?, wisdom = ?, charisma = ?, notes = ?, updated_at = ? WHERE adventure_id = ?');
    $stmt->execute($values);
    json_response(['ok' => true, 'character' => state_for_user($userId)['character']]);
}

if ($action === 'add_journal') {
    $types = ['narrative', 'action', 'dialogue', 'discovery'];
    $type = allowed_value($body['entry_type'] ?? '', $types, 'narrative');
    $title = clean_text($body['title'] ?? '', 140);
    $text = clean_text($body['body'] ?? '', 12000, true);
    if ($title === '' || $text === '') {
        json_response(['error' => 'Give this moment a title and describe what happened.'], 422);
    }
    $stmt = db()->prepare('INSERT INTO journal_entries (adventure_id, entry_type, title, body, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$adventureId, $type, $title, $text, '{}', now_utc()]);
    json_response(['ok' => true, 'state' => state_for_user($userId)]);
}

if ($action === 'set_chaos') {
    $chaos = bounded_int($body['chaos_factor'] ?? 5, 1, 9);
    $stmt = db()->prepare('UPDATE adventures SET chaos_factor = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$chaos, now_utc(), $adventureId]);
    json_response(['ok' => true, 'chaos_factor' => $chaos]);
}

if ($action === 'meaning') {
    [$actionWord, $subjectWord] = meaning_pair();
    json_response(['ok' => true, 'words' => [$actionWord, $subjectWord]]);
}

if ($action === 'oracle') {
    $question = clean_text($body['question'] ?? '', 300);
    $odds = clean_text($body['odds'] ?? 'even', 20);
    if ($question === '' || !in_array($odds, ['impossible', 'unlikely', 'even', 'likely', 'certain'], true)) {
        json_response(['error' => 'Ask one clear yes or no question.'], 422);
    }

    $adventureStmt = db()->prepare('SELECT chaos_factor FROM adventures WHERE id = ?');
    $adventureStmt->execute([$adventureId]);
    $chaos = (int) $adventureStmt->fetchColumn();
    $result = resolve_oracle_question($question, $odds, $chaos);
    store_oracle_result(db(), $adventureId, $result);
    json_response(['ok' => true, 'result' => $result]);
}

if ($action === 'guided_story') {
    $stageId = clean_text($body['stage_id'] ?? '', 60);
    $choiceId = clean_text($body['choice_id'] ?? '', 60);
    $book = lanternbound_story_book();
    $stage = $book['stages'][$stageId] ?? null;
    $choice = is_array($stage) ? ($stage['choices'][$choiceId] ?? null) : null;
    if (!is_array($stage) || !is_array($choice)) {
        json_response(['error' => 'That story choice is not available.'], 422);
    }

    $progressStmt = db()->prepare("SELECT metadata FROM journal_entries WHERE adventure_id = ? AND entry_type = 'story' ORDER BY id");
    $progressStmt->execute([$adventureId]);
    $progress = guided_story_progress_from_rows($progressStmt->fetchAll());
    if ($progress['current'] === null) {
        json_response(['error' => 'This guided chapter is already complete.'], 409);
    }
    if ($progress['current'] !== $stageId) {
        json_response(['error' => 'That page has already turned. Refresh your journey to continue.'], 409);
    }

    $adventureStmt = db()->prepare('SELECT chaos_factor FROM adventures WHERE id = ?');
    $adventureStmt->execute([$adventureId]);
    $chaos = (int) $adventureStmt->fetchColumn();
    $characterStmt = db()->prepare('SELECT name FROM characters WHERE adventure_id = ? LIMIT 1');
    $characterStmt->execute([$adventureId]);
    $heroName = (string) ($characterStmt->fetchColumn() ?: 'Wanderer');
    $oracle = resolve_oracle_question($choice['question'], $choice['odds'], $chaos);
    $resolution = guided_story_resolve($stageId, $choiceId, $oracle, $heroName);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        store_oracle_result($pdo, $adventureId, $oracle);
        $entry = $pdo->prepare('INSERT INTO journal_entries (adventure_id, entry_type, title, body, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $entry->execute([
            $adventureId,
            'story',
            $resolution['title'],
            $resolution['body'],
            json_encode($resolution['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            now_utc(),
        ]);

        if (is_string($resolution['thread']) && $resolution['thread'] !== '') {
            $existingThread = $pdo->prepare('SELECT id FROM threads WHERE adventure_id = ? AND name = ? LIMIT 1');
            $existingThread->execute([$adventureId, $resolution['thread']]);
            if (!$existingThread->fetchColumn()) {
                $thread = $pdo->prepare('INSERT INTO threads (adventure_id, name, status, progress, created_at) VALUES (?, ?, ?, 0, ?)');
                $thread->execute([$adventureId, $resolution['thread'], 'active', now_utc()]);
            }
        }

        if (is_array($resolution['npc'])) {
            $existingNpc = $pdo->prepare('SELECT id FROM npcs WHERE adventure_id = ? AND name = ? LIMIT 1');
            $existingNpc->execute([$adventureId, $resolution['npc']['name']]);
            if (!$existingNpc->fetchColumn()) {
                $npc = $pdo->prepare('INSERT INTO npcs (adventure_id, name, role, status, created_at) VALUES (?, ?, ?, ?, ?)');
                $npc->execute([$adventureId, $resolution['npc']['name'], $resolution['npc']['role'], 'unknown', now_utc()]);
            }
        }

        $update = $pdo->prepare('UPDATE adventures SET updated_at = ? WHERE id = ?');
        $update->execute([now_utc(), $adventureId]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    json_response(['ok' => true, 'result' => $oracle, 'state' => state_for_user($userId)]);
}

if ($action === 'scene') {
    $expectation = clean_text($body['expectation'] ?? '', 400, true);
    if ($expectation === '') {
        json_response(['error' => 'Describe what you expect before opening the scene.'], 422);
    }
    $adventureStmt = db()->prepare('SELECT current_scene, chaos_factor FROM adventures WHERE id = ?');
    $adventureStmt->execute([$adventureId]);
    $adventure = $adventureStmt->fetch();
    $roll = random_int(1, 10);
    $chaos = (int) $adventure['chaos_factor'];
    $sceneNumber = (int) $adventure['current_scene'] + 1;
    $kind = 'Expected scene';
    $guidance = 'Begin as expected, then play to discover what changes.';
    $focus = '';
    $meaning = '';
    if ($roll <= $chaos) {
        if ($roll % 2 === 0) {
            $kind = 'Altered scene';
            [$verb, $subject] = meaning_pair();
            $meaning = $verb . ' ' . $subject;
            $guidance = 'Keep the purpose of the scene, but change an important detail using the meaning spark.';
        } else {
            $kind = 'Interrupted scene';
            $focus = event_focus();
            [$verb, $subject] = meaning_pair();
            $meaning = $verb . ' ' . $subject;
            $guidance = 'Set the expectation aside. Begin with the interruption and discover where it leads.';
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE adventures SET current_scene = ?, updated_at = ? WHERE id = ?');
        $update->execute([$sceneNumber, now_utc(), $adventureId]);
        $bodyText = 'Expectation: ' . $expectation . "\n\n" . $guidance;
        if ($focus !== '') {
            $bodyText .= "\n\nFocus: " . $focus;
        }
        if ($meaning !== '') {
            $bodyText .= "\nMeaning: " . $meaning;
        }
        $meta = json_encode(['roll' => $roll, 'kind' => $kind, 'focus' => $focus, 'meaning' => $meaning], JSON_THROW_ON_ERROR);
        $entry = $pdo->prepare('INSERT INTO journal_entries (adventure_id, entry_type, title, body, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $entry->execute([$adventureId, 'scene', 'Scene ' . $sceneNumber . ': ' . $kind, $bodyText, $meta, now_utc()]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    json_response(['ok' => true, 'result' => ['scene' => $sceneNumber, 'kind' => $kind, 'roll' => $roll, 'focus' => $focus, 'meaning' => $meaning, 'guidance' => $guidance], 'state' => state_for_user($userId)]);
}

if ($action === 'add_thread') {
    $name = clean_text($body['name'] ?? '', 180);
    if ($name === '') {
        json_response(['error' => 'Name the unresolved goal.'], 422);
    }
    $stmt = db()->prepare('INSERT INTO threads (adventure_id, name, status, progress, created_at) VALUES (?, ?, ?, 0, ?)');
    $stmt->execute([$adventureId, $name, 'active', now_utc()]);
    json_response(['ok' => true, 'threads' => state_for_user($userId)['threads']]);
}

if ($action === 'toggle_thread') {
    $threadId = bounded_int($body['id'] ?? 0, 1, PHP_INT_MAX);
    $stmt = db()->prepare("UPDATE threads SET status = CASE WHEN status = 'active' THEN 'closed' ELSE 'active' END WHERE id = ? AND adventure_id = ?");
    $stmt->execute([$threadId, $adventureId]);
    json_response(['ok' => true, 'threads' => state_for_user($userId)['threads']]);
}

if ($action === 'add_npc') {
    $name = clean_text($body['name'] ?? '', 100);
    if ($name === '') {
        json_response(['error' => 'Name the person or creature.'], 422);
    }
    $stmt = db()->prepare('INSERT INTO npcs (adventure_id, name, role, status, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adventureId, $name, '', 'unknown', now_utc()]);
    json_response(['ok' => true, 'npcs' => state_for_user($userId)['npcs']]);
}

json_response(['error' => 'Unknown action.'], 404);
