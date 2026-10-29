<?php
declare(strict_types=1);

/*
 * Author: Sam Vossen 2026.10.29
 * Filename: upload.php
 *
 * This page is the user-facing Lightboard custom-display upload page.
 *
 * Responsibilities:
 * - Load the shared site navigation and styling.
 * - Provide the custom-display upload form.
 * - Display upload success and error messages.
 * - Send uploaded images to the Lightboard upload database handler.
 *
 * This file does NOT:
 * - Process image files.
 * - Generate pixel maps.
 * - Communicate directly with MySQL.
 * - Communicate directly with the ESP32.
 * - Compile or upload Arduino sketches.
 *
 * Those responsibilities are handled by the Lightboard include
 * files and API layer.
 */

require __DIR__ . '/includes/require_login.php';
include 'includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">
<link rel="stylesheet" href="includes/upload.css">

<main class="bodyDefult upload-page">

    <div class="upload-card">

        <h1>Upload Custom Display</h1>


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


            <?php elseif ($_GET['upload'] === 'error'): ?>

                <div class="upload-message error">
                    There was a problem uploading the image.
                </div>


            <?php elseif ($_GET['upload'] === 'too_large'): ?>

                <div class="upload-message error">
                    The image is too large.
                </div>


            <?php elseif ($_GET['upload'] === 'invalid'): ?>

                <div class="upload-message error">
                    The uploaded file is not a valid image.
                </div>

            <?php endif; ?>

        <?php endif; ?>


        <?php if (
            !isset($_GET['upload']) ||
            $_GET['upload'] !== 'success'
        ): ?>

            <form
                class="upload-form"
                action="includes/lightboard/upload_db.php"
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
                        Supports 48 x 48 PNGs only (right now)
                    </small>

                </div>


                <button
                    type="submit"
                    class="upload-button"
                >
                    Upload Display
                </button>

            </form>

        <?php endif; ?>


        <a
            href="display.php"
            class="back-button"
        >
            Back to Display
        </a>

    </div>

</main>

<?php
include 'includes/footer.php';
?>
