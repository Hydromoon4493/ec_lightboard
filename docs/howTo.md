**## borderTest**
cd ~/Documents/ec_lightboard/sketches/borderTest

**## SolidColor Test**
cd ~/Documents/ec_lightboard/sketches/SolidColorTest

**## 3ChainTest**
cd ~/Documents/ec_lightboard/sketches/3ChainTest

**## 123456789**
cd ~/Documents/ec_lightboard/sketches/123456789

**## blueJay**
cd ~/Documents/ec_lightboard/sketches/blueJay


**### Compile & Upload**
arduino-cli compile --upload -p /dev/ttyUSB0 --fqbn esp32:esp32:esp32 .

**### Upload**
arduino-cli upload -p /dev/ttyUSB0 --fqbn esp32:esp32:esp32 .

**## Authentication/Role Guidelines**
The admin role should be reserved for faculty who manage the LED Board and approve/deny display requests. I do not imagine there being many admin users, so our security practice is for the role to be granted manually through the main website/DB manager. Running the SQL statement below will grant the admin role to the selected user.
### Promote admin
UPDATE users SET roleID = (SELECT roleID FROM roles WHERE roleName = 'admin') WHERE email = 'user email';
### Demote admin
UPDATE users SET roleID = (SELECT roleID FROM roles WHERE roleName = 'user') WHERE email = 'user email';
### Remove user
DELETE FROM users WHERE email = 'user email';