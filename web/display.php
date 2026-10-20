<?php
/*
 * Author: Sam Vossen 2026.10.15
 * Filename: display.php
 *
 * This page is the user-facing Lightboard display gallery.
 *
 * Responsibilities:
 * - Load the shared site navigation and styling.
 * - Load available preset Lightboard images.
 * - Load available custom Lightboard images.
 * - Display the images in separate gallery sections.
 * - Provide buttons for selecting an image to display.
 * - Load the Lightboard JavaScript interface.
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

declare(strict_types=1);

require_once __DIR__ . '/includes/lightboard/images.php';

$presetImages = listImages('p');
$customImages = listImages('c');
?>

<?php
include __DIR__ . '/includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">

<main class="bodyDefult">

    <h1>EC Lightboard</h1>

    <p>
        Select an image below to display it on the Lightboard.
    </p>

    <!-- Preset Images -->
    <section class="lightboard-gallery">

        <h2>Preset Displays</h2>

        <?php if (empty($presetImages)): ?>

            <p>No preset displays are currently available.</p>

        <?php else: ?>

            <div class="gallery-grid">

                <?php foreach ($presetImages as $image): ?>

                    <div class="gallery-item">

                        <img
                            class="gallery-thumb"
                            src="api/thumbnail.php?src=p&id=<?= (int) $image['id'] ?>"
                            alt="<?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                        >

                        <h3>
                            <?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <button
                            type="button"
                            onclick="displayImage(
                                'p',
                                <?= (int) $image['id'] ?>,
                                this
                            )"
                        >
                            Display
                        </button>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- Custom Images -->
    <section class="lightboard-gallery">

        <h2>Custom Displays</h2>

        <?php if (empty($customImages)): ?>

            <p>No custom displays are currently available.</p>

        <?php else: ?>

            <div class="gallery-grid">

                <?php foreach ($customImages as $image): ?>

                    <div class="gallery-item">

                        <h3>
                            <?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <button
                            type="button"
                            onclick="displayImage(
                                'c',
                                <?= (int) $image['id'] ?>,
                                this
                            )"
                        >
                            Display
                        </button>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- Lightboard Controls -->
    <section class="lightboard-controls">

        <h2>Display Settings</h2>

        <label for="b">
            Brightness:
            <span id="brightnessValue">35</span>
        </label>

        <input
            type="range"
            id="b"
            name="brightness"
            min="1"
            max="100"
            value="35"
            oninput="document.getElementById('brightnessValue').textContent = this.value"
        >

        <script>
            document.getElementById('b').value = 35;
        </script>

    </section>


    <!-- Display Status -->
    <section
        id="statusCard"
        class="status-card"
    >

        <h2>Display Status</h2>

        <div id="statusList"></div>

    </section>


    <!-- Preview -->
    <section
        id="previewCard"
        class="preview-card"
        style="display: none;"
    >

        <h2>Preview</h2>

        <img
            id="previewImage"
            src=""
            alt="Lightboard preview"
        >

    </section>


    <!-- Result -->
    <section
        id="resultBox"
        class="result-box"
    ></section>

</main>


<script src="includes/lightboard/lightboard.js"></script>

<?php
include __DIR__ . '/includes/footer.php';
?>
