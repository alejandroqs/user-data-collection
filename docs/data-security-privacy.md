# Data, security, and privacy

This document describes current source behavior. It is not a legal assessment and does not certify GDPR compliance or any other regulatory status.

## Data categories

`UDC_Activator::activate()` defines the `{prefix}udc_submissions` table with:

- identity and contact: first name, last name, date of birth, address, city, postal code, and phone;
- health-related answers: general health, treatment, blood-thinning medication, allergies, and pregnancy or breastfeeding;
- appointment and service details: appointment date, time, and piercing location;
- consent and operational state: `liability_accepted`, `is_confirmed`, generated `id`, and `created_at`.

The liability field is stored as a boolean/tinyint. The current schema does not store the presented consent text, consent version, or form language alongside the acceptance.

## Data destinations

The current flow is:

```text
Public form -> custom database table -> local JSON backup -> optional Google Drive or email attachment
```

`UDC_Backup::create_backup()` serializes all rows to JSON under the uploads directory. `UDC_GDrive::sync_backups()` can upload those files to a configured Drive folder. `UDC_Email_Sync::send_backup()` can attach the newest local JSON file to an email. These destinations create copies outside the primary table and may have different access, retention, and deletion controls.

## Existing controls

**Verified:** the form and administrative AJAX handlers use WordPress nonces. Administrative endpoints check `manage_options`. Database writes use `$wpdb->insert()` or `$wpdb->update()` in the relevant classes, and many administrative outputs use WordPress escaping functions. Sortable list columns are constrained by an allowlist in `UDC_List_Table`.

These are source-level controls, not evidence of complete security or privacy compliance.

## Known limitations

- `UDC_Shortcode::handle_submission()` sanitizes fields but only requires last name, first name, and liability acceptance on the server. It does not provide complete semantic validation for dates, time, phone, or all other required browser fields.
- The public form registers an unauthenticated `admin_post_nopriv_*` handler. The source contains no rate limit, honeypot, or other anti-abuse control. A nonce is not an anti-spam mechanism.
- Local JSON backups are stored under `wp-content/uploads/udc-backups/`. `UDC_Backup::secure_directory()` writes an `.htaccess` rule and `index.php`, but `.htaccess` protection depends on the web server and does not by itself establish encryption or controlled access.
- Restore and upload paths accept JSON after extension and parse checks. They do not enforce a file-size limit, a complete schema, a field allowlist, field formats, or a transaction. `UDC_Backup::insert_backup_data()` inserts rows with an `id` that is not already present and does not validate the whole row before insertion.
- `UDC_Settings::register_settings()` registers the settings without `sanitize_callback` arguments. Design values are later interpolated into inline CSS, so escaping at output is not equivalent to CSS-value validation.
- `udc_gdrive_json` stores the service-account JSON and `UDC_GDrive::get_token()` reads its `client_email` and `private_key` from that option. The current plugin does not provide a separate secret-management or encryption-at-rest layer.
- A source search of the current plugin found no `wp_privacy_*` exporter or eraser registrations. Individual WordPress privacy export and erasure workflows are therefore not established by this plugin.
- `UDC_Settings::ajax_delete_all_data()` deletes rows from the local table, removes local JSON files, and deletes selected plugin options. It does not delete Google Drive files, email attachments, or copies retained by external systems. It also does not constitute a documented individual-subject erasure process.
- The source does not define a submission-retention policy. Local and external rotation behavior is operational behavior, not a complete privacy-retention policy.

## Requirements for future changes

Treat health answers, identity, consent, backups, email attachments, Drive copies, and credentials as sensitive. Document any new destination, column, deletion path, validation rule, or access rule here and in [Operations](operations.md) where operational behavior changes. Keep legal conclusions separate from implementation evidence.
