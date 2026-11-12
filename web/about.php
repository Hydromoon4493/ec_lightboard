
<?php
/*
 * Author: ECLightboard Team 2026
 * Filename: about.php
 *
 * Introduces the ECLightboard project and its development team.
 */

include 'includes/layout/navbar.php';
?>

<link rel="stylesheet" href="includes/css/style.css?v=2">
<link rel="stylesheet" href="includes/css/about.css?v=1">

<main class="about-page">

    <section class="about-hero">
        <p class="about-eyebrow">ABOUT THE PROJECT</p>

        <h1>Meet the ECLightboard Team</h1>

        <p>
            ECLightboard is a collaborative project developed by a
            three-person team. Our goal is to create an interactive
            LED display system that makes designing, uploading, and
            displaying custom images simple.
        </p>
    </section>


    <section class="about-project">
        <h2>What is ECLightboard?</h2>

        <p>
            ECLightboard combines a web-based interface with a physical
            LED display. Users can browse preset designs, upload their
            own images, create custom artwork, and send designs to the
            Lightboard.
        </p>
    </section>


    <section class="about-team">
        <h2>Our Team</h2>

        <p class="about-team-intro">
            Three people working together to bring the project to life.
        </p>

        <div class="team-grid">

            <article class="team-card">
                <div class="team-avatar">1</div>
                <h3>Scott Domboski</h3>
                <p>Backend + users login + scheduling</p>
            </article>

            <article class="team-card">
                <div class="team-avatar">2</div>
                <h3>Isaac Widders</h3>
                <p>Front-end + hardware + create tab</p>
            </article>

            <article class="team-card">
                <div class="team-avatar">3</div>
                <h3>Sam Vossen</h3>
                <p>Pi Communication + display</p>
            </article>

        </div>
    </section>


    <section class="about-footer">
        <h2>Built Together.</h2>

        <p>
            ECLightboard is the result of teamwork, experimentation,
            and a shared interest in technology.
        </p>

        <a href="/ec_lightboard/web/index.php" class="about-button">
            Back to Home
        </a>
    </section>

</main>

<?php
include 'includes/layout/footer.php';
?>
