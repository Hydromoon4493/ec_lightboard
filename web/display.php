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
 * - Display images in separate gallery columns.
 * - Allow users to display their custom and preset images.
 * - Allow users to rename and delete their own custom images.
 * - Allow administrators to rename and delete preset images.
 * - Load the Lightboard JavaScript interface.
 *
 * This file does NOT:
 * - Process image files.
 * - Generate pixel maps.
 * - Communicate directly with the ESP32.
 * - Compile or upload Arduino sketches.
 */


require __DIR__ . '/includes/require_login.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/lightboard/images.php';

/* ============================================================
 * LOAD USER PERMISSIONS
 * ============================================================
 */

$pdo = db();

$roleStmt = $pdo->prepare(
    'SELECT roleID FROM users WHERE userID = ? LIMIT 1'
);

$roleStmt->execute([
    (int) $_SESSION['userID']
]);

$isAdmin = ((int) $roleStmt->fetchColumn() === 1);

/* ============================================================
 * CSRF TOKEN
 * ============================================================
 */

if (empty($_SESSION['lightboard_csrf'])) {
    $_SESSION['lightboard_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['lightboard_csrf'];

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
        'empty'  => 'No custom displays are currently available.',
    ],
];

include __DIR__ . '/includes/navbar.php';
?>

<link rel="stylesheet" href="includes/css/style.css?v=2">
<link rel="stylesheet" href="includes/css/lightboard.css?v=7">

<main class="bodyDefult lightboard-page">

    <h1>EC Lightboard</h1>

    <p>
        Select an image below to display it on the Lightboard.
    </p>

    <div class="lightboard-layout">

        <!-- Preset and Custom Galleries -->

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
                                    src="api/display/thumbnail.php?src=<?= $key ?>&id=<?= (int) $image['id'] ?>"
                                    alt="<?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                >

                                <h3>
                                    <?= htmlspecialchars($image['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <button
                                    type="button"
                                    onclick="displayImage('<?= $key ?>', <?= (int) $image['id'] ?>)"
                                >
                                    Display
                                </button>

                                <?php
                                /*
                                 * Custom displays:
                                 * Every user can manage their own uploads.
                                 *
                                 * Preset displays:
                                 * Only administrators can manage them.
                                 */
                                ?>

                                <?php if ($key === 'c' || ($key === 'p' && $isAdmin)): ?>

                                    <div class="gallery-management">

                                        <button
                                            type="button"
                                            class="rename-button"
                                            onclick="manageDisplay('rename', '<?= $key ?>', <?= (int) $image['id'] ?>, <?= htmlspecialchars(json_encode($image['name']), ENT_QUOTES, 'UTF-8') ?>)"
                                        >
                                            Rename
                                        </button>

                                        <button
                                            type="button"
                                            class="delete-button"
                                            onclick="manageDisplay('delete', '<?= $key ?>', <?= (int) $image['id'] ?>)"
                                        >
                                            Delete
                                        </button>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>


        <!-- Column 3: Controls, Status, Preview -->

        <aside class="lightboard-sidebar">

            <section class="lightboard-controls">

                <h2>Display Settings</h2>

                <div class="brightness-notice">

                    <strong>Brightness Notice</strong>

                    <p>
                        With lower brightness, some pixels may appear to be off.
                        If this happens, turn the brightness up.
                    </p>

                </div>

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

            <section id="statusCard" class="status-card">

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

        </aside>

    </div>

</main>


<script>
/*
 * Rename or delete a Lightboard display.
 *
 * The backend independently verifies permissions
 * and ownership before performing the operation.
 */

async function manageDisplay(action, source, id, currentName = '') {

    let newName = '';

    if (action === 'rename') {

        newName = prompt(
            'Enter the new display name:',
            currentName
        );

        if (newName === null) {
            return;
        }

        newName = newName.trim();

        if (newName === '') {
            alert('Display name cannot be empty.');
            return;
        }

        if (newName.length > 45) {
            alert('Display name cannot exceed 45 characters.');
            return;
        }

    } else if (action === 'delete') {

        if (!confirm('Are you sure you want to permanently delete this display?')) {
            return;
        }

    } else {
        return;
    }

    const formData = new URLSearchParams();

    formData.append('action', action);
    formData.append('source', source);
    formData.append('id', id);
    formData.append('name', newName);
    formData.append('csrf_token', <?= json_encode($csrfToken) ?>);

    try {

        const response = await fetch('api/displays/manage_display.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString(),
            credentials: 'same-origin'
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            alert(result.message || 'Could not update display.');
            return;
        }

        alert(result.message);

        window.location.reload();

    } catch (error) {

        console.error('Display management error:', error);

        alert('A connection error occurred. Please try again.');

    }

}
</script>


<script src="includes/js/lightboard.js?v=20261029"></script>

<?php
include __DIR__ . '/includes/footer.php';
?>
