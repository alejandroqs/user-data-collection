# Operations

This document records operational behavior visible in the source. Runtime execution, server configuration, WP-Cron delivery, Google APIs, mail delivery, and web-server access were not verified in this checkout.

## Local backup

`UDC_Backup` derives the directory from `wp_upload_dir()` and uses:

```text
wp-content/uploads/udc-backups/
```

`UDC_Backup::create_backup()` serializes all rows from the custom table to a timestamped JSON file. `rotate_backups()` sorts all `*.json` files in that directory by modification time and deletes the oldest files until five remain. The same class provides administrative actions for manual creation, restoring a named local file, and uploading a JSON file.

Restore is additive: `insert_backup_data()` checks each supplied `id` and inserts rows that are not already present. It does not overwrite existing rows or wrap the complete operation in a transaction.

**Verified limitations:** file creation return values are not checked; upload handling does not impose a size or schema limit; JSON is parsed before a complete row allowlist or format validation; and local protection uses an `.htaccess` file whose effect depends on the web server.

## Scheduled work

The activator requests these events:

| Event | Source | Intended interval |
| --- | --- | --- |
| `udc_daily_backup_action` | `UDC_Backup` | WordPress `daily` |
| `udc_weekly_gdrive_sync_action` | `UDC_GDrive` | `udc_weekly`, 604800 seconds |
| `udc_monthly_email_sync_action` | `UDC_Email_Sync` | `udc_monthly`, 2592000 seconds |

**Verified scheduling limitation:** `UDC_Activator::activate()` requests the custom weekly and monthly events before the service constructors register their `cron_schedules` filters. The scheduler return values are not checked. The event may therefore fail to register in the intended custom interval. This requires runtime verification and code changes outside this documentation task.

WP-Cron is traffic- and configuration-dependent. A registered event is not proof that the task has run, ran on time, or succeeded.

## Google Drive

When enabled, `UDC_GDrive::sync_backups()` lists every non-trashed file in the configured folder, compares names with local `*.json` files, uploads missing local names, then keeps at most five files by deleting the oldest results from the folder listing.

**Verified limitation:** the Drive query does not filter by a plugin-specific name or MIME type before rotation. Other files in the configured folder can therefore count toward the five-file limit and can be selected for deletion. A failed HTTP response that is not represented as `WP_Error` can also be counted as a deletion success because the current code does not validate the response status for delete requests.

## Email backup

When enabled, `UDC_Email_Sync::send_backup()` selects the newest local `*.json` file by modification time and sends it as an attachment using `wp_mail()`. The configured recipient and optional sender values come from plugin options. Mailbox retention, forwarding, transport, and provider access are outside this plugin.

## Delete All Data

The administrative `UDC_Settings::ajax_delete_all_data()` action checks capability and nonce, deletes local JSON files, deletes all rows from the custom table, and removes the listed Drive, email, design, and status options. It does not remove Drive objects, sent email attachments, or other external copies. It also does not unschedule events or remove every possible WordPress metadata item.

## No-verification commands

No WP-CLI or `wp-env` command is presented as an executed run here. The repository does not contain a versioned `.wp-env.json`, and the `@wordpress/env` dependency is not fixed in the package metadata. Any future command such as `npx wp-env start` or `npx wp-env run cli wp ...` must be treated as **No verification** until it is actually run in an authorized environment.
