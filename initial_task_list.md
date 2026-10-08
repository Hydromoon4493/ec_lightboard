# Lightboard: Task Breakdown (3-Person Team)

## Planning Assumptions

- **Team size:** 3 people (P1, P2, P3)
- **Capacity:** 2 tasks per person per week x 3 people = 6 tasks per week
- **Timeline:** 7 full weeks (42 tasks) + a half week (3 tasks) = **45 tasks**
- Each task is sized for one person to finish in a few hours to a day.
- **Roles:**
  - **P1** **Scott** Web front end, UI, database
  - **P2** **Isaac** LED matrix and enclosure
  - **P3** **Sam** Raspberry Pi, backend

---

## Week 1: Planning and Setup

| ID | Task | Story |
|----|------|-------|
| T-01 | Set up the Git repo, branching rules, project skeleton, and README | All |
| T-02 | Design the database schema/ERD (users, images, presets, playlists, playlist items) and write the database creation scripts | US-01, 05, 18 |
| T-03 | Build the bill of materials and order or inventory parts (matrix, power supply, fuse, enclosure materials) | US-36, 38, 47 |
| T-04 | Choose the LED matrix library and document the matrix specs (resolution, pinout, power requirements) | US-37, 38 |
| T-05 | Flash Raspberry Pi OS, enable SSH and Wi-Fi, install dependencies | US-26 |
| T-06 | Choose and document the web/backend tech stack and define the REST API endpoint list and JSON format | All, US-31 to 35 |

## Week 2: Foundations

| ID | Task | Story |
|----|------|-------|
| T-07 | Create wireframes for the main screens (login, gallery, upload, playlist, control panel) | US-29 |
| T-08 | Build the base web layout, navigation, and styling, plus the registration and login pages | US-01, 02, 29 |
| T-09 | Wire the LED matrix to the Pi and display a test pattern | US-37 |
| T-10 | Write the image resize/convert function for the matrix resolution | US-06 |
| T-11 | Build the registration and login/logout backend with password hashing and sessions | US-01, 02 |
| T-12 | Build the upload endpoint with file type and size validation and clear error messages | US-05, 11 |

## Week 3: Gallery and Pi Integration

| ID | Task | Story |
|----|------|-------|
| T-13 | Build the upload form page and display validation errors to the user | US-05, 11 |
| T-14 | Build the personal gallery page (own images only) with rename/title and delete with a confirmation prompt | US-03, 08, 09, 10 |
| T-15 | Draft the power plan and first version of the wiring diagram | US-38, 49 |
| T-16 | Mount the Pi securely with standoffs and a mounting plate | US-36 |
| T-17 | Build the gallery backend (list own images, rename, and delete endpoints) | US-03, 08, 09, 10 |
| T-18 | Integrate the Pi so it pulls an image from the database and displays it on the matrix | US-26 |

## Week 4: Presets and Display Basics

| ID | Task | Story |
|----|------|-------|
| T-19 | Collect starter preset images, load them into the database, and build the preset library page with categories | US-12, 14 |
| T-20 | Build the "Display this image" button and the duration input UI with validation (reject zero, negative, non-numeric) | US-13, 17 |
| T-21 | Implement brightness control at the matrix/library level | US-23 |
| T-22 | Install the power switch, fuse, and final power wiring | US-39, 47 |
| T-23 | Build a background display service on the Pi that runs continuously | US-26 |
| T-24 | Build the web-to-Pi "display this image" command and the display duration/timer logic | US-13, 17 |

## Week 5: Playlists and Display Control

| ID | Task | Story |
|----|------|-------|
| T-25 | Build the playlist create/edit UI with per-image durations, reordering, and a loop option | US-18, 19, 20, 21 |
| T-26 | Build the control panel UI (pause, resume, stop, brightness slider, and status indicator) | US-22, 23, 28 |
| T-27 | Organize and label internal wiring and add strain relief | US-40 |
| T-28 | Build and fasten the back panel to seal the enclosure and add ventilation or cooling | US-41, 42 |
| T-29 | Implement pause, resume, and stop, the brightness endpoint, and the status endpoint (online, displaying, idle) | US-22, 23, 28 |
| T-30 | Build the playlist backend (data model, per-image durations, reordering, and continuous loop playback) | US-18, 19, 20, 21 |

## Week 6: REST API and Professional Finish

| ID | Task | Story |
|----|------|-------|
| T-31 | Build the LED grid preview before displaying an image | US-07 |
| T-32 | Build the admin pages for adding/removing presets and managing accounts | US-04, 16 |
| T-33 | Build the removable/hinged access panel and cut openings for power and network ports | US-43, 48 |
| T-34 | Install the diffuser or front cover and build and finish the frame/bezel | US-44, 45 |
| T-35 | Build the API endpoints to retrieve presets and images (JSON), plus API token authentication and the image upload endpoint | US-31, 32, 34 |
| T-36 | Build the API endpoints to start, stop, and set duration, and add proper HTTP status codes and error handling to all endpoints | US-33, 35 |

## Week 7: Remaining Features and Testing

| ID | Task | Story |
|----|------|-------|
| T-37 | Polish the UI for phone and desktop (responsive layout, error states, usability fixes) | US-29 |
| T-38 | Verify database integrity (e.g., deleting an image removes it from playlists) and clean up test data | US-10, 18 |
| T-39 | Build the stand or wall mount | US-46 |
| T-40 | Run a multi-hour burn-in test (heat, brightness, power stability) | US-38, 42 |
| T-41 | Set up auto-start on boot (systemd), resume the last playlist, and a default idle state | US-27, 30 |
| T-42 | Build the admin backend and write automated tests for auth, upload, and API endpoints | US-02, 04, 05, 16, 31 to 35 |

## Week 8 (Half Week): Wrap-Up and Demo

| ID | Task | Story |
|----|------|-------|
| T-43 | Write user documentation and the demo script, and rehearse the demo | All |
| T-44 | Finalize the wiring diagram and assembly notes and add the label or logo to the board | US-49, 50 |
| T-45 | Run end-to-end testing on the real hardware against each story's acceptance criteria and fix bugs found | All |

---

## Stretch Tasks (only if ahead of schedule)

| ID | Task | Story |
|----|------|-------|
| T-46 | Add favorites for images | US-15 |
| T-47 | Add transitions between images (fade, wipe) | US-24 |
| T-48 | Add scheduling of images/playlists by time | US-25 |
