
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['userID']);
$isAdmin = false;

if ($isLoggedIn) {
    require_once __DIR__ . '/lightboard/database.php';

    $stmt = db()->prepare(
        "SELECT roleID FROM users WHERE userID = ? LIMIT 1"
    );
    $stmt->execute([(int) $_SESSION['userID']]);

    $roleID = $stmt->fetchColumn();
    $isAdmin = ((int) $roleID === 1);
}
?>

<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>EC Lightboard</title>
<link rel="icon" type="image/png" href="/ec_lightboard/web/includes/images/BSN_Logo.png">
<link rel="stylesheet" href="/ec_lightboard/web/includes/css/navbar.css?v=2">
<link rel="stylesheet" href="/ec_lightboard/web/includes/css/style.css?v=2">

<header class="navbar">
    <div class="navbar__container">

        <a href="/ec_lightboard/web/" class="navbar__brand">
            ECLightboard
        </a>

        <button
            class="navbar__toggle"
            id="navbarToggle"
            aria-label="Toggle navigation"
            aria-controls="navbarMenu"
            aria-expanded="false"
        >
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <nav
            id="navbarMenu"
            class="navbar__menu"
            role="navigation"
            aria-labelledby="navbarToggle"
        >

            <!-- Main navigation -->
            <ul class="navbar__list">

                <?php if ($isLoggedIn): ?>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/upload.php" class="navbar__link">
                            Upload
                        </a>
                    </li>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/schedule.php" class="navbar__link">
                            Schedule
                        </a>
                    </li>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/create.php" class="navbar__link">
                            Create
                        </a>
                    </li>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/gallery.php" class="navbar__link">
                            Gallery
                        </a>
                    </li>

                    <li class="navbar__item navbar__item--cta">
                        <a
                            href="/ec_lightboard/web/display.php"
                            class="navbar__link navbar__link--cta"
                        >
                            Display
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

            <!-- Hamburger dropdown -->
            <ul class="navbar__more">

                <?php if ($isLoggedIn): ?>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/profile.php" class="navbar__link">
                            Profile
                        </a>
                    </li>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/about.php" class="navbar__link">
                            About
                        </a>
                    </li>

                    <?php if ($isAdmin): ?>
                        <li class="navbar__item">
                            <a href="/ec_lightboard/web/adminDashboard.php" class="navbar__link">
                                Admin Dashboard
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/logout.php" class="navbar__link navbar__link--login">
                            Log Out
                        </a>
                    </li>

                <?php else: ?>

                    <li class="navbar__item">
                        <a href="/ec_lightboard/web/login.php" class="navbar__link navbar__link--login">
                            Log In
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </nav>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const navbarToggle = document.getElementById('navbarToggle');
        const navbarMenu = document.getElementById('navbarMenu');

        if (navbarToggle && navbarMenu) {

            function setOpen(open) {
                navbarToggle.classList.toggle('is-active', open);
                navbarMenu.classList.toggle('is-active', open);
                navbarToggle.setAttribute('aria-expanded', open);
            }

            navbarToggle.addEventListener('click', function () {
                setOpen(!navbarMenu.classList.contains('is-active'));
            });

            // Close menu when clicking outside or pressing Escape.
            document.addEventListener('click', function (e) {
                if (
                    !navbarMenu.contains(e.target) &&
                    !navbarToggle.contains(e.target)
                ) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    setOpen(false);
                }
            });
        }
    });
</script>
