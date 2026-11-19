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
 * - Provide an admin dashboard link.
 *
 * This page is public and does not require login.
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

    <title><?= htmlspecialchars($pageTitle) ?> | EC Lightboard</title>

    <link rel="stylesheet" href="includes/css/style.css?v=2">
    <link rel="stylesheet" href="includes/css/home.css?v=1">

    <link rel="icon" type="image/png"
          href="/ec_lightboard/web/includes/images/BSN_Logo.png">
</head>

<body>

<main class="home-page">

    <!-- Hero -->
    <section class="home-hero">

        <div class="home-logo">
            <img
                src="/ec_lightboard/web/includes/images/BSN_Logo.png"
                alt="ECLightboard logo"
            >
        </div>

        <p class="home-eyebrow">ECLIGHTBOARD PROJECT</p>

        <h1>Bring your ideas to light.</h1>

        <p class="home-description">
            ECLightboard is a customizable LED display system that lets
            you upload images, explore presets, and send designs directly
            to the Lightboard.
        </p>

        <div class="home-actions">

            <a href="gallery.php" class="home-button home-button-primary">
                Explore Displays
            </a>

            <?php if ($isLoggedIn): ?>

                <button
                    type="button"
                    id="home-logout-button"
                    class="home-button home-button-secondary"
                >
                    Log Out
                </button>

            <?php else: ?>

                <a href="login.php" class="home-button home-button-secondary">
                    Log In
                </a>

            <?php endif; ?>

        </div>

    </section>


    <!-- Features -->
    <section class="home-features">

        <div class="home-feature-card">
            <div class="home-feature-icon">▦</div>

            <h2>Display Gallery</h2>

            <p>
                Browse available preset designs and custom images
                created by users.
            </p>

            <a href="display.php">Browse displays →</a>
        </div>


        <div class="home-feature-card">
            <div class="home-feature-icon">＋</div>

            <h2>Custom Uploads</h2>

            <p>
                Upload your own images and turn them into designs
                for the Lightboard.
            </p>

            <a href="upload.php">Upload an image →</a>
        </div>


        <div class="home-feature-card">
            <div class="home-feature-icon">👥</div>

            <h2>About Us</h2>

            <p>
                Learn more about the ECLightboard project,
                how it works, and the three-person team behind it.
            </p>

            <a href="about.php">Meet the team →</a>
        </div>

    </section>


    <!-- Footer -->
    <footer class="home-footer">
        <p>EC Lightboard · Custom displays, brought to life.</p>
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
