<?php
/*
 * ECLightboard User Profile
 *
 * Displays account information, account settings,
 * and the user's signage requests.
 */

require __DIR__ . '/includes/auth/require_login.php';
include 'includes/layout/navbar.php';
?>

<link rel="stylesheet" href="includes/css/style.css?v=1">
<link rel="stylesheet" href="includes/css/profile.css?v=1">
<script src="includes/js/profile.js?v=6" defer></script>

<main class="bodyDefault profile-page">

    <!-- Profile Overview -->
    <section class="profile-card profile-overview">

        <div class="section-heading">
            <div>
                <p class="section-eyebrow">ACCOUNT</p>
                <h1>Your Profile</h1>
                <p class="section-description">
                    View your account information and settings.
                </p>
            </div>
        </div>

        <p id="profile-status" class="status-message">
            Loading details…
        </p>

        <dl class="profile-list" id="profile-list" hidden>

            <div class="profile-info">
                <dt>First Name</dt>
                <dd id="p-first"></dd>
            </div>

            <div class="profile-info">
                <dt>Last Name</dt>
                <dd id="p-last"></dd>
            </div>

            <div class="profile-info">
                <dt>Email Address</dt>
                <dd id="p-email"></dd>
            </div>

            <div class="profile-info">
                <dt>Account Role</dt>
                <dd id="p-role"></dd>
            </div>

        </dl>

    </section>


    <!-- Account Settings -->
    <section class="account-settings-section">

        <div class="section-heading">
            <div>
                <p class="section-eyebrow">SECURITY & SETTINGS</p>
                <h2>Account Settings</h2>
                <p class="section-description">
                    Update the credentials you use to access ECLightboard.
                </p>
            </div>
        </div>

        <div class="settings-grid">

            <!-- Change Email -->
            <section class="profile-card settings-card">

                <div class="settings-card-heading">
                    <div class="settings-icon">@</div>
                    <div>
                        <h3>Change Email</h3>
                        <p>Update your login email address.</p>
                    </div>
                </div>

                <form id="change-email-form">

                    <div class="form-group">
                        <label for="email-current-password">
                            Current Password
                        </label>

                        <input
                            type="password"
                            id="email-current-password"
                            name="currentPassword"
                            autocomplete="current-password"
                            placeholder="Enter current password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email-new">
                            New Email Address
                        </label>

                        <input
                            type="email"
                            id="email-new"
                            name="newEmail"
                            maxlength="255"
                            autocomplete="email"
                            placeholder="Enter new email"
                            required
                        >
                    </div>

                    <p
                        id="email-change-status"
                        class="status-message form-status"
                        aria-live="polite"
                    ></p>

                    <button type="submit" class="settings-btn">
                        Update Email
                    </button>

                </form>

            </section>


            <!-- Change Password -->
            <section class="profile-card settings-card">

                <div class="settings-card-heading">
                    <div class="settings-icon">●</div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Choose a new password for your account.</p>
                    </div>
                </div>

                <form id="change-password-form">

                    <div class="form-group">
                        <label for="password-current">
                            Current Password
                        </label>

                        <input
                            type="password"
                            id="password-current"
                            name="currentPassword"
                            autocomplete="current-password"
                            placeholder="Enter current password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password-new">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="password-new"
                            name="newPassword"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="At least 8 characters"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password-confirm">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="password-confirm"
                            name="confirmPassword"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Re-enter new password"
                            required
                        >
                    </div>

                    <p
                        id="password-change-status"
                        class="status-message form-status"
                        aria-live="polite"
                    ></p>

                    <button type="submit" class="settings-btn">
                        Update Password
                    </button>

                </form>

            </section>

        </div>

    </section>


    <!-- Signage Requests -->
    <section class="requests-section">

        <div class="section-heading">
            <div>
                <p class="section-eyebrow">YOUR ACTIVITY</p>
                <h2>Your Signage Requests</h2>
                <p class="section-description">
                    View and manage your submitted display requests.
                </p>
            </div>
        </div>

        <div id="requests-status" class="status-message">
            Loading requests…
        </div>

        <div id="requests-list" class="requests-grid" hidden></div>

    </section>

</main>

<?php include 'includes/layout/footer.php'; ?>
