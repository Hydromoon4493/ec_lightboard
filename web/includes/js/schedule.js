/* Sends the request to POST api/schedule.php as JSON. */
(function () {
    const form = document.getElementById('scheduleForm');
    const start = document.getElementById('start');
    const end = document.getElementById('end');
    const msg = document.getElementById('scheduleError');
    const submit = form.querySelector('button[type="submit"]');

    function show(text, ok) {
        msg.textContent = text;
        msg.classList.toggle('schedule-success', ok);
    }

    start.addEventListener('change', function () {
        end.min = start.value || end.min;
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        show('', false);

        if (end.value <= start.value) {
            show('End time must be after the start time.', false);
            return;
        }

        if (new Date(end.value) - new Date(start.value) > 72 * 3600 * 1000) {
            show('A display can be scheduled for at most 72 hours.', false);
            return;
        }

        // Dropdown value looks like "p:12" or "c:3"
        const [displaySource, displayID] = form.display.value.split(':');

        submit.disabled = true;
        try {
            const res = await fetch('api/schedule/schedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    displaySource: displaySource,
                    displayID: Number(displayID),
                    start: start.value,
                    end: end.value,
                    note: form.notes.value
                })
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok) {
                show(data.message || 'Request submitted.', true);
                form.reset();
            } else {
                show(data.error || 'Something went wrong. Please try again.', false);
            }
        } catch (err) {
            show('Could not reach the server. Please try again.', false);
        } finally {
            submit.disabled = false;
        }
    });
})();