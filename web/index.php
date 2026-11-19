
<?php
/*
 * Author: Sam Vossen 2026.11.10
 * Filename: index.php
 *
 * ECLightboard public homepage.
 *
 * Responsibilities:
 * - Introduce the ECLightboard project.
 * - Provide navigation to the main Lightboard pages.
 * - Display login or logout depending on session status.
 */

// Start the session so we can check the user's login status.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Home';
$isLoggedIn = !empty($_SESSION['userID']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | EC Lightboard</title>

    <link rel="stylesheet" href="includes/css/style.css?v=2">
    <link rel="stylesheet" href="includes/css/home.css?v=3">

    <link rel="icon" type="image/png"
          href="/ec_lightboard/web/includes/images/BSN_Logo.png">
</head>

<body>

<main class="home-page">

    <!-- Header -->
    <header class="home-header">

        <a href="index.php" class="home-brand">
            <img
                src="/ec_lightboard/web/includes/images/BSN_Logo.png"
                alt="ECLightboard logo"
            >

            <span>EC Lightboard</span>
        </a>

        <nav class="home-account">

            <?php if ($isLoggedIn): ?>

                <a href="profile.php" class="home-profile-link">
                    My Profile
                </a>

                <button
                    type="button"
                    id="home-logout-button"
                    class="home-account-button"
                >
                    Log Out
                </button>

            <?php else: ?>

                <a href="login.php" class="home-account-button">
                    Log In
                </a>

            <?php endif; ?>

        </nav>

    </header>


    <!-- Hero -->
    <section class="home-hero">

        <p class="home-eyebrow">
            THE ECLIGHTBOARD PROJECT
        </p>

        <h1>
            Bring your ideas <span>to light.</span>
        </h1>

        <p class="home-description">
            Explore creative displays, upload your own images, or design
            something completely original. Turn your ideas into
            something you can see on the Lightboard.
        </p>

        <div class="home-hero-decoration">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

    </section>


    <!-- Main Features -->
    <section class="home-features">

        <div class="home-section-heading">
            <p class="home-eyebrow">GET STARTED</p>
            <h2>What would you like to do?</h2>
            <p>Choose a starting point and bring something to life.</p>
        </div>


        <div class="home-feature-grid">

            <!-- Gallery -->
            <a href="gallery.php" class="home-feature-card">

                <div class="home-feature-icon">▦</div>

                <div class="home-feature-content">
                    <h3>Display Gallery</h3>

                    <p>
                        Browse the available preset displays and explore
                        custom designs uploaded by you.
                    </p>
                </div>

                <span class="home-feature-link">
                    Explore Gallery <span>→</span>
                </span>

            </a>


            <!-- Upload -->
            <a href="upload.php" class="home-feature-card">

                <div class="home-feature-icon">↑</div>

                <div class="home-feature-content">
                    <h3>Custom Uploads</h3>

                    <p>
                        Upload your own images and turn them into
                        something made for the Lightboard.
                    </p>
                </div>

                <span class="home-feature-link">
                    Upload an Image <span>→</span>
                </span>

            </a>


            <!-- Create -->
            <a href="create.php" class="home-feature-card">

                <div class="home-feature-icon">✎</div>

                <div class="home-feature-content">
                    <h3>Create a Display</h3>

                    <p>
                        Build a custom design from scratch using
                        the Lightboard creation tools.
                    </p>
                </div>

                <span class="home-feature-link">
                    Start Creating <span>→</span>
                </span>

            </a>


            <!-- About -->
            <a href="about.php" class="home-feature-card">

                <div class="home-feature-icon">👥</div>

                <div class="home-feature-content">
                    <h3>About Us</h3>

                    <p>
                        Learn about the ECLightboard project,
                        how it works, and the people behind it.
                    </p>
                </div>

                <span class="home-feature-link">
                    Meet the Team <span>→</span>
                </span>

            </a>

        </div>

    </section>


    <!-- Footer -->
    <footer class="home-footer">
        <p>EC Lightboard <span>·</span> Custom displays, brought to life.</p>
    </footer>

</main>


<?php if ($isLoggedIn): ?>

<script>
document.getElementById('home-logout-button').addEventListener('click', async function () {

    const button = this;

    button.disabled = true;
    button.textContent = 'Logging Out…';

    try {

        const response = await fetch('api/authentication/logout.php', {
            method: 'POST',
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Logout failed.');
        }

        window.location.href = 'index.php';

    } catch (error) {

        console.error('Logout error:', error);

        button.disabled = false;
        button.textContent = 'Log Out';

        alert('Could not log out. Please try again.');

    }

});
</script>

<?php endif; ?>

</body>
</html>
