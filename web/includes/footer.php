<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        /*        
            ========================================
            Navbar Template from https://www.quackit.com/html/html_editors/scratchpad/?example=/html/templates/footers/centered_logo_links_footer
            ========================================
        */
        :root {
            --footer-bg: #ffffff;
            --footer-text-color: #555555;
            --footer-link-color: #111111;
            --footer-link-hover-color: #00ff4ca3;
            --footer-border-color: #e7e7e7;
        }

        /* Basic body styles for demonstration */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            display: grid;
            grid-template-rows: 1fr auto;
            background-color: #f4f4f4;
        }

        .main-content {
            padding: 2rem;
            text-align: center;
        }

        /* FOOTER STYLES START HERE */

        .footer-centered {
            background-color: var(--footer-bg);
            color: var(--footer-text-color);
            padding: 2.5rem 1.5rem;
            border-top: 1px solid var(--footer-border-color);
            text-align: center;
        }

        .footer-centered__container {
            max-width: 800px;
            margin: 0 auto;
            
            /* Core layout styles */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .footer-centered__logo {
            display: block;
            width: 120px;
            height: auto;
        }
        
        .footer-centered__logo img {
            display: block;
            width: 100%;
            height: auto;
        }

        .footer-centered__nav ul,
        .footer-centered__social ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-wrap: wrap; /* Allow links to wrap on very narrow screens */
            justify-content: center;
            gap: 1.5rem;
        }

        .footer-centered__nav a,
        .footer-centered__social a {
            color: var(--footer-link-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease-in-out;
        }
        
        .footer-centered__nav a:hover,
        .footer-centered__social a:hover {
            color: var(--footer-link-hover-color);
        }
        
        .footer-centered__social svg {
            width: 22px;
            height: 22px;
            fill: var(--footer-text-color);
            transition: fill 0.2s ease-in-out;
        }

        .footer-centered__social a:hover svg {
            fill: var(--footer-link-hover-color);
        }

        .footer-centered__copyright {
            font-size: 0.875rem;
            margin: 0;
        }

    </style>
</head>
<body>
    <!-- 
      ==================================================================
      CENTERED LOGO & LINKS FOOTER
      ==================================================================
    -->
    <footer class="footer-centered" role="contentinfo">
        <div class="footer-centered__container">
            
            <!-- Logo -->
            <a href="#" class="footer-centered__logo" aria-label="Homepage">
                <img src="images/BSN_Logo.png" alt="ECLightboard Logo">
            </a>

            <!-- Navigation -->
            <nav class="footer-centered__nav" aria-label="Main Footer Navigation">
                <ul>
                    <li><a href="">Creators:</a></li>
                    <li><a href="creatorPages/scottD.php">Scott D.</a></li>
                    <li><a href="creatorPages/samV.php">Sam V.</a></li>
                    <li><a href="creatorPages/isaacW.php">Isaac W.</a></li>
                </ul>
            </nav>

            <!-- Copyright -->
            <p class="footer-centered__copyright">
                &copy; 2026 ECLightboard. All Rights Reserved.
            </p>

        </div>
    </footer>

</body>
</html>