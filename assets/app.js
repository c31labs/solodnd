(() => {
    'use strict';

    const cookieNotice = document.getElementById('cookieNotice');
    const dismissCookieNotice = document.getElementById('dismissCookieNotice');

    if (cookieNotice && dismissCookieNotice) {
        let acknowledged = false;
        try {
            acknowledged = window.localStorage.getItem('lanternbound_cookie_notice_v1') === 'acknowledged';
        } catch (error) {
            acknowledged = false;
        }

        if (!acknowledged) {
            cookieNotice.hidden = false;
        }

        dismissCookieNotice.addEventListener('click', () => {
            cookieNotice.hidden = true;
            try {
                window.localStorage.setItem('lanternbound_cookie_notice_v1', 'acknowledged');
            } catch (error) {
                // The notice can still be dismissed for this page when browser storage is unavailable.
            }
        });
    }

    const root = document.getElementById('gameRoot');
    if (!root) return;

    const csrf = document.body.dataset.csrf;
    const notice = document.body.dataset.notice || '';
    let state = null;

    const classes = {
        Barbarian: { primary: 'Strength', complexity: 'Average', die: 'd12', features: ['Rage', 'Unarmored Defense', 'Weapon Mastery'] },
        Bard: { primary: 'Charisma', complexity: 'High', die: 'd8', features: ['Bardic Inspiration', 'Spellcasting', 'Weapon Mastery'] },
        Cleric: { primary: 'Wisdom', complexity: 'Average', die: 'd8', features: ['Divine Order', 'Spellcasting'] },
        Druid: { primary: 'Wisdom', complexity: 'High', die: 'd8', features: ['Druidic', 'Primal Order', 'Spellcasting'] },
        Fighter: { primary: 'Strength or Dexterity', complexity: 'Low', die: 'd10', features: ['Fighting Style', 'Second Wind', 'Weapon Mastery'] },
        Monk: { primary: 'Dexterity and Wisdom', complexity: 'High', die: 'd8', features: ['Martial Arts', 'Unarmored Defense'] },
        Paladin: { primary: 'Strength and Charisma', complexity: 'Average', die: 'd10', features: ['Lay On Hands', 'Spellcasting', 'Weapon Mastery'] },
        Ranger: { primary: 'Dexterity and Wisdom', complexity: 'Average', die: 'd10', features: ['Favored Enemy', 'Spellcasting', 'Weapon Mastery'] },
        Rogue: { primary: 'Dexterity', complexity: 'Average', die: 'd8', features: ['Expertise', 'Sneak Attack', 'Thieves Cant', 'Weapon Mastery'] },
        Sorcerer: { primary: 'Charisma', complexity: 'High', die: 'd6', features: ['Spellcasting', 'Innate Sorcery'] },
        Warlock: { primary: 'Charisma', complexity: 'High', die: 'd8', features: ['Eldritch Invocations', 'Pact Magic'] },
        Wizard: { primary: 'Intelligence', complexity: 'Average', die: 'd6', features: ['Spellcasting', 'Ritual Adept', 'Arcane Recovery'] },
    };

    const species = ['Dragonborn', 'Dwarf', 'Elf', 'Gnome', 'Goliath', 'Halfling', 'Human', 'Orc', 'Tiefling'];
    const backgrounds = ['Acolyte', 'Criminal', 'Sage', 'Soldier'];
    const alignments = ['Lawful Good', 'Neutral Good', 'Chaotic Good', 'Lawful Neutral', 'Neutral', 'Chaotic Neutral'];
    const oddsLabels = {
        impossible: 'Nearly impossible',
        unlikely: 'Unlikely',
        even: 'Even chance',
        likely: 'Likely',
        certain: 'Nearly certain',
    };

    const backgroundReference = {
        Acolyte: 'Insight and Religion, Calligrapher’s Supplies, Magic Initiate for Cleric spells.',
        Criminal: 'Sleight of Hand and Stealth, Thieves’ Tools, Alert.',
        Sage: 'Arcana and History, Calligrapher’s Supplies, Magic Initiate for Wizard spells.',
        Soldier: 'Athletics and Intimidation, one Gaming Set, Savage Attacker.',
    };

    const speciesReference = {
        Dragonborn: 'A draconic heritage with a breath weapon, damage resistance, Darkvision, and later spectral flight.',
        Dwarf: 'Poison resilience, deep Darkvision, added toughness, and a supernatural sense for stone.',
        Elf: 'Darkvision, an elven lineage, keen senses, fey ancestry, and a restorative trance.',
        Gnome: 'A small, quick minded hero with Darkvision, magical cunning, and a chosen gnomish lineage.',
        Goliath: 'A powerful giant descendant who draws a supernatural gift from giant ancestry.',
        Halfling: 'A small and nimble hero known for bravery, luck, and moving easily through a crowded fight.',
        Human: 'Resourceful and adaptable, with an extra skill, an extra Origin feat, and a burst of heroic inspiration.',
        Orc: 'Relentless and strong, with Darkvision, a powerful rush, and the endurance to resist falling.',
        Tiefling: 'Marked by a fiendish legacy that grants Darkvision, resistance, and inherited magic.',
    };

    async function api(action, data = null) {
        const options = { credentials: 'same-origin' };
        let url = `api.php?action=${encodeURIComponent(action)}`;
        if (data !== null) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf };
            options.body = JSON.stringify({ action, ...data });
            url = 'api.php';
        }
        const response = await fetch(url, options);
        const payload = await response.json().catch(() => ({ error: 'The server returned an unreadable response.' }));
        if (response.status === 401) {
            window.location.reload();
            throw new Error('Your session ended.');
        }
        if (!response.ok || payload.error) throw new Error(payload.error || 'Something went wrong.');
        return payload;
    }

    async function accountRequest(action, data = {}) {
        const response = await fetch('account.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ action, ...data }),
        });
        const payload = await response.json().catch(() => ({ error: 'The server returned an unreadable response.' }));
        if (response.status === 401) {
            window.location.reload();
            throw new Error('Your session ended.');
        }
        if (!response.ok || payload.error) throw new Error(payload.error || 'The account request failed.');
        return payload;
    }

    function showToast(message, tone = 'normal') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.dataset.tone = tone;
        toast.classList.add('show');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 3200);
    }

    function optionList(select, values) {
        select.replaceChildren(...values.map(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            return option;
        }));
    }

    function fillCharacter(character) {
        const form = document.getElementById('characterForm');
        Object.entries(character).forEach(([key, value]) => {
            const field = form.elements.namedItem(key);
            if (field) field.value = value ?? '';
        });
        updateModifiers();
        renderFeatures(character.class_name);
    }

    function updateModifiers() {
        const form = document.getElementById('characterForm');
        ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'].forEach(ability => {
            const score = Number(form.elements.namedItem(ability).value || 10);
            const modifier = Math.floor((score - 10) / 2);
            const output = document.querySelector(`[data-mod="${ability}"]`);
            output.textContent = modifier >= 0 ? `+${modifier}` : String(modifier);
        });
    }

    function renderFeatures(className) {
        const holder = document.getElementById('classFeatures');
        const reference = classes[className] || classes.Fighter;
        holder.replaceChildren(...reference.features.map(feature => {
            const tag = document.createElement('span');
            tag.textContent = feature;
            return tag;
        }));
    }

    function formatDate(value) {
        const date = new Date(String(value).replace(' ', 'T') + 'Z');
        return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('en-AU', {
            day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit'
        }).format(date);
    }

    function renderJournal(entries) {
        const timeline = document.getElementById('journalTimeline');
        timeline.replaceChildren(...entries.map(entry => {
            const article = document.createElement('article');
            article.className = `journal-entry entry-${entry.entry_type}`;
            const rail = document.createElement('div');
            rail.className = 'entry-rail';
            const dot = document.createElement('span');
            dot.className = 'entry-dot';
            rail.append(dot);
            const body = document.createElement('div');
            body.className = 'entry-body';
            const meta = document.createElement('div');
            meta.className = 'entry-meta';
            const type = document.createElement('span');
            type.textContent = entry.entry_type === 'guide'
                ? 'How to play'
                : (entry.entry_type === 'story' ? 'Guided story' : entry.entry_type);
            const date = document.createElement('time');
            date.textContent = formatDate(entry.created_at);
            meta.append(type, date);
            const title = document.createElement('h3');
            title.textContent = entry.title;
            const text = document.createElement('p');
            text.textContent = entry.body;
            body.append(meta, title, text);
            article.append(rail, body);
            return article;
        }));
    }

    function renderStoryGuide(guide) {
        const holder = document.getElementById('storyGuide');
        holder.replaceChildren();

        const heading = document.createElement('header');
        heading.className = 'story-guide-heading';
        const headingCopy = document.createElement('div');
        const chapter = document.createElement('p');
        chapter.className = 'eyebrow';
        chapter.textContent = guide.chapter;
        const title = document.createElement('h2');
        title.id = 'storyGuideTitle';
        title.textContent = guide.stage ? guide.stage.title : guide.completion.title;
        headingCopy.append(chapter, title);

        const progressWrap = document.createElement('div');
        progressWrap.className = 'story-progress';
        const progressText = document.createElement('span');
        const pageNumber = guide.stage ? guide.stage.number : guide.total_count;
        progressText.textContent = `Page ${pageNumber} of ${guide.total_count}`;
        const progress = document.createElement('progress');
        progress.max = guide.total_count;
        progress.value = guide.stage ? Math.max(1, guide.stage.number) : guide.total_count;
        progress.setAttribute('aria-label', `Guided chapter page ${pageNumber} of ${guide.total_count}`);
        progressWrap.append(progressText, progress);
        heading.append(headingCopy, progressWrap);
        holder.append(heading);

        if (guide.stage) {
            const location = document.createElement('p');
            location.className = 'story-location';
            location.textContent = guide.stage.location;
            holder.append(location);

            const prose = document.createElement('div');
            prose.className = 'story-prose';
            guide.stage.prose.forEach(paragraph => {
                const copy = document.createElement('p');
                copy.textContent = paragraph;
                prose.append(copy);
            });
            holder.append(prose);

            const choiceHeading = document.createElement('h3');
            choiceHeading.className = 'story-choice-heading';
            choiceHeading.textContent = 'Choose your approach';
            holder.append(choiceHeading);

            const choices = document.createElement('div');
            choices.className = 'story-choices';
            guide.stage.choices.forEach(choice => {
                const button = document.createElement('button');
                button.className = 'story-choice';
                button.type = 'button';
                button.dataset.storyStage = guide.stage.id;
                button.dataset.storyChoice = choice.id;
                const choiceTitle = document.createElement('strong');
                choiceTitle.textContent = choice.label;
                const lead = document.createElement('span');
                lead.textContent = choice.lead;
                const oracle = document.createElement('small');
                oracle.textContent = `${oddsLabels[choice.odds] || choice.odds}. Ask the Oracle: ${choice.question}`;
                button.append(choiceTitle, lead, oracle);
                choices.append(button);
            });
            holder.append(choices);

            const freedom = document.createElement('p');
            freedom.className = 'story-freedom';
            freedom.textContent = 'These are invitations, not limits. You can ignore them, write your own action in the journal, and use the Oracle freely.';
            holder.append(freedom);
        } else {
            const complete = document.createElement('div');
            complete.className = 'story-complete';
            const body = document.createElement('p');
            body.textContent = guide.completion.body;
            const prompt = document.createElement('p');
            prompt.className = 'story-complete-prompt';
            prompt.textContent = guide.completion.prompt;
            complete.append(body, prompt);
            holder.append(complete);
        }

        const lore = document.createElement('details');
        lore.className = 'story-lore';
        const summary = document.createElement('summary');
        const summaryTitle = document.createElement('strong');
        summaryTitle.textContent = 'World remembered';
        const count = document.createElement('span');
        count.textContent = guide.lore.length === 1 ? '1 discovery' : `${guide.lore.length} discoveries`;
        summary.append(summaryTitle, count);
        lore.append(summary);
        const loreGrid = document.createElement('div');
        loreGrid.className = 'story-lore-grid';
        guide.lore.forEach(item => {
            const article = document.createElement('article');
            const loreTitle = document.createElement('h3');
            loreTitle.textContent = item.title;
            const loreBody = document.createElement('p');
            loreBody.textContent = item.body;
            article.append(loreTitle, loreBody);
            loreGrid.append(article);
        });
        lore.append(loreGrid);
        holder.append(lore);
    }

    function renderTrackers() {
        const threadList = document.getElementById('threadList');
        threadList.replaceChildren(...state.threads.map(thread => {
            const item = document.createElement('li');
            item.className = thread.status === 'closed' ? 'closed' : '';
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.threadId = thread.id;
            button.setAttribute('aria-label', thread.status === 'closed' ? 'Reopen thread' : 'Close thread');
            button.textContent = thread.status === 'closed' ? '✓' : '○';
            const name = document.createElement('span');
            name.textContent = thread.name;
            item.append(button, name);
            return item;
        }));

        const npcList = document.getElementById('npcList');
        npcList.replaceChildren(...state.npcs.map(npc => {
            const item = document.createElement('li');
            const sigil = document.createElement('span');
            sigil.className = 'npc-sigil';
            sigil.textContent = npc.name.slice(0, 1).toUpperCase();
            const words = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = npc.name;
            const role = document.createElement('small');
            role.textContent = npc.role || 'Role still unknown';
            words.append(name, role);
            item.append(sigil, words);
            return item;
        }));
    }

    function renderState() {
        document.getElementById('adventureTitle').textContent = state.adventure.title;
        document.getElementById('story-heading').textContent = state.adventure.title;
        document.getElementById('sceneChip').textContent = `Scene ${state.adventure.current_scene}`;
        document.getElementById('chaosValue').textContent = state.adventure.chaos_factor;
        fillCharacter(state.character);
        renderStoryGuide(state.story_guide);
        renderJournal(state.journal);
        renderTrackers();
        renderAccount();
        if (state.oracle_history.length) renderOracle(state.oracle_history[0]);
    }

    function renderAccount() {
        const verified = Boolean(state.adventure.email_verified_at);
        const banner = document.getElementById('emailBanner');
        banner.hidden = verified;
        document.body.classList.toggle('has-email-banner', !verified);
        document.getElementById('accountEmail').textContent = state.adventure.email;
        const status = document.getElementById('verificationStatus');
        status.textContent = verified ? 'Email confirmed' : 'Confirmation waiting';
        status.classList.toggle('verified', verified);
        document.getElementById('resendVerification').hidden = verified;
    }

    function renderOracle(result) {
        const box = document.getElementById('oracleResult');
        box.classList.remove('empty');
        box.replaceChildren();
        const sigil = document.createElement('span');
        sigil.className = 'result-sigil';
        sigil.textContent = String(result.roll);
        const body = document.createElement('div');
        const eyebrow = document.createElement('p');
        eyebrow.className = 'eyebrow';
        eyebrow.textContent = result.event_triggered ? 'The world intervenes' : `Oracle roll ${result.roll}, target ${result.threshold || result.threshold_value}`;
        const answer = document.createElement('h3');
        answer.textContent = result.answer;
        body.append(eyebrow, answer);
        if (result.event_triggered) {
            const event = document.createElement('p');
            event.className = 'event-copy';
            event.textContent = `${result.event_focus}. Meaning: ${result.event_meaning}.`;
            body.append(event);
        }
        box.append(sigil, body);
    }

    async function load() {
        try {
            state = await api('state');
            renderState();
            root.setAttribute('aria-busy', 'false');
        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    optionList(document.getElementById('classSelect'), Object.keys(classes));
    optionList(document.getElementById('speciesSelect'), species);
    optionList(document.getElementById('backgroundSelect'), backgrounds);
    optionList(document.getElementById('alignmentSelect'), alignments);

    document.getElementById('characterForm').addEventListener('input', updateModifiers);
    document.getElementById('classSelect').addEventListener('change', event => renderFeatures(event.target.value));
    document.getElementById('characterForm').addEventListener('submit', async event => {
        event.preventDefault();
        const saveState = document.getElementById('saveState');
        saveState.textContent = 'Saving';
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            const result = await api('save_character', data);
            state.character = result.character;
            fillCharacter(state.character);
            saveState.textContent = 'Saved';
            showToast('Character saved');
        } catch (error) {
            saveState.textContent = 'Try again';
            showToast(error.message, 'error');
        }
    });

    document.getElementById('journalForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const result = await api('add_journal', Object.fromEntries(new FormData(form).entries()));
            state = result.state;
            renderState();
            form.reset();
            showToast('Added to the chronicle');
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('storyGuide').addEventListener('click', async event => {
        const button = event.target.closest('[data-story-choice]');
        if (!button) return;
        const choiceButtons = document.querySelectorAll('[data-story-choice]');
        choiceButtons.forEach(item => { item.disabled = true; });
        button.classList.add('resolving');
        try {
            const response = await api('guided_story', {
                stage_id: button.dataset.storyStage,
                choice_id: button.dataset.storyChoice,
            });
            state = response.state;
            renderState();
            renderOracle(response.result);
            showToast(`${response.result.answer}. A new page has entered the chronicle.`);
        } catch (error) {
            choiceButtons.forEach(item => { item.disabled = false; });
            button.classList.remove('resolving');
            showToast(error.message, 'error');
        }
    });

    document.getElementById('oracleForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const response = await api('oracle', Object.fromEntries(new FormData(form).entries()));
            renderOracle(response.result);
            form.elements.question.value = '';
            showToast(response.result.event_triggered ? 'A random event has entered the story' : 'The Oracle has answered');
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    async function changeChaos(change) {
        const next = Math.max(1, Math.min(9, Number(state.adventure.chaos_factor) + change));
        try {
            const response = await api('set_chaos', { chaos_factor: next });
            state.adventure.chaos_factor = response.chaos_factor;
            document.getElementById('chaosValue').textContent = response.chaos_factor;
        } catch (error) {
            showToast(error.message, 'error');
        }
    }
    document.getElementById('chaosDown').addEventListener('click', () => changeChaos(-1));
    document.getElementById('chaosUp').addEventListener('click', () => changeChaos(1));

    document.getElementById('meaningButton').addEventListener('click', async () => {
        try {
            const response = await api('meaning', {});
            const holder = document.getElementById('meaningWords');
            holder.replaceChildren(...response.words.map(word => {
                const span = document.createElement('span');
                span.textContent = word;
                return span;
            }));
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    document.getElementById('sceneForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const response = await api('scene', Object.fromEntries(new FormData(form).entries()));
            state = response.state;
            renderState();
            const result = document.getElementById('sceneResult');
            result.textContent = `${response.result.kind}. ${response.result.meaning ? `Meaning: ${response.result.meaning}. ` : ''}${response.result.guidance}`;
            form.reset();
            showToast(`Scene ${response.result.scene} opened`);
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    document.getElementById('threadForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const response = await api('add_thread', Object.fromEntries(new FormData(form).entries()));
            state.threads = response.threads;
            renderTrackers();
            form.reset();
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    document.getElementById('threadList').addEventListener('click', async event => {
        const button = event.target.closest('[data-thread-id]');
        if (!button) return;
        try {
            const response = await api('toggle_thread', { id: Number(button.dataset.threadId) });
            state.threads = response.threads;
            renderTrackers();
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    document.getElementById('npcForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const response = await api('add_npc', Object.fromEntries(new FormData(form).entries()));
            state.npcs = response.npcs;
            renderTrackers();
            form.reset();
        } catch (error) {
            showToast(error.message, 'error');
        }
    });

    function secureDie(sides) {
        const range = 0x100000000;
        const limit = Math.floor(range / sides) * sides;
        const values = new Uint32Array(1);
        do crypto.getRandomValues(values); while (values[0] >= limit);
        return (values[0] % sides) + 1;
    }

    document.querySelector('.dice-buttons').addEventListener('click', event => {
        const button = event.target.closest('[data-die]');
        if (!button) return;
        const sides = Number(button.dataset.die);
        const roll = secureDie(sides);
        const modifier = Math.max(-20, Math.min(20, Number(document.getElementById('diceModifier').value) || 0));
        const total = roll + modifier;
        const output = document.getElementById('diceOutput');
        output.textContent = modifier === 0 ? `${total}` : `${total} (${roll} ${modifier >= 0 ? '+' : '−'} ${Math.abs(modifier)})`;
        output.classList.remove('pop');
        requestAnimationFrame(() => output.classList.add('pop'));
    });

    document.querySelector('.mobile-tabs').addEventListener('click', event => {
        const tab = event.target.closest('[data-panel]');
        if (!tab) return;
        document.querySelectorAll('.mobile-tab').forEach(item => item.classList.toggle('active', item === tab));
        document.querySelectorAll('[data-panel-name]').forEach(panel => panel.classList.toggle('active', panel.dataset.panelName === tab.dataset.panel));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    const dialog = document.getElementById('compendiumDialog');
    document.getElementById('openCompendium').addEventListener('click', () => {
        renderReference('classes');
        dialog.showModal();
    });
    document.getElementById('closeCompendium').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    document.querySelector('.compendium-tabs').addEventListener('click', event => {
        const tab = event.target.closest('[data-reference]');
        if (!tab) return;
        document.querySelectorAll('.compendium-tabs button').forEach(button => button.classList.toggle('active', button === tab));
        renderReference(tab.dataset.reference);
    });

    const accountDialog = document.getElementById('accountDialog');
    document.getElementById('openAccount').addEventListener('click', () => accountDialog.showModal());
    document.getElementById('closeAccount').addEventListener('click', () => accountDialog.close());
    accountDialog.addEventListener('click', event => {
        if (event.target === accountDialog) accountDialog.close();
    });

    async function requestVerification(button) {
        button.disabled = true;
        try {
            const response = await accountRequest('resend_verification');
            showToast(response.message);
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('resendVerification').addEventListener('click', event => requestVerification(event.currentTarget));
    document.getElementById('bannerVerify').addEventListener('click', event => requestVerification(event.currentTarget));

    document.getElementById('deleteAccountForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await accountRequest('delete_account', Object.fromEntries(new FormData(form).entries()));
            window.location.assign(response.redirect || 'index.php');
        } catch (error) {
            showToast(error.message, 'error');
            button.disabled = false;
        }
    });

    function referenceCard(title, meta, text) {
        const card = document.createElement('article');
        const heading = document.createElement('h3');
        heading.textContent = title;
        const detail = document.createElement('p');
        detail.className = 'reference-meta';
        detail.textContent = meta;
        const copy = document.createElement('p');
        copy.textContent = text;
        card.append(heading, detail, copy);
        return card;
    }

    function renderReference(kind) {
        const holder = document.getElementById('compendiumContent');
        if (kind === 'classes') {
            holder.replaceChildren(...Object.entries(classes).map(([name, item]) => referenceCard(
                name,
                `Primary ability: ${item.primary}. Hit Die: ${item.die}. Complexity: ${item.complexity}.`,
                `Level one features: ${item.features.join(', ')}.`
            )));
            return;
        }
        if (kind === 'species') {
            holder.replaceChildren(...species.map(name => referenceCard(name, 'Playable SRD species', speciesReference[name])));
            return;
        }
        if (kind === 'backgrounds') {
            holder.replaceChildren(...backgrounds.map(name => referenceCard(name, 'Origin background', backgroundReference[name])));
            return;
        }
        holder.replaceChildren(
            referenceCard('Frame the scene', 'Start with context', 'State where your hero is, what they want, and what you expect will happen next.'),
            referenceCard('Choose or test', 'Keep agency with the player', 'Choose what is obvious. Roll a 5E test when ability decides. Ask the Oracle only when the world must answer.'),
            referenceCard('Interpret in context', 'Let meaning emerge', 'Use the answer and any meaning words with the people, goals, and facts already present. Prefer the first coherent idea.'),
            referenceCard('Close the scene', 'Adjust the tempo', 'Lower Chaos when your hero controlled the situation. Raise it when events escaped their control, then open the next scene.')
        );
    }

    load();
    if (notice) showToast(notice);
})();
