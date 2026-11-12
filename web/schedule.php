
<?php
/*
 * Filename: schedule.php
 *
 * This page lets users request a Lightboard job.
 *
 * Responsibilities:
 * - Load the shared site navigation and styling.
 * - Show preset and custom Lightboard images for reference (no display buttons).
 * - Provide a request form: display, start, end, notes.
 *
 * This file does NOT:
 * - Process or save the request (handled by api/schedule/schedule.php).
 * - Communicate directly with MySQL or the ESP32.
 */

require __DIR__ . '/includes/require_login.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/lightboard/images.php';

/*
 * Load available displays.
 *
 * Presets are available to everyone.
 * Custom displays are restricted to the logged-in user.
 */

$presetImages = listImages('p');
$customImages = listImages('c', (int)$_SESSION['userID']);

/*
 * One entry per image column.
 * Key = image source ('p' or 'c').
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

/*
 * Earliest selectable start: 24 hours from now,
 * Eastern time, formatted for datetime-local.
 */

$minDateTime = (new DateTimeImmutable(
    '+24 hours',
    new DateTimeZone('America/New_York')
))->format('Y-m-d\TH:i');

?>

<?php
/*
 * Shared navigation.
 * Include only once, after page initialization.
 */
include __DIR__ . '/includes/navbar.php';
?>

<link rel="stylesheet" href="includes/css/style.css?v=2">
<link rel="stylesheet" href="includes/css/lightboard.css">
<link rel="stylesheet" href="includes/css/schedule.css">

<script src="includes/js/schedule.js" defer></script>

<main class="bodyDefult lightboard-page">

    <h1>Schedule the Lightboard</h1>

    <p>
        Browse the available displays, then request a time slot using the form.
    </p>

    <div class="lightboard-layout schedule-layout">

        <!-- Columns 1 and 2: Preset and Custom galleries (reference only) -->

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

                    <div class="gallery-grid schedule-gallery-grid">

                        <?php foreach ($g['images'] as $image): ?>

                            <div class="gallery-item">

                                <img
                                    class="gallery-thumb"
                                    src="api/display/thumbnail.php?src=<?= $key ?>&id=<?= (int)$image['id'] ?>"
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


        <!-- Column 3: Job request form -->

        <aside class="lightboard-sidebar schedule-form-column">

            <section class="lightboard-controls schedule-form-card">

                <h2>Request a Job</h2>

                <form
                    id="scheduleForm"
                    method="post"
                    action="api/schedule/schedule.php"
                >

                    <!-- Display selection -->

                    <div class="form-row">

                        <label for="display">Display</label>

                        <select id="display" name="display" required>

                            <option value="" disabled selected>
                                Select a display…
                            </option>

                            <?php foreach ($galleries as $key => $g): ?>

                                <?php if (!empty($g['images'])): ?>

                                    <optgroup label="<?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>">

                                        <?php foreach ($g['images'] as $image): ?>

                                            <option value="<?= $key . ':' . (int)$image['id'] ?>">
                                                <?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </optgroup>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Start and end times -->

                    <div class="form-row form-row-pair">

                        <div>

                            <label for="start">Start date &amp; time</label>

                            <input
                                type="datetime-local"
                                id="start"
                                name="start"
                                min="<?= $minDateTime ?>"
                                required
                            >

                        </div>

                        <div>

                            <label for="end">End date &amp; time</label>

                            <input
                                type="datetime-local"
                                id="end"
                                name="end"
                                min="<?= $minDateTime ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- Optional notes -->

                    <div class="form-row">

                        <label for="notes">
                            Notes <span class="optional">(optional)</span>
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            maxlength="250"
                            placeholder="Anything we should know?"
                        ></textarea>

                    </div>


                    <!-- Scheduling restrictions -->

                    <p class="schedule-hint">
                        Requests must be made at least 24 hours in advance
                        and can last up to 72 hours.
                    </p>


                    <!-- Submit request -->

                    <button type="submit">
                        Submit Request
                    </button>

                    <p
                        id="scheduleError"
                        class="schedule-error"
                        role="alert"
                    ></p>

                </form>

            </section>

        </aside>

    </div>

</main>


<?php
/*
 * Shared footer.
 * Must appear after the page content.
 */
include __DIR__ . '/includes/footer.php';
?>
