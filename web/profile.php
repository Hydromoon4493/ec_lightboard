<?php
    include 'includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">

<main class="bodyDefult">
    <h1>Profile Page</h1>
    <p>Welcome to your profile page!</p>
    <toggle-button id="profileToggle" aria-label="Toggle profile details" aria-controls="profileDetails" aria-expanded="false">
        <span class="bar"></span><span class="bar"></span><span class="bar"></span>
    </toggle-button>
    <div id="profileDetails" class="profile-details" hidden></div>
        <p>Here are your profile details. You can edit your information and manage your account settings.</p>
</main>

<?php
    include 'includes/footer.php';
?>