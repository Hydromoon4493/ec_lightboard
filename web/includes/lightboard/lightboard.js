/*
 * Author: Sam Vossen 2026.10.15
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

document.addEventListener('DOMContentLoaded', function () {
    const brightnessSlider = document.getElementById('b');
    const brightnessValue = document.getElementById('brightnessValue');

    brightnessSlider.addEventListener('input', function () {
        brightnessValue.textContent = this.value;
    });
});

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
 * The button parameter is retained because the existing
 * interface passes it into this function. It can be used later
 * if we want to provide button-specific loading states.
 */
async function displayImage(src, id, button) {

  /* ==========================================================
   * RESET DISPLAY STATE
   * ========================================================== */

  statusCard.classList.add('show');

  statusList.innerHTML = '';

  resultBox.innerHTML = '';

  previewCard.style.display = 'none';

  logCard.style.display = 'none';


  /* ==========================================================
   * DISABLE CONTROLS
   * ========================================================== */

  setButtonsDisabled(true);

  addStatus('Selecting image...');


  /* ==========================================================
   * BUILD REQUEST
   * ========================================================== */

  const form = new FormData();

  form.append('action', 'display_stream');

  form.append('src', src);

  form.append('id', id);

  form.append('brightness', b.value);


  /* ==========================================================
   * SEND REQUEST
   * ========================================================== */

  try {

    /*
     * The current working application posts the request to
     * the current PHP page.
     *
     * During the API integration, this should be changed to
     * the public Lightboard API endpoint.
     */
    const response = await fetch(window.location.href, {
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
       * Convert the incoming bytes into text.
       *
       * stream: true allows UTF-8 characters to span
       * multiple chunks safely.
       */
      buffer += decoder.decode(value, {
        stream: true
      });


      /*
       * The server sends one JSON object per line.
       */
      const lines = buffer.split('\n');


      /*
       * Keep the incomplete final line in the buffer.
       */
      buffer = lines.pop();


      for (const line of lines) {

        if (!line.trim()) {
          continue;
        }


        /* ====================================================
         * PARSE SERVER EVENT
         * ==================================================== */

        let event;

        try {
          event = JSON.parse(line);

        } catch (e) {

          /*
           * Ignore malformed/incomplete JSON rather than
           * stopping the entire display request.
           */
          continue;
        }


        /* ====================================================
         * STATUS EVENT
         * ==================================================== */

        if (event.type === 'status') {

          addStatus(event.message);
        }


        /* ====================================================
         * SUCCESS EVENT
         * ==================================================== */

        else if (event.type === 'success') {

          addStatus(
            event.message,
            'success'
          );

          resultBox.innerHTML =
            '<div class="msg ok">' +
            escapeHtml(event.message) +
            '</div>';
        }


        /* ====================================================
         * ERROR EVENT
         * ==================================================== */

        else if (event.type === 'error') {

          addStatus(
            event.message,
            'error'
          );

          resultBox.innerHTML =
            '<div class="msg bad">' +
            escapeHtml(event.message) +
            '</div>';
        }


        /* ====================================================
         * PREVIEW EVENT
         * ==================================================== */

        else if (event.type === 'preview') {

          previewImage.src = event.message;

          previewCard.style.display = 'block';
        }
      }
    }


  /* ==========================================================
   * REQUEST ERROR
   * ========================================================== */

  } catch (error) {

    addStatus(
      'Error: ' + error.message,
      'error'
    );

    resultBox.innerHTML =
      '<div class="msg bad">' +
      escapeHtml(
        'Display request failed: ' +
        error.message
      ) +
      '</div>';


  /* ==========================================================
   * RESTORE CONTROLS
   * ========================================================== */

  } finally {

    setButtonsDisabled(false);
  }
}
