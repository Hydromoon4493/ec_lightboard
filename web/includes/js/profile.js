const AUTH_API = 'api/authentication/';
const SCHEDULE_API = 'api/schedule/schedule.php';

/* ========================================
   API Helpers
   ======================================== */

async function readResponse(res) {
    try {
        return await res.json();
    } catch (error) {
        return {};
    }
}

async function postJSON(endpoint, data) {
    const res = await fetch(AUTH_API + endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });

    const body = await readResponse(res);

    if (res.status === 401) {
        window.location.href = 'login.php';
        throw new Error('Your session has expired.');
    }

    if (!res.ok) {
        throw new Error(body.error || 'Something went wrong.');
    }

    return body;
}

/* ========================================
   Load Profile
   ======================================== */

async function loadProfile() {
    const status = document.getElementById('profile-status');
    const profileList = document.getElementById('profile-list');
    const logoutButton = document.getElementById('logout-button');

    try {
        const res = await fetch(AUTH_API + 'profile.php', {
            credentials: 'same-origin'
        });

        if (res.status === 401) {
            window.location.href = 'login.php';
            return;
        }

        if (!res.ok) {
            throw new Error(`Profile request failed (${res.status})`);
        }

        const p = await res.json();

        document.getElementById('p-first').textContent = p.firstName ?? '';
        document.getElementById('p-last').textContent = p.lastName ?? '';
        document.getElementById('p-email').textContent = p.email ?? '';
        document.getElementById('p-role').textContent = p.role ?? '';

        status.hidden = true;
        profileList.hidden = false;

        // The logout button belongs to the navbar, so it may not exist.
        if (logoutButton) {
            logoutButton.hidden = false;
        }

    } catch (error) {
        console.error('Profile loading error:', error);
        status.textContent = 'Could not load profile details.';
        status.hidden = false;
    }
}

/* ========================================
   Change Email
   ======================================== */

const changeEmailForm = document.getElementById('change-email-form');

if (changeEmailForm) {
    changeEmailForm.addEventListener('submit', async event => {
        event.preventDefault();

        const status = document.getElementById('email-change-status');
        const submitButton = changeEmailForm.querySelector('button[type="submit"]');

        const currentPassword =
            document.getElementById('email-current-password').value;

        const newEmail =
            document.getElementById('email-new').value.trim();

        status.textContent = '';
        status.className = 'status-message form-status';

        if (!currentPassword || !newEmail) {
            status.textContent = 'Please complete all fields.';
            status.classList.add('status-error');
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Updating…';

        try {
            const data = await postJSON('change_email.php', {
                currentPassword: currentPassword,
                newEmail: newEmail
            });

            status.textContent = data.message || 'Email updated successfully.';
            status.classList.add('status-success');

            // Update the email shown in the profile immediately.
            document.getElementById('p-email').textContent =
                data.email || newEmail;

            // Clear the form.
            changeEmailForm.reset();

        } catch (error) {
            console.error('Email change error:', error);

            status.textContent = error.message;
            status.classList.add('status-error');

        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Update Email';
        }
    });
}

/* ========================================
   Change Password
   ======================================== */

const changePasswordForm = document.getElementById('change-password-form');

if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async event => {
        event.preventDefault();

        const status = document.getElementById('password-change-status');
        const submitButton = changePasswordForm.querySelector('button[type="submit"]');

        const currentPassword =
            document.getElementById('password-current').value;

        const newPassword =
            document.getElementById('password-new').value;

        const confirmPassword =
            document.getElementById('password-confirm').value;

        status.textContent = '';
        status.className = 'status-message form-status';

        if (!currentPassword || !newPassword || !confirmPassword) {
            status.textContent = 'Please complete all fields.';
            status.classList.add('status-error');
            return;
        }

        if (newPassword.length < 8) {
            status.textContent = 'Your new password must be at least 8 characters.';
            status.classList.add('status-error');
            return;
        }

        if (newPassword !== confirmPassword) {
            status.textContent = 'Your new passwords do not match.';
            status.classList.add('status-error');
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Updating…';

        try {
            const data = await postJSON('change_password.php', {
                currentPassword: currentPassword,
                newPassword: newPassword,
                confirmPassword: confirmPassword
            });

            status.textContent = data.message || 'Password updated successfully.';
            status.classList.add('status-success');

            // Clear all password fields after success.
            changePasswordForm.reset();

        } catch (error) {
            console.error('Password change error:', error);

            status.textContent = error.message;
            status.classList.add('status-error');

        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Update Password';
        }
    });
}

