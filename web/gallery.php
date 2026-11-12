
<?php
/*
 * Author: Sam Vossen 2026.11.12
 * Filename: gallery.php
 *
 * This page displays the available Lightboard preset and custom displays.
 *
 * Responsibilities:
 * - Load the shared site navigation and styling.
 * - Load available preset Lightboard images.
 * - Load available custom Lightboard images.
 * - Display images in separate gallery columns.
 *
 * This file does NOT:
 * - Display images on the Lightboard.
 * - Allow users to manage or delete displays.
 * - Process image files.
 * - Generate pixel maps.
 * - Communicate directly with the ESP32.
 * - Create or schedule Lightboard jobs.
 */

require __DIR__ . '/includes/require_login.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/lightboard/images.php';

/* ============================================================
 * LOAD GALLERY IMAGES
 * ============================================================
 */

$presetImages = listImages('p');
$customImages = listImages('c', (int) $_SESSION['userID']);

/*
 * Gallery definitions.
 * p = preset displays
 * c = custom displays
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
        'empty'  => 'You have not uploaded any custom displays yet.',
    ],
];

/* ============================================================
 * PAGE SETUP
 * ============================================================
 */

$pageTitle = 'Gallery';

include __DIR__ . '/includes/navbar.php';
?>

<link rel="stylesheet" href="includes/lightboard.css?v=7">
<link rel="stylesheet" href="includes/gallery.css?v=1">

<main class="bodyDefult lightboard-page">

    <h1>Lightboard Gallery</h1>

    <p>
        Browse the available preset and custom Lightboard displays.
    </p>

    <div class="lightboard-layout gallery-page-layout">

        <?php foreach ($galleries as $key => $g): ?>

            <section class="lightboard-gallery">

                <h2>
                    <?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <?php if (empty($g['images'])): ?>

                    <p>
                        <?= htmlspecialchars($g['empty'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

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

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    </div>

</main>

<?php
include __DIR__ . '/includes/footer.php';
?>
