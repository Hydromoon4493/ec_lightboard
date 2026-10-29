<?php
    require __DIR__ . '/includes/require_login.php';
    include 'includes/navbar.php';
?>

<link rel="stylesheet" href="includes/style.css">

<main class="bodyDefult">
    <h1>Create a Picture for the Lightboard!</h1>
    <div class="create-container">
        <div class="" id="actions">
            <button type="button" data-action="red">Red</button>
            <button type="button" data-action="green">Green</button>
            <button type="button" data-action="clear">Clear</button>
        </div>

        <div class="button-grid" id="lights">
            <script>
                const grid = document.getElementById('lights');
                grid.style.gap = '1px';
                const cols = 48, rows = 48;
                grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
             
                for (let i = 1; i <= cols * rows; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.addEventListener('click', () => btn.classList.toggle('on'));

                    grid.appendChild(btn);

                    if(i % rows + 1 == 0) {
                        const br = document.createElement('br');
                        grid.appendChild(br);
                    }
                }
            </script>
        </div>    
    </div>
    

</main>

<script>
    const currColor = { value: 'black' };

    document.getElementById('lights').addEventListener('mouseenter', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        btn.style.backgroundColor = currColor.value;
    });

    document.getElementById('actions').addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;                       // clicked the gap, not a button
        currColor.value = btn.dataset.action;    // store the action in currColor
        grid.style.setProperty('--curr-color', currColor.value);

        console.log('Clicked:', btn.dataset.action);
        // e.g. if (btn.dataset.action === 'clear') { ... }
    });

</script>

<?php
    include 'includes/footer.php';
?>
