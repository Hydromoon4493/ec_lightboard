
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>EC Lightboard</title>
    <link rel="icon" type="image/png" href="/ec_lightboard/web/includes/images/BSN_Logo.png">
    <link rel="stylesheet" href="/ec_lightboard/web/includes/navbar.css?v=2">
    <link rel="stylesheet" href="/ec_lightboard/web/includes/style.css?v=2">

    <header class="navbar">
        <div class="navbar__container">
            <a href="/ec_lightboard/web/" class="navbar__brand">ECLightboard</a>
            <button class="navbar__toggle" id="navbarToggle" aria-label="Toggle navigation" aria-controls="navbarMenu" aria-expanded="false">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </button>
            <nav id="navbarMenu" class="navbar__menu" role="navigation" aria-labelledby="navbarToggle">
                <!-- always visible on desktop -->
                <ul class="navbar__list">
                    <li class="navbar__item"><a href="/ec_lightboard/web/upload.php" class="navbar__link">Upload</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/schedule.php" class="navbar__link">Schedule</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/create.php" class="navbar__link">Create</a></li>
                    <li class="navbar__item navbar__item--cta"><a href="/ec_lightboard/web/display.php" class="navbar__link navbar__link--cta">Display</a></li>
                </ul>
                <!-- inside the hamburger dropdown on desktop -->
                <ul class="navbar__more">
                    <li class="navbar__item"><a href="/ec_lightboard/web/index.php" class="navbar__link">Home</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/adminDashboard.php" class="navbar__link">Admin Dashboard</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/about.php" class="navbar__link">About</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/profile.php" class="navbar__link">Profile</a></li>
                    <li class="navbar__item"><a href="/ec_lightboard/web/custom.php" class="navbar__link">Custom</a></li>
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

                // close the menu when clicking outside it, or pressing Escape
                document.addEventListener('click', function (e) {
                    if (!navbarMenu.contains(e.target) && !navbarToggle.contains(e.target)) setOpen(false);
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') setOpen(false);
                });
            }
        });
    </script>