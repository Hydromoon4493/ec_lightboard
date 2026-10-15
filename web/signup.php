<?php
// web/signup.php  (Stage 1: visual only, form does not process anything yet)
?>
<link rel="stylesheet" href="includes/style.css">

<main class="auth-page">
    <section class="auth-card" aria-labelledby="auth-title">
        <h1 id="auth-title" class="auth-card__title">Create your account</h1>
        <p class="auth-card__subtitle">It only takes a minute.</p>
 
        <form class="auth-form" method="post" action="signup.php">
            <div class="auth-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
            </div>
 
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="email" required>
            </div>
 
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
            </div>
 
            <div class="auth-field">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            </div>
 
            <button type="submit" class="auth-button">Create account</button>
        </form>
 
        <p class="auth-card__switch">
            Already have an account? <a href="login.php">Back to log in</a>
        </p>
    </section>
</main>