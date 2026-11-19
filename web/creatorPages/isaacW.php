<?php
    require __DIR__ . '/../includes/auth/require_login.php';
    include '../includes/layout/navbar.php';
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
    <img src="../includes/images/Widders_Isaac.JPG" alt="Isaac W." class="round-image">
    <h1>Isaac Widders</h1>
        <p>Isaac is a sophomore and a Computer Science major with a concentration in Software Development, a minor in Business Administration. </p>
        <hr>
        <div class="creator-links">
            <a class="footer-link" href="https://www.linkedin.com/in/isaacwidders/">LinkedIn</a>
            <a class="footer-link" href="https://github.com/isaacwidders">GitHub</a>
        </div>
</main>

<?php
    include '../includes/layout/footer.php';
?>