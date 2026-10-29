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
 * - Display the images in separate gallery columns.
 * - Provide buttons for selecting an image to display.
 * - Show brightness, status, preview and result in a sidebar.
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
require __DIR__ . '/includes/require_login.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/lightboard/images.php';

$presetImages = listImages('p');
$customImages = listImages('c');

/*
 * Gallery definitions. Each entry becomes one column.
 * The key is the image source ('p' = presets, 'c' = custom).
 */
$galleries = [
    'p' => [
        'title'  => 'Preset Displays',
        'images' => $presetImages,
        'empty'  => 'No preset displays are currently available.',
    ],
    'c' => [
        'title'  => 'Custom Displays',
        'images' => $customImages,
        'empty'  => 'No custom displays are currently available.',
    ],
];
?>

<?php
include __DIR__ . '/includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">
<link rel="stylesheet" href="includes/lightboard.css">

<main class="bodyDefult lightboard-page">

    <h1>EC Lightboard</h1>

    <p>
        Select an image below to display it on the Lightboard.
    </p>

    <div class="lightboard-layout">

        <!-- Columns 1 and 2: Preset and Custom galleries -->
        <?php foreach ($galleries as $key => $g): ?>

            <section class="lightboard-gallery">

                <h2><?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?></h2>

                <?php if (empty($g['images'])): ?>

                    <p><?= htmlspecialchars($g['empty'], ENT_QUOTES, 'UTF-8') ?></p>

                <?php else: ?>

                    <div class="gallery-grid">

                        <?php foreach ($g['images'] as $image): ?>

                            <div class="gallery-item">

                                <img
                                    class="gallery-thumb"
                                    src="api/thumbnail.php?src=<?= $key ?>&id=<?= (int) $image['id'] ?>"
                                    alt="<?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                >

                                <h3>
                                    <?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <button
                                    type="button"
                                    onclick="displayImage('<?= $key ?>', <?= (int) $image['id'] ?>, this)"
                                >
                                    Display
                                </button>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>


        <!-- Column 3: Controls, status, preview, result -->
        <aside class="lightboard-sidebar">

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

        </aside>

    </div>

</main>


<script src="includes/lightboard/lightboard.js"></script>

<?php
include __DIR__ . '/includes/footer.php';
?>
