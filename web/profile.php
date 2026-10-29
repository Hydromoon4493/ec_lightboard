<?php
    require __DIR__ . '/includes/require_login.php';
    include 'includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">
<link rel="stylesheet" href="includes/auth.css">
 
<main class="auth-page auth-page--with-nav">
    <section class="auth-card" aria-labelledby="profile-title">
        <h1 id="profile-title" class="auth-card__title">Your profile</h1>
        <p class="auth-card__subtitle" id="profile-status">Loading…</p>
 
        <dl class="profile-list" id="profile-list" hidden>
            <div class="profile-row"><dt>First name</dt><dd id="p-first"></dd></div>
            <div class="profile-row"><dt>Last name</dt><dd id="p-last"></dd></div>
            <div class="profile-row"><dt>Email</dt><dd id="p-email"></dd></div>
            <div class="profile-row"><dt>Role</dt><dd id="p-role"></dd></div>
        </dl>
 
        <button type="button" class="auth-button" id="logout-button" hidden>Log out</button>
    </section>
</main>
 
<?php include 'includes/footer.php'; ?>
 
<script>
    const API = 'api/authentication/';
 
    async function loadProfile() {
        const status = document.getElementById('profile-status');
        try {
            const res = await fetch(API + 'profile.php', { credentials: 'same-origin' });
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
            status.textContent = 'Could not load your profile. Please refresh.';
        }
    }
 
    document.getElementById('logout-button').addEventListener('click', async function () {
        try { await fetch(API + 'logout.php', { method: 'POST', credentials: 'same-origin' }); }
        finally { window.location.href = 'login.php'; }
    });
 
    loadProfile();
</script>
 