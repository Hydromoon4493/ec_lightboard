<?php
    require __DIR__ . '/includes/require_login.php';
    include 'includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">
<link rel="stylesheet" href="includes/profile.css">
<script src="includes/profile.js" defer></script>

<main class="bodyDefault profile-layout">
    
    <!-- Left Column: User Details -->
    <aside class="profile-sidebar">
        <section class="profile-card">
            <h1>Your Profile</h1>
            <p id="profile-status" class="status-message">Loading details…</p>

            <dl class="profile-list" id="profile-list" hidden>
                <div class="profile-row"><dt>First name</dt><dd id="p-first"></dd></div>
                <div class="profile-row"><dt>Last name</dt><dd id="p-last"></dd></div>
                <div class="profile-row"><dt>Email</dt><dd id="p-email"></dd></div>
                <div class="profile-row"><dt>Role</dt><dd id="p-role"></dd></div>
            </dl>

            <button type="button" class="logout-btn" id="logout-button" hidden>Log out</button>
        </section>
    </aside>

    <!-- Right Column: Requests List -->
    <section class="profile-requests-column">
        <h2>Your Signage Requests</h2>
        <div id="requests-status" class="status-message">Loading requests…</div>
        <div id="requests-list" class="requests-grid" hidden></div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>