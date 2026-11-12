
<?php
/*
 * Filename: adminDashboard.php
 *
 * Administrator dashboard: user roles and lightboard request review.
 * Data is loaded by includes/admin/*.js from the REST APIs; every API
 * re-checks the admin role server-side.
 */

require_once 'includes/auth/require_login.php';
require_once 'includes/lightboard/core/database.php';

$pageTitle = 'Admin Dashboard';
include 'includes/navbar.php';
?>

<link rel="stylesheet" href="/ec_lightboard/web/includes/style.css?v=2">
<link rel="stylesheet" href="/ec_lightboard/web/includes/adminDashboard.css?v=1">
<script src="/ec_lightboard/web/includes/admin/admin.js?v=1" defer></script>
<script src="/ec_lightboard/web/includes/admin/users.js?v=1" defer></script>
<script src="/ec_lightboard/web/includes/admin/requests.js?v=1" defer></script>

<main class="admin-page">

    <header class="admin-top">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Manage user roles and review display requests.</p>
        </div>
    </header>

    <div id="admin-flash" class="admin-flash" role="status" hidden></div>

    <nav class="admin-tabs" role="tablist">
        <button type="button" class="admin-tab is-active" data-tab="users" role="tab" aria-selected="true">Users</button>
        <button type="button" class="admin-tab" data-tab="pending" role="tab" aria-selected="false">
            Pending Requests <span class="tab-badge" id="pending-badge" hidden>0</span>
        </button>
        <button type="button" class="admin-tab" data-tab="upcoming" role="tab" aria-selected="false">Upcoming</button>
        <div class="admin-quick">
            <a class="btn btn-small btn-dark" href="display.php">View Displays</a>
            <a class="btn btn-small btn-dark" href="upload.php">Upload Presets</a>
        </div>
    </nav>

    <!-- USERS -->
    <section class="admin-panel" id="tab-users">
        <div class="admin-toolbar">
            <input type="search" id="user-search" placeholder="Search name or email" maxlength="100">
            <select id="user-role-filter" aria-label="Filter by role">
                <option value="">All roles</option>
                <option value="admin">Admins</option>
                <option value="user">Users</option>
            </select>
            <label class="page-size">
                Show
                <select id="user-limit">
                    <option>10</option><option selected>20</option>
                    <option>50</option><option>100</option>
                </select>
            </label>
        </div>

        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
                </thead>
                <tbody id="users-body">
                    <tr><td colspan="4" class="table-empty">Loading users…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pager">
            <span id="users-summary"></span>
            <div>
                <button type="button" class="btn btn-small btn-light" id="users-prev">Previous</button>
                <span id="users-page"></span>
                <button type="button" class="btn btn-small btn-light" id="users-next">Next</button>
            </div>
        </div>
    </section>

    <!-- PENDING -->
    <section class="admin-panel" id="tab-pending" hidden>
        <p class="status-message" id="pending-status">Loading requests…</p>
        <div class="req-list" id="pending-list"></div>
    </section>

    <!-- UPCOMING -->
    <section class="admin-panel" id="tab-upcoming" hidden>
        <p class="status-message" id="upcoming-status">Loading requests…</p>
        <div class="req-list" id="upcoming-list"></div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>