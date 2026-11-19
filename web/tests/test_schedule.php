<?php
/*
 * ECLightboard API Test
 * File: Schedule API Tests
 *
 * Standalone browser-based test page.
 * Open this file directly in a browser and click Run Tests.
 *
 * Tests use same-origin fetch requests and do not require PHPUnit.
 * Tests are designed to avoid intentionally changing or deleting
 * existing user data.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule API Tests | ECLightboard Tests</title>
<style>
body{font-family:Arial,sans-serif;margin:32px auto;padding:0 18px;max-width:900px;color:#222;background:#f7f8fa}
main{background:#fff;padding:28px;border:1px solid #e5e7eb;border-radius:12px}
h1{margin-top:0}.muted{color:#666;line-height:1.5}
button{padding:11px 18px;border:0;border-radius:7px;background:#1f4f86;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
button:disabled{opacity:.55;cursor:wait}
li{margin:10px 0;line-height:1.45}.pass{color:#16803c}.fail{color:#c62828}.skip{color:#946200}
#summary{font-weight:bold;margin-top:20px;padding-top:14px;border-top:1px solid #ddd}
code{background:#f0f1f3;padding:2px 5px;border-radius:4px}
</style>
</head>
<body>
<main>
<h1>Schedule API Tests</h1>
<p class="muted">Checks the schedule endpoint using a read-only GET request. It does not create, approve, cancel, or delete any requests.</p>

<button type="button" id="run-tests">Run Tests</button>
<ul id="results"></ul>
<p id="summary">Tests have not run yet.</p>
</main>
<script>
const results = document.getElementById('results');
const summary = document.getElementById('summary');
const runButton = document.getElementById('run-tests');
let passed = 0, failed = 0, skipped = 0;

function report(name, status, detail = '') {
    const li = document.createElement('li');
    li.className = status;
    const label = status === 'pass' ? 'PASS' : status === 'fail' ? 'FAIL' : 'SKIP';
    li.textContent = label + ': ' + name + (detail ? ' — ' + detail : '');
    results.appendChild(li);
    if (status === 'pass') passed++;
    else if (status === 'fail') failed++;
    else skipped++;
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        ...options
    });
    const raw = await response.text();
    let data = null;
    try { data = raw ? JSON.parse(raw) : null; } catch (e) {}
    return { response, raw, data };
}

async function runTests() {
    results.innerHTML = '';
    passed = failed = skipped = 0;
    runButton.disabled = true;
    summary.textContent = 'Running tests…';
    try {
        await runSuite();
    } catch (error) {
        report('Test runner', 'fail', error.message || String(error));
    }
    summary.textContent = `Tests Passed: ${passed} | Tests Failed: ${failed} | Skipped: ${skipped}`;
    runButton.disabled = false;
}

runButton.addEventListener('click', runTests);


async function runSuite() {
    try {
        const r = await request('../api/schedule/schedule.php');
        if (r.response.status === 200 && r.data && typeof r.data === 'object') {
            report('Retrieve schedule data', 'pass', 'HTTP 200 JSON response');
        } else if ([401,403].includes(r.response.status)) {
            report('Schedule endpoint access control', 'pass', 'HTTP ' + r.response.status + ' (login/permission required)');
        } else {
            report('Retrieve schedule data', 'fail', 'HTTP ' + r.response.status + (r.raw ? ' — ' + r.raw.slice(0,120) : ''));
        }
    } catch (e) { report('Retrieve schedule data', 'fail', e.message); }
}

</script>
</body>
</html>
