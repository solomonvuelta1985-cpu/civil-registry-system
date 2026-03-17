# Device Lock Security — Feature Documentation

**iScan Civil Registry Records Management System**
**Version:** 1.0 | **Date:** 2026-02-17 | **Author:** System Administrator

---

## Table of Contents

1. [Overview](#1-overview)
2. [How It Works](#2-how-it-works)
3. [System Architecture](#3-system-architecture)
4. [Database Schema](#4-database-schema)
5. [File Reference](#5-file-reference)
6. [Configuration](#6-configuration)
7. [Setup Guide](#7-setup-guide)
8. [Admin Usage Guide](#8-admin-usage-guide)
9. [Testing the Feature](#9-testing-the-feature)
10. [Security Considerations](#10-security-considerations)
11. [Troubleshooting](#11-troubleshooting)
12. [API Reference](#12-api-reference)
13. [PHP Function Reference](#13-php-function-reference)

---

## 1. Overview

The **Device Lock Security** feature restricts access to the iScan system so that only **pre-approved physical devices** can log in — even if an unauthorized user somehow obtains a valid username and password.

### Problem it solves

| Scenario | Without Device Lock | With Device Lock |
|---|---|---|
| Attacker gets credentials | Can log in from any device | Blocked — device not registered |
| Employee uses personal laptop | Allowed | Blocked |
| Unauthorized URL access | Shows login page | Blocked before login even shows |
| Registered office PC | Allowed | Allowed |

### Key characteristics

- Works at the **login stage** — unregistered devices are blocked before credentials are checked
- Uses **browser fingerprinting** (not IP addresses) — works across networks and WiFi changes
- **No extra hardware required** — purely software-based
- **Admin-managed** — only admin users can register or revoke devices
- **Fully audited** — all registrations, revocations, and block events are logged

---

## 2. How It Works

### Login flow with Device Lock ENABLED

```
User visits login page
        │
        ▼
JS generates device fingerprint
(SHA-256 hash of browser + GPU signals)
        │
        ▼
Fingerprint submitted with login form (hidden field)
        │
        ▼
Server checks: Is ENABLE_DEVICE_LOCK=true?
        │
      YES │                NO │
        ▼                    ▼
Is fingerprint in          Normal login
registered_devices         (no device check)
table with status=Active?
        │
    YES │           NO │
        ▼               ▼
Update last_seen   Redirect to
Continue to        device_blocked.php
credential check   (hard block — no login)
        │
        ▼
Normal username/password validation
```

### Device fingerprint composition

The fingerprint is a **SHA-256 hash** of 11 browser/hardware signals concatenated together:

| Signal | Example Value | Purpose |
|---|---|---|
| User-Agent | `Mozilla/5.0 (Windows NT 10.0...)` | Browser + OS identification |
| Language | `en-US` | Browser language setting |
| Platform | `Win32` | Operating system platform |
| CPU cores | `8` | Hardware logical core count |
| Screen resolution | `1920x1080` | Monitor resolution |
| Color depth | `24` | Display color depth (bits) |
| Pixel depth | `24` | Display pixel depth |
| Timezone | `Asia/Manila` | System timezone |
| Touch points | `0` | Max touch points (0 for desktop) |
| Canvas render | *(PNG pixel data)* | GPU-unique rendering output |
| WebGL renderer | `ANGLE (Intel, UHD 620)` | GPU model and driver string |

All signals are joined, encoded as UTF-8, and hashed using `crypto.subtle.digest('SHA-256')`. The result is a **64-character hex string** stored in the database.

### Why not IP address?

| Method | Problem |
|---|---|
| IP address | Changes when connecting to different networks, WiFi, or when using DHCP |
| Serial number | Requires OS-level access; not accessible from a web browser |
| Browser fingerprint | Stable per device+browser combination; no special access needed |

---

## 3. System Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Browser (Client)                   │
│                                                      │
│  device-fingerprint.js ──► SHA-256 hash             │
│  login.php form         ──► POST with fingerprint   │
│  device_blocked.php     ◄── redirect if blocked     │
└──────────────────────────┬──────────────────────────┘
                           │ HTTPS POST
┌──────────────────────────▼──────────────────────────┐
│                    PHP Server                        │
│                                                      │
│  public/login.php          ← entry point            │
│    └─ includes/device_auth.php                      │
│         └─ isDeviceLockEnabled()                    │
│         └─ checkDeviceRegistered($hash)             │
│         └─ updateDeviceLastSeen($hash, $ip)         │
│                                                      │
│  admin/devices.php         ← management UI          │
│  api/device_save.php       ← register endpoint      │
│  api/device_delete.php     ← revoke/reactivate      │
└──────────────────────────┬──────────────────────────┘
                           │ PDO / MySQL
┌──────────────────────────▼──────────────────────────┐
│               registered_devices table               │
│  id | device_name | fingerprint_hash | status | ...  │
└─────────────────────────────────────────────────────┘
```

---

## 4. Database Schema

**Table:** `registered_devices`

```sql
CREATE TABLE IF NOT EXISTS registered_devices (
    id               INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_name      VARCHAR(100)  NOT NULL,
    fingerprint_hash CHAR(64)      NOT NULL,
    registered_by    INT(11) UNSIGNED NOT NULL,
    registered_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_seen_at     TIMESTAMP     NULL,
    last_seen_ip     VARCHAR(45)   NULL,
    status           ENUM('Active','Revoked') DEFAULT 'Active',
    notes            TEXT          NULL,

    UNIQUE KEY uniq_fingerprint (fingerprint_hash),
    INDEX idx_status       (status),
    INDEX idx_registered_by (registered_by),
    INDEX idx_last_seen    (last_seen_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Column descriptions

| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED | Auto-increment primary key |
| `device_name` | VARCHAR(100) | Human-readable label (e.g. "Front Desk PC") |
| `fingerprint_hash` | CHAR(64) | SHA-256 hex hash of browser fingerprint |
| `registered_by` | INT UNSIGNED | FK → `users.id` (admin who registered it) |
| `registered_at` | TIMESTAMP | When the device was first registered |
| `last_seen_at` | TIMESTAMP | Last successful login from this device |
| `last_seen_ip` | VARCHAR(45) | IP address at last login (IPv4 or IPv6) |
| `status` | ENUM | `Active` = allowed to log in; `Revoked` = blocked |
| `notes` | TEXT | Optional admin notes (e.g. room number, OS version) |

### Migration file

Located at: `database/migrations/006_registered_devices.sql`

To run manually:
```bash
mysql -u root iscan_db < database/migrations/006_registered_devices.sql
```

---

## 5. File Reference

All files added or modified for this feature:

### New files

| File | Purpose |
|---|---|
| `assets/js/device-fingerprint.js` | Client-side fingerprint generator (SHA-256) |
| `includes/device_auth.php` | Server-side helper functions |
| `admin/devices.php` | Admin management UI |
| `api/device_save.php` | REST endpoint — register a device |
| `api/device_delete.php` | REST endpoint — revoke / reactivate |
| `public/device_blocked.php` | Hard-block page for unregistered devices |
| `database/migrations/006_registered_devices.sql` | DB migration |

### Modified files

| File | Change |
|---|---|
| `includes/config.php` | Added `ENABLE_DEVICE_LOCK` constant and `device_auth.php` require |
| `public/login.php` | Added device fingerprint check before credential validation |
| `includes/security_headers.php` | Updated CSP to allow CDN domains (Lucide, Notiflix, Font Awesome) |
| `includes/sidebar_nav.php` | Added "Devices" link in admin sidebar |
| `database_schema.sql` | Appended `registered_devices` table definition |
| `.env.example` | Added `ENABLE_DEVICE_LOCK` setting |
| `.env` | Created with `ENABLE_DEVICE_LOCK=true` |

---

## 6. Configuration

The feature is controlled by a single environment variable in your `.env` file:

```ini
# Device Lock Security
ENABLE_DEVICE_LOCK=false
# false = any device can log in (safe for development / initial setup)
# true  = ONLY registered devices can log in (recommended for production)
```

### When to use each value

| Value | When to use |
|---|---|
| `false` | Initial setup (before devices are registered), development/testing |
| `true` | Production — after all office PCs have been registered |

> **Important:** Always register all devices BEFORE setting `ENABLE_DEVICE_LOCK=true`. If you lock yourself out, set it back to `false` in `.env` and restart Apache.

---

## 7. Setup Guide

Follow these steps in order when setting up Device Lock for the first time.

### Step 1 — Run the database migration

```bash
# On Windows (XAMPP):
"c:/xampp/mysql/bin/mysql.exe" -u root iscan_db < database/migrations/006_registered_devices.sql

# On Linux/Synology:
mysql -u root -p iscan_db < database/migrations/006_registered_devices.sql
```

Verify the table was created:
```sql
SHOW TABLES LIKE 'registered_devices';
```

### Step 2 — Keep Device Lock disabled initially

In your `.env` file, ensure:
```ini
ENABLE_DEVICE_LOCK=false
```

This allows you to log in from any device during setup.

### Step 3 — Log in as admin

Go to `http://localhost/iscan/public/login.php` and log in with your admin credentials.

### Step 4 — Register all office devices

For **each computer** that needs access:

1. Open the system on that computer
2. Navigate to **Admin → Devices** (sidebar)
3. Click **"Register This Device"**
4. Enter a recognizable name (e.g. `Encoder Station 1`, `Mayor's Office PC`)
5. Optionally add notes (e.g. room number, Windows version)
6. Click **Register Device**
7. Confirm the device appears in the table with status **Active**

### Step 5 — Enable the lock

Once all devices are registered, edit `.env`:
```ini
ENABLE_DEVICE_LOCK=true
```

No Apache restart is needed — the setting is read on every request.

### Step 6 — Test the block

Open an **incognito/private browser window** and go to the login page. You should be redirected to the device blocked page immediately.

---

## 8. Admin Usage Guide

### Accessing the Devices page

`Admin Panel → Devices` (sidebar, under System section)

URL: `http://localhost/iscan/admin/devices.php`

> Requires: Admin role

### Page overview

```
┌─────────────────────────────────────────────────────┐
│  [!] Device Lock is DISABLED / [✓] ACTIVE banner    │
├──────────┬──────────┬──────────┬────────────────────┤
│ Registered│  Active  │  Revoked │   Lock Status      │
│    3      │    2     │    1     │  ✓ Enforced        │
├─────────────────────────────────────────────────────┤
│ Device Registry             [+ Register This Device] │
├────┬──────────────┬────────────┬──────┬─────────────┤
│ #  │ Device Name  │ Device ID  │Status│ Actions     │
├────┼──────────────┼────────────┼──────┼─────────────┤
│ 1  │ Front Desk   │ a3f9b2c1…  │Active│ [Revoke]    │
│ 2  │ Encoder 2    │ 7de4f1a2…  │Active│ [Revoke]    │
│ 3  │ Old Laptop   │ 9bc3d5e6…  │Revoked│[Reactivate]│
└────┴──────────────┴────────────┴──────┴─────────────┘
```

### Registering a device

1. From the computer you want to register, open `admin/devices.php`
2. Click **"Register This Device"**
3. A modal appears showing the device's fingerprint hash (auto-generated)
4. Enter a **Device Name** (required, max 100 characters)
5. Enter optional **Notes** (e.g. physical location, OS version)
6. Click **Register Device**

> The fingerprint shown in the modal is unique to that browser on that computer. If you register from a different browser on the same PC, it generates a different fingerprint.

### Revoking a device

Click **Revoke** next to the device. After confirmation, the device status changes to `Revoked`. The device will be blocked on the next login attempt.

> Revoke is a soft-delete — the record is kept for audit purposes. The device can be reactivated later.

### Reactivating a device

Click **Reactivate** next to a revoked device. The status changes back to `Active` and the device can log in again immediately.

### Viewing the Device ID (fingerprint)

The fingerprint is truncated in the table (first 18 characters + `…`). Click on it to copy the full 64-character hash to clipboard.

---

## 9. Testing the Feature

### Quick test checklist

- [ ] **Test 1 — Register works:** Go to `admin/devices.php`, click "Register This Device", enter a name, click Register. Device should appear in table.
- [ ] **Test 2 — Lock is active:** The green banner reads "Device Lock is ACTIVE" (requires `ENABLE_DEVICE_LOCK=true` in `.env`)
- [ ] **Test 3 — Unregistered device is blocked:** Open an incognito window, go to login page → should redirect to `device_blocked.php`
- [ ] **Test 4 — Registered device passes:** In your normal browser, log in normally → succeeds
- [ ] **Test 5 — Revoke blocks:** Revoke your device, log out, try to log in → blocked
- [ ] **Test 6 — Reactivate restores:** Reactivate device, try again → login succeeds
- [ ] **Test 7 — Last seen updates:** After logging in, check the "Last Seen" column in `admin/devices.php` — it should update

### Testing environment

| Browser Mode | Expected Behavior |
|---|---|
| Normal browser (registered) | Login page shows, can log in |
| Incognito / Private window | Redirected to `device_blocked.php` |
| Different browser (unregistered) | Redirected to `device_blocked.php` |
| Same browser after revoking | Redirected to `device_blocked.php` |

---

## 10. Security Considerations

### Strengths

- **IP-independent** — works across different networks, WiFi, hotspots
- **Pre-authentication block** — attacker with stolen credentials still cannot log in
- **Audit trail** — all events logged in `security_logs` and `activity_logs`
- **CSRF-protected** — all registration/revocation API calls require CSRF token
- **Admin-only management** — regular users cannot register or view devices

### Limitations

| Limitation | Explanation |
|---|---|
| Browser-specific | Registering Chrome does not cover Firefox on the same PC |
| Not hardware-locked | Reinstalling Windows or clearing browser data changes the fingerprint |
| Canvas blocking | Some privacy browsers/extensions block canvas fingerprinting; the fallback hash is less unique |
| Browser updates | Major browser updates may slightly change the fingerprint (rare) |

### Best practice

- Register the **same browser** that staff will use daily (e.g. Chrome)
- Re-register a device after a Windows reinstall or browser profile reset
- Review the `last_seen_at` column periodically to identify unused/stale devices
- Keep `ENABLE_DEVICE_LOCK=false` only during setup; enable it once all devices are registered

### What happens to blocked devices

When a device is blocked, `public/device_blocked.php` is shown. It displays:
- The device's fingerprint hash (so the user can share it with admin)
- Instructions to contact the system administrator
- No system information is leaked

The block event is logged in `security_logs` with event type `DEVICE_BLOCKED` and severity `HIGH`.

---

## 11. Troubleshooting

### "Nothing happens" after clicking Register

**Cause:** Usually a JavaScript error (check browser console with F12)

Common causes:
- Lucide or Notiflix scripts blocked by CSP → fixed in `security_headers.php`
- `ENABLE_DEVICE_LOCK=false` (the lock is off, so no visible change after registration)
- No `.env` file (defaults used, lock defaults to `false`)

**Fix:** Ensure `.env` exists with `ENABLE_DEVICE_LOCK=true`, hard-refresh with Ctrl+Shift+R

---

### API returns 403 Forbidden

**Cause 1 — Not logged in as admin:** Only admin users can call device API endpoints.

**Cause 2 — CSRF token mismatch:** This can happen if the page was open for a very long time and the session expired.

**Fix:** Log out, log back in as admin, refresh `devices.php`, try again.

---

### Locked out — can't log in from any device

**Fix:** Edit `.env` and set `ENABLE_DEVICE_LOCK=false`, then reload the login page.

```ini
ENABLE_DEVICE_LOCK=false
```

Then log in, go to `admin/devices.php`, re-register your device, and set it back to `true`.

---

### Device registered but still blocked

**Possible causes:**

1. **Wrong browser** — you registered Chrome but are logging in with Edge (different fingerprint)
2. **Incognito mode** — canvas fingerprint may differ from normal mode in some browsers
3. **Status is Revoked** — check the status column in `admin/devices.php`
4. **`.env` not loaded** — ensure `.env` exists and `ENABLE_DEVICE_LOCK=true` is set

**Debug:** On the `device_blocked.php` page, your fingerprint hash is shown. Compare it against the hash in `admin/devices.php`. If they differ, the wrong device/browser was registered.

---

### Fingerprint changes after browser update

Browser updates rarely change the fingerprint, but it can happen with major GPU driver or OS updates. If this occurs:

1. Set `ENABLE_DEVICE_LOCK=false` in `.env`
2. Log in and go to `admin/devices.php`
3. Revoke the old device entry
4. Register the device again (new fingerprint)
5. Set `ENABLE_DEVICE_LOCK=true`

---

## 12. API Reference

### POST `/api/device_save.php`

Registers the current device's fingerprint as a trusted device.

**Authentication:** Must be logged in as Admin
**CSRF:** Required (`csrf_token` field)

**Request body (form-data):**

| Field | Type | Required | Description |
|---|---|---|---|
| `csrf_token` | string | Yes | CSRF token from page meta tag |
| `device_fingerprint` | string | Yes | 64-char SHA-256 hex from `DeviceFingerprint.get()` |
| `device_name` | string | Yes | Human-readable name (max 100 chars) |
| `notes` | string | No | Optional notes |

**Success response:**
```json
{
  "success": true,
  "message": "Device \"Front Desk PC\" registered successfully"
}
```

**Error responses:**
```json
{ "success": false, "message": "This device is already registered as: Front Desk PC" }
{ "success": false, "message": "Device name is required (max 100 characters)" }
{ "success": false, "message": "Invalid device fingerprint" }
{ "success": false, "message": "Admin access required" }
```

---

### POST `/api/device_delete.php`

Revokes or reactivates a registered device.

**Authentication:** Must be logged in as Admin
**CSRF:** Required

**Request body (form-data):**

| Field | Type | Required | Description |
|---|---|---|---|
| `csrf_token` | string | Yes | CSRF token |
| `device_id` | int | Yes | ID of device to update |
| `action` | string | Yes | `revoke` or `reactivate` |

**Success response:**
```json
{ "success": true, "message": "Device revoked successfully" }
{ "success": true, "message": "Device reactivated successfully" }
```

---

## 13. PHP Function Reference

All functions are in `includes/device_auth.php`.

---

### `isDeviceLockEnabled(): bool`

Returns `true` if `ENABLE_DEVICE_LOCK` is set to a truthy value in `.env`.

```php
if (isDeviceLockEnabled()) {
    // enforce device check
}
```

---

### `checkDeviceRegistered(string $hash): array|false`

Checks if a fingerprint hash exists in `registered_devices` with `status = 'Active'`.

```php
$device = checkDeviceRegistered($fingerprintHash);
if (!$device) {
    header('Location: device_blocked.php');
    exit;
}
```

**Returns:** Associative array `['id', 'device_name', 'status']` or `false`

---

### `updateDeviceLastSeen(string $hash, string $ip): void`

Updates `last_seen_at` and `last_seen_ip` for a device after successful login.

```php
updateDeviceLastSeen($fingerprintHash, $_SERVER['REMOTE_ADDR'] ?? '');
```

---

### `registerDevice(string $hash, string $name, int $userId, string $notes = ''): bool`

Inserts a new device into `registered_devices`. Returns `false` if the fingerprint already exists.

```php
$ok = registerDevice($hash, 'Front Desk PC', $adminUserId, 'Room 3');
```

---

### `getAllDevices(): array`

Returns all registered devices joined with the registering admin's `full_name`, ordered by `registered_at DESC`.

```php
$devices = getAllDevices();
foreach ($devices as $device) {
    echo $device['device_name'] . ' — ' . $device['status'];
}
```

---

### `revokeDevice(int $deviceId): bool`

Sets a device's status to `'Revoked'`. Returns `true` if a row was updated.

```php
$ok = revokeDevice(3);
```

---

### `reactivateDevice(int $deviceId): bool`

Sets a device's status back to `'Active'`. Returns `true` if a row was updated.

```php
$ok = reactivateDevice(3);
```

---

### `countActiveDevices(): int`

Returns the total count of devices with `status = 'Active'`.

```php
$count = countActiveDevices(); // e.g. 4
```

---

## Quick Reference Card

```
┌─────────────────────────────────────────────────────┐
│           Device Lock — Quick Reference              │
├─────────────────────────────────────────────────────┤
│  Enable lock:    .env → ENABLE_DEVICE_LOCK=true     │
│  Disable lock:   .env → ENABLE_DEVICE_LOCK=false    │
│                                                      │
│  Manage devices: Admin → Devices (sidebar)           │
│  URL:            /iscan/admin/devices.php            │
│                                                      │
│  Block page:     /iscan/public/device_blocked.php   │
│                                                      │
│  DB table:       registered_devices                  │
│  Migration:      database/migrations/006_*.sql       │
│                                                      │
│  Locked out?     Set ENABLE_DEVICE_LOCK=false        │
│                  in .env, then re-register           │
└─────────────────────────────────────────────────────┘
```
