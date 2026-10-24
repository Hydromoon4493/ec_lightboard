<?php
// web/login.php  (Stage 1: visual only, form does not process anything yet)
?>

<!-- Shared includes: match however your other pages (e.g. index.php) pull these in -->
<link rel="stylesheet" href="/ec_lightboard/web/includes/style.css">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>EC Lightboard</title>
<link rel="icon" type="image/png" href="/ec_lightboard/web/includes/images/BSN_Logo.png">
<script src="includes/auth.js" defer></script>

<main class="auth-page">
    <section class="auth-card" aria-labelledby="auth-title">
        <h1 id="auth-title" class="auth-card__title">Log in</h1>
        <p class="auth-card__subtitle">Welcome back to ECLightboard.</p>
        <p class="auth-error" id="form-error" role="alert" hidden></p>

        <form class="auth-form" id="login-form" method="post" >
            
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="email" required>
            </div>
 
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
 
            <button type="submit" class="auth-button">Log in</button>
        </form>
 
        <p class="auth-card__switch">
            New here? <a href="signup.php">Create an account</a>
        </p>
    </section>
</main>
