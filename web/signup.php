<?php
$pageTitle = 'Sign up';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>EC Lightboard</title>
    <link rel="icon" type="image/png" href="/ec_lightboard/web/includes/images/BSN_Logo.png">
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/auth.css">
    <!-- defer = wait until the form exists before running -->
    <script src="includes/auth.js" defer></script>
</head>
<body>
<main class="auth-page">
    <section class="auth-card" aria-labelledby="auth-title">
        <h1 id="auth-title" class="auth-card__title">Create your account</h1>
        <p class="auth-card__subtitle">It only takes a minute.</p>
        <p class="auth-error" id="form-error" role="alert" hidden></p>
 
        <form class="auth-form" id="signup-form" method="post">
            <div class="auth-field">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" autocomplete="given-name" maxlength="45" required>
            </div>
 
            <div class="auth-field">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" autocomplete="family-name" maxlength="45" required>
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
</body>
</html>