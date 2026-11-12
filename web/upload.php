<?php

/*
 * Author: Sam Vossen 2026.11.10
 * Filename: upload.php
 *
 * This page provides two Lightboard upload options:
 * - Admin-only preset display uploads.
 * - User custom display uploads.
 *
 * Responsibilities:
 * - Verify the user is logged in.
 * - Determine the user's database role.
 * - Display the appropriate upload forms.
 * - Send uploads to their respective handlers.
 *
 * This file does NOT:
 * - Process image files.
 * - Generate pixel maps.
 * - Communicate directly with the ESP32.
 */

require __DIR__ . '/includes/require_login.php';
require_once __DIR__ . '/includes/lightboard/database.php';

include 'includes/navbar.php';

/*
 * Retrieve the user's role directly from the database.
 * roleID 1 = admin
 * roleID 2 = user
 */

$isAdmin = false;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT roleID FROM users WHERE userID = ? LIMIT 1'
    );

    $stmt->execute([(int) $_SESSION['userID']]);

    $roleID = $stmt->fetchColumn();

    $isAdmin = ((int) $roleID === 1);

} catch (Throwable $e) {
    error_log('Lightboard role lookup error: ' . $e->getMessage());
}

?>
<link rel="stylesheet" href="includes/css/style.css">
<link rel="stylesheet" href="includes/css/upload.css?v=5">

<main class="bodyDefult upload-page">

    <div class="upload-container">

        <div class="upload-heading">
            <h1>Upload Displays</h1>
            <p>
                Upload preset displays or create your own custom display.
            </p>
        </div>

        <div class="upload-columns <?= $isAdmin ? 'admin-layout' : 'user-layout' ?>">

            <?php if ($isAdmin): ?>

                <!-- ADMIN PRESET UPLOAD -->

                <section class="upload-card">

                    <div class="upload-card-heading">
                        <h2>Admin Preset Upload</h2>

                        <span class="upload-tag admin-tag">
                            Administrator
                        </span>
                    </div>

                    <p class="upload-description">
                        Upload preset displays that will be available
                        to all users.
                    </p>

                    <?php if (
                        isset($_GET['preset_upload']) &&
                        $_GET['preset_upload'] === 'success'
                    ): ?>

                        <div class="upload-message success">
                            Preset display uploaded successfully.
                        </div>

                    <?php elseif (
                        isset($_GET['preset_upload']) &&
                        $_GET['preset_upload'] === 'error'
                    ): ?>

                        <div class="upload-message error">
                            There was a problem uploading the preset.
                        </div>

                    <?php endif; ?>

                    <form
                        class="upload-form"
                        action="includes/lightboard/preset_custom_upload_db.php"
                        method="post"
                        enctype="multipart/form-data"
                    >

                        <div class="upload-field">

                            <label for="preset-name">
                                Display Name
                            </label>

                            <input
                                type="text"
                                id="preset-name"
                                name="name"
                                maxlength="45"
                                placeholder="Enter a preset name"
                                required
                            >

                        </div>

                        <div class="upload-field">

                            <label for="preset-image">
                                Select an image
                            </label>

                            <input
                                type="file"
                                id="preset-image"
                                name="image"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                required
                            >

                            <small>
                                Supports PNG, JPG, JPEG, and GIF
                            </small>

                        </div>

                        <button
                            type="submit"
                            class="upload-button"
                        >
                            Upload Preset Display
                        </button>

                    </form>

                </section>

            <?php endif; ?>


            <!-- USER CUSTOM DISPLAY UPLOAD -->

            <section class="upload-card">

                <div class="upload-card-heading">
                    <h2>Custom Display Upload</h2>

                    <span class="upload-tag user-tag">
                        All Users
                    </span>
                </div>

                <p class="upload-description">
                    Upload your own image to create a custom Lightboard
                    display.
                </p>

                <?php if (isset($_GET['upload'])): ?>

                    <?php if ($_GET['upload'] === 'success'): ?>

                        <div class="upload-message success">

                            <strong>Image uploaded successfully.</strong>

                            <?php if (isset($_GET['name'])): ?>

                                <span>
                                    Display Name:
                                    <?= htmlspecialchars(
                                        $_GET['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                    <?php elseif (
                        in_array(
                            $_GET['upload'],
                            ['error', 'too_large', 'invalid'],
                            true
                        )
                    ): ?>

                        <div class="upload-message error">

                            <?php
                            $messages = [
                                'error' => 'There was a problem uploading the image.',
                                'too_large' => 'The image is too large.',
                                'invalid' => 'The uploaded file is not a valid image.'
                            ];

                            echo htmlspecialchars(
                                $messages[$_GET['upload']],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>


                <form
                    class="upload-form"
                    action="includes/lightboard/custom_upload_db.php"
                    method="post"
                    enctype="multipart/form-data"
                >

                    <div class="upload-field">

                        <label for="name">
                            Display Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="45"
                            placeholder="Enter a display name"
                            required
                        >

                    </div>

                    <div class="upload-field">

                        <label for="image">
                            Select an image
                        </label>

                        <input
                            type="file"
                            id="image"
                            name="image"
                            accept="image/png,image/jpeg,image/gif,image/webp"
                            required
                        >

                        <small>
                            Supports PNG, JPG, JPEG, and GIF
                        </small>

                    </div>

                    <button
                        type="submit"
                        class="upload-button"
                    >
                        Upload Custom Display
                    </button>

                </form>

            </section>

        </div>

        <a href="display.php" class="back-button">
            Back to Display
        </a>

    </div>

</main>

<?php include 'includes/footer.php'; ?>
