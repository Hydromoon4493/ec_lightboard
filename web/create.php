<?php
    //require __DIR__ . '/includes/auth/require_login.php';
    include 'includes/layout/navbar.php';
?>

<link rel="stylesheet" href="includes/css/style.css?v=2">
<link rel="stylesheet" href="includes/css/create.css?v=3">

<main class="bodyDefult create-page">
    <h1>Create a Picture for the Lightboard!</h1>
    <p>Pick a color, then click or drag across the board to paint.</p>

    <div class="create-layout">

        <!-- Board -->
        <div class="create-card board-card">
            <div class="board-grid" id="lights" aria-label="Lightboard canvas"></div>
        </div>

        <!-- Tools -->
        <aside class="create-card tools-card">

            <section>
                <h2>Colors</h2>

                <div class="palette" id="palette">
                    <?php
                        $colors = [
                            '#ff0000', '#ff8000', '#ffbf00', '#ffff00', '#80ff00',
                            '#00ff00', '#00ff80', '#00ffff', '#0080ff', '#0000ff',
                            '#8000ff', '#bf00ff', '#ff00ff', '#ff0080', '#ff80c0',
                            '#804000', '#808080', '#c0c0c0', '#ffe0c0', '#ffffff',
                        ];

                        foreach ($colors as $c):
                    ?>
                        <button
                            type="button"
                            class="swatch"
                            data-color="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                            style="background-color: <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="Color <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>">
                        </button>
                    <?php endforeach; ?>
                </div>

                <label class="custom-color">
                    Edit selected color
                    <input type="color" id="colorPicker" value="#ff0000">
                </label>
            </section>

            <section>
                <h2>Tools</h2>

                <div class="tool-buttons">
                    <div class="bg-status">
                        Current background
                        <span class="bg-status__value">
                            <span class="bg-status__swatch" id="bgSwatch"></span>
                            <span id="bgLabel">Off</span>
                        </span>
                    </div>

                    <button type="button" class="tool-button" id="bgBtn">
                        Set Background
                    </button>

                    <div class="tool-row">
                        <button type="button" class="tool-button" id="eraserBtn">
                            Eraser
                        </button>

                        <button type="button" class="tool-button" id="clearBtn">
                            Clear Board
                        </button>
                    </div>
                </div>
            </section>

            <section class="save-section">
                <h2>Save</h2>

                <div class="tool-buttons">
                    <label class="name-field">
                        Display Name
                        <input
                            type="text"
                            id="displayName"
                            maxlength="45"
                            placeholder="Enter a display name">
                    </label>

                    <button
                        type="button"
                        class="tool-button tool-button--primary"
                        id="saveBtn">
                        Upload Image
                    </button>

                    <div class="save-message" id="saveMessage" hidden></div>
                </div>
            </section>

        </aside>
    </div>
</main>

