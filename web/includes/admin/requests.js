/* Pending + Upcoming request lists. */
(function () {
    const API = 'api/schedule/schedule.php';

    const pendingList = document.getElementById('pending-list');
    const pendingStatus = document.getElementById('pending-status');
    const upcomingList = document.getElementById('upcoming-list');
    const upcomingStatus = document.getElementById('upcoming-status');
    const badge = document.getElementById('pending-badge');
    let upcomingLoaded = false;

    function card(r, mode) {
        const who = r.user
            ? `${Admin.esc(r.user.name || 'Unknown user')} · ${Admin.esc(r.user.email || '')}`
            : '';
        const actions = mode === 'pending'
            ? `<button type="button" class="btn btn-small btn-approve" data-act="approved">Approve</button>
               <button type="button" class="btn btn-small btn-deny" data-act="denied">Deny</button>`
            : `<button type="button" class="btn btn-small btn-deny" data-act="cancel">Cancel</button>`;

        return `
            <article class="req-card" data-id="${r.eventID}">
                <img class="req-thumb"
                     src="api/thumbnail.php?src=${encodeURIComponent(r.displaySource)}&id=${r.displayID}"
                     alt="" loading="lazy">
                <div class="req-info">
                    <h3>${Admin.esc(r.displayName || 'Deleted display')}</h3>
                    <p>${who}</p>
                    <p>${Admin.fmt(r.start)} → ${Admin.fmt(r.end)} (ET)</p>
                    ${r.note ? `<p class="req-note">“${Admin.esc(r.note)}”</p>` : ''}
                </div>
                <div class="req-actions">${actions}</div>
            </article>`;
    }

    function render(listEl, statusEl, requests, mode, emptyText) {
        statusEl.hidden = true;
        listEl.innerHTML = requests.length
            ? requests.map(r => card(r, mode)).join('')
            : `<p class="empty-msg">${emptyText}</p>`;
        listEl.querySelectorAll('.req-thumb').forEach(img =>
            img.addEventListener('error', () => img.classList.add('is-broken')));
    }

    function setBadge(n) {
        badge.textContent = n;
        badge.hidden = n === 0;
    }

    async function loadPending() {
        try {
            const data = await Admin.api(`${API}?scope=all&status=pending&sort=start`);
            render(pendingList, pendingStatus, data.requests, 'pending', 'No pending requests.');
            setBadge(data.requests.length);
        } catch (e) {
            pendingStatus.hidden = false;
            pendingStatus.textContent = 'Could not load pending requests.';
        }
    }

    async function loadUpcoming() {
        try {
            const data = await Admin.api(`${API}?scope=all&status=approved&upcoming=1&sort=start`);
            render(upcomingList, upcomingStatus, data.requests, 'upcoming', 'No upcoming approved requests.');
        } catch (e) {
            upcomingStatus.hidden = false;
            upcomingStatus.textContent = 'Could not load upcoming requests.';
        }
    }

    // Remove a card from the DOM right away and keep the badge in sync.
    function removePendingCard(cardEl) {
        cardEl.remove();
        const left = pendingList.querySelectorAll('.req-card').length;
        setBadge(left);
        if (left === 0) pendingList.innerHTML = '<p class="empty-msg">No pending requests.</p>';
    }

    pendingList.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const cardEl = btn.closest('.req-card');
        const status = btn.dataset.act;

        if (status === 'denied' && !confirm('Deny this request?')) return;

        cardEl.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res = await Admin.api(`${API}?id=${cardEl.dataset.id}`, {
                method: 'PATCH',
                body: { status: status }
            });
            Admin.flash(res.message);
            removePendingCard(cardEl);
            if (status === 'approved' && upcomingLoaded) loadUpcoming();
        } catch (err) {
            Admin.flash(err.message, 'error');   // e.g. 409 conflict / already handled
            loadPending();                        // resync with the server
        }
    });

    upcomingList.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act="cancel"]');
        if (!btn) return;
        const cardEl = btn.closest('.req-card');

        if (!confirm('Cancel this approved request? The user will see it as cancelled.')) return;

        btn.disabled = true;
        try {
            const res = await Admin.api(`${API}?id=${cardEl.dataset.id}`, { method: 'DELETE' });
            Admin.flash(res.message);
        } catch (err) {
            Admin.flash(err.message, 'error');
        }
        loadUpcoming();
    });

    loadPending();
    document.querySelector('[data-tab="upcoming"]').addEventListener('click', () => {
        if (!upcomingLoaded) { upcomingLoaded = true; loadUpcoming(); }
    });
})();