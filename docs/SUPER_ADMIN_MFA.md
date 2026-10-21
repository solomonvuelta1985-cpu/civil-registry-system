# Super Admin and MFA Operations

## Roles

- **Super Admin** has all routine Admin permissions and controls system configuration, devices, backup and restore, PDF version management, upload reorganization, and privileged accounts.
- **Admin** handles routine operations, records, reports, archives/trash, logs, and PDF inventory/reconciliation. Admins can view and manage Encoder and Viewer accounts only. Admin and Super Admin accounts are hidden from Admin user lists and counts.
- **Encoder** and **Viewer** retain their existing record permissions.

Page and API checks enforce these boundaries. Hiding a sidebar link is only a usability feature; it is not the authorization check.

## Deployment and first Super Admin

1. Back up the database.
2. Configure a dedicated 32-byte encryption key in the application `.env` file. Generate it once with:

   ```bash
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

   Set the output as `MFA_ENCRYPTION_KEY`. Keep the key outside source control, restrict access to `.env`, and store a secure backup separately from the database. Losing the key prevents authenticator secrets from being decrypted.
3. For an existing installation, apply migrations `056_super_admin_mfa.sql` and `057_super_admin_mfa_authenticators.sql` **before activating the updated authentication code**. The session validator and login flow read the MFA device table and session version, so deploying the code before these migrations prevents Super Admin sign-in. On the Synology NAS, follow Section 5 of `NAS_DEPLOYMENT_GUIDE.md` to stage the target migration files in `/tmp` and run the CLI migration runner with `--env-file=/volume1/iscan/release_blue_candidate_20260930/.env`. For a normal application checkout with `.env` at its root, check and apply the migrations in order:

   ```bash
   php database/apply_single_migration.php --check 056_super_admin_mfa.sql
   php database/apply_single_migration.php --apply 056_super_admin_mfa.sql
   php database/apply_single_migration.php --check 057_super_admin_mfa_authenticators.sql
   php database/apply_single_migration.php --apply 057_super_admin_mfa_authenticators.sql
   ```

   For a fresh database, use the normal migration runner as part of schema setup. Confirm migrations 056 and 057 completed before activating application files that read the MFA device table and session-version column.
4. Deploy the application code and confirm the key is available to PHP.
5. Promote the intended existing active Admin exactly once:

   ```bash
   php scripts/bootstrap_super_admin.php USERNAME --confirm-bootstrap
   ```

   The command refuses if any Super Admin account already exists, verifies the named account is an active Admin, requires the encryption key, and records the promotion in `security_logs`.
6. Sign in with that account. After password and device checks, the account must enroll an authenticator app and confirm a current six-digit code. The enrollment page shows a QR code and the manual setup key; scanning is optional. Save the ten one-time recovery codes securely before continuing.
7. Add or promote a second active Super Admin and have that person complete MFA enrollment before using controlled restore workflows. Super Admin user creation and role changes are available under **Users**.

## Manage authenticator devices

- The **My MFA** account action lists the signed-in Super Admin's authenticator devices and unused recovery-code count. Each account can have up to five devices.
- Adding a device requires the account password and a fresh code from an existing device, followed by a valid code from the new device. The pending setup page shows a QR code and manual setup key for that new device. Device secrets are encrypted, never displayed again after confirmation, and each device tracks its own accepted TOTP step.
- QR codes are generated locally in the browser from the pending `otpauth://` URI. They do not use an external QR service. Treat the QR and setup key as secrets; both disappear when setup is canceled, expires, or completes. If QR generation is unavailable, use the displayed manual setup key or setup URI.
- Removing a device requires the account password and a fresh code from an existing device. The last device cannot be removed through self-service. If every device is lost, another active Super Admin can perform a full MFA reset.
- Replacing recovery codes requires the account password and a fresh authenticator code. It invalidates all earlier recovery codes; new codes are shown once and only their hashes are stored.
- Under **Users**, another active Super Admin can reset an account's MFA after confirming their own password and current authenticator code, entering a reason, and confirming the action. The reset revokes every target device and recovery code, invalidates the target's existing sessions, and requires fresh enrollment on the next sign-in. An administrator cannot reset their own MFA from the web page.
- The CLI reset remains available to an authorized deployment operator when no peer Super Admin can help:

  ```bash
  php scripts/reset_super_admin_mfa.php USERNAME --confirm-mfa-reset
  ```

## MFA behavior and recovery

- Every Super Admin must complete TOTP enrollment after password verification. Until then, the session is restricted to `public/mfa.php` and expires after ten minutes.
- TOTP secrets are encrypted using AES-256-GCM with `MFA_ENCRYPTION_KEY`. The database stores only password hashes for one-time recovery codes.
- TOTP codes are accepted within one 30-second time step on either side of the server clock and each accepted step can be used only once. Keep the application server clock synchronized.
- If an account still has a recovery code, sign in with that code in place of a TOTP code. Each recovery code is consumed once.
- If the authenticator and all recovery codes are unavailable, use the server CLI as an authorized operator:

  ```bash
  php scripts/reset_super_admin_mfa.php USERNAME --confirm-mfa-reset
  ```

  This clears the stored authenticator and recovery codes, records the reset, and requires fresh enrollment on the next sign-in. It does not change the role or password.
- Back up the encryption key and database together under separate access controls. If the key is lost or changed, install the replacement key and run the MFA reset command for each active Super Admin so each can enroll a new authenticator; old encrypted secrets cannot be decrypted with the replacement key.

## Restore approval requirement

Controlled PDF production recovery and live database restore require at least two active Super Admin accounts. The restore helper checks the approver's active role in the database when recording approval. The creator cannot supply the first independent approval, and the second stage must be approved by a different Super Admin. Enable Maintenance Mode as required by the restore workflow.

The PDF Inventory page remains available to Admins for scans. Backfill and individual restore controls are Super Admin-only.

## Cloudflare account MFA

`CLOUDFLARE_SECURITY_CHECKLIST.md` covers MFA for the Cloudflare account and Cloudflare members. That protection is separate from iScan's Super Admin authenticator MFA described here.
