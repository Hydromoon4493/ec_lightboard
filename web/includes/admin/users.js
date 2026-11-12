/* Users tab: Name | Email | Role | Promote/Demote */
(function () {
    const API = 'api/users/users.php';
    const state = { page: 1, limit: 20, search: '', role: '' };

    const body = document.getElementById('users-body');
    const summary = document.getElementById('users-summary');
    const pageEl = document.getElementById('users-page');
    const prevBtn = document.getElementById('users-prev');
    const nextBtn = document.getElementById('users-next');
    let lastPages = 1;

    async function load() {
        const q = new URLSearchParams({
            page: state.page, limit: state.limit, search: state.search, role: state.role
        });
        try {
            const data = await Admin.api(API + '?' + q);
            lastPages = data.pages;
            state.page = data.page;
            render(data);
        } catch (e) {
            body.innerHTML = '<tr><td colspan="4" class="table-empty">Could not load users.</td></tr>';
            Admin.flash(e.message, 'error');
        }
    }

    function render(data) {
        if (data.users.length === 0) {
            body.innerHTML = '<tr><td colspan="4" class="table-empty">No users found.</td></tr>';
        } else {
            body.innerHTML = data.users.map(u => {
                const isAdmin = u.role === 'admin';
                const isMe = u.userID === data.me;
                const target = isAdmin ? 'user' : 'admin';
                const label = isMe ? 'You' : (isAdmin ? 'Demote' : 'Promote');
                const cls = isAdmin ? 'btn-demote' : 'btn-promote';
                return `
                    <tr>
                        <td>${Admin.esc(u.firstName)} ${Admin.esc(u.lastName)}</td>
                        <td>${Admin.esc(u.email)}</td>
                        <td><span class="role-badge role-${Admin.esc(u.role)}">${Admin.esc(u.role)}</span></td>
                        <td>
                            <button type="button" class="btn btn-small ${cls}"
                                    data-id="${u.userID}" data-role="${target}"
                                    data-name="${Admin.esc(u.firstName + ' ' + u.lastName)}"
                                    ${isMe ? 'disabled' : ''}>${label}</button>
                        </td>
                    </tr>`;
            }).join('');
        }

        const from = data.total === 0 ? 0 : (data.page - 1) * data.limit + 1;
        const to = Math.min(data.page * data.limit, data.total);
        summary.textContent = `Showing ${from}–${to} of ${data.total}`;
        pageEl.textContent = `Page ${data.page} of ${data.pages}`;
        prevBtn.disabled = data.page <= 1;
        nextBtn.disabled = data.page >= data.pages;
    }

    body.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-id]');
        if (!btn) return;

        const verb = btn.dataset.role === 'admin' ? 'promote' : 'demote';
        const to = btn.dataset.role === 'admin' ? 'an admin' : 'a regular user';
        if (!confirm(`Are you sure you want to ${verb} ${btn.dataset.name} to ${to}?`)) return;

        btn.disabled = true;
        try {
            const res = await Admin.api(`${API}?id=${btn.dataset.id}`, {
                method: 'PATCH',
                body: { role: btn.dataset.role }
            });
            Admin.flash(`${btn.dataset.name}: ${res.message}`);
        } catch (err) {
            Admin.flash(err.message, 'error');
        }
        load(); // refresh either way so the list is never stale
    });

    // Controls
    let searchTimer = null;
    document.getElementById('user-search').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { state.search = e.target.value.trim(); state.page = 1; load(); }, 300);
    });
    document.getElementById('user-role-filter').addEventListener('change', (e) => {
        state.role = e.target.value; state.page = 1; load();
    });
    document.getElementById('user-limit').addEventListener('change', (e) => {
        state.limit = Number(e.target.value); state.page = 1; load();
    });
    prevBtn.addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
    nextBtn.addEventListener('click', () => { if (state.page < lastPages) { state.page++; load(); } });

    load();
})();