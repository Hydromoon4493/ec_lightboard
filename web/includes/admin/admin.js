/* Shared helpers + tab switching for the admin dashboard. */
const Admin = (function () {

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]
        ));
    }

    // fetch wrapper: JSON in/out, throws Error(message) on any non-2xx.
    async function api(url, options = {}) {
        const init = { method: options.method || 'GET', credentials: 'same-origin', headers: {} };
        if (options.body !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }
        const res = await fetch(url, init);
        let data = {};
        try { data = await res.json(); } catch (e) { /* non-JSON */ }

        if (res.status === 401) { window.location.href = 'login.php'; throw new Error('Please log in.'); }
        if (!res.ok) {
            const err = new Error(data.error || 'Something went wrong. Please try again.');
            err.status = res.status;
            throw err;
        }
        return data;
    }

    // Request times arrive as "YYYY-MM-DDTHH:MM" (Eastern); show them as written.
    function fmt(str) {
        const d = new Date(str);
        return isNaN(d) ? str : d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    }

    let flashTimer = null;
    function flash(message, type = 'success') {
        const el = document.getElementById('admin-flash');
        el.textContent = message;               // textContent, never innerHTML
        el.className = 'admin-flash ' + type;
        el.hidden = false;
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => { el.hidden = true; }, 5000);
    }

    // Tabs
    const tabs = document.querySelectorAll('.admin-tab');
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(t => {
            const active = t === tab;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active);
            document.getElementById('tab-' + t.dataset.tab).hidden = !active;
        });
    }));

    return { esc, api, fmt, flash };
})();