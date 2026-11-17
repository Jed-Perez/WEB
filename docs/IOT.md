# ESP32 temperature integration

The web ingestion path is implemented and tested with real HTTP requests against MariaDB. Physical ESP32/DHT22 operation, wiring, sensor accuracy and Wi-Fi coverage were **not** tested because no device was supplied.

## Configure a device

1. Sign in as administrator, open Facility monitoring, and add a device linked to an active facility.
2. Set an appropriate name, minimum/maximum temperature, and stale interval. Defaults are 15–40°C and five minutes; these are implementation defaults, not validated safety thresholds.
3. Save the one-time key privately. Only its SHA-256 hash remains in the database.
4. On the ESP32, use the XAMPP computer's LAN address and `http://<LAN-IP>/webngakingbaby/api/sensor.php`. Both machines must have network connectivity. Permit Apache on the chosen trusted LAN interface/port through the Windows firewall if needed. `localhost` on the ESP32 refers to the ESP32, not the PC.
5. The supplied `.ino` example targets a DHT22 sensor on GPIO 4. Install the ESP32 board support, Adafruit DHT sensor library and Adafruit Unified Sensor in Arduino IDE. Copy `iot/config.example.h` to `iot/config.h`, provide actual configuration and upload the sketch. Check the sensor/module's wiring and pull-up requirements before powering it.
6. Observe Facility monitoring and Temperature history. The dashboard shows server receipt time in Asia/Manila. Stop sending samples to confirm stale detection. Compare readings against an independent reference to validate accuracy.

## HTTP contract

```http
POST /webngakingbaby/api/sensor.php
Authorization: Bearer <64-character-device-key>
Content-Type: application/json

{"sample_id":"device-boot-session-0001","temperature":28.25}
```

Success:

```json
{"success":true,"message":"Temperature received.","data":{"id":1,"duplicate":false}}
```

`sample_id` must be 1–80 characters from letters, digits, dot, underscore, colon or hyphen. Use a unique boot/session identifier plus a monotonic sequence. Retrying the same sample ID and rounded value returns the original ID with `duplicate:true`; the sample does not insert twice or make an old device appear newly online. Reusing an ID with a different value returns 409. Invalid/out-of-range values return 422; valid range is -55 to 125°C. A new sample within five seconds returns 429. The example sends every thirty seconds and retries transient failures with the same payload.

A disabled/unknown device key returns 401. A rotated key immediately replaces the old credential. Keys never appear in ordinary device listings, logs, or reports. Browser session cookies and CSRF tokens are not device credentials. The device endpoint validates its bearer key and accepts POST only. Apache `CGIPassAuth On` is included to expose Authorization to PHP under XAMPP.

HTTP is suitable only for the intended trusted local demonstration network. For untrusted networks, configure HTTPS and certificate verification on both Apache and the ESP32. The provided example is not a cloud service, health/safety alarm or substitute for sensor calibration. A restart loses the sketch's in-memory retry queue; durable device buffering was not specified in the source requirements.

Library references: [Espressif Arduino ESP32](https://github.com/espressif/arduino-esp32), [Adafruit DHT sensor library](https://github.com/adafruit/DHT-sensor-library).
