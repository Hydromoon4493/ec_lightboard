
<?php
/*
 * Author: Sam Vossen 2026.11.12
 * Filename: gallery.php
 *
 * This page displays the available Lightboard preset and custom displays.
 *
 * Responsibilities:
 * - Load the shared site navigation and styling.
 * - Allow public access to preset Lightboard images.
 * - Load available custom Lightboard images for logged-in users.
 * - Prompt logged-out visitors to log in to upload custom displays.
 * - Display images in separate gallery columns.
 * - Allow users to rename and delete their own custom displays.
 * - Allow administrators to rename and delete preset displays.
 *
 * This file does NOT:
 * - Display images on the Lightboard.
 * - Process image files.
 * - Generate pixel maps.
 * - Communicate directly with the ESP32.
 * - Create or schedule Lightboard jobs.
 */

/* ============================================================
 * SESSION
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['userID']);
$userID = $isLoggedIn ? (int) $_SESSION['userID'] : 0;

/* ============================================================
 * LOAD LIGHTBOARD FUNCTIONS
 * ============================================================
 */

require_once __DIR__ . '/includes/lightboard/core/images.php';

/* ============================================================
 * LOAD USER PERMISSIONS
 * ============================================================
 */

$isAdmin = false;

if ($isLoggedIn) {

    $pdo = db();

    $roleStmt = $pdo->prepare(
        'SELECT roleID FROM users WHERE userID = ? LIMIT 1'
    );

    $roleStmt->execute([$userID]);

    $isAdmin = ((int) $roleStmt->fetchColumn() === 1);
}

/* ============================================================
 * CSRF TOKEN
 * ============================================================
 */

$csrfToken = '';

if ($isLoggedIn) {

    if (empty($_SESSION['lightboard_csrf'])) {
        $_SESSION['lightboard_csrf'] = bin2hex(random_bytes(32));
    }

    $csrfToken = $_SESSION['lightboard_csrf'];
}

/* ============================================================
 * LOAD GALLERY IMAGES
 * ============================================================
 */

$presetImages = listImages('p');

$customImages = [];

if ($isLoggedIn) {
    $customImages = listImages('c', $userID);
}

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

include __DIR__ . '/includes/layout/navbar.php';
?>

<link rel="stylesheet" href="includes/css/lightboard.css?v=8">
<link rel="stylesheet" href="includes/css/gallery.css?v=3">

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

                <?php if ($key === 'c' && !$isLoggedIn): ?>

                    <div class="gallery-login-prompt">

                        <h3>Want to upload your own display?</h3>

                        <p>
                            Log in to your account to upload and manage
                            your own custom Lightboard displays.
                        </p>

                        <a href="login.php" class="gallery-login-button">
                            Log In to Upload Custom Displays
                        </a>

                    </div>

                <?php elseif (empty($g['images'])): ?>

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

                                <span class="animation-tag <?= !empty($image['isAnimated']) ? 'gif' : 'static' ?>">
                                    <?= !empty($image['isAnimated']) ? 'ANIMATED GIF' : 'STATIC IMAGE' ?>
                                </span>

                                <?php
                                /*
                                 * Custom displays:
                                 * Logged-in users can manage their own uploads.
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
                                            onclick="manageDisplay(
                                                'rename',
                                                '<?= $key ?>',
                                                <?= (int) $image['id'] ?>,
                                                <?= htmlspecialchars(
                                                    json_encode($image['name']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            )"
                                        >
                                            Rename
                                        </button>

                                        <button
                                            type="button"
                                            class="delete-button"
                                            onclick="manageDisplay(
                                                'delete',
                                                '<?= $key ?>',
                                                <?= (int) $image['id'] ?>
                                            )"
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

    </div>

</main>

<?php if ($isLoggedIn): ?>

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

        console.error('Gallery management error:', error);

        alert('A connection error occurred. Please try again.');

    }
}
</script>

<?php endif; ?>

<?php
include __DIR__ . '/includes/layout/footer.php';
?>
