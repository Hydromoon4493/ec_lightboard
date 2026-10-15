<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECLightboard</title>
    <link rel="icon" type="image/png" href="includes/BSN_Logo.png">
    <style>
        /*
            ========================================
            Navbar Template from https://www.quackit.com/html/html_editors/scratchpad/?example=/html/templates/navbars/playful_and_animated_navbar
            ========================================
        */
        :root {
            --color-background: #fff;
            --color-text: #2c3e50;
            --color-primary: #027725a3;
            --color-primary-darker: #009d2f92;; /* For reduced-motion hover */
            --color-secondary: #418b58a3;
            --color-highlight-bg: #f2f5f7;
            --font-family-sans-serif: "Nunito", system-ui, -apple-system, sans-serif;
            --font-weight-bold: 700;
            --navbar-height: 80px;
            --navbar-padding: 1.5rem;
            --container-width: 1140px;
            --transition-speed: 0.3s;
            --transition-bounce: cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@500;700&display=swap');
        
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0; font-family: var(--font-family-sans-serif);
            font-size: 1rem; font-weight: 500; line-height: 1.6;
            color: var(--color-text); background-color: var(--color-background);
        }
        ul { list-style: none; margin: 0; padding: 0; }
        a { text-decoration: none; color: inherit; }

        /*
        ========================================
        Navbar Base & Mobile Styles
        ========================================
        */
        .navbar {
            height: var(--navbar-height); display: flex; align-items: center;
            background-color: var(--color-background);
            border-bottom: 2px solid var(--color-highlight-bg);
            position: sticky; top: 0; z-index: 1000;
        }
        .navbar__container {
            display: flex; justify-content: space-between; align-items: center;
            width: 100%; max-width: var(--container-width);
            margin: 0 auto; padding: 0 var(--navbar-padding);
        }
        .navbar__brand { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--color-primary); }
        .navbar__toggle { display: block; border: none; padding: 0.5rem; background: transparent; cursor: pointer; z-index: 1001; }
        .navbar__toggle .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--color-text); border-radius: 3px; transition: all var(--transition-speed) var(--transition-bounce); }
        /* These transforms are for the mobile menu 'hamburger' to 'X' animation */
        .navbar__toggle.is-active .bar:nth-child(1) { transform: translateY(8px) rotate(45deg); }
        .navbar__toggle.is-active .bar:nth-child(2) { opacity: 0; }
        .navbar__toggle.is-active .bar:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
        
        .navbar__menu { display: none; flex-direction: column; width: 100%; position: absolute; top: var(--navbar-height); left: 0; background-color: var(--color-background); border-bottom: 2px solid var(--color-highlight-bg); box-shadow: 0 10px 20px rgba(0,0,0,0.04); z-index: 999; }
        .navbar__menu.is-active { display: flex; }
        .navbar__item { text-align: center; }
        .navbar__link { display: block; padding: 1.5rem; font-weight: var(--font-weight-bold); transition: background-color var(--transition-speed) ease; }
        .navbar__link:hover, .navbar__link:focus { background-color: var(--color-highlight-bg); }
        :is(.navbar__toggle, .navbar__link):focus-visible { outline: 3px solid var(--color-secondary); outline-offset: 2px; }

        /*
        ========================================
        Desktop Navbar Styles & Animations
        ========================================
        */
        @media (min-width: 768px) {
            .navbar__toggle { display: none; }
            .navbar__menu { display: flex; flex-direction: row; align-items: center; position: static; width: auto; background: none; border: none; box-shadow: none; }
            .navbar__list { display: flex; align-items: center; gap: 0.5rem; }
            .navbar__link { padding: 0.5rem 1rem; position: relative; }
            
            /* General hover rule that applies to non-CTA links. */
            .navbar__link:not(.navbar__link--cta):hover, 
            .navbar__link:not(.navbar__link--cta):focus { background-color: transparent; }
            
            /* Underline animation */
            .navbar__link:not(.navbar__link--cta)::after {
                content: ''; position: absolute; bottom: 0; left: 50%; width: 0;
                height: 3px; background-color: var(--color-secondary);
                transition: all var(--transition-speed) ease; transform: translateX(-50%);
            }
            .navbar__link:not(.navbar__link--cta):hover::after, .navbar__link:not(.navbar__link--cta):focus::after { width: 100%; }

            /* CTA Button */
            .navbar__item--cta { margin-left: 1rem; }
            .navbar__link--cta {
                background-color: var(--color-primary); color: #fff;
                padding: 0.75rem 1.5rem; border-radius: 50px;
                transition: transform var(--transition-speed) var(--transition-bounce), 
                            background-color var(--transition-speed) ease;
            }
            
            .navbar__link--cta:hover, .navbar__link--cta:focus {
                background-color: var(--color-primary);
                transform: scale(1.1);
            }
        }
        
        /* Reduced Motion Accessibility */
        @media (prefers-reduced-motion: reduce) {
            /* Disable animations */
            .navbar__toggle .bar, .navbar__link:not(.navbar__link--cta)::after, .navbar__link--cta { transition: none; }
            /* Instantly show underline for non-CTA links on hover */
            .navbar__link:not(.navbar__link--cta):hover::after, .navbar__link:not(.navbar__link--cta):focus::after { width: 100%; }
            /* Disable scaling on CTA and just change color for feedback */
            .navbar__link--cta:hover, .navbar__link--cta:focus {
                transform: none;
                background-color: var(--color-primary-darker);
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar__container">
            <a href="../" class="navbar__brand">ECLightboard</a>
            <button class="navbar__toggle" id="navbarToggle" aria-label="Toggle navigation" aria-controls="navbarMenu" aria-expanded="false">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </button>
            <nav id="navbarMenu" class="navbar__menu" role="navigation" aria-labelledby="navbarToggle">
                <ul class="navbar__list">
                    <li class="navbar__item"><a href="#" class="navbar__link">Upload</a></li>
                    <li class="navbar__item"><a href="#" class="navbar__link">Schedule</a></li>
                    <li class="navbar__item"><a href="#" class="navbar__link">Create</a></li>
                    <li class="navbar__item navbar__item--cta"><a href="#" class="navbar__link navbar__link--cta">Profile</a></li>
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
</body>
</html>