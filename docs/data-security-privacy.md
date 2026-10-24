# Data, security, and privacy

**Answers:** Which sensitive data is processed, where it goes, which source-level controls exist, and which privacy or security capabilities are absent or unverified.

**Read when:** Changing fields, schema, validation, permissions, consent, credentials, data destinations, retention, export, or deletion.

**Canonical sources:** `UDC_Activator`, `UDC_Shortcode`, `UDC_Validation`, `UDC_Backup`, `UDC_Settings`, `UDC_GDrive`, and `UDC_Email_Sync`.

**Update when:** A data category, destination, validation rule, access rule, consent record, credential, retention rule, or deletion path changes.

**Out of scope:** Operational procedures and release packaging; use [Operations](operations.md) and [Release](release.md).

This document describes current source behavior. It is not a legal assessment and does not certify GDPR compliance or any other regulatory status.

## Data categories

`UDC_Activator::activate()` defines the `{prefix}udc_submissions` table with:

- identity and contact: first name, last name, date of birth, address, city, postal code, and phone;
- health-related answers: general health, treatment, blood-thinning medication, allergies, and pregnancy or breastfeeding;
- appointment and service details: appointment date, time, and piercing location;
- consent and operational state: `liability_accepted`, generated `id`, and `created_at`.

The liability field is stored as a boolean/tinyint. The current schema does not store the presented consent text, consent version, or form language alongside the acceptance.

## Retired approval state

**Verified from source:** version 1.6.0 omits is_confirmed from the schema, submissions, new backups, and administrative views. UDC_Activator::activate() inspects the table after dbDelta and drops only that retired column if present. If inspection or deletion fails, the stored schema version is not advanced, allowing a later load to retry. Existing submission rows and all other fields are retained; prior approval values are discarded. Historical backups keep their original contents, but restore validates and discards the retired boolean before insertion. See [Operations](operations.md) for accepted backup schemas.

**Unknown:** this upgrade has not been executed against a live WordPress database.

## Data destinations

The current flow is:

```text
Public form -> custom database table -> local JSON backup -> optional Google Drive or email attachment
```

`UDC_Backup::create_backup()` serializes all rows to JSON under the uploads directory. `UDC_GDrive::sync_backups()` can upload those files to a configured Drive folder. `UDC_Email_Sync::send_backup()` can attach the newest local JSON file to an email. These destinations create copies outside the primary table and may have different access, retention, and deletion controls.

## Existing controls

**Verified:** the form and administrative AJAX handlers use WordPress nonces. Administrative endpoints check `manage_options`. Submission and restore writes use `$wpdb->insert()`, and many administrative outputs use WordPress escaping functions. Sortable list columns are constrained by an allowlist in `UDC_List_Table`.

These are source-level controls, not evidence of complete security or privacy compliance.

`UDC_Shortcode::handle_submission()` counts attempts only after nonce, scalar structure, checkbox, required-field, length, date, and time validation, using a filterable transient lifetime of ten minutes per observed `REMOTE_ADDR`. The transient key uses an HMAC of that address; the raw address is not stored or logged. The default budget is five attempts. Each counted attempt resets the transient lifetime; this is an inactivity-based expiry, not a fixed window. Rejected field values do not consume this budget. The transient counter is non-atomic and is not protection against distributed sources. Counter failures fail closed, and an exceeded budget returns HTTP 429.

`UDC_Settings::register_settings()` supplies explicit setting types, defaults, and sanitizers. Email values, Drive folder IDs, service-account JSON structure, and design color tokens are validated before storage. An administrator can clear the configured credentials by submitting an empty or whitespace-only value; while configured, the service-account JSON and private key remain stored in a WordPress option pending an operator decision about secret storage.

## Known limitations

- The public form remains unauthenticated and uses a nonce for request integrity, not authentication. The attempt budget is an abuse-control measure, not proof of identity or a complete anti-spam system.
- Local JSON backups are stored under `wp-content/uploads/udc-backups/`. `UDC_Backup::secure_directory()` writes an `.htaccess` rule and `index.php`, but `.htaccess` protection depends on the web server and does not by itself establish encryption or controlled access.
- Restore and upload paths are bounded to 10 MiB and 10,000 rows, use an allowlisted schema and field validation, reject mixed current/legacy schemas, and use a transactional additive import when the table is InnoDB. Duplicate positive IDs remain skipped.
- Design values are restricted to the supported color-token grammar before their escaped inline CSS output is generated.
- `udc_gdrive_json` stores the service-account JSON and `UDC_GDrive::get_token()` reads its `client_email` and `private_key` from that option. The current plugin does not provide a separate secret-management or encryption-at-rest layer.
- A source search of the current plugin found no `wp_privacy_*` exporter or eraser registrations. Individual WordPress privacy export and erasure workflows are therefore not established by this plugin.
- `UDC_Settings::ajax_delete_all_data()` deletes rows from the local table, removes local JSON files, and deletes selected plugin options. It does not delete Google Drive files, email attachments, or copies retained by external systems. It also does not constitute a documented individual-subject erasure process.
- The source does not define a submission-retention policy. Local and external rotation behavior is operational behavior, not a complete privacy-retention policy.

## Requirements for future changes

Treat health answers, identity, consent, backups, email attachments, Drive copies, and credentials as sensitive. Document any new destination, column, deletion path, validation rule, or access rule here and in [Operations](operations.md) where operational behavior changes. Keep legal conclusions separate from implementation evidence.
