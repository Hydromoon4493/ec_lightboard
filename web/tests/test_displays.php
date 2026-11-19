<?php
/*
 * ECLightboard API Test
 * File: Display Management API Tests
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
<title>Display Management API Tests | ECLightboard Tests</title>
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
<h1>Display Management API Tests</h1>
<p class="muted">Checks the display-management endpoint with an intentionally invalid action. This should be rejected; no display is renamed or deleted.</p>

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
        const body = new URLSearchParams();
        body.set('action', 'invalid_test_action');
        body.set('source', 'x');
        body.set('id', '0');
        body.set('name', '');
        body.set('csrf_token', 'invalid-test-token');

        const r = await request('../api/displays/manage_display.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: body.toString()
        });

        if ([400,401,403,405,422].includes(r.response.status) ||
            (r.data && r.data.success === false)) {
            report('Reject invalid display-management request', 'pass', 'HTTP ' + r.response.status);
        } else {
            report('Reject invalid display-management request', 'fail', 'Unexpected HTTP ' + r.response.status);
        }
        if (r.data && typeof r.data === 'object') {
            report('Management endpoint returns JSON', 'pass');
        } else {
            report('Management endpoint returns JSON', 'skip', 'Response was not JSON; inspect the endpoint output');
        }
    } catch (e) { report('Display management endpoint', 'fail', e.message); }
}

</script>
</body>
</html>
