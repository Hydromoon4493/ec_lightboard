# Lightboard: Initial User Stories

## Epic 1: User Accounts

| ID | User Story | Priority |
|----|------------|----------|
| US-01 | As a new user, I want to create an account so that I can manage my own images and settings. | Must Have |
| US-02 | As a registered user, I want to log in and log out securely so that my uploaded images are protected. | Must Have |
| US-03 | As a logged-in user, I want to see only the images I uploaded so that I can manage my own content. | Must Have |
| US-04 | As an administrator, I want to manage user accounts so that I can remove misuse of the system. | Could Have |

## Epic 2: Image Upload and Management

| ID | User Story | Priority |
|----|------------|----------|
| US-05 | As a user, I want to upload a custom image so that it can be displayed on the lightboard. | Must Have |
| US-06 | As a user, I want the system to resize or convert my uploaded image to fit the LED matrix resolution so that it displays correctly. | Must Have |
| US-07 | As a user, I want to see a preview of how my image will look on the LED grid before displaying it so that I can adjust it if needed. | Should Have |
| US-08 | As a user, I want to view a gallery of my uploaded images so that I can find and select one quickly. | Must Have |
| US-09 | As a user, I want to rename or add a title to my images so that I can organize them. | Should Have |
| US-10 | As a user, I want to delete an uploaded image so that I can remove ones I no longer want. | Must Have |
| US-11 | As a user, I want to be told if my upload is an unsupported file type or too large so that I can fix the problem. | Must Have |

## Epic 3: Preset Images and Image Library

| ID | User Story | Priority |
|----|------------|----------|
| US-12 | As a user, I want to browse a library of preset images so that I can use the lightboard without uploading anything. | Must Have |
| US-13 | As a user, I want to select a preset image to display on the lightboard so that I can quickly show something. | Must Have |
| US-14 | As a user, I want preset images grouped by category (e.g., holidays, patterns, shapes) so that I can find what I need. | Should Have |
| US-15 | As a user, I want to mark images as favorites so that I can access them quickly. | Could Have |
| US-16 | As an administrator, I want to add or remove preset images so that the library stays current. | Should Have |

## Epic 4: Display Control and Timing

| ID | User Story | Priority |
|----|------------|----------|
| US-17 | As a user, I want to set how long a picture is displayed so that I control how long it stays on the board. | Must Have |
| US-18 | As a user, I want to create a playlist of multiple images so that they display one after another. | Must Have |
| US-19 | As a user, I want to set a different display duration for each image in a playlist so that I have control over the timing. | Should Have |
| US-20 | As a user, I want to reorder the images in a playlist so that I can change the display sequence. | Should Have |
| US-21 | As a user, I want a playlist to loop continuously so that the lightboard can run without supervision. | Should Have |
| US-22 | As a user, I want to pause, resume, or stop the current display so that I can control the board in real time. | Must Have |
| US-23 | As a user, I want to adjust the brightness of the LEDs so that the board looks right in different lighting. | Should Have |
| US-24 | As a user, I want to choose a transition between images (e.g., fade, wipe) so that changes look smooth. | Could Have |
| US-25 | As a user, I want to schedule images or playlists to display at specific times so that the board changes automatically. | Could Have |

## Epic 5: Hardware and Raspberry Pi Integration

| ID | User Story | Priority |
|----|------------|----------|
| US-26 | As a user, I want the Raspberry Pi to retrieve images from the database and send them to the LED matrix so that the board displays them. | Must Have |
| US-27 | As a user, I want the lightboard to resume its last playlist after a power cycle so that I don't have to restart it manually. | Should Have |
| US-28 | As a user, I want to see the lightboard's current status (online, displaying, idle) so that I know it is working. | Should Have |
| US-29 | As a user, I want to control the lightboard from a web interface on my phone or computer so that I don't need physical access to the Pi. | Must Have |
| US-30 | As a user, I want the board to show a default image or turn off when nothing is scheduled so that it is never in an unknown state. | Should Have |
| US-31 | As a user, I want the board that is easy to maintain and fits well in it's room. | Must Have |

## Epic 6: RESTful API

| ID | User Story | Priority |
|----|------------|----------|
| US-32 | As a client application, I want to retrieve a list of preset images through the REST API so that image data can be used programmatically. | Must Have |
| US-33 | As an authenticated client, I want to upload an image through the REST API so that functionality is not limited to the web interface. | Should Have |
| US-34 | As an authenticated client, I want to start, stop, and set the duration of a display through the REST API so that the board can be controlled by other applications. | Must Have |
| US-35 | As an API consumer, I want responses returned in JSON so that the data can easily be consumed by web and mobile applications. | Must Have |
| US-36 | As an API consumer, I want appropriate HTTP status codes (200, 201, 400, 401, 404, 500) so that my application can handle success and failure correctly. | Must Have |
