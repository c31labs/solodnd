<?php
declare(strict_types=1);

/**
 * Original guided fiction for The Bell Beneath Briarwatch.
 *
 * Each page establishes context, gives the player a meaningful approach, and
 * lets the Oracle decide the uncertain part. Completed pages are stored as
 * ordinary journal entries, so no additional database table is required.
 */
function lanternbound_story_book(): array
{
    return [
        'title' => 'The Bell Beneath Briarwatch',
        'chapter' => 'Chapter One: The Road That Remembers',
        'opening_lore' => [
            [
                'title' => 'Briarwatch',
                'body' => 'A rainbound valley settlement built beneath a ruined bell tower. Its library keeps records of every local birth and death, except for a missing span of twenty years.',
            ],
        ],
        'completion' => [
            'eyebrow' => 'Chapter One complete',
            'title' => 'The road beyond Briarwatch',
            'body' => 'The first truth of the wider world is now yours. Roads once joined distant lantern holds, and something has begun erasing them from memory. Continue freely with the Oracle, or let the next Direwrought chapter begin from the path your answers created.',
            'prompt' => 'Write what your hero does with the first living road, then open the next scene when you are ready.',
        ],
        'stages' => [
            'red_umbrella' => [
                'number' => 1,
                'location' => 'The black stair',
                'title' => 'The figure beneath the red umbrella',
                'prose' => [
                    'The stair climbs through pines that shine after rain. Halfway to the ruined tower, a small figure waits beneath a red umbrella. Water falls around it, but never touches its shoes.',
                    'Behind you, the road is already quieter than it should be. Ahead, the bell tower has no bell. Choose how your hero meets the first impossible thing.',
                ],
                'next' => 'forgotten_name',
                'unlock_lore' => [
                    'title' => 'The red umbrella',
                    'body' => 'An object absent from every Briarwatch household, carried by a figure that waited on the tower stair before the rain stopped.',
                ],
                'npc' => [
                    'name' => 'The Umbrella Bearer',
                    'role' => 'A small figure waiting between the remembered road and the ruined tower.',
                ],
                'choices' => [
                    'offer_name' => [
                        'label' => 'Offer your name',
                        'lead' => 'Step into the open and introduce yourself.',
                        'question' => 'Does the figure answer with a name of its own?',
                        'odds' => 'likely',
                        'title' => 'A name beneath red silk',
                        'yes' => 'The umbrella tips back. “Wren,” the figure says, though the voice sounds older than the tower. “That was my name when this road still knew where it went.” One pale hand points toward the ruin. Fresh ink moves across the page in your journal without touching pen or rain.',
                        'no' => 'No voice answers. The figure writes a single word on the wet stone with one bare finger. The mark glows like banked coal, then vanishes. The same word appears in your journal, written in the unfamiliar hand that summoned you.',
                    ],
                    'watch_first' => [
                        'label' => 'Watch from the pines',
                        'lead' => 'Remain hidden and study the figure before moving.',
                        'question' => 'Does the figure reveal something while it believes itself unobserved?',
                        'odds' => 'even',
                        'title' => 'What the watcher sees',
                        'yes' => 'The figure raises the umbrella toward the empty tower. For one breath, red light shows a road hanging in the air above the trees, bright with lanterns and crowded with travellers who cast no shadows. Then the vision folds into the umbrella and the figure turns directly toward your hiding place.',
                        'no' => 'The figure does not move, but your patience changes the world around it. Rain begins to fall upward from the black stair. Each drop carries a tiny reflection of the ruined tower as it stood long ago, complete, guarded, and burning with lantern light.',
                    ],
                    'walk_past' => [
                        'label' => 'Continue toward the tower',
                        'lead' => 'Keep your purpose and climb without stopping.',
                        'question' => 'Does the figure allow me to pass without following?',
                        'odds' => 'unlikely',
                        'title' => 'The step that follows',
                        'yes' => 'You pass close enough to hear rain ticking against the red silk. The figure remains behind, yet a second set of wet footprints begins beside yours. They climb toward the tower one pace at a time, made by something the world refuses to show you.',
                        'no' => 'The red umbrella closes across the stair like a sudden flower. “There is no tower above us,” the figure says. “Not the one that called you.” It points down through the roots, where a deep note trembles beneath the stone.',
                    ],
                ],
            ],
            'forgotten_name' => [
                'number' => 2,
                'location' => 'The rainward landing',
                'title' => 'The name the valley buried',
                'prose' => [
                    'However you met the figure, the encounter leaves a new word in your journal: ORISON. Mara Vell never spoke it in the library, yet the letters feel older than Briarwatch and strangely familiar.',
                    'The figure stands beside a weathered boundary stone. Someone has carefully cut every name from its face. Decide which silence you challenge.',
                ],
                'next' => 'hingeless_door',
                'unlock_lore' => [
                    'title' => 'Orison Vale',
                    'body' => 'The older name beneath Briarwatch. It was one of several settlements joined by roads that carried memory as surely as travellers.',
                ],
                'thread' => 'Restore the name erased from Orison Vale',
                'choices' => [
                    'ask_bell' => [
                        'label' => 'Ask about the bell',
                        'lead' => 'Demand to know why the silent bell called you here.',
                        'question' => 'Does the Umbrella Bearer know why the bell called me?',
                        'odds' => 'likely',
                        'title' => 'Why the bell remembers',
                        'yes' => '“It calls people who can still make choices,” the figure says. “The others only repeat what the road has already lost.” The cut names on the boundary stone begin to whisper. One of those voices belongs to you, but it speaks from many years ahead.',
                        'no' => 'The figure flinches from the question. It knows the bell is afraid, but not what it wants. Beneath the boundary stone you find a narrow cavity containing a brass token stamped with a lantern and a road that divides into nine paths.',
                    ],
                    'study_journal' => [
                        'label' => 'Study the living journal',
                        'lead' => 'Compare the new word with the message that brought you here.',
                        'question' => 'Was the word ORISON written by the same hand that summoned me?',
                        'odds' => 'likely',
                        'title' => 'Two hands in one book',
                        'yes' => 'The strokes match perfectly until the final letter. There the ink splits into two colours, black and ember red. The hand that summoned you is not alone inside the journal. A second writer has been answering from somewhere beneath the tower.',
                        'no' => 'The strokes are deliberately different. Your summoner writes with a hurried human hand. ORISON was pressed into the page as though the book itself remembered the word. When you touch it, every bird in the pines calls the same three notes.',
                    ],
                    'name_mara' => [
                        'label' => 'Speak of Mara Vell',
                        'lead' => 'Ask what the keeper of the library has concealed.',
                        'question' => 'Does the Umbrella Bearer recognise Mara Vell?',
                        'odds' => 'even',
                        'title' => 'The keeper and the forgotten',
                        'yes' => 'At Mara’s name, the umbrella opens though no rain falls. Its inner silk shows a younger Mara standing before the intact tower. She holds the same journal and weeps as every lantern on the distant road goes dark, one after another.',
                        'no' => 'The name means nothing to the figure, which is answer enough. Something has removed Mara from a memory that should include her. Your journal adds a question beneath ORISON: What must a keeper surrender to be forgotten?',
                    ],
                ],
            ],
            'hingeless_door' => [
                'number' => 3,
                'location' => 'Briarwatch ruin',
                'title' => 'The door with no hinge',
                'prose' => [
                    'At the summit, the ruined tower leans over the valley like a listening giant. There is no chamber, no stair, and no bell. Only a freestanding stone door remains among the nettles.',
                    'The door has no hinge or handle. Nine shallow lanterns circle its frame, and one holds a coal red light. The next truth waits behind it.',
                ],
                'next' => 'bell_below',
                'unlock_lore' => [
                    'title' => 'The nine lantern mark',
                    'body' => 'A sign found on the tower door and an old brass token. Only one of its nine lanterns still burns.',
                ],
                'choices' => [
                    'speak_orison' => [
                        'label' => 'Speak the buried name',
                        'lead' => 'Place your hand against the stone and say Orison aloud.',
                        'question' => 'Does the door open when I speak the valley’s forgotten name?',
                        'odds' => 'likely',
                        'title' => 'The name as a key',
                        'yes' => 'Stone softens beneath your palm. The door opens inward upon a stair descending through roots and starlight. Far below, something enormous draws breath, and the single burning lantern on the frame moves to the second position.',
                        'no' => 'The name wakes the door, but it refuses you. Letters appear across the stone: A PLACE IS MORE THAN WHAT IT WAS CALLED. From the valley below comes the sound of every library window opening at once.',
                    ],
                    'offer_journal' => [
                        'label' => 'Offer the journal',
                        'lead' => 'Hold the living book before the circle of lanterns.',
                        'question' => 'Does the journal act as a key to the stone door?',
                        'odds' => 'even',
                        'title' => 'A page for a passage',
                        'yes' => 'A blank page tears itself free and passes into the stone. The door drinks every word that might have been written there, then opens upon a downward stair. Your journal is one page lighter, and a future choice has vanished with it.',
                        'no' => 'The door rejects the book with a pulse that numbs your hands. Yet the journal has taken an impression of the lantern circle. On the copied mark, the dark lanterns form letters when viewed from the corner of your eye: BELOW, NOT BEYOND.',
                    ],
                    'search_roots' => [
                        'label' => 'Search beneath the roots',
                        'lead' => 'Ignore the obvious threshold and look for the path time concealed.',
                        'question' => 'Is there a safer entrance beneath the tower roots?',
                        'odds' => 'unlikely',
                        'title' => 'Where the roots make room',
                        'yes' => 'A fox watches from a hollow beneath the oldest pine, then slips through roots too narrow for its body. Behind them you find a mason’s passage descending beside the sealed tower. Small bells hang along its ceiling, each stuffed with a scrap of written memory.',
                        'no' => 'Every root returns you to the same stone door. The tower is not preventing entry; it is preventing escape. When you realise this, the door stands open behind you and warm air rises from the dark stair below.',
                    ],
                ],
            ],
            'bell_below' => [
                'number' => 4,
                'location' => 'The stair beneath roots',
                'title' => 'The bell below the tower',
                'prose' => [
                    'The passage descends far deeper than the hill allows. Roots cross the walls like the ribs of a sleeping beast. With every turn, the note beneath your feet grows clearer.',
                    'It is not a bell ringing. It is a bell dreaming of being rung. The dream knows that you are near.',
                ],
                'next' => 'first_toll',
                'unlock_lore' => [
                    'title' => 'The Underbell',
                    'body' => 'A vast bell hidden beneath Briarwatch. It dreams, remembers possible futures, and calls to people who can still choose among them.',
                ],
                'thread' => 'Learn who forged the Underbell',
                'choices' => [
                    'follow_note' => [
                        'label' => 'Follow the deepest note',
                        'lead' => 'Let the vibration guide you when the stair divides.',
                        'question' => 'Is the true bell waiting at the heart of the lower passage?',
                        'odds' => 'certain',
                        'title' => 'The chamber that should not fit',
                        'yes' => 'The note leads into a chamber wider than the hill and roofed by a sky full of unfamiliar stars. The Underbell hangs above a black pool, suspended from a chain that rises into darkness. Its metal is covered with names, including yours.',
                        'no' => 'The strongest note is a decoy, a memory of the bell rather than the bell itself. By walking against the vibration, you find a narrow arch where the roots have been burned white. Beyond it hangs the real Underbell, perfectly silent and waiting.',
                    ],
                    'read_carvings' => [
                        'label' => 'Read the carved names',
                        'lead' => 'Stop at the wall of names and search for a familiar hand.',
                        'question' => 'Do the carvings include Mara Vell’s name?',
                        'odds' => 'unlikely',
                        'title' => 'What the stone kept',
                        'yes' => 'Mara’s name appears hundreds of times, each followed by a different date. Some dates lie centuries in the past. Others have not happened. Beside the newest carving, the stone is still warm and a second name is beginning to form.',
                        'no' => 'Mara is absent, but every other keeper of the valley library is here. Each name ends with the same title: WITNESS OF THE FIRST ROAD. The final line is blank, carefully prepared for a keeper who escaped the pattern.',
                    ],
                    'listen_heartbeat' => [
                        'label' => 'Answer with your heartbeat',
                        'lead' => 'Stand in silence and let the bell hear that you are alive.',
                        'question' => 'Does something below answer the rhythm of my heart?',
                        'odds' => 'likely',
                        'title' => 'The answer in the dark',
                        'yes' => 'A second heartbeat answers, vast and slow. With each pulse, the walls become briefly transparent. Beyond them stretch roads across forests, deserts, drowned cities, and a mountain split by eternal dawn. Eight distant lanterns are dark. One burns beneath your feet.',
                        'no' => 'Nothing answers, and the silence becomes a message of its own. The bell is listening for something your hero has not yet become. On the final step lies a length of red thread leading toward a chamber of black water.',
                    ],
                ],
            ],
            'first_toll' => [
                'number' => 5,
                'location' => 'The Underbell chamber',
                'title' => 'The first toll',
                'prose' => [
                    'The Underbell waits above still water. Its chain disappears into a sky that cannot exist beneath Briarwatch. Around the pool, nine roads have been drawn in powdered silver. Eight end abruptly. One reaches your boots.',
                    'Your journal opens to its final blank page. Whatever you choose now will not complete the world. It will decide where the world begins.',
                ],
                'next' => null,
                'unlock_lore' => [
                    'title' => 'The Lantern Roads',
                    'body' => 'Nine living roads once joined distant holds across the wider world. Eight have been erased or darkened. The road beneath Briarwatch has awakened again.',
                ],
                'thread' => 'Follow the first awakened Lantern Road',
                'choices' => [
                    'ring_bell' => [
                        'label' => 'Wake the Underbell',
                        'lead' => 'Reach for the hanging chain and accept that every toll has a price.',
                        'question' => 'Can the Underbell be awakened without surrendering one of my memories?',
                        'odds' => 'unlikely',
                        'title' => 'The price of the first toll',
                        'yes' => 'The chain moves at your touch. The Underbell sounds without taking anything from you, because someone else paid the price long ago. In the black water you see Mara Vell close a younger hand around the same chain and give the bell every memory of her own child.',
                        'no' => 'The bell takes a memory before it will sound. Choose something your hero once knew with certainty and write it in the journal, then cross it out. The first toll rolls through the roots. Far beyond Briarwatch, one dark lantern answers.',
                    ],
                    'listen_bell' => [
                        'label' => 'Listen before touching it',
                        'lead' => 'Ask the bell to reveal the hand inside your journal.',
                        'question' => 'Does the Underbell reveal who wrote the message that summoned me?',
                        'odds' => 'likely',
                        'title' => 'The writer at the road’s end',
                        'yes' => 'The black water clears. Your own face looks back, older and marked by roads you have not travelled. The writer is a future you, calling backward from the place where the ninth lantern failed. The reflection mouths one warning: Do not arrive alone.',
                        'no' => 'The writer remains hidden, but the bell reveals where the message was written. You see a city built inside the ribs of a colossal creature, beneath a lantern that burns blue. A road of living ink now runs from your journal toward that impossible horizon.',
                    ],
                    'break_chain' => [
                        'label' => 'Sever the chain',
                        'lead' => 'Refuse the bell’s design and attempt to silence it forever.',
                        'question' => 'Will breaking the chain truly silence the Underbell?',
                        'odds' => 'unlikely',
                        'title' => 'A bell without a chain',
                        'yes' => 'The chain breaks with the sound of a door closing. The Underbell falls, but never reaches the water. Its final note becomes a road of white fire leading out of the chamber and beyond every map in Mara’s library.',
                        'no' => 'The chain parts and rises into the false sky. Freed from its anchor, the Underbell rings for the first time in twenty years. The sound does not travel through air. It travels through names, and every person in Briarwatch remembers Orison Vale at once.',
                    ],
                ],
            ],
        ],
    ];
}

