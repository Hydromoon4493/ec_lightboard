const AUTH_API = 'api/authentication/';
const SCHEDULE_API = 'api/schedule/schedule.php';

async function loadProfile() {
    const status = document.getElementById('profile-status');
    try {
        const res = await fetch(AUTH_API + 'profile.php', { credentials: 'same-origin' });
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        if (!res.ok) throw new Error();
        const p = await res.json();

        document.getElementById('p-first').textContent = p.firstName;
        document.getElementById('p-last').textContent = p.lastName;
        document.getElementById('p-email').textContent = p.email;
        document.getElementById('p-role').textContent = p.role;

        status.hidden = true;
        document.getElementById('profile-list').hidden = false;
        document.getElementById('logout-button').hidden = false;
    } catch (e) {
        status.textContent = 'Could not load profile details.';
    }
}

async function loadRequests() {
    const statusEl = document.getElementById('requests-status');
    const listEl = document.getElementById('requests-list');

    try {
        const res = await fetch(SCHEDULE_API, { credentials: 'same-origin' });
        if (!res.ok) throw new Error();
        const data = await res.json();

        statusEl.hidden = true;
        listEl.hidden = false;
        listEl.innerHTML = '';

        if (data.requests.length === 0) {
            listEl.innerHTML = '<p class="empty-msg">You have no display requests yet.</p>';
            return;
        }

        data.requests.forEach(req => {
            const card = document.createElement('div');
            card.className = `request-item request-status-${req.status}`;

            const now = new Date();
            const startDate = new Date(req.start);
            const canCancel = (req.status === 'pending' || req.status === 'approved') && startDate > now;
            const dateFormatted = req.createdAt ? new Date(req.createdAt + 'T00:00:00').toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }) : '';

            card.innerHTML = `
                <div class="request-main">
                    <h3>${escapeHtml(req.displayName)}</h3>
                    <span class="status-badge badge-${req.status}">${req.status}</span>
                </div>
                <div class="request-details">
                    <p><strong>Start:</strong> ${req.start}</p>
                    <p><strong>End:</strong> ${req.end}</p>
                    <p class="request-date"><strong>Requested:</strong> ${dateFormatted}</p>
                </div>
                ${req.note ? `<p class="request-note"><strong>Note:</strong> ${escapeHtml(req.note)}</p>` : ''}
                ${canCancel ? `<button class="cancel-btn" data-id="${req.eventID}">Cancel Request</button>` : ''}
            `;
            listEl.appendChild(card);
        });
    } catch (e) {
        statusEl.textContent = 'Could not load display requests.';
    }
}

async function cancelRequest(id) {
    if (!confirm('Are you sure you want to cancel this display request?')) return;

    try {
        const res = await fetch(`${SCHEDULE_API}?id=${id}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed to cancel request');

        loadRequests(); // Refresh the requests list
    } catch (e) {
        alert(e.message);
    }
}

// Event listener delegation for dynamically generated cancel buttons
document.getElementById('requests-list').addEventListener('click', (e) => {
    if (e.target.classList.contains('cancel-btn')) {
        const id = e.target.getAttribute('data-id');
        if (id) {
            cancelRequest(id);
        }
    }
});

function formatDate(isoStr) {
    return new Date(isoStr).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

document.getElementById('logout-button').addEventListener('click', async function () {
    try { await fetch(AUTH_API + 'logout.php', { method: 'POST', credentials: 'same-origin' }); }
    finally { window.location.href = 'login.php'; }
});

loadProfile();
loadRequests();