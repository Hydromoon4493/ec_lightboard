<?php
    require __DIR__ . '/../includes/require_login.php';
    include '../includes/navbar.php';
?>
<style>
    .creator-links {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .creator-links a {
        display: inline-block;
        padding: 8px 16px;
        border: 1px solid currentColor;
        border-radius: 6px;
        text-decoration: none;
        transition: background-color 0.2s, color 0.2s;
    }

    .creator-links a:hover {
        background-color: black;
        color: white;
    }
</style>
<link rel="stylesheet" href="../includes/style.css?v=2">

<main class="bodyDefult">
    <img src="../includes/images/Vossen_Sam.JPG" alt="Sam V." class="round-image">
    <h1>Sam Vossen</h1>
        <p>Sam is a junior Information Systems major with a concentration in Cybersecurity, a minor in Criminal Justice, and a minor in Forensic Studies. </p>
        <hr>
        <div class="creator-links">
            <a class="footer-link" href="https://www.linkedin.com/in/samvossen42/">LinkedIn</a>
            <a class="footer-link" href="https://github.com/Hydromoon4493">GitHub</a>
            <a class="footer-link" href="https://www.jpsdomain.org/sam/index.html">Portfolio Website</a>
        </div>
</main>

<?php
    include '../includes/footer.php';
?>