
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>EC Lightboard</title>
    <link rel="icon" type="image/png" href="/ec_lightboard/web/includes/images/BSN_Logo.png">

    <header class="navbar">
        <div class="navbar__container">
            <a href="/ec_lightboard/web/" class="navbar__brand">ECLightboard</a>
            <button class="navbar__toggle" id="navbarToggle" aria-label="Toggle navigation" aria-controls="navbarMenu" aria-expanded="false">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </button>
            <nav id="navbarMenu" class="navbar__menu" role="navigation" aria-labelledby="navbarToggle">
                <ul class="navbar__list">
                    <li class="navbar__item"><a href="/ec_lightboard/web/display.php" target="_blank" class="navbar__link">Display Photo</a></li>
                    <li class="navbar__item"><a href="#" class="navbar__link">Upload</a></li>
                    <li class="navbar__item"><a href="#" class="navbar__link">Schedule</a></li>
                    <li class="navbar__item"><a href="#" class="navbar__link">Create</a></li>
                    <li class="navbar__item navbar__item--cta"><a href="/ec_lightboard/web/profile.php" class="navbar__link navbar__link--cta">Profile</a></li>
                </ul>
            </nav>
        </div>
    </header>

    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const navbarToggle = document.getElementById('navbarToggle');
            const navbarMenu = document.getElementById('navbarMenu');
            if (navbarToggle && navbarMenu) {
                navbarToggle.addEventListener('click', function () {
                    navbarToggle.classList.toggle('is-active');
                    navbarMenu.classList.toggle('is-active');
                    const isExpanded = navbarToggle.getAttribute('aria-expanded') === 'true';
                    navbarToggle.setAttribute('aria-expanded', !isExpanded);
                });
            }
        });
    </script>