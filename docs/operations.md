# Operations

This document records operational behavior visible in the source. Runtime execution, server configuration, WP-Cron delivery, Google APIs, mail delivery, and web-server access were not verified in this checkout.

## Local backup

`UDC_Backup` derives the directory from `wp_upload_dir()` and uses:

```text
wp-content/uploads/udc-backups/
```

`UDC_Backup::create_backup()` serializes all rows from the custom table to a timestamped JSON file. `rotate_backups()` sorts all `*.json` files in that directory by modification time and deletes the oldest files until five remain. The same class provides administrative actions for manual creation, restoring a named local file, and uploading a JSON file.

Restore is additive: `insert_backup_data()` validates the complete bounded document, checks each supplied positive `id`, and inserts rows that are not already present. Current-schema rows require a nonempty `zip`. The exact historical schema used by v1.0.0 and v1.1.0, which contains `zip_city` instead of `city` and `zip`, is also supported: the full historical `zip_city` value is copied to `city` and `zip` is set to the empty string. Empty `zip` is permitted only after that exact legacy schema is positively identified; current-schema rows and mixed current/legacy documents are rejected. Imports use a transaction when the table is InnoDB and fail closed otherwise.

**Verified limitations:** the local protection files still depend on web-server behavior, and no functional WordPress or database runtime gate exists in this checkout. Backup creation bounds the full-table snapshot to 10,000 rows, rejects encoded JSON larger than the 10 MiB restore limit, writes through a locked temporary file, and atomically renames it before rotation.

## Scheduled work

The activator requests these events:

| Event | Source | Intended interval |
| --- | --- | --- |
| `udc_daily_backup_action` | `UDC_Backup` | WordPress `daily` |
| `udc_weekly_gdrive_sync_action` | `UDC_GDrive` | `udc_weekly`, 604800 seconds |
| `udc_monthly_email_sync_action` | `UDC_Email_Sync` | `udc_monthly`, 2592000 seconds |

`UDC_Activator::activate()` registers the custom weekly and monthly schedules before requesting events and checks scheduling results. Deactivation clears every matching timestamp. WP-Cron delivery and runtime execution remain unverified.

WP-Cron is traffic- and configuration-dependent. A registered event is not proof that the task has run, ran on time, or succeeded.

## Google Drive

When enabled, `UDC_GDrive::sync_backups()` paginates a bounded Drive listing, uploads missing local JSON files with the exact `appProperties.udc_backup = 1` marker, and keeps marked files separate from unmarked strict legacy-name observations. Only marker-owned files are eligible for automatic rotation and deletion. An unmarked historical filename may suppress a duplicate upload but is never automatically deleted. HTTP status, JSON structure, required fields, ownership properties, and successful deletion responses are checked.

**Verified limitation:** Drive API behavior, permissions, pagination, and deletion were not executed against a real or isolated Drive environment.

## Email backup

When enabled, `UDC_Email_Sync::send_backup()` selects the newest local `*.json` file by modification time and sends it as an attachment using `wp_mail()`. The configured recipient and optional sender values come from plugin options. Mailbox retention, forwarding, transport, and provider access are outside this plugin.

## Delete All Data

The administrative `UDC_Settings::ajax_delete_all_data()` action checks capability and nonce, deletes local JSON files, deletes all rows from the custom table, and removes the listed Drive, email, design, and status options. It does not remove Drive objects, sent email attachments, or other external copies. It also does not unschedule events or remove every possible WordPress metadata item.

## No-verification commands

No WP-CLI or `wp-env` command is presented as an executed run here. The repository does not contain a versioned `.wp-env.json`, and the `@wordpress/env` dependency is not fixed in the package metadata. Any future command such as `npx wp-env start` or `npx wp-env run cli wp ...` must be treated as **No verification** until it is actually run in an authorized environment.
