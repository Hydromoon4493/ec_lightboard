ECLightboard Standalone API Test Pages
======================================

CONTENTS
--------
Nine independent PHP test pages:

1. test_authentication.php
2. test_schedule.php
3. test_displays.php
4. test_upload.php
5. test_jobs.php
6. test_admin.php
7. test_thumbnails.php
8. test_permissions.php
9. test_database.php

https://lightshow.etowndb.com/ec_lightboard/web/tests/test_authentication.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_schedule.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_displays.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_upload.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_jobs.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_admin.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_thumbnails.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_permissions.php
https://lightshow.etowndb.com/ec_lightboard/web/tests/test_database.php

IMPORTANT
---------
- Every test file is standalone. It does not need a separate JavaScript or CSS file.
- Tests use same-origin browser requests, so your current login session is used.
- These tests are intentionally conservative and avoid changing/deleting real records.
- The jobs test intentionally sends no Pi token and expects the claim endpoint to reject it.
- Upload tests check the upload page/form; they do not submit a real file.
- Some tests report SKIP for operations that would mutate real records.
- Run these on your local/test installation first, not against production data.

API ROUTES USED
---------------
api/authentication/profile.php
api/authentication/change_email.php
api/authentication/change_password.php
api/schedule/schedule.php
api/displays/manage_display.php
api/jobs/pi_claim.php
api/users/users.php
api/display/thumbnail.php
upload.php

If your local project uses a different base path, keep the tests folder under
web/ so the relative ../api and ../upload.php paths resolve correctly.