<script>
    const COLS = 48;
    const ROWS = 48;

    const lights  = document.getElementById('lights');
    const palette = document.getElementById('palette');
    const picker  = document.getElementById('colorPicker');
    const eraser  = document.getElementById('eraserBtn');

    let currColor = null;
    let selectedSwatch = null;
    let painting = false;
    let bgColor = null;

    const bgSwatch = document.getElementById('bgSwatch');
    const bgLabel  = document.getElementById('bgLabel');

    // Build the 48 x 48 board.
    lights.style.setProperty('--cols', COLS);

    for (let i = 0; i < COLS * ROWS; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.tabIndex = -1;
        lights.appendChild(btn);
    }

    // Paint a board cell using the currently selected color.
    function paint(cell) {
        if (!cell || cell.parentElement !== lights) return;

        cell.style.backgroundColor = currColor ?? '';

        if (currColor) {
            cell.dataset.color = currColor;
        } else {
            delete cell.dataset.color;
        }
    }

    // Click and drag painting.
    lights.addEventListener('pointerdown', (e) => {
        e.preventDefault();

        painting = true;
        paint(e.target.closest('button'));
    });

    lights.addEventListener('pointermove', (e) => {
        if (!painting) return;

        paint(
            document.elementFromPoint(e.clientX, e.clientY)?.closest('button')
        );
    });

    document.addEventListener('pointerup', () => {
        painting = false;
    });

    document.addEventListener('pointercancel', () => {
        painting = false;
    });

    lights.addEventListener('dragstart', (e) => {
        e.preventDefault();
    });

    // Select a palette color.
    function selectSwatch(swatch) {
        if (!swatch) return;

        selectedSwatch?.classList.remove('is-selected');
        eraser.classList.remove('is-selected');

        selectedSwatch = swatch;
        swatch.classList.add('is-selected');

        currColor = swatch.dataset.color;
        picker.value = currColor;
    }

    palette.addEventListener('click', (e) => {
        const swatch = e.target.closest('.swatch');

        if (swatch) {
            selectSwatch(swatch);
        }
    });

    // Custom color picker edits the selected swatch.
    picker.addEventListener('input', () => {
        if (!selectedSwatch) {
            selectSwatch(palette.querySelector('.swatch'));
        }

        selectedSwatch.dataset.color = picker.value;
        selectedSwatch.style.backgroundColor = picker.value;
        currColor = picker.value;
    });

    // Eraser tool.
    eraser.addEventListener('click', () => {
        selectedSwatch?.classList.remove('is-selected');

        selectedSwatch = null;
        eraser.classList.add('is-selected');
        currColor = null;
    });

    // Set the board background.
    function setBackground(color) {
        bgColor = color;

        if (color) {
            lights.style.setProperty('--board-bg', color);
        } else {
            lights.style.removeProperty('--board-bg');
        }

        bgSwatch.style.backgroundColor = color ?? '';
        bgLabel.textContent = color ? color.toUpperCase() : 'Off';
    }

    document.getElementById('bgBtn').addEventListener('click', () => {
        setBackground(currColor);
    });

    // Clear the board.
    document.getElementById('clearBtn').addEventListener('click', () => {
        if (!confirm('Clear the whole board?')) return;

        for (const cell of lights.children) {
            cell.style.backgroundColor = '';
            delete cell.dataset.color;
        }

        setBackground(null);
    });

    // Save elements.
    const saveBtn   = document.getElementById('saveBtn');
    const nameInput = document.getElementById('displayName');
    const saveMsg   = document.getElementById('saveMessage');

    function showMessage(message, type) {
        saveMsg.textContent = message;
        saveMsg.className = 'save-message ' + type;
        saveMsg.hidden = false;
    }

    // Convert the board into a 48 x 48 PNG.
    function boardToPng() {
        const canvas = document.createElement('canvas');

        canvas.width = COLS;
        canvas.height = ROWS;

        const ctx = canvas.getContext('2d');

        ctx.fillStyle = bgColor ?? '#000000';
        ctx.fillRect(0, 0, COLS, ROWS);

        [...lights.children].forEach((cell, i) => {
            if (!cell.dataset.color) return;

            ctx.fillStyle = cell.dataset.color;

            ctx.fillRect(
                i % COLS,
                Math.floor(i / COLS),
                1,
                1
            );
        });

        return new Promise(resolve => {
            canvas.toBlob(resolve, 'image/png');
        });
    }

    const saveErrors = {
        error: 'There was a problem saving the display.',
        too_large: 'The image is too large.',
        invalid: 'The image could not be read.'
    };

    // Upload the PNG to the custom display handler.
    saveBtn.addEventListener('click', async () => {
        const name = nameInput.value.trim();

        if (!name) {
            showMessage('Please enter a display name.', 'error');
            nameInput.focus();
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const png = await boardToPng();

            if (!png) {
                throw new Error('Could not create PNG.');
            }

            const form = new FormData();

            form.append('name', name);
            form.append('image', png, 'creation.png');

            const res = await fetch(
                'includes/lightboard/displays/custom_upload_db.php',
                {
                    method: 'POST',
                    body: form
                }
            );

            const status = new URL(res.url).searchParams.get('upload');

            if (status === 'success') {
                showMessage(
                    `"${name}" saved! You can find it under Custom Displays.`,
                    'success'
                );

                nameInput.value = '';
            } else {
                showMessage(
                    saveErrors[status] ?? 'Could not save. Are you logged in?',
                    'error'
                );
            }

        } catch (err) {
            console.error('Lightboard save error:', err);
            showMessage('Could not reach the server.', 'error');

        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Upload Image';
        }
    });

    // Keep the board column sized to the tools card.
    const layout = document.querySelector('.create-layout');
    const toolsCard = document.querySelector('.tools-card');

    const resizeObserver = new ResizeObserver(() => {
        layout.style.setProperty(
            '--board-col',
            toolsCard.offsetHeight + 'px'
        );
    });

    resizeObserver.observe(toolsCard);

    // Start with the first color selected.
    selectSwatch(palette.querySelector('.swatch'));
</script>

<?php
    include 'includes/layout/footer.php';
?>