function guided_story_progress_from_rows(array $rows): array
{
    $book = lanternbound_story_book();
    $current = array_key_first($book['stages']);
    $completed = [];

    foreach ($rows as $row) {
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
        if (!is_array($metadata) || ($metadata['guided_story_version'] ?? null) !== 1) {
            continue;
        }
        $stageId = is_string($metadata['stage_id'] ?? null) ? $metadata['stage_id'] : '';
        if (!isset($book['stages'][$stageId])) {
            continue;
        }
        if (!in_array($stageId, $completed, true)) {
            $completed[] = $stageId;
        }
        $next = $metadata['next_stage'] ?? null;
        $current = is_string($next) && isset($book['stages'][$next]) ? $next : null;
    }

    return ['current' => $current, 'completed' => $completed];
}

function guided_story_state(int $adventureId, string $heroName): array
{
    $stmt = db()->prepare("SELECT metadata FROM journal_entries WHERE adventure_id = ? AND entry_type = 'story' ORDER BY id");
    $stmt->execute([$adventureId]);
    $progress = guided_story_progress_from_rows($stmt->fetchAll());
    $book = lanternbound_story_book();
    $lore = $book['opening_lore'];

    foreach ($progress['completed'] as $stageId) {
        $lore[] = $book['stages'][$stageId]['unlock_lore'];
    }

    $stage = null;
    if ($progress['current'] !== null) {
        $source = $book['stages'][$progress['current']];
        $choices = [];
        foreach ($source['choices'] as $choiceId => $choice) {
            $choices[] = [
                'id' => $choiceId,
                'label' => $choice['label'],
                'lead' => $choice['lead'],
                'question' => $choice['question'],
                'odds' => $choice['odds'],
            ];
        }
        $stage = [
            'id' => $progress['current'],
            'number' => $source['number'],
            'location' => $source['location'],
            'title' => $source['title'],
            'prose' => array_map(
                static fn(string $paragraph): string => str_replace('{hero}', $heroName, $paragraph),
                $source['prose']
            ),
            'choices' => $choices,
        ];
    }

    return [
        'title' => $book['title'],
        'chapter' => $book['chapter'],
        'completed_count' => count($progress['completed']),
        'total_count' => count($book['stages']),
        'stage' => $stage,
        'lore' => $lore,
        'completion' => $stage === null ? $book['completion'] : null,
    ];
}

