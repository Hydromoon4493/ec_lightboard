/*
 * Author: Sam Vossen 2026.10.18
 * Filename: includes/lightboard/lightboard.js
 *
 * This file contains the client-side JavaScript for the
 * Lightboard display interface.
 *
 * Responsibilities:
 * - Start a Lightboard display request.
 * - Send the selected image and brightness to the server.
 * - Read the streamed response from the server.
 * - Update the status display as progress messages arrive.
 * - Display success and error messages.
 * - Display the generated Lightboard preview.
 * - Re-enable interface buttons when the request finishes.
 *
 * This file does NOT:
 * - Process images.
 * - Communicate directly with MySQL.
 * - Communicate directly with the ESP32.
 * - Generate Arduino sketches.
 *
 * The server-side Lightboard API handles those operations.
 */


/* ============================================================
 * UI HELPERS
 * ============================================================ */

/*
 * Adds a line to the status list.
 *
 * The previous "active" line is marked "done" so the list reads
 * as a checklist, with the newest line showing as in progress.
 * Passing 'success' or 'error' as kind styles the final line.
 */
function addStatus(message, kind) {
  const list = document.getElementById('statusList');

  const previous = list.querySelector('.status-line.active');

  if (previous) {
    previous.classList.remove('active');
    previous.classList.add('done');
  }

  const line = document.createElement('div');

  line.className = 'status-line ' + (kind || 'active');

  /*
   * textContent (not innerHTML) means server messages can
   * never inject HTML into the page.
   */
  line.textContent = message;

  list.appendChild(line);
}


/*
 * Shows the final success/error message in the result box.
 */
function showResult(message, ok) {
  const box = document.getElementById('resultBox');

  const div = document.createElement('div');

  div.className = 'msg ' + (ok ? 'ok' : 'bad');

  div.textContent = message;

  box.replaceChildren(div);
}


/*
 * Disables or re-enables every Display button and the
 * brightness slider while a request is running.
 */
function setButtonsDisabled(disabled) {
  document
    .querySelectorAll('.gallery-item button')
    .forEach(function (btn) {
      btn.disabled = disabled;
    });

  document.getElementById('b').disabled = disabled;
}


/* ============================================================
 * DISPLAY IMAGE
 * ============================================================
 *
 * Starts the process of displaying an image on the Lightboard.
 *
 * Parameters:
 *
 * src:
 *     Identifies the image source.
 *     Example: "p" for presets or "c" for custom uploads.
 *
 * id:
 *     Database ID of the selected image.
 *
 * button:
 *     Button that initiated the display request.
 *
 * The button parameter is retained because display.php passes
 * it into this function. It can be used later if we want
 * button-specific loading states.
 */
async function displayImage(src, id, button) {

  /* ==========================================================
   * ELEMENT REFERENCES
   * ========================================================== */

  const statusCard  = document.getElementById('statusCard');
  const statusList  = document.getElementById('statusList');
  const resultBox   = document.getElementById('resultBox');
  const previewCard = document.getElementById('previewCard');
  const previewImg  = document.getElementById('previewImage');


  /* ==========================================================
   * RESET DISPLAY STATE
   * ========================================================== */

  statusCard.classList.add('show');

  statusList.innerHTML = '';

  resultBox.innerHTML = '';

  previewCard.style.display = 'none';


  /* ==========================================================
   * DISABLE CONTROLS
   * ========================================================== */

  setButtonsDisabled(true);

  addStatus('Selecting image...');


  /* ==========================================================
   * BUILD REQUEST
   * ========================================================== */

  const form = new FormData();

  form.append('src', src);

  form.append('id', id);

  form.append('brightness', document.getElementById('b').value);


  /* ==========================================================
   * EVENT HANDLING
   * ========================================================== */

  /*
   * Tracks whether the server sent a final success or error.
   * If the stream ends without one, something went wrong
   * server-side (for example a PHP fatal error).
   */
  let finished = false;

  function handleEvent(event) {

    if (event.type === 'status') {

      addStatus(event.message);

    } else if (event.type === 'preview') {

      previewImg.src = event.message;

      previewCard.style.display = 'block';

    } else if (event.type === 'success') {

      finished = true;

      addStatus(event.message, 'success');

      showResult(event.message, true);

    } else if (event.type === 'error') {

      finished = true;

      /*
       * The status list gets only the first line. The full
       * message (which may include arduino-cli output) goes
       * in the result box.
       */
      addStatus(event.message.split('\n')[0], 'error');

      showResult(event.message, false);
    }
  }

  function handleLine(line) {

    if (!line.trim()) {
      return;
    }

    try {
      handleEvent(JSON.parse(line));

    } catch (e) {

      /*
       * Ignore non-JSON lines (such as a PHP warning)
       * rather than stopping the entire display request.
       */
    }
  }


  /* ==========================================================
   * SEND REQUEST
   * ========================================================== */

  try {

    /*
     * Posts the request to the streaming Lightboard endpoint.
     * The path is relative to display.php, so this works while
     * the gallery page sits in the web root.
     */
    const response = await fetch('api/display_stream.php', {
      method: 'POST',
      body: form,
      cache: 'no-store'
    });


    /* ========================================================
     * CHECK HTTP RESPONSE
     * ======================================================== */

    if (!response.ok) {
      throw new Error(
        'HTTP error ' +
        response.status +
        ' (' +
        response.statusText +
        ')'
      );
    }


    /* ========================================================
     * CHECK STREAMING SUPPORT
     * ======================================================== */

    if (!response.body) {
      throw new Error(
        'The browser did not provide a streaming response.'
      );
    }


    /* ========================================================
     * READ STREAM
     * ======================================================== */

    const reader = response.body.getReader();

    const decoder = new TextDecoder();

    let buffer = '';

    while (true) {

      const { value, done } = await reader.read();

      if (done) {
        break;
      }

      /*
       * stream: true allows UTF-8 characters to span
       * multiple chunks safely.
       */
      buffer += decoder.decode(value, { stream: true });

      /*
       * The server sends one JSON object per line.
       * Keep the incomplete final line in the buffer.
       */
      const lines = buffer.split('\n');

      buffer = lines.pop();

      lines.forEach(handleLine);
    }

    /*
     * Handle anything left in the buffer when the stream ends.
     */
    handleLine(buffer);

    if (!finished) {
      throw new Error(
        'The server stopped without a result. ' +
        'Check the PHP server log.'
      );
    }


  /* ==========================================================
   * REQUEST ERROR
   * ========================================================== */

  } catch (error) {

    addStatus('Error: ' + error.message, 'error');

    showResult(
      'Display request failed: ' + error.message,
      false
    );


  /* ==========================================================
   * RESTORE CONTROLS
   * ========================================================== */

  } finally {

    setButtonsDisabled(false);
  }
}