/* ========================================
   Load Signage Requests
   ======================================== */

async function loadRequests() {
    const statusEl = document.getElementById('requests-status');
    const listEl = document.getElementById('requests-list');

    try {
        const res = await fetch(SCHEDULE_API, {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error(`Requests request failed (${res.status})`);
        }

        const data = await res.json();

        if (!Array.isArray(data.requests)) {
            throw new Error('Invalid requests response.');
        }

        listEl.innerHTML = '';
        statusEl.hidden = true;
        listEl.hidden = false;

        if (data.requests.length === 0) {
            listEl.innerHTML =
                '<p class="empty-msg">You have no display requests yet.</p>';
            return;
        }

        data.requests.forEach(req => {
            const card = document.createElement('div');

            const allowedStatuses = [
                'pending',
                'approved',
                'rejected',
                'cancelled',
                'completed'
            ];

            const safeStatus = allowedStatuses.includes(req.status)
                ? req.status
                : 'unknown';

            card.className = `request-item request-status-${safeStatus}`;

            const startDate = new Date(req.start);

            const canCancel =
                ['pending', 'approved'].includes(req.status) &&
                !isNaN(startDate.getTime()) &&
                startDate > new Date();

            const dateFormatted = req.createdAt
                ? new Date(req.createdAt + 'T00:00:00').toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                })
                : '';

            card.innerHTML = `
                <div class="request-main">
                    <h3>${escapeHtml(req.displayName)}</h3>
                    <span class="status-badge badge-${safeStatus}">
                        ${escapeHtml(safeStatus)}
                    </span>
                </div>

                <div class="request-details">
                    <p><strong>Start:</strong> ${escapeHtml(req.start)}</p>
                    <p><strong>End:</strong> ${escapeHtml(req.end)}</p>
                    <p class="request-date">
                        <strong>Requested:</strong> ${dateFormatted}
                    </p>
                </div>

                ${req.note
                    ? `<p class="request-note"><strong>Note:</strong> ${escapeHtml(req.note)}</p>`
                    : ''}

                ${canCancel
                    ? `<button class="cancel-btn" data-id="${Number(req.eventID)}">Cancel Request</button>`
                    : ''}
            `;

            listEl.appendChild(card);
        });

    } catch (error) {
        console.error('Requests loading error:', error);
        statusEl.textContent = 'Could not load display requests.';
        statusEl.hidden = false;
    }
}

/* ========================================
   Cancel Request
   ======================================== */

async function cancelRequest(id) {
    if (!confirm('Are you sure you want to cancel this display request?')) {
        return;
    }

    try {
        const res = await fetch(
            `${SCHEDULE_API}?id=${encodeURIComponent(id)}`,
            {
                method: 'DELETE',
                credentials: 'same-origin'
            }
        );

        const data = await readResponse(res);

        if (!res.ok) {
            throw new Error(data.error || 'Failed to cancel request');
        }

        await loadRequests();

    } catch (error) {
        console.error('Cancel request error:', error);
        alert(error.message);
    }
}

/* ========================================
   Utility
   ======================================== */

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char]));
}

/* ========================================
   Event Listeners
   ======================================== */

// Cancel button event delegation.
const requestsList = document.getElementById('requests-list');

if (requestsList) {
    requestsList.addEventListener('click', event => {
        const button = event.target.closest('.cancel-btn');

        if (button && button.dataset.id) {
            cancelRequest(button.dataset.id);
        }
    });
}

// Logout button is optional because it is generated by the navbar.
const logoutButton = document.getElementById('logout-button');

if (logoutButton) {
    logoutButton.addEventListener('click', async () => {
        try {
            await fetch(AUTH_API + 'logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } finally {
            window.location.href = 'login.php';
        }
    });
}

/* ========================================
   Initial Load
   ======================================== */

loadProfile();
loadRequests();