function guided_story_resolve(string $stageId, string $choiceId, array $oracle, string $heroName): array
{
    $book = lanternbound_story_book();
    $stage = $book['stages'][$stageId] ?? null;
    $choice = is_array($stage) ? ($stage['choices'][$choiceId] ?? null) : null;
    if (!is_array($stage) || !is_array($choice)) {
        throw new InvalidArgumentException('That story choice is not available.');
    }

    $isYes = in_array($oracle['answer'], ['Yes', 'Exceptional yes'], true);
    $body = str_replace('{hero}', $heroName, (string) $choice[$isYes ? 'yes' : 'no']);
    if (!empty($oracle['exceptional'])) {
        $body .= $isYes
            ? "\n\nThe answer arrives with unusual force. Add one lasting advantage or deeper revelation that follows naturally from this moment."
            : "\n\nThe refusal is absolute. Add one serious complication that follows naturally from what has just been revealed.";
    }
    if (!empty($oracle['event_triggered'])) {
        $body .= "\n\nThe world intervenes. " . $oracle['event_focus'] . '. Meaning: ' . $oracle['event_meaning'] . '. Weave this surprise into the scene before continuing.';
    }

    $body .= "\n\nOracle: " . $oracle['answer'] . '. Question: ' . $choice['question'];

    return [
        'title' => $choice['title'],
        'body' => $body,
        'next_stage' => $stage['next'],
        'thread' => $stage['thread'] ?? null,
        'npc' => $stage['npc'] ?? null,
        'metadata' => [
            'guided_story_version' => 1,
            'stage_id' => $stageId,
            'choice_id' => $choiceId,
            'question' => $choice['question'],
            'odds' => $choice['odds'],
            'answer' => $oracle['answer'],
            'next_stage' => $stage['next'],
        ],
    ];
}
