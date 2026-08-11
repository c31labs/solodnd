(() => {
    'use strict';

    const mode = document.body.dataset.accountMode;
    const status = document.getElementById('tokenStatus');
    const returnLink = document.getElementById('tokenReturn');
    const parameters = new URLSearchParams(window.location.hash.slice(1));
    const token = parameters.get('token') || '';
    history.replaceState(null, '', window.location.pathname);

    async function submit(payload) {
        const response = await fetch('account.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({ error: 'The server returned an unreadable response.' }));
        if (!response.ok || result.error) throw new Error(result.error || 'The account request failed.');
        return result;
    }

    function render(message, isError = false) {
        status.replaceChildren();
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        status.append(paragraph);
        status.classList.toggle('error', isError);
        returnLink.hidden = false;
    }

    if (mode === 'verify') {
        if (!/^[a-f0-9]{64}$/.test(token)) {
            render('This confirmation link is incomplete. Request a new link from Account settings.', true);
        } else {
            submit({ action: 'verify_email', token })
                .then(result => render(result.message))
                .catch(error => render(error.message, true));
        }
    }

    if (mode === 'reset') {
        const form = document.getElementById('resetForm');
        if (!/^[a-f0-9]{64}$/.test(token)) {
            form.hidden = true;
            render('This reset link is incomplete. Request a new one from the sign in page.', true);
        }
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button');
            button.disabled = true;
            const values = Object.fromEntries(new FormData(form).entries());
            try {
                const result = await submit({ action: 'reset_password', token, ...values });
                form.hidden = true;
                render(result.message);
            } catch (error) {
                render(error.message, true);
            } finally {
                button.disabled = false;
            }
        });
    }
})();
