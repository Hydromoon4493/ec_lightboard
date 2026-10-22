/*
 * Author: Sam Vossen 2026.10.15
 * Filename: includes/lightboard/lightboard.js
 *
 * Browser side of the Lightboard page.
 *
 * Flow: POST api/display_jobs.php -> get job_id -> poll api/job_status.php
 * every 2 seconds until the job is done, failed or cancelled.
 */

const POLL_INTERVAL_MS = 2000;
const POLL_TIMEOUT_MS  = 6 * 60 * 1000;

function setButtonsDisabled(disabled) {
    document.querySelectorAll('.gallery-item button').forEach(function (b) {
        b.disabled = disabled;
    });
}

function clearStatus() {
    document.getElementById('statusList').textContent = '';
    document.getElementById('resultBox').textContent = '';
    document.getElementById('resultBox').className = 'result-box';
}

function addStatus(message) {
    const line = document.createElement('div');
    line.className = 'status-line';
    line.textContent = message;
    document.getElementById('statusList').appendChild(line);
}

function showResult(ok, message) {
    const box = document.getElementById('resultBox');
    box.textContent = message;
    box.className = 'result-box ' + (ok ? 'result-success' : 'result-error');
}

function showPreview(key, id) {
    document.getElementById('previewImage').src =
        'api/thumbnail.php?src=' + encodeURIComponent(key) + '&id=' + encodeURIComponent(id);
    document.getElementById('previewCard').style.display = '';
}

function sleep(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
}

async function readJson(response) {
    try {
        return await response.json();
    } catch (e) {
        return {};
    }
}

async function pollJob(jobId) {
    const started = Date.now();
    let lastStatus = '';

    while (Date.now() - started < POLL_TIMEOUT_MS) {
        const res  = await fetch('api/job_status.php?id=' + encodeURIComponent(jobId), { cache: 'no-store' });
        const data = await readJson(res);

        if (!res.ok) {
            throw new Error(data.error || 'Could not check the job status.');
        }

        if (data.status !== lastStatus) {
            lastStatus = data.status;
            addStatus(data.message);
        }

        if (data.status === 'done') {
            showResult(true, data.message);
            return;
        }

        if (data.status === 'failed' || data.status === 'cancelled') {
            showResult(false, data.message);
            return;
        }

        await sleep(POLL_INTERVAL_MS);
    }

    showResult(false, 'Timed out waiting for the Lightboard. Is it powered on and connected?');
}

/* Called by the Display buttons in display.php. */
async function displayImage(key, id, button) {
    const brightness = parseInt(document.getElementById('b').value, 10) || 35;

    setButtonsDisabled(true);
    clearStatus();
    showPreview(key, id);
    addStatus('Sending request...');

    try {
        const res = await fetch('api/display_jobs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ src: key, id: id, brightness: brightness })
        });
        const data = await readJson(res);

        if (!res.ok || !data.job_id) {
            throw new Error(data.error || 'The request failed.');
        }

        await pollJob(data.job_id);
    } catch (err) {
        showResult(false, err.message || 'Something went wrong.');
    } finally {
        setButtonsDisabled(false);
    }
}