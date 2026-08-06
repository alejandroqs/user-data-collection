# Architecture

## Status

The component and lifecycle descriptions below are **Verified** from `user-data-collection.php` and the ten `includes/class-udc-*.php` files. The codebase-memory graph is useful for discovery but recognizes only part of the class inventory; direct source is authoritative for this document.

## Entrypoint and lifecycle

`user-data-collection.php` defines plugin constants, manually requires ten classes, registers activation and deactivation callbacks, and attaches `udc_init_plugin()` to `plugins_loaded`.

On activation, `UDC_Activator::activate()` calls `dbDelta`, stores `UDC_DB_VERSION`, and calls the three scheduler methods. On later loads, `udc_init_plugin()` calls the activator when the stored database version differs. It then instantiates `UDC_i18n`, `UDC_Shortcode`, `UDC_Backup`, `UDC_GDrive`, and `UDC_Email_Sync`. In an administrative request it also instantiates `UDC_Admin`, `UDC_Ajax`, and `UDC_Settings`.

Deactivation calls `UDC_Backup::clear_cron()`, `UDC_GDrive::clear_cron()`, and `UDC_Email_Sync::clear_cron()`. There is no uninstall flow in the current source.

## Components

| Component | Verified responsibility |
| --- | --- |
| `UDC_Activator` | Creates or updates `{prefix}udc_submissions` with `dbDelta`, stores the schema version, and requests scheduled events. |
| `UDC_Shortcode` | Renders `[udc_contact_form]`, registers public and authenticated `admin_post` handlers, applies the nonce-valid attempt budget, validates submitted fields, and inserts rows. |
| `UDC_List_Table` | Extends `WP_List_Table`; displays filtered, paginated submissions with an allowlist for sortable columns and row actions. |
| `UDC_Admin` | Creates the Submissions, Backups, and Settings screens; renders list/detail views and inline confirmation JavaScript. |
| `UDC_Ajax` | Confirms or unconfirms a submission through authenticated AJAX actions. |
| `UDC_Backup` | Creates local JSON snapshots, rotates local files, restores missing rows, accepts uploaded JSON, and exposes administrative AJAX actions. |
| `UDC_Settings` | Registers integration and design options and exposes the global Delete All Data action. |
| `UDC_GDrive` | Creates JWT credentials from the configured service-account JSON, uploads local JSON files, lists Drive files, and rotates the configured folder. |
| `UDC_Email_Sync` | Sends the newest local JSON file through `wp_mail` and registers the monthly scheduler. |
| `UDC_i18n` | Loads the text domain and optionally registers and resolves Polylang strings. |

## Hooks and flows

| Hook or registration | Source symbol | Flow |
| --- | --- | --- |
| `register_activation_hook` | `user-data-collection.php` | Calls `UDC_Activator::activate()`. |
| `plugins_loaded` | `udc_init_plugin()` | Updates the schema when needed and instantiates services. |
| `admin_menu` | `UDC_Admin::add_admin_menu()` | Adds the three administrative screens. It constructs additional `UDC_Backup` and `UDC_Settings` objects for submenu callbacks. |
| `admin_post_udc_submit_form` and `admin_post_nopriv_udc_submit_form` | `UDC_Shortcode` | Receives the frontend form and writes a submission. |
| `wp_ajax_udc_confirm_submission` and `wp_ajax_udc_unconfirm_submission` | `UDC_Ajax` | Changes `is_confirmed` after capability and nonce checks. |
| `udc_daily_backup_action` | `UDC_Backup` | Creates a local JSON snapshot. |
| `udc_weekly_gdrive_sync_action` | `UDC_GDrive` | Attempts a Drive synchronization when enabled. |
| `udc_monthly_email_sync_action` | `UDC_Email_Sync` | Attempts an email backup when enabled. |
| `cron_schedules` | `UDC_Activator`, `UDC_GDrive`, `UDC_Email_Sync` | Registers custom weekly and monthly intervals before scheduling. |
| `admin_init` | `UDC_Settings` | Registers typed options with defaults and sanitization callbacks. |
| `init` | `UDC_i18n` | Registers Polylang strings when Polylang is available. |

The public form flows into the custom table. Administrative list/detail views and confirmation actions read or update that table. The table can then flow into local JSON, Google Drive, and email attachments. See [Data, security, and privacy](data-security-privacy.md) and [Operations](operations.md).

## Dependencies and current implementation characteristics

The plugin depends on WordPress APIs, the WordPress database abstraction, filesystem access to the uploads directory, WP-Cron, `wp_mail`, OpenSSL for JWT signing, and optional Polylang and Google Drive HTTP endpoints. It does not bundle a PHP library.

**Verified limitation:** `UDC_Backup` and `UDC_Settings` are instantiated during plugin initialization and again from `UDC_Admin::add_admin_menu()`. Their constructors register hooks, so administrative requests can register duplicate effects.

**Verified limitation:** The form, admin actions, and settings pages emit inline CSS or JavaScript. This increases coupling to the rendered page and makes a strict content-security-policy and independent asset testing harder.
