<?php
/*
 * Filename: creatorPages/scottD.php
 *
 * Creator page for Scott Domboski.
 * Public on purpose (no require_login) so recruiters can open the link.
 * Static content only: this page itself makes no database calls.
 */

$pageTitle = 'Scott D.';
include '../includes/layout/navbar.php';
?>

<link rel="stylesheet" href="../includes/css/style.css?v=2">
<link rel="stylesheet" href="../includes/css/creator.css?v=1">

<main class="creator-page">

    <!-- Intro -->
    <section class="creator-intro">
        <img class="creator-photo"
             src="../includes/images/scott_face.webp"
             alt="Portrait of Scott Domboski">

        <div class="creator-intro__text">
            <h1>Scott Domboski</h1>
            <p class="creator-role">
                Backend developer on ECLightboard: accounts, sessions, and scheduling.
            </p>
            <p>
                I'm a junior Information Systems major with a concentration in
                Cybersecurity. On this project I worked on both front and backend implementation,
                focusing mainly on the backend. My main contributions were database design, login systems,
                scheduling logic, and admin dashboard.
            </p>

            <div class="creator-links">
                <a class="creator-btn creator-btn--primary"
                   href="https://www.linkedin.com/in/scott-domboski-58208227b"
                   target="_blank" rel="noopener noreferrer">LinkedIn</a>
                <a class="creator-btn"
                   href="https://github.com/YOUR-USERNAME"
                   target="_blank" rel="noopener noreferrer">GitHub</a>
                <!-- <a class="creator-btn" href="mailto:you@school.edu">Email</a> -->
            </div>
        </div>
    </section>

    <!-- What I built -->
    <section class="creator-section">
        <h2>What I built</h2>

        <div class="creator-grid">

            <article class="creator-card">
                <h3>Accounts and sessions</h3>
                <p>
                    Signup and login endpoints with server-side validation.
                    Passwords are hashed, login and "no such user"
                    return the same error so accounts can't be probed, and the
                    session ID is regenerated on login to prevent fixation.
                    Session cookies are HttpOnly, SameSite, and Secure over HTTPS.
                </p>
                <p class="creator-tags">PHP · PDO · Sessions · bcrypt</p>
            </article>

            <article class="creator-card">
                <h3>Scheduling REST API</h3>
                <p>
                    One resource, four methods: <code>GET</code> lists requests,
                    <code>POST</code> creates one, <code>PATCH</code> lets admins
                    approve or deny, and <code>DELETE</code> cancels. Roles are
                    checked on the server, and the status of a request is never
                    trusted from the client.
                </p>
                <p class="creator-tags">REST · JSON · Role-based access</p>
            </article>

            <article class="creator-card">
                <h3>Database Design</h3>
                <p>
                    Relational MySQL database with multiple tables to handle data for
                    scheduling requests, users, pictures, and roles. Communication takes 
                    place through parameterized queries, and REST API's. Website was 
                    optimized to stay within Database connection limits.
                </p>
                <p class="creator-tags">MySQL · Concurrency · Validation</p>
            </article>

        </div>
    </section>

    <!-- Security -->
    <section class="creator-section">
        <h2>Security choices</h2>
        <ul class="creator-list">
            <li>Every query uses prepared statements.</li>
            <li>Table and column names come from a fixed whitelist, never from user input.</li>
            <li>Passwords are length-limited to match bcrypt's 72-byte cap.</li>
            <li>Cancelling a request is a soft delete, so the history is kept.</li>
        </ul>
    </section>

    <!-- Skills -->
    <section class="creator-section">
        <h2>Skills used</h2>
        <ul class="creator-chips">
            <li>PHP</li>
            <li>MySQL</li>
            <li>REST API design</li>
            <li>JavaScript (fetch)</li>
            <li>Authentication</li>
            <li>Input validation</li>
            <li>Git</li>
        </ul>
    </section>

    <!-- Team -->
    <section class="creator-section creator-team">
        <p>
            Built with <a href="/ec_lightboard/web/creatorPages/samV.php">Sam V.</a>
            and <a href="/ec_lightboard/web/creatorPages/isaacW.php">Isaac W.</a>
            &nbsp;|&nbsp;
            <a href="/ec_lightboard/web/about.php">About the project</a>
        </p>
    </section>

</main>

<?php
include '../includes/layout/footer.php';
?>
