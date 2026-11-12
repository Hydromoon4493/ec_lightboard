// Wires the signup and login forms to the API.
(function () {
    // Relative to web/login.php. Works when the site root is the project root
    // (so pages live at /web/... and the API at /api/...).
    const API = 'api/authentication/';

    async function postJSON(endpoint, data) {
        const res = await fetch(API + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });
        let body = {};
        try { body = await res.json(); } catch (e) { /* non-JSON response */ }
        return { ok: res.ok, body: body };
    }

    function wire(formId, endpoint, buildPayload) {
        const form = document.getElementById(formId);
        if (!form) return;

        const errorEl = document.getElementById('form-error');
        const button = form.querySelector('button[type="submit"]');

        function showError(msg) {
            errorEl.textContent = msg; // textContent, never innerHTML
            errorEl.hidden = false;
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            errorEl.hidden = true;

            const data = buildPayload(new FormData(form));
            if (data.confirmPassword !== undefined && data.password !== data.confirmPassword) {
                showError('Passwords do not match.');
                return;
            }

            button.disabled = true;
            try {
                const result = await postJSON(endpoint, data);
                if (result.ok) {
                    window.location.href = 'profile.php';
                } else {
                    showError(result.body.error || 'Something went wrong. Please try again.');
                }
            } catch (err) {
                showError('Could not reach the server. Please try again.');
            } finally {
                button.disabled = false;
            }
        });
    }

    wire('signup-form', 'signup.php', function (f) {
        return {
            firstName: f.get('first_name'),
            lastName: f.get('last_name'),
            email: f.get('email'),
            password: f.get('password'),
            confirmPassword: f.get('confirm_password')
        };
    });

    wire('login-form', 'login.php', function (f) {
        return { email: f.get('email'), password: f.get('password') };
    });
})();